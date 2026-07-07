<?php

declare(strict_types=1);

// surface-lint.php: shell/PHP/JSON/YAML lint helpers, extracted verbatim (behavior-preserving
// move; no logic changes) from tools/ai/full-install-validation.php. See
// docs/tickets/arch-todo-full-install-validation-extraction-20260706-230257/plan.md, Phase 1.
// Runtime dependencies: lintShellScripts(), lintPhpFiles(), and lintYamlFiles() call
// runCommandWatchdog() (tools/ai/validation/watchdog-runner.php); lintJsonFiles() calls the
// shared stripJsonCommentsAndTrailingCommas() already defined in
// tools/ai/validation/config-loader.php instead of keeping a local duplicate (the local copy
// previously in full-install-validation.php has been removed). The caller must require both
// config-loader.php and watchdog-runner.php before invoking these functions.

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
        || $path === 'opencode.jsonc'
        || str_ends_with($path, '/opencode.json')
        || str_ends_with($path, '/opencode.jsonc')
        || str_ends_with($path, 'copilot-vscode-settings.template.json');
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
