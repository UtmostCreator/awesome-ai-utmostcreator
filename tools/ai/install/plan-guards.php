<?php

declare(strict_types=1);

function aiInstallerIsSelfTargetInstall(array $config): bool
{
    $sourceRoot = str_replace('\\', '/', (string) ($config['sourceRoot'] ?? ''));
    $targetRoot = str_replace('\\', '/', (string) ($config['targetRoot'] ?? ''));

    return $sourceRoot !== '' && $sourceRoot === $targetRoot;
}

function aiInstallerAssertPlanSourcesExist(array $config, array $plan): void
{
    $missing = [];
    foreach ($plan as $item) {
        if (in_array((string) ($item['action'] ?? ''), ['SKIP_EXISTING_UNMANAGED', 'SKIP_PROTECTED_CORE', 'SKIP_IDENTICAL_EXISTING'], true)) {
            continue;
        }

        $source = (string) ($item['source'] ?? '');
        $type = (string) ($item['type'] ?? 'file');
        $abs = (string) $config['sourceRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $source);
        $exists = $type === 'dir' ? is_dir($abs) : is_file($abs);
        if (!$exists) {
            $missing[] = $source . ' -> ' . (string) ($item['target'] ?? 'unknown');
        }
    }

    if ($missing !== []) {
        $sample = array_slice($missing, 0, 20);
        $suffix = count($missing) > count($sample) ? '; ... and ' . (count($missing) - count($sample)) . ' more' : '';
        throw new RuntimeException('install source surface is incomplete; missing selected pack source(s): ' . implode('; ', $sample) . $suffix);
    }
}

/**
 * Stop the install when any never_auto_merge target is in CONFLICT_FOREIGN state.
 *
 * @param list<array<string,mixed>> $plan
 */
function aiInstallerAssertNoForeignConflicts(array $plan): void
{
    $conflicts = [];
    foreach ($plan as $item) {
        if (($item['action'] ?? '') === 'CONFLICT_FOREIGN') {
            $conflicts[] = (string) ($item['target'] ?? '');
        }
    }

    if ($conflicts === []) {
        return;
    }

    $list = implode(', ', array_filter($conflicts));
    throw new RuntimeException(
        'install aborted: existing file(s) conflict with the kit and must not be auto-merged: ' . $list . '. '
        . 'These files are managed as adopt-or-conflict (e.g. opencode.jsonc). '
        . 'Review them, then rerun with --adopt to overwrite (a backup is recorded with --backup) '
        . 'or resolve the differences manually.'
    );
}

function aiInstallerAssertAllowedTarget(array $config): void
{
    $sourceRoot = str_replace('\\', '/', (string) ($config['sourceRoot'] ?? ''));
    $targetRoot = str_replace('\\', '/', (string) ($config['targetRoot'] ?? ''));

    if ($sourceRoot === '' || $targetRoot === '') {
        return;
    }

    $reservedExampleRoot = rtrim($sourceRoot, '/') . '/packages/ai-universal-rules/examples';

    if ($targetRoot === $reservedExampleRoot || str_starts_with($targetRoot . '/', $reservedExampleRoot . '/')) {
        throw new RuntimeException('installer target under packages/ai-universal-rules/examples is reserved; install into a dedicated external project directory instead');
    }

    // Git-root guard: refuse to install (and patch .gitignore) into a directory that is not
    // a git repository root, unless explicitly overridden. This prevents accidental writes
    // into arbitrary directories. Dry-run is exempt because it writes nothing.
    if (empty($config['dryRun']) && empty($config['allowNonGit'])) {
        $gitDir = rtrim((string) ($config['targetRoot'] ?? ''), '/\\') . DIRECTORY_SEPARATOR . '.git';
        if (!is_dir($gitDir) && !is_file($gitDir)) {
            throw new RuntimeException(
                'installer target is not a git repository root (no .git found at ' . $config['targetRoot'] . '). '
                . 'Run inside a git repo, or pass --allow-non-git to override.'
            );
        }
    }
}

/**
 * Resolve and normalize a plan item's target for path-safety guards. Shared by
 * aiInstallerAssertSafePlanTargets and aiInstallerAssertNoCaseCollisions.
 *
 * @return array{0:string,1:?string} [rawTarget, normalizedTarget]; normalizedTarget is null
 *     when the item has no target to check (caller should skip it).
 */
function aiInstallerPlanItemTarget(array $item): array
{
    $target = (string) ($item['target'] ?? '');
    if ($target === '') {
        return [$target, null];
    }
    return [$target, str_replace('\\', '/', $target)];
}

/**
 * PathGuard: validate every plan target stays inside the target root. Rejects path traversal
 * (`..`), absolute targets, and any target whose existing parent chain escapes the root via a
 * symlink. Throws on the first violation so installs fail closed before writing.
 */
function aiInstallerAssertSafePlanTargets(string $targetRoot, array $plan): void
{
    $rootReal = realpath($targetRoot);
    if ($rootReal === false) {
        throw new RuntimeException('PathGuard: target root does not resolve: ' . $targetRoot);
    }
    $rootReal = rtrim(str_replace('\\', '/', $rootReal), '/');

    foreach ($plan as $item) {
        [$target, $normalized] = aiInstallerPlanItemTarget($item);
        if ($normalized === null) {
            continue;
        }
        if (str_starts_with($normalized, '/') || preg_match('#^[A-Za-z]:#', $normalized) === 1) {
            throw new RuntimeException('PathGuard: absolute install target rejected: ' . $target);
        }
        if (preg_match('#(^|/)\.\.(/|$)#', $normalized) === 1) {
            throw new RuntimeException('PathGuard: path traversal in install target rejected: ' . $target);
        }

        // Walk the deepest existing ancestor; if it resolves outside the root, reject (symlink escape).
        $candidate = $rootReal . '/' . $normalized;
        $ancestor = $candidate;
        while ($ancestor !== '' && !file_exists($ancestor)) {
            $parent = dirname($ancestor);
            if ($parent === $ancestor) {
                break;
            }
            $ancestor = $parent;
        }
        $ancestorReal = $ancestor !== '' ? realpath($ancestor) : false;
        if ($ancestorReal !== false) {
            $ancestorReal = rtrim(str_replace('\\', '/', $ancestorReal), '/');
            if ($ancestorReal !== $rootReal && !str_starts_with($ancestorReal . '/', $rootReal . '/')) {
                throw new RuntimeException('PathGuard: install target escapes root via symlink: ' . $target);
            }
        }
    }
}

/**
 * Case-collision guard: detect distinct plan targets that map to the same path under a
 * case-insensitive filesystem. Two such targets would overwrite each other unpredictably,
 * so installs fail closed with a clear message listing the colliding pair.
 */
function aiInstallerAssertNoCaseCollisions(array $plan): void
{
    $seen = [];
    foreach ($plan as $item) {
        [, $normalized] = aiInstallerPlanItemTarget($item);
        if ($normalized === null) {
            continue;
        }
        $key = strtolower($normalized);
        if (isset($seen[$key]) && $seen[$key] !== $normalized) {
            throw new RuntimeException(
                'case-collision in install targets: "' . $seen[$key] . '" vs "' . $normalized
                . '" would clobber each other on case-insensitive filesystems'
            );
        }
        $seen[$key] = $normalized;
    }
}
