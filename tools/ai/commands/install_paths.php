<?php

declare(strict_types=1);

require_once __DIR__ . '/../ai_catalog_lib.php';

function aiPackageLockPath(string $root): string
{
    return aiAbsolutePath($root, aiResolveKitDescriptorPath($root, 'package-lock.ai.json'));
}

function aiInstallManifestPath(string $root): string
{
    return $root . DIRECTORY_SEPARATOR . '.ai-install-manifest.json';
}

function aiInstallDerivedManifestPath(string $root): string
{
    return aiCliGeneratedDir($root) . DIRECTORY_SEPARATOR . 'install-manifest.json';
}

function aiHashPath(string $path): string
{
    if (is_file($path)) {
        return 'sha256:' . hash_file('sha256', $path);
    }
    if (!is_dir($path)) {
        return 'missing';
    }
    $parts = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $abs = $file->getPathname();
        $rel = str_replace('\\', '/', substr($abs, strlen($path) + 1));
        $parts[] = $rel . ':' . hash_file('sha256', $abs);
    }
    sort($parts);
    return 'sha256:' . hash('sha256', implode("\n", $parts));
}

function aiCollectTemplateChecksums(string $root): array
{
    $base = $root . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'ai-universal-rules' . DIRECTORY_SEPARATOR . 'templates';
    if (!is_dir($base)) {
        throw new RuntimeException('Missing package templates directory at packages/ai-universal-rules/templates');
    }

    $checksums = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $abs = $file->getPathname();
        $rel = 'templates/' . str_replace('\\', '/', substr($abs, strlen($base) + 1));
        $checksums[$rel] = 'sha256:' . hash_file('sha256', $abs);
    }
    ksort($checksums);
    return $checksums;
}
