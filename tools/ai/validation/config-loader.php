<?php

declare(strict_types=1);

// config-loader.php: pure load/parse/extract functions used by tools/ai/validate-ai-config.php.
// No validation decisions live here — these functions only read, decode, normalize, and
// return structured data (or null/defaults on absence). Extracted verbatim (behavior-preserving
// move; see docs/tickets/arch-todo-validate-ai-config-extraction-20260706-223128/plan.md, Phase 1).
// `loadJsonFile` calls `safeRead`, so both live in this one file (no cross-file split needed).

function loadJsonFile(string $root, string $relativePath, array &$errors): ?array
{
    $content = safeRead($root, $relativePath);

    if ($content === null) {
        $errors[] = "missing JSON config file: {$relativePath}";
        return null;
    }

    // opencode.jsonc (and any .jsonc) is JSON-with-comments. The kit ships it with a
    // managed soft-notice comment header, so strip comments/trailing commas before decoding.
    if (str_ends_with(strtolower($relativePath), '.jsonc')) {
        $content = stripJsonCommentsAndTrailingCommas($content);
    }

    $decoded = json_decode($content, true);

    if (!is_array($decoded)) {
        $errors[] = "invalid JSON config file: {$relativePath}";
        return null;
    }

    return $decoded;
}

/**
 * Strip // line comments, block comments, and trailing commas from a JSONC string.
 * String-literal aware so comment markers inside JSON strings are preserved.
 */
function stripJsonCommentsAndTrailingCommas(string $input): string
{
    $out = '';
    $inString = false;
    $escaped = false;
    $inLineComment = false;
    $inBlockComment = false;
    $length = strlen($input);

    // Index of the last comma emitted outside a string, plus the whitespace
    // emitted after it, so a trailing comma can be dropped when the next
    // structural char turns out to be `}` or `]`. Commas inside string literals
    // are never tracked, so string contents like "a,}" survive untouched.
    $pendingCommaPos = -1;

    for ($i = 0; $i < $length; $i++) {
        $char = $input[$i];
        $next = $i + 1 < $length ? $input[$i + 1] : '';

        if ($inLineComment) {
            if ($char === "\n") {
                $inLineComment = false;
                $out .= $char;
            }
            continue;
        }

        if ($inBlockComment) {
            if ($char === '*' && $next === '/') {
                $inBlockComment = false;
                $i++;
            }
            continue;
        }

        if ($inString) {
            $out .= $char;
            if ($escaped) {
                $escaped = false;
            } elseif ($char === '\\') {
                $escaped = true;
            } elseif ($char === '"') {
                $inString = false;
            }
            continue;
        }

        if ($char === '"') {
            $pendingCommaPos = -1;
            $inString = true;
            $out .= $char;
            continue;
        }

        if ($char === '/' && $next === '/') {
            $inLineComment = true;
            $i++;
            continue;
        }

        if ($char === '/' && $next === '*') {
            $inBlockComment = true;
            $i++;
            continue;
        }

        // Outside strings/comments: track a candidate trailing comma and drop it
        // when the next non-whitespace structural char is `}` or `]`.
        if ($char === ',') {
            $pendingCommaPos = strlen($out);
            $out .= $char;
            continue;
        }

        if ($pendingCommaPos >= 0) {
            if ($char === ' ' || $char === "\t" || $char === "\r" || $char === "\n") {
                // Whitespace between the comma and a potential closer; keep it
                // and keep the pending comma candidate alive.
                $out .= $char;
                continue;
            }
            if ($char === '}' || $char === ']') {
                // Trailing comma: remove the previously emitted comma.
                $out = substr($out, 0, $pendingCommaPos) . substr($out, $pendingCommaPos + 1);
            }
            $pendingCommaPos = -1;
        }

        $out .= $char;
    }

    return $out;
}

function safeRead(string $root, string $relativePath): ?string
{
    $absolutePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (!is_file($absolutePath)) {
        return null;
    }

    $content = file_get_contents($absolutePath);

    return $content === false ? null : $content;
}

/**
 * Load the canonical placeholder dictionary so we can distinguish intentional
 * template tokens from typo-style leaks. The dictionary is the union of every
 * `<UPPERCASE_TOKEN>` referenced in PLACEHOLDERS.md.
 *
 * @return array<int, string> Sorted list of documented placeholder tokens.
 */
function loadDocumentedPlaceholders(string $root): array
{
    $path = $root . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'ai-universal-rules' . DIRECTORY_SEPARATOR . 'PLACEHOLDERS.md';
    if (!is_file($path)) {
        return [];
    }
    $content = (string) file_get_contents($path);
    if (!preg_match_all('/`(<[A-Z0-9_]+>)`/', $content, $matches)) {
        return [];
    }
    $tokens = array_values(array_unique($matches[1]));
    sort($tokens);
    return $tokens;
}

function extractBacktickPaths(string $content): array
{
    preg_match_all('/`([^`]+)`/', $content, $matches);

    $paths = [];

    foreach ($matches[1] as $candidate) {
        $trimmed = trim($candidate);

        if (
            str_contains($trimmed, '/') ||
            str_ends_with($trimmed, '.md') ||
            str_ends_with($trimmed, '.json') ||
            str_ends_with($trimmed, '.php') ||
            str_ends_with($trimmed, '.ps1')
        ) {
            $paths[] = $trimmed;
        }
    }

    return array_values(array_unique($paths));
}

function shouldSkipPathCheck(string $path): bool
{
    if ($path === '' || preg_match('/\s/', $path) === 1) {
        return true;
    }

    if (preg_match('#^(search|read|edit|execute|vscode|agent|web|todo)/#', $path) === 1) {
        return true;
    }

    if (str_starts_with($path, 'docs/ai/generated/task-context/')) {
        return true;
    }

    if (in_array($path, ['.agent.md', '.prompt.md', 'tools:'], true)) {
        return true;
    }

    // Stack-conditional config-tool dotfiles/lockfiles named in the multi-stack-agnostic
    // config-maintainer agent template (packages/ai-universal-rules/templates/core/agents/
    // config-maintainer.md): these are permission.edit allow-list entries for tooling that
    // exists only in SOME installed project stacks (JS/CSS lint configs, npm lockfile, an
    // auth credential filename used only as a deny-list example). They are legitimately
    // absent in a PHP-only host repo like this one; presence is validated per-project by
    // the installer/stack scanner, not by this cross-repo existence check.
    if (in_array($path, ['.eslintrc.json', '.prettierrc.json', '.stylelintrc.json', 'package-lock.json', 'auth.json'], true)) {
        return true;
    }

    foreach (['*', '{', '}', '<', '>', 'http://', 'https://', ',', '->'] as $fragment) {
        if (str_contains($path, $fragment)) {
            return true;
        }
    }

    return false;
}

function loadAgnosticLeakRules(string $root): array
{
    $defaults = [
        'banned_terms' => ['project-name-3', 'Nuxt', 'Vue 3', 'PHPUnit 11'],
        'allowed_paths' => [],
    ];

    $rulesPath = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'rules' . DIRECTORY_SEPARATOR . 'agnostic-leak-rules.json';

    if (!is_file($rulesPath)) {
        return $defaults;
    }

    $raw = file_get_contents($rulesPath);
    if ($raw === false) {
        return $defaults;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $defaults;
    }

    $bannedTerms = $decoded['banned_terms'] ?? $defaults['banned_terms'];
    $allowedPaths = $decoded['allowed_paths'] ?? [];

    return [
        'banned_terms' => is_array($bannedTerms) ? array_values(array_filter($bannedTerms, 'is_string')) : $defaults['banned_terms'],
        'allowed_paths' => is_array($allowedPaths) ? array_values(array_filter($allowedPaths, 'is_string')) : [],
    ];
}
