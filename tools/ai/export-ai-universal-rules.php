<?php

declare(strict_types=1);

require_once __DIR__ . '/ai_catalog_lib.php';

$checkOnly = in_array('--check', $argv, true);
$budgetCheckOnly = in_array('--budget-check', $argv, true);
$verifyOnly = in_array('--verify', $argv, true);
$profileId = 'dual-runtime-starter';
$explicitProfile = false;

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--profile=')) {
        $profileId = substr($argument, strlen('--profile='));
        $explicitProfile = true;
    }
}

$root = aiRepoRoot();
$manifest = aiLoadJson($root, 'packages/ai-universal-rules/manifest.json');
$profiles = [];

foreach ($manifest['starter_profiles'] as $profile) {
    $profiles[$profile['id']] = $profile;
}

if (!isset($profiles[$profileId])) {
    fwrite(STDERR, "ERROR: unknown profile {$profileId}\n");
    exit(1);
}

$exportRoot = aiAbsolutePath($root, $manifest['release']['export_root']);

/**
 * Collect every file under $directory as sorted bundle-relative paths.
 * Sorting keeps SHA256SUMS output and budget summation deterministic.
 *
 * @return list<string>
 */
function exportCollectBundleRelativeFiles(string $directory): array
{
    $files = [];

    if (!is_dir($directory)) {
        return $files;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    $prefixLength = strlen(rtrim(str_replace('\\', '/', $directory), '/')) + 1;

    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }

        $files[] = substr(str_replace('\\', '/', $item->getPathname()), $prefixLength);
    }

    sort($files);

    return $files;
}

/**
 * Sum line counts across every bundled file. Templates ship as markdown, JSON,
 * and shell so all bundle content is treated as text. A trailing newline is
 * counted as terminating its line, not as an extra empty line.
 */
function exportSumBundleLines(string $bundleDirectory): int
{
    $total = 0;

    foreach (exportCollectBundleRelativeFiles($bundleDirectory) as $relativePath) {
        $contents = (string) file_get_contents($bundleDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

        if ($contents === '') {
            continue;
        }

        $lines = substr_count($contents, "\n");

        if (substr($contents, -1) !== "\n") {
            $lines++;
        }

        $total += $lines;
    }

    return $total;
}

/**
 * Enforce the aggregate per-bundle line budget. Returns false on overflow after
 * writing a STDERR message; returns true (and warns) when no budget is declared.
 */
function exportEnforceBundleBudget(array $manifest, string $bundleDirectory, string $profileId): bool
{
    if (!array_key_exists('max_bundle_lines', $manifest['release'])) {
        fwrite(STDERR, "WARN: release.max_bundle_lines is not declared; skipping aggregate budget check for {$profileId}\n");

        return true;
    }

    $max = (int) $manifest['release']['max_bundle_lines'];
    $total = exportSumBundleLines($bundleDirectory);

    if ($total > $max) {
        fwrite(STDERR, "ERROR: bundle {$profileId} aggregate line count {$total} exceeds release.max_bundle_lines {$max}\n");

        return false;
    }

    fwrite(STDOUT, "OK: bundle {$profileId} aggregate line count {$total} within budget {$max}\n");

    return true;
}

if ($verifyOnly) {
    $bundleDirectory = $exportRoot . DIRECTORY_SEPARATOR . $manifest['version'] . DIRECTORY_SEPARATOR . $profileId;
    $checksumPath = $bundleDirectory . DIRECTORY_SEPARATOR . 'SHA256SUMS';

    if (!is_dir($bundleDirectory)) {
        fwrite(STDERR, "ERROR: bundle directory not found for profile {$profileId}: {$bundleDirectory}\n");
        exit(1);
    }

    if (!is_file($checksumPath)) {
        fwrite(STDERR, "ERROR: SHA256SUMS missing for profile {$profileId}\n");
        exit(1);
    }

    $expected = [];

    foreach (preg_split('/\r?\n/', (string) file_get_contents($checksumPath)) ?: [] as $line) {
        if ($line === '') {
            continue;
        }

        if (!preg_match('/^([0-9a-f]{64})  (.+)$/', $line, $matches)) {
            fwrite(STDERR, "ERROR: malformed SHA256SUMS line for profile {$profileId}: {$line}\n");
            exit(1);
        }

        $expected[$matches[2]] = $matches[1];
    }

    $failed = false;

    foreach ($expected as $relativePath => $hash) {
        $absolute = $bundleDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (!is_file($absolute)) {
            fwrite(STDERR, "ERROR: listed file missing from bundle {$profileId}: {$relativePath}\n");
            $failed = true;
            continue;
        }

        if (hash_file('sha256', $absolute) !== $hash) {
            fwrite(STDERR, "ERROR: checksum mismatch in bundle {$profileId}: {$relativePath}\n");
            $failed = true;
        }
    }

    foreach (exportCollectBundleRelativeFiles($bundleDirectory) as $relativePath) {
        if ($relativePath === 'SHA256SUMS') {
            continue;
        }

        if (!array_key_exists($relativePath, $expected)) {
            fwrite(STDERR, "ERROR: extra unlisted file in bundle {$profileId}: {$relativePath}\n");
            $failed = true;
        }
    }

    if ($failed) {
        exit(1);
    }

    fwrite(STDOUT, "OK: bundle {$profileId} checksums verified\n");
    exit(0);
}

if ($checkOnly && $explicitProfile === false) {
    foreach ($profiles as $candidate) {
        foreach ($candidate['includes'] as $include) {
            $source = aiAbsolutePath($root, 'packages/ai-universal-rules/' . $include);

            if (!file_exists($source)) {
                fwrite(STDERR, "ERROR: missing export source {$include} for profile {$candidate['id']}\n");
                exit(1);
            }
        }

        fwrite(STDOUT, "OK: export profile {$candidate['id']} is valid\n");
    }

    exit(0);
}

$profile = $profiles[$profileId];
$bundleDirectory = $exportRoot . DIRECTORY_SEPARATOR . $manifest['version'] . DIRECTORY_SEPARATOR . $profileId;

foreach ($profile['includes'] as $include) {
    $source = aiAbsolutePath($root, 'packages/ai-universal-rules/' . $include);

    if (!file_exists($source)) {
        fwrite(STDERR, "ERROR: missing export source {$include}\n");
        exit(1);
    }
}

if ($budgetCheckOnly) {
    if (!is_dir($bundleDirectory)) {
        fwrite(STDERR, "ERROR: bundle directory not found for profile {$profileId}: {$bundleDirectory}\n");
        exit(1);
    }

    exit(exportEnforceBundleBudget($manifest, $bundleDirectory, $profileId) ? 0 : 1);
}

if ($checkOnly) {
    fwrite(STDOUT, "OK: export profile {$profileId} is valid\n");
    exit(0);
}

if (!is_dir($bundleDirectory) && !mkdir($bundleDirectory, 0777, true) && !is_dir($bundleDirectory)) {
    fwrite(STDERR, "ERROR: unable to create export directory {$bundleDirectory}\n");
    exit(1);
}

foreach ($profile['includes'] as $include) {
    aiCopyPath(
        aiAbsolutePath($root, 'packages/ai-universal-rules/' . $include),
        $bundleDirectory . DIRECTORY_SEPARATOR . $include
    );
}

$releaseManifest = [
    'package' => $manifest['name'],
    'version' => $manifest['version'],
    'profile' => $profile['id'],
    'description' => $profile['description'],
    'includes' => $profile['includes'],
    'notes' => $manifest['release']['notes'],
];

file_put_contents(
    $bundleDirectory . DIRECTORY_SEPARATOR . 'RELEASE-MANIFEST.json',
    json_encode($releaseManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);

$checksumLines = [];

foreach (exportCollectBundleRelativeFiles($bundleDirectory) as $relativePath) {
    if ($relativePath === 'SHA256SUMS') {
        continue;
    }

    $hash = hash_file('sha256', $bundleDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
    $checksumLines[] = $hash . '  ' . $relativePath;
}

file_put_contents(
    $bundleDirectory . DIRECTORY_SEPARATOR . 'SHA256SUMS',
    implode("\n", $checksumLines) . "\n"
);

if (!exportEnforceBundleBudget($manifest, $bundleDirectory, $profileId)) {
    exit(1);
}

fwrite(STDOUT, "OK: exported {$profileId} bundle to {$bundleDirectory}\n");
