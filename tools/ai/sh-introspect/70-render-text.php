<?php

declare(strict_types=1);

/**
 * Classify the script kind: "script" (has shebang and executable top-level
 * logic), "library" (sourced helper without a shebang/main entry), else
 * "unknown".
 *
 * @param array<int,string> $codeLines
 */
function shIntrospectDetectKind(string $raw, array $codeLines): string
{
    $hasShebang = (bool) preg_match('/^#!.*\b(ba)?sh\b/', $raw);

    // Top-level executable logic: a non-comment, non-function, non-blank line at
    // column 0 that is not purely a `source`/`.`/variable assignment heredoc.
    $hasTopLevelLogic = false;
    foreach ($codeLines as $line) {
        if ($line === '' || preg_match('/^\s/', $line)) {
            continue; // indented => inside a block/function
        }
        $t = trim($line);
        if ($t === '' || str_starts_with($t, '#')) {
            continue;
        }
        // Skip declarations that do not by themselves make it a runnable script.
        if (preg_match('/^(function\s+)?[A-Za-z_][A-Za-z0-9_-]*\s*\(\s*\)\s*\{/', $t)
            || preg_match('/^(set|shopt|declare|readonly|export|source|\.)\b/', $t)
            || preg_match('/^(fi|done|esac|\}|\{|then|else|elif|do)\b/', $t)) {
            continue;
        }
        $hasTopLevelLogic = true;
        break;
    }

    if ($hasShebang && $hasTopLevelLogic) {
        return 'script';
    }
    if (!$hasShebang) {
        return 'library';
    }
    // Shebang present but no obvious top-level logic detected.
    return 'unknown';
}

/**
 * Overall confidence: a coarse blend reflecting how much structure was found.
 *
 * @param array<string,mixed> $env
 */
function shIntrospectOverallConfidence(array $env): int
{
    $hasFunctions = $env['functions'] !== [];
    $hasModesOrParams = $env['modes'] !== [] || $env['params'] !== [];

    if ($hasFunctions && $hasModesOrParams) {
        return 90;
    }
    if ($hasFunctions || $hasModesOrParams) {
        return 70;
    }
    return 40;
}

/**
 * Render a human-readable grouped summary.
 *
 * @param array<string,mixed> $env
 */
function shIntrospectRenderText(array $env): string
{
    $out = [];
    $out[] = 'sh-introspect: ' . $env['file'];
    $out[] = sprintf(
        'status=%s kind=%s confidence=%d target_executed=%s',
        $env['status'],
        $env['kind'] ?? 'unknown',
        $env['meta']['confidence'],
        $env['meta']['target_executed'] ? 'true' : 'false'
    );
    $risk = $env['risk_summary'] ?? [];
    $out[] = sprintf(
        'Risk: max_risk=%s mutation=%s dynamic_execution=%s unresolved_source=%s',
        $risk['max_risk'] ?? 'unknown',
        !empty($risk['has_mutation']) ? 'yes' : 'no',
        !empty($risk['has_dynamic_execution']) ? 'yes' : 'no',
        !empty($risk['has_unresolved_source']) ? 'yes' : 'no'
    );
    $out[] = '';

    foreach ($env['warnings'] as $w) {
        $out[] = 'WARNING: ' . $w;
    }
    if ($env['warnings'] !== []) {
        $out[] = '';
    }

    $out[] = shIntrospectSection('Functions (' . count($env['functions']) . ')', array_map(
        static fn(array $f): string => sprintf('  %s  (line %d)', $f['name'], $f['line']),
        $env['functions']
    ));

    $out[] = shIntrospectSection('Modes (' . count($env['modes']) . ')', array_map(
        static fn(array $m): string => sprintf(
            '  %s  [%s%s] query_required=%s%s (conf %d)',
            $m['name'],
            $m['family'],
            '',
            $m['query_required'] ? 'yes' : 'no',
            !empty($m['deprecated']) ? ' DEPRECATED' : '',
            $m['confidence']
        ),
        $env['modes']
    ));

    $modeContracts = is_array($env['mode_contracts'] ?? null) ? $env['mode_contracts'] : [];
    $out[] = shIntrospectSection('Mode contracts (' . count($modeContracts) . ')', array_map(
        static function (array $c): string {
            $positionals = is_array($c['positionals'] ?? null) ? $c['positionals'] : [];
            $tokens = array_map(
                static fn(array $p): string => !empty($p['required'])
                    ? (string) $p['name']
                    : '[' . (string) $p['name'] . ']',
                $positionals
            );
            $line = sprintf(
                '  %s  [%s] query_required=%s positionals=%s',
                $c['name'],
                $c['family'] ?? 'unknown',
                !empty($c['query_required']) ? 'yes' : 'no',
                $tokens === [] ? '(none)' : implode(' ', $tokens)
            );
            if (!empty($c['deprecated'])) {
                $replacements = is_array($c['replacements'] ?? null) ? $c['replacements'] : [];
                $line .= ' DEPRECATED'
                    . ($replacements !== [] ? ' -> ' . implode(', ', $replacements) : '');
            }
            return $line;
        },
        $modeContracts
    ));

    $out[] = shIntrospectSection('Params (' . count($env['params']) . ')', array_map(
        static fn(array $p): string => sprintf(
            '  %s%s  takes_value=%s repeatable=%s%s (%s, conf %d)',
            $p['name'],
            $p['aliases'] !== [] ? ' (' . implode(', ', $p['aliases']) . ')' : '',
            $p['takes_value'] ? 'yes' : 'no',
            $p['repeatable'] ? 'yes' : 'no',
            isset($p['value_hint']) ? ' hint=' . $p['value_hint'] : '',
            $p['source'],
            $p['confidence']
        ),
        $env['params']
    ));

    $out[] = shIntrospectSection('Positionals (' . count($env['positionals']) . ')', array_map(
        static fn(array $p): string => sprintf(
            '  %s  required=%s%s',
            $p['name'],
            !empty($p['required']) ? 'yes' : 'no',
            isset($p['default']) ? ' default=' . $p['default'] : ''
        ),
        $env['positionals']
    ));

    $out[] = shIntrospectSection('Case labels (' . count($env['case_labels']) . ')', array_map(
        static fn(array $k): string => sprintf('  %s  (subject %s, line %d)', $k['name'], $k['case_subject'], $k['line']),
        $env['case_labels']
    ));

    $out[] = shIntrospectSection('JSON keys (' . count($env['json_keys']) . ')', array_map(
        static fn(array $k): string => sprintf('  %s  (%s, conf %d)', $k['name'], $k['source'], $k['confidence']),
        $env['json_keys']
    ));

    $jsonPaths = is_array($env['json_paths'] ?? null) ? $env['json_paths'] : [];
    if ($jsonPaths !== []) {
        $out[] = shIntrospectSection('JSON paths (' . count($jsonPaths) . ')', array_map(
            static fn(array $p): string => sprintf('  %s  (conf %d)', $p['path'], $p['confidence']),
            $jsonPaths
        ));
    }

    $outputSchemas = is_array($env['output_schemas'] ?? null) ? $env['output_schemas'] : [];
    if ($outputSchemas !== []) {
        $out[] = shIntrospectSection('Output schemas (' . count($outputSchemas) . ')', array_map(
            static function (array $s): string {
                $modes = is_array($s['modes'] ?? null) ? $s['modes'] : [];
                $modeStr = $modes !== [] ? ' modes=[' . implode(',', $modes) . ']' : '';
                return sprintf('  %s%s  keys=[%s]', $s['name'], $modeStr, implode(',', (array) ($s['keys'] ?? [])));
            },
            $outputSchemas
        ));
    }

    $out[] = shIntrospectSection('Dependencies (' . count($env['dependencies']) . ')', array_map(
        static fn(array $d): string => sprintf(
            '  %s  (%s, %s, conf %d)%s',
            $d['name'],
            $d['source'],
            $d['classification'] ?? 'unknown',
            $d['confidence'],
            isset($d['required_for_modes']) ? ' for=[' . implode(',', (array) $d['required_for_modes']) . ']' : ''
        ),
        $env['dependencies']
    ));

    if ($env['unknown_option_handlers'] !== []) {
        $out[] = shIntrospectSection('Unknown option handlers (' . count($env['unknown_option_handlers']) . ')', array_map(
            static fn(array $u): string => sprintf('  %s  action=%s (line %d)', $u['pattern'], $u['action'], $u['line']),
            $env['unknown_option_handlers']
        ));
    }

    $out[] = shIntrospectSection('Env inputs (' . count($env['env_inputs']) . ')', array_map(
        static fn(array $e): string => sprintf(
            '  %s%s  (line %d)',
            $e['name'],
            isset($e['default']) ? '=' . $e['default'] : '',
            $e['line']
        ),
        $env['env_inputs']
    ));

    $out[] = shIntrospectSection('Sources (' . count($env['sources']) . ')', array_map(
        static fn(array $s): string => sprintf('  %s  (line %d)', $s['target'], $s['line']),
        $env['sources']
    ));

    $out[] = shIntrospectSection('Examples (' . count($env['examples']) . ')', array_map(
        static fn(array $ex): string => '  ' . $ex['text'],
        $env['examples']
    ));

    $commands = is_array($env['commands'] ?? null) ? $env['commands'] : [];
    if ($commands !== []) {
        $out[] = shIntrospectSection('Commands (' . count($commands) . ')', array_map(
            static fn(array $c): string => sprintf(
                '  %s  risk=%s effect=%s kind=%s (line %d)',
                $c['name'],
                $c['risk'],
                $c['effect'],
                $c['kind'] ?? 'invocation',
                $c['line']
            ),
            $commands
        ));
    }

    $riskFindings = is_array($env['risk_findings'] ?? null) ? $env['risk_findings'] : [];
    if ($riskFindings !== []) {
        $out[] = shIntrospectSection('Risk findings (' . count($riskFindings) . ')', array_map(
            static fn(array $f): string => sprintf(
                '  [%s] %s (line %d): %s',
                $f['risk'],
                $f['code'],
                $f['line'],
                $f['detail']
            ),
            $riskFindings
        ));
    }

    return implode("\n", $out) . "\n";
}

/**
 * @param array<int,string> $rows
 */
function shIntrospectSection(string $title, array $rows): string
{
    $block = $title . ':';
    if ($rows === []) {
        return $block . "\n  (none)\n";
    }
    return $block . "\n" . implode("\n", $rows) . "\n";
}
