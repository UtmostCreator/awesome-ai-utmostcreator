<?php

declare(strict_types=1);

require_once __DIR__ . '/markers.php';
require_once __DIR__ . '/project-yaml.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/packs.php';
require_once __DIR__ . '/fs-writers.php';
require_once __DIR__ . '/plan-guards.php';
require_once __DIR__ . '/install-lock.php';
require_once __DIR__ . '/gitignore.php';
require_once __DIR__ . '/user-sections.php';
require_once __DIR__ . '/placeholders.php';
require_once __DIR__ . '/conflict-channels.php';
require_once __DIR__ . '/project-values.php';
require_once __DIR__ . '/executor.php';
require_once __DIR__ . '/planner.php';
require_once __DIR__ . '/manifest.php';
require_once __DIR__ . '/docs.php';
require_once __DIR__ . '/toolchain.php';
require_once __DIR__ . '/script-runner.php';
require_once __DIR__ . '/copilot-agent-renderer.php';
require_once __DIR__ . '/claude-agent-renderer.php';
require_once __DIR__ . '/claude-settings-merge.php';
require_once __DIR__ . '/backup.php';
require_once __DIR__ . '/migrations.php';

function aiInstallerRun(array $argv): int
{
    if (aiInstallerWantsHelp($argv)) {
        aiInstallerUsage();
        return 0;
    }

    $config = aiInstallerParseArgs($argv);
    if (($config['stackDetectOnly'] ?? false) === true) {
        require_once __DIR__ . '/../commands/stack_selection.php';
        $resolved = aiStackSelectionResolve((string) $config['targetRoot'], $config);
        fwrite(STDOUT, aiStackSelectionSummary($resolved) . PHP_EOL);
        return 0;
    }
    aiInstallerAssertLockCompatible((string) $config['targetRoot']);

    // Resolve stack detection/selection BEFORE any kit content is copied into the target:
    // detecting against the target's pre-existing files only avoids falsely attributing the
    // kit's own shipped files (e.g. .github/workflows/*.yml) to the target project's stack.
    require_once __DIR__ . '/../commands/stack_selection.php';
    $stackResolved = aiStackSelectionResolve((string) $config['targetRoot'], $config);

    aiInstallerBootstrapPath();

    aiInstallerLog('source root: ' . $config['sourceRoot']);
    aiInstallerLog('target root: ' . $config['targetRoot']);
    aiInstallerLog('profile: ' . $config['profile']);
    aiInstallerLog('runtime: ' . $config['runtime']);

    aiInstallerAssertAllowedTarget($config);

    $registry = aiInstallerPackRegistry();
    $registryErrors = aiInstallerValidatePackRegistry($registry);
    if ($registryErrors !== []) {
        throw new RuntimeException('invalid pack registry: ' . implode('; ', $registryErrors));
    }
    $packs = aiInstallerResolveSelectedPacks($config, $registry);

    $dep = aiInstallerPackToolRequirements($packs);
    $missingRequired = [];
    $missingOptional = [];
    foreach ($dep['required'] as $tool) {
        if (!aiInstallerCommandExists((string) $tool)) {
            $missingRequired[] = $tool;
        }
    }
    foreach ($dep['optional'] as $tool) {
        if (!aiInstallerCommandExists((string) $tool)) {
            $missingOptional[] = $tool;
        }
    }
    if ($missingRequired !== [] && ($config['dependencyMode'] ?? 'strict') === 'strict') {
        $hint = aiInstallerMissingToolsHint($missingRequired);
        throw new RuntimeException(
            'missing required tools for selected packs: ' . implode(', ', $missingRequired)
            . PHP_EOL . $hint
        );
    }

    $plan = aiInstallerBuildPlan($config, $registry, $packs);
    aiInstallerAssertPlanSourcesExist($config, $plan);

    // PathGuard: reject any plan target that escapes the target root via traversal,
    // absolute paths, or symlinked parent directories. Runs before any write.
    aiInstallerAssertSafePlanTargets((string) $config['targetRoot'], $plan);

    // Case-collision guard: two distinct targets that differ only by case would clobber
    // each other on case-insensitive filesystems (macOS/Windows). Fail fast and explicitly.
    aiInstallerAssertNoCaseCollisions($plan);

    // Adopt-or-conflict gate: files flagged never_auto_merge (e.g. opencode.jsonc) that
    // already exist and differ from the kit version must not be silently skipped or merged.
    // Fail fast before any writes so the install is all-or-nothing for the user's config.
    if (!$config['dryRun']) {
        aiInstallerAssertNoForeignConflicts($plan);
    }

    // Single-process guard: only one writing install may run per target at a time.
    $installLock = null;
    $signalState = null;
    $backupInfo = null;
    if (!$config['dryRun']) {
        $installLock = aiInstallerAcquireInstallLock((string) $config['targetRoot']);
        $signalState = aiInstallerInstallSignalHandlers();
    }
    try {

    // Ensure runtime/generated state is gitignored in the target repo. Apply
    // flow only; skip on dry-run. This must happen before backups/conflicts are
    // written so transient safety artifacts are ignored before they exist.
    if (!$config['dryRun']) {
        aiInstallerEnsureGitignoreEntries($config['targetRoot'], [
            '.ai/backups/',
            '.ai/logs/',
            '.ai-backups/',
            '.ai-logs/',
            '.ai/conflicts/',
            '.ai/templates-new/',
            '.ai/local-manifest.json',
            '.ai/install.lock',
            '.repomix-context/',
            '*.tmp',
            '*.bak',
            'docs/ai/generated/',
        ]);
        aiInstallerAssertGitignoreEffective($config['targetRoot'], [
            '.ai/backups/install-ai-kit-probe',
            '.ai/conflicts/probe',
            'docs/ai/generated/probe.json',
        ]);
    }

    if (!$config['dryRun'] && ($config['backup'] ?? false)) {
        $backupInfo = aiInstallBackupCreate($config['targetRoot'], $plan, $config['sourceRoot'], 'install-ai-kit');
        aiInstallerLog('backup created: ' . $backupInfo['backup_dir']);
    }

    // Capture any user-owned `<!-- BEGIN ai-kit:user -->` blocks from existing managed files
    // before the copy overwrites them, so they can be re-injected after rendering.
    $userSections = $config['dryRun'] ? [] : aiInstallerCaptureUserSections($config['targetRoot'], $plan);

    $executed = aiInstallerExecutePlan($config, $plan);
    $applied = $executed['applied'];
    $templateRefreshes = $executed['template_refreshes'];

    $placeholderStatus = [
        'unresolved_required' => [],
        'unresolved_optional' => [],
        'resolved_values_hash' => 'unknown',
    ];

    if (!$config['dryRun']) {
        if (is_array($backupInfo) && is_string($backupInfo['backup_id'] ?? null)) {
            aiInstallBackupUpdateState($config['targetRoot'], (string) $backupInfo['backup_id'], 'applying');
        }
        aiInstallerEnsureProjectValuesFile($config['targetRoot'], $config['projectName']);
        aiInstallerApplyStackSelectionToProjectValues((string) $config['targetRoot'], $stackResolved);
        aiInstallerWriteStackDetectionEvidence((string) $config['targetRoot'], $stackResolved);
        aiInstallerApplyPlaceholders(
            $config['targetRoot'],
            $config['projectName'],
            $plan,
            aiInstallerIsSelfTargetInstall($config)
        );
        aiInstallerEnsureAgentsMarkedSectionForSkippedUserFile($config['targetRoot'], $plan);
        // Re-inject preserved user blocks into freshly rendered files (byte-for-byte).
        aiInstallerRestoreUserSections($config['targetRoot'], $userSections);
        // Recompile the dependency-free command policy from the target's tiers + local overrides
        // so .ai/project.yml policy.allow takes effect (and downgrade attempts fail the install).
        aiInstallerCompileCommandPolicy((string) $config['targetRoot']);
        $placeholderStatus = aiInstallerCollectPlaceholderStatus($config['targetRoot']);

        // `full` is an alias of full-governance, so it inherits strict placeholder
        // gating. standard/creator alias dual (non-strict) and basic/agents-only are
        // intentionally non-strict, matching the profiles they alias. `claude` is
        // deliberately non-strict too, matching its single-runtime siblings `copilot`/
        // `opencode` (neither of which is in this list either) — decided, not omitted by
        // oversight (Claude adapter parity plan, P1-6).
        $strictProfiles = ['guarded', 'accelerated', 'full-governance', 'full'];
        if (in_array((string) $config['profile'], $strictProfiles, true)
            && $placeholderStatus['unresolved_required'] !== []
            && !($config['allowPlaceholders'] ?? false)
        ) {
            throw new RuntimeException('unresolved required placeholders found for strict profile; rerun with --allow-placeholders only when intentional');
        }

        $manifest = aiInstallerBuildManifest($config, $packs, $plan);
        $manifest['migrations'] = aiInstallerRunMigrations(
            (string) $config['sourceRoot'],
            (string) $config['targetRoot'],
            (string) ($manifest['package']['installed_version'] ?? 'unknown'),
            (string) ($manifest['installer_version'] ?? 'unknown')
        );
        $manifest['placeholders'] = $placeholderStatus;
        aiInstallerWriteManifest($config['targetRoot'], $manifest);
        // P5-b: informational-only summary for the user. NOT a write allowlist — the
        // canonical manifest files{} and the lock remain authoritative for all writes.
        aiInstallerWriteLocalManifest($config['targetRoot'], $manifest);

        if (is_array($backupInfo) && is_string($backupInfo['backup_id'] ?? null)) {
            aiInstallBackupRecordAfter($config['targetRoot'], (string) $backupInfo['backup_id'], $plan, $config['sourceRoot'], 'applied');
        }

        if (!empty($config['verifyAfter'])) {
            aiInstallerRunPostInstallVerification($config['targetRoot']);
            if (is_array($backupInfo) && is_string($backupInfo['backup_id'] ?? null)) {
                aiInstallBackupUpdateState($config['targetRoot'], (string) $backupInfo['backup_id'], 'validated');
            }
        }
    }

    aiInstallerLog($config['dryRun'] ? 'dry-run complete; no files changed' : 'install complete');
    aiInstallerLog('actions: ' . count($plan));
    aiInstallerLog('selected packs: ' . implode(', ', $packs));
    if ($missingRequired !== []) {
        aiInstallerLog('missing required tools: ' . implode(', ', $missingRequired));
    }
    if ($missingOptional !== []) {
        aiInstallerLog('missing optional tools: ' . implode(', ', $missingOptional));
    }
    if (!$config['dryRun']) {
        $unresolvedRequired  = $placeholderStatus['unresolved_required']  ?? [];
        $unresolvedOptional  = $placeholderStatus['unresolved_optional']  ?? [];

        if ($unresolvedRequired !== [] || $unresolvedOptional !== []) {
            aiInstallerLog('');
            aiInstallerLog('⚠  PLACEHOLDER RESOLUTION REQUIRED ⚠');
            aiInstallerLog('The following files contain tokens that MUST be replaced with real project values.');
            aiInstallerLog('Do not run AI agents in write mode until required placeholders are resolved.');
            aiInstallerLog('');
            aiInstallerLog('  PRIMARY FILE TO EDIT:');
            aiInstallerLog('    docs/ai/project-context.md   ← fill every <PLACEHOLDER> with your project facts');
            aiInstallerLog('');

            $instructionFiles = [
                '.github/instructions/frontend.instructions.md'  => '<FRONTEND_PATH_GLOB>',
                '.github/instructions/testing.instructions.md'   => '<TEST_PATH_GLOB>',
                'scripts/ai/pre-tool-use.sh'                     => 'project-specific rules',
            ];
            $extraFiles = [];
            foreach ($instructionFiles as $rel => $token) {
                $abs = rtrim((string) $config['targetRoot'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                if (is_file($abs) && preg_match('/<[A-Z_]+>/', (string) file_get_contents($abs))) {
                    $extraFiles[] = $rel . '  ← replace ' . $token;
                }
            }
            if ($extraFiles !== []) {
                aiInstallerLog('  ADDITIONAL FILES WITH PLACEHOLDERS:');
                foreach ($extraFiles as $line) {
                    aiInstallerLog('    ' . $line);
                }
                aiInstallerLog('');
            }

            if ($unresolvedRequired !== []) {
                aiInstallerLog('  REQUIRED tokens (must resolve before write-capable AI runs):');
                foreach ($unresolvedRequired as $token) {
                    aiInstallerLog('    ' . $token);
                }
                aiInstallerLog('');
            }

            if ($unresolvedOptional !== []) {
                aiInstallerLog('  OPTIONAL tokens (resolve to improve AI quality, safe to leave as-is initially):');
                foreach (array_slice($unresolvedOptional, 0, 12) as $token) {
                    aiInstallerLog('    ' . $token);
                }
                if (count($unresolvedOptional) > 12) {
                    aiInstallerLog('    ... and ' . (count($unresolvedOptional) - 12) . ' more — see docs/ai/project-context-placeholders.md');
                }
                aiInstallerLog('');
            }

            aiInstallerLog('  Full placeholder guide: docs/ai/project-context-placeholders.md');
            aiInstallerLog('  Post-install checklist: docs/ai/POST-INSTALL.md');
            aiInstallerLog('');
        }
    }

    // Preserved-template pointer: when a skip-if-exists template (notably .vscode/settings.json)
    // was kept because the user already had the file, the kit's recommended version is surfaced
    // under .ai/templates-new/<path>. Point the user to it so they can open it and borrow rules;
    // the user's own file is never overwritten.
    if ($templateRefreshes !== []) {
        $vscodeRefresh = null;
        foreach ($templateRefreshes as $refreshRel) {
            if (str_contains((string) $refreshRel, '.vscode/settings.json')) {
                $vscodeRefresh = (string) $refreshRel;
                break;
            }
        }
        aiInstallerLog('');
        aiInstallerLog('ℹ  PRESERVED YOUR EXISTING FILES — recommended versions saved for review:');
        if ($vscodeRefresh !== null) {
            aiInstallerLog('   Your .vscode/settings.json was kept as-is (not overwritten).');
            aiInstallerLog('   The kit\'s recommended VS Code settings are here — open it and borrow what you want:');
            aiInstallerLog('     ' . $vscodeRefresh);
        }
        foreach ($templateRefreshes as $refreshRel) {
            if ((string) $refreshRel === $vscodeRefresh) {
                continue;
            }
            aiInstallerLog('     ' . $refreshRel);
        }
        aiInstallerLog('');
    }

    // Agent-dependency warning: agents reference scripts/ai/*.sh in their permission
    // allowlists. If an agent pack is selected without scripts-pack, the installed
    // agents reference commands that do not exist. Detection is shared (packs.php).
    $installWarnings = aiInstallerAgentDependencyWarnings($packs);
    foreach ($installWarnings as $warning) {
        aiInstallerLog('WARNING: ' . $warning);
    }
    if ($installWarnings !== []) {
        aiInstallerLog('');
    }

    aiInstallerLog('next steps:');
    aiInstallerLog('1) ✏  Edit docs/ai/project-context.md — replace every <PLACEHOLDER> with real project facts');
    aiInstallerLog('2) ✏  Edit .github/instructions/frontend.instructions.md — replace <FRONTEND_PATH_GLOB>');
    aiInstallerLog('3) ✏  Edit .github/instructions/testing.instructions.md  — replace <TEST_PATH_GLOB>');
    aiInstallerLog('4) ✔  run php tools/ai/validate-ai-config.php');
    aiInstallerLog('5) ✔  run php tools/ai/validate-install-surface.php --strict');
    aiInstallerLog('6) ✔  run php tools/ai/validate-ai-catalog.php (if catalog files changed)');
    aiInstallerLog('7)    run bash scripts/ai/repomix-context-tree.sh analyze . (optional context packing)');
    aiInstallerLog('8)    run php tools/ai/ai.php advisor --all (optional project advisor)');
    aiInstallerLog('');
    aiInstallerLog('Full post-install guide: docs/ai/POST-INSTALL.md');

    if (($config['outputJson'] ?? '') !== '') {
        $payload = [
            'status' => 'ok',
            'profile' => $config['profile'],
            'runtime' => $config['runtime'],
            'dry_run' => (bool) $config['dryRun'],
            'target' => $config['targetRoot'],
            'source' => $config['sourceRoot'],
            'selected_packs' => $packs,
            'plan_actions' => count($plan),
            'applied_actions' => count($applied),
            'missing_required_tools' => $missingRequired,
            'missing_optional_tools' => $missingOptional,
            'backup' => $backupInfo,
            'placeholders' => $placeholderStatus,
            'template_updates' => $templateRefreshes,
            'warnings' => $installWarnings,
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents((string) $config['outputJson'], $json);
    }

        return 0;
    } catch (Throwable $e) {
        if (!$config['dryRun'] && is_array($backupInfo) && is_string($backupInfo['backup_id'] ?? null)) {
            $backupId = (string) $backupInfo['backup_id'];
            aiInstallBackupUpdateState((string) $config['targetRoot'], $backupId, 'failed', $e->getMessage());
            try {
                $rollback = aiInstallBackupRollback((string) $config['targetRoot'], $backupId, true, true);
                $rolledBack = (($rollback['status'] ?? null) === 'ok') && (($rollback['conflicts'] ?? []) === []);
                aiInstallBackupUpdateState((string) $config['targetRoot'], $backupId, $rolledBack ? 'failed_rolled_back' : 'failed_recoverable', $e->getMessage());
                aiInstallBackupAppendAudit((string) $config['targetRoot'], $rolledBack ? 'install_failed_auto_rollback' : 'install_failed_recoverable', [
                    'backup_id' => $backupId,
                    'reason' => $e->getMessage(),
                    'rollback_status' => $rollback['status'] ?? 'unknown',
                    'rollback_conflicts' => $rollback['conflicts'] ?? [],
                ]);
            } catch (Throwable $rollbackError) {
                aiInstallBackupUpdateState((string) $config['targetRoot'], $backupId, 'failed_recoverable', $rollbackError->getMessage());
                aiInstallBackupAppendAudit((string) $config['targetRoot'], 'install_failed_recoverable', [
                    'backup_id' => $backupId,
                    'reason' => $e->getMessage(),
                    'rollback_error' => $rollbackError->getMessage(),
                ]);
            }
        } elseif (!$config['dryRun']) {
            aiInstallBackupMarkRecoverableNoBackup((string) $config['targetRoot'], $e->getMessage());
        }
        throw $e;
    } finally {
        if (is_array($signalState)) {
            aiInstallerRestoreSignalHandlers($signalState);
        }
        if ($installLock !== null) {
            aiInstallerReleaseInstallLock($installLock);
        }
    }
}

/** @param list<string> $command @return array{stdout:string,stderr:string,exit:int} */
function aiInstallerRunTargetCommand(string $targetRoot, array $command): array
{
    $escaped = implode(' ', array_map('escapeshellarg', $command));
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($escaped, $descriptors, $pipes, $targetRoot);
    if (!is_resource($process)) {
        throw new RuntimeException('could not start post-install verification');
    }
    fclose($pipes[0]);
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
}

function aiInstallerRunPostInstallVerification(string $targetRoot): void
{
    $validatorRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
    if ($validatorRoot === false) {
        throw new RuntimeException('could not resolve validator root for post-install verification');
    }

    $commands = [
        [PHP_BINARY, $validatorRoot . DIRECTORY_SEPARATOR . 'validate-ai-config.php', '--target=' . $targetRoot],
        [PHP_BINARY, $validatorRoot . DIRECTORY_SEPARATOR . 'validate-install-surface.php', '--strict', '--target=' . $targetRoot],
        [PHP_BINARY, $validatorRoot . DIRECTORY_SEPARATOR . 'validate-ai-catalog.php', '--target=' . $targetRoot],
    ];

    foreach ($commands as $command) {
        $verify = aiInstallerRunTargetCommand($targetRoot, $command);
        if ($verify['stdout'] !== '') {
            fwrite(STDOUT, $verify['stdout']);
        }
        if ($verify['stderr'] !== '') {
            fwrite(STDERR, $verify['stderr']);
        }
        if ($verify['exit'] !== 0) {
            throw new RuntimeException('post-install verification failed; inspect target validator output');
        }
    }
}

/**
 * Recompile the dependency-free command policy in the target repo when the necessary inputs are
 * present (tiers file + hooks scripts dir). Honors .ai/project.yml policy.allow[] via the compiler
 * and re-throws downgrade/wildcard violations so a malicious local override fails the install.
 * No-ops silently when the target did not receive the policy/hooks surfaces.
 */
function aiInstallerCompileCommandPolicy(string $targetRoot): void
{
    $tiers = $targetRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'command-policy.tiers.yaml';
    $outDir = $targetRoot . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'hooks' . DIRECTORY_SEPARATOR . 'scripts';
    if (!is_file($tiers) || !is_dir($outDir)) {
        return;
    }

    $compiler = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'compile-command-policy.php';
    if (!is_file($compiler)) {
        return;
    }
    require_once $compiler;

    $out = $outDir . DIRECTORY_SEPARATOR . 'command-policy.compiled.sh';
    $projectValues = $targetRoot . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'project.yml';

    // The compiler prints its own OK/ERROR lines; capture them so installer logging stays clean,
    // and surface the captured detail only when compilation fails.
    ob_start();
    $exit = aiPolicyCompileMain([
        'compile-command-policy.php',
        '--in=' . $tiers,
        '--out=' . $out,
        '--project-values=' . $projectValues,
    ]);
    $compilerOutput = trim((string) ob_get_clean());

    if ($exit !== 0) {
        throw new RuntimeException('command policy compilation failed (check .ai/project.yml policy.allow for downgrade/wildcard violations)'
            . ($compilerOutput !== '' ? ': ' . $compilerOutput : ''));
    }

    aiInstallerLog('compiled command policy: ' . str_replace($targetRoot . DIRECTORY_SEPARATOR, '', $out));
}

function aiInstallerDetectProjectType(string $targetRoot): string
{
    if (is_file($targetRoot . DIRECTORY_SEPARATOR . 'composer.json')) {
        return 'php project';
    }
    if (is_file($targetRoot . DIRECTORY_SEPARATOR . 'package.json')) {
        return 'node project';
    }
    if (is_file($targetRoot . DIRECTORY_SEPARATOR . 'go.mod')) {
        return 'go project';
    }
    return 'repository';
}

function aiInstallerCollectActivePaths(string $targetRoot): string
{
    $gitDir = $targetRoot . DIRECTORY_SEPARATOR . '.git';
    if (!is_dir($gitDir)) {
        return '_root';
    }
    $output = [];
    exec('git -C ' . escapeshellarg($targetRoot) . ' ls-files', $output);
    if ($output === []) {
        return '_root';
    }
    $tops = [];
    foreach ($output as $line) {
        $parts = explode('/', $line);
        $tops[$parts[0] !== '' ? $parts[0] : '_root'] = true;
    }
    return implode(',', array_keys($tops));
}

function aiInstallerCreateBackup(string $targetRoot, array $plan): array
{
    $targets = [];
    $manifestPath = aiInstallerCanonicalManifestPath($targetRoot);
    if (is_file($manifestPath)) {
        $decoded = json_decode((string) file_get_contents($manifestPath), true);
        if (is_array($decoded) && is_array($decoded['files'] ?? null)) {
            foreach (array_keys($decoded['files']) as $target) {
                if (is_string($target) && $target !== '') {
                    $targets[$target] = true;
                }
            }
        }
    }

    foreach ($plan as $item) {
        if (($item['action'] ?? '') === 'SKIP_EXISTING_UNMANAGED' || ($item['action'] ?? '') === 'SKIP_PROTECTED_CORE' || ($item['action'] ?? '') === 'SKIP_IDENTICAL_EXISTING') {
            continue;
        }
        $target = (string) ($item['target'] ?? '');
        if ($target === '') {
            continue;
        }
        $targets[$target] = true;
    }

    foreach ([
        '.ai-install-manifest.json',
        'docs/ai/generated/install-manifest.json',
        'docs/ai/SETUP.md',
        'docs/ai/POST-INSTALL.md',
        'docs/ai/installed-files.md',
        'docs/ai/project-configuration.md',
        'docs/ai/available-packs.md',
        'docs/ai/generated/install-summary.md',
        'docs/ai/generated/install-instructions.md',
        'docs/ai/generated/install-instructions.json',
    ] as $target) {
        $targets[$target] = true;
    }

    $stamp = gmdate('Ymd\THis\Z');
    $backupId = $stamp . '-install';
    $backupDir = aiInstallerPrivateBackupDir($targetRoot, 'install', $stamp);
    $filesDir = $backupDir . DIRECTORY_SEPARATOR . 'files';
    aiInstallerMkdir($filesDir, aiInstallerPrivateDirMode());

    $entries = [];
    foreach (array_keys($targets) as $target) {
        $source = $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);
        if (!file_exists($source)) {
            continue;
        }

        $snapshot = $filesDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);
        aiInstallerSnapshotPath($source, $snapshot);
        $entries[] = [
            'path' => $target,
            'type' => is_dir($source) ? 'dir' : 'file',
        ];
    }

    $manifest = [
        'backup_id' => $backupId,
        'created_at' => gmdate('c'),
        'entry_count' => count($entries),
        'entries' => $entries,
    ];
    file_put_contents($backupDir . DIRECTORY_SEPARATOR . 'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

    return [
        'backup_id' => $backupId,
        'backup_dir' => aiInstallerPrivateBackupRel('install', $stamp),
        'entry_count' => count($entries),
    ];
}

function aiInstallerLog(string $message): void
{
    fwrite(STDOUT, '[install-ai-kit] ' . $message . PHP_EOL);
}
