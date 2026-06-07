<?php

declare(strict_types=1);

function aiInstallerBuildPlan(array $config, array $packRegistry, array $packs): array
{
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
                } else {
                    $action = 'OVERWRITE_MANAGED';
                }
            }
            if ($exists && $config['force'] && (($item['core'] ?? false) === true) && !$config['allowCoreOverwrite']) {
                $action = 'SKIP_PROTECTED_CORE';
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
