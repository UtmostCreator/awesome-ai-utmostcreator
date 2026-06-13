<?php

declare(strict_types=1);

/**
 * Merge usage-doc-only flags as confidence-75 params when not already present
 * from parser-case classification.
 *
 * @param array<int,array<string,mixed>> $params
 * @return array<int,array<string,mixed>>
 */
function shIntrospectMergeUsageParams(array $params, string $usageBlock): array
{
    if ($usageBlock === '') {
        return $params;
    }

    $known = [];
    foreach ($params as $p) {
        $known[(string) $p['name']] = true;
        foreach ((array) $p['aliases'] as $a) {
            $known[(string) $a] = true;
        }
    }

    // Find flag tokens in the usage block: leading-of-line `--flag` or `-X`.
    $usageLines = preg_split('/\n/', $usageBlock) ?: [];
    foreach ($usageLines as $uline) {
        if (!preg_match('/^\s*(--[a-z][a-z0-9-]*)\b/i', $uline, $m)) {
            continue;
        }
        $flag = $m[1];
        if (isset($known[$flag])) {
            continue;
        }
        $known[$flag] = true;

        $hint = shIntrospectValueHint($flag, $usageBlock);
        $param = [
            'name' => $flag,
            'aliases' => [],
            'line' => 0,
            'takes_value' => $hint !== null,
            'repeatable' => false,
            'source' => 'usage-doc',
            'confidence' => 75,
        ];
        if ($hint !== null) {
            $param['value_hint'] = $hint;
        }
        $params[] = $param;
    }

    return $params;
}

/**
 * Extract the documented status vocabulary from a usage block line of the form
 * "Status is one of: a | b | c." (the values may wrap onto the next line). Only
 * lowercase identifier tokens are kept; returns [] when the phrase is absent.
 * Never invents values.
 *
 * @return array<int,string>
 */
function shIntrospectExtractStatusValues(string $usageBlock): array
{
    if ($usageBlock === '') {
        return [];
    }
    // Collapse to a single space so wrapped value lists are captured whole, then
    // grab everything between the marker phrase and the terminating period.
    $flat = preg_replace('/\s+/', ' ', $usageBlock) ?? $usageBlock;
    if (!preg_match('/Status (?:is|emitted by this script)[^:]*:\s*([^.]+)/i', $flat, $m)) {
        return [];
    }
    $segment = $m[1];
    $values = [];
    foreach (preg_split('/\s*\|\s*/', $segment) ?: [] as $token) {
        $token = trim($token);
        // Keep only bare status identifiers (lower-case, _-joined words).
        if (preg_match('/^[a-z][a-z0-9_]*$/', $token)) {
            $values[$token] = true;
        }
    }
    return array_keys($values);
}

/**
 * Extract top-level help metadata from the usage block:
 *   - summary: text after the em/en-dash on the title line (script tagline).
 *   - usage: the first indented line under a "Usage:" heading.
 *   - json_output_env: the documented JSON-output env toggle (e.g.
 *     "AI_OUTPUT=json") when the usage text references it.
 * Each key is present only when derivable; returns [] otherwise. Never invents.
 *
 * @return array<string,string>
 */
function shIntrospectExtractHelpMeta(string $usageBlock): array
{
    if ($usageBlock === '') {
        return [];
    }
    $lines = preg_split('/\n/', $usageBlock) ?: [];
    $meta = [];

    // summary: "<tool> — <summary>." on the first non-empty line.
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }
        if (preg_match('/^\S.*?(?:\x{2014}|\x{2013}|\s-\s)\s*(.+)$/u', $trimmed, $m)) {
            $summary = rtrim(trim($m[1]), '.');
            if ($summary !== '') {
                $meta['summary'] = $summary;
            }
        }
        break;
    }

    // usage: first non-empty indented line after a "Usage:" heading line.
    $afterUsageHeading = false;
    foreach ($lines as $line) {
        if (preg_match('/^\s*Usage:\s*$/', $line)) {
            $afterUsageHeading = true;
            continue;
        }
        if ($afterUsageHeading) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            $meta['usage'] = $trimmed;
            break;
        }
    }

    // json_output_env: the AI_OUTPUT=json (or similar *_OUTPUT=json) toggle.
    if (preg_match('/\b([A-Z][A-Z0-9_]*=json)\b/', $usageBlock, $m)) {
        $meta['json_output_env'] = $m[1];
    }

    return $meta;
}

/**
 * Parse documented mode rows from the usage block, keyed by mode name.
 *
 * A mode row lives under a section heading whose text contains "modes" and has
 * the shape `<indent><name>[ QUERYTOKEN]  <description>`, where continuation
 * lines (deeper indent, no description column of their own) extend the previous
 * row's description. Flag rows (`--flag`/`-x`) and deprecated-alias rows that
 * pair two names with `|` are ignored here. Output-shape hints embedded in the
 * description ("results carry ...") are also surfaced as `output_notes`.
 *
 * Each mode also carries a `display_group`: a human-facing grouping derived
 * from its section heading (e.g. "Git-aware modes:" -> `git-aware`, "Structural
 * modes:" -> `structural`). This is purely presentational and never overrides
 * the machine `family`. Empty string when the heading has no useful group.
 *
 * @return array<string,array{description:string,output_notes:string,display_group:string}>
 */
function shIntrospectExtractUsageModeDocs(string $usageBlock): array
{
    if ($usageBlock === '') {
        return [];
    }
    $lines = preg_split('/\n/', $usageBlock) ?: [];

    $docs = [];
    $inModeSection = false;
    $lastName = null;
    $displayGroup = '';

    foreach ($lines as $line) {
        // Section heading: non-indented (<=1 leading space) text ending in ':'.
        if (preg_match('/^\s{0,1}(\S.*):\s*$/', $line, $hm)) {
            $inModeSection = stripos($hm[1], 'modes') !== false;
            $displayGroup = $inModeSection ? shIntrospectModeDisplayGroup($hm[1]) : '';
            $lastName = null;
            continue;
        }
        // Blank line ends the current row's continuation but not the section.
        if (trim($line) === '') {
            $lastName = null;
            if (!preg_match('/^\s/', $line)) {
                $inModeSection = false;
            }
            continue;
        }
        if (!$inModeSection) {
            continue;
        }

        // Mode row: indented, first token is a lower-case mode name (with an
        // optional trailing QUERY/PATTERN/NAME/REF positional token), then >=2
        // spaces and a description.
        if (preg_match('/^\s{2,}([a-z][a-z0-9-]*)(?:\s+[A-Z][A-Z]+)?\s{2,}(\S.*)$/', $line, $rm)) {
            $name = $rm[1];
            $desc = trim($rm[2]);
            $docs[$name] = [
                'description' => $desc,
                'output_notes' => shIntrospectModeOutputNote($desc),
                'display_group' => $displayGroup,
            ];
            $lastName = $name;
            continue;
        }

        // Continuation line: deeper indent, no own description column. Append to
        // the previous mode's description.
        if ($lastName !== null && preg_match('/^\s{6,}(\S.*)$/', $line, $cm)) {
            $cont = trim($cm[1]);
            if ($cont !== '') {
                $docs[$lastName]['description'] = trim($docs[$lastName]['description'] . ' ' . $cont);
                $docs[$lastName]['output_notes'] = shIntrospectModeOutputNote($docs[$lastName]['description']);
            }
            continue;
        }

        // Anything else (e.g. a `name | name` alias row) does not extend a row.
        $lastName = null;
    }

    return $docs;
}

/**
 * Pull an output-shape note out of a mode description when it documents the
 * result columns (e.g. "results carry path/marker/new_line/text/scope"). Returns
 * '' when no such phrase is present. Derived only from the description text.
 */
function shIntrospectModeDisplayGroup(string $heading): string
{
    $h = strtolower(trim($heading));
    // Drop the trailing "modes" word so "Git-aware modes" -> "git-aware".
    $h = trim((string) preg_replace('/\bmodes?\b/', '', $h));
    if ($h === '') {
        return '';
    }
    if (str_contains($h, 'git-aware') || str_contains($h, 'git aware')) {
        return 'git-aware';
    }
    if (str_contains($h, 'structural')) {
        return 'structural';
    }
    if (str_contains($h, 'curated')) {
        return 'curated';
    }
    if (str_contains($h, 'file-list') || str_contains($h, 'file list')) {
        return 'file-list';
    }
    if (str_contains($h, 'surface')) {
        return 'surface';
    }
    if (str_contains($h, 'content')) {
        return 'content';
    }
    // Fall back to a slug of the heading (first word group), e.g. "other".
    if (preg_match('/^([a-z][a-z0-9-]*)/', $h, $m)) {
        return $m[1];
    }
    return '';
}

function shIntrospectModeOutputNote(string $description): string
{
    if (preg_match('/((?:results?|emits|symbols\[\])[^.;]*?(?:carry|carries|with|of)\s+[A-Za-z][A-Za-z0-9_\/, ]+)/i', $description, $m)) {
        return trim($m[1]);
    }
    return '';
}

/**
 * Merge usage-documented modes that have no `is_*_mode` family guard into the
 * classified mode list. This keeps --help and --introspect in agreement for
 * modes the script implements but routes outside the family functions (e.g.
 * `doctor`, `unsafe-all`). Existing modes are also enriched with a `description`
 * from the usage docs. New modes are tagged family `other` and never marked
 * query-required.
 *
 * `$guardModes` carries mode names proven to be REAL dispatch targets by a
 * `[[ "$mode" == "name" ]]`-style equality guard in the code (see
 * shIntrospectExtractModeEqualityGuards). A documented mode that is also a guard
 * target is added with `source: mode-equality-guard` and confidence 90 instead
 * of the documentation-only confidence 75 — i.e. it is confirmed implemented,
 * not merely documented. Conservative: only documented rows are added; nothing
 * invented.
 *
 * @param array<int,array<string,mixed>> $modes
 * @param array<string,array{description:string,output_notes:string,display_group:string}> $usageModeDocs
 * @param array<string,int> $guardModes mode name => 1-based guard line
 * @return array<int,array<string,mixed>>
 */
function shIntrospectMergeUsageModes(array $modes, array $usageModeDocs, array $guardModes = []): array
{
    if ($usageModeDocs === []) {
        return $modes;
    }

    $known = [];
    foreach ($modes as $i => $mode) {
        $name = (string) ($mode['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $known[$name] = true;
        // Enrich an existing classified mode with its usage description.
        if (isset($usageModeDocs[$name]['description'])
            && $usageModeDocs[$name]['description'] !== ''
            && !isset($modes[$i]['description'])
        ) {
            $modes[$i]['description'] = $usageModeDocs[$name]['description'];
        }
        // Attach the human-facing display_group (presentational only; never
        // overrides the machine `family`). e.g. diff/history stay family
        // `content` but display_group `git-aware`.
        $dg = (string) ($usageModeDocs[$name]['display_group'] ?? '');
        if ($dg !== '' && !isset($modes[$i]['display_group'])) {
            $modes[$i]['display_group'] = $dg;
        }
    }

    foreach ($usageModeDocs as $name => $doc) {
        if (isset($known[$name])) {
            continue;
        }
        // A documented mode proven by an equality guard is a confirmed dispatch
        // target (implemented), so it earns the higher-confidence source.
        $isGuarded = isset($guardModes[$name]);
        $mode = [
            'name' => $name,
            'query_required' => false,
            'family' => 'other',
            'source' => $isGuarded ? 'mode-equality-guard' : 'usage-doc',
            'confidence' => $isGuarded ? 90 : 75,
        ];
        if ($isGuarded) {
            $mode['line'] = (int) $guardModes[$name];
        }
        if (($doc['description'] ?? '') !== '') {
            $mode['description'] = (string) $doc['description'];
        }
        if (($doc['display_group'] ?? '') !== '') {
            $mode['display_group'] = (string) $doc['display_group'];
        }
        $modes[] = $mode;
    }

    return $modes;
}

/**
 * Detect modes that are real dispatch targets via an equality guard against the
 * mode subject, e.g. `[[ "$mode" == "doctor" ]]`, `[ "$1" = "doctor" ]`, or
 * `if [[ "$cmd" == doctor ]]`. These confirm a mode is IMPLEMENTED (routed in
 * code), as opposed to merely documented in the usage block.
 *
 * Only equality comparisons whose left side is the recognised mode subject
 * (`mode`, `1`, or `cmd`) are honoured, so an unrelated `[[ "$x" == "y" ]]` is
 * never mistaken for a mode. Returns a name => 1-based line map (first match per
 * name wins).
 *
 * @param array<int,string> $codeLines
 * @return array<string,int>
 */
function shIntrospectExtractModeEqualityGuards(array $codeLines): array
{
    $out = [];
    // Left side: "$mode" / $mode / "${mode}" / "$1" / "$cmd"; operator: == or =;
    // right side: a bare or quoted lower-case mode-like token.
    $pattern = '/(?:\[\[?|\b(?:test)\b)\s*'
        . '"?\$\{?(mode|1|cmd)\}?"?\s*'
        . '(?:==|=)\s*'
        . '["\']?([a-z][a-z0-9-]*)["\']?/';

    foreach ($codeLines as $idx => $line) {
        if (!preg_match_all($pattern, $line, $all, PREG_SET_ORDER)) {
            continue;
        }
        foreach ($all as $m) {
            $name = $m[2];
            if ($name === '' || isset($out[$name])) {
                continue;
            }
            $out[$name] = $idx + 1;
        }
    }

    return $out;
}

/**
 * Extract the flag/alias names from a flag-row fragment, tolerating interleaved
 * uppercase value placeholders (e.g. `--before-context N | -B N`). Every token
 * that looks like a flag is kept, in order; value placeholders and other tokens
 * are ignored.
 *
 * @return array<int,string>
 */
function shIntrospectExtractFlagRowNames(string $fragment): array
{
    $names = [];
    if (preg_match_all('/(?<![\w-])(--?[A-Za-z][\w-]*)/', $fragment, $m)) {
        foreach ($m[1] as $tok) {
            if (preg_match('/^--?[A-Za-z][\w-]*$/', $tok)) {
                $names[] = $tok;
            }
        }
    }
    return $names;
}

/**
 * Parse documented flag rows from the usage block into a group + description map
 * keyed by flag name (and any aliases). Group names come from the sub-headings
 * under "Flags:" (e.g. "Pattern / case:", "Scope:", "Bounds:"), normalised to a
 * short token. Only rows beginning with `--flag`/`-x` are considered; the first
 * token after the flag column (>=2 spaces) is the description. Returns [] when
 * no flag rows are present. Never invents descriptions.
 *
 * @return array<string,array{group:string,description:string}>
 */
function shIntrospectExtractUsageParamDocs(string $usageBlock): array
{
    if ($usageBlock === '') {
        return [];
    }
    $lines = preg_split('/\n/', $usageBlock) ?: [];

    // Map a usage sub-heading to one of the documented group tokens.
    $groupFor = static function (string $heading): string {
        $h = strtolower($heading);
        if (str_contains($h, 'pattern') || str_contains($h, 'case')) {
            return 'pattern';
        }
        if (str_contains($h, 'ignore')) {
            return 'ignore';
        }
        if (str_contains($h, 'scope')) {
            return 'scope';
        }
        if (str_contains($h, 'context')) {
            return 'context';
        }
        if (str_contains($h, 'output')) {
            return 'output';
        }
        if (str_contains($h, 'bound')) {
            return 'bounds';
        }
        if (str_contains($h, 'git')) {
            return 'git';
        }
        if (str_contains($h, 'structural')) {
            return 'structural';
        }
        return 'misc';
    };

    $docs = [];
    $inFlags = false;
    $group = 'misc';
    $lastFlags = [];

    foreach ($lines as $line) {
        // Top-level "Flags:" heading enters the flag region.
        if (preg_match('/^\s{0,1}Flags:\s*$/', $line)) {
            $inFlags = true;
            $group = 'misc';
            $lastFlags = [];
            continue;
        }
        if (!$inFlags) {
            continue;
        }
        // A new top-level heading (e.g. "Examples:") ends the flag region.
        if (preg_match('/^\s{0,1}(\S.*):\s*$/', $line, $hm) && !preg_match('/^\s{2,}/', $line)) {
            $inFlags = false;
            continue;
        }
        // Sub-heading under Flags: indented label ending with ':' (no flag).
        if (preg_match('/^\s{2,}([A-Za-z][^:]*):\s*$/', $line, $sm)
            && !preg_match('/^\s*-/', $line)
        ) {
            $group = $groupFor(trim($sm[1]));
            $lastFlags = [];
            continue;
        }

        // Flag row: `--flag` or `-x`, optional aliases via `|`, then >=2 spaces
        // and a description. Drop a value placeholder token after the flag.
        if (preg_match('/^\s{2,}(--?[A-Za-z][\w-]*(?:\s*\|\s*--?[A-Za-z][\w-]*)*)\b.*?\s{2,}(\S.*)$/', $line, $fm)) {
            $flagsPart = $fm[1];
            $desc = trim($fm[2]);
            $names = shIntrospectExtractFlagRowNames($flagsPart);
            $lastFlags = $names;
            foreach ($names as $n) {
                if (!isset($docs[$n])) {
                    $docs[$n] = ['group' => $group, 'description' => $desc];
                }
            }
            continue;
        }

        // Description-less flag row: a flag (and optional aliases / value
        // placeholders) with NO trailing description column, e.g.
        //   `--before-context N | -B N`. The flag still belongs to the current
        // sub-heading group, so record it with an empty description rather than
        // letting it fall through ungrouped (which would default it to `misc`).
        if (preg_match('/^\s{2,}(--?[A-Za-z][\w-]*)\b/', $line, $bm)
            && !preg_match('/^\s{2,}[A-Za-z][^:]*:\s*$/', $line)
        ) {
            $names = shIntrospectExtractFlagRowNames(trim($line));
            if ($names !== []) {
                $lastFlags = $names;
                foreach ($names as $n) {
                    if (!isset($docs[$n])) {
                        $docs[$n] = ['group' => $group, 'description' => ''];
                    }
                }
                continue;
            }
        }

        // Continuation line: deeper indent, append to the last flag(s) desc.
        if ($lastFlags !== [] && preg_match('/^\s{6,}(\S.*)$/', $line, $cm)) {
            $cont = trim($cm[1]);
            // Skip parenthetical clarifications that are not part of the desc.
            foreach ($lastFlags as $n) {
                if (isset($docs[$n])) {
                    $docs[$n]['description'] = trim($docs[$n]['description'] . ' ' . $cont);
                }
            }
            continue;
        }

        if (trim($line) === '') {
            $lastFlags = [];
        }
    }

    return $docs;
}

/**
 * Attach `group` and `description` to params from the usage flag docs. A param
 * matches by its primary name or any alias. Only adds keys when the usage block
 * documents that flag; never invents. value_hint/group/description stay absent
 * when unknown.
 *
 * @param array<int,array<string,mixed>> $params
 * @param array<string,array{group:string,description:string}> $usageParamDocs
 * @return array<int,array<string,mixed>>
 */
function shIntrospectAttachParamDocs(array $params, array $usageParamDocs): array
{
    if ($usageParamDocs === []) {
        return $params;
    }

    foreach ($params as $i => $param) {
        $candidates = [(string) ($param['name'] ?? '')];
        foreach ((array) ($param['aliases'] ?? []) as $a) {
            $candidates[] = (string) $a;
        }
        foreach ($candidates as $cand) {
            if ($cand === '' || !isset($usageParamDocs[$cand])) {
                continue;
            }
            $doc = $usageParamDocs[$cand];
            if (!isset($params[$i]['group']) && ($doc['group'] ?? '') !== '') {
                $params[$i]['group'] = $doc['group'];
            }
            if (!isset($params[$i]['description']) && ($doc['description'] ?? '') !== '') {
                $params[$i]['description'] = $doc['description'];
            }
            break;
        }
    }

    return $params;
}
