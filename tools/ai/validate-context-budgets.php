<?php

declare(strict_types=1);

require_once __DIR__ . '/ai_catalog_lib.php';

$root = resolveRoot($argv);
$packageBase = aiResolvePackageBase($root);
$packageDocsBase = aiResolvePackageDocsBase($root);
$policyPath = $root . '/' . $packageBase . 'policies/ai-file-standards.json';

$policy = loadPolicy($policyPath);
if ($policy === null) {
    exit(1);
}

$lineLimits = $policy['line_limits'] ?? null;
if (!is_array($lineLimits) || $lineLimits === []) {
    fwrite(STDERR, "ERROR: ai-file-standards policy contains no line_limits\n");
    exit(1);
}

$warnings = [];
$failures = [];
$allowlistedFailures = [];
$checked = 0;

foreach ($lineLimits as $rule) {
    if (!is_array($rule)) {
        continue;
    }

    $id = (string) ($rule['id'] ?? 'unknown');
    $patterns = $rule['patterns'] ?? null;
    $warnAbove = (int) ($rule['warn_above'] ?? 0);
    $failAbove = (int) ($rule['fail_above'] ?? 0);
    $warnAllowlist = lineLimitAllowlist($rule, 'warn_allowlist');
    $failAllowlist = lineLimitAllowlist($rule, 'fail_allowlist');

    if (!is_array($patterns) || $patterns === [] || $warnAbove < 1 || $failAbove < 1) {
        continue;
    }

    foreach ($patterns as $pattern) {
        foreach (matchingFiles($root, (string) $pattern) as $path) {
            $relativePath = relativePath($root, $path);
            if (isGeneratedPath($relativePath, $packageBase, $packageDocsBase)) {
                continue;
            }

            $lineCount = countLines($path);
            $checked++;

            if ($lineCount > $failAbove) {
                if (isset($failAllowlist[$relativePath])) {
                    $allowlistedFailures[] = sprintf(
                        'ALLOWLISTED-FAIL %s %s = %d lines > hard max %d; reason: %s',
                        $id,
                        $relativePath,
                        $lineCount,
                        $failAbove,
                        $failAllowlist[$relativePath]
                    );
                    continue;
                }

                $failures[] = sprintf(
                    'FAIL %s %s = %d lines > hard max %d',
                    $id,
                    $relativePath,
                    $lineCount,
                    $failAbove
                );
                continue;
            }

            if ($lineCount > $warnAbove && !isset($warnAllowlist[$relativePath])) {
                $warnings[] = sprintf(
                    'WARN %s %s = %d lines > soft max %d; split or extract reusable guidance before this grows',
                    $id,
                    $relativePath,
                    $lineCount,
                    $warnAbove
                );
            }
        }
    }
}

foreach ($warnings as $warning) {
    fwrite(STDOUT, $warning . "\n");
}

foreach ($allowlistedFailures as $allowlistedFailure) {
    fwrite(STDOUT, $allowlistedFailure . "\n");
}

foreach ($failures as $failure) {
    fwrite(STDERR, $failure . "\n");
}

printf(
    "OK: context budget check scanned %d file(s); warnings=%d failures=%d\n",
    $checked,
    count($warnings),
    count($failures)
);

exit($failures === [] ? 0 : 1);

/** @return array<string,string> */
function lineLimitAllowlist(array $rule, string $key): array
{
    $allowlist = [];
    foreach (($rule[$key] ?? []) as $entry) {
        if (!is_array($entry) || !is_string($entry['path'] ?? null)) {
            continue;
        }
        $allowlist[str_replace('\\', '/', (string) $entry['path'])] = (string) ($entry['reason'] ?? 'allowlisted');
    }
    return $allowlist;
}

function resolveRoot(array $argv): string
{
    $candidate = $argv[1] ?? (__DIR__ . '/../..');
    if (in_array($candidate, ['--help', '-h'], true)) {
        fwrite(STDOUT, "Usage: php tools/ai/validate-context-budgets.php [repo-root]\n");
        exit(0);
    }

    $root = realpath($candidate);
    if ($root === false || !is_dir($root)) {
        fwrite(STDERR, "ERROR: repository root not found: {$candidate}\n");
        exit(1);
    }

    return str_replace('\\', '/', $root);
}

function loadPolicy(string $path): ?array
{
    if (!is_file($path)) {
        fwrite(STDERR, "ERROR: missing ai-file-standards policy: {$path}\n");
        return null;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "ERROR: invalid ai-file-standards policy JSON: {$path}\n");
        return null;
    }

    return $decoded;
}

/** @return array<int, string> */
function matchingFiles(string $root, string $pattern): array
{
    $pattern = ltrim(str_replace('\\', '/', $pattern), '/');
    if ($pattern === '') {
        return [];
    }

    $matches = glob($root . '/' . $pattern, GLOB_NOSORT) ?: [];
    $files = [];
    foreach ($matches as $match) {
        if (is_file($match)) {
            $files[] = str_replace('\\', '/', $match);
        }
    }

    sort($files);
    return $files;
}

function countLines(string $path): int
{
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return 0;
    }

    return count($lines);
}

function relativePath(string $root, string $path): string
{
    $normalized = str_replace('\\', '/', $path);
    if (str_starts_with($normalized, $root . '/')) {
        return substr($normalized, strlen($root) + 1);
    }

    return $normalized;
}

function isGeneratedPath(string $relativePath, string $packageBase, string $packageDocsBase): bool
{
    if (str_starts_with($relativePath, 'docs/ai/generated/')) {
        return true;
    }

    return in_array($relativePath, [
        'docs/ai/catalog.md',
        $packageBase . 'catalog.json',
        $packageDocsBase . 'BROWSE.md',
        $packageDocsBase . 'INSTALL-CATALOG.md',
        'llms.txt',
    ], true);
}
