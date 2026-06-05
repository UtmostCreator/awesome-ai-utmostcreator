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

if ($missing !== []) {
    foreach ($missing as $token) {
        fwrite(STDERR, "ERROR: undocumented placeholder token {$token}\n");
    }
    exit(1);
}

fwrite(STDOUT, "OK: placeholder registry covers template tokens\n");
exit(0);
