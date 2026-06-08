<?php

declare(strict_types=1);

function aiInstallerBuildPlan(array $config, array $packRegistry, array $packs): array
{
    // Known prior-kit checksums let install adopt files placed by an older kit version
    // instead of flagging them foreign. Tests may inject the map directly; real callers
    // load it from the shipped source registry.
    $knownKitChecksums = is_array($config['knownKitChecksums'] ?? null)
        ? $config['knownKitChecksums']
        : aiInstallerLoadKnownKitChecksums((string) ($config['sourceRoot'] ?? ''));

    // Files recorded as kit-managed by a previous install. Under --force we may only overwrite
    // a differing on-disk file when we can prove the kit owns it (identical to source, a known
    // prior-kit checksum, or recorded in this manifest). Anything else is foreign and must not be
    // silently clobbered, even with --force. See improvement-plan invariants 1 and 2.
    //
    // The ownership gate only engages once the kit already manages this target (a manifest exists):
    // on a re-install/upgrade an unrecorded, differing file is genuinely foreign and must be
    // protected. On a true first install (no manifest) there is no ownership context, so --force
    // keeps its explicit overwrite semantics. First-install collision with a consumer's own generic
    // root file (e.g. manifest.json) is independently prevented by namespacing kit descriptors
    // under .ai/ (Part 1), so this gate need not second-guess a clean first install.
    // A full backup (--backup snapshots every displaced file to .ai/backups/) makes a force
    // overwrite recoverable, so it is an explicit, safe acknowledgment that satisfies the gate.
    $backupRequested = ($config['backup'] ?? false) === true;
    $targetRootForGate = (string) ($config['targetRoot'] ?? '');
    $kitAlreadyManagesTarget = $targetRootForGate !== ''
        && is_file($targetRootForGate . DIRECTORY_SEPARATOR . '.ai-install-manifest.json');
    $recordedKitFiles = aiInstallerLoadRecordedManifestFiles($targetRootForGate);

    $plan = [];
    foreach ($packs as $packId) {
        foreach ($packRegistry[$packId] ?? [] as $item) {
            $target = $item['target'];
            $source = $item['source'];
            $absSource = $config['sourceRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $source);
            $absTarget = $config['targetRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);
            $exists = file_exists($absTarget);
            $neverAutoMerge = ($item['never_auto_merge'] ?? false) === true;
            $adopt = ($config['adopt'] ?? false) === true;
            $action = 'CREATE';
            $reason = $exists ? 'target exists' : 'target missing';
            if ($exists && !$config['force']) {
                if (aiInstallerPathsAreIdentical($absSource, $absTarget)) {
                    // Already matches the shipped kit file: adopt silently.
                    $action = 'SKIP_IDENTICAL_EXISTING';
                    $reason = 'target exists and already matches source';
                } elseif ($adopt) {
                    // --adopt: overwrite pre-existing foreign files at kit-claimed paths
                    // (including never_auto_merge files such as opencode.jsonc). Pair with
                    // --backup to snapshot the displaced user content first.
                    $action = 'OVERWRITE_MANAGED';
                    $reason = 'foreign target adopted via --adopt';
                } elseif (aiInstallerMatchesKnownKitChecksum($target, $absTarget, $knownKitChecksums)) {
                    // The on-disk file matches a known prior kit version: it is ours, not the
                    // user's. Adopt it into the lock and refresh from the current source.
                    $action = 'ADOPT_KNOWN_KIT';
                    $reason = 'target matches a known prior kit checksum; adopting into lock';
                } elseif ($neverAutoMerge) {
                    // Files such as opencode.jsonc must never be auto-merged or silently
                    // skipped: surface a conflict and stop so the user decides.
                    $action = 'CONFLICT_FOREIGN';
                    $reason = 'target exists, differs from the kit version, and must not be auto-merged; rerun with --adopt to overwrite (a backup is recorded) or resolve manually';
                } elseif (($config['upgradeSuffix'] ?? '') !== '') {
                    $target = aiInstallerResolveUpgradeTarget($config, $item, $target);
                    $action = 'CREATE_UPGRADE_COPY';
                    $reason = 'target exists; writing suffixed upgrade copy';
                } else {
                    $action = 'SKIP_EXISTING_UNMANAGED';
                }
            } elseif ($exists && $config['force']) {
                // Force overwrites kit-managed files, but template files (skip-if-exists) are
                // user-owned once installed: never clobber them on force/upgrade unless the
                // caller explicitly adopts. This keeps "template untouched on upgrade" true even
                // when --force is used to refresh owned files.
                if (($item['merge_strategy'] ?? '') === 'skip-if-exists' && !$adopt) {
                    if (aiInstallerPathsAreIdentical($absSource, $absTarget)) {
                        $action = 'SKIP_IDENTICAL_EXISTING';
                        $reason = 'template target matches source';
                    } else {
                        $action = 'SKIP_EXISTING_UNMANAGED';
                        $reason = 'template (skip-if-exists) preserved under force';
                    }
                } elseif ($adopt) {
                    // Explicit adoption: the user accepts overwriting whatever is on disk
                    // (a backup/conflict snapshot is recorded by the apply path).
                    $action = 'OVERWRITE_MANAGED';
                    $reason = 'foreign or owned target overwritten via --force --adopt';
                } elseif (
                    !$kitAlreadyManagesTarget
                    || $backupRequested
                    || aiInstallerPathsAreIdentical($absSource, $absTarget)
                    || aiInstallerMatchesKnownKitChecksum($target, $absTarget, $knownKitChecksums)
                    || isset($recordedKitFiles[$target])
                ) {
                    // Safe to overwrite: a true first install (no ownership context, --force is
                    // explicit), an explicit --backup (displaced bytes are snapshotted), or
                    // ownership proven (identical to source, known prior-kit checksum, or recorded
                    // as kit-managed in this target's manifest).
                    $action = 'OVERWRITE_MANAGED';
                } else {
                    // Re-install/upgrade --force must NOT clobber a file the kit manages a project
                    // but never recorded — that is a genuinely foreign file. Surface a conflict so
                    // the user decides (rerun with --adopt to overwrite, with a backup recorded).
                    $action = 'CONFLICT_FOREIGN';
                    $reason = 'target exists, differs from the kit version, and is not kit-owned; rerun with --adopt to overwrite (a backup is recorded) or resolve manually';
                }
            }
            if ($exists && $config['force'] && (($item['core'] ?? false) === true) && !$config['allowCoreOverwrite']) {
                // Protect core files from force-overwrite, but an identical core file is an
                // idempotent no-op: classifying it SKIP_IDENTICAL_EXISTING keeps a clean re-run
                // zero-diff instead of reporting SKIP_PROTECTED_CORE drift every time.
                if (aiInstallerPathsAreIdentical($absSource, $absTarget)) {
                    $action = 'SKIP_IDENTICAL_EXISTING';
                    $reason = 'core target matches source';
                } else {
                    $action = 'SKIP_PROTECTED_CORE';
                }
            }

            $plan[] = array_merge($item, [
                'pack' => $packId,
                'type' => $item['type'],
                'source' => $item['source'],
                'target' => $target,
                'action' => $action,
                'required' => (bool) ($item['required'] ?? true),
                'merge_strategy' => (string) ($item['merge_strategy'] ?? ($config['force'] ? 'replace' : 'skip-if-exists')),
                'reason' => $reason,
                'requested_target' => $item['target'],
            ]);
        }
    }
    return $plan;
}

/**
 * Load the known historical kit-checksum registry shipped with the source kit.
 *
 * Returns a map of target path -> list of historical sha256 strings (bare or sha256:-prefixed).
 * Missing or malformed registries yield an empty map (adoption simply never triggers).
 *
 * @return array<string,list<string>>
 */
function aiInstallerLoadKnownKitChecksums(string $sourceRoot): array
{
    $path = $sourceRoot . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'ai'
        . DIRECTORY_SEPARATOR . 'known-kit-checksums.json';
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded) || !is_array($decoded['checksums'] ?? null)) {
        return [];
    }

    $map = [];
    foreach ($decoded['checksums'] as $target => $hashes) {
        if (!is_array($hashes)) {
            continue;
        }
        $map[(string) $target] = array_values(array_filter(
            array_map('strval', $hashes),
            static fn(string $h): bool => $h !== ''
        ));
    }

    return $map;
}

/**
 * True when the on-disk file at $absTarget matches any recorded historical kit checksum
 * for its $target path. Such a file was installed by a prior kit version and is safe to
 * adopt (overwrite/refresh) rather than treat as a foreign conflict.
 *
 * @param array<string,list<string>> $knownChecksums
 */
function aiInstallerMatchesKnownKitChecksum(string $target, string $absTarget, array $knownChecksums): bool
{
    $hashes = $knownChecksums[$target] ?? null;
    if (!is_array($hashes) || $hashes === [] || !is_file($absTarget)) {
        return false;
    }

    $actual = hash_file('sha256', $absTarget);
    foreach ($hashes as $known) {
        $normalized = str_starts_with($known, 'sha256:') ? substr($known, 7) : $known;
        if (hash_equals($normalized, $actual)) {
            return true;
        }
    }

    return false;
}

/**
 * Load the set of target-relative paths recorded as kit-managed by a previous install.
 *
 * Reads `.ai-install-manifest.json`'s `files{}` map. Returns a path => true set used by the
 * --force ownership gate so we only refresh files the kit already owns and never clobber an
 * unrelated foreign file that happens to sit at a kit-claimed path.
 *
 * @return array<string,true>
 */
function aiInstallerLoadRecordedManifestFiles(string $targetRoot): array
{
    if ($targetRoot === '') {
        return [];
    }
    $path = $targetRoot . DIRECTORY_SEPARATOR . '.ai-install-manifest.json';
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded) || !is_array($decoded['files'] ?? null)) {
        return [];
    }

    $set = [];
    foreach (array_keys($decoded['files']) as $relative) {
        $set[str_replace('\\', '/', (string) $relative)] = true;
    }

    return $set;
}

function aiInstallerPathsAreIdentical(string $source, string $target): bool
{
    if (!file_exists($source) || !file_exists($target)) {
        return false;
    }

    if (is_file($source) && is_file($target)) {
        return hash_file('sha256', $source) === hash_file('sha256', $target);
    }

    if (is_dir($source) && is_dir($target)) {
        return aiInstallerDirectoryFingerprint($source) === aiInstallerDirectoryFingerprint($target);
    }

    return false;
}

function aiInstallerDirectoryFingerprint(string $path): string
{
    $parts = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }
        $absolutePath = $item->getPathname();
        $relativePath = str_replace('\\', '/', substr($absolutePath, strlen($path) + 1));
        $parts[] = $relativePath . ':' . hash_file('sha256', $absolutePath);
    }
    sort($parts);
    return hash('sha256', implode("\n", $parts));
}

function aiInstallerResolveUpgradeTarget(array $config, array $item, string $target): string
{
    $suffix = (string) ($config['upgradeSuffix'] ?? '');
    if ($suffix === '') {
        return $target;
    }

    $candidate = aiInstallerApplyUpgradeSuffix($target, $suffix, (string) ($item['type'] ?? 'file'));
    $counter = 2;
    while (file_exists($config['targetRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate))) {
        $candidate = aiInstallerApplyUpgradeSuffix($target, $suffix . '-' . $counter, (string) ($item['type'] ?? 'file'));
        $counter++;
    }

    return $candidate;
}

function aiInstallerApplyUpgradeSuffix(string $target, string $suffix, string $type): string
{
    $normalized = trim($suffix);
    if ($normalized === '') {
        $normalized = '-upgrade';
    }

    if ($type === 'dir') {
        return rtrim($target, '/') . $normalized;
    }

    $directory = dirname($target);
    $basename = basename($target);
    $dotPosition = strrpos($basename, '.');

    if ($dotPosition === false || $dotPosition === 0) {
        $renamed = $basename . $normalized;
    } else {
        $renamed = substr($basename, 0, $dotPosition) . $normalized . substr($basename, $dotPosition);
    }

    return $directory === '.' ? $renamed : $directory . '/' . $renamed;
}
