<?php

declare(strict_types=1);

/**
 * P1: split emitted JSON keys into confirmed (high-confidence) and candidates.
 *
 * Keys whose confidence is below the confirmation threshold (e.g. best-effort
 * code-scan hits) are moved to `json_key_candidates` so the primary json_keys
 * surface only carries trustworthy, envelope-grade keys. Both lists stay sorted
 * by name for deterministic output.
 *
 * @param array<int,array<string,mixed>> $jsonKeys
 * @return array{confirmed:array<int,array<string,mixed>>,candidates:array<int,array<string,mixed>>}
 */
function shIntrospectSplitJsonKeyCandidates(array $jsonKeys): array
{
    $confirmed = [];
    $candidates = [];
    foreach ($jsonKeys as $key) {
        $confidence = (int) ($key['confidence'] ?? 0);
        if ($confidence >= 80) {
            $confirmed[] = $key;
        } else {
            $candidates[] = $key;
        }
    }
    $byName = static function (array $a, array $b): int {
        return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    };
    usort($confirmed, $byName);
    usort($candidates, $byName);
    return ['confirmed' => $confirmed, 'candidates' => $candidates];
}

/**
 * P1: extract JSONPath-style output paths with confidence, e.g.
 *   { "path": "$.schema", "confidence": 90 }
 *   { "path": "$.limits.max_results", "confidence": 80 }
 *   { "path": "$.results[].path", "confidence": 70 }
 *
 * Sources, by descending confidence:
 *  - confirmed top-level keys -> `$.<key>` (90)
 *  - nested jq object blocks `parent: { child: ... }` (multi-line, bare keys)
 *    -> `$.parent.child` (80)
 *  - usage envelope nested groups `parent{child,...}` -> `$.parent.child` (80)
 *  - array-element subfields documented for `key[]` -> `$.key[].field` (70)
 *
 * Parents are constrained to confirmed top-level keys so prose like
 * `results[] of {path}` cannot leak a bogus `$.of.path`. Deterministic by path.
 *
 * @param array<int,string> $codeLines
 * @param array<int,array<string,mixed>> $jsonKeys confirmed top-level keys
 * @return array<int,array<string,mixed>> items { path, confidence, source }
 */
function shIntrospectExtractJsonPaths(array $codeLines, string $usageBlock, array $jsonKeys = []): array
{
    $parentAllow = [];
    $topKeys = [];
    foreach ($jsonKeys as $key) {
        $name = (string) ($key['name'] ?? '');
        if ($name !== '') {
            $parentAllow[$name] = true;
            $topKeys[$name] = (int) ($key['confidence'] ?? 90);
        }
    }

    $byPath = [];
    $add = static function (string $path, int $confidence, string $source) use (&$byPath): void {
        if ($path === '') {
            return;
        }
        $jsonPath = '$.' . $path;
        // Keep the highest-confidence record per path.
        if (!isset($byPath[$jsonPath]) || $confidence > (int) $byPath[$jsonPath]['confidence']) {
            $byPath[$jsonPath] = ['path' => $jsonPath, 'confidence' => $confidence, 'source' => $source];
        }
    };
    // Nested child is accepted only when its parent is a confirmed key.
    $addNested = static function (string $parent, string $child, int $confidence, string $source) use ($add, $parentAllow): void {
        $parent = trim($parent);
        $child = trim(preg_replace('/\[\s*\]$/', '', $child) ?? $child);
        $child = trim($child, " \t[](){}\"'");
        if ($parent === '' || $child === '' || !isset($parentAllow[$parent])) {
            return;
        }
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $child)) {
            return;
        }
        $add($parent . '.' . $child, $confidence, $source);
    };

    // 1) Top-level confirmed keys -> $.key.
    foreach ($topKeys as $name => $conf) {
        $add($name, max(85, $conf), 'top-level-key');
    }

    // 2) jq nested object blocks: `parent: { child: ... ; child2: ... }`,
    //    possibly spanning multiple lines and using bare (unquoted) keys.
    $codeFlat = implode("\n", $codeLines);
    if (preg_match_all('/([A-Za-z_][A-Za-z0-9_]*)\s*:\s*\{([^{}]*)\}/', $codeFlat, $blocks, PREG_SET_ORDER)) {
        foreach ($blocks as $b) {
            $parent = $b[1];
            if (!isset($parentAllow[$parent])) {
                continue;
            }
            // Children are the `child:` keys inside the block.
            if (preg_match_all('/([A-Za-z_][A-Za-z0-9_]*)\s*:/', $b[2], $cm)) {
                foreach ($cm[1] as $child) {
                    $addNested($parent, $child, 80, 'jq-object');
                }
            }
        }
    }

    // 3) Usage envelope nested groups: `parent{child,child2}`.
    $flat = preg_replace('/\s+/', ' ', $usageBlock) ?? $usageBlock;
    if (preg_match_all('/([A-Za-z_][A-Za-z0-9_]*)\s*\{([^{}]*)\}/', $flat, $groups, PREG_SET_ORDER)) {
        foreach ($groups as $g) {
            if (stripos($g[2], 'schema') !== false && stripos($g[2], 'status') !== false) {
                continue; // the top-level envelope object itself
            }
            foreach (explode(',', $g[2]) as $child) {
                $addNested($g[1], $child, 80, 'usage-envelope');
            }
        }
    }

    // 4) Array-element subfields: for a confirmed array key (e.g. results,
    //    matches, symbols), capture documented per-element fields written as
    //    `key[] ... {field/field2/...}` or `key carry field, field2`.
    foreach (array_keys($parentAllow) as $arrayKey) {
        $fields = shIntrospectArrayElementFields($arrayKey, $usageBlock, $codeLines);
        foreach ($fields as $field) {
            $add($arrayKey . '[].' . $field, 70, 'array-element');
        }
    }

    ksort($byPath, SORT_STRING);
    return array_values($byPath);
}

/**
 * Detect per-element fields documented for an array key. Looks for usage rows
 * like `results[] of {path,count}`, `symbols[] (kind/name/path/...)`, or
 * `results carry commit/author/...` and returns the field tokens.
 *
 * @param array<int,string> $codeLines
 * @return array<int,string>
 */
function shIntrospectArrayElementFields(string $key, string $usageBlock, array $codeLines): array
{
    $out = [];
    $seen = [];
    $addField = static function (string $field) use (&$out, &$seen): void {
        $field = trim($field, " \t.,;()[]{}");
        if ($field !== '' && !isset($seen[$field]) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field)) {
            $seen[$field] = true;
            $out[] = $field;
        }
    };

    $q = preg_quote($key, '/');
    foreach (preg_split('/\n/', $usageBlock) ?: [] as $line) {
        // `key[] ... {a,b,c}`  or  `key[] (a/b/c)`  on the same line.
        if (preg_match('/\b' . $q . '\[\][^\n]*?[\(\{]([A-Za-z0-9_,\/ ]+)[\)\}]/', $line, $m)
            || preg_match('/\b' . $q . '\b[^\n]*?\b(?:of|carry|with)\b[^\n]*?[\(\{]?([A-Za-z0-9_]+(?:[\/,][A-Za-z0-9_]+)+)/', $line, $m)) {
            foreach (preg_split('#[,/]#', $m[1]) as $field) {
                $addField($field);
            }
        }
    }

    return $out;
}

/**
 * P0/P1: build the `output_schemas[]` surface.
 *
 * Emits:
 *  - `envelope`: the always-present top-level key set (the binding contract).
 *  - per-family result schemas (e.g. `file-list-result`, `content-search-result`,
 *    `structural-result`) listing the modes they cover and the result-bearing
 *    keys for that family. These are derived from the mode families already
 *    classified, so they stay accurate without per-script hardcoding.
 *
 * Returns an empty array when no envelope is documented (never null).
 *
 * @param array<int,array<string,mixed>> $jsonKeys
 * @param array<int,array<string,mixed>> $modes
 * @param array<string,array{description:string,output_notes:string,display_group:string}> $usageModeDocs
 * @return array<int,array<string,mixed>>
 */
function shIntrospectExtractOutputSchemas(string $usageBlock, array $jsonKeys, array $modes = [], array $usageModeDocs = []): array
{
    if ($jsonKeys === []) {
        return [];
    }
    // Only assert a schema when the usage block actually documents an envelope
    // (mentions both schema and status anchors).
    $flat = preg_replace('/\s+/', ' ', $usageBlock) ?? $usageBlock;
    if (stripos($flat, 'schema') === false || stripos($flat, 'status') === false) {
        return [];
    }
    $keyNames = [];
    foreach ($jsonKeys as $key) {
        $name = (string) ($key['name'] ?? '');
        if ($name !== '') {
            $keyNames[$name] = true;
        }
    }
    if ($keyNames === []) {
        return [];
    }
    $names = array_keys($keyNames);
    sort($names, SORT_STRING);

    $schemas = [[
        'name' => 'envelope',
        'source' => 'usage-envelope',
        'keys' => $names,
        'confidence' => 85,
    ]];

    // Per-family result schemas. Base keys are always present; family keys are
    // the result-bearing keys that exist in the detected envelope.
    $has = static fn(string $k): bool => isset($keyNames[$k]);
    $base = array_values(array_filter(['schema', 'status', 'tool', 'warnings', 'errors'], $has));

    $familySpecs = [
        'file-list' => ['name' => 'file-list-result', 'keys' => ['results']],
        'content' => ['name' => 'content-search-result', 'keys' => ['matches', 'results', 'limits', 'meta']],
        'curated' => ['name' => 'curated-result', 'keys' => ['matches', 'results', 'limits', 'meta']],
        'structural' => ['name' => 'structural-result', 'keys' => ['symbols', 'results', 'meta']],
        'surface' => ['name' => 'surface-result', 'keys' => ['matches', 'results', 'limits', 'meta']],
    ];

    // Group mode names by family.
    $modesByFamily = [];
    foreach ($modes as $mode) {
        $family = (string) ($mode['family'] ?? '');
        $name = (string) ($mode['name'] ?? '');
        if ($family !== '' && $name !== '' && empty($mode['deprecated'])) {
            $modesByFamily[$family][$name] = true;
        }
    }

    foreach ($familySpecs as $family => $spec) {
        if (!isset($modesByFamily[$family])) {
            continue;
        }
        $familyKeys = array_values(array_filter($spec['keys'], $has));
        if ($familyKeys === []) {
            continue;
        }
        $modeNames = array_keys($modesByFamily[$family]);
        sort($modeNames, SORT_STRING);
        $schemaKeys = array_values(array_unique(array_merge($base, $familyKeys)));
        sort($schemaKeys, SORT_STRING);
        $schemas[] = [
            'name' => $spec['name'],
            'source' => 'mode-family',
            'modes' => $modeNames,
            'keys' => $schemaKeys,
            'confidence' => 75,
        ];
    }

    // Per-mode result schemas (diff-result, history-result, symbols-result,
    // todo-result, unsafe-patterns-result, doctor-result). Each is emitted ONLY
    // when the mode is detected AND the usage block documents its per-element
    // fields (so nothing is invented). The per-element fields come from the
    // mode's `output_notes` ("results carry a/b/c") or a `key[] (a/b/c)` /
    // `key + b` style description.
    $modeNameSet = [];
    foreach ($modes as $mode) {
        $n = (string) ($mode['name'] ?? '');
        if ($n !== '' && empty($mode['deprecated'])) {
            $modeNameSet[$n] = true;
        }
    }
    foreach (shIntrospectModeResultSchemas($modeNameSet, $usageModeDocs, $base, $has) as $schema) {
        $schemas[] = $schema;
    }

    return $schemas;
}

/**
 * Build per-mode result schemas from documented per-element fields. For each
 * supported mode we know which top-level result-bearing key it emits (e.g. diff
 * -> results[], symbols -> symbols[]); the element subfields are parsed from the
 * mode's documentation. A schema is produced only when the mode exists and at
 * least one documented field is found. Output is sorted by schema name.
 *
 * @param array<string,bool> $modeNameSet
 * @param array<string,array{description:string,output_notes:string,display_group:string}> $usageModeDocs
 * @param array<int,string> $base base envelope keys present in the envelope
 * @param callable(string):bool $has envelope-key presence test
 * @return array<int,array<string,mixed>>
 */
function shIntrospectModeResultSchemas(array $modeNameSet, array $usageModeDocs, array $base, callable $has): array
{
    // mode => the top-level result key it populates.
    $resultKeyFor = [
        'diff' => 'results',
        'history' => 'results',
        'symbols' => 'symbols',
        'class' => 'symbols',
        'todo' => 'results',
        'unsafe-patterns' => 'results',
        'doctor' => null, // doctor emits top-level diagnostic keys, not an array
    ];

    $out = [];
    foreach ($resultKeyFor as $mode => $resultKey) {
        if (!isset($modeNameSet[$mode])) {
            continue;
        }
        $doc = $usageModeDocs[$mode] ?? [];
        $desc = (string) ($doc['description'] ?? '');
        $notes = (string) ($doc['output_notes'] ?? '');
        $fields = shIntrospectDocumentedElementFields($desc, $notes);
        if ($fields === []) {
            continue;
        }

        $keys = $base;
        if ($resultKey !== null && $has($resultKey)) {
            $keys[] = $resultKey;
        }
        $keys = array_values(array_unique($keys));
        sort($keys, SORT_STRING);

        $entry = [
            'name' => $mode . '-result',
            'source' => 'mode-doc',
            'modes' => [$mode],
            'keys' => $keys,
            'confidence' => 70,
        ];
        if ($resultKey !== null) {
            $entry['element_key'] = $resultKey;
        }
        $entry['element_fields'] = $fields;
        $out[$entry['name']] = $entry;
    }

    ksort($out, SORT_STRING);
    return array_values($out);
}

/**
 * Extract documented per-element field tokens from a mode description and/or its
 * output_notes. Recognises:
 *   - "results carry a/b/c"  /  "results carry a, b, c"
 *   - "symbols[] (a/b/c)"  /  "{a,b,c}"
 *   - "report a + b + c"  /  "a + b"
 * Returns the field tokens in documented order, de-duplicated. Only bareword
 * identifier tokens are kept.
 *
 * @return array<int,string>
 */
function shIntrospectDocumentedElementFields(string $description, string $notes): array
{
    $sources = [];
    if ($notes !== '') {
        $sources[] = $notes;
    }
    if ($description !== '') {
        $sources[] = $description;
    }

    $out = [];
    $seen = [];
    $addRun = static function (string $run) use (&$out, &$seen): void {
        foreach (preg_split('#[\s,/+]+#', trim($run)) ?: [] as $tok) {
            $tok = trim($tok, " \t.,;:()[]{}");
            if ($tok !== '' && preg_match('/^[a-z_][a-z0-9_]*$/i', $tok) && !isset($seen[$tok])) {
                $seen[$tok] = true;
                $out[] = $tok;
            }
        }
    };

    // For a `+`-joined run, each segment may be a short phrase ("tool
    // availability + root + git_available"); keep the LAST identifier of each
    // segment as the field name.
    $addPlusRun = static function (string $run) use (&$out, &$seen): void {
        foreach (explode('+', $run) as $segment) {
            if (preg_match_all('/[a-z_][a-z0-9_]*/i', trim($segment), $tm) && $tm[0] !== []) {
                $tok = (string) end($tm[0]);
                if ($tok !== '' && !isset($seen[$tok])) {
                    $seen[$tok] = true;
                    $out[] = $tok;
                }
            }
        }
    };

    foreach ($sources as $text) {
        // "carry|carries|with|of|emits a/b/c" run (slash-, comma- or +-joined).
        if (preg_match('/\b(?:carry|carries|with|of|emits)\s+([a-z_][a-z0-9_]*(?:\s*[\/,+]\s*[a-z_][a-z0-9_]*)+)/i', $text, $m)) {
            $addRun($m[1]);
        }
        // "(a/b/c)" or "{a,b,c}" group.
        if ($out === [] && preg_match('/[\(\{]\s*([a-z_][a-z0-9_]*(?:\s*[\/,]\s*[a-z_][a-z0-9_]*)+)\s*[\)\}]/i', $text, $m)) {
            $addRun($m[1]);
        }
        // "report X + Y + Z" run (segments may be phrases; last identifier wins).
        if ($out === [] && preg_match('/\breport\s+(.+?[a-z0-9_]\s*\+\s*[a-z0-9_].+)$/i', $text, $m)) {
            $addPlusRun($m[1]);
        }
        if ($out !== []) {
            break; // first source that yields fields wins (output_notes preferred)
        }
    }

    return $out;
}

/**
 * Detect the JSON object keys the target script emits.
 *
 * Primary, reliable source: the usage heredoc envelope description line, e.g.
 *   {schema,status,tool,query,mode,matches[],results[],warnings[],errors[],limits,meta}
 * Secondary, best-effort: code lines with jq/printf object-key patterns such as
 *   "schema":  'status'  --arg schema  schema:
 *
 * @param array<int,string> $codeLines
 * @param array<int,array<string,mixed>> $functions
 * @return array<int,array<string,mixed>> items { name, source, confidence }
 */
function shIntrospectExtractJsonKeys(array $codeLines, string $usageBlock, array $functions): array
{
    // name => best record (highest confidence wins on merge).
    $byName = [];

    $add = static function (string $name, string $source, int $confidence) use (&$byName): void {
        $name = trim($name);
        if ($name === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            return;
        }
        // Drop single-character names from the best-effort code scan: those are
        // almost always jq/printf bind variables (`m`, `n`, `p`, `c`), not real
        // emitted JSON keys. The reliable usage-envelope source is kept as-is.
        if ($source === 'code' && strlen($name) < 2) {
            return;
        }
        if (!isset($byName[$name]) || $confidence > (int) $byName[$name]['confidence']) {
            $byName[$name] = [
                'name' => $name,
                'source' => $source,
                'confidence' => $confidence,
            ];
        }
    };

    // 1) Usage-envelope: find a `{...}` block listing the envelope keys. The
    //    description may wrap across lines, so collapse the whole usage block
    //    and capture the first brace group that contains a known anchor key.
    $flat = preg_replace('/\s+/', ' ', $usageBlock) ?? $usageBlock;
    if (preg_match_all('/\{([^{}]*)\}/', $flat, $groups)) {
        foreach ($groups[1] as $group) {
            // Only treat it as the envelope when it mentions schema+status.
            if (stripos($group, 'schema') === false || stripos($group, 'status') === false) {
                continue;
            }
            // Tokenise on commas; strip [] suffixes and surrounding noise.
            foreach (explode(',', $group) as $token) {
                $token = trim($token);
                $token = preg_replace('/\[\s*\]$/', '', $token) ?? $token;
                // Drop nested key descriptions like `limits{max_results}` -> limits.
                $token = preg_replace('/\{.*$/', '', $token) ?? $token;
                $token = trim($token, " \t[](){}");
                $add($token, 'usage-envelope', 90);
            }
            break;
        }
    }

    // 2) Best-effort code scan for jq/printf object-key construction.
    $fnNames = [];
    foreach ($functions as $fn) {
        $fnNames[(string) $fn['name']] = true;
    }
    foreach ($codeLines as $line) {
        // "key":  and  'key':  (jq/printf object literals)
        if (preg_match_all('/["\']([A-Za-z_][A-Za-z0-9_]*)["\']\s*:/', $line, $m)) {
            foreach ($m[1] as $name) {
                $add($name, 'code', 60);
            }
        }
        // --arg NAME / --argjson NAME (jq -n argument binding)
        if (preg_match_all('/--arg(?:json)?\s+([A-Za-z_][A-Za-z0-9_]*)\b/', $line, $m)) {
            foreach ($m[1] as $name) {
                if (!isset($fnNames[$name])) {
                    $add($name, 'code', 55);
                }
            }
        }
    }

    // Deterministic ordering: by name.
    ksort($byName, SORT_STRING);
    return array_values($byName);
}
