<?php

declare(strict_types=1);

require_once __DIR__ . '/ai_catalog_lib.php';

$checkOnly = in_array('--check', $argv, true);
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
$exportRoot = aiAbsolutePath($root, $manifest['release']['export_root']);
$bundleDirectory = $exportRoot . DIRECTORY_SEPARATOR . $manifest['version'] . DIRECTORY_SEPARATOR . $profileId;

foreach ($profile['includes'] as $include) {
    $source = aiAbsolutePath($root, 'packages/ai-universal-rules/' . $include);

    if (!file_exists($source)) {
        fwrite(STDERR, "ERROR: missing export source {$include}\n");
        exit(1);
    }
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

fwrite(STDOUT, "OK: exported {$profileId} bundle to {$bundleDirectory}\n");
