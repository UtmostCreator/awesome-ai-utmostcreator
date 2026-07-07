<?php

declare(strict_types=1);

// repo-inventory.php: tracked-file inventory helpers, extracted verbatim (behavior-preserving
// move; no logic changes) from tools/ai/full-install-validation.php. See
// docs/tickets/arch-todo-full-install-validation-extraction-20260706-230257/plan.md, Phase 1.
// Runtime dependency: listTrackedFiles() and buildInventory() call runCommandWatchdog(), which
// lives in tools/ai/validation/watchdog-runner.php; the caller must require that file before
// invoking these functions.

function listTrackedFiles(string $root, int $timeoutSec, int $idleTimeoutSec, int $heartbeatSec, string $cancelFlag, string $liveLog): array
{
    $filesOut = runCommandWatchdog($root, 'git ls-files', $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog, 'inventory-tracked-all');
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

    return $files;
}

function trackedPathMatchesGlob(string $path, string $glob): bool
{
    if (str_starts_with($glob, '*.')) {
        $suffix = substr($glob, 1);

        return $suffix !== false && str_ends_with($path, $suffix);
    }

    if (function_exists('fnmatch')) {
        return fnmatch($glob, $path);
    }

    return $path === $glob;
}

function buildInventory(string $root, string $glob, bool $withScc, int $timeoutSec, int $idleTimeoutSec, int $heartbeatSec, string $cancelFlag, string $liveLog, ?array $trackedFiles = null): array
{
    if ($trackedFiles === null) {
        $trackedFiles = listTrackedFiles($root, $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog);
    }

    $files = [];
    foreach ($trackedFiles as $trackedPath) {
        if (!is_string($trackedPath) || $trackedPath === '') {
            continue;
        }
        if (!trackedPathMatchesGlob($trackedPath, $glob)) {
            continue;
        }
        $files[] = $trackedPath;
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

function buildYamlInventory(string $root, int $timeoutSec, int $idleTimeoutSec, int $heartbeatSec, string $cancelFlag, string $liveLog, ?array $trackedFiles = null): array
{
    $yml = buildInventory($root, '*.yml', false, $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog, $trackedFiles);
    $yaml = buildInventory($root, '*.yaml', false, $timeoutSec, $idleTimeoutSec, $heartbeatSec, $cancelFlag, $liveLog, $trackedFiles);
    $files = array_values(array_unique(array_merge($yml['files'], $yaml['files'])));
    sort($files);
    return ['files' => $files, 'total' => count($files), 'scc_enabled' => false, 'items' => array_map(static fn(string $p): array => ['path' => $p, 'scc' => null], $files)];
}
