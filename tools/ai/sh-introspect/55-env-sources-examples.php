<?php

declare(strict_types=1);

/**
 * Extract environment inputs (strict ${NAME...} interpolation forms only).
 * NAME must be [A-Z][A-Z0-9_]+. Plain assignments are NOT env_inputs.
 *
 * @param array<int,string> $codeLines
 * @return array<int,array<string,mixed>>
 */
function shIntrospectExtractEnvInputs(array $codeLines): array
{
    $seen = [];
    $out = [];

    foreach ($codeLines as $idx => $line) {
        // ${NAME:-default} ${NAME:=default} ${NAME-default} ${NAME}
        if (!preg_match_all('/\$\{([A-Z][A-Z0-9_]+)(:?[-=][^}]*)?\}/', $line, $matches, PREG_SET_ORDER)) {
            continue;
        }
        foreach ($matches as $m) {
            $name = $m[1];
            if (isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;

            $entry = [
                'name' => $name,
                'line' => $idx + 1,
                'source' => 'param-expansion',
                'confidence' => 90,
            ];

            if (isset($m[2]) && $m[2] !== '') {
                // Strip the leading :- / := / - operator to get the default.
                $default = preg_replace('/^:?[-=]/', '', $m[2]);
                $entry['default'] = $default;
            }

            $out[] = $entry;
        }
    }

    return $out;
}

/**
 * Extract `source X` and `. X` statements.
 *
 * When $file is supplied, the common
 * `$(dirname "${BASH_SOURCE[0]}")/sibling.sh` pattern is resolved relative to
 * the target script's directory, adding `resolved`, `absolute`, `exists`, and
 * `inside_repo` to that source record.
 *
 * @param array<int,string> $codeLines
 * @return array<int,array<string,mixed>>
 */
function shIntrospectExtractSources(array $codeLines, string $file = ''): array
{
    $out = [];

    foreach ($codeLines as $idx => $line) {
        // `source X` is unambiguous.
        if (preg_match('/^\s*source\s+(.+?)\s*$/', $line, $m)) {
            $rest = trim($m[1]);
            if ($rest === '' || str_starts_with($rest, '#')) {
                continue;
            }
            $target = shIntrospectSourceTarget($rest);
            $out[] = shIntrospectSourceRecord($target, $line, $idx + 1, $file);
            continue;
        }

        // `. X` dot-sourcing: only when the target looks like a path or a
        // variable/command expansion, NOT jq/awk fragments like `. as $c`.
        if (preg_match('/^\s*\.\s+(.+?)\s*$/', $line, $m)) {
            $rest = trim($m[1]);
            if ($rest === '' || str_starts_with($rest, '#')) {
                continue;
            }
            $target = shIntrospectSourceTarget($rest);
            if (!shIntrospectLooksLikeSourcePath($target)) {
                continue;
            }
            $out[] = shIntrospectSourceRecord($target, $line, $idx + 1, $file);
        }
    }

    return $out;
}

/**
 * Build a source record and, when possible, statically resolve the
 * `$(dirname "${BASH_SOURCE[0]}")/sibling` pattern relative to the target
 * script directory.
 *
 * @return array<string,mixed>
 */
function shIntrospectSourceRecord(string $target, string $line, int $lineNo, string $file): array
{
    $record = [
        'target' => $target,
        'raw' => trim($line),
        'line' => $lineNo,
        'confidence' => 80,
    ];

    $resolved = shIntrospectResolveSiblingSource($target, $file);
    if ($resolved !== null) {
        $record['resolved'] = $resolved['resolved'];
        $record['absolute'] = $resolved['absolute'];
        $record['exists'] = $resolved['exists'];
        $record['inside_repo'] = $resolved['inside_repo'];
    }

    return $record;
}

/**
 * Resolve the common self-relative source pattern:
 *   $(dirname "${BASH_SOURCE[0]}")/sibling.sh
 *   $(dirname "$0")/sibling.sh
 * Returns the resolved sibling path data, or null when the target is not this
 * exact, statically-resolvable shape.
 *
 * @return array{resolved:string,absolute:string,exists:bool,inside_repo:bool}|null
 */
function shIntrospectResolveSiblingSource(string $target, string $file): ?array
{
    if ($file === '') {
        return null;
    }
    // Match the self-relative dirname forms, all resolved against $file's dir:
    //   $(dirname "${BASH_SOURCE[0]}")/REST
    //   $(dirname "$0")/REST
    //   $(dirname -- "${BASH_SOURCE[0]}")/REST          (bash `--` end-of-opts)
    //   $(dirname "${VAR:-${BASH_SOURCE[0]}}")/REST      (entrypoint-path var
    //       with a BASH_SOURCE[0] default, e.g. AI_SEARCH_ENTRYPOINT)
    if (!preg_match(
        '#^\$\(\s*dirname\s+(?:--\s+)?"?'
        . '(?:'
        . '\$\{?(?:BASH_SOURCE\[0\]|0)\}?'
        . '|\$\{[A-Za-z_][A-Za-z0-9_]*:-\$\{?BASH_SOURCE\[0\]\}?\}'
        . ')'
        . '"?\s*\)/(.+)$#',
        $target,
        $m
    )) {
        return null;
    }
    $rest = $m[1];
    // The sibling must be a static relative path (no further expansion).
    if (preg_match('/[$`]/', $rest)) {
        return null;
    }

    $dir = dirname($file);
    $joined = $dir . '/' . $rest;

    // Normalise `.`/`..` segments without requiring the file to exist.
    $normalized = shIntrospectNormalizePath($joined);
    $exists = is_file($normalized);

    $cwd = getcwd();
    $insideRepo = false;
    // `resolved` is the repo-relative path when inside the repo (more useful
    // than a bare basename, which loses directory context); otherwise the
    // normalised absolute path.
    $resolved = $normalized;
    if ($cwd !== false) {
        $prefix = rtrim($cwd, '/\\') . DIRECTORY_SEPARATOR;
        if (str_starts_with($normalized, $prefix)) {
            $insideRepo = true;
            $resolved = substr($normalized, strlen($prefix));
        }
    }

    return [
        'resolved' => $resolved,
        'absolute' => $normalized,
        'exists' => $exists,
        'inside_repo' => $insideRepo,
    ];
}

/**
 * Normalise a path by collapsing `.` and `..` segments lexically (no fs access
 * required so non-existent paths still normalise).
 */
function shIntrospectNormalizePath(string $path): string
{
    $isAbsolute = str_starts_with($path, '/');
    $parts = explode('/', $path);
    $stack = [];
    foreach ($parts as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            if ($stack !== [] && end($stack) !== '..') {
                array_pop($stack);
            } elseif (!$isAbsolute) {
                $stack[] = '..';
            }
            continue;
        }
        $stack[] = $part;
    }
    return ($isAbsolute ? '/' : '') . implode('/', $stack);
}

/**
 * Heuristic: does the dot-source target look like an actual sourced file
 * (path, quoted path, or command/variable expansion) rather than a jq/awk
 * code fragment such as `. as $c` or `. | map(...)`?
 */
function shIntrospectLooksLikeSourcePath(string $target): bool
{
    $t = trim($target);
    if ($t === '') {
        return false;
    }
    // Reject obvious non-shell-source fragments.
    if (preg_match('/^(as|map|select|if|then|else|end|\||\[|\{|\.)\b/', $t)) {
        return false;
    }
    // Accept command/variable expansion or path-like tokens.
    if (str_starts_with($t, '$') || str_starts_with($t, '/')
        || str_starts_with($t, './') || str_starts_with($t, '~')) {
        return true;
    }
    // Accept a bare path that contains a slash or ends in a shell extension.
    return str_contains($t, '/') || (bool) preg_match('/\.(sh|bash|env)$/', $t);
}

/**
 * Best-effort target expression for a source statement: drop trailing
 * redirections / comments and surrounding quotes, keep the path expression.
 */
function shIntrospectSourceTarget(string $rest): string
{
    // Strip a trailing inline comment.
    $rest = preg_replace('/\s+#.*$/', '', $rest) ?? $rest;
    $rest = trim($rest);
    // Strip matching surrounding quotes if the whole token is quoted.
    if (preg_match('/^"(.*)"$/', $rest, $m) || preg_match("/^'(.*)'$/", $rest, $m)) {
        return $m[1];
    }
    return $rest;
}

/**
 * Extract examples from the usage block's `Examples:` section. Each non-empty
 * line after the heading (until a blank line or a new heading) is one example.
 *
 * @return array<int,array<string,string>>
 */
function shIntrospectExtractExamples(string $usageBlock): array
{
    if ($usageBlock === '') {
        return [];
    }

    $lines = preg_split('/\n/', $usageBlock) ?: [];
    $out = [];
    $inExamples = false;

    foreach ($lines as $line) {
        if (!$inExamples) {
            if (preg_match('/^\s*Examples?\s*:\s*$/i', $line)) {
                $inExamples = true;
            }
            continue;
        }

        $trimmed = trim($line);
        if ($trimmed === '') {
            // Blank line ends the examples block.
            break;
        }
        // A new heading (Word:) ends the block.
        if (preg_match('/^\s*[A-Z][A-Za-z ]*:\s*$/', $line)) {
            break;
        }
        $out[] = ['text' => $trimmed];
    }

    return $out;
}

/**
 * Best-effort positionals from the usage synopsis line, e.g.
 *   `ai-search.sh MODE [QUERY] [root] [flags]`
 * Bracketed tokens are optional; bare tokens are required. The literal `flags`
 * placeholder is ignored (it denotes the flag stream, not a positional).
 *
 * @return array<int,array<string,mixed>>
 */
function shIntrospectExtractPositionals(string $usageBlock): array
{
    if ($usageBlock === '') {
        return [];
    }

    $lines = preg_split('/\n/', $usageBlock) ?: [];
    foreach ($lines as $line) {
        // A synopsis line invokes the script with positional placeholders.
        if (!preg_match('/\.sh\s+(.+)$/', $line, $m)) {
            continue;
        }
        $rest = trim($m[1]);
        // Require at least one uppercase placeholder or a bracketed token so we
        // do not pick up prose lines that merely mention the script name.
        if (!preg_match('/[A-Z]{2,}|\[/', $rest)) {
            continue;
        }

        $out = [];
        $seen = [];
        // Tokenise into bracketed [TOKEN] or bare TOKEN groups.
        if (preg_match_all('/\[([^\]]+)\]|(\S+)/', $rest, $tm, PREG_SET_ORDER)) {
            foreach ($tm as $t) {
                $optional = $t[1] !== '';
                $raw = $optional ? $t[1] : ($t[2] ?? '');
                $name = trim($raw);
                // Skip the flags placeholder and anything flag-like.
                if ($name === '' || $name === 'flags' || str_starts_with($name, '-')) {
                    continue;
                }
                // Only accept identifier-ish placeholder names.
                if (!preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/', $name)) {
                    continue;
                }
                if (isset($seen[$name])) {
                    continue;
                }
                $seen[$name] = true;
                $out[] = [
                    'name' => $name,
                    'required' => !$optional,
                    'source' => 'usage',
                ];
            }
        }

        if ($out !== []) {
            return $out;
        }
    }

    return [];
}
