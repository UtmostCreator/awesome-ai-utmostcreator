<?php

declare(strict_types=1);

/**
 * Post-install placeholder verifier.
 *
 * Scans an installed project for any required placeholder tokens that were
 * not substituted during install. A successful install means every required
 * token is resolved and no canonical surface still contains the literal
 * `<UPPERCASE_TOKEN>` form for those tokens.
 *
 * Usage:
 *   php tools/ai/verify-install-placeholders.php
 *       (uses repository root containing this script)
 *
 *   php tools/ai/verify-install-placeholders.php --target=/path/to/installed-project
 *       (verifies a remote installed project)
 *
 *   php tools/ai/verify-install-placeholders.php --json
 *       (emits a JSON evidence envelope for downstream pipelines)
 *
 * Exit codes:
 *   0 - all required placeholders resolved (or only optional placeholders remain)
 *   1 - at least one required placeholder is still present in canonical surfaces
 *   2 - usage or environment error (missing dictionary, bad target, etc.)
 */

$targetArg = null;
$emitJson = false;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--target=')) {
        $targetArg = substr($arg, 9);
    } elseif ($arg === '--json' || $arg === '-j') {
        $emitJson = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, "Usage: php tools/ai/verify-install-placeholders.php [--target=PATH] [--json]\n");
        exit(0);
    }
}

$kitRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
if ($kitRoot === false) {
    emitError($emitJson, 'kit_root_unresolved', 'could not resolve kit root for dictionary lookup');
    exit(2);
}

$target = $targetArg !== null ? realpath($targetArg) : $kitRoot;
if ($target === false) {
    emitError($emitJson, 'target_unresolved', 'could not resolve --target path');
    exit(2);
}

$dictionary = loadDictionary($kitRoot);
if ($dictionary === null) {
    emitError($emitJson, 'dictionary_missing', 'PLACEHOLDERS.md not found in kit root');
    exit(2);
}

$requiredTokens = requiredTokensList();

$scanRoots = [
    'AGENTS.md',
    'CLAUDE.md',
    'README.md',
    'docs/ai',
    '.github',
    '.opencode',
];

$findings = [];
foreach ($scanRoots as $rel) {
    $abs = $target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (is_file($abs)) {
        scanFile($abs, $target, $requiredTokens, $findings);
        continue;
    }
    if (!is_dir($abs)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['md', 'json', 'yaml', 'yml', 'jsonc'], true)) {
            continue;
        }
        scanFile($file->getPathname(), $target, $requiredTokens, $findings);
    }
}

$unresolvedRequired = [];
$unresolvedOptional = [];
foreach ($findings as $finding) {
    if ($finding['required']) {
        $unresolvedRequired[] = $finding;
    } else {
        $unresolvedOptional[] = $finding;
    }
}

if ($emitJson) {
    $envelope = [
        'schema' => 'ai.verify-install-placeholders.v1',
        'status' => $unresolvedRequired === [] ? 'ok' : 'error',
        'tool' => 'verify-install-placeholders.php',
        'target' => $target,
        'kit_root' => $kitRoot,
        'dictionary_size' => count($dictionary),
        'required_tokens' => $requiredTokens,
        'unresolved_required' => $unresolvedRequired,
        'unresolved_optional' => $unresolvedOptional,
        'warnings' => [],
        'errors' => $unresolvedRequired === [] ? [] : ['at least one required placeholder is still present'],
    ];
    fwrite(STDOUT, json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    exit($unresolvedRequired === [] ? 0 : 1);
}

if ($unresolvedRequired === []) {
    fwrite(STDOUT, "OK: no required placeholders remain in installed surfaces\n");
    if ($unresolvedOptional !== []) {
        fwrite(STDOUT, sprintf("NOTE: %d optional placeholder occurrence(s) remain (non-blocking)\n", count($unresolvedOptional)));
        foreach (firstFew($unresolvedOptional, 5) as $finding) {
            fwrite(STDOUT, "  - {$finding['token']} in {$finding['file']}:{$finding['line']}\n");
        }
    }
    exit(0);
}

fwrite(STDERR, sprintf("ERROR: %d required placeholder occurrence(s) still present\n", count($unresolvedRequired)));
foreach ($unresolvedRequired as $finding) {
    fwrite(STDERR, "  - {$finding['token']} in {$finding['file']}:{$finding['line']}\n");
}
fwrite(STDERR, "Install is not complete. Resolve every required placeholder before trusting AI write-capable flows.\n");
fwrite(STDERR, "See PLACEHOLDERS.md for substitution guidance.\n");
exit(1);

/**
 * Load the placeholder dictionary from the source package, or from root PLACEHOLDERS.md in installed projects.
 *
 * @return array<int, string>|null Sorted token list, or null if not found.
 */
function loadDictionary(string $kitRoot): ?array
{
    $paths = [
        $kitRoot . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'ai-universal-rules' . DIRECTORY_SEPARATOR . 'PLACEHOLDERS.md',
        $kitRoot . DIRECTORY_SEPARATOR . 'PLACEHOLDERS.md',
    ];

    $path = null;
    foreach ($paths as $candidate) {
        if (is_file($candidate)) {
            $path = $candidate;
            break;
        }
    }

    if ($path === null) {
        return null;
    }
    $content = (string) file_get_contents($path);
    if (!preg_match_all('/`(<[A-Z0-9_]+>)`/', $content, $matches)) {
        return [];
    }
    $tokens = array_values(array_unique($matches[1]));
    sort($tokens);
    return $tokens;
}

/**
 * Required token list mirrored from tools/ai/install/core.php::aiInstallerCollectPlaceholderStatus.
 * Keep this in sync with the installer's $required array.
 *
 * @return array<int, string>
 */
function requiredTokensList(): array
{
    return [
        '<PROJECT_NAME>', '<PROJECT_TYPE>', '<PRIMARY_LANGUAGE>', '<PRIMARY_RUNTIME>',
        '<SOURCE_DIRS>', '<TEST_DIRS>', '<TEST_COMMAND>', '<BUILD_COMMAND>',
        '<LINT_COMMAND>', '<PACKAGE_MANAGER>', '<CI_COMMANDS>', '<PROTECTED_PATHS>',
        '<PRIMARY_STACK>', '<FILE_PLACEMENT_RULES>', '<NAMING_RULES>',
        '<GOLDEN_EXAMPLES>', '<FORMATTER_CONFIG_FILES>', '<LINTER_CONFIG_FILES>',
        '<EDITORCONFIG_PATH>', '<IGNORE_FILES>', '<GENERATED_FILES>',
        '<PROTECTED_FILES>', '<INSTALL_COMMAND>', '<FORMAT_COMMAND>',
    ];
}

/**
 * Scan a single file and append any token occurrences to $findings.
 *
 * @param array<int, string> $requiredTokens
 * @param array<int, array<string, mixed>> $findings
 */
function scanFile(string $absolutePath, string $target, array $requiredTokens, array &$findings): void
{
    $relative = ltrim(str_replace('\\', '/', substr($absolutePath, strlen($target))), '/');
    if (placeholderVerifierShouldSkipPath($relative)) {
        return;
    }

    $content = @file_get_contents($absolutePath);
    if ($content === false) {
        return;
    }
    // Strip HTML comments so example/explanatory blocks do not trigger findings.
    $stripped = preg_replace('/<!--.*?-->/s', '', $content) ?: $content;
    if (!preg_match_all('/<[A-Z][A-Z0-9_]*>/', $stripped, $matches, PREG_OFFSET_CAPTURE)) {
        return;
    }
    foreach ($matches[0] as $hit) {
        $token = (string) ($hit[0] ?? '');
        $offset = (int) ($hit[1] ?? 0);
        $line = 1 + substr_count(substr($content, 0, $offset), "\n");
        $isRequired = in_array($token, $requiredTokens, true);
        $findings[] = [
            'token' => $token,
            'file' => $relative,
            'line' => $line,
            'required' => $isRequired,
        ];
    }
}

function placeholderVerifierShouldSkipPath(string $relativePath): bool
{
    if ($relativePath === 'docs/ai/catalog.md' || $relativePath === 'docs/ai/project-context-placeholders.md') {
        return true;
    }

    return str_starts_with($relativePath, 'docs/ai/generated/');
}

/**
 * @param array<int, array<string, mixed>> $list
 * @return array<int, array<string, mixed>>
 */
function firstFew(array $list, int $max): array
{
    return array_slice($list, 0, $max);
}

function emitError(bool $emitJson, string $code, string $message): void
{
    if ($emitJson) {
        fwrite(STDOUT, json_encode([
            'schema' => 'ai.verify-install-placeholders.v1',
            'status' => 'error',
            'tool' => 'verify-install-placeholders.php',
            'errors' => [['code' => $code, 'message' => $message]],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        return;
    }
    fwrite(STDERR, "ERROR: {$message}\n");
}
