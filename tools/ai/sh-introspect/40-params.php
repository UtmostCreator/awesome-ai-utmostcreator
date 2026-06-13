<?php

declare(strict_types=1);

/**
 * Build a param record from a flag branch.
 *
 * @param array<string,mixed> $branch
 * @return array<string,mixed>
 */
function shIntrospectBuildParam(array $branch, string $usageBlock): array
{
    $labels = $branch['labels'];
    $name = $labels[0];
    $aliases = array_values(array_slice($labels, 1));

    $body = (string) $branch['body'];

    // takes_value: a `shift` OR a reference to "${1:-}" / "$1".
    $takesValue = (bool) (
        preg_match('/(^|\s)shift\b/', $body)
        || strpos($body, '"${1:-}"') !== false
        || strpos($body, '${1:-}') !== false
        || preg_match('/"\$1"/', $body)
    );

    // repeatable: body contains `+=(`.
    $repeatable = strpos($body, '+=(') !== false;

    $param = [
        'name' => $name,
        'aliases' => $aliases,
        'line' => $branch['line'],
        'takes_value' => $takesValue,
        'repeatable' => $repeatable,
        'source' => 'parser-case',
        'confidence' => 95,
    ];

    // Only attach a value hint when the flag actually consumes a value;
    // otherwise an unrelated uppercase token after the flag in the usage
    // block (e.g. a following example) would produce a misleading hint.
    if ($takesValue) {
        $hint = shIntrospectValueHint($name, $usageBlock);
        if ($hint !== null) {
            $param['value_hint'] = $hint;
        }
    }

    return $param;
}

/**
 * Best-effort value hint for a flag from the usage block (e.g. `--glob PATTERN`).
 */
function shIntrospectValueHint(string $flag, string $usageBlock): ?string
{
    if ($usageBlock === '') {
        return null;
    }
    // Match: <flag> WORD   where WORD is an uppercase-ish placeholder token.
    $pattern = '/' . preg_quote($flag, '/') . '\s+([A-Z][A-Z0-9_]*)\b/';
    if (preg_match($pattern, $usageBlock, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * P1: map each param to the modes it applies to — strict, high-precision only.
 *
 * Earlier heuristics over-matched (mode names appearing as field names or prose
 * on a co-incidental line). This version only records a mapping when the flag's
 * OWN documentation row carries an explicit `<mode>:` qualifier, which is the
 * real "this flag is mode-scoped" convention, e.g.
 *
 *     --messages              history: search commit messages
 *     --patch                 history: attach commit patch text
 *
 * A flag's doc row is the usage line that starts with that flag (after optional
 * indentation). Only mode names that appear as a `mode:` (or `mode/mode2:`)
 * qualifier within that row — or on its immediate continuation row that begins
 * with `mode:` — are recorded. When no explicit qualifier exists the param is
 * left unmapped (no `applies_to_modes` key) rather than guessing.
 *
 * @param array<int,array<string,mixed>> $params
 * @param array<int,array<string,mixed>> $modes
 * @return array<int,array<string,mixed>>
 */
function shIntrospectMapParamsToModes(array $params, array $modes, string $usageBlock): array
{
    if ($params === [] || $modes === [] || $usageBlock === '') {
        return $params;
    }

    $modeNames = [];
    foreach ($modes as $mode) {
        $name = (string) ($mode['name'] ?? '');
        if ($name !== '') {
            $modeNames[$name] = true;
        }
    }

    $usageLines = preg_split('/\n/', $usageBlock) ?: [];
    $lineCount = count($usageLines);

    foreach ($params as &$param) {
        $flag = (string) ($param['name'] ?? '');
        if ($flag === '') {
            continue;
        }
        $aliases = is_array($param['aliases'] ?? null) ? $param['aliases'] : [];
        $needles = array_merge([$flag], array_map(static fn($a): string => (string) $a, $aliases));

        // Find this flag's own documentation row(s): a line beginning (after
        // indentation) with the flag token.
        $hits = [];
        for ($i = 0; $i < $lineCount; $i++) {
            $line = $usageLines[$i];
            $isOwnRow = false;
            foreach ($needles as $needle) {
                if ($needle !== '' && preg_match('/^\s*' . preg_quote($needle, '/') . '\b/', $line)) {
                    $isOwnRow = true;
                    break;
                }
            }
            if (!$isOwnRow) {
                continue;
            }

            // Gather the row text plus a single continuation line (indented,
            // not itself a new flag/mode row) for wrapped descriptions.
            $rowText = $line;
            if ($i + 1 < $lineCount
                && preg_match('/^\s+\S/', $usageLines[$i + 1])
                && !preg_match('/^\s*-/', $usageLines[$i + 1])) {
                $rowText .= ' ' . $usageLines[$i + 1];
            }

            foreach (shIntrospectModeQualifiers($rowText, $modeNames) as $modeName) {
                $hits[$modeName] = true;
            }
        }

        // Secondary, narrow signal: a description that opens with a
        // slash-joined run of mode names (e.g. `struct/symbols/class language`)
        // where EVERY token is a real mode. This captures structural flags like
        // `--lang` that scope to several modes without a `mode:` colon group.
        if ($hits === []) {
            foreach ($needles as $needle) {
                if ($needle === '') {
                    continue;
                }
                foreach ($usageLines as $line) {
                    // Allow an optional uppercase value placeholder between the
                    // flag and the description column (e.g. `--lang LANG  ...`).
                    if (!preg_match('/^\s*' . preg_quote($needle, '/') . '\b(?:\s+[A-Z][A-Z0-9_]*)?\s{2,}(\S.*)$/', $line, $dm)) {
                        continue;
                    }
                    foreach (shIntrospectSlashModeRun($dm[1], $modeNames) as $modeName) {
                        $hits[$modeName] = true;
                    }
                }
            }
        }

        if ($hits !== []) {
            $names = array_keys($hits);
            sort($names, SORT_STRING);
            $param['applies_to_modes'] = $names;
            // A flag scoped to specific modes is, by definition, not global.
            $param['scope'] = 'mode-specific';
        } else {
            // No documented mode restriction: the flag is global. This removes
            // ambiguity for AI command generation — every public param now
            // carries either applies_to_modes (mode-specific) or scope:global.
            $param['scope'] = 'global';
        }
    }
    unset($param);

    return $params;
}

/**
 * Extract explicit `<mode>:` qualifiers from a flag-description row. Matches
 * `history:` or `history/diff:` style prefixes and returns the contained mode
 * names that are real modes. Field-name and prose mentions (no trailing colon
 * group) are ignored.
 *
 * @param array<string,bool> $modeNames
 * @return array<int,string>
 */
function shIntrospectModeQualifiers(string $rowText, array $modeNames): array
{
    $out = [];
    // Find `word(/word)*:` qualifier groups and keep the tokens that are modes.
    if (preg_match_all('/\b([a-z][a-z0-9-]*(?:\/[a-z][a-z0-9-]*)*)\s*:/i', $rowText, $m)) {
        foreach ($m[1] as $group) {
            foreach (explode('/', $group) as $token) {
                $token = trim($token);
                if ($token !== '' && isset($modeNames[$token])) {
                    $out[$token] = true;
                }
            }
        }
    }
    return array_keys($out);
}
