<?php

declare(strict_types=1);

/**
 * Build per-mode call contracts (P1 AI-value field).
 *
 * Each entry is derived deterministically from already-parsed data; nothing is
 * guessed beyond what the modes/positionals/examples already prove:
 *   - name, family, query_required: copied from the classified mode.
 *   - positionals: the global positionals, but the QUERY positional is dropped
 *     for modes that do not require a query (e.g. file-list / curated modes).
 *   - dependencies: the script-level dependency names (baseline; per-mode
 *     refinement is a later TODO).
 *   - examples: usage examples whose command invokes this exact mode token.
 *   - deprecated + replacements: for deprecated modes, the replacement mode
 *     names parsed from the deprecation message in the code.
 *
 * Output is sorted by mode name for stable, test-friendly ordering.
 *
 * @param array<int,array<string,mixed>> $modes
 * @param array<int,array<string,mixed>> $positionals
 * @param array<int,array<string,mixed>> $dependencies
 * @param array<int,array<string,mixed>> $examples
 * @param array<int,string> $codeLines
 * @return array<int,array<string,mixed>>
 */
function shIntrospectBuildModeContracts(
    array $modes,
    array $positionals,
    array $dependencies,
    array $examples,
    array $codeLines,
    array $usageModeDocs = []
): array {
    if ($modes === []) {
        return [];
    }

    // All detected dependency names (the conservative superset fallback).
    $depNames = [];
    foreach ($dependencies as $dep) {
        $name = (string) ($dep['name'] ?? '');
        if ($name !== '') {
            $depNames[$name] = true;
        }
    }
    $depNames = array_keys($depNames);
    sort($depNames, SORT_STRING);

    // Per-mode dependency map derived from the script's own routing guards
    // (`mode_needs_<tool>()` functions and the structural/ast family). When a
    // mode has a derived set we use it; otherwise we fall back to [] (unknown),
    // never the misleading full superset.
    $perModeDeps = shIntrospectResolveModeDependencies($modes, $codeLines, $depNames);

    $contracts = [];
    foreach ($modes as $mode) {
        $name = (string) ($mode['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $queryRequired = !empty($mode['query_required']);

        $contract = [
            'name' => $name,
            'family' => (string) ($mode['family'] ?? 'unknown'),
            'query_required' => $queryRequired,
            'positionals' => shIntrospectModePositionals($positionals, $queryRequired),
            'dependencies' => $perModeDeps[$name] ?? [],
            'examples' => shIntrospectModeExamples($examples, $name),
        ];

        // description / output_notes are only present when the usage block
        // documents them for this mode (never fabricated).
        $doc = $usageModeDocs[$name] ?? null;
        if (is_array($doc)) {
            if (($doc['description'] ?? '') !== '') {
                $contract['description'] = (string) $doc['description'];
            }
            if (($doc['output_notes'] ?? '') !== '') {
                $contract['output_notes'] = (string) $doc['output_notes'];
            }
        }

        if (!empty($mode['deprecated'])) {
            $contract['deprecated'] = true;
            $replacements = shIntrospectModeReplacements($name, $codeLines);
            if ($replacements !== []) {
                $contract['replacements'] = $replacements;
            }
        }

        $contracts[$name] = $contract;
    }

    ksort($contracts, SORT_STRING);
    return array_values($contracts);
}

/**
 * Resolve per-mode dependencies from the script's own routing guards.
 *
 * Generic strategy (no per-script hardcoding):
 *  - Parse every `mode_needs_<tool>()` function: each bare case label inside it
 *    maps that mode to tool `<tool>` (e.g. `mode_needs_rg` -> rg).
 *  - Map modes in the structural/ast family (family === 'structural' or an
 *    `is_ast_mode` guard) to `ast-grep`.
 *  - Detect a per-mode dispatch backend `MODE) ... <tool> ...` in the final
 *    dispatch case for any leftover modes (covers e.g. `files` -> fd).
 *
 * Only tools that were actually detected as dependencies are kept. A mode with
 * no derivable tool gets an empty list (honest "unknown"), never the full
 * superset.
 *
 * @param array<int,array<string,mixed>> $modes
 * @param array<int,string> $codeLines
 * @param array<int,string> $depNames detected dependency names (allowlist)
 * @return array<string,array<int,string>> mode name => sorted tool list
 */
function shIntrospectResolveModeDependencies(array $modes, array $codeLines, array $depNames): array
{
    $allow = array_fill_keys($depNames, true);
    $modeSet = [];
    foreach ($modes as $mode) {
        $n = (string) ($mode['name'] ?? '');
        if ($n !== '') {
            $modeSet[$n] = true;
        }
    }

    // mode => set of tools.
    $map = [];
    $addTool = static function (string $mode, string $tool) use (&$map, $allow, $modeSet): void {
        if ($tool === '' || !isset($modeSet[$mode]) || !isset($allow[$tool])) {
            return;
        }
        $map[$mode][$tool] = true;
    };

    // 1) Parse `mode_needs_<tool>() { case ... in <labels>) return 0 ;; }`.
    $total = count($codeLines);
    for ($i = 0; $i < $total; $i++) {
        if (!preg_match('/^\s*(?:function\s+)?mode_needs_([A-Za-z][A-Za-z0-9_]*)\s*\(\s*\)\s*\{/', $codeLines[$i], $fm)) {
            continue;
        }
        $tool = shIntrospectNormalizeToolName($fm[1]);
        // Collect bare labels that `return 0` until the function closes.
        $depth = 0;
        $started = false;
        for ($j = $i; $j < $total; $j++) {
            $depth += substr_count($codeLines[$j], '{') - substr_count($codeLines[$j], '}');
            if (!$started && substr_count($codeLines[$j], '{') > 0) {
                $started = true;
            }
            // A `LABELS) return 0 ;;` branch (labels may continue with trailing \).
            if (preg_match('/^\s*([A-Za-z0-9 |*\\\\-]+?)\)\s*return\s+0\b/', $codeLines[$j], $lm)) {
                foreach (shIntrospectSplitCaseLabels($lm[1]) as $label) {
                    $addTool($label, $tool);
                }
            } else {
                // Multi-line label list ending in `\` then a `) return 0` line.
                if (preg_match('/^\s*([A-Za-z0-9 |*\\\\-]+)\\\\\s*$/', $codeLines[$j], $cont)
                    && isset($codeLines[$j + 1])
                    && preg_match('/^\s*([A-Za-z0-9 |*-]+?)\)\s*return\s+0\b/', $codeLines[$j + 1], $lm2)) {
                    $labels = shIntrospectSplitCaseLabels($cont[1] . ' ' . $lm2[1]);
                    foreach ($labels as $label) {
                        $addTool($label, $tool);
                    }
                }
            }
            if ($started && $depth <= 0) {
                break;
            }
        }
    }

    // 2) Structural/ast modes need ast-grep. Detected from the `is_ast_mode()`
    //    guard (parsed like mode_needs_*) and/or the structural family.
    for ($i = 0; $i < $total; $i++) {
        if (!preg_match('/^\s*(?:function\s+)?is_ast_mode\s*\(\s*\)\s*\{/', $codeLines[$i])) {
            continue;
        }
        $depth = 0;
        $started = false;
        for ($j = $i; $j < $total; $j++) {
            $depth += substr_count($codeLines[$j], '{') - substr_count($codeLines[$j], '}');
            if (!$started && substr_count($codeLines[$j], '{') > 0) {
                $started = true;
            }
            if (preg_match('/^\s*([A-Za-z0-9 |*-]+?)\)\s*return\s+0\b/', $codeLines[$j], $lm)) {
                foreach (shIntrospectSplitCaseLabels($lm[1]) as $label) {
                    $addTool($label, 'ast-grep');
                }
            }
            if ($started && $depth <= 0) {
                break;
            }
        }
    }
    foreach ($modes as $mode) {
        if ((string) ($mode['family'] ?? '') === 'structural') {
            $addTool((string) $mode['name'], 'ast-grep');
        }
    }

    // 3) Final dispatch backend per mode: `MODE) ... <tool> ...` lines.
    $dispatchTools = shIntrospectDispatchBackends($codeLines, $modeSet, $allow);
    foreach ($dispatchTools as $mode => $tools) {
        foreach ($tools as $tool => $_) {
            $addTool($mode, $tool);
        }
    }

    // Flatten + sort.
    $out = [];
    foreach ($map as $mode => $tools) {
        $names = array_keys($tools);
        sort($names, SORT_STRING);
        $out[$mode] = $names;
    }
    return $out;
}

/**
 * Normalise a guard-derived tool token to its dependency name. `ast` -> the
 * `ast-grep` dependency; everything else stays as-is.
 */
function shIntrospectNormalizeToolName(string $tool): string
{
    $t = str_replace('_', '-', $tool);
    if ($t === 'ast') {
        return 'ast-grep';
    }
    return $t;
}

/**
 * Split a case-label fragment on `|`, trimming whitespace and line
 * continuations, dropping the `*` fallback.
 *
 * @return array<int,string>
 */
function shIntrospectSplitCaseLabels(string $raw): array
{
    $raw = str_replace('\\', ' ', $raw);
    $out = [];
    foreach (explode('|', $raw) as $token) {
        $token = trim($token);
        if ($token === '' || $token === '*') {
            continue;
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $token)) {
            $out[] = $token;
        }
    }
    return $out;
}

/**
 * Detect the per-mode dispatch backend from the final dispatch `case` of the
 * script: branches of the form `MODE) ... <known tool> ...`. This catches modes
 * whose tool is not gated by a `mode_needs_*` guard (e.g. a `files) ... fd ...`
 * branch). Conservative: only the allowlisted tools count.
 *
 * @param array<string,bool> $modeSet
 * @param array<string,bool> $allow
 * @return array<string,array<string,bool>>
 */
function shIntrospectDispatchBackends(array $codeLines, array $modeSet, array $allow): array
{
    $out = [];
    $total = count($codeLines);
    $allowTools = array_keys($allow);

    for ($i = 0; $i < $total; $i++) {
        // A dispatch branch: `mode | mode2) run_x_mode ;;` or `mode) ... tool`.
        if (!preg_match('/^\s*([A-Za-z][A-Za-z0-9_| -]*?)\)\s*(.*)$/', $codeLines[$i], $m)) {
            continue;
        }
        $labels = shIntrospectSplitCaseLabels($m[1]);
        // Only consider branches whose labels are all known modes (a real mode
        // dispatch), to avoid matching arbitrary inner case statements.
        if ($labels === []) {
            continue;
        }
        $allModes = true;
        foreach ($labels as $label) {
            if (!isset($modeSet[$label])) {
                $allModes = false;
                break;
            }
        }
        if (!$allModes) {
            continue;
        }
        // Scan this branch body (this line + following until `;;`) for tools.
        $body = $m[2];
        for ($j = $i + 1; $j < $total && !preg_match('/;;\s*$/', $codeLines[$j - 1]); $j++) {
            if (preg_match('/;;/', $body)) {
                break;
            }
            $body .= "\n" . $codeLines[$j];
            if (preg_match('/;;/', $codeLines[$j])) {
                break;
            }
        }

        // A dispatch branch often delegates to a backend FUNCTION rather than
        // inlining the tool call (e.g. `files) backend_files ;;`). Follow each
        // called function defined in this script and append its body, so the
        // tool it actually runs (e.g. fd) is still attributed to the mode. This
        // keeps facade splits (logic moved into modules) equivalent to the
        // pre-split inline form.
        foreach (shIntrospectCalledFunctionBodies($body, $codeLines) as $fnBody) {
            $body .= "\n" . $fnBody;
        }

        foreach ($allowTools as $tool) {
            $q = preg_quote($tool, '/');
            if (!preg_match('/(^|[\s|(){};&`$])' . $q . '(\s|$)/', $body)) {
                continue;
            }
            // `grep` as part of `git grep` is git, not standalone grep.
            if ($tool === 'grep' && preg_match('/\bgit\s+grep\b/', $body) && !preg_match('/(^|[\s|(){};&`$])grep(\s|$)/', preg_replace('/\bgit\s+grep\b/', '', $body) ?? $body)) {
                continue;
            }
            foreach ($labels as $label) {
                $out[$label][$tool] = true;
            }
        }
    }

    return $out;
}

/**
 * Given a dispatch-branch body, find calls to functions DEFINED in this script
 * and return their bodies (transitively, up to a small depth). Used so a mode
 * that delegates to a backend function still attributes that function's tool
 * usage to the mode. Conservative: only resolves `name() { ... }` definitions
 * found in $codeLines; depth- and visit-bounded.
 *
 * @param array<int,string> $codeLines
 * @return array<int,string> function body chunks
 */
function shIntrospectCalledFunctionBodies(string $body, array $codeLines, int $depth = 0, array &$seen = []): array
{
    if ($depth >= 3) {
        return [];
    }
    $defs = shIntrospectFunctionBodyMap($codeLines);
    $out = [];
    // Candidate call tokens: identifiers that name a known function.
    if (!preg_match_all('/[A-Za-z_][A-Za-z0-9_]*/', $body, $mm)) {
        return [];
    }
    foreach (array_unique($mm[0]) as $name) {
        if (!isset($defs[$name]) || isset($seen[$name])) {
            continue;
        }
        $seen[$name] = true;
        $fnBody = $defs[$name];
        $out[] = $fnBody;
        foreach (shIntrospectCalledFunctionBodies($fnBody, $codeLines, $depth + 1, $seen) as $nested) {
            $out[] = $nested;
        }
    }
    return $out;
}

/**
 * Build a map of function name => body text from the code lines. Bodies span
 * from the `name() {` line to the matching closing brace (best-effort by brace
 * depth). Cached per identical $codeLines via a static memo.
 *
 * @param array<int,string> $codeLines
 * @return array<string,string>
 */
function shIntrospectFunctionBodyMap(array $codeLines): array
{
    static $memoKey = null;
    static $memo = [];
    $key = md5(implode("\n", $codeLines));
    if ($memoKey === $key) {
        return $memo;
    }

    $map = [];
    $total = count($codeLines);
    for ($i = 0; $i < $total; $i++) {
        if (!preg_match('/^\s*(?:function\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*\(\s*\)\s*\{/', $codeLines[$i], $fm)) {
            continue;
        }
        $name = $fm[1];
        $depth = 0;
        $started = false;
        $chunk = [];
        for ($j = $i; $j < $total; $j++) {
            $chunk[] = $codeLines[$j];
            $depth += substr_count($codeLines[$j], '{') - substr_count($codeLines[$j], '}');
            if (!$started && substr_count($codeLines[$j], '{') > 0) {
                $started = true;
            }
            if ($started && $depth <= 0) {
                break;
            }
        }
        // Keep the first definition if a name is (re)defined; modules are unique.
        if (!isset($map[$name])) {
            $map[$name] = implode("\n", $chunk);
        }
    }

    $memoKey = $key;
    $memo = $map;
    return $map;
}

/**
 * Mode-aware positionals: drop the QUERY positional for modes that do not
 * require a query so the contract reflects the real call shape.
 *
 * @param array<int,array<string,mixed>> $positionals
 * @return array<int,array<string,mixed>>
 */
function shIntrospectModePositionals(array $positionals, bool $queryRequired): array
{
    $out = [];
    foreach ($positionals as $pos) {
        $name = (string) ($pos['name'] ?? '');
        if ($name === '') {
            continue;
        }
        // The QUERY positional only applies when the mode consumes a query.
        if (!$queryRequired && strcasecmp($name, 'QUERY') === 0) {
            continue;
        }
        $entry = ['name' => $name, 'required' => !empty($pos['required'])];
        // A query-required mode makes the QUERY positional required even if the
        // global synopsis marked it optional (modes share one synopsis line).
        if ($queryRequired && strcasecmp($name, 'QUERY') === 0) {
            $entry['required'] = true;
        }
        $out[] = $entry;
    }
    return $out;
}

/**
 * Examples whose command invokes this exact mode token. Examples are matched by
 * locating the mode name as a standalone word after the script invocation.
 *
 * @param array<int,array<string,mixed>> $examples
 * @return array<int,string>
 */
function shIntrospectModeExamples(array $examples, string $mode): array
{
    $out = [];
    $quoted = preg_quote($mode, '/');
    // Match `ai-search.sh <mode> ` (mode as a whole word right after the script
    // name), tolerating an optional path component before the script.
    $pattern = '/\bai-search\.sh\s+' . $quoted . '(\s|$)/';
    foreach ($examples as $ex) {
        $text = (string) ($ex['text'] ?? '');
        if ($text !== '' && preg_match($pattern, $text)) {
            $out[] = $text;
        }
    }
    return $out;
}

/**
 * Parse replacement mode names for a deprecated mode from the deprecation
 * message in the code, e.g.
 *   "mode 'changed' is deprecated; use 'changed-files' ... or 'changed-text' ..."
 * Returns the quoted replacement tokens (excluding the deprecated mode itself),
 * de-duplicated and in first-seen order.
 *
 * @param array<int,string> $codeLines
 * @return array<int,string>
 */
function shIntrospectModeReplacements(string $mode, array $codeLines): array
{
    $out = [];
    $seen = [];
    foreach ($codeLines as $line) {
        // Only consider deprecation messages that name this mode.
        if (stripos($line, 'deprecated') === false) {
            continue;
        }
        if (!preg_match("/'" . preg_quote($mode, '/') . "'\\s+is\\s+deprecated/i", $line)) {
            continue;
        }
        // Collect every quoted token on the line; keep those that are not the
        // deprecated mode and look like a replacement mode name.
        if (preg_match_all("/'([a-z][a-z0-9-]*)'/i", $line, $m)) {
            foreach ($m[1] as $token) {
                if ($token === $mode || isset($seen[$token])) {
                    continue;
                }
                $seen[$token] = true;
                $out[] = $token;
            }
        }
    }
    return $out;
}

/**
 * Detect a leading slash-joined run of mode names in a flag description, e.g.
 * `struct/symbols/class language`. Returns the contained mode names ONLY when
 * every slash-joined token in the run is a real mode (so prose like
 * `path/marker/text` — none of which are modes — is ignored). Conservative: a
 * single non-mode token in the run discards the whole run.
 *
 * @param array<string,bool> $modeNames
 * @return array<int,string>
 */
function shIntrospectSlashModeRun(string $description, array $modeNames): array
{
    if (!preg_match('/^([a-z][a-z0-9-]*(?:\/[a-z][a-z0-9-]*)+)\b/i', trim($description), $m)) {
        return [];
    }
    $tokens = explode('/', $m[1]);
    $out = [];
    foreach ($tokens as $token) {
        $token = trim($token);
        if ($token === '' || !isset($modeNames[$token])) {
            return [];
        }
        $out[$token] = true;
    }
    return array_keys($out);
}

/**
 * P1: build a per-mode example index: { mode, examples[] } for every mode that
 * has at least one linked example. Examples are matched by the mode token
 * appearing right after the script invocation. Deterministic by mode name.
 *
 * @param array<int,array<string,mixed>> $modes
 * @param array<int,array<string,mixed>> $examples
 * @return array<int,array<string,mixed>>
 */
function shIntrospectBuildExamplesByMode(array $modes, array $examples): array
{
    $out = [];
    foreach ($modes as $mode) {
        $name = (string) ($mode['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $linked = shIntrospectModeExamples($examples, $name);
        if ($linked !== []) {
            $out[$name] = ['mode' => $name, 'examples' => $linked];
        }
    }
    ksort($out, SORT_STRING);
    return array_values($out);
}
