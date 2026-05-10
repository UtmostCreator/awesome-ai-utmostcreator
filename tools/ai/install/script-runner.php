<?php

declare(strict_types=1);

require_once __DIR__ . '/script-registry.php';
require_once __DIR__ . '/toolchain.php';

function aiInstallerResolveScriptPath(string $root, array $entry): ?string
{
    $source = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) ($entry['source_path'] ?? ''));
    if (is_file($source)) {
        return $source;
    }
    $installed = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) ($entry['installed_path'] ?? ''));
    if (is_file($installed)) {
        return $installed;
    }
    return null;
}

function aiInstallerMissingTools(array $tools): array
{
    $report = aiInstallerToolchainReport($tools);
    $missing = [];
    foreach ($report as $row) {
        if (!($row['present'] ?? false)) {
            $missing[] = (string) ($row['tool'] ?? 'unknown');
        }
    }
    return $missing;
}
