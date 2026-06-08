<?php

declare(strict_types=1);

/** @return list<string> */
function aiInstallBackupDefaultExtraTargets(): array
{
    return [
        '.ai-install-manifest.json',
        'docs/ai/generated/install-manifest.json',
        'docs/ai/SETUP.md',
        'docs/ai/POST-INSTALL.md',
        'docs/ai/installed-files.md',
        'docs/ai/project-configuration.md',
        'docs/ai/available-packs.md',
        'docs/ai/generated/install-summary.md',
        'docs/ai/generated/install-instructions.md',
        'docs/ai/generated/install-instructions.json',
    ];
}

function aiInstallBackupCreate(string $targetRoot, array $plan, string $sourceRoot = '', string $prefix = 'install-ai-kit'): array
{
    $op = aiInstallBackupOperationFromPrefix($prefix);
    $stamp = gmdate('Ymd\THis\Z');
    $backupId = $stamp . '-' . $op;
    $backupDir = aiInstallBackupDir($targetRoot, $backupId);
    $filesDir = $backupDir . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'before';
    aiInstallBackupMkdir($filesDir, 0700);

    $paths = aiInstallBackupCollectAffectedPaths($targetRoot, $plan, $sourceRoot);
    $entries = [];
    foreach ($paths as $path) {
        $rel = aiInstallBackupNormalizeRelativePath($path);
        $abs = aiInstallBackupAbsolutePath($targetRoot, $rel);
        $exists = is_file($abs);
        $snapshot = $exists ? 'files/before/' . $rel : null;
        if ($exists && is_string($snapshot)) {
            $snapshotAbs = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $snapshot);
            aiInstallBackupMkdir(dirname($snapshotAbs), 0700);
            if (!copy($abs, $snapshotAbs)) {
                throw new RuntimeException('failed to back up file: ' . $abs);
            }
        }
        $entries[$rel] = [
            'path' => $rel,
            'operation' => $exists ? 'overwrite' : 'create',
            'type' => 'file',
            'existed_before' => $exists,
            'owned_by_install' => !$exists,
            'sha256_before' => $exists ? aiInstallBackupHashFile($abs) : null,
            'sha256_after' => null,
            'snapshot_before' => $snapshot,
        ];
    }

    $manifest = [
        'schema' => 'ai-install-backup/v1',
        'backup_id' => $backupId,
        'transaction_id' => $backupId,
        'created_at' => gmdate('c'),
        'updated_at' => gmdate('c'),
        'state' => 'backed_up',
        'entries' => array_values($entries),
        'integrity' => [
            'manifest_sha256' => null,
            'snapshots_sha256' => aiInstallBackupSnapshotHashes($backupDir, array_values($entries)),
        ],
    ];
    aiInstallBackupWriteManifest($backupDir, $manifest);

    aiInstallBackupPruneOld($targetRoot, 5);

    return [
        'backup_id' => $backupId,
        'backup_dir' => '.ai/backups/' . $backupId,
        'entry_count' => count($entries),
        'schema' => 'ai-install-backup/v1',
    ];
}

/**
 * Keep only the most recent $keep backup directories under .ai/backups, deleting older ones.
 * Backup ids are timestamp-suffixed, so lexical sort matches chronological order.
 */
function aiInstallBackupPruneOld(string $targetRoot, int $keep): void
{
    if ($keep < 1) {
        return;
    }

    $base = $targetRoot . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'backups';
    if (!is_dir($base)) {
        return;
    }

    $dirs = [];
    foreach (scandir($base) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $abs = $base . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($abs)) {
            $dirs[] = $entry;
        }
    }

    if (count($dirs) <= $keep) {
        return;
    }

    sort($dirs, SORT_STRING);
    $toDelete = array_slice($dirs, 0, count($dirs) - $keep);
    foreach ($toDelete as $dir) {
        aiInstallBackupDeleteTree($base . DIRECTORY_SEPARATOR . $dir);
    }
}

function aiInstallBackupDeleteTree(string $path): void
{
    if (!is_dir($path)) {
        if (is_file($path) || is_link($path)) {
            @unlink($path);
        }
        return;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($path);
}

function aiInstallBackupRecordAfter(string $targetRoot, string $backupId, array $plan = [], string $sourceRoot = '', string $state = 'applied'): array
{
    $backupDir = aiInstallBackupDir($targetRoot, $backupId);
    $manifest = aiInstallBackupLoadManifest($targetRoot, $backupId);
    if (($manifest['schema'] ?? '') !== 'ai-install-backup/v1') {
        return $manifest;
    }

    $entries = [];
    foreach (($manifest['entries'] ?? []) as $entry) {
        if (!is_array($entry) || !is_string($entry['path'] ?? null)) {
            continue;
        }
        $rel = aiInstallBackupNormalizeRelativePath((string) $entry['path']);
        $abs = aiInstallBackupAbsolutePath($targetRoot, $rel);
        $entry['path'] = $rel;
        $entry['sha256_after'] = is_file($abs) ? aiInstallBackupHashFile($abs) : null;
        $entries[$rel] = $entry;
    }

    foreach (aiInstallBackupCollectAffectedPaths($targetRoot, $plan, $sourceRoot) as $path) {
        $rel = aiInstallBackupNormalizeRelativePath($path);
        if (isset($entries[$rel])) {
            continue;
        }
        $abs = aiInstallBackupAbsolutePath($targetRoot, $rel);
        if (!is_file($abs)) {
            continue;
        }
        $entries[$rel] = [
            'path' => $rel,
            'operation' => 'create',
            'type' => 'file',
            'existed_before' => false,
            'owned_by_install' => true,
            'sha256_before' => null,
            'sha256_after' => aiInstallBackupHashFile($abs),
            'snapshot_before' => null,
        ];
    }

    $manifest['entries'] = array_values($entries);
    $manifest['state'] = $state;
    $manifest['updated_at'] = gmdate('c');
    $manifest['integrity']['snapshots_sha256'] = aiInstallBackupSnapshotHashes($backupDir, $manifest['entries']);
    $manifest['integrity']['manifest_sha256'] = null;
    aiInstallBackupWriteManifest($backupDir, $manifest);
    return $manifest;
}

function aiInstallBackupUpdateState(string $targetRoot, string $backupId, string $state, ?string $failure = null): void
{
    $manifest = aiInstallBackupLoadManifest($targetRoot, $backupId);
    if (($manifest['schema'] ?? '') !== 'ai-install-backup/v1') {
        return;
    }
    $manifest['state'] = $state;
    $manifest['updated_at'] = gmdate('c');
    if ($failure !== null) {
        $manifest['failure'] = $failure;
    }
    $backupDir = aiInstallBackupDir($targetRoot, $backupId);
    aiInstallBackupWriteManifest($backupDir, $manifest);
}

/**
 * Append one transaction/audit event under `.ai/logs/` without rewriting prior events.
 *
 * @param array<string,mixed> $event
 */
function aiInstallBackupAppendAudit(string $root, string $event, array $data = []): void
{
    $dir = $root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'logs';
    aiInstallBackupMkdir($dir, 0700);
    $payload = array_merge([
        'schema' => 'ai-install-audit/v1',
        'ts' => gmdate('c'),
        'event' => $event,
    ], $data);
    file_put_contents(
        $dir . DIRECTORY_SEPARATOR . 'install-transactions-' . gmdate('Ymd') . '.jsonl',
        json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND
    );
}

/** Mark an interrupted/failed write window when no backup exists yet, so verify can detect it. */
function aiInstallBackupMarkRecoverableNoBackup(string $root, string $reason): void
{
    $dir = $root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'logs';
    aiInstallBackupMkdir($dir, 0700);
    $marker = [
        'schema' => 'ai-install-recoverable/v1',
        'ts' => gmdate('c'),
        'state' => 'failed_recoverable_no_backup',
        'reason' => $reason,
    ];
    file_put_contents(
        $dir . DIRECTORY_SEPARATOR . 'install-recoverable.json',
        json_encode($marker, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
    );
    aiInstallBackupAppendAudit($root, 'install_failed_recoverable_no_backup', ['reason' => $reason]);
}

function aiInstallBackupLoadManifest(string $root, string $backupId): array
{
    aiInstallBackupNormalizeRelativePath($backupId);
    $path = aiInstallBackupDir($root, $backupId) . DIRECTORY_SEPARATOR . 'manifest.json';
    if (!is_file($path)) {
        throw new RuntimeException('backup manifest not found for backup id: ' . $backupId);
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('invalid backup manifest JSON for backup id: ' . $backupId);
    }
    return $decoded;
}

/** @param list<string> $only */
function aiInstallBackupRollback(string $root, string $backupId, bool $apply, bool $force = false, array $only = []): array
{
    $manifest = aiInstallBackupLoadManifest($root, $backupId);
    if (($manifest['schema'] ?? '') !== 'ai-install-backup/v1') {
        return aiInstallBackupRollbackLegacy($root, $backupId, $manifest, $apply);
    }

    $base = aiInstallBackupDir($root, $backupId);
    $only = array_map('aiInstallBackupNormalizeRelativePath', $only);
    $restore = [];
    $delete = [];
    $noop = [];
    $conflicts = [];

    foreach (($manifest['entries'] ?? []) as $entry) {
        if (!is_array($entry) || !is_string($entry['path'] ?? null)) {
            continue;
        }
        $rel = aiInstallBackupNormalizeRelativePath((string) $entry['path']);
        if (!aiInstallBackupMatchesOnly($rel, $only)) {
            continue;
        }
        $abs = aiInstallBackupAbsolutePath($root, $rel);
        $current = is_file($abs) ? aiInstallBackupHashFile($abs) : null;
        $after = is_string($entry['sha256_after'] ?? null) ? $entry['sha256_after'] : null;
        $existed = (bool) ($entry['existed_before'] ?? false);
        $owned = (bool) ($entry['owned_by_install'] ?? false);

        if ($existed) {
            if ($current === $after || $current === null || $force) {
                $restore[] = $rel;
            } else {
                $conflicts[] = ['path' => $rel, 'reason' => 'changed_after_install'];
            }
            continue;
        }

        if (!$owned) {
            $noop[] = $rel;
            continue;
        }
        if ($current === null) {
            $noop[] = $rel;
        } elseif ($current === $after || $force) {
            $delete[] = $rel;
        } else {
            $conflicts[] = ['path' => $rel, 'reason' => 'created_file_changed_after_install'];
        }
    }

    if ($apply && $conflicts !== [] && !$force) {
        return [
            'status' => 'blocked',
            'backup' => $backupId,
            'dry_run' => false,
            'restore' => $restore,
            'delete' => $delete,
            'noop' => $noop,
            'conflicts' => $conflicts,
        ];
    }

    $restored = [];
    $deleted = [];
    if ($apply) {
        foreach ($restore as $rel) {
            $entry = aiInstallBackupFindEntry($manifest, $rel);
            $snapshot = is_array($entry) && is_string($entry['snapshot_before'] ?? null) ? $entry['snapshot_before'] : null;
            if ($snapshot === null) {
                $conflicts[] = ['path' => $rel, 'reason' => 'missing_snapshot'];
                continue;
            }
            $source = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, aiInstallBackupNormalizeRelativePath($snapshot));
            if (!is_file($source)) {
                $conflicts[] = ['path' => $rel, 'reason' => 'snapshot_not_found'];
                continue;
            }
            $dest = aiInstallBackupAbsolutePath($root, $rel);
            aiInstallBackupMkdir(dirname($dest));
            if (!copy($source, $dest)) {
                throw new RuntimeException('failed to restore backup path: ' . $rel);
            }
            $restored[] = $rel;
        }
        foreach ($delete as $rel) {
            $dest = aiInstallBackupAbsolutePath($root, $rel);
            if (is_file($dest) || is_link($dest)) {
                @unlink($dest);
            }
            $deleted[] = $rel;
        }
        aiInstallBackupUpdateState($root, $backupId, $conflicts === [] ? 'rolled_back' : 'failed_recoverable');
    }

    return [
        'status' => $conflicts === [] ? 'ok' : 'conflicts',
        'backup' => $backupId,
        'dry_run' => !$apply,
        'restore' => $restore,
        'delete' => $delete,
        'noop' => $noop,
        'conflicts' => $conflicts,
        'restored_targets' => $restored,
        'deleted_targets' => $deleted,
        'only' => $only,
    ];
}

/** @return list<string> */
function aiInstallBackupCollectAffectedPaths(string $targetRoot, array $plan, string $sourceRoot = ''): array
{
    $paths = [];
    $add = static function (string $path) use (&$paths): void {
        $paths[aiInstallBackupNormalizeRelativePath($path)] = true;
    };

    $manifestPath = $targetRoot . DIRECTORY_SEPARATOR . '.ai-install-manifest.json';
    if (is_file($manifestPath)) {
        $decoded = json_decode((string) file_get_contents($manifestPath), true);
        if (is_array($decoded) && is_array($decoded['files'] ?? null)) {
            foreach (array_keys($decoded['files']) as $target) {
                if (is_string($target) && $target !== '') {
                    $abs = aiInstallBackupAbsolutePath($targetRoot, $target);
                    if (is_dir($abs)) {
                        foreach (aiInstallBackupListFiles($abs, $target) as $file) {
                            $add($file);
                        }
                    } else {
                        $add($target);
                    }
                }
            }
        }
    }

    foreach ($plan as $item) {
        if (in_array((string) ($item['action'] ?? ''), ['SKIP_EXISTING_UNMANAGED', 'SKIP_PROTECTED_CORE', 'SKIP_IDENTICAL_EXISTING'], true)) {
            continue;
        }
        $target = (string) ($item['target'] ?? '');
        if ($target === '') {
            continue;
        }
        $targetAbs = aiInstallBackupAbsolutePath($targetRoot, $target);
        if (is_file($targetAbs)) {
            $add($target);
        } elseif (is_dir($targetAbs)) {
            foreach (aiInstallBackupListFiles($targetAbs, $target) as $file) {
                $add($file);
            }
        }
        foreach (aiInstallBackupPlannedOutputPaths($sourceRoot, $item) as $planned) {
            $add($planned);
        }
    }

    foreach (aiInstallBackupDefaultExtraTargets() as $target) {
        $abs = aiInstallBackupAbsolutePath($targetRoot, $target);
        if (is_file($abs)) {
            $add($target);
        }
    }

    $out = array_keys($paths);
    sort($out);
    return $out;
}

/** @return list<string> */
function aiInstallBackupPlannedOutputPaths(string $sourceRoot, array $item): array
{
    if ($sourceRoot === '') {
        return [];
    }
    $source = (string) ($item['source'] ?? '');
    $target = (string) ($item['target'] ?? '');
    if ($source === '' || $target === '') {
        return [];
    }
    $srcAbs = $sourceRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $source);
    if (($item['type'] ?? '') === 'file') {
        return is_file($srcAbs) ? [aiInstallBackupNormalizeRelativePath($target)] : [];
    }
    if (!is_dir($srcAbs)) {
        return [];
    }
    $paths = [];
    $files = aiInstallBackupListFiles($srcAbs, '');
    foreach ($files as $file) {
        $sub = ltrim($file, '/');
        if (($item['install_type'] ?? '') === 'skill-dirs') {
            if (pathinfo($sub, PATHINFO_EXTENSION) !== 'md') {
                continue;
            }
            $paths[] = rtrim($target, '/') . '/' . pathinfo($sub, PATHINFO_FILENAME) . '/SKILL.md';
        } elseif (isset($item['rename_ext'])) {
            $dir = dirname($sub);
            $name = pathinfo($sub, PATHINFO_FILENAME) . (string) $item['rename_ext'];
            $paths[] = rtrim($target, '/') . '/' . ($dir !== '.' ? $dir . '/' : '') . $name;
        } else {
            $paths[] = rtrim($target, '/') . '/' . $sub;
        }
    }
    return array_map('aiInstallBackupNormalizeRelativePath', $paths);
}

/** @return list<string> */
function aiInstallBackupListFiles(string $absRoot, string $relativeRoot): array
{
    if (!is_dir($absRoot)) {
        return [];
    }
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $item) {
        if (!$item->isFile()) {
            continue;
        }
        $sub = str_replace('\\', '/', substr($item->getPathname(), strlen($absRoot) + 1));
        $out[] = aiInstallBackupNormalizeRelativePath(rtrim($relativeRoot, '/') . '/' . $sub);
    }
    return $out;
}

function aiInstallBackupNormalizeRelativePath(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    $path = ltrim($path, '/');
    if ($path === '' || str_contains($path, "\0") || preg_match('#(^|/)\.\.(/|$)#', $path) === 1 || str_starts_with($path, '.git/')) {
        throw new RuntimeException('unsafe backup path: ' . $path);
    }
    return $path;
}

function aiInstallBackupAbsolutePath(string $root, string $relative): string
{
    return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, aiInstallBackupNormalizeRelativePath($relative));
}

function aiInstallBackupHashFile(string $path): string
{
    return 'sha256:' . hash_file('sha256', $path);
}

function aiInstallBackupMkdir(string $path, int $mode = 0777): void
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

function aiInstallBackupOperationFromPrefix(string $prefix): string
{
    $op = $prefix === 'install-ai-kit' ? 'install' : $prefix;
    $op = preg_replace('/[^A-Za-z0-9_-]+/', '-', $op) ?? 'install';
    $op = trim($op, '-_');
    return $op === '' ? 'install' : $op;
}

function aiInstallBackupDir(string $root, string $backupId): string
{
    aiInstallBackupNormalizeRelativePath($backupId);
    $new = $root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $backupId;
    $legacy = $root . DIRECTORY_SEPARATOR . '.ai-backups' . DIRECTORY_SEPARATOR . $backupId;
    if (is_dir($legacy) && !is_dir($new)) {
        return $legacy;
    }
    return $new;
}

function aiInstallBackupWriteManifest(string $backupDir, array $manifest): void
{
    $manifest['integrity']['manifest_sha256'] = null;
    $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $manifest['integrity']['manifest_sha256'] = 'sha256:' . hash('sha256', $json);
    $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    file_put_contents($backupDir . DIRECTORY_SEPARATOR . 'manifest.json', $json);
}

function aiInstallBackupSnapshotHashes(string $backupDir, array $entries): array
{
    $hashes = [];
    foreach ($entries as $entry) {
        if (!is_array($entry) || !is_string($entry['snapshot_before'] ?? null)) {
            continue;
        }
        $rel = aiInstallBackupNormalizeRelativePath((string) $entry['snapshot_before']);
        $abs = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_file($abs)) {
            $hashes[$rel] = aiInstallBackupHashFile($abs);
        }
    }
    ksort($hashes);
    return $hashes;
}

function aiInstallBackupFindEntry(array $manifest, string $path): ?array
{
    foreach (($manifest['entries'] ?? []) as $entry) {
        if (is_array($entry) && ($entry['path'] ?? null) === $path) {
            return $entry;
        }
    }
    return null;
}

/** @param list<string> $only */
function aiInstallBackupMatchesOnly(string $path, array $only): bool
{
    if ($only === []) {
        return true;
    }
    foreach ($only as $candidate) {
        $candidate = rtrim($candidate, '/');
        if ($path === $candidate || str_starts_with($path, $candidate . '/')) {
            return true;
        }
    }
    return false;
}

function aiInstallBackupRollbackLegacy(string $root, string $backupId, array $manifest, bool $apply): array
{
    $targets = $manifest['targets'] ?? [];
    $base = aiInstallBackupDir($root, $backupId);
    $filesDir = $base . DIRECTORY_SEPARATOR . 'files';
    $restored = [];
    if ($apply && is_dir($filesDir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($filesDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $item) {
            $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($filesDir) + 1));
            $dest = aiInstallBackupAbsolutePath($root, $rel);
            if ($item->isDir()) {
                aiInstallBackupMkdir($dest);
                continue;
            }
            aiInstallBackupMkdir(dirname($dest));
            copy($item->getPathname(), $dest);
            $restored[] = $rel;
        }
    }
    return [
        'status' => 'ok',
        'backup' => $backupId,
        'legacy' => true,
        'dry_run' => !$apply,
        'target_count' => is_array($targets) ? count($targets) : 0,
        'restored_targets' => $restored,
    ];
}

/** @return list<string> */
function aiInstallBackupParseOnlyArgs(array $args): array
{
    $out = [];
    for ($i = 0; $i < count($args); $i++) {
        $arg = (string) $args[$i];
        if ($arg === '--only') {
            foreach (explode(',', (string) ($args[++$i] ?? '')) as $item) {
                if (trim($item) !== '') {
                    $out[] = aiInstallBackupNormalizeRelativePath(trim($item));
                }
            }
            continue;
        }
        if (str_starts_with($arg, '--only=')) {
            foreach (explode(',', substr($arg, 7)) as $item) {
                if (trim($item) !== '') {
                    $out[] = aiInstallBackupNormalizeRelativePath(trim($item));
                }
            }
        }
    }
    return array_values(array_unique($out));
}
