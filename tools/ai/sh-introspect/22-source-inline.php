<?php

declare(strict_types=1);

/**
 * Source inlining for facade scripts.
 *
 * sh-introspect.php is a static single-file parser: it never executes the
 * target and cannot, on its own, see the contract of a thin entrypoint that
 * `source`s its real logic from sibling modules (e.g. scripts/ai/ai-search.sh
 * sourcing scripts/ai/internal/search/NN-*.sh).
 *
 * This module expands the raw script text BEFORE parsing by inlining the
 * contents of statically-resolvable, inside-repo `.sh` modules it sources, so
 * the downstream parser sees the aggregated contract (usage, modes, params,
 * functions, dependencies, commands) as if it were one file.
 *
 * Safety properties:
 *   - text-only: files are read, never executed (target_executed stays false);
 *   - bounded: recursion depth and total inlined files are capped;
 *   - cycle-safe: each absolute path is inlined at most once per expansion;
 *   - conservative: only resolves the self-relative `$(dirname ...)`/dir-var
 *     patterns this repo uses; anything dynamic is left as a normal `source`
 *     line (still reported by shIntrospectExtractSources, possibly unresolved).
 *
 * The inlined module bodies have their own shebang line dropped and are wrapped
 * with marker comments so line-based parsing stays well-formed.
 */

/** Maximum nesting depth when following sourced modules. */
const SH_INTROSPECT_INLINE_MAX_DEPTH = 4;

/** Maximum number of distinct files inlined in a single expansion. */
const SH_INTROSPECT_INLINE_MAX_FILES = 64;

/**
 * Expand $raw by inlining statically-resolvable sourced modules relative to
 * $file. Returns the (possibly unchanged) script text.
 *
 * @param array<string,bool> $seen absolute paths already inlined (cycle guard)
 */
function shIntrospectInlineSources(
    string $raw,
    string $file,
    int $depth = 0,
    array &$seen = []
): string {
    if ($file === '' || $depth >= SH_INTROSPECT_INLINE_MAX_DEPTH) {
        return $raw;
    }

    $absFile = realpath($file);
    if ($absFile !== false) {
        $seen[$absFile] = true;
    }

    $lines = preg_split('/\r\n|\n|\r/', $raw);
    if ($lines === false) {
        return $raw;
    }

    $dir = dirname($absFile !== false ? $absFile : $file);

    // First pass: collect directory-variable assignments of the self-relative
    // form  VAR="$( ... dirname ... ${BASH_SOURCE[0]} ... )/SUBPATH".
    $dirVars = shIntrospectCollectDirVars($lines, $dir);

    $out = [];
    foreach ($lines as $line) {
        $target = shIntrospectSourceLineTarget($line);
        if ($target === null) {
            $out[] = $line;
            continue;
        }

        $resolved = shIntrospectResolveInlineTarget($target, $dir, $dirVars);
        if ($resolved === null
            || !is_file($resolved)
            || !shIntrospectPathInsideRepo($resolved)
            || shIntrospectIsSharedLibrarySource($resolved)
            || count($seen) >= SH_INTROSPECT_INLINE_MAX_FILES
        ) {
            // Leave the original source line in place for normal reporting.
            // Shared libraries (common.sh, */lib/*) keep their own introspection
            // identity and are reported as resolved sources, not inlined — so a
            // facade's risk surface stays its own, not the library's.
            $out[] = $line;
            continue;
        }

        $absChild = realpath($resolved);
        if ($absChild === false || isset($seen[$absChild])) {
            $out[] = $line;
            continue;
        }
        $seen[$absChild] = true;

        $childRaw = @file_get_contents($absChild);
        if ($childRaw === false || strpos($childRaw, "\0") !== false) {
            $out[] = $line;
            continue;
        }

        // Recurse so a sourced module that itself sources further modules is
        // also expanded.
        $childRaw = shIntrospectInlineSources($childRaw, $absChild, $depth + 1, $seen);

        $out[] = '# >>> sh-introspect inlined: ' . $resolved;
        foreach (shIntrospectStripModuleShebang($childRaw) as $cl) {
            $out[] = $cl;
        }
        $out[] = '# <<< sh-introspect inlined: ' . $resolved;
    }

    return implode("\n", $out);
}

/**
 * Collect self-relative directory variables, e.g.
 *   _search_dir="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)/ai-search"
 *   libdir="$(dirname "${BASH_SOURCE[0]}")/lib"
 * Maps VAR => absolute directory (relative to $dir). Only the self-relative
 * dirname/BASH_SOURCE[0] (or $0) form is recognised.
 *
 * @param array<int,string> $lines
 * @return array<string,string> VAR (without `$`) => absolute dir
 */
function shIntrospectCollectDirVars(array $lines, string $dir): array
{
    $vars = [];
    foreach ($lines as $line) {
        if (!preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)=(.+?)\s*$/', $line, $m)) {
            continue;
        }
        $name = $m[1];
        $value = trim($m[2]);
        // Must reference a self-relative dirname of BASH_SOURCE[0] or $0.
        if (!preg_match('/dirname\b.*(?:BASH_SOURCE\[0\]|\$0)/', $value)) {
            continue;
        }
        // Capture an optional trailing `/SUBPATH` after the command substitution
        // (the closing `)` of $(...) followed by /sub/dir). Tolerate a quote.
        $sub = '';
        if (preg_match('#\)\s*"?/([A-Za-z0-9._/-]+)"?\s*$#', $value, $mm)) {
            $sub = $mm[1];
        }
        $abs = $sub === '' ? $dir : shIntrospectNormalizePath($dir . '/' . $sub);
        $vars[$name] = $abs;
    }
    return $vars;
}

/**
 * If $line is a `source X` / `. X` statement, return the unquoted target
 * expression; otherwise null. Dot-source fragments that are jq/awk code are
 * rejected via the shared heuristic.
 */
function shIntrospectSourceLineTarget(string $line): ?string
{
    if (preg_match('/^\s*source\s+(.+?)\s*$/', $line, $m)) {
        $rest = trim($m[1]);
        if ($rest === '' || str_starts_with($rest, '#')) {
            return null;
        }
        return shIntrospectSourceTarget($rest);
    }
    if (preg_match('/^\s*\.\s+(.+?)\s*$/', $line, $m)) {
        $rest = trim($m[1]);
        if ($rest === '' || str_starts_with($rest, '#')) {
            return null;
        }
        $target = shIntrospectSourceTarget($rest);
        if (!shIntrospectLooksLikeSourcePath($target)) {
            return null;
        }
        return $target;
    }
    return null;
}

/**
 * Resolve a source target to an absolute path using either a collected
 * directory variable (`$VAR/x.sh` / `${VAR}/x.sh`) or the inline self-relative
 * `$(dirname "${BASH_SOURCE[0]}")/x.sh` form. Returns null when not statically
 * resolvable.
 *
 * @param array<string,string> $dirVars
 */
function shIntrospectResolveInlineTarget(string $target, string $dir, array $dirVars): ?string
{
    // $VAR/rest or ${VAR}/rest
    if (preg_match('/^\$\{?([A-Za-z_][A-Za-z0-9_]*)\}?\/(.+)$/', $target, $m)) {
        $var = $m[1];
        $rest = $m[2];
        if (!isset($dirVars[$var]) || preg_match('/[$`]/', $rest)) {
            return null;
        }
        return shIntrospectNormalizePath($dirVars[$var] . '/' . $rest);
    }

    // $(dirname "${BASH_SOURCE[0]}")/rest and its variants (incl. a
    // `${VAR:-${BASH_SOURCE[0]}}` entrypoint-path default and bash `--`).
    if (preg_match(
        '#^\$\(\s*dirname\s+(?:--\s+)?"?'
        . '(?:'
        . '\$\{?(?:BASH_SOURCE\[0\]|0)\}?'
        . '|\$\{[A-Za-z_][A-Za-z0-9_]*:-\$\{?BASH_SOURCE\[0\]\}?\}'
        . ')'
        . '"?\s*\)/(.+)$#',
        $target,
        $m
    )) {
        $rest = $m[1];
        if (preg_match('/[$`]/', $rest)) {
            return null;
        }
        return shIntrospectNormalizePath($dir . '/' . $rest);
    }

    // Plain relative path (no expansion) — resolve against $dir.
    if (!preg_match('/[$`]/', $target)
        && (str_starts_with($target, './') || str_contains($target, '/'))
        && !str_starts_with($target, '/')
    ) {
        return shIntrospectNormalizePath($dir . '/' . $target);
    }

    return null;
}

/**
 * True when $resolved is a shared library facade that must NOT be inlined: the
 * universal common.sh, or any module under a `lib/` directory. These are
 * reusable libraries with their own introspection identity and risk surface;
 * inlining them would wrongly fold their (mutating) surface into every facade
 * that sources them. They remain reported as resolved `sources[]`.
 */
function shIntrospectIsSharedLibrarySource(string $resolved): bool
{
    $base = basename($resolved);
    if ($base === 'common.sh') {
        return true;
    }
    $norm = str_replace('\\', '/', $resolved);
    return str_contains($norm, '/lib/');
}

/**
 * True when $absPath is within the current working directory tree (the repo
 * checkout). Mirrors the inside-repo notion used by shIntrospectResolveSiblingSource.
 */
function shIntrospectPathInsideRepo(string $absPath): bool
{
    $cwd = getcwd();
    if ($cwd === false) {
        return false;
    }
    $prefix = rtrim($cwd, '/\\') . DIRECTORY_SEPARATOR;
    return str_starts_with($absPath, $prefix);
}

/**
 * Drop a leading `#!` shebang line from an inlined module body so it is not
 * mistaken for script content. Returns the remaining lines.
 *
 * @return array<int,string>
 */
function shIntrospectStripModuleShebang(string $raw): array
{
    $lines = preg_split('/\r\n|\n|\r/', $raw);
    if ($lines === false) {
        return [$raw];
    }
    if (isset($lines[0]) && str_starts_with($lines[0], '#!')) {
        array_shift($lines);
    }
    return $lines;
}
