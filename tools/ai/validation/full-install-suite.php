<?php

declare(strict_types=1);

// Extracted verbatim from tools/ai/full-install-validation.php as part of
// docs/tickets/arch-todo-full-install-validation-extraction-20260706-230257/plan.md (Phase 2).
// Runtime dependency: the CLI wrapper (tools/ai/full-install-validation.php) must require_once
// watchdog-runner.php, repo-inventory.php, and surface-lint.php (in that order) before this file,
// so that cliArg/normalizePhp/runRequired/addStage/markFailure/logLine, listTrackedFiles/
// buildInventory/buildYamlInventory, and lintShellScripts/lintPhpFiles/lintJsonFiles/lintYamlFiles
// are already defined when aiRunFullInstallValidation() executes.

function runRegisteredScriptsDryRun(array &$report, string $root, string $phpBin, int $timeoutSec, int $idleTimeoutSec, int $heartbeatSec, int $retryCount, string $cancelFlag, string $liveLog): void
{
    require_once __DIR__ . '/../install/script-registry.php';
    $allowNonZero = [
        'install-mandatory-tools' => true,
        'prune-shipped-targets' => true,
    ];

    $rows = [];
    foreach (aiInstallerScriptRegistry() as $id => $_entry) {
        $cmd = normalizePhp($phpBin, 'php tools/ai/ai.php run-script ' . $id . ' --dry-run');
        $attempt = 0;
        $run = ['exit' => 1];
        do {
            $attempt++;
            $run = runCommandWatchdog($root, $cmd, $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog, 'run-script-' . $id);
            if (($run['exit'] ?? 1) === 0 || !empty($run['timed_out']) || !empty($run['cancelled'])) {
                break;
            }
        } while ($attempt <= $retryCount);

        $exitCode = (int) ($run['exit'] ?? 1);
        $stderr = trim((string) ($run['stderr'] ?? ''));
        $stdout = trim((string) ($run['stdout'] ?? ''));
        $allowed = isset($allowNonZero[$id]) && $exitCode !== 0 && $stderr === '' && str_contains($stdout, 'OK: wrote docs/ai/generated/scripts.json');

        if ($allowed) {
            $exitCode = 0;
        }

        $rows[] = ['id' => $id, 'exit' => $exitCode, 'attempts' => $attempt, 'allowed_nonzero' => $allowed];

        if ($exitCode !== 0) {
            markFailure($report, 'run-script-' . $id, 'run-script dry-run failed', ['stderr' => trim((string) ($run['stderr'] ?? '')), 'attempts' => $attempt]);
        }
    }
    $ok = true;
    foreach ($rows as $row) {
        if (($row['exit'] ?? 1) !== 0) {
            $ok = false;
            break;
        }
    }
    addStage($report, 'run-script-dry-run-all', $ok, ['scripts' => $rows]);
}

function writeReports(string $root, array $report): void
{
    $dir = $root . '/docs/ai/generated';
    if (!is_dir($dir)) {
        mkdir($dir, AI_DIR_MODE, true);
    }

    $jsonPath = $dir . '/full-install-validation.json';
    $mdPath = $dir . '/full-install-validation.md';

    file_put_contents($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

    $md = "# Full Install Validation\n\n";
    $md .= '- Status: `' . $report['status'] . "`\n";
    $md .= '- Generated at: `' . $report['generated_at'] . "`\n";
    $md .= '- Profile: `' . $report['profile'] . "`\n";
    $md .= '- Mode: `' . $report['mode'] . "`\n";
    $md .= '- Apply run: `' . ($report['apply'] ? 'yes' : 'no') . "`\n";
    $md .= '- Smoke mode: `' . ($report['smoke'] ? 'yes' : 'no') . "`\n";
    $md .= '- Release gate: `' . (!empty($report['release_gate']) ? 'yes' : 'no') . "`\n";
    $md .= '- Include phpunit: `' . (!empty($report['include_phpunit']) ? 'yes' : 'no') . "`\n";
    $md .= '- Include deep verify: `' . (!empty($report['include_deep_verify']) ? 'yes' : 'no') . "`\n";
    $md .= '- Timeout: `' . (int) $report['timeout_sec'] . "s`\n";
    $md .= '- Retries: `' . (int) $report['retries'] . "`\n";
    if (is_string($report['backup_id']) && $report['backup_id'] !== '') {
        $md .= '- Backup ID: `' . $report['backup_id'] . "`\n";
    }
    $md .= "\n## Stages\n\n";
    foreach ($report['stages'] as $stage) {
        $md .= '- `' . $stage['id'] . '` => `' . ($stage['ok'] ? 'ok' : 'failed') . "`\n";
    }
    $md .= "\n## Failures\n\n";
    if ($report['failures'] === []) {
        $md .= "- none\n";
    } else {
        foreach ($report['failures'] as $failure) {
            $md .= '- `' . $failure['id'] . '`: ' . $failure['message'] . "\n";
        }
    }
    $md .= "\n## Inventory\n\n";
    $md .= '- Shell files: `' . (int) ($report['shell_inventory']['total'] ?? 0) . "`\n";
    $md .= '- PHP files: `' . (int) ($report['php_inventory']['total'] ?? 0) . "`\n";
    $md .= '- JSON files: `' . (int) ($report['json_inventory']['total'] ?? 0) . "`\n";
    $md .= '- YAML files: `' . (int) ($report['yaml_inventory']['total'] ?? 0) . "`\n";
    $md .= '- Markdown files: `' . (int) ($report['markdown_inventory']['total'] ?? 0) . "`\n";
    $md .= '- SCC enabled for shell inventory: `' . (!empty($report['shell_inventory']['scc_enabled']) ? 'yes' : 'no') . "`\n";
    $md .= "\n## Cancellation\n\n";
    $md .= '- Create `docs/ai/generated/full-install-validation.cancel` to request cancellation during long-running stages.\n';

    file_put_contents($mdPath, $md);
}

function aiRunFullInstallValidation(array $argv): int
{
    $root = realpath(__DIR__ . '/..' . '/..' . '/..');

    defined('AI_DIR_MODE') || define('AI_DIR_MODE', 0755);

    if ($root === false) {
        fwrite(STDERR, "ERROR: unable to resolve repository root\n");
        return 1;
    }

    $phpBin = (string) (defined('PHP_BINARY') ? PHP_BINARY : 'php');
    $profile = cliArg('profile', 'full-governance');
    $mode = cliArg('mode', 'safe-merge');
    $withScc = in_array('--with-scc', $argv, true);
    $runApply = in_array('--apply', $argv, true);
    $smokeMode = in_array('--smoke', $argv, true);
    $releaseGate = in_array('--release-gate', $argv, true);
    $includePhpunit = in_array('--include-phpunit', $argv, true) || $releaseGate;
    $includeDeepVerify = in_array('--include-deep-verify', $argv, true) || $releaseGate;
    $timeoutSec = (int) cliArg('timeout-sec', '600');
    $idleTimeoutSec = (int) cliArg('idle-timeout-sec', '180');
    $retryCount = (int) cliArg('retries', '1');
    $heartbeatSec = (int) cliArg('heartbeat-sec', '5');
    $cancelFlag = $root . '/docs/ai/generated/full-install-validation.cancel';
    $liveLog = $root . '/docs/ai/generated/full-install-validation.log';

    if (in_array('--clear-cancel', $argv, true) && is_file($cancelFlag)) {
        @unlink($cancelFlag);
    }

    if (!is_dir(dirname($liveLog))) {
        mkdir(dirname($liveLog), AI_DIR_MODE, true);
    }
    file_put_contents($liveLog, '[' . gmdate('c') . "] start full-install-validation\n");

    $report = [
        'status' => 'passed',
        'generated_at' => gmdate('c'),
        'root' => $root,
        'profile' => $profile,
        'mode' => $mode,
        'with_scc' => $withScc,
        'apply' => $runApply,
        'smoke' => $smokeMode,
        'release_gate' => $releaseGate,
        'include_phpunit' => $includePhpunit,
        'include_deep_verify' => $includeDeepVerify,
        'timeout_sec' => $timeoutSec,
        'idle_timeout_sec' => $idleTimeoutSec,
        'heartbeat_sec' => $heartbeatSec,
        'retries' => $retryCount,
        'cancel_flag' => $cancelFlag,
        'log_file' => 'docs/ai/generated/full-install-validation.log',
        'stages' => [],
        'shell_inventory' => null,
        'php_inventory' => null,
        'json_inventory' => null,
        'yaml_inventory' => null,
        'markdown_inventory' => null,
        'backup_id' => null,
        'failures' => [],
        'notes' => [
            'Create docs/ai/generated/full-install-validation.cancel to request cancellation.',
        ],
    ];

    $hasPackageTemplates = is_dir($root . '/packages/ai-universal-rules/templates');

    if ($hasPackageTemplates) {
        runRequired($report, 'preflight', $root, normalizePhp($phpBin, 'php tools/ai/ai.php preflight'), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $retryCount, $cancelFlag, $liveLog);
    } else {
        addStage($report, 'preflight', true, [
            'skipped' => true,
            'reason' => 'packages/ai-universal-rules/templates is not present in this installed target',
        ]);
    }

    if ($hasPackageTemplates) {
        runRequired($report, 'package-verify', $root, normalizePhp($phpBin, 'php tools/ai/ai.php package-verify'), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $retryCount, $cancelFlag, $liveLog);
    } else {
        addStage($report, 'package-verify', true, [
            'skipped' => true,
            'reason' => 'packages/ai-universal-rules/templates is not present in this installed target',
        ]);
    }
    runRequired($report, 'adapter-plan', $root, normalizePhp($phpBin, "php tools/ai/ai.php adapter-plan --profile {$profile} --mode {$mode} --force --allow-core-overwrite --reinstall"), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $retryCount, $cancelFlag, $liveLog);
    runRequired($report, 'install-dry-run', $root, normalizePhp($phpBin, "php tools/ai/ai.php install --profile {$profile} --mode {$mode} --force --allow-core-overwrite --reinstall --dry-run"), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $retryCount, $cancelFlag, $liveLog);

    if ($runApply && !$smokeMode) {
        runRequired($report, 'install-backup-only', $root, normalizePhp($phpBin, "php tools/ai/ai.php install --backup-only --apply --profile {$profile} --mode {$mode} --force --allow-core-overwrite --reinstall"), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $retryCount, $cancelFlag, $liveLog);
        $backupId = readBackupId($root);
        if ($backupId === null) {
            markFailure($report, 'install-apply', 'backup id not found in docs/ai/generated/install.json');
        } else {
            $report['backup_id'] = $backupId;
            runRequired($report, 'install-apply', $root, normalizePhp($phpBin, "php tools/ai/ai.php install --apply --profile {$profile} --mode {$mode} --force --allow-core-overwrite --reinstall --backup {$backupId}"), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $retryCount, $cancelFlag, $liveLog);
            runRequired($report, 'post-install-catalog-refresh', $root, normalizePhp($phpBin, 'php tools/ai/generate-ai-catalog.php --write'), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $retryCount, $cancelFlag, $liveLog);
        }
    }

    $trackedFiles = listTrackedFiles($root, $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog);

    $shellInventory = buildInventory($root, '*.sh', $withScc, $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog, $trackedFiles);
    $report['shell_inventory'] = $shellInventory;
    $phpInventory = buildInventory($root, '*.php', false, $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog, $trackedFiles);
    $report['php_inventory'] = $phpInventory;
    $jsonInventory = buildInventory($root, '*.json', false, $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog, $trackedFiles);
    $report['json_inventory'] = $jsonInventory;
    $yamlInventory = buildYamlInventory($root, $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog, $trackedFiles);
    $report['yaml_inventory'] = $yamlInventory;
    $mdInventory = buildInventory($root, '*.md', false, $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog, $trackedFiles);
    $report['markdown_inventory'] = $mdInventory;

    lintShellScripts($report, $root, $shellInventory['files'], $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog);
    lintPhpFiles($report, $root, $phpInventory['files'], $phpBin, $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog);
    lintJsonFiles($report, $root, $jsonInventory['files']);
    lintYamlFiles($report, $root, $yamlInventory['files'], $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog);

    runRequired($report, 'run-script-list', $root, normalizePhp($phpBin, 'php tools/ai/ai.php run-script --list'), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $retryCount, $cancelFlag, $liveLog);
    runRegisteredScriptsDryRun($report, $root, $phpBin, $timeoutSec, $idleTimeoutSec, $heartbeatSec, $retryCount, $cancelFlag, $liveLog);

    runRequired($report, 'validate-install-surface', $root, normalizePhp($phpBin, 'php tools/ai/validate-install-surface.php --strict'), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $retryCount, $cancelFlag, $liveLog);
    runRequired($report, 'validate-ai-config', $root, normalizePhp($phpBin, 'php tools/ai/validate-ai-config.php'), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $retryCount, $cancelFlag, $liveLog);
    runRequired($report, 'validate-ai-catalog', $root, normalizePhp($phpBin, 'php tools/ai/validate-ai-catalog.php'), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $retryCount, $cancelFlag, $liveLog);
    runRequired($report, 'generate-ai-catalog-check', $root, normalizePhp($phpBin, 'php tools/ai/generate-ai-catalog.php --check'), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $retryCount, $cancelFlag, $liveLog);
    runRequired($report, 'generate-repo-structure-check', $root, normalizePhp($phpBin, 'php tools/ai/generate-repo-structure.php --check --with-scc'), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $retryCount, $cancelFlag, $liveLog);
    runRequired($report, 'validate-generated-artifacts', $root, normalizePhp($phpBin, 'php tools/ai/validate-generated-artifacts.php'), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $retryCount, $cancelFlag, $liveLog);
    runRequired($report, 'verify-json', $root, normalizePhp($phpBin, 'php tools/ai/ai.php verify --json'), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $retryCount, $cancelFlag, $liveLog);
    if ($includeDeepVerify) {
        runRequired($report, 'verify-full-install', $root, normalizePhp($phpBin, 'php tools/ai/verify-full-install.php'), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $retryCount, $cancelFlag, $liveLog);
    } else {
        addStage($report, 'verify-full-install', true, ['skipped' => true, 'reason' => 'enable with --include-deep-verify or --release-gate']);
    }

    if (!$smokeMode && $includePhpunit) {
        runRequired($report, 'phpunit', $root, normalizePhp($phpBin, 'php vendor/bin/phpunit --colors=never --log-junit docs/ai/generated/phpunit.xml'), max($timeoutSec, 1800), max($idleTimeoutSec, 300), $heartbeatSec, 0, $cancelFlag, $liveLog);
    } else {
        addStage($report, 'phpunit', true, ['skipped' => true, 'reason' => 'enable with --include-phpunit or --release-gate']);
    }

    if ($report['failures'] !== []) {
        $report['status'] = 'failed';
    }

    writeReports($root, $report);
    logLine($liveLog, 'done status=' . $report['status']);
    fwrite(STDOUT, 'OK: wrote docs/ai/generated/full-install-validation.json and docs/ai/generated/full-install-validation.md' . PHP_EOL);
    return $report['status'] === 'passed' ? 0 : 1;
}
