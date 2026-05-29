<?php

declare(strict_types=1);

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
        ];
        $written = aiCliWriteArtifact($root, 'install', 'php tools/ai/ai.php install --dry-run', $data, 'ok', null, 'Run install --backup-only before install --apply.');
        fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
        return 0;
    }

    $backupOnly = in_array('--backup-only', $args, true);
    if ($backupOnly) {
        $planData = aiLoadArtifactData($root, 'adapter-plan.json');
        $creates = $planData['data']['create'] ?? [];
        $modifies = $planData['data']['modify'] ?? [];
        $targets = [];
        foreach ([$creates, $modifies] as $list) {
            if (!is_array($list)) {
                continue;
            }
            foreach ($list as $item) {
                if (!is_string($item) || $item === '') {
                    continue;
                }
                $targets[] = $item;
            }
        }
        $targets = array_values(array_unique($targets));

        $backupRoot = $root . DIRECTORY_SEPARATOR . '.ai-backups';
        if (!is_dir($backupRoot)) {
            mkdir($backupRoot, AI_DIR_MODE, true);
        }
        $backupId = 'install-' . gmdate('Ymd-His');
        $dir = $backupRoot . DIRECTORY_SEPARATOR . $backupId;
        mkdir($dir, AI_DIR_MODE, true);

        $zipPath = $dir . DIRECTORY_SEPARATOR . 'backup.zip';
        $filesDir = $dir . DIRECTORY_SEPARATOR . 'files';
        $zipStatus = 'skipped';
        $dirStatus = 'skipped';
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                foreach ($targets as $rel) {
                    $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, rtrim($rel, '/'));
                    if (is_file($abs)) {
                        $zip->addFile($abs, str_replace('\\', '/', rtrim($rel, '/')));
                    }
                }
                $zip->close();
                $zipStatus = 'created';
            }
        }

        if ($zipStatus !== 'created') {
            if (!is_dir($filesDir)) {
                mkdir($filesDir, AI_DIR_MODE, true);
            }
            foreach ($targets as $rel) {
                $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, rtrim($rel, '/'));
                $snapshot = $filesDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, rtrim($rel, '/'));
                if (is_file($abs)) {
                    $parent = dirname($snapshot);
                    if (!is_dir($parent)) {
                        mkdir($parent, AI_DIR_MODE, true);
                    }
                    copy($abs, $snapshot);
                }
            }
            $dirStatus = 'created';
        }

        $manifest = [
            'backup_id' => $backupId,
            'created_at_utc' => gmdate('c'),
            'zip_status' => $zipStatus,
            'directory_status' => $dirStatus,
            'targets' => $targets,
        ];
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        $data = [
            'status' => 'ok',
            'mode' => $mode,
            'runtime_mode' => $runtimeMode,
            'backup_id' => $backupId,
            'backup_dir' => '.ai-backups/' . $backupId,
            'zip_status' => $zipStatus,
            'directory_status' => $dirStatus,
            'target_count' => count($targets),
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
        $backupManifestPath = $root . DIRECTORY_SEPARATOR . '.ai-backups' . DIRECTORY_SEPARATOR . $backupId . DIRECTORY_SEPARATOR . 'manifest.json';
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
    $cmd = 'php tools/ai/install-ai-kit.php --target . --runtime ' . escapeshellarg($runtime) . ' --profile ' . escapeshellarg((string) $installConfig['profile']);
    if ($mode === 'sidecar-only') {
        $cmd .= ' --no-base';
    }
    if (!empty($installConfig['force'])) {
        $cmd .= ' --force';
    }
    if (!empty($installConfig['allowCoreOverwrite'])) {
        $cmd .= ' --allow-core-overwrite';
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

    $run = aiRunCommand($root, $cmd);
    $status = $run['exit'] === 0 ? 'ok' : 'failed';

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
        $manifest = [
            'schema_version' => 1,
            'installer_version' => '0.2.0',
            'installed_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
            'profile' => (string) $installConfig['profile'],
            'mode' => $mode,
            'runtime' => $runtime,
            'package' => [
                'name' => 'ai-universal-rules',
                'distribution' => 'git-tag',
                'source_repository' => 'UtmostCreator/app-configs',
                'source_remote' => 'origin',
                'source_ref' => 'unknown',
                'source_commit' => 'unknown',
                'installed_version' => 'unknown',
            ],
            'managed_paths' => $managed,
            'packs' => $selectedPacks,
            'toolchain' => [
                'checked' => !empty($installConfig['toolchainCheck']),
                'install_plan_printed' => !empty($installConfig['toolchainInstallPlan']),
                'applied' => !empty($installConfig['toolchainApply']),
            ],
            'post_install_script' => $postInstallScript,
            'package_lock_sha256' => is_file(aiPackageLockPath($root)) ? 'sha256:' . hash_file('sha256', aiPackageLockPath($root)) : 'unknown',
        ];
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

    $profileMap = ['1' => 'minimal', '2' => 'copilot', '3' => 'opencode', '4' => 'dual', '5' => 'accelerated', '6' => 'full-governance', '7' => 'custom'];
    $profileInput = strtolower(aiPromptLine('Select profile: [1] minimal, [2] copilot, [3] opencode, [4] dual, [5] accelerated, [6] full-governance, [7] custom (default 4): '));
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
    if (in_array('hooks-pack', $with, true) || $allFeatures || in_array($profile, ['full-governance'], true)) {
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
            fwrite(STDOUT, "OK: created backup .ai-backups/{$backupId}/\n");
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

function aiRunUpgradeWorkflow(string $root, array $args): int
{
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

        $status = 'unchanged';
        $action = 'skip';
        if ($targetCurrentHash === 'missing') {
            $status = 'missing';
            $action = 'restore or remove from manifest';
        } elseif ($sourceCurrentHash !== $sourceAtInstall && $targetCurrentHash === $installedAtInstall) {
            $status = 'source-updated';
            $action = 'auto-update';
        } elseif ($sourceCurrentHash === $sourceAtInstall && $targetCurrentHash !== $installedAtInstall) {
            $status = 'local-customised';
            $action = 'preserve and review';
        } elseif ($sourceCurrentHash !== $sourceAtInstall && $targetCurrentHash !== $installedAtInstall) {
            $status = 'both-changed';
            $action = 'merge-required';
        }

        $fileActions[] = [
            'file' => (string) $target,
            'status' => $status,
            'action' => $action,
            'source' => $sourceRel,
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

    $installArgs = ['--apply', '--reinstall', '--mode', $mode, '--backup', $backupId, '--no-interaction'];
    if (in_array('--agent', $args, true)) {
        $installArgs[] = '--agent';
    }
    if (in_array('--ci', $args, true)) {
        $installArgs[] = '--ci';
    }
    $exit = aiRunInstallWorkflow($root, $installArgs);
    $install = aiLoadArtifactData($root, 'install.json');
    $status = $exit === 0 ? 'ok' : 'failed';
    $data = [
        'status' => $status,
        'mode' => 'apply',
        'backup_id' => $backupId,
        'target_ref' => $targetRef !== '' ? $targetRef : null,
        'file_actions_preview' => $fileActions,
        'install_status' => $install['status'] ?? 'unknown',
    ];
    $written = aiCliWriteArtifact($root, 'upgrade', 'php tools/ai/ai.php upgrade --apply', $data, $status, null, $status === 'ok' ? 'Upgrade apply completed; run adapter-validate.' : 'Upgrade apply failed; inspect install artifact.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return $exit;
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

function aiRunRollbackWorkflow(string $root, array $args): int
{
    $backupId = aiParseArg($args, 'backup') ?? '';
    if ($backupId === '') {
        throw new RuntimeException('rollback requires --backup <backup-id>');
    }

    $dryRun = in_array('--dry-run', $args, true) || !in_array('--apply', $args, true);
    $base = $root . DIRECTORY_SEPARATOR . '.ai-backups' . DIRECTORY_SEPARATOR . $backupId;
    $manifestPath = $base . DIRECTORY_SEPARATOR . 'manifest.json';
    if (!is_file($manifestPath)) {
        throw new RuntimeException('backup manifest not found for backup id: ' . $backupId);
    }
    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    if (!is_array($manifest)) {
        throw new RuntimeException('invalid backup manifest JSON for backup id: ' . $backupId);
    }

    $targets = $manifest['targets'] ?? [];
    $zipPath = $base . DIRECTORY_SEPARATOR . 'backup.zip';
    $filesDir = $base . DIRECTORY_SEPARATOR . 'files';
    $restored = [];
    if (!$dryRun && is_file($zipPath) && class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) === true) {
            $zip->extractTo($root);
            $zip->close();
            $restored = is_array($targets) ? $targets : [];
        }
    }
    if (!$dryRun && $restored === [] && is_dir($filesDir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($filesDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $item) {
            $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($filesDir) + 1));
            $dest = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if ($item->isDir()) {
                if (!is_dir($dest)) {
                    mkdir($dest, AI_DIR_MODE, true);
                }
                continue;
            }
            $parent = dirname($dest);
            if (!is_dir($parent)) {
                mkdir($parent, AI_DIR_MODE, true);
            }
            copy($item->getPathname(), $dest);
            $restored[] = $rel;
        }
    }

    $data = [
        'status' => 'ok',
        'backup' => $backupId,
        'dry_run' => $dryRun,
        'target_count' => is_array($targets) ? count($targets) : 0,
        'restored_targets' => $restored,
    ];
    $written = aiCliWriteArtifact($root, 'rollback', 'php tools/ai/ai.php rollback --backup ' . $backupId, $data, 'ok', null, $dryRun ? 'Dry-run complete; use --apply to restore from backup.' : 'Rollback applied from backup snapshot.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}
