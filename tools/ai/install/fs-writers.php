<?php

declare(strict_types=1);

function aiInstallerCopyFile(string $src, string $dest): void
{
    if (!is_file($src)) {
        throw new RuntimeException('missing source file: ' . $src);
    }
    $srcReal = realpath($src);
    $destReal = file_exists($dest) ? realpath($dest) : false;
    if ($srcReal !== false && $destReal !== false && $srcReal === $destReal) {
        return;
    }
    aiInstallerMkdir(dirname($dest));
    // Suppress the native warning; a clean RuntimeException is thrown on failure (e.g. a
    // read-only destination) so callers get one well-formed error, not raw PHP warning noise.
    if (!@copy($src, $dest)) {
        throw new RuntimeException('failed to copy file: ' . $src . ' -> ' . $dest);
    }
    $mode = @fileperms($src);
    if ($mode !== false) {
        @chmod($dest, $mode & 0777);
    }
}

function aiInstallerCopyDirAsSkillDirs(string $src, string $dest): void
{
    if (!is_dir($src)) {
        throw new RuntimeException('missing source directory: ' . $src);
    }
    $srcReal = realpath($src);
    $destReal = file_exists($dest) ? realpath($dest) : false;
    if ($srcReal !== false && $destReal !== false && $srcReal === $destReal) {
        return;
    }
    // Do NOT delete-tree the whole destination. Sibling skill dirs in this same target
    // (e.g. ai-search / ai-scripts shipped as standalone file items, or user-authored
    // skills) must survive. A blind wipe here races those siblings — on --reinstall the
    // sibling file items are SKIP_IDENTICAL_EXISTING and never re-written, so a wipe
    // would permanently delete them. Replace only the skill dirs this source owns.
    aiInstallerMkdir($dest);
    foreach (glob($src . DIRECTORY_SEPARATOR . '*.md') ?: [] as $file) {
        $skillName = pathinfo($file, PATHINFO_FILENAME);
        $skillDir = $dest . DIRECTORY_SEPARATOR . $skillName;
        if (is_dir($skillDir)) {
            // Prune stale content inside this kit-owned skill before rewriting it.
            aiInstallerDeleteTree($skillDir);
        }
        aiInstallerMkdir($skillDir);
        // P3: hard GENERATED marker after the YAML frontmatter so the shipped
        // SKILL.md stays parseable. Idempotent.
        $content = aiInstallerInsertGeneratedHeaderAfterFrontmatter(
            (string) file_get_contents($file),
            'ai-kit installer from packages/ai-universal-rules/templates'
        );
        if (file_put_contents($skillDir . DIRECTORY_SEPARATOR . 'SKILL.md', $content) === false) {
            throw new RuntimeException('failed to copy skill file: ' . $file);
        }
    }
}

function aiInstallerCopyDirWithRename(string $src, string $dest, string $newExt): void
{
    if (!is_dir($src)) {
        throw new RuntimeException('missing source directory: ' . $src);
    }
    $srcReal = realpath($src);
    $destReal = file_exists($dest) ? realpath($dest) : false;
    if ($srcReal !== false && $destReal !== false && $srcReal === $destReal) {
        return;
    }
    if (file_exists($dest)) {
        aiInstallerDeleteTree($dest);
    }
    aiInstallerMkdir($dest);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $item) {
        $subPath = $it->getSubPathName();
        if ($item->isDir()) {
            aiInstallerMkdir($dest . DIRECTORY_SEPARATOR . $subPath);
            continue;
        }
        $baseName = pathinfo($subPath, PATHINFO_FILENAME);
        $dirPart = dirname($subPath);
        $renamedName = $baseName . $newExt;
        $target = $dest . DIRECTORY_SEPARATOR . ($dirPart !== '.' ? $dirPart . DIRECTORY_SEPARATOR . $renamedName : $renamedName);
        aiInstallerMkdir(dirname($target));
        if (!copy($item->getPathname(), $target)) {
            throw new RuntimeException('failed to copy file: ' . $item->getPathname());
        }
    }
}

/**
 * Copy a commands directory (e.g. .opencode/commands) like aiInstallerCopyDir, but inject the
 * hard GENERATED marker after each markdown file's YAML frontmatter so the shipped command files
 * carry the marker while staying frontmatter-parseable. Non-markdown files are copied verbatim.
 * Idempotent: never double-inserts the marker.
 */
function aiInstallerCopyDirAsOpenCodeCommands(string $src, string $dest, bool $cleanFirst = false): void
{
    if (!is_dir($src)) {
        throw new RuntimeException('missing source directory: ' . $src);
    }
    $srcReal = realpath($src);
    $destReal = file_exists($dest) ? realpath($dest) : false;
    if ($srcReal !== false && $destReal !== false && $srcReal === $destReal) {
        return;
    }
    if ($cleanFirst && file_exists($dest)) {
        aiInstallerDeleteTree($dest);
    }
    aiInstallerMkdir($dest);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $item) {
        $target = $dest . DIRECTORY_SEPARATOR . $it->getSubPathName();
        if ($item->isDir()) {
            aiInstallerMkdir($target);
            continue;
        }
        aiInstallerMkdir(dirname($target));
        if (strtolower($item->getExtension()) === 'md') {
            $content = aiInstallerInsertGeneratedHeaderAfterFrontmatter(
                (string) file_get_contents($item->getPathname()),
                'ai-kit installer from packages/ai-universal-rules/templates'
            );
            if (file_put_contents($target, $content) === false) {
                throw new RuntimeException('failed to copy command file: ' . $item->getPathname());
            }
            continue;
        }
        if (!copy($item->getPathname(), $target)) {
            throw new RuntimeException('failed to copy file: ' . $item->getPathname());
        }
    }
}

function aiInstallerCopyDir(string $src, string $dest, bool $cleanFirst = false): void
{
    if (!is_dir($src)) {
        throw new RuntimeException('missing source directory: ' . $src);
    }
    $srcReal = realpath($src);
    $destReal = file_exists($dest) ? realpath($dest) : false;
    if ($srcReal !== false && $destReal !== false && $srcReal === $destReal) {
        return;
    }
    if ($cleanFirst && file_exists($dest)) {
        aiInstallerDeleteTree($dest);
    }
    aiInstallerMkdir($dest);
    aiInstallerCopyTreeInto($src, $dest, 'copy file');
}

function aiInstallerSnapshotPath(string $source, string $snapshot): void
{
    if (is_file($source)) {
        aiInstallerMkdir(dirname($snapshot));
        if (!copy($source, $snapshot)) {
            throw new RuntimeException('failed to back up file: ' . $source);
        }
        return;
    }

    if (!is_dir($source)) {
        return;
    }

    aiInstallerMkdir($snapshot);
    aiInstallerCopyTreeInto($source, $snapshot, 'back up file');
}

/**
 * Recursively walk $src and copy every entry into the equivalent path under $dest, creating
 * directories as needed. Shared tree-walk used by aiInstallerCopyDir (fresh install copy) and
 * aiInstallerSnapshotPath (backup snapshot) — same traversal, only the failure-message verb
 * differs ('copy file' vs 'back up file'), preserved via $failureVerb.
 */
function aiInstallerCopyTreeInto(string $src, string $dest, string $failureVerb): void
{
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $item) {
        $target = $dest . DIRECTORY_SEPARATOR . $it->getSubPathName();
        if ($item->isDir()) {
            aiInstallerMkdir($target);
            continue;
        }
        aiInstallerMkdir(dirname($target));
        if (!copy($item->getPathname(), $target)) {
            throw new RuntimeException('failed to ' . $failureVerb . ': ' . $item->getPathname());
        }
    }
}

function aiInstallerDeleteTree(string $path): void
{
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($path);
}

function aiInstallerMkdir(string $path, int $mode = 0777): void
{
    if (is_dir($path)) {
        if ($mode !== 0777) {
            @chmod($path, $mode);
        }
        return;
    }
    if (!mkdir($path, $mode, true) && !is_dir($path)) {
        throw new RuntimeException('failed to create directory: ' . $path);
    }
    @chmod($path, $mode);
}
