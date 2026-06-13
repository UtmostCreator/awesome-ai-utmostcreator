<?php

declare(strict_types=1);

/**
 * P4: classify dependencies.
 *
 * Adds, per dependency: `classification` (required | optional | candidate),
 * `category` (base-utility | primary-tool), and `required_for_modes` when the
 * tool is gated to specific modes via an `is_*_mode`/`mode_needs_*` helper or a
 * direct mode-name guard. Existing fields (name/source/confidence) are kept for
 * backward compatibility.
 *
 * @param array<int,array<string,mixed>> $dependencies
 * @param array<int,string> $codeLines
 * @param array<int,array<string,mixed>> $modes
 * @return array<int,array<string,mixed>>
 */
function shIntrospectClassifyDependencies(array $dependencies, array $codeLines, array $modes): array
{
    // Base POSIX utilities that are effectively always present.
    $baseUtils = array_fill_keys([
        'cat', 'sed', 'awk', 'sort', 'head', 'tail', 'grep', 'tr', 'cut',
        'tee', 'find', 'env', 'printf', 'echo', 'dirname', 'basename',
    ], true);

    foreach ($dependencies as &$dep) {
        $name = (string) ($dep['name'] ?? '');
        $source = (string) ($dep['source'] ?? '');
        $confidence = (int) ($dep['confidence'] ?? 0);

        // Category: base utility vs primary tool.
        $dep['category'] = isset($baseUtils[$name]) ? 'base-utility' : 'primary-tool';

        // Classification: a hard command-check / version-probe is required; a
        // loop-listed tool is optional (often a "use if available" set); a bare
        // invocation is a candidate (lower certainty).
        if ($source === 'command-check' || $source === 'version-probe') {
            $dep['classification'] = 'required';
        } elseif ($source === 'tool-loop') {
            $dep['classification'] = 'optional';
        } elseif ($confidence >= 80) {
            $dep['classification'] = 'required';
        } else {
            $dep['classification'] = 'candidate';
        }

        // Mode mapping: if the tool name appears on a line that also guards on a
        // specific mode name, record those modes.
        $forModes = shIntrospectDependencyModes($name, $codeLines, $modes);
        if ($forModes !== []) {
            $dep['required_for_modes'] = $forModes;
        }
    }
    unset($dep);

    return $dependencies;
}

/**
 * Best-effort: modes a dependency is gated to, when a line invoking the tool
 * also references a specific mode name as a whole word.
 *
 * @param array<int,string> $codeLines
 * @param array<int,array<string,mixed>> $modes
 * @return array<int,string>
 */
function shIntrospectDependencyModes(string $tool, array $codeLines, array $modes): array
{
    if ($tool === '' || $modes === []) {
        return [];
    }
    $modeNames = [];
    foreach ($modes as $mode) {
        $name = (string) ($mode['name'] ?? '');
        if ($name !== '') {
            $modeNames[$name] = true;
        }
    }

    $hits = [];
    $toolRe = '/(^|[\s|(){};&`$])' . preg_quote($tool, '/') . '(\s|$)/';
    foreach ($codeLines as $line) {
        if (!preg_match($toolRe, $line)) {
            continue;
        }
        foreach (array_keys($modeNames) as $modeName) {
            // Require the mode token to look like a guard, not arbitrary prose:
            // appear quoted or as a case label / comparison.
            if (preg_match('/["\']?' . preg_quote($modeName, '/') . '["\']?\s*\)/', $line)
                || preg_match('/==\s*["\']?' . preg_quote($modeName, '/') . '["\']?/', $line)) {
                $hits[$modeName] = true;
            }
        }
    }

    if ($hits === []) {
        return [];
    }
    $names = array_keys($hits);
    sort($names, SORT_STRING);
    return $names;
}

/**
 * Detect external tool dependencies from the code.
 *
 * Sources: `command -v X`, `command_exists X`, `require_tool X`, `X --version`,
 * `for tool in a b c`, and obvious top-level invocations of a known external
 * command allowlist. Shell builtins and the script's own functions are excluded.
 *
 * @param array<int,string> $codeLines
 * @param array<int,array<string,mixed>> $functions
 * @return array<int,array<string,mixed>> items { name, source, confidence }
 */
function shIntrospectExtractDependencies(array $codeLines, array $functions): array
{
    $known = [
        'rg', 'git', 'jq', 'fd', 'fdfind', 'ast-grep', 'sg', 'sed', 'awk',
        'sort', 'head', 'tail', 'grep', 'cat', 'find', 'tee', 'curl', 'wget',
    ];
    $knownSet = array_fill_keys($known, true);

    $fnNames = [];
    foreach ($functions as $fn) {
        $fnNames[(string) $fn['name']] = true;
    }

    // name => best confidence + source.
    $byName = [];
    $add = static function (string $name, string $source, int $confidence) use (&$byName, $fnNames): void {
        $name = trim($name);
        if ($name === '' || isset($fnNames[$name])) {
            return;
        }
        if (!isset($byName[$name]) || $confidence > (int) $byName[$name]['confidence']) {
            $byName[$name] = ['name' => $name, 'source' => $source, 'confidence' => $confidence];
        }
    };

    foreach ($codeLines as $line) {
        // command -v X / command_exists X / require_tool X
        if (preg_match_all('/\b(?:command\s+-v|command_exists|require_tool|need_tool|have)\s+["\']?([A-Za-z][A-Za-z0-9_.-]*)/', $line, $m)) {
            foreach ($m[1] as $name) {
                $add($name, 'command-check', 90);
            }
        }
        // for tool in a b c; do
        if (preg_match('/\bfor\s+\w+\s+in\s+(.+?);?\s*do\b/', $line, $fm)) {
            foreach (preg_split('/\s+/', trim($fm[1])) ?: [] as $tok) {
                $tok = trim($tok, "\"' ");
                if (isset($knownSet[$tok])) {
                    $add($tok, 'tool-loop', 85);
                }
            }
        }
        // X --version
        if (preg_match_all('/\b([A-Za-z][A-Za-z0-9_.-]*)\s+--version\b/', $line, $m)) {
            foreach ($m[1] as $name) {
                if (isset($knownSet[$name])) {
                    $add($name, 'version-probe', 80);
                }
            }
        }
        // Obvious top-level invocation of a known external command (word boundary,
        // not part of a longer token, not preceded by `.` or `-`).
        foreach ($known as $tool) {
            $q = preg_quote($tool, '/');
            if (preg_match('/(^|[\s|(){};&`$])' . $q . '(\s|$)/', $line)) {
                // normalize fdfind -> fd alias note kept as its own name
                $add($tool, 'invocation', 70);
            }
        }
    }

    // Deterministic ordering by name.
    ksort($byName, SORT_STRING);
    return array_values($byName);
}
