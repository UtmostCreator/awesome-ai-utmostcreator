<?php

declare(strict_types=1);

/**
 * Catalog drift check.
 *
 * The install catalog (packages/ai-universal-rules/docs/INSTALL-CATALOG.md and its generated
 * JSON/MD twins) is derived from the installer pack registry. It is normally only regenerated
 * during an install, so a pack edit can leave the tracked catalog stale without any existing
 * validator noticing. This standalone, side-effect-free check fails when the committed catalog
 * disagrees with a fresh render of the current registry.
 *
 * Usage:
 *   php tools/ai/validate-catalog-drift.php [--root=PATH]
 * Exit: 0 when the catalog is up to date, 1 on drift.
 */

require_once __DIR__ . '/install/docs.php';

function aiCatalogDriftMain(array $argv): int
{
    $root = getcwd() ?: '.';
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--root=')) {
            $root = substr($arg, 7);
        }
    }
    $root = rtrim(str_replace('\\', '/', (string) (realpath($root) ?: $root)), '/');

    if (!function_exists('aiInstallerCheckCatalogDocs')) {
        fwrite(STDERR, "ERROR: catalog check function unavailable\n");
        return 1;
    }

    $result = aiInstallerCheckCatalogDocs($root);
    $drift = $result['drift'] ?? [];

    if (!is_array($drift) || $drift === []) {
        fwrite(STDOUT, "OK: install catalog is up to date\n");
        return 0;
    }

    fwrite(STDERR, "ERROR: install catalog drift detected in:\n");
    foreach ($drift as $path) {
        fwrite(STDERR, ' - ' . (string) $path . "\n");
    }
    fwrite(STDERR, "Regenerate with: php tools/ai/ai.php install-docs --write\n");

    return 1;
}

if (PHP_SAPI === 'cli' && isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit(aiCatalogDriftMain($argv));
}
