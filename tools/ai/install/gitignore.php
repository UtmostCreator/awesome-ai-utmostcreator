<?php

declare(strict_types=1);

/**
 * Ensure the target-root .gitignore contains the given entries.
 *
 * Idempotent and content-preserving: creates .gitignore if missing, never
 * sorts or removes existing rules, and only appends entries that are not
 * already covered. Duplicate detection tolerates trailing-slash and glob
 * variants (e.g. ".ai-logs", ".ai-logs/", ".ai-logs/*", ".ai-logs/**").
 *
 * @param list<string> $entries
 */
function aiInstallerEnsureGitignoreEntries(string $targetRoot, array $entries): void
{
    $gitignorePath = rtrim($targetRoot, '/\\') . DIRECTORY_SEPARATOR . '.gitignore';

    $existing = is_file($gitignorePath) ? (string) file_get_contents($gitignorePath) : '';
    $begin = AI_MARKER_HASH_BEGIN;
    $end = AI_MARKER_HASH_END;

    $blockEntries = [];
    foreach ($entries as $entry) {
        $norm = aiInstallerNormalizeGitignoreEntry($entry);
        if ($norm === '' || isset($blockEntries[$norm])) {
            continue;
        }
        // Preserve the caller's entry form verbatim. Callers pass canonical patterns:
        // directories end with '/', file rules (e.g. '.ai/install.lock') and globs
        // (e.g. '*.tmp') must NOT get a trailing slash, or git would treat them as
        // directories and fail to ignore the actual file.
        $blockEntries[$norm] = trim($entry);
    }

    if ($blockEntries === []) {
        return;
    }

    $block = $begin . "\n" . implode("\n", array_values($blockEntries)) . "\n" . $end;
    $pattern = '/^# BEGIN ai-kit\R.*?^# END ai-kit/ms';
    if (preg_match($pattern, $existing) === 1) {
        $updated = preg_replace($pattern, $block, $existing);
        if (is_string($updated) && $updated !== $existing) {
            file_put_contents($gitignorePath, $updated);
        }
        return;
    }

    $append = $existing;
    if ($append !== '' && !str_ends_with($append, "\n")) {
        $append .= "\n";
    }
    if ($append !== '') {
        $append .= "\n";
    }
    $append .= $block . "\n";

    file_put_contents($gitignorePath, $append);
}

/** @param list<string> $paths */
function aiInstallerAssertGitignoreEffective(string $targetRoot, array $paths): void
{
    $gitDir = rtrim($targetRoot, '/\\') . DIRECTORY_SEPARATOR . '.git';
    if (!is_dir($gitDir) && !is_file($gitDir)) {
        return;
    }

    $repoCheckExit = 0;
    exec('git -C ' . escapeshellarg($targetRoot) . ' rev-parse --is-inside-work-tree 2>/dev/null', $repoCheckOut, $repoCheckExit);
    if ($repoCheckExit !== 0) {
        return;
    }

    foreach ($paths as $path) {
        $cmd = 'git -C ' . escapeshellarg($targetRoot) . ' check-ignore --quiet -- ' . escapeshellarg($path);
        $exit = 0;
        exec($cmd, $out, $exit);
        if ($exit !== 0) {
            throw new RuntimeException('gitignore entry is not effective before backup write: ' . $path);
        }
    }
}

/**
 * Normalize a .gitignore entry to a bare root for duplicate detection.
 * Strips a leading slash and any trailing slash or /* or /** glob suffix.
 */
function aiInstallerNormalizeGitignoreEntry(string $entry): string
{
    $entry = trim($entry);
    $entry = ltrim($entry, '/');
    $entry = preg_replace('#/\*\*$#', '', $entry) ?? $entry;
    $entry = preg_replace('#/\*$#', '', $entry) ?? $entry;
    return rtrim($entry, '/');
}
