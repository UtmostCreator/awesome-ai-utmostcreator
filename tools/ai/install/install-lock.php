<?php

declare(strict_types=1);

/** @return array<int,mixed>|null */
function aiInstallerInstallSignalHandlers(): ?array
{
    if (!function_exists('pcntl_signal')) {
        return null;
    }
    if (function_exists('pcntl_async_signals')) {
        pcntl_async_signals(true);
    }
    $signals = [];
    foreach ([SIGINT, SIGTERM] as $signal) {
        $signals[$signal] = pcntl_signal_get_handler($signal);
        pcntl_signal($signal, static function (int $caught): void {
            $name = $caught === SIGINT ? 'SIGINT' : 'SIGTERM';
            throw new RuntimeException('install interrupted by ' . $name);
        });
    }
    return $signals;
}

/** @param array<int,mixed> $signals */
function aiInstallerRestoreSignalHandlers(array $signals): void
{
    if (!function_exists('pcntl_signal')) {
        return;
    }
    foreach ($signals as $signal => $handler) {
        pcntl_signal((int) $signal, $handler);
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
