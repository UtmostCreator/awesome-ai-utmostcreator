<?php

declare(strict_types=1);

/**
 * Classify case branches into params, modes, case_labels, and the unknown-option
 * fallback handlers per the binding rules.
 *
 * @param array<int,array<string,mixed>> $branches
 * @return array{
 *   params:array<int,array<string,mixed>>,
 *   modes:array<int,array<string,mixed>>,
 *   case_labels:array<int,array<string,mixed>>,
 *   unknown_option_handlers:array<int,array<string,mixed>>
 * }
 */
function shIntrospectClassifyBranches(array $branches, string $usageBlock): array
{
    $params = [];
    $modes = [];
    $caseLabels = [];
    $unknownHandlers = [];
    // Track mode names so they are never duplicated into case_labels.
    $modeNames = [];

    // Family map keyed by enclosing is_*_mode function name.
    $familyMap = [
        'is_content_mode' => ['family' => 'content', 'query_required' => true],
        'is_file_list_mode' => ['family' => 'file-list', 'query_required' => false],
        'is_no_query_mode' => ['family' => 'curated', 'query_required' => false],
        'is_ast_mode' => ['family' => 'structural', 'query_required' => true],
        'is_surface_mode' => ['family' => 'surface', 'query_required' => false],
    ];

    foreach ($branches as $branch) {
        $labels = $branch['labels'];
        if ($labels === []) {
            continue;
        }

        $primary = $labels[0];

        // Unknown-option fallback handlers: `--*`, `-*`, or a bare `*` that lives
        // inside a flag-parsing case (i.e. siblings are flag labels). These are
        // NOT real params and must be reported separately.
        if (in_array($primary, ['--*', '-*', '*'], true)) {
            if (shIntrospectBranchIsFlagFallback($primary, $branch)) {
                $unknownHandlers[] = shIntrospectBuildUnknownHandler($branch);
            }
            // A bare `*)` outside a flag parser is just a default branch; skip it.
            continue;
        }

        $isFlag = static fn(string $l): bool => str_starts_with($l, '-');

        // (a) Flag labels => params.
        if ($isFlag($primary)) {
            $params[] = shIntrospectBuildParam($branch, $usageBlock);
            continue;
        }

        // (b) Bare-word labels inside an is_*_mode function => modes.
        $fn = (string) $branch['enclosing_function'];
        if (isset($familyMap[$fn]) && shIntrospectBodyReturnsZero($branch['body'])) {
            foreach ($labels as $label) {
                if ($label === '*') {
                    continue;
                }
                $mode = [
                    'name' => $label,
                    'line' => $branch['line'],
                    'query_required' => (bool) $familyMap[$fn]['query_required'],
                    'family' => (string) $familyMap[$fn]['family'],
                    'source' => 'mode-family:' . $fn,
                    'confidence' => 95,
                ];
                if (shIntrospectIsDeprecatedMode($label, $branch['body'])) {
                    $mode['deprecated'] = true;
                }
                $modes[] = $mode;
                $modeNames[$label] = true;
            }
            continue;
        }

        // (c) Bare-word labels under case "$mode"/"$1"/"$cmd" => likely modes.
        $subject = shIntrospectNormalizeSubject($branch['subject']);
        if (in_array($subject, ['mode', '1', 'cmd'], true)) {
            foreach ($labels as $label) {
                if ($label === '*') {
                    continue;
                }
                $mode = [
                    'name' => $label,
                    'line' => $branch['line'],
                    'query_required' => false,
                    'family' => 'unknown',
                    'source' => 'subject-case:' . $subject,
                    'confidence' => 80,
                ];
                if (shIntrospectIsDeprecatedMode($label, $branch['body'])) {
                    $mode['deprecated'] = true;
                }
                $modes[] = $mode;
                $modeNames[$label] = true;
            }
            continue;
        }

        // (d) Other bare-word labels => case_labels (internal case branch labels).
        foreach ($labels as $label) {
            if (!shIntrospectIsRealCaseLabel($label)) {
                continue;
            }
            $caseLabels[] = [
                'name' => $label,
                'line' => $branch['line'],
                'case_subject' => $branch['subject'],
                'source' => 'internal-case',
                'confidence' => 70,
            ];
        }
    }

    // De-duplicate case_labels by name (keep first occurrence) and drop any that
    // collide with a classified mode name.
    $caseLabels = shIntrospectDedupeCaseLabels($caseLabels, $modeNames);

    // De-duplicate modes by name, keeping the highest confidence; merge the
    // deprecated flag if any occurrence set it.
    $modes = shIntrospectDedupeModes($modes);

    return [
        'params' => $params,
        'modes' => $modes,
        'case_labels' => $caseLabels,
        'unknown_option_handlers' => $unknownHandlers,
    ];
}

/**
 * Decide whether a label is a real internal case label worth reporting.
 *
 * Drops pure-noise labels: the bare fallback `*`, bare integers (`0`,`1`,`2`),
 * and any token containing glob/quote/expansion junk (`*`, `"`, `$`, `(`, `)`).
 * Keeps real bare-word labels such as `fixed`, `ignore`, `count-matches`.
 */
function shIntrospectIsRealCaseLabel(string $label): bool
{
    $label = trim($label);
    if ($label === '' || $label === '*') {
        return false;
    }
    // Bare integers are positional/noise, not meaningful labels.
    if (preg_match('/^[0-9]+$/', $label)) {
        return false;
    }
    // Reject glob-ish / quote / expansion junk.
    if (preg_match('/[*"$()]/', $label)) {
        return false;
    }
    return true;
}

/**
 * De-duplicate case_labels by name (keep first occurrence) and drop any label
 * that was already classified as a mode.
 *
 * @param array<int,array<string,mixed>> $caseLabels
 * @param array<string,bool> $modeNames
 * @return array<int,array<string,mixed>>
 */
function shIntrospectDedupeCaseLabels(array $caseLabels, array $modeNames): array
{
    $byName = [];
    foreach ($caseLabels as $label) {
        $name = (string) $label['name'];
        if (isset($modeNames[$name]) || isset($byName[$name])) {
            continue;
        }
        $byName[$name] = $label;
    }
    return array_values($byName);
}

/**
 * Is this fallback branch part of a flag-parsing case?
 *
 * `--*` and `-*` are always option-fallback patterns. A bare `*` only counts
 * when its case subject is the argument/flag stream (e.g. `$1`, `$arg`, `flag`)
 * or its body references a flag-ish action; otherwise it is an ordinary default
 * branch and is ignored.
 *
 * @param array<string,mixed> $branch
 */
function shIntrospectBranchIsFlagFallback(string $primary, array $branch): bool
{
    if ($primary === '--*' || $primary === '-*') {
        return true;
    }
    // primary === '*': require flag-parsing context.
    $subject = shIntrospectNormalizeSubject((string) $branch['subject']);
    if (in_array($subject, ['1', 'arg', 'flag', 'opt', 'option'], true)) {
        $body = (string) $branch['body'];
        return (bool) preg_match('/(unknown|invalid|unrecognized)\s+(flag|option|arg)/i', $body)
            || (bool) preg_match('/positionals?\+?=\(/', $body);
    }
    return false;
}

/**
 * Build an unknown-option-handler record from a fallback branch.
 *
 * action = "fail" when the body fails/dies/errors/exits; otherwise best-effort
 * "passthrough" when it stores the arg, else "unknown".
 *
 * @param array<string,mixed> $branch
 * @return array<string,mixed>
 */
function shIntrospectBuildUnknownHandler(array $branch): array
{
    $body = (string) $branch['body'];
    $action = 'unknown';
    if (preg_match('/\b(fail|die|error)\b/i', $body) || preg_match('/\bexit\b/', $body)) {
        $action = 'fail';
    } elseif (preg_match('/positionals?\+?=\(/', $body) || preg_match('/\+=\(/', $body)) {
        $action = 'passthrough';
    }

    return [
        'pattern' => (string) $branch['labels'][0],
        'action' => $action,
        'line' => (int) $branch['line'],
    ];
}

/**
 * True when a branch body returns 0 (mode-family membership).
 */
function shIntrospectBodyReturnsZero(string $body): bool
{
    return (bool) preg_match('/\breturn\s+0\b/', $body);
}

/**
 * Mark a mode deprecated only when the body references "deprecated".
 */
function shIntrospectIsDeprecatedMode(string $label, string $body): bool
{
    return stripos($body, 'deprecated') !== false;
}

/**
 * Normalize a case subject expression to a bare variable name where possible:
 *   "$mode" / $mode / "${mode}" -> mode ; "$1" -> 1.
 */
function shIntrospectNormalizeSubject(string $subject): string
{
    $s = trim($subject);
    $s = trim($s, '"\'');
    // ${name} or ${name:-...}
    if (preg_match('/^\$\{?([A-Za-z_][A-Za-z0-9_]*)/', $s, $m)) {
        return $m[1];
    }
    // $1
    if (preg_match('/^\$\{?([0-9]+)/', $s, $m)) {
        return $m[1];
    }
    return $s;
}

/**
 * De-duplicate modes by name; keep highest confidence and OR the deprecated
 * flag.
 *
 * @param array<int,array<string,mixed>> $modes
 * @return array<int,array<string,mixed>>
 */
function shIntrospectDedupeModes(array $modes): array
{
    $byName = [];
    foreach ($modes as $mode) {
        $name = (string) $mode['name'];
        if (!isset($byName[$name])) {
            $byName[$name] = $mode;
            continue;
        }
        $existing = $byName[$name];
        // Prefer the higher-confidence record.
        if ((int) $mode['confidence'] > (int) $existing['confidence']) {
            if (!empty($existing['deprecated'])) {
                $mode['deprecated'] = true;
            }
            $byName[$name] = $mode;
        } elseif (!empty($mode['deprecated'])) {
            $byName[$name]['deprecated'] = true;
        }
    }
    return array_values($byName);
}
