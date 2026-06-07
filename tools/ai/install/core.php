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

    // Adopt-or-conflict gate: files flagged never_auto_merge (e.g. opencode.jsonc) that
    // already exist and differ from the kit version must not be silently skipped or merged.
    // Fail fast before any writes so the install is all-or-nothing for the user's config.
    if (!$config['dryRun']) {
        aiInstallerAssertNoForeignConflicts($plan);
    }

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
    if (!copy($src, $dest)) {
        throw new RuntimeException('failed to copy file: ' . $src);
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
