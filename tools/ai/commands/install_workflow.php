<?php

declare(strict_types=1);

// install_workflow.php calls private-conflict helpers (aiInstallerPrivateConflictDir,
// aiInstallerPrivateConflictRel, ...) defined in tools/ai/install/core.php. Load that
// dependency explicitly so this file is self-contained regardless of test/worker load
// order. core.php is idempotent (require_once + top-level functions), so this is safe.
require_once __DIR__ . '/../install/core.php';

/**
 * Merge workflow-level install metadata onto the canonical manifest written by the
 * subprocess installer (install-ai-kit.php -> manifest.php).
 *
 * The subprocess is the single source of truth for the rich per-file `files{}` map
 * (which carries per-file ownership/merge metadata). The orchestrator must augment that
 * manifest with workflow-level fields (mode, runtime, toolchain, post_install_script,
 * package_lock_sha256, managed_paths) WITHOUT discarding `files{}`. Previously the
 * orchestrator overwrote the canonical manifest with a flat `managed_paths` shape, which
 * silently dropped the per-file map on the `ai.php install --apply` path.
 *
 * @param array<string,mixed> $canonical Canonical manifest read back from disk (may be empty).
 * @param array<string,mixed> $workflowFields Workflow-level fields to layer on top.
 * @param list<string>        $managedPaths Flat list of managed target paths (fallback only).
 * @return array<string,mixed> Merged manifest with `files{}` preserved.
 */
function aiInstallerMergeWorkflowManifest(array $canonical, array $workflowFields, array $managedPaths): array
{
    // Preserve the authoritative per-file map. If the subprocess did not produce one
    // (e.g. its shape changed), synthesise a minimal map so downstream ownership/upgrade
    // logic still has a per-file structure to rely on.
    if (!is_array($canonical['files'] ?? null)) {
        $canonical['files'] = [];
        foreach ($managedPaths as $managedPath) {
            if (is_string($managedPath) && $managedPath !== '') {
                $canonical['files'][$managedPath] = ['managed' => true, 'ownership' => 'owned'];
            }
        }
    }

    $merged = array_merge($canonical, $workflowFields);
    // `files` must never be replaced by the workflow layer.
    $merged['files'] = $canonical['files'];
    $merged['managed_paths'] = array_values(array_filter(
        $managedPaths,
        static fn($p): bool => is_string($p) && $p !== ''
    ));

    return $merged;
}

function aiRunAdapterPlan(string $root, array $args): int
{
    $planConfig = aiInstallerConfigFromAiArgs($root, $args, true);
    $packs = aiInstallerResolveSelectedPacks($planConfig, aiInstallerPackRegistry());
    $actions = aiInstallerBuildPlan($planConfig, aiInstallerPackRegistry(), $packs);

    $creates = [];
    $conflicts = [];
    foreach ($actions as $action) {
        if ($action['action'] === 'CREATE') {
            $creates[] = $action['target'];
        }
        if ($action['action'] === 'SKIP_EXISTING_UNMANAGED') {
            $conflicts[] = $action['target'];
        }
    }

    $data = [
        'mode' => $planConfig['mergeMode'] ?? 'sidecar-only',
        'targets' => aiInstallerTargetsFromRuntime((string) $planConfig['runtime']),
        'profile' => $planConfig['profile'],
        'packs' => $packs,
        'create' => $creates,
        'modify' => [],
        'conflicts' => $conflicts,
        'actions' => $actions,
        'backup_required' => true,
        'atomic_transaction_steps' => ['preflight', 'package-verify', 'backup', 'stage', 'apply', 'validate'],
    ];

    $written = aiCliWriteArtifact($root, 'adapter-plan', 'php tools/ai/ai.php adapter-plan', $data, 'ok', null, 'Run install --dry-run then install --backup-only before apply.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

function aiRunInstallWorkflow(string $root, array $args): int
{
    $runtimeMode = aiDetectRuntimeMode($args);
    $noInteraction = in_array('--no-interaction', $args, true);
    $isInteractiveEntry = in_array('--wizard', $args, true);
    if ($isInteractiveEntry) {
        return aiRunInstallWizard($root);
    }

    $preflight = aiRunPreflight($root);
    if ($preflight !== 0 && in_array('--apply', $args, true)) {
        $data = ['status' => 'blocked', 'reason' => 'preflight failed', 'next_action' => 'fix preflight and rerun install'];
        $written = aiCliWriteArtifact($root, 'install', 'php tools/ai/ai.php install', $data, 'blocked', null, 'Preflight must pass before apply.');
        fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
        return 1;
    }

    $installConfig = aiInstallerConfigFromAiArgs($root, $args);
    $selectedPacks = aiInstallerResolveSelectedPacks($installConfig, aiInstallerPackRegistry());
    if (is_string($installConfig['runAfterInstall'] ?? null) && $installConfig['runAfterInstall'] !== '') {
        $registry = aiInstallerScriptRegistry();
        $scriptId = (string) $installConfig['runAfterInstall'];
        if (!isset($registry[$scriptId])) {
            throw new RuntimeException('unknown post-install script id: ' . $scriptId);
        }
        $requiredPack = (string) ($registry[$scriptId]['pack'] ?? '');
        if ($requiredPack !== '' && !in_array($requiredPack, $selectedPacks, true)) {
            $data = [
                'status' => 'blocked',
                'reason' => 'post-install script requires missing pack: ' . $requiredPack,
                'script_id' => $scriptId,
                'selected_packs' => $selectedPacks,
            ];
            $written = aiCliWriteArtifact($root, 'install', 'php tools/ai/ai.php install', $data, 'blocked', null, 'Add the required pack with --with or choose a profile that includes it.');
            fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
            return 1;
        }
    }
    if (!empty($installConfig['toolchainCheck']) || !empty($installConfig['toolchainInstallPlan']) || !empty($installConfig['toolchainApply'])) {
        $tcArgs = ['--profile', (string) $installConfig['profile'], '--runtime', (string) $installConfig['runtime'], '--check'];
        if (!empty($installConfig['toolchainInstallPlan'])) {
            $tcArgs[] = '--install-plan';
        }
        if (!empty($installConfig['toolchainApply'])) {
            $tcArgs[] = '--toolchain-apply';
        }
        if (!empty($installConfig['toolchainTools'])) {
            $tcArgs[] = '--with';
            $tcArgs[] = implode(',', (array) $installConfig['toolchainTools']);
        }
        aiRunToolchain($root, $tcArgs);
    }
    $dryRun = (bool) $installConfig['dryRun'] || !in_array('--apply', $args, true);
    $mode = (string) ($installConfig['mergeMode'] ?? 'sidecar-only');
    $reinstall = in_array('--reinstall', $args, true);
    $manifestPath = aiInstallManifestPath($root);
    $hasManifest = is_file($manifestPath);

    if ($hasManifest && !$reinstall && !$dryRun) {
        $data = [
            'status' => 'blocked',
            'reason' => 'manifest already exists; use upgrade or install --reinstall',
            'manifest_path' => '.ai-install-manifest.json',
        ];
        $written = aiCliWriteArtifact($root, 'install', 'php tools/ai/ai.php install', $data, 'blocked', null, 'Use upgrade for existing installs unless forced reinstall is intended.');
        fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
        return 1;
    }

    if ($dryRun) {
        $plan = aiInstallerBuildPlan($installConfig, aiInstallerPackRegistry(), aiInstallerResolveSelectedPacks($installConfig, aiInstallerPackRegistry()));
        $creates = count(array_filter($plan, static fn(array $x): bool => ($x['action'] ?? '') === 'CREATE'));
        $skips = count(array_filter($plan, static fn(array $x): bool => ($x['action'] ?? '') === 'SKIP_EXISTING_UNMANAGED'));
        $data = [
            'status' => 'planned',
            'mode' => $mode,
            'runtime_mode' => $runtimeMode,
            'profile' => $installConfig['profile'],
            'packs' => $selectedPacks,
            'apply' => false,
            'summary' => ['create' => $creates, 'skip' => $skips],
            'install_kind' => $hasManifest ? 'reinstall' : 'fresh_install',
            'required_first' => ['preflight', 'package-verify', 'adapter-plan', 'install --backup-only'],
            'warnings' => aiInstallerAgentDependencyWarnings($selectedPacks),
        ];
        $written = aiCliWriteArtifact($root, 'install', 'php tools/ai/ai.php install --dry-run', $data, 'ok', null, 'Run install --backup-only before install --apply.');
        fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
        return 0;
    }

    $backupOnly = in_array('--backup-only', $args, true);
    if ($backupOnly) {
        $planData = aiLoadArtifactData($root, 'adapter-plan.json');
        $actions = $planData['data']['actions'] ?? [];
        if (!is_array($actions)) {
            $actions = [];
        }
        $backup = aiInstallBackupCreate($root, $actions, $root, 'install');

        $data = [
            'status' => 'ok',
            'mode' => $mode,
            'runtime_mode' => $runtimeMode,
            'backup_id' => $backup['backup_id'],
            'backup_dir' => $backup['backup_dir'],
            'schema' => $backup['schema'],
            'target_count' => $backup['entry_count'],
        ];
        $written = aiCliWriteArtifact($root, 'install', 'php tools/ai/ai.php install --backup-only', $data, 'ok', null, 'Backup created; proceed to install --apply once transaction apply is enabled.');
        fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
        return 0;
    }

    $backupId = aiParseArg($args, 'backup') ?? '';
    if ($backupId === '' && empty($installConfig['force'])) {
        $data = [
            'status' => 'blocked',
            'mode' => $mode,
            'runtime_mode' => $runtimeMode,
            'reason' => 'apply requires explicit backup id (use --force to skip)',
            'next_action' => 'php tools/ai/ai.php install --backup-only --apply --mode ' . $mode,
        ];
        $written = aiCliWriteArtifact($root, 'install', 'php tools/ai/ai.php install --apply', $data, 'blocked', null, 'Create backup first, then rerun apply with --backup <backup-id>. Use --force to bypass backup requirement.');
        fwrite(STDERR, "Error: backup is mandatory for install --apply. Create a backup first or use --force to skip." . PHP_EOL);
        fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
        return 1;
    }
    if ($backupId !== '') {
        $backupManifestPath = aiInstallBackupDir($root, $backupId) . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!is_file($backupManifestPath)) {
            throw new RuntimeException('backup manifest not found for apply backup id: ' . $backupId);
        }
    }

    $transactionId = 'install-' . gmdate('Ymd-His');
    $stagingDir = '.ai-tmp/' . $transactionId;
    $tx = [
        'transaction_id' => $transactionId,
        'status' => 'prepared',
        'staging_dir' => $stagingDir,
        'mode' => $mode,
        'runtime_mode' => $runtimeMode,
    ];
    aiCliWriteArtifact($root, 'install-transaction', 'php tools/ai/ai.php install --apply', $tx, 'ok', null, 'Transaction prepared; apply command execution follows.');

    $runtime = (string) $installConfig['runtime'];
    // A reinstall over an existing manifest must actually overwrite managed files.
    // Without forcing, the subprocess installer skips existing files
    // (skip_existing_unmanaged) and the reinstall is a silent no-op for managed dirs
    // such as .opencode/agents. The upgrade path already pairs --reinstall with --force;
    // the builder mirrors that by forcing whenever reinstall is requested.
    $installConfig['reinstall'] = $reinstall;
    $cmd = aiInstallerBuildSubprocessInstallCommand($runtime, (string) $installConfig['profile'], $mode, $installConfig);

    $run = aiRunCommand($root, $cmd);
    $status = $run['exit'] === 0 ? 'ok' : 'failed';
    if ($backupId !== '') {
        if ($status === 'ok') {
            $planData = aiLoadArtifactData($root, 'adapter-plan.json');
            $actions = $planData['data']['actions'] ?? [];
            aiInstallBackupRecordAfter($root, $backupId, is_array($actions) ? $actions : [], $root, 'applied');
        } else {
            aiInstallBackupUpdateState($root, $backupId, 'failed', 'installer command failed');
            try {
                $rollback = aiInstallBackupRollback($root, $backupId, true, true);
                $rolledBack = (($rollback['status'] ?? null) === 'ok') && (($rollback['conflicts'] ?? []) === []);
                aiInstallBackupUpdateState($root, $backupId, $rolledBack ? 'failed_rolled_back' : 'failed_recoverable', 'installer command failed');
                aiInstallBackupAppendAudit($root, $rolledBack ? 'install_failed_auto_rollback' : 'install_failed_recoverable', [
                    'backup_id' => $backupId,
                    'reason' => 'installer command failed',
                    'rollback_status' => $rollback['status'] ?? 'unknown',
                    'rollback_conflicts' => $rollback['conflicts'] ?? [],
                ]);
            } catch (\Throwable $e) {
                aiInstallBackupUpdateState($root, $backupId, 'failed_recoverable', $e->getMessage());
                aiInstallBackupAppendAudit($root, 'install_failed_recoverable', [
                    'backup_id' => $backupId,
                    'reason' => 'installer command failed',
                    'rollback_error' => $e->getMessage(),
                ]);
            }
        }
    }

    $postInstallScript = ['requested' => $installConfig['runAfterInstall'] ?? null, 'executed' => false, 'reason' => null, 'exit' => null];
    if ($status === 'ok') {
        $plan = aiLoadArtifactData($root, 'adapter-plan.json');
        $managed = [];
        if (is_array($plan) && is_array($plan['data']['create'] ?? null)) {
            foreach ($plan['data']['create'] as $item) {
                if (is_string($item) && $item !== '') {
                    $managed[] = $item;
                }
            }
        }
        // The subprocess installer (install-ai-kit.php -> manifest.php) is the canonical
        // manifest writer: it emits the rich per-file `files{}` map. The orchestrator must
        // augment that manifest with workflow-level metadata, never overwrite it with a flat
        // `managed_paths` list (which would silently drop per-file ownership/merge metadata).
        $canonicalManifest = [];
        if (is_file($manifestPath)) {
            $decodedManifest = json_decode((string) file_get_contents($manifestPath), true);
            if (is_array($decodedManifest)) {
                $canonicalManifest = $decodedManifest;
            }
        }

        $manifest = aiInstallerMergeWorkflowManifest($canonicalManifest, [
            'schema_version' => $canonicalManifest['schema_version'] ?? 1,
            'installer_version' => $canonicalManifest['installer_version'] ?? '0.2.0',
            'installed_at' => (string) ($canonicalManifest['installed_at'] ?? gmdate('c')),
            'updated_at' => gmdate('c'),
            'profile' => (string) $installConfig['profile'],
            'mode' => $mode,
            'runtime' => $runtime,
            'package' => is_array($canonicalManifest['package'] ?? null) ? $canonicalManifest['package'] : [
                'name' => 'ai-universal-rules',
                'distribution' => 'git-tag',
                'source_repository' => 'UtmostCreator/app-configs',
                'source_remote' => 'origin',
                'source_ref' => 'unknown',
                'source_commit' => 'unknown',
                'installed_version' => 'unknown',
            ],
            'packs' => $selectedPacks,
            'toolchain' => [
                'checked' => !empty($installConfig['toolchainCheck']),
                'install_plan_printed' => !empty($installConfig['toolchainInstallPlan']),
                'applied' => !empty($installConfig['toolchainApply']),
            ],
            'post_install_script' => $postInstallScript,
            'package_lock_sha256' => is_file(aiPackageLockPath($root)) ? 'sha256:' . hash_file('sha256', aiPackageLockPath($root)) : 'unknown',
        ], $managed);
        $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($manifestPath, $manifestJson);
        $derivedDir = dirname(aiInstallDerivedManifestPath($root));
        if (!is_dir($derivedDir)) {
            mkdir($derivedDir, AI_DIR_MODE, true);
        }
        file_put_contents(aiInstallDerivedManifestPath($root), $manifestJson);

        if (!empty($installConfig['verifyAfter'])) {
            $verifyExit = aiRunVerify($root, []);
            if ($verifyExit !== 0) {
                $status = 'failed';
                $postInstallScript['reason'] = 'skipped_verify_failed';
            }
        }

        if ($status === 'ok' && is_string($installConfig['runAfterInstall'] ?? null) && $installConfig['runAfterInstall'] !== '') {
            $runScript = aiRunScriptById($root, (string) $installConfig['runAfterInstall'], ['--apply'], $selectedPacks);
            $postInstallScript['executed'] = $runScript['exit'] === 0;
            $postInstallScript['reason'] = $runScript['exit'] === 0 ? 'executed' : (($runScript['error'] ?? 'failed'));
            $postInstallScript['exit'] = $runScript['exit'];
            if (($runScript['exit'] ?? 1) !== 0) {
                $status = 'failed';
            }
        } elseif ($status === 'ok' && is_string($installConfig['runAfterInstall'] ?? null) && $installConfig['runAfterInstall'] !== '') {
            $postInstallScript['reason'] = 'skipped_install_not_ok';
        }

        $manifest['post_install_script'] = $postInstallScript;
        $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($manifestPath, $manifestJson);
        file_put_contents(aiInstallDerivedManifestPath($root), $manifestJson);
    }

    $data = [
        'status' => $status,
        'mode' => $mode,
        'runtime_mode' => $runtimeMode,
        'backup_id' => $backupId,
        'transaction_id' => $transactionId,
        'installer_command' => $cmd,
        'installer_exit' => $run['exit'],
        'installer_stdout_preview' => substr($run['stdout'], 0, 3000),
        'installer_stderr_preview' => substr($run['stderr'], 0, 3000),
        'post_install_script' => $postInstallScript,
    ];
    $written = aiCliWriteArtifact($root, 'install', 'php tools/ai/ai.php install --apply', $data, $status, null, $status === 'ok' ? 'Install apply completed; run adapter-validate next.' : 'Inspect installer output and rerun install after fixing errors.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return $status === 'ok' ? 0 : 1;
}

/** @param array<string,mixed> $installConfig */
function aiInstallerBuildSubprocessInstallCommand(string $runtime, string $profile, string $mode, array $installConfig): string
{
    $cmd = 'php tools/ai/install-ai-kit.php --target . --runtime ' . escapeshellarg($runtime) . ' --profile ' . escapeshellarg($profile);
    if ($mode === 'sidecar-only') {
        $cmd .= ' --no-base';
    }
    // A reinstall implies overwriting existing managed files, so force the subprocess
    // installer; otherwise it skips them and the reinstall is a no-op for managed dirs.
    if (!empty($installConfig['force']) || !empty($installConfig['reinstall'])) {
        $cmd .= ' --force';
    }
    if (!empty($installConfig['allowCoreOverwrite'])) {
        $cmd .= ' --allow-core-overwrite';
    }
    if (!empty($installConfig['adopt'])) {
        $cmd .= ' --adopt';
    }
    if (!empty($installConfig['allowNonGit'])) {
        $cmd .= ' --allow-non-git';
    }
    if (!empty($installConfig['allFeatures'])) {
        $cmd .= ' --all-features';
    }
    if (!empty($installConfig['withPacks'])) {
        $cmd .= ' --with ' . escapeshellarg(implode(',', $installConfig['withPacks']));
    }
    if (!empty($installConfig['withoutPacks'])) {
        $cmd .= ' --without ' . escapeshellarg(implode(',', $installConfig['withoutPacks']));
    }
    if (!empty($installConfig['allowPlaceholders'])) {
        $cmd .= ' --allow-placeholders';
    }

    return $cmd;
}

function aiRunInstallWizard(string $root): int
{
    fwrite(STDOUT, "AI Installer Wizard\n");
    fwrite(STDOUT, "Select runtime target and install profile with optional packs.\n\n");

    $target = strtolower(aiPromptLine('Select targets: [1] both, [2] copilot, [3] opencode (default 1): '));
    $runtime = 'both';
    if ($target === '2' || $target === 'copilot') {
        $runtime = 'github-copilot';
    } elseif ($target === '3' || $target === 'opencode') {
        $runtime = 'opencode';
    }

    $profileMap = ['1' => 'minimal', '2' => 'copilot', '3' => 'opencode', '4' => 'dual', '5' => 'accelerated', '6' => 'full-governance', '7' => 'custom', '8' => 'basic', '9' => 'standard', '10' => 'creator', '11' => 'full', '12' => 'agents-only'];
    $profileInput = strtolower(aiPromptLine('Select profile: [1] minimal, [2] copilot, [3] opencode, [4] dual, [5] accelerated, [6] full-governance, [7] custom; editions: [8] basic, [9] standard, [10] creator, [11] full, [12] agents-only (default 4): '));
    $profile = $profileMap[$profileInput] ?? 'dual';

    $allFeatures = aiPromptYesNo('Install all available AI feature packs?', true);
    $with = [];
    if (!$allFeatures) {
        $customize = aiPromptYesNo('Customize optional packs?', true);
        if ($customize || $profile === 'custom') {
            foreach (['scripts-pack', 'policy-pack', 'hooks-pack', 'ci-pack', 'evidence-pack', 'docs-reference-pack', 'capabilities-governance', 'delivery-pack', 'optional-agents-pack', 'optional-prompts-pack'] as $pack) {
                if (aiPromptYesNo('Install ' . $pack . '?', true)) {
                    $with[] = $pack;
                }
            }
        }
    }

    $hookDriver = 'none';
    if (in_array('hooks-pack', $with, true) || $allFeatures || in_array($profile, ['full-governance', 'full'], true)) {
        $wire = strtolower(aiPromptLine('Wire hooks now? [1] no, [2] husky, [3] lefthook, [4] native git hooks (default 1): '));
        $hookDriver = match ($wire) {
            '2', 'husky' => 'husky',
            '3', 'lefthook' => 'lefthook',
            '4', 'native' => 'native',
            default => 'none',
        };
    }

    $modeInput = strtolower(aiPromptLine('Select mode: [1] sidecar-only, [2] safe-merge (default 1): '));
    $mode = ($modeInput === '2' || $modeInput === 'safe-merge') ? 'safe-merge' : 'sidecar-only';

    $planArgs = ['--runtime', $runtime, '--profile', $profile, '--mode', $mode, '--no-interaction'];
    if ($allFeatures) {
        $planArgs[] = '--all-features';
    }
    if ($with !== []) {
        $planArgs[] = '--with';
        $planArgs[] = implode(',', $with);
    }
    if ($hookDriver !== 'none') {
        $planArgs[] = '--hook-driver';
        $planArgs[] = $hookDriver;
    }

    $cfg = aiInstallerConfigFromAiArgs($root, $planArgs, true);
    $registry = aiInstallerPackRegistry();
    $packs = aiInstallerResolveSelectedPacks($cfg, $registry);
    $toolchainCheck = false;
    $toolchainPlan = false;
    $toolchainApply = false;
    if (in_array('scripts-pack', $packs, true)) {
        $toolchainCheck = aiPromptYesNo('Run toolchain check for scripts-pack?', false);
        if ($toolchainCheck) {
            $toolchainPlan = aiPromptYesNo('Print tool install plan?', true);
            $toolchainApply = aiPromptYesNo('Apply safe tool installs (repomix only)?', true);
            if ($toolchainCheck) {
                $planArgs[] = '--toolchain-check';
            }
            if ($toolchainPlan) {
                $planArgs[] = '--toolchain-install-plan';
            }
            if ($toolchainApply) {
                $planArgs[] = '--toolchain-apply';
            }
        }
    }
    $actions = aiInstallerBuildPlan($cfg, $registry, $packs);
    $dep = aiInstallerPackToolRequirements($packs);

    $missingRequired = [];
    $missingOptional = [];
    foreach ($dep['required'] as $tool) {
        if (!aiCliCommandExists((string) $tool)) {
            $missingRequired[] = $tool;
        }
    }
    foreach ($dep['optional'] as $tool) {
        if (!aiCliCommandExists((string) $tool)) {
            $missingOptional[] = $tool;
        }
    }

    $createCount = count(array_filter($actions, static fn(array $a): bool => ($a['action'] ?? '') === 'CREATE'));
    $updateCount = count(array_filter($actions, static fn(array $a): bool => ($a['action'] ?? '') === 'OVERWRITE_MANAGED'));
    $skipCount = count(array_filter($actions, static fn(array $a): bool => str_starts_with((string) ($a['action'] ?? ''), 'SKIP')));
    $conflictCount = count(array_filter($actions, static fn(array $a): bool => ($a['action'] ?? '') === 'SKIP_EXISTING_UNMANAGED'));

    fwrite(STDOUT, "\nInstall summary\n\n");
    fwrite(STDOUT, "Runtime: {$runtime}\n");
    fwrite(STDOUT, "Profile: {$profile}\n");
    fwrite(STDOUT, "Selected packs:\n- " . implode("\n- ", $packs) . "\n");
    fwrite(STDOUT, "Files to create: {$createCount}\n");
    fwrite(STDOUT, "Files to update: {$updateCount}\n");
    fwrite(STDOUT, "Files skipped: {$skipCount}\n");
    fwrite(STDOUT, "Manual conflicts: {$conflictCount}\n");
    fwrite(STDOUT, "Required tools missing: " . count($missingRequired) . "\n");
    fwrite(STDOUT, "Optional tools missing: " . count($missingOptional) . "\n");

    $runAfterInstall = 'none';
    if (in_array('scripts-pack', $packs, true)) {
        fwrite(STDOUT, "Run a script after install? [0] none, [1] repomix-context, [2] repomix-tree, [3] repomix-scc-router, [4] pack-context\n");
        $sel = strtolower(aiPromptLine('Selection (default 0): '));
        $runAfterInstall = match ($sel) {
            '1', 'repomix-context' => 'repomix-context',
            '2', 'repomix-tree' => 'repomix-tree',
            '3', 'repomix-scc-router' => 'repomix-scc-router',
            '4', 'pack-context' => 'pack-context',
            default => 'none',
        };
        if ($runAfterInstall !== 'none') {
            $planArgs[] = '--run-after-install';
            $planArgs[] = $runAfterInstall;
        }
    }

    fwrite(STDOUT, "\nFinal action\n[1] Dry-run\n[2] Backup only\n[3] Apply with backup\n[4] Cancel\n");
    $final = strtolower(aiPromptLine('Selection (default 1): '));
    if ($final === '' || $final === '1' || $final === 'dry-run') {
        aiRunInstallWorkflow($root, array_merge($planArgs, ['--dry-run']));
        if ($toolchainCheck) {
            $tcArgs = ['--profile', $profile, '--runtime', $runtime, '--check'];
            if ($toolchainPlan) {
                $tcArgs[] = '--install-plan';
            }
            if ($toolchainApply) {
                $tcArgs[] = '--toolchain-apply';
            }
            aiRunToolchain($root, $tcArgs);
        }
        return 0;
    }

    if ($final === '2' || $final === 'backup') {
        aiRunInstallWorkflow($root, array_merge($planArgs, ['--backup-only', '--apply']));
        $backupId = aiLatestBackupId($root);
        if ($backupId !== null) {
            fwrite(STDOUT, "OK: created backup .ai/backups/{$backupId}/\n");
        }
        return 0;
    }

    if ($final === '3' || $final === 'apply') {
        aiRunInstallWorkflow($root, array_merge($planArgs, ['--backup-only', '--apply']));
        $backupId = aiLatestBackupId($root);
        if ($backupId === null) {
            fwrite(STDERR, "Error: no backup found. Create backup first with install --backup-only.\n");
            return 1;
        }
        $exit = aiRunInstallWorkflow($root, array_merge($planArgs, ['--apply', '--backup', $backupId]));
        aiRunAdapterValidate($root);
        if (aiPromptYesNo('Run verify now?', false)) {
            aiRunVerify($root, []);
        }
        return $exit;
    }

    $data = [
        'status' => 'planned',
        'interactive' => true,
        'runtime' => $runtime,
        'profile' => $profile,
        'packs' => $packs,
        'mode' => $mode,
        'next_action' => 'Run install --apply with backup after reviewing dry-run.',
    ];
    $written = aiCliWriteArtifact($root, 'install', 'php tools/ai/ai.php install --interactive', $data, 'ok', null, 'Wizard exited before apply; no installation changes made.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

/**
 * Copy user-modified owned files to .ai/conflicts/<timestamp>-upgrade/files/ before an upgrade reinstall
 * overwrites them, so the user's edits are recoverable. Returns the list of preserved files.
 *
 * @param list<array<string,mixed>> $fileActions
 * @return list<array{file:string,preserved_to:string}>
 */
function aiUpgradePreserveOwnedConflicts(string $root, array $fileActions): array
{
    $conflicts = [];
    foreach ($fileActions as $fa) {
        if (($fa['action'] ?? '') === 'conflict-preserve-user') {
            $conflicts[] = (string) ($fa['file'] ?? '');
        }
    }
    $conflicts = array_values(array_filter($conflicts, static fn($f): bool => $f !== ''));
    if ($conflicts === []) {
        return [];
    }

    $stamp = gmdate('Ymd\THis\Z');
    $conflictRoot = aiInstallerPrivateConflictDir($root, 'upgrade', 'files', $stamp);

    $preserved = [];
    foreach ($conflicts as $rel) {
        $srcAbs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($srcAbs)) {
            continue;
        }
        $destAbs = $conflictRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $destDir = dirname($destAbs);
        if (!is_dir($destDir)) {
            mkdir($destDir, aiInstallerPrivateDirMode(), true);
        }
        if (copy($srcAbs, $destAbs)) {
            $preserved[] = ['file' => $rel, 'preserved_to' => aiInstallerPrivateConflictRel('upgrade', 'files', $stamp) . '/' . $rel];
        }
    }

    return $preserved;
}

/**
 * Flatten every target path the current pack registry ships, across all packs.
 * Used to compute the `deprecated` class (manifest files no longer in this set).
 *
 * @return list<string>
 */
function aiUpgradeCurrentRegistryTargets(): array
{
    $targets = [];
    foreach (aiInstallerPackRegistry() as $items) {
        foreach ($items as $item) {
            $target = (string) ($item['target'] ?? '');
            if ($target !== '') {
                $targets[$target] = true;
            }
        }
    }

    return array_keys($targets);
}

/**
 * Resolve the upgrade status/action for a single installed file by ownership class.
 *
 * Per-class routing (deprecated is computed separately by aiUpgradeComputeDeprecated):
 *  - missing target      -> restore or remove from manifest
 *  - template            -> preserve (never overwritten; --reset-templates handles refresh)
 *  - rendered            -> regenerate from project.yml (user marker sections preserved)
 *  - patch-managed       -> update-managed-block (only the marker block; user content kept)
 *  - owned + user-modified + --force-owned -> force-overwrite (after preserving user bytes)
 *  - owned + user-modified                 -> conflict-preserve-user
 *  - owned + source-updated (clean)        -> auto-update
 *  - otherwise                             -> skip (unchanged)
 *
 * @return array{status:string,action:string}
 */
function aiUpgradeResolveFileAction(
    string $ownership,
    bool $userModified,
    bool $sourceUpdated,
    bool $targetMissing,
    bool $forceOwned
): array {
    if ($targetMissing) {
        return ['status' => 'missing', 'action' => 'restore or remove from manifest'];
    }
    if ($ownership === 'template') {
        return [
            'status' => $userModified ? 'template-user-owned' : 'template-unchanged',
            'action' => 'preserve',
        ];
    }
    if ($ownership === 'rendered') {
        return ['status' => 'rendered', 'action' => 'regenerate'];
    }
    if ($ownership === 'patch-managed') {
        return ['status' => 'patch-managed', 'action' => 'update-managed-block'];
    }
    if ($ownership === 'owned' && $userModified) {
        if ($forceOwned) {
            return ['status' => 'owned-force-overwrite', 'action' => 'force-overwrite'];
        }
        return [
            'status' => $sourceUpdated ? 'owned-both-changed' : 'owned-user-modified',
            'action' => 'conflict-preserve-user',
        ];
    }
    if ($sourceUpdated && !$userModified) {
        return ['status' => 'source-updated', 'action' => 'auto-update'];
    }

    return ['status' => 'unchanged', 'action' => 'skip'];
}

/**
 * Apply-path removal of computed `deprecated` files (see aiUpgradeComputeDeprecated).
 *
 * Only operates on the deprecated entries it is given (each derived from the install
 * manifest), so the write-allowlist invariant holds — foreign files are never touched.
 *  - action `delete`          -> remove the file (a byte-identical copy is already in backup)
 *  - action `route-to-removed` -> copy user bytes to .ai/conflicts/<ts>-upgrade/removed/ then remove
 *
 * Files already absent from disk are skipped. Returns the list of files actually acted on.
 *
 * @param list<array{file:string,action:string}> $deprecated
 * @return list<array{file:string,action:string,routed_to?:string}>
 */
function aiUpgradeRemoveDeprecated(string $root, array $deprecated): array
{
    if ($deprecated === []) {
        return [];
    }

    $stamp = gmdate('Ymd\THis\Z');
    $removedRoot = aiInstallerPrivateConflictDir($root, 'upgrade', 'removed', $stamp);

    $acted = [];
    foreach ($deprecated as $entry) {
        $rel = (string) ($entry['file'] ?? '');
        $action = (string) ($entry['action'] ?? '');
        if ($rel === '') {
            continue;
        }
        $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($abs)) {
            // Already gone from disk: nothing to remove or route.
            continue;
        }

        if ($action === 'route-to-removed') {
            $destAbs = $removedRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $destDir = dirname($destAbs);
            if (!is_dir($destDir)) {
                mkdir($destDir, aiInstallerPrivateDirMode(), true);
            }
            if (!copy($abs, $destAbs)) {
                // Never delete user bytes we failed to preserve.
                continue;
            }
            unlink($abs);
            $acted[] = [
                'file' => $rel,
                'action' => $action,
                'routed_to' => aiInstallerPrivateConflictRel('upgrade', 'removed', $stamp) . '/' . $rel,
            ];
            continue;
        }

        // Default: delete (deprecated-unchanged; already backed up).
        unlink($abs);
        $acted[] = ['file' => $rel, 'action' => 'delete'];
    }

    return $acted;
}

/**
 * Compute the `deprecated` ownership class at plan time (never stored).
 *
 * A deprecated file is one recorded in the installed manifest but no longer shipped by the
 * current pack registry (e.g. a stale hook or policy the kit dropped). Upgrade routes each:
 *  - deprecated-unchanged     -> delete           (the byte-identical copy is already in backup)
 *  - deprecated-user-modified -> route-to-removed (user edits go to conflicts/<ts>-upgrade/removed/)
 *
 * Files already absent from disk produce no action. Invariant 1 (write-allowlist) holds:
 * only manifest-recorded paths are ever considered, never foreign files.
 *
 * @param array<string,mixed> $manifestFiles  Canonical files{} map from the install manifest.
 * @param list<string>        $registryTargets Target paths the current kit still ships.
 * @return list<array{file:string,ownership:string,status:string,action:string}>
 */
function aiUpgradeComputeDeprecated(array $manifestFiles, array $registryTargets, string $root): array
{
    $shipped = array_fill_keys(array_map('strval', $registryTargets), true);
    $deprecated = [];

    foreach ($manifestFiles as $target => $meta) {
        $target = (string) $target;
        if (isset($shipped[$target])) {
            continue;
        }
        $targetAbs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);
        $currentHash = aiHashPath($targetAbs);
        if ($currentHash === 'missing') {
            // Already gone from disk: nothing to delete or route.
            continue;
        }
        $installedHash = is_array($meta) ? (string) ($meta['installed_hash'] ?? 'unknown') : 'unknown';
        $userModified = $currentHash !== $installedHash;

        $deprecated[] = [
            'file' => $target,
            'ownership' => 'deprecated',
            'status' => $userModified ? 'deprecated-user-modified' : 'deprecated-unchanged',
            'action' => $userModified ? 'route-to-removed' : 'delete',
        ];
    }

    return $deprecated;
}

function aiRunUpgradeWorkflow(string $root, array $args): int
{
    aiInstallerAssertLockCompatible($root);

    $manifestPath = aiInstallManifestPath($root);
    if (!is_file($manifestPath)) {
        $data = [
            'status' => 'blocked',
            'reason' => 'no install manifest found; run install first',
            'manifest_path' => '.ai-install-manifest.json',
        ];
        $written = aiCliWriteArtifact($root, 'upgrade', 'php tools/ai/ai.php upgrade', $data, 'blocked', null, 'Install workflow must create manifest before upgrade.');
        fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
        return 1;
    }

    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    if (!is_array($manifest)) {
        throw new RuntimeException('Invalid install manifest JSON at .ai-install-manifest.json');
    }

    $dryRun = in_array('--dry-run', $args, true) || !in_array('--apply', $args, true);
    $forceOwned = in_array('--force-owned', $args, true);
    $targetRef = aiParseArg($args, 'to') ?? '';
    $verifyExit = aiRunPackageVerify($root);
    $verify = aiLoadArtifactData($root, 'package-verify.json');

    $changes = [];
    if ($verifyExit !== 0) {
        $changes[] = [
            'type' => 'source_checksum_drift',
            'action' => 'review package-lock and template changes',
        ];
    }

    $sourceRef = (string) (($manifest['package']['source_ref'] ?? 'unknown'));
    $tags = [];
    $tagExit = 0;
    exec('git -C ' . escapeshellarg($root) . ' tag --sort=-v:refname', $tags, $tagExit);
    $latestTag = $tagExit === 0 && $tags !== [] ? (string) $tags[0] : 'unknown';
    if ($latestTag !== 'unknown' && $sourceRef !== 'unknown' && $latestTag !== $sourceRef) {
        $changes[] = [
            'type' => 'newer_package_available',
            'current_ref' => $sourceRef,
            'latest_ref' => $latestTag,
            'action' => 'review upgrade plan and apply with backup',
        ];
    }

    $files = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
    $fileActions = [];
    foreach ($files as $target => $meta) {
        if (!is_array($meta)) {
            continue;
        }
        $sourceRel = (string) ($meta['source'] ?? '');
        $sourceAbs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sourceRel);
        $targetAbs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $target);
        $sourceCurrentHash = aiHashPath($sourceAbs);
        $installedAtInstall = (string) ($meta['installed_hash'] ?? 'unknown');
        $sourceAtInstall = (string) ($meta['source_hash'] ?? 'unknown');
        $targetCurrentHash = aiHashPath($targetAbs);

        // Ownership drives upgrade behaviour. Default to 'owned' for manifests written before
        // ownership classes existed, so legacy installs keep the previous (overwrite) behaviour.
        $ownership = (string) ($meta['ownership'] ?? 'owned');
        $userModified = $targetCurrentHash !== 'missing' && $targetCurrentHash !== $installedAtInstall;
        $sourceUpdated = $sourceCurrentHash !== $sourceAtInstall;

        $resolved = aiUpgradeResolveFileAction(
            $ownership,
            $userModified,
            $sourceUpdated,
            $targetCurrentHash === 'missing',
            $forceOwned
        );

        $fileActions[] = [
            'file' => (string) $target,
            'status' => $resolved['status'],
            'action' => $resolved['action'],
            'ownership' => $ownership,
            'source' => $sourceRel,
        ];
    }

    // Computed `deprecated` class (never stored): manifest files the current kit no longer
    // ships. Surfaced here for the dry-run report; the apply path deletes/routes them.
    $registryTargets = aiUpgradeCurrentRegistryTargets();
    $deprecated = aiUpgradeComputeDeprecated($files, $registryTargets, $root);
    if ($deprecated !== []) {
        $changes[] = [
            'type' => 'deprecated_files_present',
            'count' => count($deprecated),
            'action' => 'upgrade --apply removes unchanged deprecated files (backed up) and routes user-modified ones to .ai/conflicts/<ts>-upgrade/removed/',
        ];
    }

    if ($dryRun) {
        $data = [
            'status' => $changes === [] ? 'ok' : 'warning',
            'mode' => 'dry-run',
            'manifest_runtime' => $manifest['runtime'] ?? 'unknown',
            'manifest_mode' => $manifest['mode'] ?? 'unknown',
            'package_source_ref' => $sourceRef,
            'latest_available_tag' => $latestTag,
            'target_ref' => $targetRef !== '' ? $targetRef : null,
            'detected_changes' => $changes,
            'file_actions' => $fileActions,
            'deprecated_files' => $deprecated,
            'package_verify_status' => $verify['status'] ?? 'unknown',
        ];
        $written = aiCliWriteArtifact($root, 'upgrade', 'php tools/ai/ai.php upgrade --dry-run', $data, $changes === [] ? 'ok' : 'warning', null, 'If changes look safe, run upgrade --apply.');
        fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
        return 0;
    }

    $mode = (string) ($manifest['mode'] ?? 'sidecar-only');
    $backupId = aiParseArg($args, 'backup') ?? '';
    if ($backupId === '') {
        $data = [
            'status' => 'blocked',
            'reason' => 'upgrade apply requires explicit backup id',
            'next_action' => 'php tools/ai/ai.php install --backup-only --apply --mode ' . $mode,
        ];
        $written = aiCliWriteArtifact($root, 'upgrade', 'php tools/ai/ai.php upgrade --apply', $data, 'blocked', null, 'Create backup first, then rerun upgrade --apply --backup <id>.');
        fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
        return 1;
    }

    // Preserve user-modified owned files before the reinstall overwrites them. Their edits
    // are copied to .ai/conflicts/<timestamp>-upgrade/files/ so nothing is silently lost on upgrade.
    // Template files are preserved by the installer itself (skip-if-exists) and are not touched.
    $preserved = aiUpgradePreserveOwnedConflicts($root, $fileActions);

    // Remove computed `deprecated` files (manifest-recorded, no longer shipped). The
    // explicit backup ($backupId) already snapshotted them; user-modified ones are routed
    // to .ai/conflicts/<ts>-upgrade/removed/ before deletion. Only manifest paths are touched.
    $removedDeprecated = aiUpgradeRemoveDeprecated($root, $deprecated);

    $installArgs = aiUpgradeBuildApplyInstallArgs($mode, $backupId, $args);
    $exit = aiRunInstallWorkflow($root, $installArgs);
    $install = aiLoadArtifactData($root, 'install.json');
    $status = $exit === 0 ? 'ok' : 'failed';
    $data = [
        'status' => $status,
        'mode' => 'apply',
        'backup_id' => $backupId,
        'target_ref' => $targetRef !== '' ? $targetRef : null,
        'file_actions_preview' => $fileActions,
        'preserved_conflicts' => $preserved,
        'removed_deprecated' => $removedDeprecated,
        'install_status' => $install['status'] ?? 'unknown',
    ];
    $written = aiCliWriteArtifact($root, 'upgrade', 'php tools/ai/ai.php upgrade --apply', $data, $status, null, $status === 'ok' ? 'Upgrade apply completed; run adapter-validate.' : 'Upgrade apply failed; inspect install artifact.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return $exit;
}

/** @param list<string> $args @return list<string> */
function aiUpgradeBuildApplyInstallArgs(string $mode, string $backupId, array $args): array
{
    // Force the reinstall so owned/auto-update files are actually rewritten with the new kit
    // version. Without --force the planner marks differing files SKIP_EXISTING_UNMANAGED and the
    // upgrade becomes a no-op. User edits to owned files are already preserved above.
    $installArgs = ['--apply', '--reinstall', '--force', '--mode', $mode, '--backup', $backupId, '--no-interaction'];
    if (in_array('--agent', $args, true)) {
        $installArgs[] = '--agent';
    }
    if (in_array('--ci', $args, true)) {
        $installArgs[] = '--ci';
    }

    return $installArgs;
}

function aiRunAdapterValidate(string $root): int
{
    $lock = aiLoadArtifactData($root, 'package-verify.json');
    $manifestPath = aiInstallManifestPath($root);
    $derivedManifestPath = aiInstallDerivedManifestPath($root);
    $manifestExists = is_file($manifestPath);
    $derivedExists = is_file($derivedManifestPath);
    $derivedMatches = false;
    if ($manifestExists && $derivedExists) {
        $derivedMatches = hash_file('sha256', $manifestPath) === hash_file('sha256', $derivedManifestPath);
    }
    $status = ($lock['status'] ?? 'unknown') === 'ok' && $manifestExists ? 'ok' : 'warning';
    if ($manifestExists && $derivedExists && !$derivedMatches) {
        $status = 'warning';
    }
    $data = [
        'status' => $status,
        'package_verify_status' => $lock['status'] ?? 'unknown',
        'install_manifest_present' => $manifestExists,
        'derived_install_manifest_present' => $derivedExists,
        'manifest_drift_detected' => $manifestExists && $derivedExists ? !$derivedMatches : null,
        'checks' => ['package-verify artifact', 'instruction-audit artifact', 'install manifest present', 'derived manifest drift'],
    ];
    $written = aiCliWriteArtifact($root, 'adapter-validate', 'php tools/ai/ai.php adapter-validate', $data, $status, null, 'Run package-verify and audit-instructions first if missing.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

/**
 * Remove kit-installed files using the manifest's ownership classes.
 *
 * - owned / rendered files: removed (they are kit-managed).
 * - template files: preserved by default (user owns them); removed only with --purge.
 * - .ai/project.yml and .ai/ runtime state: preserved unless --purge.
 * Empty parent directories left behind by removed files are pruned best-effort.
 * --dry-run (default) reports the plan and writes nothing.
 */
function aiRunUninstallWorkflow(string $root, array $args): int
{
    aiInstallerAssertLockCompatible($root);

    $manifestPath = aiInstallManifestPath($root);
    if (!is_file($manifestPath)) {
        $data = ['status' => 'blocked', 'reason' => 'no install manifest found; nothing to uninstall'];
        $written = aiCliWriteArtifact($root, 'uninstall', 'php tools/ai/ai.php uninstall', $data, 'blocked', null, 'Install manifest absent; nothing to remove.');
        fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
        return 1;
    }

    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    if (!is_array($manifest) || !is_array($manifest['files'] ?? null)) {
        throw new RuntimeException('Invalid install manifest JSON at .ai-install-manifest.json');
    }

    $apply = in_array('--apply', $args, true);
    $dryRun = !$apply;
    $purge = in_array('--purge', $args, true);

    $toRemove = [];
    $preserved = [];
    foreach ($manifest['files'] as $rel => $meta) {
        $rel = (string) $rel;
        $ownership = is_array($meta) ? (string) ($meta['ownership'] ?? 'owned') : 'owned';
        $isTemplate = $ownership === 'template';
        if ($isTemplate && !$purge) {
            $preserved[] = ['file' => $rel, 'reason' => 'template (user-owned); use --purge to remove'];
            continue;
        }
        $toRemove[] = [
            'file' => $rel,
            'ownership' => $ownership,
            'installed_hash' => is_array($meta) ? (string) ($meta['installed_hash'] ?? '') : '',
        ];
    }

    $removed = [];
    $missing = [];
    if ($apply) {
        $removedDirs = [];
        foreach ($toRemove as $entry) {
            $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry['file']);
            if (is_file($abs) || is_link($abs)) {
                if (@unlink($abs)) {
                    $removed[] = $entry['file'];
                    $removedDirs[dirname($abs)] = true;
                }
            } elseif (is_dir($abs)) {
                // Never blindly recursive-delete a kit-installed directory: it may now contain
                // user-added files. Only remove it when its current fingerprint still matches the
                // hash recorded at install time (i.e. it is pristine kit content). Otherwise
                // preserve the whole directory and surface it for manual review.
                $expected = (string) $entry['installed_hash'];
                $current = aiHashPath($abs);
                if ($expected !== '' && str_starts_with($expected, 'sha256:') && $current === $expected) {
                    aiInstallerDeleteTree($abs);
                    $removed[] = $entry['file'];
                    $removedDirs[dirname($abs)] = true;
                } else {
                    $preserved[] = [
                        'file' => $entry['file'],
                        'reason' => 'directory contains changes since install (possibly user-added files); preserved for manual review',
                    ];
                }
            } else {
                $missing[] = $entry['file'];
            }
        }
        // Best-effort prune of now-empty parent directories, gated to lock createdDirs so
        // only kit-created directories can be removed (never pre-existing user directories).
        $createdDirs = aiInstallerReadLockCreatedDirs($root);
        foreach (array_keys($removedDirs) as $dir) {
            aiUninstallPruneEmptyParents($dir, $root, $createdDirs);
        }
        // Remove the manifest itself last (and .ai/ state when purging).
        @unlink($manifestPath);
        if ($purge) {
            aiInstallerDeleteTree($root . DIRECTORY_SEPARATOR . '.ai');
        }
    }

    $status = 'ok';
    $data = [
        'status' => $status,
        'mode' => $dryRun ? 'dry-run' : 'apply',
        'purge' => $purge,
        'planned_removals' => array_map(static fn(array $e): string => $e['file'], $toRemove),
        'preserved' => $preserved,
        'removed' => $removed,
        'missing' => $missing,
        'removed_count' => count($removed),
        'preserved_count' => count($preserved),
    ];
    $summaryNext = $dryRun
        ? 'Dry-run only; rerun with --apply to remove. Add --purge to also remove template files and .ai/ state.'
        : 'Uninstall applied. Template files preserved unless --purge was used.';
    $written = aiCliWriteArtifact($root, 'uninstall', 'php tools/ai/ai.php uninstall', $data, $status, null, $summaryNext);
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

/**
 * Remove empty directories upward from $dir until reaching (but not removing) $root.
 *
 * P3-e: a directory is only pruned when it is empty AND the kit recorded it in the lock's
 * createdDirs allowlist. A pre-existing user directory that merely became empty is preserved,
 * and a non-empty directory is never touched. This is a single-level rmdir walk, never a
 * recursive delete.
 *
 * @param list<string> $createdDirs Repo-relative directory paths the kit created (lock createdDirs).
 */
function aiUninstallPruneEmptyParents(string $dir, string $root, array $createdDirs = []): void
{
    $root = rtrim($root, '/\\');
    $dir = rtrim($dir, '/\\');
    $allowed = array_fill_keys(array_map(
        static fn(string $d): string => trim(str_replace('\\', '/', $d), '/'),
        $createdDirs
    ), true);

    while ($dir !== '' && $dir !== $root && str_starts_with($dir, $root) && is_dir($dir)) {
        $rel = trim(str_replace('\\', '/', substr($dir, strlen($root))), '/');
        if (!isset($allowed[$rel])) {
            // Not a kit-created directory: never remove it, even if empty.
            return;
        }
        $entries = @scandir($dir);
        if ($entries === false) {
            return;
        }
        $entries = array_diff($entries, ['.', '..']);
        if ($entries !== []) {
            return;
        }
        if (!@rmdir($dir)) {
            return;
        }
        $dir = rtrim(dirname($dir), '/\\');
    }
}

/**
 * P4-b: restore --from <ts> [--path <p>] — checksum-gated copy-back from a backup snapshot.
 *
 * Thin wrapper over the canonical (checksum-gated) backup rollback machinery: --from selects
 * the backup id/timestamp, --path narrows to a single file (mapped to --only). Dry-run by
 * default writes nothing; --apply restores and appends an audit entry to .ai/logs/.
 */
function aiRunRestoreWorkflow(string $root, array $args): int
{
    $from = aiParseArg($args, 'from') ?? '';
    if ($from === '') {
        throw new RuntimeException('restore requires --from <backup-id-or-timestamp>');
    }

    $dryRun = in_array('--dry-run', $args, true) || !in_array('--apply', $args, true);
    $force = in_array('--force', $args, true);
    $path = aiParseArg($args, 'path');
    $only = $path !== null && $path !== '' ? [$path] : [];

    $data = aiInstallBackupRollback($root, $from, !$dryRun, $force, $only);
    $data['from'] = $from;
    if ($path !== null && $path !== '') {
        $data['path'] = $path;
    }

    $artifactStatus = ($data['status'] ?? 'ok') === 'blocked' ? 'blocked' : 'ok';

    if (!$dryRun && $artifactStatus !== 'blocked') {
        aiRestoreAppendAuditLog($root, $data);
    }

    $next = $dryRun
        ? 'Dry-run complete; rerun with --apply to restore from the backup snapshot.'
        : ($artifactStatus === 'blocked'
            ? 'Restore blocked by checksum conflicts; rerun with --force only if intentional.'
            : 'Restore applied from backup snapshot; see .ai/logs/ for the audit entry.');
    $written = aiCliWriteArtifact($root, 'restore', 'php tools/ai/ai.php restore --from ' . $from, $data, $artifactStatus, null, $next);
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);

    return $artifactStatus === 'blocked' ? 1 : 0;
}

/**
 * Append an append-only restore audit entry under .ai/logs/restore-<ts>.json.
 *
 * @param array<string,mixed> $data
 */
function aiRestoreAppendAuditLog(string $root, array $data): void
{
    $logsDir = $root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logsDir)) {
        mkdir($logsDir, AI_DIR_MODE, true);
    }
    $stamp = gmdate('Ymd\THis\Z');
    $entry = [
        'op' => 'restore',
        'at' => gmdate('c'),
        'from' => (string) ($data['from'] ?? ''),
        'path' => $data['path'] ?? null,
        'status' => (string) ($data['status'] ?? 'ok'),
        'restored_targets' => array_values($data['restored_targets'] ?? []),
        'deleted_targets' => array_values($data['deleted_targets'] ?? []),
    ];
    $file = $logsDir . DIRECTORY_SEPARATOR . 'restore-' . $stamp . '.json';
    file_put_contents($file, json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

function aiRunRollbackWorkflow(string $root, array $args): int
{
    $backupId = aiParseArg($args, 'backup') ?? '';
    if ($backupId === '') {
        throw new RuntimeException('rollback requires --backup <backup-id>');
    }

    $dryRun = in_array('--dry-run', $args, true) || !in_array('--apply', $args, true);
    $data = aiInstallBackupRollback($root, $backupId, !$dryRun, in_array('--force', $args, true), aiInstallBackupParseOnlyArgs($args));
    $artifactStatus = ($data['status'] ?? 'ok') === 'blocked' ? 'blocked' : 'ok';
    $written = aiCliWriteArtifact($root, 'rollback', 'php tools/ai/ai.php rollback --backup ' . $backupId, $data, $artifactStatus, null, $dryRun ? 'Dry-run complete; use --apply to restore from backup.' : ($artifactStatus === 'blocked' ? 'Rollback blocked by conflicts; rerun with --force only if intentional.' : 'Rollback applied from backup snapshot.'));
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return ($data['status'] ?? 'ok') === 'blocked' ? 1 : 0;
}
