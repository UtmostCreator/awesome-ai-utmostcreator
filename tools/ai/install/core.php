<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/packs.php';
require_once __DIR__ . '/planner.php';
require_once __DIR__ . '/manifest.php';
require_once __DIR__ . '/docs.php';
require_once __DIR__ . '/toolchain.php';
require_once __DIR__ . '/script-runner.php';
require_once __DIR__ . '/copilot-agent-renderer.php';
require_once __DIR__ . '/backup.php';
require_once __DIR__ . '/migrations.php';

function aiInstallerRun(array $argv): int
{
    $config = aiInstallerParseArgs($argv);
    if (($config['help'] ?? false) === true) {
        aiInstallerUsage();
        return 0;
    }
    aiInstallerAssertLockCompatible((string) $config['targetRoot']);

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
    if (!$config['dryRun']) {
        $installLock = aiInstallerAcquireInstallLock((string) $config['targetRoot']);
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
            '.ai-backups/install-ai-kit-probe',
            '.ai/conflicts/probe',
            'docs/ai/generated/probe.json',
        ]);
    }

    $backupInfo = null;
    if (!$config['dryRun'] && ($config['backup'] ?? false)) {
        $backupInfo = aiInstallBackupCreate($config['targetRoot'], $plan, $config['sourceRoot'], 'install-ai-kit');
        aiInstallerLog('backup created: ' . $backupInfo['backup_dir']);
    }

    // Capture any user-owned `<!-- BEGIN ai-kit:user -->` blocks from existing managed files
    // before the copy overwrites them, so they can be re-injected after rendering.
    $userSections = $config['dryRun'] ? [] : aiInstallerCaptureUserSections($config['targetRoot'], $plan);

    $applied = [];
    $seenDirTargets = [];
    foreach ($plan as $item) {
        if ($item['action'] === 'SKIP_EXISTING_UNMANAGED' || $item['action'] === 'SKIP_PROTECTED_CORE' || $item['action'] === 'SKIP_IDENTICAL_EXISTING') {
            aiInstallerLog('skip ' . $item['target'] . ' (' . strtolower($item['action']) . ')');
            continue;
        }
        if ($item['action'] === 'CONFLICT_FOREIGN') {
            // Surfaced and (for apply) already aborted by aiInstallerAssertNoForeignConflicts.
            // On dry-run we only report it; never write.
            aiInstallerLog('conflict ' . $item['target'] . ' (foreign; rerun with --adopt or resolve manually)');
            continue;
        }
        if ($config['dryRun']) {
            aiInstallerLog('plan ' . strtolower($item['type']) . ': ' . $item['source'] . ' -> ' . $item['target']);
            continue;
        }

        $src = $config['sourceRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $item['source']);
        $dest = $config['targetRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $item['target']);
        if ($item['type'] === 'file') {
            aiInstallerCopyFile($src, $dest);
        } elseif (($item['install_type'] ?? '') === 'copilot-agents') {
            $scriptsRoot = $config['targetRoot'] . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'ai';
            aiInstallerCopyDirAsCopilotAgents($src, $dest, $scriptsRoot);
        } elseif (($item['install_type'] ?? '') === 'opencode-agents') {
            aiInstallerCopyDirAsOpenCodeAgents($src, $dest);
        } elseif (($item['install_type'] ?? '') === 'skill-dirs') {
            aiInstallerCopyDirAsSkillDirs($src, $dest);
        } elseif (isset($item['rename_ext'])) {
            aiInstallerCopyDirWithRename($src, $dest, $item['rename_ext']);
        } else {
            $cleanFirst = !isset($seenDirTargets[$item['target']]);
            $seenDirTargets[$item['target']] = true;
            aiInstallerCopyDir($src, $dest, $cleanFirst);
        }
        $applied[] = $item;
        aiInstallerLog('copied ' . $item['type'] . ': ' . $item['target']);
    }

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

        $strictProfiles = ['guarded', 'accelerated', 'full-governance'];
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
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents((string) $config['outputJson'], $json);
    }

        return 0;
    } finally {
        if ($installLock !== null) {
            aiInstallerReleaseInstallLock($installLock);
        }
    }
}

/**
 * Acquire an exclusive per-target install lock at .ai/install.lock. Detects and reclaims a
 * stale lock (dead PID), but refuses to proceed when another live process holds it.
 *
 * @return array{path:string,handle:resource}
 */
function aiInstallerAcquireInstallLock(string $targetRoot)
{
    $dir = $targetRoot . DIRECTORY_SEPARATOR . '.ai';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $lockPath = $dir . DIRECTORY_SEPARATOR . 'install.lock';

    $handle = @fopen($lockPath, 'c+');
    if ($handle === false) {
        throw new RuntimeException('unable to open install lock: ' . $lockPath);
    }

    if (!flock($handle, LOCK_EX | LOCK_NB)) {
        $existing = trim((string) stream_get_contents($handle));
        fclose($handle);
        throw new RuntimeException('another install is already running for this target (.ai/install.lock held' . ($existing !== '' ? ': ' . $existing : '') . ')');
    }

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, 'pid=' . getmypid() . ' at=' . gmdate('c') . "\n");
    fflush($handle);

    return ['path' => $lockPath, 'handle' => $handle];
}

/** @param array{path:string,handle:resource} $lock */
function aiInstallerReleaseInstallLock(array $lock): void
{
    $handle = $lock['handle'] ?? null;
    if (is_resource($handle)) {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
    if (is_string($lock['path'] ?? null) && is_file($lock['path'])) {
        @unlink($lock['path']);
    }
}

function aiInstallerIsSelfTargetInstall(array $config): bool
{
    $sourceRoot = str_replace('\\', '/', (string) ($config['sourceRoot'] ?? ''));
    $targetRoot = str_replace('\\', '/', (string) ($config['targetRoot'] ?? ''));

    return $sourceRoot !== '' && $sourceRoot === $targetRoot;
}

function aiInstallerAssertPlanSourcesExist(array $config, array $plan): void
{
    $missing = [];
    foreach ($plan as $item) {
        if (in_array((string) ($item['action'] ?? ''), ['SKIP_EXISTING_UNMANAGED', 'SKIP_PROTECTED_CORE', 'SKIP_IDENTICAL_EXISTING'], true)) {
            continue;
        }

        $source = (string) ($item['source'] ?? '');
        $type = (string) ($item['type'] ?? 'file');
        $abs = (string) $config['sourceRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $source);
        $exists = $type === 'dir' ? is_dir($abs) : is_file($abs);
        if (!$exists) {
            $missing[] = $source . ' -> ' . (string) ($item['target'] ?? 'unknown');
        }
    }

    if ($missing !== []) {
        $sample = array_slice($missing, 0, 20);
        $suffix = count($missing) > count($sample) ? '; ... and ' . (count($missing) - count($sample)) . ' more' : '';
        throw new RuntimeException('install source surface is incomplete; missing selected pack source(s): ' . implode('; ', $sample) . $suffix);
    }
}

/**
 * Stop the install when any never_auto_merge target is in CONFLICT_FOREIGN state.
 *
 * @param list<array<string,mixed>> $plan
 */
function aiInstallerAssertNoForeignConflicts(array $plan): void
{
    $conflicts = [];
    foreach ($plan as $item) {
        if (($item['action'] ?? '') === 'CONFLICT_FOREIGN') {
            $conflicts[] = (string) ($item['target'] ?? '');
        }
    }

    if ($conflicts === []) {
        return;
    }

    $list = implode(', ', array_filter($conflicts));
    throw new RuntimeException(
        'install aborted: existing file(s) conflict with the kit and must not be auto-merged: ' . $list . '. '
        . 'These files are managed as adopt-or-conflict (e.g. opencode.jsonc). '
        . 'Review them, then rerun with --adopt to overwrite (a backup is recorded with --backup) '
        . 'or resolve the differences manually.'
    );
}

function aiInstallerAssertAllowedTarget(array $config): void
{
    $sourceRoot = str_replace('\\', '/', (string) ($config['sourceRoot'] ?? ''));
    $targetRoot = str_replace('\\', '/', (string) ($config['targetRoot'] ?? ''));

    if ($sourceRoot === '' || $targetRoot === '') {
        return;
    }

    $reservedExampleRoot = rtrim($sourceRoot, '/') . '/packages/ai-universal-rules/examples';

    if ($targetRoot === $reservedExampleRoot || str_starts_with($targetRoot . '/', $reservedExampleRoot . '/')) {
        throw new RuntimeException('installer target under packages/ai-universal-rules/examples is reserved; install into a dedicated external project directory instead');
    }

    // Git-root guard: refuse to install (and patch .gitignore) into a directory that is not
    // a git repository root, unless explicitly overridden. This prevents accidental writes
    // into arbitrary directories. Dry-run is exempt because it writes nothing.
    if (empty($config['dryRun']) && empty($config['allowNonGit'])) {
        $gitDir = rtrim((string) ($config['targetRoot'] ?? ''), '/\\') . DIRECTORY_SEPARATOR . '.git';
        if (!is_dir($gitDir) && !is_file($gitDir)) {
            throw new RuntimeException(
                'installer target is not a git repository root (no .git found at ' . $config['targetRoot'] . '). '
                . 'Run inside a git repo, or pass --allow-non-git to override.'
            );
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

function aiInstallerCollectPlaceholderStatus(string $targetRoot): array
{
    $required = [
        '<PROJECT_NAME>', '<PROJECT_TYPE>', '<PRIMARY_LANGUAGE>', '<PRIMARY_RUNTIME>',
        '<SOURCE_DIRS>', '<TEST_DIRS>', '<TEST_COMMAND>', '<BUILD_COMMAND>',
        '<LINT_COMMAND>', '<PACKAGE_MANAGER>', '<CI_COMMANDS>', '<PROTECTED_PATHS>',
        // Project-context extensions: an installed project must resolve these
        // before AI write-capable flows are trusted.
        '<PRIMARY_STACK>', '<FILE_PLACEMENT_RULES>', '<NAMING_RULES>',
        '<GOLDEN_EXAMPLES>', '<FORMATTER_CONFIG_FILES>', '<LINTER_CONFIG_FILES>',
        '<EDITORCONFIG_PATH>', '<IGNORE_FILES>', '<GENERATED_FILES>',
        '<PROTECTED_FILES>', '<INSTALL_COMMAND>', '<FORMAT_COMMAND>',
    ];
    // Note: <SCRIPTS_ROOT> is resolved at install time and is intentionally not in the required list.
    // Note: <UNKNOWN>, <FILES_OR_COMMANDS_CHECKED>, <NEXT_STEP> are format slots in blocked-response examples
    //       and are not project values. They stay as placeholders in canonical docs.
    // Note: <PROJECT_FORMATTING_EXCEPTIONS>, <PROJECT_IGNORED_FILES>, <PROJECT_ALLOWED_SCRIPTS>,
    //       <PROJECT_FORBIDDEN_SCRIPTS>, <PROJECT_SECURITY_RULES> are project-specific and remain
    //       optional; they should be filled when relevant for the installed project.
    $hits = [];
    $scanRoots = ['AGENTS.md', 'docs/ai', '.github', '.opencode'];
    foreach ($scanRoots as $path) {
        $abs = $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (is_file($abs)) {
            $hits = array_merge($hits, aiInstallerExtractPlaceholders((string) file_get_contents($abs)));
            continue;
        }
        if (!is_dir($abs)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile() || strtolower($f->getExtension()) !== 'md') {
                continue;
            }
            $relative = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($targetRoot))), '/');
            if (aiInstallerShouldSkipPlaceholderScanPath($relative)) {
                continue;
            }
            $hits = array_merge($hits, aiInstallerExtractPlaceholders((string) file_get_contents($f->getPathname())));
        }
    }
    $hits = array_values(array_unique($hits));
    $unresolvedRequired = array_values(array_intersect($required, $hits));
    $unresolvedOptional = array_values(array_diff($hits, $required));
    $resolvedHash = 'sha256:' . hash('sha256', json_encode(['required' => $unresolvedRequired, 'optional' => $unresolvedOptional], JSON_UNESCAPED_SLASHES));
    return [
        'unresolved_required' => $unresolvedRequired,
        'unresolved_optional' => $unresolvedOptional,
        'resolved_values_hash' => $resolvedHash,
    ];
}

function aiInstallerShouldSkipPlaceholderScanPath(string $relativePath): bool
{
    if ($relativePath === 'docs/ai/catalog.md' || $relativePath === 'docs/ai/project-context-placeholders.md') {
        return true;
    }

    return str_starts_with($relativePath, 'docs/ai/generated/');
}

function aiInstallerExtractPlaceholders(string $content): array
{
    if (preg_match_all('/<[A-Z0-9_]+>/', $content, $m) !== 1 && (!isset($m[0]) || $m[0] === [])) {
        return [];
    }
    return array_values(array_unique($m[0] ?? []));
}

function aiInstallerApplyPlaceholders(string $targetRoot, string $projectName, array $plan, bool $allowSkippedUnmanaged = false): void
{
    $projectValues = aiInstallerLoadProjectValues($targetRoot, $projectName);
    $map = [
        '<PROJECT_NAME>' => $projectName,
        '<PROJECT_SUMMARY>' => 'AI workflow starter for ' . $projectName,
        '<PROJECT_TYPE>' => aiInstallerDetectProjectType($targetRoot),
        '<PRIMARY_LANGUAGE>' => 'unknown',
        '<PRIMARY_RUNTIME>' => 'unknown',
        '<ACTIVE_PATHS>' => aiInstallerCollectActivePaths($targetRoot),
        '<INACTIVE_PATHS>' => 'unknown',
        '<PRIMARY_ENTRYPOINTS>' => 'README.md, docs/ai/project-context.md',
        '<PRIMARY_VERIFY_COMMAND>' => 'unknown',
        '<PRIMARY_BUILD_COMMAND>' => 'unknown',
        '<PRIMARY_TEST_COMMAND>' => 'unknown',
        '<PROJECT_CONTEXT_PATH>' => 'docs/ai/project-context.md',
        '<AVAILABLE_CAPABILITIES>' => 'project-context, verify-change, review-diff',
        '<REVIEW_PRIORITIES>' => 'correctness, regressions, configuration drift',
        '<APPROVAL_REQUIRED_CHANGES>' => 'secrets, destructive changes, auth or billing changes',
        '<TARGET_PLATFORMS>' => 'unknown',
        '<SOURCE_DIRS>' => 'unknown',
        '<TEST_DIRS>' => 'unknown',
        '<TEST_COMMAND>' => 'unknown',
        '<BUILD_COMMAND>' => 'unknown',
        '<LINT_COMMAND>' => 'unknown',
        '<PACKAGE_MANAGER>' => 'unknown',
        '<CI_COMMANDS>' => 'unknown',
        '<PROTECTED_PATHS>' => 'unknown',
        '<ARCHITECTURE_NOTES>' => 'Keep policy and capability docs canonical; keep runtime adapters thin.',
        '<RISK_AREAS>' => 'stale docs, adapter drift, unsafe command usage',
        '<NARROW_VERIFY_GUIDANCE>' => 'start with the narrowest repo-local check and escalate only if needed',
        '<CAPABILITY_COMPOSITION_NOTES>' => 'start with project-context, then verify-change, then review-diff',
        '<RELEASE_SAFETY_NOTES>' => 'define rollback posture for medium/high risk changes',
        '<KNOWN_GOTCHA_THEMES>' => 'stale paths, broad edits without evidence, guessed behavior',
        '<COPILOT_SURFACE>' => 'VS Code, CLI, GitHub.com',
        '<SUPPORTED_FEATURES>' => 'repo instructions, path instructions',
        '<OPTIONAL_FEATURES>' => 'prompt files, custom agents, hooks, MCP',
        '<INSTRUCTION_PRECEDENCE_NOTES>' => 'Nearest AGENTS.md wins for agent instructions.',
        '<CONFLICT_AVOIDANCE_NOTES>' => 'Keep repo-wide and path-specific guidance complementary.',
        '<GLOBAL_OR_SHARED_RULE_SOURCES>' => 'organization instructions, user-level instructions',
        '<OPTIONAL_VERIFY_COMMAND>' => 'unknown',
        '<SCRIPTS_ROOT>' => 'scripts/ai',
        // Project-context extension defaults. Installed projects should overwrite
        // these with concrete values during the first audit.
        '<PRIMARY_STACK>' => 'unknown',
        '<FILE_PLACEMENT_RULES>' => 'unknown',
        '<NAMING_RULES>' => 'unknown',
        '<GOLDEN_EXAMPLES>' => 'unknown',
        '<FORMATTER_CONFIG_FILES>' => 'unknown',
        '<LINTER_CONFIG_FILES>' => 'unknown',
        '<EDITORCONFIG_PATH>' => 'unknown',
        '<IGNORE_FILES>' => 'unknown',
        '<GENERATED_FILES>' => 'unknown',
        '<PROTECTED_FILES>' => 'unknown',
        '<INSTALL_COMMAND>' => 'unknown',
        '<FORMAT_COMMAND>' => 'unknown',
        '<PROJECT_FORMATTING_EXCEPTIONS>' => 'unknown',
        '<PROJECT_IGNORED_FILES>' => 'unknown',
        '<PROJECT_ALLOWED_SCRIPTS>' => 'unknown',
        '<PROJECT_FORBIDDEN_SCRIPTS>' => 'unknown',
        '<PROJECT_SECURITY_RULES>' => 'unknown',
    ];
    $map = array_merge($map, aiInstallerProjectValuesPlaceholderMap($projectValues));
    // Inject the user-owned extra-docs list from .ai/project.yml context.extraDocs.
    // It is regenerated from project.yml on every install, so user pointers survive re-render.
    $map['<EXTRA_DOCS>'] = aiInstallerRenderExtraDocsBlock(aiInstallerLoadProjectExtraDocs($targetRoot));

    foreach ($plan as $item) {
        $target = (string) ($item['target'] ?? '');
        $action = (string) ($item['action'] ?? '');
        if (
            $action === 'SKIP_PROTECTED_CORE'
            || ($action === 'SKIP_EXISTING_UNMANAGED' && !$allowSkippedUnmanaged)
        ) {
            continue;
        }
        // The shipped package source must stay verbatim so kit-level tools
        // (generate-ai-catalog, validate-ai-catalog, package-verify) keep
        // working in installed targets. PLACEHOLDERS.md is also informational
        // and must not be rewritten. Consumer installs now relocate the package
        // descriptors to the root and to docs/ai/package + policies, so those
        // relocated targets must also skip placeholder rewriting (rewriting a
        // descriptor or its docs/policies would corrupt JSON/lock/policy data).
        if (
            $target === 'PLACEHOLDERS.md'
            || $target === 'manifest.json'
            || $target === 'manifest.yml'
            || $target === 'catalog.json'
            || $target === 'package-lock.ai.json'
            || str_starts_with($target, 'packages/ai-universal-rules/')
            || str_starts_with($target, 'docs/ai/package/')
            || str_starts_with($target, 'policies/')
        ) {
            continue;
        }
        $abs = $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);
        if (is_file($abs) && str_ends_with(strtolower($abs), '.md')) {
            $content = (string) file_get_contents($abs);
            file_put_contents($abs, str_replace(array_keys($map), array_values($map), $content));
            continue;
        }
        if (!is_dir($abs)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') {
                continue;
            }
            $content = (string) file_get_contents($file->getPathname());
            file_put_contents($file->getPathname(), str_replace(array_keys($map), array_values($map), $content));
        }
    }
}

function aiInstallerProjectValuesPath(string $targetRoot): string
{
    return $targetRoot . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'project.yml';
}

function aiInstallerEnsureProjectValuesFile(string $targetRoot, string $projectName): void
{
    $path = aiInstallerProjectValuesPath($targetRoot);
    if (is_file($path)) {
        return;
    }

    aiInstallerMkdir(dirname($path));
    $values = [
        'schemaVersion' => '1',
        'projectName' => $projectName,
        'projectType' => aiInstallerDetectProjectType($targetRoot),
        'projectSummary' => 'AI workflow starter for ' . $projectName,
        'primaryLanguage' => 'unknown',
        'primaryRuntime' => 'unknown',
        'primaryEntrypoints' => 'README.md, docs/ai/project-context.md',
        'primaryVerifyCommand' => 'unknown',
        'primaryBuildCommand' => 'unknown',
        'primaryTestCommand' => 'unknown',
    ];

    $lines = [
        '# AI kit project values. Template/user-owned: edit values here, then rerun install/upgrade to re-render managed files.',
    ];
    foreach ($values as $key => $value) {
        $lines[] = $key . ': ' . aiInstallerProjectYamlQuote($value);
    }

    file_put_contents($path, implode("\n", $lines) . "\n");
}

/** @return array<string,string> */
function aiInstallerLoadProjectValues(string $targetRoot, string $projectName): array
{
    $defaults = [
        'projectName' => $projectName,
        'projectType' => aiInstallerDetectProjectType($targetRoot),
        'projectSummary' => 'AI workflow starter for ' . $projectName,
        'primaryLanguage' => 'unknown',
        'primaryRuntime' => 'unknown',
        'primaryEntrypoints' => 'README.md, docs/ai/project-context.md',
        'primaryVerifyCommand' => 'unknown',
        'primaryBuildCommand' => 'unknown',
        'primaryTestCommand' => 'unknown',
    ];

    $path = aiInstallerProjectValuesPath($targetRoot);
    if (!is_file($path)) {
        return $defaults;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, ':')) {
            continue;
        }
        [$key, $value] = explode(':', $trimmed, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '' || !array_key_exists($key, $defaults)) {
            continue;
        }
        $defaults[$key] = aiInstallerProjectYamlUnquote($value);
    }

    return $defaults;
}

/** @param array<string,string> $values @return array<string,string> */
function aiInstallerProjectValuesPlaceholderMap(array $values): array
{
    return [
        '<PROJECT_NAME>' => $values['projectName'] ?? '',
        '<PROJECT_TYPE>' => $values['projectType'] ?? '',
        '<PROJECT_SUMMARY>' => $values['projectSummary'] ?? '',
        '<PRIMARY_LANGUAGE>' => $values['primaryLanguage'] ?? '',
        '<PRIMARY_RUNTIME>' => $values['primaryRuntime'] ?? '',
        '<PRIMARY_ENTRYPOINTS>' => $values['primaryEntrypoints'] ?? '',
        '<PRIMARY_VERIFY_COMMAND>' => $values['primaryVerifyCommand'] ?? '',
        '<PRIMARY_BUILD_COMMAND>' => $values['primaryBuildCommand'] ?? '',
        '<PRIMARY_TEST_COMMAND>' => $values['primaryTestCommand'] ?? '',
    ];
}

/**
 * Read the optional `context.extraDocs:` list from .ai/project.yml. These are user-owned pointers
 * to additional project docs the AI should reference. They live in project.yml (not in rendered
 * files), so they survive every re-render: the installer regenerates the <EXTRA_DOCS> block from
 * this list each time.
 *
 * @return list<string>
 */
function aiInstallerLoadProjectExtraDocs(string $targetRoot): array
{
    $path = aiInstallerProjectValuesPath($targetRoot);
    if (!is_file($path)) {
        return [];
    }

    $docs = [];
    $inContext = false;
    $inExtra = false;
    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
            continue;
        }
        $indent = strlen($line) - strlen(ltrim($line));
        $trimmed = trim($line);

        if ($indent === 0) {
            $inContext = ($trimmed === 'context:');
            $inExtra = false;
            continue;
        }
        if (!$inContext) {
            continue;
        }
        if (preg_match('/^extraDocs:\s*(\[\])?\s*$/', $trimmed) === 1) {
            $inExtra = true;
            continue;
        }
        if ($inExtra && !str_starts_with($trimmed, '- ') && str_ends_with($trimmed, ':')) {
            $inExtra = false;
            continue;
        }
        if ($inExtra && str_starts_with($trimmed, '- ')) {
            $value = aiInstallerProjectYamlUnquote(trim(substr($trimmed, 2)));
            if ($value !== '') {
                $docs[] = $value;
            }
        }
    }

    return array_values(array_unique($docs));
}

/**
 * Render the <EXTRA_DOCS> placeholder value: a markdown bullet list of user-listed extra docs,
 * or a neutral note when none are configured. Safe to inject into any rendered markdown.
 *
 * @param list<string> $docs
 */
function aiInstallerRenderExtraDocsBlock(array $docs): string
{
    if ($docs === []) {
        return '_No additional project docs configured. Add paths under `context.extraDocs` in `.ai/project.yml`._';
    }

    $lines = [];
    foreach ($docs as $doc) {
        $lines[] = '- [`' . $doc . '`](' . $doc . ')';
    }

    return implode("\n", $lines);
}

function aiInstallerProjectYamlQuote(string $value): string
{
    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
}

function aiInstallerProjectYamlUnquote(string $value): string
{
    if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
        $value = substr($value, 1, -1);
        return str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
    }

    return $value;
}

function aiInstallerEnsureAgentsMarkedSectionForSkippedUserFile(string $targetRoot, array $plan): void
{
    $shouldPatch = false;
    foreach ($plan as $item) {
        if (($item['target'] ?? '') === 'AGENTS.md' && ($item['action'] ?? '') === 'SKIP_EXISTING_UNMANAGED') {
            $shouldPatch = true;
            break;
        }
    }
    if (!$shouldPatch) {
        return;
    }

    $path = $targetRoot . DIRECTORY_SEPARATOR . 'AGENTS.md';
    if (!is_file($path)) {
        return;
    }

    $content = (string) file_get_contents($path);
    $begin = '<!-- BEGIN ai-kit -->';
    $end = '<!-- END ai-kit -->';
    $section = implode("\n", [
        $begin,
        'AI kit instructions are installed for this repository. Keep your project-specific guidance outside this managed block.',
        '',
        '- Canonical project context: `docs/ai/project-context.md`',
        '- Workflow defaults: `docs/ai/workflow.md`',
        '- Execution protocol: `docs/ai/execution-protocol.md`',
        $end,
    ]);

    $pattern = '/<!-- BEGIN ai-kit -->.*?<!-- END ai-kit -->/s';
    if (preg_match($pattern, $content) === 1) {
        $updated = preg_replace($pattern, $section, $content);
        if (is_string($updated) && $updated !== $content) {
            file_put_contents($path, $updated);
        }
        return;
    }

    if ($content !== '' && !str_ends_with($content, "\n")) {
        $content .= "\n";
    }
    file_put_contents($path, $content . "\n" . $section . "\n");
}

/**
 * PathGuard: validate every plan target stays inside the target root. Rejects path traversal
 * (`..`), absolute targets, and any target whose existing parent chain escapes the root via a
 * symlink. Throws on the first violation so installs fail closed before writing.
 */
function aiInstallerAssertSafePlanTargets(string $targetRoot, array $plan): void
{
    $rootReal = realpath($targetRoot);
    if ($rootReal === false) {
        throw new RuntimeException('PathGuard: target root does not resolve: ' . $targetRoot);
    }
    $rootReal = rtrim(str_replace('\\', '/', $rootReal), '/');

    foreach ($plan as $item) {
        $target = (string) ($item['target'] ?? '');
        if ($target === '') {
            continue;
        }
        $normalized = str_replace('\\', '/', $target);
        if (str_starts_with($normalized, '/') || preg_match('#^[A-Za-z]:#', $normalized) === 1) {
            throw new RuntimeException('PathGuard: absolute install target rejected: ' . $target);
        }
        if (preg_match('#(^|/)\.\.(/|$)#', $normalized) === 1) {
            throw new RuntimeException('PathGuard: path traversal in install target rejected: ' . $target);
        }

        // Walk the deepest existing ancestor; if it resolves outside the root, reject (symlink escape).
        $candidate = $rootReal . '/' . $normalized;
        $ancestor = $candidate;
        while ($ancestor !== '' && !file_exists($ancestor)) {
            $parent = dirname($ancestor);
            if ($parent === $ancestor) {
                break;
            }
            $ancestor = $parent;
        }
        $ancestorReal = $ancestor !== '' ? realpath($ancestor) : false;
        if ($ancestorReal !== false) {
            $ancestorReal = rtrim(str_replace('\\', '/', $ancestorReal), '/');
            if ($ancestorReal !== $rootReal && !str_starts_with($ancestorReal . '/', $rootReal . '/')) {
                throw new RuntimeException('PathGuard: install target escapes root via symlink: ' . $target);
            }
        }
    }
}

/**
 * Case-collision guard: detect distinct plan targets that map to the same path under a
 * case-insensitive filesystem. Two such targets would overwrite each other unpredictably,
 * so installs fail closed with a clear message listing the colliding pair.
 */
function aiInstallerAssertNoCaseCollisions(array $plan): void
{
    $seen = [];
    foreach ($plan as $item) {
        $target = (string) ($item['target'] ?? '');
        if ($target === '') {
            continue;
        }
        $normalized = str_replace('\\', '/', $target);
        $key = strtolower($normalized);
        if (isset($seen[$key]) && $seen[$key] !== $normalized) {
            throw new RuntimeException(
                'case-collision in install targets: "' . $seen[$key] . '" vs "' . $normalized
                . '" would clobber each other on case-insensitive filesystems'
            );
        }
        $seen[$key] = $normalized;
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

/**
 * Capture `<!-- BEGIN ai-kit:user -->...<!-- END ai-kit:user -->` blocks from existing managed
 * `.md` targets before they are overwritten. Returns a map of relative path => user block text.
 *
 * @return array<string,string>
 */
function aiInstallerCaptureUserSections(string $targetRoot, array $plan): array
{
    $pattern = '/<!-- BEGIN ai-kit:user -->.*?<!-- END ai-kit:user -->/s';
    $captured = [];
    foreach ($plan as $item) {
        if (($item['type'] ?? '') !== 'file') {
            continue;
        }
        $rel = (string) ($item['target'] ?? '');
        if ($rel === '' || !str_ends_with(strtolower($rel), '.md')) {
            continue;
        }
        $abs = $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($abs)) {
            continue;
        }
        $content = (string) file_get_contents($abs);
        if (preg_match($pattern, $content, $m) === 1) {
            $captured[$rel] = $m[0];
        }
    }

    return $captured;
}

/**
 * Re-inject previously captured user blocks into freshly rendered files. If the rendered file
 * already has a user block (from the shipped template), it is replaced byte-for-byte with the
 * user's preserved block; otherwise the block is appended so user content is never lost.
 *
 * @param array<string,string> $userSections
 */
function aiInstallerRestoreUserSections(string $targetRoot, array $userSections): void
{
    $pattern = '/<!-- BEGIN ai-kit:user -->.*?<!-- END ai-kit:user -->/s';
    foreach ($userSections as $rel => $block) {
        $abs = $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($abs)) {
            continue;
        }
        $content = (string) file_get_contents($abs);
        if (preg_match($pattern, $content) === 1) {
            $updated = preg_replace($pattern, addcslashes($block, '\\$'), $content, 1);
            if (is_string($updated) && $updated !== $content) {
                file_put_contents($abs, $updated);
            }
            continue;
        }
        if ($content !== '' && !str_ends_with($content, "\n")) {
            $content .= "\n";
        }
        file_put_contents($abs, $content . "\n" . $block . "\n");
    }
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

function aiInstallerCopyFile(string $src, string $dest): void
{
    if (!is_file($src)) {
        throw new RuntimeException('missing source file: ' . $src);
    }
    $srcReal = realpath($src);
    $destReal = file_exists($dest) ? realpath($dest) : false;
    if ($srcReal !== false && $destReal !== false && $srcReal === $destReal) {
        return;
    }
    aiInstallerMkdir(dirname($dest));
    // Suppress the native warning; a clean RuntimeException is thrown on failure (e.g. a
    // read-only destination) so callers get one well-formed error, not raw PHP warning noise.
    if (!@copy($src, $dest)) {
        throw new RuntimeException('failed to copy file: ' . $src . ' -> ' . $dest);
    }
    $mode = @fileperms($src);
    if ($mode !== false) {
        @chmod($dest, $mode & 0777);
    }
}

function aiInstallerCopyDirAsSkillDirs(string $src, string $dest): void
{
    if (!is_dir($src)) {
        throw new RuntimeException('missing source directory: ' . $src);
    }
    $srcReal = realpath($src);
    $destReal = file_exists($dest) ? realpath($dest) : false;
    if ($srcReal !== false && $destReal !== false && $srcReal === $destReal) {
        return;
    }
    if (file_exists($dest)) {
        aiInstallerDeleteTree($dest);
    }
    aiInstallerMkdir($dest);
    foreach (glob($src . DIRECTORY_SEPARATOR . '*.md') ?: [] as $file) {
        $skillName = pathinfo($file, PATHINFO_FILENAME);
        $skillDir = $dest . DIRECTORY_SEPARATOR . $skillName;
        aiInstallerMkdir($skillDir);
        if (!copy($file, $skillDir . DIRECTORY_SEPARATOR . 'SKILL.md')) {
            throw new RuntimeException('failed to copy skill file: ' . $file);
        }
    }
}

function aiInstallerCopyDirWithRename(string $src, string $dest, string $newExt): void
{
    if (!is_dir($src)) {
        throw new RuntimeException('missing source directory: ' . $src);
    }
    $srcReal = realpath($src);
    $destReal = file_exists($dest) ? realpath($dest) : false;
    if ($srcReal !== false && $destReal !== false && $srcReal === $destReal) {
        return;
    }
    if (file_exists($dest)) {
        aiInstallerDeleteTree($dest);
    }
    aiInstallerMkdir($dest);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $item) {
        $subPath = $it->getSubPathName();
        if ($item->isDir()) {
            aiInstallerMkdir($dest . DIRECTORY_SEPARATOR . $subPath);
            continue;
        }
        $baseName = pathinfo($subPath, PATHINFO_FILENAME);
        $dirPart = dirname($subPath);
        $renamedName = $baseName . $newExt;
        $target = $dest . DIRECTORY_SEPARATOR . ($dirPart !== '.' ? $dirPart . DIRECTORY_SEPARATOR . $renamedName : $renamedName);
        aiInstallerMkdir(dirname($target));
        if (!copy($item->getPathname(), $target)) {
            throw new RuntimeException('failed to copy file: ' . $item->getPathname());
        }
    }
}

function aiInstallerCopyDir(string $src, string $dest, bool $cleanFirst = false): void
{
    if (!is_dir($src)) {
        throw new RuntimeException('missing source directory: ' . $src);
    }
    $srcReal = realpath($src);
    $destReal = file_exists($dest) ? realpath($dest) : false;
    if ($srcReal !== false && $destReal !== false && $srcReal === $destReal) {
        return;
    }
    if ($cleanFirst && file_exists($dest)) {
        aiInstallerDeleteTree($dest);
    }
    aiInstallerMkdir($dest);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $item) {
        $target = $dest . DIRECTORY_SEPARATOR . $it->getSubPathName();
        if ($item->isDir()) {
            aiInstallerMkdir($target);
            continue;
        }
        aiInstallerMkdir(dirname($target));
        if (!copy($item->getPathname(), $target)) {
            throw new RuntimeException('failed to copy file: ' . $item->getPathname());
        }
    }
}

/**
 * Ensure the target-root .gitignore contains the given entries.
 *
 * Idempotent and content-preserving: creates .gitignore if missing, never
 * sorts or removes existing rules, and only appends entries that are not
 * already covered. Duplicate detection tolerates trailing-slash and glob
 * variants (e.g. ".ai-logs", ".ai-logs/", ".ai-logs/*", ".ai-logs/**").
 *
 * @param list<string> $entries
 */
function aiInstallerEnsureGitignoreEntries(string $targetRoot, array $entries): void
{
    $gitignorePath = rtrim($targetRoot, '/\\') . DIRECTORY_SEPARATOR . '.gitignore';

    $existing = is_file($gitignorePath) ? (string) file_get_contents($gitignorePath) : '';
    $begin = '# BEGIN ai-kit';
    $end = '# END ai-kit';

    $blockEntries = [];
    foreach ($entries as $entry) {
        $norm = aiInstallerNormalizeGitignoreEntry($entry);
        if ($norm === '' || isset($blockEntries[$norm])) {
            continue;
        }
        // Preserve the caller's entry form verbatim. Callers pass canonical patterns:
        // directories end with '/', file rules (e.g. '.ai/install.lock') and globs
        // (e.g. '*.tmp') must NOT get a trailing slash, or git would treat them as
        // directories and fail to ignore the actual file.
        $blockEntries[$norm] = trim($entry);
    }

    if ($blockEntries === []) {
        return;
    }

    $block = $begin . "\n" . implode("\n", array_values($blockEntries)) . "\n" . $end;
    $pattern = '/^# BEGIN ai-kit\R.*?^# END ai-kit/ms';
    if (preg_match($pattern, $existing) === 1) {
        $updated = preg_replace($pattern, $block, $existing);
        if (is_string($updated) && $updated !== $existing) {
            file_put_contents($gitignorePath, $updated);
        }
        return;
    }

    $append = $existing;
    if ($append !== '' && !str_ends_with($append, "\n")) {
        $append .= "\n";
    }
    if ($append !== '') {
        $append .= "\n";
    }
    $append .= $block . "\n";

    file_put_contents($gitignorePath, $append);
}

/** @param list<string> $paths */
function aiInstallerAssertGitignoreEffective(string $targetRoot, array $paths): void
{
    $gitDir = rtrim($targetRoot, '/\\') . DIRECTORY_SEPARATOR . '.git';
    if (!is_dir($gitDir) && !is_file($gitDir)) {
        return;
    }

    $repoCheckExit = 0;
    exec('git -C ' . escapeshellarg($targetRoot) . ' rev-parse --is-inside-work-tree 2>/dev/null', $repoCheckOut, $repoCheckExit);
    if ($repoCheckExit !== 0) {
        return;
    }

    foreach ($paths as $path) {
        $cmd = 'git -C ' . escapeshellarg($targetRoot) . ' check-ignore --quiet -- ' . escapeshellarg($path);
        $exit = 0;
        exec($cmd, $out, $exit);
        if ($exit !== 0) {
            throw new RuntimeException('gitignore entry is not effective before backup write: ' . $path);
        }
    }
}

/**
 * Normalize a .gitignore entry to a bare root for duplicate detection.
 * Strips a leading slash and any trailing slash or /* or /** glob suffix.
 */
function aiInstallerNormalizeGitignoreEntry(string $entry): string
{
    $entry = trim($entry);
    $entry = ltrim($entry, '/');
    $entry = preg_replace('#/\*\*$#', '', $entry) ?? $entry;
    $entry = preg_replace('#/\*$#', '', $entry) ?? $entry;
    return rtrim($entry, '/');
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

    $backupId = 'install-ai-kit-' . gmdate('Ymd-His');
    $backupDir = $targetRoot . DIRECTORY_SEPARATOR . '.ai-backups' . DIRECTORY_SEPARATOR . $backupId;
    $filesDir = $backupDir . DIRECTORY_SEPARATOR . 'files';
    aiInstallerMkdir($filesDir);

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
        'backup_dir' => '.ai-backups/' . $backupId,
        'entry_count' => count($entries),
    ];
}

function aiInstallerSnapshotPath(string $source, string $snapshot): void
{
    if (is_file($source)) {
        aiInstallerMkdir(dirname($snapshot));
        if (!copy($source, $snapshot)) {
            throw new RuntimeException('failed to back up file: ' . $source);
        }
        return;
    }

    if (!is_dir($source)) {
        return;
    }

    aiInstallerMkdir($snapshot);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $item) {
        $target = $snapshot . DIRECTORY_SEPARATOR . $it->getSubPathName();
        if ($item->isDir()) {
            aiInstallerMkdir($target);
            continue;
        }
        aiInstallerMkdir(dirname($target));
        if (!copy($item->getPathname(), $target)) {
            throw new RuntimeException('failed to back up file: ' . $item->getPathname());
        }
    }
}

function aiInstallerDeleteTree(string $path): void
{
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($path);
}

function aiInstallerMkdir(string $path): void
{
    if (is_dir($path)) {
        return;
    }
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('failed to create directory: ' . $path);
    }
}

function aiInstallerLog(string $message): void
{
    fwrite(STDOUT, '[install-ai-kit] ' . $message . PHP_EOL);
}
