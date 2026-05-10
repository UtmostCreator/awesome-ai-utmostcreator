<?php

declare(strict_types=1);

function aiRunVerify(string $root, array $args): int
{
    $strict = in_array('--strict', $args, true);
    $jsonMode = in_array('--json', $args, true);
    $generatedDir = aiCliGeneratedDir($root);
    $logDirName = 'verify-' . date('Ymd-His');
    $logBaseDir = $generatedDir . DIRECTORY_SEPARATOR . 'logs';
    $logDir = $logBaseDir . DIRECTORY_SEPARATOR . $logDirName;
    $logDirLabel = 'docs/ai/generated/logs/' . $logDirName;
    $logFilePrefix = '';
    if (!is_dir($logDir) && !mkdir($logDir, AI_DIR_MODE, true) && !is_dir($logDir)) {
        if (is_dir($logBaseDir)) {
            $logDir = $logBaseDir;
            $logDirLabel = 'docs/ai/generated/logs';
            $logFilePrefix = $logDirName . '-';
        } else {
            $fallbackBase = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'app-configs-ai-logs';
            $fallbackDir = $fallbackBase . DIRECTORY_SEPARATOR . $logDirName;
            if (!is_dir($fallbackDir) && !mkdir($fallbackDir, AI_DIR_MODE, true) && !is_dir($fallbackDir)) {
                throw new RuntimeException('Could not create verify log dir');
            }
            $logDir = $fallbackDir;
            $logDirLabel = str_replace('\\', '/', $fallbackDir);
        }
    }

    $checks = [
        'validate-ai-config' => 'php tools/ai/validate-ai-config.php',
        'validate-ai-catalog' => 'php tools/ai/validate-ai-catalog.php',
        'generate-ai-catalog-check' => 'php tools/ai/generate-ai-catalog.php --check',
        'generate-repo-structure-check' => 'php tools/ai/generate-repo-structure.php --check --with-scc',
        'install-docs-check' => 'php tools/ai/ai.php install-docs --check',
        'advisor-check' => 'php tools/ai/ai.php advisor --check',
    ];

    $results = [];
    $failed = [];
    foreach ($checks as $name => $command) {
        $run = aiRunCommand($root, $command);
        $autoFixApplied = false;

        if ($run['exit'] !== 0 && $name === 'generate-ai-catalog-check') {
            $regen = aiRunCommand($root, 'php tools/ai/generate-ai-catalog.php');
            if ($regen['exit'] === 0) {
                $run = aiRunCommand($root, $command);
                $autoFixApplied = true;
            }
        }

        if ($run['exit'] !== 0 && $name === 'generate-repo-structure-check') {
            $regen = aiRunCommand($root, 'php tools/ai/generate-repo-structure.php --with-scc');
            if ($regen['exit'] === 0) {
                $run = aiRunCommand($root, $command);
                $autoFixApplied = true;
            }
        }

        $results[] = [
            'name' => $name,
            'command' => $command,
            'exit' => $run['exit'],
            'passed' => $run['exit'] === 0,
            'auto_fix_applied' => $autoFixApplied,
            'log' => $logDirLabel . '/' . $logFilePrefix . $name . '.log',
        ];
        file_put_contents($logDir . DIRECTORY_SEPARATOR . $logFilePrefix . $name . '.log', "STDOUT:\n" . $run['stdout'] . "\nSTDERR:\n" . $run['stderr']);
        if ($run['exit'] !== 0) {
            $failed[] = $name;
        }
    }

    $status = $failed === [] ? 'passed' : 'failed';
    $recommended = $failed === []
        ? 'Run next to choose commit or PR closeout action.'
        : 'Open verify logs and fix the first failing check before proceeding.';

    $findings = [];
    foreach ($failed as $name) {
        $findings[] = [
            'severity' => 'ERROR',
            'code' => 'CHECK_FAILED',
            'file' => null,
            'message' => 'Verification check failed: ' . $name,
            'suggested_fix' => 'Inspect docs/ai/generated logs and rerun verify.',
        ];
    }

    $placeholderArtifact = aiLoadArtifactData($root, 'placeholders.json');
    $placeholderCount = (int) (($placeholderArtifact['data']['count'] ?? 0));
    if ($placeholderCount > 0) {
        $findings[] = [
            'severity' => $strict ? 'ERROR' : 'WARNING',
            'code' => 'UNFILLED_REQUIRED_PLACEHOLDER',
            'file' => 'docs/ai',
            'message' => 'Unresolved placeholders detected.',
            'suggested_fix' => 'Run php tools/ai/ai.php placeholders --fail and update placeholders.',
        ];
        $findings[] = [
            'severity' => $strict ? 'WARNING' : 'INFO',
            'code' => 'UNFILLED_OPTIONAL_PLACEHOLDER',
            'file' => 'docs/ai',
            'message' => 'Optional placeholders may remain unresolved.',
            'suggested_fix' => 'Review placeholder list and fill values as needed for strict mode.',
        ];
    }

    $manifestPresent = is_file(aiInstallManifestPath($root));
    if (!$manifestPresent) {
        $findings[] = [
            'severity' => 'ERROR',
            'code' => 'MISSING_REQUIRED_FILE',
            'file' => '.ai-install-manifest.json',
            'message' => 'Canonical install manifest is missing.',
            'suggested_fix' => 'Run install apply to create canonical install manifest.',
        ];
    } else {
        $canonicalManifest = json_decode((string) file_get_contents(aiInstallManifestPath($root)), true);
        if (!is_array($canonicalManifest)) {
            $findings[] = [
                'severity' => 'ERROR',
                'code' => 'MISSING_REQUIRED_FILE',
                'file' => '.ai-install-manifest.json',
                'message' => 'Canonical install manifest is invalid JSON.',
                'suggested_fix' => 'Re-run install apply to regenerate manifest.',
            ];
        } else {
            $derivedManifestPath = aiInstallDerivedManifestPath($root);
            if (is_file($derivedManifestPath)) {
                if (hash_file('sha256', aiInstallManifestPath($root)) !== hash_file('sha256', $derivedManifestPath)) {
                    $findings[] = [
                        'severity' => $strict ? 'WARNING' : 'INFO',
                        'code' => 'GENERATED_DOC_OUT_OF_SYNC',
                        'file' => 'docs/ai/generated/install-manifest.json',
                        'message' => 'Derived install manifest is out of sync with canonical manifest.',
                        'suggested_fix' => 'Regenerate derived install artifacts by rerunning install or sync command.',
                    ];
                }
            }

            $manifestFiles = is_array($canonicalManifest['files'] ?? null) ? $canonicalManifest['files'] : [];
            foreach ($manifestFiles as $rel => $meta) {
                if (!is_array($meta)) {
                    continue;
                }
                $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $rel);
                if (!file_exists($abs)) {
                    $findings[] = [
                        'severity' => 'ERROR',
                        'code' => 'MISSING_REQUIRED_FILE',
                        'file' => (string) $rel,
                        'message' => 'Required managed file is missing.',
                        'suggested_fix' => 'Restore via install repair or rollback.',
                    ];
                    continue;
                }
                $currentHash = aiHashPath($abs);
                $installedHash = (string) ($meta['installed_hash'] ?? 'unknown');
                if ($installedHash !== 'unknown' && $currentHash !== $installedHash) {
                    $findings[] = [
                        'severity' => $strict ? 'ERROR' : 'WARNING',
                        'code' => 'HASH_DRIFT_MANAGED_FILE',
                        'file' => (string) $rel,
                        'message' => 'Managed file hash drift detected.',
                        'suggested_fix' => 'Review local customization and merge with source updates.',
                    ];
                    $findings[] = [
                        'severity' => 'INFO',
                        'code' => 'CUSTOMISED_MANAGED_FILE',
                        'file' => (string) $rel,
                        'message' => 'Managed file appears customized locally.',
                        'suggested_fix' => 'Keep or merge local changes intentionally.',
                    ];
                }
            }

            $managedPaths = is_array($canonicalManifest['managed_paths'] ?? null) ? $canonicalManifest['managed_paths'] : [];
            foreach ($managedPaths as $managedPath) {
                if (!is_string($managedPath) || $managedPath === '') {
                    continue;
                }
                $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $managedPath);
                if (!file_exists($abs)) {
                    $findings[] = [
                        'severity' => 'WARNING',
                        'code' => 'ORPHANED_MANAGED_FILE',
                        'file' => $managedPath,
                        'message' => 'Managed path listed in manifest is missing.',
                        'suggested_fix' => 'Reinstall managed adapters or clean manifest state.',
                    ];
                }
            }

            $sourceRepo = (string) (($canonicalManifest['package']['source_repository'] ?? 'unknown'));
            if ($sourceRepo === 'unknown' || $sourceRepo === '') {
                $findings[] = [
                    'severity' => 'ERROR',
                    'code' => 'PACKAGE_SOURCE_UNAVAILABLE',
                    'file' => '.ai-install-manifest.json',
                    'message' => 'Package source identity is missing.',
                    'suggested_fix' => 'Record source repository and ref in canonical manifest.',
                ];
            } else {
                $tags = [];
                $tagExit = 0;
                exec('git -C ' . escapeshellarg($root) . ' tag --sort=-v:refname', $tags, $tagExit);
                if ($tagExit !== 0) {
                    $findings[] = [
                        'severity' => 'WARNING',
                        'code' => 'PACKAGE_SOURCE_UNAVAILABLE',
                        'file' => '.ai-install-manifest.json',
                        'message' => 'Unable to query git tags for source-aware upgrade checks.',
                        'suggested_fix' => 'Ensure git metadata is available before upgrade.',
                    ];
                } else {
                    $installedRef = (string) (($canonicalManifest['package']['source_ref'] ?? 'unknown'));
                    $latestTag = $tags !== [] ? (string) $tags[0] : 'unknown';
                    if ($installedRef !== 'unknown' && $latestTag !== 'unknown' && $installedRef !== $latestTag) {
                        $findings[] = [
                            'severity' => 'INFO',
                            'code' => 'NEWER_PACKAGE_AVAILABLE',
                            'file' => '.ai-install-manifest.json',
                            'message' => 'A newer package tag appears available.',
                            'suggested_fix' => 'Run upgrade --dry-run and review file actions.',
                        ];
                    }
                }
            }
        }
    }

    $scriptsAiDir = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'ai';
    if (is_dir($scriptsAiDir)) {
        $requiredTools = ['bash', 'git', 'jq', 'rg', 'repomix', 'scc'];
        $optionalTools = ['fd', 'gh', 'fzf', 'bat', 'delta', 'yq', 'shellcheck', 'semgrep', 'ast-grep'];
        foreach ($requiredTools as $tool) {
            if (!aiCliCommandExists($tool)) {
                $findings[] = [
                    'severity' => 'ERROR',
                    'code' => 'MISSING_REQUIRED_TOOL',
                    'file' => 'scripts/ai',
                    'message' => 'Required tool missing: ' . $tool,
                    'suggested_fix' => 'Install required scripts-pack dependency.',
                ];
            }
        }
        foreach ($optionalTools as $tool) {
            if (!aiCliCommandExists($tool)) {
                $findings[] = [
                    'severity' => $strict ? 'WARNING' : 'INFO',
                    'code' => 'MISSING_OPTIONAL_TOOL',
                    'file' => 'scripts/ai',
                    'message' => 'Optional tool missing: ' . $tool,
                    'suggested_fix' => 'Install optional tooling for faster workflows.',
                ];
            }
        }
    }

    $hooksWired = is_dir($root . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . 'hooks') || is_dir($root . DIRECTORY_SEPARATOR . '.husky') || is_file($root . DIRECTORY_SEPARATOR . '.lefthook.yml');
    $hookFiles = [
        $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'hooks' . DIRECTORY_SEPARATOR . 'pre-commit.sh',
        $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'hooks' . DIRECTORY_SEPARATOR . 'commit-msg.sh',
    ];
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    foreach ($hookFiles as $hookFile) {
        if (!is_file($hookFile)) {
            continue;
        }
        if ($isWindows) {
            $findings[] = [
                'severity' => 'INFO',
                'code' => 'HOOK_EXEC_CHECK_PLATFORM_LIMIT',
                'file' => str_replace('\\', '/', substr($hookFile, strlen($root) + 1)),
                'message' => 'Executable bit check skipped on Windows.',
                'suggested_fix' => 'Verify hook execution manually on Windows.',
            ];
            continue;
        }
        if ($hooksWired && !is_executable($hookFile)) {
            $findings[] = [
                'severity' => 'ERROR',
                'code' => 'UNRESOLVED_MANUAL_CONFLICT',
                'file' => str_replace('\\', '/', substr($hookFile, strlen($root) + 1)),
                'message' => 'Hook file is not executable while hooks appear wired.',
                'suggested_fix' => 'Run chmod +x on hook script files.',
            ];
        } elseif (!$hooksWired && !is_executable($hookFile)) {
            $findings[] = [
                'severity' => 'WARNING',
                'code' => 'HOOK_NOT_WIRED',
                'file' => str_replace('\\', '/', substr($hookFile, strlen($root) + 1)),
                'message' => 'Hook pack files exist but hooks are not wired.',
                'suggested_fix' => 'Use php tools/ai/ai.php hooks --driver <driver>.',
            ];
        }
    }

    $counts = ['errors' => 0, 'warnings' => 0, 'info' => 0];
    foreach ($findings as $finding) {
        $sev = strtolower((string) ($finding['severity'] ?? 'info'));
        if ($sev === 'error') {
            $counts['errors']++;
        } elseif ($sev === 'warning') {
            $counts['warnings']++;
        } else {
            $counts['info']++;
        }
    }
    $verifyStatus = ($counts['errors'] > 0 || ($strict && $counts['warnings'] > 0)) ? 'failed' : 'passed';

    $data = [
        'status' => $verifyStatus,
        'mode' => $strict ? 'strict' : 'default',
        'summary' => $counts,
        'check_count' => count($results),
        'failed_checks' => $failed,
        'results' => $results,
        'findings' => $findings,
        'log_dir' => $logDirLabel,
    ];

    $written = aiCliWriteArtifact($root, 'verify', 'php tools/ai/ai.php verify --changed', $data, $verifyStatus, null, $recommended);
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    if ($jsonMode) {
        fwrite(STDOUT, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }
    if ($counts['errors'] > 0) {
        return 2;
    }
    if ($strict && $counts['warnings'] > 0) {
        return 2;
    }
    return 0;
}
