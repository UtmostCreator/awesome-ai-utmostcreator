<?php

declare(strict_types=1);

/**
 * P5-c: mode for kit-private directories (backups/conflicts/logs/templates-new). 0700 keeps
 * snapshotted user bytes out of world-readable space.
 */
function aiInstallerPrivateDirMode(): int
{
    return 0700;
}

/**
 * P5-c: resolve a conflict subtree path under a single operation-tagged timestamped root:
 * `.ai/conflicts/<ts>-<op>/<kind>`. `$kind` is one of files|incoming|removed.
 *
 * Callers within one operation should pass the same `$stamp` so all subtrees share one root;
 * when `$stamp` is null a fresh UTC stamp is generated.
 */
function aiInstallerPrivateConflictDir(string $targetRoot, string $op, string $kind, ?string $stamp = null): string
{
    $stamp ??= gmdate('Ymd\THis\Z');
    return $targetRoot . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'conflicts'
        . DIRECTORY_SEPARATOR . $stamp . '-' . $op . DIRECTORY_SEPARATOR . $kind;
}

/** P5-c: repo-relative form of aiInstallerPrivateConflictDir for reporting. */
function aiInstallerPrivateConflictRel(string $op, string $kind, string $stamp): string
{
    return '.ai/conflicts/' . $stamp . '-' . $op . '/' . $kind;
}

/** P5-c: repo-relative root for operation-tagged backups. */
function aiInstallerPrivateBackupRel(string $op, string $stamp): string
{
    return '.ai/backups/' . $stamp . '-' . $op;
}

/** P5-c: target-absolute root for operation-tagged backups. */
function aiInstallerPrivateBackupDir(string $targetRoot, string $op, string $stamp): string
{
    return $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, aiInstallerPrivateBackupRel($op, $stamp));
}

/** P5-c: repo-relative root for template refreshes. */
function aiInstallerPrivateTemplatesNewRel(): string
{
    return '.ai/templates-new';
}

/**
 * Template refresh channel. When a skip-if-exists template is preserved (the user already has the
 * file) but the shipped source differs from the user's copy, write the new upstream version to
 * `.ai/templates-new/<target>` so the user can review and merge. Never overwrites the user's file.
 *
 * @param array<string,mixed> $config
 * @param array<string,mixed> $item
 * @return string|null relative path of the written template-new file, or null when nothing to do
 */
function aiInstallerOfferTemplateRefresh(array $config, array $item): ?string
{
    $source = (string) ($item['source'] ?? '');
    $target = (string) ($item['target'] ?? '');
    if ($source === '' || $target === '') {
        return null;
    }

    $src = $config['sourceRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $source);
    $dest = $config['targetRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);
    if (!is_file($src) || !is_file($dest)) {
        return null;
    }

    // Only surface a refresh when the upstream template actually changed vs the user's copy.
    if (aiInstallerPathsAreIdentical($src, $dest)) {
        return null;
    }

    $rel = aiInstallerPrivateTemplatesNewRel() . '/' . $target;
    $out = $config['targetRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    aiInstallerMkdir(dirname($out), aiInstallerPrivateDirMode());
    if (!@copy($src, $out)) {
        return null;
    }

    return $rel;
}

/**
 * P3-c: surface an incoming kit file that collided with an existing foreign file.
 *
 * When a non-template kit file is skipped because a differing foreign (user-authored)
 * file already occupies its target path, write the kit version to
 * .ai/conflicts/<ts>-install/incoming/<path> so the user can diff/merge. The foreign file on
 * disk is never overwritten. Returns the relative path of the incoming copy, or null
 * when there is no collision (target missing) or the existing file already matches.
 */
function aiInstallerOfferIncomingConflict(array $config, array $item): ?string
{
    $source = (string) ($item['source'] ?? '');
    $target = (string) ($item['target'] ?? '');
    if ($source === '' || $target === '') {
        return null;
    }

    $src = $config['sourceRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $source);
    $dest = $config['targetRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);
    // Only a genuine collision with an existing, differing foreign file is routed.
    if (!is_file($src) || !is_file($dest) || aiInstallerPathsAreIdentical($src, $dest)) {
        return null;
    }

    $stamp = gmdate('Ymd\THis\Z');
    $rel = aiInstallerPrivateConflictRel('install', 'incoming', $stamp) . '/' . $target;
    $out = $config['targetRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    aiInstallerMkdir(dirname($out), aiInstallerPrivateDirMode());
    if (!@copy($src, $out)) {
        return null;
    }

    return $rel;
}

/**
 * Snapshot a foreign file that is about to be overwritten by an adopted --force/--adopt overwrite.
 *
 * Copies the existing on-disk file to `.ai/conflicts/<ts>-install/files/<path>` so user bytes are
 * never destroyed without a recoverable copy, even when the caller did not pass --backup. Returns
 * the relative snapshot path, or null when there is nothing to snapshot (no existing file, or the
 * existing file already matches the incoming source).
 */
function aiInstallerSnapshotAdoptedForeign(array $config, array $item, string $stamp): ?string
{
    $source = (string) ($item['source'] ?? '');
    $target = (string) ($item['target'] ?? '');
    if ($source === '' || $target === '') {
        return null;
    }

    $src = $config['sourceRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $source);
    $dest = $config['targetRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);
    // Only snapshot a genuine, differing existing file.
    if (!is_file($dest) || (is_file($src) && aiInstallerPathsAreIdentical($src, $dest))) {
        return null;
    }

    $rel = aiInstallerPrivateConflictRel('install', 'files', $stamp) . '/' . $target;
    $out = $config['targetRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    aiInstallerMkdir(dirname($out), aiInstallerPrivateDirMode());
    if (!@copy($dest, $out)) {
        return null;
    }

    return $rel;
}
