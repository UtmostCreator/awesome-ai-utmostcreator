<?php

declare(strict_types=1);

// watchdog-runner.php: CLI-arg helpers, the subprocess watchdog engine, and stage/report
// bookkeeping helpers, extracted verbatim (behavior-preserving move; no logic changes) from
// tools/ai/full-install-validation.php. See
// docs/tickets/arch-todo-full-install-validation-extraction-20260706-230257/plan.md, Phase 1.

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
        if (($run['exit'] ?? 1) !== 0 && $id === 'generate-ai-catalog-check' && $attempt <= $retries) {
            runCommandWatchdog($root, normalizePhp((string) (defined('PHP_BINARY') ? PHP_BINARY : 'php'), 'php tools/ai/generate-ai-catalog.php --write'), max($timeoutSec, 900), max($idleTimeoutSec, 300), $heartbeatSec, $cancelFlag, $liveLog, 'catalog-refresh');
        }
        if (($run['exit'] ?? 1) !== 0 && $id === 'generate-repo-structure-check' && $attempt <= $retries) {
            $stderrText = (string) ($run['stderr'] ?? '');
            if (str_contains($stderrText, 'metadata file not found:')) {
                bootstrapRepoDirectoryMapIfMissing($root, $liveLog);
            }
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

function bootstrapRepoDirectoryMapIfMissing(string $root, string $liveLog): void
{
    $metadataPath = $root . '/docs/ai/repo-directory-map.json';
    if (is_file($metadataPath)) {
        return;
    }

    $run = runCommandWatchdog($root, 'git ls-files', 120, 60, 5, $root . '/docs/ai/generated/full-install-validation.cancel', $liveLog, 'repo-map-bootstrap-scan');
    if (($run['exit'] ?? 1) !== 0) {
        logLine($liveLog, 'repo-map-bootstrap skipped: git ls-files failed');
        return;
    }

    $topLevel = [];
    foreach (preg_split('/\R/', (string) ($run['stdout'] ?? '')) ?: [] as $line) {
        $line = trim(str_replace('\\', '/', $line));
        if ($line === '') {
            continue;
        }
        $firstSlash = strpos($line, '/');
        $path = $firstSlash === false ? '.' : substr($line, 0, $firstSlash);
        $topLevel[$path] = true;
    }

    if ($topLevel === []) {
        logLine($liveLog, 'repo-map-bootstrap skipped: no tracked files');
        return;
    }

    ksort($topLevel, SORT_STRING);
    $directories = [];
    foreach (array_keys($topLevel) as $path) {
        $directories[] = [
            'path' => $path,
            'purpose' => 'unknown',
            'designed_for' => 'unknown',
            'install_guide' => 'none',
            'install_script' => 'none',
            'ai_entrypoint' => 'none',
            'notes' => 'auto-generated bootstrap metadata; replace with project-specific values',
        ];
    }

    $payload = [
        'schema_version' => 1,
        'directories' => $directories,
        'metadata_exemptions' => [
            [
                'path' => '.repomix-context',
                'reason' => 'Generated during context packing and not tracked',
            ],
        ],
    ];

    $dir = dirname($metadataPath);
    if (!is_dir($dir)) {
        mkdir($dir, AI_DIR_MODE, true);
    }
    file_put_contents($metadataPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    logLine($liveLog, 'repo-map-bootstrap wrote docs/ai/repo-directory-map.json');
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

function logLine(string $logPath, string $line): void
{
    $text = '[' . gmdate('c') . '] ' . $line;
    file_put_contents($logPath, $text . PHP_EOL, FILE_APPEND);
    fwrite(STDOUT, $text . PHP_EOL);
}
