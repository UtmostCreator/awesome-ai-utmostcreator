<?php

declare(strict_types=1);

/**
 * Target wrap width for aligned description columns in the help summary. Long
 * mode/flag descriptions wrap onto continuation lines indented to align under
 * the description column so the output stays readable in an ~80-column terminal.
 */
const SH_INTROSPECT_HELP_WRAP_WIDTH = 78;

/**
 * Word-wrap a description into one or more lines. The first line is prefixed by
 * `$firstPrefix` (the aligned name+type columns); continuation lines are padded
 * to `$contIndent` spaces so they line up under the description column. Returns
 * the prefixed first line plus any continuation lines, joined with "\n".
 *
 * Wrapping is by whole words at <= ($width - $contIndent) description columns;
 * a single word longer than the available width is emitted unbroken (never
 * split mid-token, so flag globs/paths stay intact). Continuation lines never
 * begin with a flag-like token, preserving the help param parser contract.
 */
function shIntrospectHelpWrapDescription(
    string $firstPrefix,
    string $description,
    int $contIndent,
    int $width = SH_INTROSPECT_HELP_WRAP_WIDTH
): string {
    $description = trim($description);
    if ($description === '') {
        return rtrim($firstPrefix);
    }

    $avail = max(1, $width - $contIndent);
    $words = preg_split('/\s+/', $description) ?: [$description];

    $lines = [];
    $current = '';
    foreach ($words as $word) {
        if ($current === '') {
            $current = $word;
            continue;
        }
        if (strlen($current) + 1 + strlen($word) <= $avail) {
            $current .= ' ' . $word;
            continue;
        }
        $lines[] = $current;
        $current = $word;
    }
    if ($current !== '') {
        $lines[] = $current;
    }

    $contPad = str_repeat(' ', $contIndent);
    $out = [rtrim($firstPrefix . $lines[0])];
    for ($i = 1, $n = count($lines); $i < $n; $i++) {
        $out[] = rtrim($contPad . $lines[$i]);
    }

    return implode("\n", $out);
}

/**
 * Render a CONCISE, human-friendly contract summary derived entirely from the
 * parsed envelope arrays. Intended for embedding in another script's --help.
 *
 * Distinct from shIntrospectRenderText (verbose) and the JSON envelope. Output
 * is deterministic (stable sorting, no timestamps) and omits empty sections.
 *
 * @param array<string,mixed> $env
 */
function shIntrospectRenderHelpSummary(array $env): string
{
    $blocks = [];

    // Repo-relative path for the title and the contract commands.
    $path = is_array($env['path'] ?? null) ? $env['path'] : [];
    $hintPath = (string) ($path['relative'] ?? ($env['file'] ?? ''));
    if ($hintPath === '') {
        $hintPath = '<file>';
    }
    $base = basename($hintPath !== '<file>' ? $hintPath : (string) ($env['file'] ?? 'script'));

    // Title + optional summary (from help.summary, parsed from the usage block).
    $help = is_array($env['help'] ?? null) ? $env['help'] : [];
    $summary = (string) ($help['summary'] ?? '');
    $title = $base;
    if ($summary !== '') {
        $title .= ' — ' . $summary;
    }
    $blocks[] = $title;

    // Usage + JSON-output env note (only the ones we could derive).
    $headerLines = [];
    if (($help['usage'] ?? '') !== '') {
        $headerLines[] = 'Usage: ' . $help['usage'];
    } else {
        $positionalsLine = shIntrospectHelpPositionalsLine(
            is_array($env['positionals'] ?? null) ? $env['positionals'] : []
        );
        if ($positionalsLine !== '') {
            // Reuse the positionals tokens as a usage hint when no usage line.
            $headerLines[] = 'Usage: ' . $base . ' ' . trim(substr($positionalsLine, strlen('Positionals:')));
        }
    }
    if (($help['json_output_env'] ?? '') !== '') {
        $headerLines[] = 'JSON output: ' . $help['json_output_env'];
    }
    if ($headerLines !== []) {
        $blocks[] = implode("\n", $headerLines);
    }

    $warnings = is_array($env['warnings'] ?? null) ? $env['warnings'] : [];
    if ($warnings !== []) {
        $blocks[] = implode("\n", array_map(
            static fn(string $warning): string => 'WARNING: ' . $warning,
            array_map('strval', $warnings)
        ));
    }

    // Positionals (kept as an explicit line for the contract surface).
    $positionalsLine = shIntrospectHelpPositionalsLine(
        is_array($env['positionals'] ?? null) ? $env['positionals'] : []
    );
    if ($positionalsLine !== '') {
        $blocks[] = $positionalsLine;
    }

    // Status vocabulary (documented envelope statuses).
    $statusValues = is_array($env['status_values'] ?? null) ? $env['status_values'] : [];
    if ($statusValues !== []) {
        $blocks[] = 'Status values: ' . implode(' ', $statusValues);
    }

    $modesBlock = shIntrospectHelpModesBlock(is_array($env['modes'] ?? null) ? $env['modes'] : []);
    if ($modesBlock !== '') {
        $blocks[] = $modesBlock;
    }

    // Deprecated modes + their replacements, from mode_contracts.
    $deprecatedBlock = shIntrospectHelpDeprecatedBlock(
        is_array($env['mode_contracts'] ?? null) ? $env['mode_contracts'] : []
    );
    if ($deprecatedBlock !== '') {
        $blocks[] = $deprecatedBlock;
    }

    $unknownHandlers = is_array($env['unknown_option_handlers'] ?? null)
        ? $env['unknown_option_handlers']
        : [];
    $paramsBlock = shIntrospectHelpParamsBlock(
        is_array($env['params'] ?? null) ? $env['params'] : [],
        $unknownHandlers
    );
    if ($paramsBlock !== '') {
        $blocks[] = $paramsBlock;
    }

    $envLine = shIntrospectHelpEnvLine(is_array($env['env_inputs'] ?? null) ? $env['env_inputs'] : []);
    if ($envLine !== '') {
        $blocks[] = $envLine;
    }

    $needsLine = shIntrospectHelpNeedsLine(
        is_array($env['dependencies'] ?? null) ? $env['dependencies'] : []
    );
    if ($needsLine !== '') {
        $blocks[] = $needsLine;
    }

    // Examples (verbatim from the usage Examples: section).
    $examplesBlock = shIntrospectHelpExamplesBlock(
        is_array($env['examples'] ?? null) ? $env['examples'] : []
    );
    if ($examplesBlock !== '') {
        $blocks[] = $examplesBlock;
    }

    // See also: actionable next-step pointers derived from contract evidence
    // (never invented). The doctor line is gated on an actual `doctor` mode;
    // the --introspect line is always available on the wrapper.
    $modeNameSet = [];
    foreach ((is_array($env['modes'] ?? null) ? $env['modes'] : []) as $mode) {
        $modeName = (string) ($mode['name'] ?? '');
        if ($modeName !== '') {
            $modeNameSet[$modeName] = true;
        }
    }
    $seeAlsoLines = [];
    if (isset($modeNameSet['doctor'])) {
        $seeAlsoLines[] = '  ' . $base . ' doctor          runtime diagnostics (missing tools / root / git state)';
    }
    $seeAlsoLines[] = '  bash ' . $hintPath . ' --introspect   machine-readable contract (modes, flags, output schema)';
    if ($seeAlsoLines !== []) {
        $blocks[] = 'See also:' . "\n" . implode("\n", $seeAlsoLines);
    }

    // Machine + full contract pointers. `--introspect` is the script's own raw
    // JSON entrypoint; the full php command is the canonical generator.
    $contractLines = [
        'Machine contract: bash ' . $hintPath . ' --introspect',
        'Full contract: AI_OUTPUT=json php tools/ai/sh-introspect.php ' . $hintPath,
    ];
    $blocks[] = implode("\n", $contractLines);

    return implode("\n\n", $blocks) . "\n";
}

/**
 * Build the "Deprecated modes:" block listing each deprecated mode and its
 * documented replacements (`name -> repl1, repl2`). Returns '' when no mode
 * contract is marked deprecated.
 *
 * @param array<int,array<string,mixed>> $modeContracts
 */
function shIntrospectHelpDeprecatedBlock(array $modeContracts): string
{
    $rows = [];
    foreach ($modeContracts as $contract) {
        if (empty($contract['deprecated'])) {
            continue;
        }
        $name = (string) ($contract['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $repl = is_array($contract['replacements'] ?? null) ? $contract['replacements'] : [];
        $rows[$name] = $repl;
    }
    if ($rows === []) {
        return '';
    }
    ksort($rows, SORT_STRING);

    $nameWidth = 0;
    foreach (array_keys($rows) as $name) {
        $nameWidth = max($nameWidth, strlen($name));
    }

    $lines = ['Deprecated modes:'];
    foreach ($rows as $name => $repl) {
        $suffix = $repl !== [] ? ' -> ' . implode(', ', $repl) : ' (no replacement documented)';
        $lines[] = '  ' . str_pad($name, $nameWidth) . $suffix;
    }
    return implode("\n", $lines);
}

/**
 * Build the "Examples:" block from the parsed usage examples (verbatim, in
 * source order). Returns '' when there are no examples.
 *
 * @param array<int,array<string,mixed>> $examples
 */
function shIntrospectHelpExamplesBlock(array $examples): string
{
    $lines = ['Examples:'];
    $count = 0;
    foreach ($examples as $ex) {
        $text = (string) ($ex['text'] ?? '');
        if ($text === '') {
            continue;
        }
        $lines[] = '  ' . $text;
        $count++;
    }
    if ($count === 0) {
        return '';
    }
    return implode("\n", $lines);
}

/**
 * Build the grouped "Modes:" block. Groups by family with deterministic order
 * and sorted mode names; deprecated modes go on a separate `deprecated:` line.
 * Returns '' when there are no modes.
 *
 * @param array<int,array<string,mixed>> $modes
 */
function shIntrospectHelpModesBlock(array $modes): string
{
    if ($modes === []) {
        return '';
    }

    // group => [name => description]; deprecated names listed apart. The
    // grouping key prefers the human-facing `display_group` (e.g. git-aware,
    // structural, surface) and falls back to the machine `family`. This keeps
    // the help readable without altering machine family logic.
    $byFamily = [];
    $familyHasQuery = [];
    $deprecated = [];

    foreach ($modes as $mode) {
        $name = (string) ($mode['name'] ?? '');
        if ($name === '') {
            continue;
        }
        if (!empty($mode['deprecated'])) {
            $deprecated[$name] = true;
            continue;
        }
        $family = (string) ($mode['display_group'] ?? '');
        if ($family === '') {
            $family = (string) ($mode['family'] ?? 'unknown');
        }
        $byFamily[$family][$name] = (string) ($mode['description'] ?? '');
        if (!empty($mode['query_required'])) {
            $familyHasQuery[$family] = true;
        } elseif (!isset($familyHasQuery[$family])) {
            $familyHasQuery[$family] = false;
        }
    }

    // Deterministic group order; unknown groups appended sorted.
    $preferredOrder = ['content', 'surface', 'file-list', 'git-aware', 'curated', 'structural', 'other'];
    $orderedFamilies = [];
    foreach ($preferredOrder as $fam) {
        if (isset($byFamily[$fam])) {
            $orderedFamilies[] = $fam;
        }
    }
    $remaining = array_diff(array_keys($byFamily), $preferredOrder);
    sort($remaining, SORT_STRING);
    foreach ($remaining as $fam) {
        $orderedFamilies[] = $fam;
    }

    // Curated uses an explicit "(no query)" annotation per the contract.
    $annotation = static function (string $family) use ($familyHasQuery): string {
        if (!empty($familyHasQuery[$family])) {
            return ' (query required)';
        }
        if ($family === 'curated') {
            return ' (no query)';
        }
        return '';
    };

    // Friendly, task-oriented sub-heading labels for the descriptive layout.
    // Applied to the HEADING TEXT ONLY; the raw `$family` key still drives
    // sorting, grouping, and data. Unmapped keys fall back to the raw key so no
    // group is ever lost. file-list and git-aware intentionally share a label
    // but remain two separate sub-headings.
    $friendlyLabel = static function (string $family): string {
        $labels = [
            'content'    => 'Search repository content',
            'surface'    => 'Search specific surfaces',
            'file-list'  => 'Inspect Git state',
            'git-aware'  => 'Inspect Git state',
            'curated'    => 'Find maintenance risks',
            'structural' => 'Inspect code structure',
            'other'      => 'Diagnose',
        ];
        return $labels[$family] ?? $family;
    };

    // Whether any mode anywhere has a description; if not, fall back to the
    // compact one-line-per-family layout to keep things tight.
    $anyDescription = false;
    foreach ($byFamily as $names) {
        foreach ($names as $desc) {
            if ($desc !== '') {
                $anyDescription = true;
                break 2;
            }
        }
    }

    if (!$anyDescription) {
        // Compact layout: family label + space-joined sorted mode names.
        $rows = [];
        foreach ($orderedFamilies as $family) {
            $names = array_keys($byFamily[$family]);
            sort($names, SORT_STRING);
            $rows[] = ['label' => $family . $annotation($family) . ':', 'names' => implode(' ', $names)];
        }
        if ($deprecated !== []) {
            $names = array_keys($deprecated);
            sort($names, SORT_STRING);
            $rows[] = ['label' => 'deprecated:', 'names' => implode(' ', $names)];
        }
        $labelWidth = 0;
        foreach ($rows as $row) {
            $labelWidth = max($labelWidth, strlen($row['label']));
        }
        $lines = ['Modes:'];
        foreach ($rows as $row) {
            $lines[] = '  ' . str_pad($row['label'], $labelWidth) . ' ' . $row['names'];
        }
        return implode("\n", $lines);
    }

    // Descriptive layout: a sub-heading per family, then one mode per line with
    // its description aligned in a column. Name column width is computed across
    // all families for consistent alignment.
    $nameWidth = 0;
    foreach ($byFamily as $names) {
        foreach (array_keys($names) as $n) {
            $nameWidth = max($nameWidth, strlen($n));
        }
    }
    foreach (array_keys($deprecated) as $n) {
        $nameWidth = max($nameWidth, strlen($n));
    }

    $lines = ['Modes:'];
    foreach ($orderedFamilies as $family) {
        $lines[] = '  ' . $friendlyLabel($family) . $annotation($family) . ':';
        $names = array_keys($byFamily[$family]);
        sort($names, SORT_STRING);
        foreach ($names as $n) {
            $desc = $byFamily[$family][$n];
            if ($desc !== '') {
                $prefix = '    ' . str_pad($n, $nameWidth) . '  ';
                $lines[] = shIntrospectHelpWrapDescription($prefix, $desc, strlen($prefix));
            } else {
                $lines[] = '    ' . $n;
            }
        }
    }
    if ($deprecated !== []) {
        $names = array_keys($deprecated);
        sort($names, SORT_STRING);
        $lines[] = '  deprecated:';
        $lines[] = '    ' . implode(' ', $names) . '  (see Deprecated modes below)';
    }

    return implode("\n", $lines);
}

/**
 * Build the single "Positionals:" line. Required tokens are bare; optional
 * tokens are bracketed. Returns '' when there are no positionals.
 *
 * @param array<int,array<string,mixed>> $positionals
 */
function shIntrospectHelpPositionalsLine(array $positionals): string
{
    if ($positionals === []) {
        return '';
    }

    $tokens = [];
    foreach ($positionals as $pos) {
        $name = (string) ($pos['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $tokens[] = !empty($pos['required']) ? $name : '[' . $name . ']';
    }

    if ($tokens === []) {
        return '';
    }

    return 'Positionals: ' . implode(' ', $tokens);
}

/**
 * Build the aligned "Flags:" block. Each line shows the primary flag plus any
 * aliases joined with ` | `, and a type column (`flag` / `value`, plus `+` and
 * a `(repeatable)` note for repeatable value flags). Flags appearing in the
 * unknown-option handlers (e.g. `--*`) are excluded. Sorted by primary name.
 * Returns '' when there are no printable params.
 *
 * @param array<int,array<string,mixed>> $params
 * @param array<int,array<string,mixed>> $unknownHandlers
 */
function shIntrospectHelpParamsBlock(array $params, array $unknownHandlers): string
{
    // Exclude unknown-option fallback patterns (e.g. --*, -*, *).
    $excluded = [];
    foreach ($unknownHandlers as $handler) {
        $pattern = (string) ($handler['pattern'] ?? '');
        if ($pattern !== '') {
            $excluded[$pattern] = true;
        }
    }

    // name => row, keyed by primary name for de-dup + sorting. `group` is the
    // documented flag group (default 'misc' when undocumented).
    $rows = [];
    $anyGroup = false;
    foreach ($params as $param) {
        $name = (string) ($param['name'] ?? '');
        if ($name === '' || isset($excluded[$name])) {
            continue;
        }

        $aliases = is_array($param['aliases'] ?? null) ? $param['aliases'] : [];
        $aliases = array_values(array_filter(
            array_map(static fn($a): string => (string) $a, $aliases),
            static fn(string $a): bool => $a !== '' && !isset($excluded[$a])
        ));

        $takesValue = !empty($param['takes_value']);
        $repeatable = !empty($param['repeatable']);

        // Render the value placeholder next to the primary name when the
        // contract documents one (e.g. "--glob PATTERN", "--base REF"). This
        // follows the GNU/argparse convention and makes the flag self-
        // documenting. The hint attaches to the primary form only to avoid
        // repeating it on every alias.
        $valueHint = $takesValue ? trim((string) ($param['value_hint'] ?? '')) : '';
        $display = $valueHint !== '' ? $name . ' ' . $valueHint : $name;
        if ($aliases !== []) {
            $display .= ' | ' . implode(' | ', $aliases);
        }

        if ($takesValue) {
            $type = 'value';
            if ($repeatable) {
                $type .= '+';
            }
        } else {
            $type = 'flag';
        }
        $note = ($takesValue && $repeatable) ? '(repeatable)' : '';
        $group = (string) ($param['group'] ?? '');
        if ($group !== '') {
            $anyGroup = true;
        }

        $rows[$name] = [
            'display' => $display,
            'type' => $type,
            'note' => $note,
            'group' => $group !== '' ? $group : 'misc',
            'description' => (string) ($param['description'] ?? ''),
        ];
    }

    if ($rows === []) {
        return '';
    }

    ksort($rows, SORT_STRING);

    // Column widths computed across all printed rows.
    $nameWidth = 0;
    $typeWidth = 0;
    foreach ($rows as $row) {
        $nameWidth = max($nameWidth, strlen($row['display']));
        $typeWidth = max($typeWidth, strlen($row['type']));
    }

    $renderRow = static function (array $row, string $indent) use ($nameWidth, $typeWidth): string {
        $prefix = $indent . str_pad($row['display'], $nameWidth) . '  ' . str_pad($row['type'], $typeWidth);
        // Suppress the redundant "(repeatable)" note when the description already
        // conveys repeatability (the `+` type already signals it too).
        $note = $row['note'];
        if ($note !== '' && stripos($row['description'], 'repeatable') !== false) {
            $note = '';
        }
        $tail = trim(($note !== '' ? $note . ' ' : '') . $row['description']);
        if ($tail === '') {
            return rtrim($prefix);
        }
        // Wrap long descriptions; continuation lines align under the description
        // column (after name + 2 + type + 2 spaces).
        $prefix .= '  ';
        return shIntrospectHelpWrapDescription($prefix, $tail, strlen($prefix));
    };

    // When no group metadata exists, keep the flat (legacy) layout.
    if (!$anyGroup) {
        $lines = ['Flags:'];
        foreach ($rows as $row) {
            $lines[] = $renderRow($row, '  ');
        }
        return implode("\n", $lines);
    }

    // Grouped layout: deterministic group order, each group a sub-heading.
    $groupOrder = ['pattern', 'scope', 'ignore', 'context', 'output', 'bounds', 'git', 'structural', 'misc'];
    $byGroup = [];
    foreach ($rows as $name => $row) {
        $byGroup[$row['group']][$name] = $row;
    }
    $orderedGroups = [];
    foreach ($groupOrder as $g) {
        if (isset($byGroup[$g])) {
            $orderedGroups[] = $g;
        }
    }
    foreach (array_diff(array_keys($byGroup), $groupOrder) as $g) {
        $orderedGroups[] = $g;
    }

    $lines = ['Flags:'];
    foreach ($orderedGroups as $group) {
        $lines[] = '  ' . $group . ':';
        $groupRows = $byGroup[$group];
        ksort($groupRows, SORT_STRING);
        foreach ($groupRows as $row) {
            $lines[] = $renderRow($row, '    ');
        }
    }

    return implode("\n", $lines);
}

/**
 * Build the single "Env:" line: space-joined, sorted env-input names.
 * Returns '' when there are no env inputs.
 *
 * @param array<int,array<string,mixed>> $envInputs
 */
function shIntrospectHelpEnvLine(array $envInputs): string
{
    $names = [];
    foreach ($envInputs as $input) {
        $name = (string) ($input['name'] ?? '');
        if ($name !== '') {
            $names[$name] = true;
        }
    }
    if ($names === []) {
        return '';
    }
    $names = array_keys($names);
    sort($names, SORT_STRING);
    return 'Env: ' . implode(' ', $names);
}

/**
 * Build the "Tools:" block, grouping detected dependencies by their classified
 * `category` so the help does not imply every mode needs every tool. Primary
 * tools (rg/fd/git/jq/ast-grep ...) and base utilities (awk/cat/grep ...) are
 * listed on separate lines, plus a pointer to the per-mode tool contract.
 *
 * Falls back to a single sorted line when no category metadata is present.
 * Returns '' when there are no dependencies.
 *
 * @param array<int,array<string,mixed>> $dependencies
 */
function shIntrospectHelpNeedsLine(array $dependencies): string
{
    $primary = [];
    $base = [];
    $all = [];
    $anyCategory = false;
    foreach ($dependencies as $dep) {
        $name = (string) ($dep['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $all[$name] = true;
        $category = (string) ($dep['category'] ?? '');
        if ($category !== '') {
            $anyCategory = true;
        }
        if ($category === 'base-utility') {
            $base[$name] = true;
        } else {
            $primary[$name] = true;
        }
    }
    if ($all === []) {
        return '';
    }

    // No category metadata: keep the legacy single-line form.
    if (!$anyCategory) {
        $names = array_keys($all);
        sort($names, SORT_STRING);
        return 'Tools: ' . implode(' ', $names);
    }

    $primaryNames = array_keys($primary);
    $baseNames = array_keys($base);
    sort($primaryNames, SORT_STRING);
    sort($baseNames, SORT_STRING);

    $lines = ['Tools:'];
    if ($primaryNames !== []) {
        $lines[] = '  primary: ' . implode(' ', $primaryNames);
    }
    if ($baseNames !== []) {
        $lines[] = '  base utilities: ' . implode(' ', $baseNames);
    }
    $lines[] = '  mode-specific tools: see mode contract via --introspect';

    return implode("\n", $lines);
}
