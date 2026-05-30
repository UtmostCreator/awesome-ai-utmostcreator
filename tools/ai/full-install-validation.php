<?php

declare(strict_types=1);

$root = realpath(__DIR__ . '/..' . '/..');

const AI_DIR_MODE = 0755;

if ($root === false) {
    fwrite(STDERR, "ERROR: unable to resolve repository root\n");
    exit(1);
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

runRequired($report, 'preflight', $root, normalizePhp($phpBin, 'php tools/ai/ai.php preflight'), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $retryCount, $cancelFlag, $liveLog);
runRequired($report, 'package-verify', $root, normalizePhp($phpBin, 'php tools/ai/ai.php package-verify'), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $retryCount, $cancelFlag, $liveLog);
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
    }
}

$shellInventory = buildInventory($root, '*.sh', $withScc, $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog);
$report['shell_inventory'] = $shellInventory;
$phpInventory = buildInventory($root, '*.php', false, $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog);
$report['php_inventory'] = $phpInventory;
$jsonInventory = buildInventory($root, '*.json', false, $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog);
$report['json_inventory'] = $jsonInventory;
$yamlInventory = buildYamlInventory($root, $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog);
$report['yaml_inventory'] = $yamlInventory;
$mdInventory = buildInventory($root, '*.md', false, $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog);
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
exit($report['status'] === 'passed' ? 0 : 1);

function cliArg(string $name, string $default): string
{
    global $argv;
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) {
            $value = substr($arg, strlen($name) + 3);
            return $value === '' ? $default : $value;
        }
    }
    return $default;
}

function normalizePhp(string $phpBin, string $command): string
{
    if (str_starts_with($command, 'php ')) {
        return escapeshellarg($phpBin) . substr($command, 3);
    }
    return $command;
}

function runRequired(array &$report, string $id, string $root, string $command, int $timeoutSec, int $idleTimeoutSec, int $heartbeatSec, int $retries, string $cancelFlag, string $liveLog): void
{
    $attempt = 0;
    $run = ['exit' => 1, 'stdout' => '', 'stderr' => 'not-run', 'timed_out' => false, 'cancelled' => false, 'duration_sec' => 0.0];
    do {
        $attempt++;
        logLine($liveLog, "stage={$id} attempt={$attempt} cmd={$command}");
        $run = runCommandWatchdog($root, $command, $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog, $id);
        if (($run['exit'] ?? 1) !== 0 && $id === 'package-verify' && $attempt <= $retries) {
            runCommandWatchdog($root, normalizePhp((string) (defined('PHP_BINARY') ? PHP_BINARY : 'php'), 'php tools/ai/ai.php package-lock'), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog, 'package-lock-refresh');
        }
        if (($run['exit'] ?? 1) !== 0 && $id === 'generate-repo-structure-check' && $attempt <= $retries) {
            runCommandWatchdog($root, normalizePhp((string) (defined('PHP_BINARY') ? PHP_BINARY : 'php'), 'php tools/ai/generate-repo-structure.php --with-scc'), max($timeoutSec, 900), max($idleTimeoutSec, 300), $heartbeatSec, $cancelFlag, $liveLog, 'repo-structure-refresh');
        }
        if (($run['exit'] ?? 1) === 0) {
            break;
        }
        if (!empty($run['cancelled']) || !empty($run['timed_out'])) {
            break;
        }
    } while ($attempt <= $retries);

    $ok = ($run['exit'] ?? 1) === 0;
    addStage($report, $id, $ok, [
        'command' => $command,
        'exit' => $run['exit'] ?? 1,
        'attempts' => $attempt,
        'timed_out' => (bool) ($run['timed_out'] ?? false),
        'cancelled' => (bool) ($run['cancelled'] ?? false),
        'duration_sec' => (float) ($run['duration_sec'] ?? 0.0),
        'idle_timed_out' => (bool) ($run['idle_timed_out'] ?? false),
    ]);
    if (!$ok) {
        $resolution = 'rectify error and rerun this stage';
        if (!empty($run['cancelled'])) {
            $resolution = 'run separately after removing cancel flag file';
        } elseif (!empty($run['timed_out']) || !empty($run['idle_timed_out'])) {
            $resolution = 'run separately with higher timeout or narrow scope';
        }
        markFailure($report, $id, 'required stage failed', [
            'stderr' => trim((string) ($run['stderr'] ?? '')),
            'stdout_excerpt' => substr(trim((string) ($run['stdout'] ?? '')), 0, 1200),
            'attempts' => $attempt,
            'timed_out' => (bool) ($run['timed_out'] ?? false),
            'cancelled' => (bool) ($run['cancelled'] ?? false),
            'idle_timed_out' => (bool) ($run['idle_timed_out'] ?? false),
            'next_action' => $resolution,
        ]);
    }
}

function runCommandWatchdog(string $root, string $command, int $timeoutSec, int $idleTimeoutSec, int $heartbeatSec, string $cancelFlag, string $liveLog, string $stageId): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proc = proc_open($command, $descriptors, $pipes, $root);
    if (!is_resource($proc)) {
        return ['exit' => 1, 'stdout' => '', 'stderr' => 'failed to start process', 'timed_out' => false, 'cancelled' => false, 'duration_sec' => 0.0];
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $stderr = '';
    $start = microtime(true);
    $lastBeat = microtime(true);
    $timedOut = false;
    $cancelled = false;
    $idleTimedOut = false;
    $lastOutputAt = microtime(true);
    $lastLen = 0;

    while (true) {
        $status = proc_get_status($proc);
        $chunkOut = (string) stream_get_contents($pipes[1]);
        $chunkErr = (string) stream_get_contents($pipes[2]);
        if ($chunkOut !== '' || $chunkErr !== '') {
            $lastOutputAt = microtime(true);
            $stdout .= $chunkOut;
            $stderr .= $chunkErr;
        }

        $elapsed = microtime(true) - $start;
        if ((microtime(true) - $lastBeat) >= $heartbeatSec) {
            $idleFor = microtime(true) - $lastOutputAt;
            logLine($liveLog, "heartbeat stage={$stageId} elapsed=" . round($elapsed, 1) . 's idle=' . round($idleFor, 1) . 's running=' . (($status['running'] ?? false) ? 'yes' : 'no'));
            $lastBeat = microtime(true);
        }

        if (is_file($cancelFlag)) {
            $cancelled = true;
            proc_terminate($proc);
            break;
        }

        if ($elapsed > $timeoutSec) {
            $timedOut = true;
            terminateProcess($proc);
            break;
        }

        if ((microtime(true) - $lastOutputAt) > $idleTimeoutSec) {
            $idleTimedOut = true;
            terminateProcess($proc);
            break;
        }

        if (!($status['running'] ?? false)) {
            break;
        }
        usleep(200000);
    }

    $stdout .= (string) stream_get_contents($pipes[1]);
    $stderr .= (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = (int) proc_close($proc);

    if ($cancelled && $exit === 0) {
        $exit = 130;
    }
    if ($timedOut && $exit === 0) {
        $exit = 124;
    }
    if ($idleTimedOut && $exit === 0) {
        $exit = 124;
    }

    return [
        'exit' => $exit,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'timed_out' => $timedOut,
        'idle_timed_out' => $idleTimedOut,
        'cancelled' => $cancelled,
        'duration_sec' => microtime(true) - $start,
    ];
}

function terminateProcess($proc): void
{
    $status = proc_get_status($proc);
    @proc_terminate($proc);
    usleep(300000);
    $statusAfter = proc_get_status($proc);
    if (!($statusAfter['running'] ?? false)) {
        return;
    }
    if (PHP_OS_FAMILY === 'Windows' && isset($statusAfter['pid'])) {
        $pid = (int) $statusAfter['pid'];
        if ($pid > 0) {
            @exec('taskkill /T /F /PID ' . $pid . ' >NUL 2>NUL');
        }
    } else {
        @proc_terminate($proc, 9);
    }
}

function addStage(array &$report, string $id, bool $ok, array $data = []): void
{
    $report['stages'][] = ['id' => $id, 'ok' => $ok, 'data' => $data];
}

function markFailure(array &$report, string $id, string $message, array $data = []): void
{
    $report['failures'][] = ['id' => $id, 'message' => $message, 'data' => $data];
}

function readBackupId(string $root): ?string
{
    $path = $root . '/docs/ai/generated/install.json';
    if (!is_file($path)) {
        return null;
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return null;
    }
    $id = $decoded['data']['backup_id'] ?? null;
    return is_string($id) && $id !== '' ? $id : null;
}

function buildInventory(string $root, string $glob, bool $withScc, int $timeoutSec, int $idleTimeoutSec, int $heartbeatSec, string $cancelFlag, string $liveLog): array
{
    $filesOut = runCommandWatchdog($root, 'git ls-files ' . escapeshellarg($glob), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog, 'inventory-' . $glob);
    $files = [];
    foreach (preg_split('/\R/', (string) ($filesOut['stdout'] ?? '')) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (!file_exists($root . '/' . str_replace('\\', '/', $line))) {
            continue;
        }
        $files[] = $line;
    }
    sort($files);

    $sccByFile = [];
    $sccEnabled = false;
    if ($withScc) {
        $sccRun = runCommandWatchdog($root, 'scc --by-file --format json .', max($timeoutSec, 900), max($idleTimeoutSec, 300), $heartbeatSec, $cancelFlag, $liveLog, 'scc-scan');
        if (($sccRun['exit'] ?? 1) === 0) {
            $decoded = json_decode((string) ($sccRun['stdout'] ?? ''), true);
            if (is_array($decoded)) {
                $sccEnabled = true;
                foreach ($decoded as $group) {
                    if (!is_array($group) || !is_array($group['Files'] ?? null)) {
                        continue;
                    }
                    foreach ($group['Files'] as $entry) {
                        if (!is_array($entry) || !is_string($entry['Location'] ?? null)) {
                            continue;
                        }
                        $location = preg_replace('/^\.\//', '', str_replace('\\', '/', (string) $entry['Location'])) ?? (string) $entry['Location'];
                        $sccByFile[$location] = [
                            'lines' => (int) ($entry['Lines'] ?? 0),
                            'code' => (int) ($entry['Code'] ?? 0),
                            'complexity' => (int) ($entry['Complexity'] ?? 0),
                        ];
                    }
                }
            }
        }
    }

    $items = [];
    foreach ($files as $file) {
        $items[] = [
            'path' => $file,
            'scc' => $sccByFile[$file] ?? null,
        ];
    }

    return ['files' => $files, 'total' => count($files), 'scc_enabled' => $sccEnabled, 'items' => $items];
}

function lintShellScripts(array &$report, string $root, array $files, int $timeoutSec, int $idleTimeoutSec, int $heartbeatSec, string $cancelFlag, string $liveLog): void
{
    $failures = [];
    foreach ($files as $file) {
        $lint = runCommandWatchdog($root, 'bash -n ' . escapeshellarg($file), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog, 'bash-lint');
        if (($lint['exit'] ?? 1) !== 0) {
            $failures[] = ['file' => $file, 'stderr' => trim((string) ($lint['stderr'] ?? ''))];
        }
    }
    if ($failures !== []) {
        markFailure($report, 'bash-lint-all', 'shell lint failures found', ['failures' => $failures]);
        addStage($report, 'bash-lint-all', false, ['checked_files' => count($files)]);
        return;
    }
    addStage($report, 'bash-lint-all', true, ['checked_files' => count($files)]);
}

function lintPhpFiles(array &$report, string $root, array $files, string $phpBin, int $timeoutSec, int $idleTimeoutSec, int $heartbeatSec, string $cancelFlag, string $liveLog): void
{
    $failures = [];
    foreach ($files as $file) {
        $lint = runCommandWatchdog($root, escapeshellarg($phpBin) . ' -l ' . escapeshellarg($file), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog, 'php-lint');
        if (($lint['exit'] ?? 1) !== 0) {
            $failures[] = ['file' => $file, 'stderr' => trim((string) ($lint['stderr'] ?? ''))];
        }
    }
    if ($failures !== []) {
        markFailure($report, 'php-lint-all', 'php lint failures found', ['failures' => $failures]);
        addStage($report, 'php-lint-all', false, ['checked_files' => count($files)]);
        return;
    }
    addStage($report, 'php-lint-all', true, ['checked_files' => count($files)]);
}

function lintJsonFiles(array &$report, string $root, array $files): void
{
    $failures = [];
    foreach ($files as $file) {
        $path = $root . '/' . str_replace('\\', '/', $file);
        $raw = @file_get_contents($path);
        if (!is_string($raw)) {
            $failures[] = ['file' => $file, 'error' => 'unreadable'];
            continue;
        }
        $decodeInput = isJsoncLikePath($file) ? stripJsonCommentsAndTrailingCommas($raw) : $raw;
        json_decode($decodeInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $failures[] = ['file' => $file, 'error' => json_last_error_msg()];
        }
    }
    if ($failures !== []) {
        markFailure($report, 'json-parse-all', 'json parse failures found', ['failures' => $failures]);
        addStage($report, 'json-parse-all', false, ['checked_files' => count($files)]);
        return;
    }
    addStage($report, 'json-parse-all', true, ['checked_files' => count($files)]);
}

function isJsoncLikePath(string $file): bool
{
    $path = str_replace('\\', '/', $file);

    return str_starts_with($path, 'configs/vscode/')
        || $path === 'configs/karabiner/karabiner.json'
        || $path === '.vscode/settings.json'
        || str_ends_with($path, 'copilot-vscode-settings.template.json');
}

function stripJsonCommentsAndTrailingCommas(string $input): string
{
    $withoutComments = '';
    $inString = false;
    $escaped = false;
    $inLineComment = false;
    $inBlockComment = false;
    $length = strlen($input);

    for ($index = 0; $index < $length; $index++) {
        $char = $input[$index];
        $next = $index + 1 < $length ? $input[$index + 1] : '';

        if ($inLineComment) {
            if ($char === "\n") {
                $inLineComment = false;
                $withoutComments .= $char;
            }
            continue;
        }

        if ($inBlockComment) {
            if ($char === '*' && $next === '/') {
                $inBlockComment = false;
                $index++;
            }
            continue;
        }

        if ($inString) {
            $withoutComments .= $char;

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === '"') {
                $inString = false;
            }

            continue;
        }

        if ($char === '"') {
            $inString = true;
            $withoutComments .= $char;
            continue;
        }

        if ($char === '/' && $next === '/') {
            $inLineComment = true;
            $index++;
            continue;
        }

        if ($char === '/' && $next === '*') {
            $inBlockComment = true;
            $index++;
            continue;
        }

        $withoutComments .= $char;
    }

    $normalized = '';
    $inString = false;
    $escaped = false;
    $length = strlen($withoutComments);

    for ($index = 0; $index < $length; $index++) {
        $char = $withoutComments[$index];

        if ($inString) {
            $normalized .= $char;

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === '"') {
                $inString = false;
            }

            continue;
        }

        if ($char === '"') {
            $inString = true;
            $normalized .= $char;
            continue;
        }

        if ($char === ',') {
            $nextIndex = findNextNonWhitespaceIndex($withoutComments, $index + 1);
            if ($nextIndex !== null) {
                $nextChar = $withoutComments[$nextIndex];
                if ($nextChar === '}' || $nextChar === ']') {
                    continue;
                }
            }
        }

        $normalized .= $char;
    }

    return $normalized;
}

function findNextNonWhitespaceIndex(string $input, int $start): ?int
{
    $length = strlen($input);

    for ($index = $start; $index < $length; $index++) {
        if (!ctype_space($input[$index])) {
            return $index;
        }
    }

    return null;
}

function lintYamlFiles(array &$report, string $root, array $files, int $timeoutSec, int $idleTimeoutSec, int $heartbeatSec, string $cancelFlag, string $liveLog): void
{
    if (!commandExists('yq')) {
        addStage($report, 'yaml-parse-all', true, ['checked_files' => count($files), 'skipped' => true, 'reason' => 'yq not found']);
        return;
    }

    $failures = [];
    foreach ($files as $file) {
        $run = runCommandWatchdog($root, 'yq e . ' . escapeshellarg($file), $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog, 'yaml-parse');
        if (($run['exit'] ?? 1) !== 0) {
            $failures[] = ['file' => $file, 'stderr' => trim((string) ($run['stderr'] ?? ''))];
        }
    }

    if ($failures !== []) {
        markFailure($report, 'yaml-parse-all', 'yaml parse failures found', ['failures' => $failures]);
        addStage($report, 'yaml-parse-all', false, ['checked_files' => count($files)]);
        return;
    }
    addStage($report, 'yaml-parse-all', true, ['checked_files' => count($files)]);
}

function buildYamlInventory(string $root, int $timeoutSec, int $idleTimeoutSec, int $heartbeatSec, string $cancelFlag, string $liveLog): array
{
    $yml = buildInventory($root, '*.yml', false, $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog);
    $yaml = buildInventory($root, '*.yaml', false, $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog);
    $files = array_values(array_unique(array_merge($yml['files'], $yaml['files'])));
    sort($files);
    return ['files' => $files, 'total' => count($files), 'scc_enabled' => false, 'items' => array_map(static fn(string $p): array => ['path' => $p, 'scc' => null], $files)];
}

function commandExists(string $name): bool
{
    $out = [];
    $exit = 0;
    if (PHP_OS_FAMILY === 'Windows') {
        exec('where ' . escapeshellarg($name) . ' >NUL 2>&1', $out, $exit);
        return $exit === 0;
    }
    exec('command -v ' . escapeshellarg($name) . ' >/dev/null 2>&1', $out, $exit);
    return $exit === 0;
}

function runRegisteredScriptsDryRun(array &$report, string $root, string $phpBin, int $timeoutSec, int $idleTimeoutSec, int $heartbeatSec, int $retryCount, string $cancelFlag, string $liveLog): void
{
    require_once __DIR__ . '/install/script-registry.php';
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

        $rows[] = ['id' => $id, 'exit' => $run['exit'] ?? 1, 'attempts' => $attempt];
        if (($run['exit'] ?? 1) !== 0) {
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

function logLine(string $logPath, string $line): void
{
    $text = '[' . gmdate('c') . '] ' . $line;
    file_put_contents($logPath, $text . PHP_EOL, FILE_APPEND);
    fwrite(STDOUT, $text . PHP_EOL);
}
