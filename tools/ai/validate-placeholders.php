<?php

declare(strict_types=1);

require_once __DIR__ . '/ai_catalog_lib.php';

$root = realpath(__DIR__ . '/..' . '/..');
if ($root === false) {
    fwrite(STDERR, "ERROR: repository root not found\n");
    exit(1);
}

$placeholdersDoc = $root . '/' . aiResolvePackageBase($root) . 'PLACEHOLDERS.md';
if (!is_file($placeholdersDoc)) {
    $placeholdersDoc = $root . '/PLACEHOLDERS.md';
}
if (!is_file($placeholdersDoc)) {
    fwrite(STDERR, "ERROR: missing PLACEHOLDERS.md\n");
    exit(1);
}

$doc = (string) file_get_contents($placeholdersDoc);
$documented = [];
if (preg_match_all('/`(<[A-Z0-9_]+>)`/', $doc, $m) === 1 || (!empty($m[1]))) {
    $documented = array_values(array_unique($m[1]));
}

$templatePaths = [];
$templatesDir = $root . '/packages/ai-universal-rules/templates';
if (is_dir($templatesDir)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($templatesDir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') {
            continue;
        }
        $templatePaths[] = $file->getPathname();
    }
}

$used = [];
foreach ($templatePaths as $path) {
    $content = (string) file_get_contents($path);
    if (preg_match_all('/<[A-Z0-9_]+>/', $content, $m2) === 1 || (!empty($m2[0]))) {
        $used = array_merge($used, $m2[0]);
    }
}
$used = array_values(array_unique($used));

$missing = array_values(array_diff($used, $documented));

$failed = false;
if ($missing !== []) {
    foreach ($missing as $token) {
        fwrite(STDERR, "ERROR: undocumented placeholder token {$token}\n");
    }
    $failed = true;
}

// Machine-readable registry (placeholders.json) must stay token-synced with PLACEHOLDERS.md.
$registryPath = $root . '/' . aiResolvePackageBase($root) . 'placeholders.json';
if (!is_file($registryPath)) {
    $registryPath = $root . '/.ai/placeholders.json';
}
if (!is_file($registryPath)) {
    $registryPath = $root . '/placeholders.json';
}

if (is_file($registryPath)) {
    $registry = json_decode((string) file_get_contents($registryPath), true);
    if (!is_array($registry) || !is_array($registry['tokens'] ?? null)) {
        fwrite(STDERR, "ERROR: invalid placeholder registry JSON: {$registryPath}\n");
        $failed = true;
    } else {
        $registryTokens = [];
        foreach ($registry['tokens'] as $entry) {
            if (is_array($entry) && is_string($entry['token'] ?? null)) {
                $registryTokens[] = $entry['token'];
            }
        }
        $registryTokens = array_values(array_unique($registryTokens));

        foreach (array_diff($documented, $registryTokens) as $token) {
            fwrite(STDERR, "ERROR: token documented in PLACEHOLDERS.md but missing from placeholders.json: {$token}\n");
            $failed = true;
        }
        foreach (array_diff($registryTokens, $documented) as $token) {
            fwrite(STDERR, "ERROR: token in placeholders.json but undocumented in PLACEHOLDERS.md: {$token}\n");
            $failed = true;
        }
    }
} else {
    fwrite(STDERR, "ERROR: missing placeholders.json registry next to PLACEHOLDERS.md\n");
    $failed = true;
}

if ($failed) {
    exit(1);
}

fwrite(STDOUT, "OK: placeholder registry covers template tokens and matches placeholders.json\n");
exit(0);
