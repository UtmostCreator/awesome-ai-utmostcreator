<?php

declare(strict_types=1);

/** @return list<string> */
function aiInstallerDiscoverMigrations(string $sourceRoot): array
{
    $dir = rtrim($sourceRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'migrations';
    if (!is_dir($dir)) {
        return [];
    }

    $versions = [];
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (is_dir($dir . DIRECTORY_SEPARATOR . $entry)) {
            $versions[] = $entry;
        }
    }
    sort($versions, SORT_NATURAL);

    return array_values($versions);
}

/**
 * Migration runner foundation.
 *
 * Migrations are version directories under `<sourceRoot>/migrations`. A migration runs only when
 * the directory contains `migrate.php` returning a callable with signature:
 *
 *     function (string $targetRoot, string $sourceRoot): void
 *
 * @return array{schemaVersion:int,fromVersion:string,targetVersion:string,discovered:list<string>,applied:list<string>}
 */
function aiInstallerRunMigrations(string $sourceRoot, string $targetRoot, string $fromVersion, string $targetVersion): array
{
    $discovered = aiInstallerDiscoverMigrations($sourceRoot);
    $applied = [];
    foreach ($discovered as $version) {
        if (!aiInstallerMigrationInRange($version, $fromVersion, $targetVersion)) {
            continue;
        }
        $file = rtrim($sourceRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . $version . DIRECTORY_SEPARATOR . 'migrate.php';
        if (!is_file($file)) {
            continue;
        }
        $migration = require $file;
        if (!is_callable($migration)) {
            throw new RuntimeException('migration must return a callable: ' . $version);
        }
        $migration($targetRoot, $sourceRoot);
        $applied[] = $version;
    }

    return [
        'schemaVersion' => 1,
        'fromVersion' => $fromVersion,
        'targetVersion' => $targetVersion,
        'discovered' => $discovered,
        'applied' => $applied,
    ];
}

function aiInstallerMigrationInRange(string $version, string $fromVersion, string $targetVersion): bool
{
    if ($fromVersion === 'unknown' || $targetVersion === 'unknown') {
        return true;
    }

    return version_compare($version, $fromVersion, '>') && version_compare($version, $targetVersion, '<=');
}
