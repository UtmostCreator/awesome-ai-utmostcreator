<?php

declare(strict_types=1);

/**
 * sh-introspect.php — universal shell-script introspector (static parser).
 *
 * Statically parses a Bash script and reports its callable surface:
 * functions, modes, per-mode call contracts, flags/params, positionals,
 * internal case labels, emitted JSON keys, environment inputs, sourced files,
 * dependencies, unknown-option handlers, side effects, a coarse risk summary,
 * and usage examples. The target script is NEVER executed; this is a text-only
 * parse (`meta.target_executed` is always false).
 *
 * Usage:
 *   php tools/ai/sh-introspect.php FILE.sh
 *       Human-readable grouped text (default).
 *
 *   AI_OUTPUT=json php tools/ai/sh-introspect.php FILE.sh
 *   php tools/ai/sh-introspect.php --format=json FILE.sh
 *       JSON envelope (schema ai.sh-introspect/v1).
 *
 *   php tools/ai/sh-introspect.php --format=help FILE.sh
 *       Compact, human-friendly contract summary (modes + param:type lines)
 *       suitable for embedding in another script's --help.
 *
 *   php tools/ai/sh-introspect.php --help | -h
 *       Usage text, exit 0.
 *
 * Exit codes:
 *   0 - parsed successfully (status=ok)
 *   2 - path/validation error (status=error)
 *
 * Style mirrors other standalone tool scripts in tools/ai/ (top-level
 * functions, no classes; envelope rendered with json_encode + the
 * JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES flags).
 */

const SH_INTROSPECT_SCHEMA = 'ai.sh-introspect/v1';
const SH_INTROSPECT_INDEX_SCHEMA = 'ai.sh-introspect-index/v1';
const SH_INTROSPECT_TOOL = 'sh-introspect';

exit(shIntrospectMain($argv));

/**
 * @param array<int,string> $argv
 */
function shIntrospectMain(array $argv): int
{
    $forceJson = false;
    $helpSummary = false;
    $all = false;
    $strictRisk = false;
    $path = null;
    $expectFormat = false; // true after a bare `--format` awaiting its value

    foreach (array_slice($argv, 1) as $arg) {
        // Value for a preceding bare `--format` (space-separated form).
        if ($expectFormat) {
            $expectFormat = false;
            if ($arg === 'json') {
                $forceJson = true;
                continue;
            }
            if ($arg === 'help') {
                $helpSummary = true;
                continue;
            }
            $jsonMode = getenv('AI_OUTPUT') === 'json';
            return shIntrospectFail($jsonMode, '', "unknown --format value: {$arg} (expected json or help)");
        }
        if ($arg === '--help' || $arg === '-h') {
            fwrite(STDOUT, shIntrospectUsage());
            return 0;
        }
        // --all: repo-wide index mode (discover scripts and emit an index).
        if ($arg === '--all') {
            $all = true;
            continue;
        }
        // --strict-risk: exit non-zero (3) when a critical risk is detected.
        if ($arg === '--strict-risk') {
            $strictRisk = true;
            continue;
        }
        // Bare `--format VALUE` (space-separated); the value is the next arg.
        if ($arg === '--format') {
            $expectFormat = true;
            continue;
        }
        // --format=VALUE: json -> JSON envelope, help -> compact help summary.
        // Any other value is an explicit error (status/stderr).
        if (str_starts_with($arg, '--format=')) {
            $value = substr($arg, strlen('--format='));
            if ($value === 'json') {
                $forceJson = true;
                continue;
            }
            if ($value === 'help') {
                $helpSummary = true;
                continue;
            }
            // Honour AI_OUTPUT=json for the error envelope shape when present.
            $jsonMode = getenv('AI_OUTPUT') === 'json';
            return shIntrospectFail($jsonMode, '', "unknown --format value: {$value} (expected json or help)");
        }
        if (str_starts_with($arg, '--')) {
            // Unknown flags are ignored for forward-compat; the only meaningful
            // toggles are --all, --format[=]json and --format[=]help. Path
            // resolution still proceeds.
            continue;
        }
        if ($path === null) {
            $path = $arg;
        }
    }

    $jsonMode = $forceJson || (getenv('AI_OUTPUT') === 'json');

    // Repo-wide index mode: discover the AI shell scripts/libraries and emit a
    // compact per-file index. `$path`, when given, is treated as the discovery
    // root; otherwise the repo's scripts/ai directory is used.
    if ($all) {
        return shIntrospectAllMain($jsonMode, $path, $strictRisk);
    }

    if ($path === null) {
        return shIntrospectFail($jsonMode, '', 'no input file given; usage: sh-introspect.php FILE.sh');
    }

    // Validate the path before parsing. Each branch yields status=error.
    if (!file_exists($path)) {
        return shIntrospectFail($jsonMode, $path, "file not found: {$path}");
    }
    if (is_dir($path)) {
        return shIntrospectFail($jsonMode, $path, "path is a directory, not a file: {$path}");
    }
    if (!is_readable($path)) {
        return shIntrospectFail($jsonMode, $path, "file is not readable: {$path}");
    }

    $abs = realpath($path);
    if ($abs === false) {
        return shIntrospectFail($jsonMode, $path, "could not resolve path: {$path}");
    }

    $raw = @file_get_contents($abs);
    if ($raw === false) {
        return shIntrospectFail($jsonMode, $abs, "could not read file: {$abs}");
    }

    if (strpos($raw, "\0") !== false) {
        return shIntrospectFail($jsonMode, $abs, "file appears to be binary (contains NUL byte): {$abs}");
    }

    $result = shIntrospectParse($raw, $abs);

    if ($helpSummary) {
        // --format=help is an explicit request for the compact summary and wins
        // over the AI_OUTPUT=json default. Path validation above still used the
        // JSON envelope shape when AI_OUTPUT=json was set.
        fwrite(STDOUT, shIntrospectRenderHelpSummary($result));
    } elseif ($jsonMode) {
        fwrite(STDOUT, shIntrospectEncode($result) . "\n");
    } else {
        fwrite(STDOUT, shIntrospectRenderText($result));
    }

    // --strict-risk: a critical max_risk fails the run (exit 3) so the contract
    // can gate CI. The full report is still emitted above first.
    if ($strictRisk && (string) ($result['risk_summary']['max_risk'] ?? '') === 'critical') {
        fwrite(STDERR, "STRICT-RISK: critical risk detected in " . $abs . "\n");
        return 3;
    }

    return 0;
}

/**
 * Repo-wide index mode (`--all`). Discovers the AI shell scripts and libraries,
 * statically introspects each (never executing any), and emits a compact index
 * envelope (schema ai.sh-introspect-index/v1). No target is ever executed.
 *
 * Discovery root: $root when given, else the directory containing this tool's
 * sibling `scripts/ai`. Globs: `scripts/ai/*.sh` and `scripts/ai/lib/*.sh`.
 *
 * In human mode a short table is printed; in JSON mode the full index envelope.
 * When $strictRisk is true, the run exits 3 if any indexed file is critical.
 */
function shIntrospectAllMain(bool $jsonMode, ?string $root, bool $strictRisk = false): int
{
    $baseDir = shIntrospectResolveScanRoot($root);
    if ($baseDir === null) {
        return shIntrospectFailIndex($jsonMode, "could not resolve scan root for --all (looked for a scripts/ai directory)");
    }

    $files = shIntrospectDiscoverScripts($baseDir);
    if ($files === []) {
        // Still a valid, successful empty index.
        $envelope = shIntrospectIndexEnvelope([], $baseDir);
        $envelope['warnings'][] = 'no shell scripts discovered under ' . $baseDir;
        fwrite(STDOUT, shIntrospectEncode($envelope) . "\n");
        return 0;
    }

    $entries = [];
    foreach ($files as $file) {
        $entries[] = shIntrospectIndexEntry($file);
    }

    // Deterministic ordering by relative path.
    usort($entries, static fn(array $a, array $b): int => strcmp((string) $a['path'], (string) $b['path']));

    $envelope = shIntrospectIndexEnvelope($entries, $baseDir);

    if ($jsonMode) {
        fwrite(STDOUT, shIntrospectEncode($envelope) . "\n");
    } else {
        fwrite(STDOUT, shIntrospectRenderIndexText($envelope));
    }

    // --strict-risk gate across the whole index.
    if ($strictRisk) {
        $critical = array_values(array_filter(
            $entries,
            static fn(array $e): bool => (string) ($e['max_risk'] ?? '') === 'critical'
        ));
        if ($critical !== []) {
            $names = implode(', ', array_map(static fn(array $e): string => (string) $e['path'], $critical));
            fwrite(STDERR, "STRICT-RISK: critical risk detected in: {$names}\n");
            return 3;
        }
    }

    return 0;
}

/**
 * Resolve the directory to scan for `--all`. Accepts an explicit root (a
 * `scripts/ai` dir, its parent, or the repo root) or falls back to the tool's
 * own `<repo>/scripts/ai`. Returns the absolute `scripts/ai` dir or null.
 */
function shIntrospectResolveScanRoot(?string $root): ?string
{
    $candidates = [];
    if ($root !== null && $root !== '') {
        $abs = realpath($root);
        if ($abs !== false) {
            $candidates[] = $abs;                       // given dir itself
            $candidates[] = $abs . '/scripts/ai';       // given repo root
            $candidates[] = $abs . '/ai';               // given scripts/ dir
        }
    }
    // Tool is at <repo>/tools/ai/sh-introspect.php -> <repo>/scripts/ai.
    $repoRoot = realpath(__DIR__ . '/../..');
    if ($repoRoot !== false) {
        $candidates[] = $repoRoot . '/scripts/ai';
    }

    foreach ($candidates as $cand) {
        if (is_dir($cand) && basename($cand) === 'ai' && is_dir(dirname($cand))) {
            return $cand;
        }
        // Accept a directory that directly contains .sh files even if not named ai.
        if (is_dir($cand) && glob(rtrim($cand, '/') . '/*.sh') !== []) {
            return $cand;
        }
    }
    return null;
}

/**
 * Discover the introspection targets under a `scripts/ai` directory:
 * `*.sh` directly inside it and `lib/*.sh` one level down. Symlinks and
 * non-readable files are skipped. Returns absolute paths.
 *
 * @return array<int,string>
 */
function shIntrospectDiscoverScripts(string $baseDir): array
{
    $found = [];
    foreach ([rtrim($baseDir, '/') . '/*.sh', rtrim($baseDir, '/') . '/lib/*.sh'] as $glob) {
        $matches = glob($glob);
        if ($matches === false) {
            continue;
        }
        foreach ($matches as $m) {
            if (is_file($m) && is_readable($m)) {
                $found[$m] = true;
            }
        }
    }
    return array_keys($found);
}

/**
 * Build one compact index entry for a script by running the full static parse
 * and projecting the high-signal summary fields. The target is NOT executed.
 *
 * @return array<string,mixed>
 */
function shIntrospectIndexEntry(string $file): array
{
    $abs = realpath($file) ?: $file;
    $raw = @file_get_contents($abs);
    if ($raw === false || strpos((string) $raw, "\0") !== false) {
        return [
            'path' => shIntrospectPathObject($abs)['relative'],
            'absolute' => $abs,
            'status' => 'error',
            'errors' => [$raw === false ? 'could not read file' : 'binary file (NUL byte)'],
        ];
    }

    $env = shIntrospectParse((string) $raw, $abs);
    $risk = is_array($env['risk_summary'] ?? null) ? $env['risk_summary'] : [];

    return [
        'path' => shIntrospectPathObject($abs)['relative'],
        'absolute' => $abs,
        'status' => (string) ($env['status'] ?? 'ok'),
        'kind' => (string) ($env['kind'] ?? 'unknown'),
        'modes' => count(is_array($env['modes'] ?? null) ? $env['modes'] : []),
        'params' => count(is_array($env['params'] ?? null) ? $env['params'] : []),
        'functions' => count(is_array($env['functions'] ?? null) ? $env['functions'] : []),
        'max_risk' => (string) ($risk['max_risk'] ?? 'unknown'),
        'has_mutation' => (bool) ($risk['has_mutation'] ?? false),
        'has_dynamic_execution' => (bool) ($risk['has_dynamic_execution'] ?? false),
        'confidence' => (int) ($env['meta']['confidence'] ?? 0),
        'target_executed' => false,
    ];
}

/**
 * Wrap index entries in the index envelope.
 *
 * @param array<int,array<string,mixed>> $entries
 * @return array<string,mixed>
 */
function shIntrospectIndexEnvelope(array $entries, string $baseDir): array
{
    return [
        'schema' => SH_INTROSPECT_INDEX_SCHEMA,
        'status' => 'ok',
        'tool' => SH_INTROSPECT_TOOL,
        'root' => shIntrospectPathObject(rtrim($baseDir, '/'))['relative'],
        'count' => count($entries),
        'files' => $entries,
        'warnings' => [],
        'errors' => [],
        'meta' => [
            'parser' => 'php-static',
            'target_executed' => false,
        ],
    ];
}

/**
 * Emit an index-mode error envelope (or stderr line) and return exit code 2.
 */
function shIntrospectFailIndex(bool $jsonMode, string $message): int
{
    if ($jsonMode) {
        $envelope = shIntrospectIndexEnvelope([], '');
        $envelope['status'] = 'error';
        $envelope['errors'] = [$message];
        fwrite(STDOUT, shIntrospectEncode($envelope) . "\n");
    } else {
        fwrite(STDERR, "ERROR: {$message}\n");
    }
    return 2;
}

/**
 * Render a short human-readable table for the repo-wide index.
 *
 * @param array<string,mixed> $envelope
 */
function shIntrospectRenderIndexText(array $envelope): string
{
    $files = is_array($envelope['files'] ?? null) ? $envelope['files'] : [];
    $out = [];
    $out[] = sprintf(
        'sh-introspect index: %d file(s) under %s',
        (int) ($envelope['count'] ?? count($files)),
        (string) ($envelope['root'] ?? '')
    );
    foreach ((array) ($envelope['warnings'] ?? []) as $w) {
        $out[] = 'WARNING: ' . $w;
    }
    $out[] = '';
    foreach ($files as $f) {
        $out[] = sprintf(
            '  %-40s kind=%-8s modes=%-2d params=%-2d risk=%-8s conf=%d',
            (string) ($f['path'] ?? ''),
            (string) ($f['kind'] ?? 'unknown'),
            (int) ($f['modes'] ?? 0),
            (int) ($f['params'] ?? 0),
            (string) ($f['max_risk'] ?? 'unknown'),
            (int) ($f['confidence'] ?? 0)
        );
    }
    return implode("\n", $out) . "\n";
}

function shIntrospectUsage(): string
{
    return <<<TXT
sh-introspect.php — static introspector for Bash scripts.

Usage:
    php tools/ai/sh-introspect.php FILE.sh            human-readable summary
    AI_OUTPUT=json php tools/ai/sh-introspect.php FILE.sh   JSON envelope
    php tools/ai/sh-introspect.php --format=json FILE.sh    force JSON
    php tools/ai/sh-introspect.php --format=help FILE.sh    compact help summary
    php tools/ai/sh-introspect.php --all [ROOT]            repo-wide index
    php tools/ai/sh-introspect.php --all --format json     index JSON envelope
    php tools/ai/sh-introspect.php --strict-risk FILE.sh   exit 3 on critical risk
    php tools/ai/sh-introspect.php --help | -h             this help

The target script is never executed; FILE.sh is parsed statically as text.
Reports: functions, modes (with display_group), mode_contracts (per-mode
deps/positionals/examples), params (with applies_to_modes + scope), positionals,
case_labels, json_keys, json_key_candidates, json_paths (JSONPath + confidence),
output_schemas (envelope + per-family + per-mode), env_inputs, sources (with
resolved siblings), dependencies (classified), unknown_option_handlers, commands
(read + mutating, with kind/source), side_effects, risk_summary, risk_findings,
examples, examples_by_mode.

--all emits the index envelope (schema ai.sh-introspect-index/v1) for the AI
shell scripts under scripts/ai (and scripts/ai/lib). --strict-risk makes the run
exit 3 when any critical risk is detected (single file or across the index).

TXT;
}

/**
 * Emit an error envelope (JSON) or stderr line, returning the exit code.
 */
function shIntrospectFail(bool $jsonMode, string $file, string $message): int
{
    if ($jsonMode) {
        $envelope = shIntrospectEmptyEnvelope($file);
        $envelope['status'] = 'error';
        $envelope['errors'] = [$message];
        $envelope['meta']['confidence'] = 0;
        fwrite(STDOUT, shIntrospectEncode($envelope) . "\n");
    } else {
        fwrite(STDERR, "ERROR: {$message}\n");
    }
    return 2;
}

/**
 * Canonical empty envelope. Every key in the binding contract is present.
 *
 * @return array<string,mixed>
 */
function shIntrospectEmptyEnvelope(string $file): array
{
    return [
        'schema' => SH_INTROSPECT_SCHEMA,
        'status' => 'ok',
        'tool' => SH_INTROSPECT_TOOL,
        'kind' => 'unknown',
        // `file` is kept as the absolute path string for backward compatibility.
        // `path` is the additive structured form {absolute, relative}.
        'file' => $file,
        'path' => shIntrospectPathObject($file),
        'functions' => [],
        // Documented envelope status vocabulary, parsed from the usage block
        // ("Status is one of: ...") when present; [] otherwise. Never invented.
        'status_values' => [],
        // Top-level human-help metadata parsed from the usage block: summary,
        // usage line, and the JSON-output env note. Keys are present only when
        // derivable from usage text.
        'help' => [],
        'modes' => [],
        'mode_contracts' => [],
        'params' => [],
        'positionals' => [],
        'case_labels' => [],
        'json_keys' => [],
        'json_key_candidates' => [],
        'json_paths' => [],
        'output_schemas' => [],
        'env_inputs' => [],
        'sources' => [],
        'dependencies' => [],
        'unknown_option_handlers' => [],
        'commands' => [],
        'side_effects' => [],
        'risk_summary' => [
            'max_risk' => 'unknown',
            'has_mutation' => false,
            'has_dynamic_execution' => false,
            'has_unresolved_source' => false,
        ],
        'risk_findings' => [],
        'examples' => [],
        'examples_by_mode' => [],
        'warnings' => [],
        'errors' => [],
        'meta' => [
            'parser' => 'php-static',
            'confidence' => 0,
            'target_executed' => false,
        ],
    ];
}

/**
 * Build the structured path object {absolute, relative}. The relative path is
 * resolved against the current working directory when the absolute path lives
 * underneath it; otherwise it falls back to the absolute path.
 *
 * @return array{absolute:string,relative:string}
 */
function shIntrospectPathObject(string $file): array
{
    $absolute = $file;
    $relative = $file;

    $cwd = getcwd();
    if ($cwd !== false && $file !== '') {
        $prefix = rtrim($cwd, '/\\') . DIRECTORY_SEPARATOR;
        if (str_starts_with($file, $prefix)) {
            $relative = substr($file, strlen($prefix));
        }
    }

    return ['absolute' => $absolute, 'relative' => $relative];
}

/**
 * @param array<string,mixed> $envelope
 */
function shIntrospectEncode(array $envelope): string
{
    return json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

/**
 * Top-level static parse. Returns a fully-formed envelope.
 *
 * @return array<string,mixed>
 */
function shIntrospectParse(string $raw, string $file): array
{
    $env = shIntrospectEmptyEnvelope($file);
    $warnings = [];

    // Extension advisory (still status=ok).
    if (!preg_match('/\.(sh|bash)$/', $file)) {
        $warnings[] = 'file extension is not .sh/.bash; parsed as shell text anyway';
    }

    // 1) Split into lines for line-number reporting (1-based).
    $lines = preg_split('/\r\n|\n|\r/', $raw);
    if ($lines === false) {
        $lines = [$raw];
    }

    // 2) Extract the usage() heredoc block (help text) separately.
    $usageBlock = shIntrospectExtractUsageBlock($lines);

    // 3) Make a code copy with ALL heredocs stripped so help text is not
    //    parsed as code. Heredoc body lines are blanked (kept as empty lines)
    //    to preserve 1-based line numbers.
    $codeLines = shIntrospectStripHeredocs($lines);

    // 4) Functions from the code copy.
    $functions = shIntrospectExtractFunctions($codeLines);

    // 5) Case branches from the code copy.
    $cases = shIntrospectExtractCaseBranches($codeLines, $functions);

    // 6/7/8/9) Classify branches into params / modes / case_labels and the
    //          unknown-option fallback handlers (e.g. `--*` / `-*` / `*`).
    $classified = shIntrospectClassifyBranches($cases, $usageBlock);
    $params = $classified['params'];
    $modes = $classified['modes'];
    $caseLabels = $classified['case_labels'];
    $unknownHandlers = $classified['unknown_option_handlers'];

    // Help-surface fields parsed from the usage block only (never invented):
    //  - status_values: the documented "Status is one of: a | b | c" vocabulary.
    //  - help.summary/usage/json_output_env: the title summary, Usage: line, and
    //    the AI_OUTPUT=json activation note.
    //  - usage mode rows (name -> description), used to enrich modes that are not
    //    in an `is_*_mode` family (e.g. doctor, unsafe-all) and to add a
    //    `description` to all modes where the usage block documents them.
    //  - usage flag rows (flag -> {group, description}) for grouped help output.
    $statusValues = shIntrospectExtractStatusValues($usageBlock);
    $helpMeta = shIntrospectExtractHelpMeta($usageBlock);
    $usageModeDocs = shIntrospectExtractUsageModeDocs($usageBlock);
    $usageParamDocs = shIntrospectExtractUsageParamDocs($usageBlock);

    // Modes proven by a `[[ "$mode" == "name" ]]`-style equality guard in code
    // (e.g. doctor, unsafe-all). These are confirmed implemented dispatch
    // targets and earn a higher-confidence source than documentation alone.
    $guardModes = shIntrospectExtractModeEqualityGuards($codeLines);

    // Merge usage-only documented modes (e.g. doctor, unsafe-all) that have no
    // `is_*_mode` family guard, so --help and --introspect agree. Conservative:
    // only modes that appear as documented rows in the usage block are added.
    $modes = shIntrospectMergeUsageModes($modes, $usageModeDocs, $guardModes);

    // 10) Merge usage-doc-only flags as confidence-75 params.
    $params = shIntrospectMergeUsageParams($params, $usageBlock);

    // Attach derived description/group to params from the grouped usage rows.
    $params = shIntrospectAttachParamDocs($params, $usageParamDocs);

    // P1: map each param to the modes it applies to, where derivable from the
    // usage block. Adds `applies_to_modes` only when evidence exists.
    $params = shIntrospectMapParamsToModes($params, array_values($modes), $usageBlock);

    // env_inputs (strict ${NAME...} forms only).
    $envInputs = shIntrospectExtractEnvInputs($codeLines);

    // sources (`source X` / `. X`), with best-effort resolution of the common
    // `$(dirname "${BASH_SOURCE[0]}")/sibling.sh` pattern relative to $file.
    $sources = shIntrospectExtractSources($codeLines, $file);

    // examples from the usage Examples: section.
    $examples = shIntrospectExtractExamples($usageBlock);

    // Additive P0 fields.
    $jsonKeys = shIntrospectExtractJsonKeys($codeLines, $usageBlock, array_values($functions));
    // P1: split low-confidence emitted keys into a separate candidates list so
    // the high-signal json_keys surface stays trustworthy.
    $split = shIntrospectSplitJsonKeyCandidates(array_values($jsonKeys));
    $jsonKeys = $split['confirmed'];
    $jsonKeyCandidates = $split['candidates'];
    // P1: nested JSON paths (e.g. limits.max_results, meta.returned). Parents
    // are constrained to confirmed top-level keys to avoid prose false hits.
    $jsonPaths = shIntrospectExtractJsonPaths($codeLines, $usageBlock, array_values($jsonKeys));
    // P0: output_schemas placeholder surface (always an array; populated from
    // the usage envelope description when a recognisable schema block exists).
    $outputSchemas = shIntrospectExtractOutputSchemas($usageBlock, array_values($jsonKeys), array_values($modes), $usageModeDocs);
    $positionals = shIntrospectExtractPositionals($usageBlock);
    $dependencies = shIntrospectExtractDependencies($codeLines, array_values($functions));
    // P4: classify each dependency (required/optional/candidate), tag base
    // utilities vs primary tools, and map to modes where derivable.
    $dependencies = shIntrospectClassifyDependencies(array_values($dependencies), $codeLines, array_values($modes));
    $sideEffects = shIntrospectExtractSideEffects($codeLines);
    // P4: per-call command extraction (line, argv hint, risk, effect).
    $commands = shIntrospectExtractCommands($codeLines);
    $riskSummary = shIntrospectRiskSummary($codeLines, $sideEffects, $sources);
    // P4: escalate max_risk to `critical` when any extracted command is critical
    // (eval / sh -c / curl|sh / rm -rf with expansion).
    foreach ($commands as $cmd) {
        if ((string) ($cmd['risk'] ?? '') === 'critical') {
            $riskSummary['max_risk'] = 'critical';
            break;
        }
    }
    // P4: structured risk findings explaining max_risk and each unresolved source.
    $riskFindings = shIntrospectBuildRiskFindings($riskSummary, $sources, $commands);

    // P1: per-mode call contracts derived entirely from already-parsed data.
    // $usageModeDocs supplies the per-mode description/output_notes where the
    // usage block documents them (never invented).
    $modeContracts = shIntrospectBuildModeContracts(
        array_values($modes),
        array_values($positionals),
        array_values($dependencies),
        array_values($examples),
        $codeLines,
        $usageModeDocs
    );
    // P1: per-mode example index keyed by mode name.
    $examplesByMode = shIntrospectBuildExamplesByMode(array_values($modes), array_values($examples));

    // P4: emit a warning when a dynamic source could not be statically resolved.
    if (!empty($riskSummary['has_unresolved_source'])) {
        $warnings[] = 'risk_summary.has_unresolved_source=true: one or more `source`/`.` targets are dynamic and could not be statically resolved';
    }

    $env['kind'] = shIntrospectDetectKind($raw, $codeLines);
    $env['functions'] = array_values($functions);
    $env['status_values'] = $statusValues;
    $env['help'] = $helpMeta;
    $env['modes'] = array_values($modes);
    $env['mode_contracts'] = $modeContracts;
    $env['params'] = array_values($params);
    $env['positionals'] = array_values($positionals);
    $env['case_labels'] = array_values($caseLabels);
    $env['json_keys'] = array_values($jsonKeys);
    $env['json_key_candidates'] = array_values($jsonKeyCandidates);
    $env['json_paths'] = array_values($jsonPaths);
    $env['output_schemas'] = array_values($outputSchemas);
    $env['env_inputs'] = array_values($envInputs);
    $env['sources'] = array_values($sources);
    $env['dependencies'] = array_values($dependencies);
    $env['unknown_option_handlers'] = array_values($unknownHandlers);
    $env['commands'] = array_values($commands);
    $env['side_effects'] = array_values($sideEffects);
    $env['risk_summary'] = $riskSummary;
    $env['risk_findings'] = array_values($riskFindings);
    $env['examples'] = array_values($examples);
    $env['examples_by_mode'] = array_values($examplesByMode);
    $env['warnings'] = $warnings;
    $env['meta']['confidence'] = shIntrospectOverallConfidence($env);

    return $env;
}

/**
 * Extract the heredoc body inside the usage() function. Returns the raw help
 * text (without the heredoc delimiters), or '' when not found.
 *
 * @param array<int,string> $lines
 */
function shIntrospectExtractUsageBlock(array $lines): string
{
    $inUsage = false;
    $braceDepth = 0;
    $collecting = false;
    $delimiter = null;
    $collected = [];

    foreach ($lines as $line) {
        if (!$inUsage) {
            // usage() { OR function usage { OR function usage() {
            if (preg_match('/^\s*(function\s+)?usage\s*(\(\s*\))?\s*\{/', $line)) {
                $inUsage = true;
                $braceDepth = substr_count($line, '{') - substr_count($line, '}');
            }
            continue;
        }

        if ($collecting) {
            // Heredoc terminator: delimiter alone on its own line (optionally
            // indented when <<- was used; we accept leading whitespace).
            if (preg_match('/^\s*' . preg_quote((string) $delimiter, '/') . '\s*$/', $line)) {
                $collecting = false;
                $delimiter = null;
                continue;
            }
            $collected[] = $line;
            continue;
        }

        // Detect a heredoc opener: cat <<'EOF' / <<EOF / <<-"EOF".
        if (preg_match('/<<-?\s*[\'"]?([A-Za-z_][A-Za-z0-9_]*)[\'"]?/', $line, $m)) {
            $collecting = true;
            $delimiter = $m[1];
            continue;
        }

        $braceDepth += substr_count($line, '{') - substr_count($line, '}');
        if ($braceDepth <= 0) {
            break;
        }
    }

    return implode("\n", $collected);
}

/**
 * Produce a copy of the source lines with all heredoc bodies blanked out so
 * help/usage text is never parsed as code. Line count is preserved.
 *
 * @param array<int,string> $lines
 * @return array<int,string>
 */
function shIntrospectStripHeredocs(array $lines): array
{
    $out = [];
    $delimiter = null;

    foreach ($lines as $line) {
        if ($delimiter !== null) {
            // Inside a heredoc body: blank it but keep the line slot.
            if (preg_match('/^\s*' . preg_quote($delimiter, '/') . '\s*$/', $line)) {
                $delimiter = null;
            }
            $out[] = '';
            continue;
        }

        // Opener detection. Ignore here-strings (<<<) and `$(...)`. Capture the
        // delimiter token; require <<word or <<'word'/<<"word"/<<-word.
        if (preg_match('/<<-?\s*[\'"]?([A-Za-z_][A-Za-z0-9_]*)[\'"]?/', $line)
            && !preg_match('/<<</', $line)) {
            preg_match('/<<-?\s*[\'"]?([A-Za-z_][A-Za-z0-9_]*)[\'"]?/', $line, $m);
            $delimiter = $m[1];
            // Keep the opener line itself (it is code), only blank the body.
            $out[] = $line;
            continue;
        }

        $out[] = $line;
    }

    return $out;
}

/**
 * Extract function definitions from the (heredoc-stripped) code lines.
 * Detects: name() {, function name {, function name() {.
 *
 * @param array<int,string> $codeLines
 * @return array<string,array<string,mixed>> keyed by function name
 */
function shIntrospectExtractFunctions(array $codeLines): array
{
    $functions = [];

    foreach ($codeLines as $idx => $line) {
        if (preg_match('/^\s*(?:function\s+)([A-Za-z_][A-Za-z0-9_-]*)\s*(?:\(\s*\))?\s*\{/', $line, $m)
            || preg_match('/^\s*([A-Za-z_][A-Za-z0-9_-]*)\s*\(\s*\)\s*\{/', $line, $m)) {
            $name = $m[1];
            if (isset($functions[$name])) {
                continue;
            }
            $functions[$name] = [
                'name' => $name,
                'line' => $idx + 1,
                'source' => 'definition',
                'confidence' => 95,
            ];
        }
    }

    return $functions;
}

/**
 * Walk the code lines and extract every `case <subject> in ... esac` with its
 * branches. Each branch records labels, the start line, and the body lines.
 *
 * @param array<int,string> $codeLines
 * @param array<string,array<string,mixed>> $functions
 * @return array<int,array<string,mixed>> list of branch records:
 *   { subject, labels[], line, body, enclosing_function }
 */
function shIntrospectExtractCaseBranches(array $codeLines, array $functions): array
{
    $branches = [];
    $total = count($codeLines);

    // Precompute, for each line, the nearest enclosing function name by
    // tracking brace depth from each function header.
    $enclosing = shIntrospectEnclosingFunctions($codeLines, $functions);

    $i = 0;
    while ($i < $total) {
        $line = $codeLines[$i];

        // case SUBJECT in
        if (preg_match('/^\s*case\s+(.+?)\s+in\b/', $line, $cm)) {
            $subject = trim($cm[1]);
            $caseFn = $enclosing[$i] ?? '';
            $i++;

            // Collect branches until matching `esac`.
            while ($i < $total) {
                $bline = $codeLines[$i];

                if (preg_match('/^\s*esac\b/', $bline)) {
                    $i++;
                    break;
                }

                // Nested case inside this case: skip to its esac to avoid
                // double-counting its branches at the outer level.
                if (preg_match('/^\s*case\s+.+?\s+in\b/', $bline)) {
                    $depth = 1;
                    $i++;
                    while ($i < $total && $depth > 0) {
                        if (preg_match('/^\s*case\s+.+?\s+in\b/', $codeLines[$i])) {
                            $depth++;
                        } elseif (preg_match('/^\s*esac\b/', $codeLines[$i])) {
                            $depth--;
                        }
                        $i++;
                    }
                    continue;
                }

                // A branch label line: LABELS) ... ;;  (labels up to the
                // first unescaped `)`). Body runs from this line until `;;`.
                if (preg_match('/^\s*([^()]+?)\)/', $bline, $lm)) {
                    $labelRaw = trim($lm[1]);
                    $labels = array_values(array_filter(array_map('trim', explode('|', $labelRaw)), static fn($s) => $s !== ''));
                    $startLine = $i + 1;
                    $body = [];

                    // Include remainder of the label line after the `)` as body.
                    $afterParen = substr($bline, strpos($bline, ')') + 1);
                    $body[] = $afterParen;

                    // Detect ;; on the label line itself.
                    if (preg_match('/;;\s*$/', $bline) || strpos($afterParen, ';;') !== false) {
                        $branches[] = shIntrospectBranchRecord($subject, $labels, $startLine, $body, $caseFn);
                        $i++;
                        continue;
                    }

                    $i++;
                    // Track nested case...esac so an inner `;;` does not
                    // prematurely terminate this (outer) branch body.
                    $nestDepth = 0;
                    while ($i < $total) {
                        $bodyLine = $codeLines[$i];

                        if ($nestDepth === 0 && preg_match('/^\s*esac\b/', $bodyLine)) {
                            // Unterminated branch ended by the enclosing esac.
                            break;
                        }

                        if (preg_match('/^\s*case\s+.+?\s+in\b/', $bodyLine)) {
                            $nestDepth++;
                            $body[] = $bodyLine;
                            $i++;
                            continue;
                        }
                        if ($nestDepth > 0 && preg_match('/^\s*esac\b/', $bodyLine)) {
                            $nestDepth--;
                            $body[] = $bodyLine;
                            $i++;
                            continue;
                        }

                        if ($nestDepth === 0 && (preg_match('/;;\s*$/', $bodyLine) || preg_match('/;;\s/', $bodyLine))) {
                            // Body up to (and including) text before ;;.
                            $body[] = preg_replace('/;;.*$/', '', $bodyLine) ?? '';
                            $i++;
                            break;
                        }

                        $body[] = $bodyLine;
                        $i++;
                    }

                    $branches[] = shIntrospectBranchRecord($subject, $labels, $startLine, $body, $caseFn);
                    continue;
                }

                $i++;
            }
            continue;
        }

        $i++;
    }

    return $branches;
}

/**
 * @param array<int,string> $labels
 * @param array<int,string> $body
 * @return array<string,mixed>
 */
function shIntrospectBranchRecord(string $subject, array $labels, int $startLine, array $body, string $caseFn): array
{
    return [
        'subject' => $subject,
        'labels' => $labels,
        'line' => $startLine,
        'body' => implode("\n", $body),
        'enclosing_function' => $caseFn,
    ];
}

/**
 * Map each line index to the name of its enclosing function (or '').
 *
 * @param array<int,string> $codeLines
 * @param array<string,array<string,mixed>> $functions
 * @return array<int,string>
 */
function shIntrospectEnclosingFunctions(array $codeLines, array $functions): array
{
    $map = array_fill(0, count($codeLines), '');

    foreach ($functions as $fn) {
        $start = $fn['line'] - 1;
        $depth = 0;
        $started = false;
        for ($j = $start, $n = count($codeLines); $j < $n; $j++) {
            $depth += substr_count($codeLines[$j], '{') - substr_count($codeLines[$j], '}');
            if (!$started && substr_count($codeLines[$j], '{') > 0) {
                $started = true;
            }
            // Only claim lines not already owned by a deeper (later-starting)
            // function. Since we iterate functions in definition order, the
            // last writer for a line wins; prefer the innermost by writing the
            // function whose header is closest above. We approximate by only
            // setting when currently empty OR the existing owner starts earlier.
            if ($map[$j] === '') {
                $map[$j] = $fn['name'];
            }
            if ($started && $depth <= 0) {
                break;
            }
        }
    }

    return $map;
}

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

/**
 * P4: extract concrete command invocations with a coarse risk classification.
 *
 * Each finding records the 1-based line, the command name, a short argv hint
 * (the rest of the line, trimmed), a risk class, and an effect tag. Detection
 * is deliberately conservative and command-position aware to avoid matching
 * literals inside strings. Covers destructive filesystem mutations, git
 * mutations, installer/network mutations, and dynamic execution.
 *
 * @param array<int,string> $codeLines
 * @return array<int,array<string,mixed>>
 */
function shIntrospectExtractCommands(array $codeLines): array
{
    // De-duplicate by name+effect+risk, keeping the FIRST occurrence (and its
    // line/argv_hint). This keeps the list compact and stable: read commands
    // that recur on many lines collapse to one entry, and each distinct
    // mutating command class is reported once with its first site.
    $seen = [];
    $out = [];
    foreach ($codeLines as $idx => $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        // Classify the call SITE so dependency probes and tool-name loops are
        // not reported as real command executions (e.g. `for tool in ... rg ...`
        // or `command_exists ast-grep`). `kind` is `invocation` for a genuine
        // execution and `dependency-check` for a probe/loop; `source` records
        // the detected context.
        $context = shIntrospectCommandContext($line);

        $found = shIntrospectClassifyCommandLine($line);
        foreach ($found as $f) {
            $dedupKey = $f['name'] . "\0" . $f['effect'] . "\0" . $f['risk'] . "\0" . $context['kind'];
            if (isset($seen[$dedupKey])) {
                continue;
            }
            $seen[$dedupKey] = true;
            $out[] = [
                'name' => $f['name'],
                'line' => $idx + 1,
                'argv_hint' => shIntrospectArgvHint($trimmed),
                'risk' => $f['risk'],
                'effect' => $f['effect'],
                'source' => $context['source'],
                'kind' => $context['kind'],
            ];
        }
    }

    return $out;
}

/**
 * Classify the SITE of a command line so dependency probes and tool-name loops
 * are distinguished from real executions:
 *
 *   - `for tool in jq git rg ast-grep; do`  -> tool-loop / dependency-check
 *   - `command_exists ast-grep || ...`      -> command-check / dependency-check
 *   - `command -v rg >/dev/null`            -> command-check / dependency-check
 *   - anything else                          -> command-position / invocation
 *
 * The tool name appearing inside these contexts is a check, not a run, so the
 * caller can tag the finding as `kind: dependency-check`.
 *
 * @return array{source:string,kind:string}
 */
function shIntrospectCommandContext(string $line): array
{
    // `for VAR in ...; do` — a tool-name iteration list, not an execution.
    if (preg_match('/\bfor\s+\w+\s+in\b.*\bdo\b/', $line)) {
        return ['source' => 'tool-loop', 'kind' => 'dependency-check'];
    }
    // Availability probes: command_exists/require_tool/need_tool/have X, or
    // `command -v X` / `type X` / `hash X`.
    if (preg_match('/\b(?:command_exists|require_tool|need_tool|have)\b/', $line)
        || preg_match('/\bcommand\s+-v\b/', $line)
        || preg_match('/\b(?:type|hash)\s+-?\w/', $line)
    ) {
        return ['source' => 'command-check', 'kind' => 'dependency-check'];
    }
    return ['source' => 'command-position', 'kind' => 'invocation'];
}

/**
 * Classify a single line into zero or more dangerous command findings.
 *
 * @return array<int,array{name:string,risk:string,effect:string}>
 */
function shIntrospectClassifyCommandLine(string $line): array
{
    $found = [];

    // Dynamic execution (critical).
    if (preg_match('/(?:^|[;&|]|\b(?:then|else|do)\s)\s*eval\s+[^(]/', $line)) {
        $found[] = ['name' => 'eval', 'risk' => 'critical', 'effect' => 'dynamic-execution'];
    }
    if (preg_match('/(?:^\s*|[;&|(]\s*|\bxargs\s+)(?:bash|sh)\s+-c\b/', $line)) {
        $found[] = ['name' => 'sh -c', 'risk' => 'critical', 'effect' => 'dynamic-execution'];
    }

    // Dynamic source: `source X` / `. X` where X is a variable / command
    // substitution. Sourcing `$0`/${BASH_SOURCE} re-enters the running script
    // and is treated as critical (self dynamic-execution); other dynamic source
    // targets are high (they execute arbitrary external file contents).
    if (preg_match('/(?:^\s*|[;&|]\s*|\b(?:then|else|do)\s+)(?:source|\.)\s+(["\']?)([^"\';\s]+)\1/', $line, $sm)) {
        $target = $sm[2];
        $isDynamic = (bool) preg_match('/[\$`]/', $target);
        if ($isDynamic) {
            $isSelf = (bool) preg_match('/\$\{?0\}?|\$\{?BASH_SOURCE/', $target);
            if ($isSelf) {
                $found[] = ['name' => 'source $0', 'risk' => 'critical', 'effect' => 'dynamic-execution'];
            } else {
                $found[] = ['name' => 'source', 'risk' => 'high', 'effect' => 'dynamic-source'];
            }
        }
    }

    // Installer / network mutation (high).
    if (preg_match('/\b(?:curl|wget)\b[^|]*\|\s*(?:sudo\s+)?(?:bash|sh)\b/', $line)) {
        $found[] = ['name' => 'curl|sh', 'risk' => 'critical', 'effect' => 'network-exec'];
    }
    if (preg_match('/\b(?:npm|pnpm|yarn)\s+(?:install|add|i)\b/', $line)) {
        $found[] = ['name' => 'npm-install', 'risk' => 'high', 'effect' => 'installer'];
    }
    if (preg_match('/\bcomposer\s+(?:install|require|update)\b/', $line)) {
        $found[] = ['name' => 'composer-install', 'risk' => 'high', 'effect' => 'installer'];
    }
    if (preg_match('/\bpip3?\s+install\b/', $line)) {
        $found[] = ['name' => 'pip-install', 'risk' => 'high', 'effect' => 'installer'];
    }

    // Destructive filesystem mutation (high; rm -rf with expansion is critical).
    if (preg_match('/\brm\s+(?:-[A-Za-z]*\s+)*-?[A-Za-z]*[rf]/', $line)) {
        $risk = preg_match('/\brm\s+-[A-Za-z]*r[A-Za-z]*f|\brm\s+-[A-Za-z]*f[A-Za-z]*r|\brm\s+-rf\b|\brm\s+-fr\b/', $line)
            && preg_match('/\$|`|\*/', $line) ? 'critical' : 'high';
        $found[] = ['name' => 'rm', 'risk' => $risk, 'effect' => 'filesystem-delete'];
    } elseif (preg_match('/\brm\s+/', $line)) {
        $found[] = ['name' => 'rm', 'risk' => 'high', 'effect' => 'filesystem-delete'];
    }
    if (preg_match('/\b(mv|cp)\s+\S/', $line, $mc)) {
        $found[] = ['name' => $mc[1], 'risk' => 'medium', 'effect' => 'filesystem-write'];
    }
    if (preg_match('/\bsed\s+-i\b/', $line)) {
        $found[] = ['name' => 'sed -i', 'risk' => 'high', 'effect' => 'filesystem-write'];
    }
    if (preg_match('/\bfind\b.*-delete\b/', $line)) {
        $found[] = ['name' => 'find -delete', 'risk' => 'high', 'effect' => 'filesystem-delete'];
    }
    if (preg_match('/(?:^|[\s;&|(])truncate\s+-/', $line)) {
        $found[] = ['name' => 'truncate', 'risk' => 'high', 'effect' => 'filesystem-write'];
    }
    if (preg_match('/\bchmod\s+(?:-[A-Za-z]*\s+)*-R\b|\bchmod\s+-R\b/', $line)) {
        $found[] = ['name' => 'chmod -R', 'risk' => 'high', 'effect' => 'filesystem-perms'];
    }
    if (preg_match('/\bchown\s+(?:-[A-Za-z]*\s+)*-R\b|\bchown\s+-R\b/', $line)) {
        $found[] = ['name' => 'chown -R', 'risk' => 'high', 'effect' => 'filesystem-perms'];
    }
    if (preg_match('/\brsync\b.*--delete\b/', $line)) {
        $found[] = ['name' => 'rsync --delete', 'risk' => 'high', 'effect' => 'filesystem-delete'];
    }

    // Git mutation (high). Destructive/irreversible variants are critical:
    // `git reset --hard`, `git clean -f[d]`, `git push --force/-f`.
    if (preg_match('/\bgit\b[^|]*\b(reset|clean|checkout|restore|add|commit|push|rebase|merge|tag)\b/', $line, $gm)) {
        $isCriticalGit =
            (bool) preg_match('/\bgit\b[^|]*\breset\b[^|]*--hard\b/', $line)
            || (bool) preg_match('/\bgit\b[^|]*\bclean\b[^|]*-[A-Za-z]*f/', $line)
            || (bool) preg_match('/\bgit\b[^|]*\bpush\b[^|]*(?:--force\b|-f\b|--force-with-lease\b)/', $line);
        $found[] = [
            'name' => 'git ' . $gm[1],
            'risk' => $isCriticalGit ? 'critical' : 'high',
            'effect' => 'git-mutation',
        ];
    }

    // --- Read-only commands (low risk). Only when no mutating finding already
    //     matched this line, so a mutation never gets shadowed by a read tag. ---
    if ($found === []) {
        // git read subcommands.
        if (preg_match('/\bgit\b(?:\s+-C\s+\S+)?\s+(grep|log|diff|show|rev-parse|ls-files|blame|config|status|cat-file)\b/', $line, $grm)) {
            $found[] = ['name' => 'git ' . $grm[1], 'risk' => 'low', 'effect' => 'git-read'];
        }
        // filesystem read tools.
        if (preg_match('/(?:^|[\s;|&(`$])(rg|fd|fdfind|ast-grep|sg|cat|head|tail|sort)\b/', $line, $frm)) {
            $found[] = ['name' => $frm[1], 'risk' => 'low', 'effect' => 'filesystem-read'];
        }
    }

    return $found;
}

/**
 * Short, single-line argv hint: collapse whitespace and cap length so findings
 * stay compact in the envelope.
 */
function shIntrospectArgvHint(string $trimmed): string
{
    $hint = preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed;
    if (strlen($hint) > 120) {
        $hint = substr($hint, 0, 117) . '...';
    }
    return $hint;
}

/**
 * P4: structured risk findings explaining max_risk and each unresolved source.
 *
 * Combines: a finding per unresolved dynamic source, and a finding per critical
 * command (eval / sh -c / curl|sh / rm -rf expansion). Deterministic ordering
 * by (line, code).
 *
 * @param array{max_risk:string,has_mutation:bool,has_dynamic_execution:bool,has_unresolved_source:bool} $riskSummary
 * @param array<int,array<string,mixed>> $sources
 * @param array<int,array<string,mixed>> $commands
 * @return array<int,array<string,mixed>>
 */
/**
 * A source is "unresolved" when its target is dynamic (variable / command
 * substitution) AND it was not statically resolved to an existing sibling file.
 * A resolved sibling source (e.g. the common
 * `$(dirname "${BASH_SOURCE[0]}")/common.sh` pattern that exists on disk) is
 * treated as resolved.
 *
 * @param array<string,mixed> $src
 */
function shIntrospectSourceIsUnresolved(array $src): bool
{
    $target = (string) ($src['target'] ?? '');
    if ($target === '') {
        return false;
    }
    $isDynamic = str_contains($target, '$') || str_contains($target, '`') || str_contains($target, '(');
    if (!$isDynamic) {
        return false;
    }
    // Resolved to an existing sibling => no longer unresolved.
    if (!empty($src['resolved']) && !empty($src['exists'])) {
        return false;
    }
    return true;
}

function shIntrospectBuildRiskFindings(array $riskSummary, array $sources, array $commands): array
{
    $findings = [];

    // Unresolved-source findings.
    foreach ($sources as $src) {
        if (shIntrospectSourceIsUnresolved($src)) {
            $findings[] = [
                'code' => 'unresolved-source',
                'risk' => 'unknown',
                'line' => (int) ($src['line'] ?? 0),
                'detail' => 'dynamic source target could not be statically resolved: ' . (string) ($src['target'] ?? ''),
            ];
        }
    }

    // Critical / high command findings.
    foreach ($commands as $cmd) {
        $risk = (string) ($cmd['risk'] ?? '');
        if ($risk === 'critical' || $risk === 'high') {
            $findings[] = [
                'code' => (string) ($cmd['effect'] ?? 'command'),
                'risk' => $risk,
                'line' => (int) ($cmd['line'] ?? 0),
                'detail' => (string) ($cmd['name'] ?? '') . ': ' . (string) ($cmd['argv_hint'] ?? ''),
            ];
        }
    }

    usort($findings, static function (array $a, array $b): int {
        return [$a['line'], $a['code']] <=> [$b['line'], $b['code']];
    });

    return $findings;
}

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
    // Match $(dirname "${BASH_SOURCE[0]}")/REST  or  $(dirname "$0")/REST.
    if (!preg_match(
        '#^\$\(\s*dirname\s+"?\$\{?(?:BASH_SOURCE\[0\]|0)\}?"?\s*\)/(.+)$#',
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

/**
 * Best-effort side-effect classification.
 *
 * Read-only effects: filesystem-read (rg/fd/cat/preview), git-read (git
 * grep/log/diff/show/rev-parse). Mutating effects: filesystem-write (`>`/`>>`,
 * tee, rm/mv/cp, sed -i, find -delete) and git-mutation (reset/checkout/clean/
 * restore/add/commit/push).
 *
 * @param array<int,string> $codeLines
 * @return array<int,array<string,mixed>> items { type, confidence }
 */
function shIntrospectExtractSideEffects(array $codeLines): array
{
    $found = [];
    $add = static function (string $type, int $confidence) use (&$found): void {
        if (!isset($found[$type]) || $confidence > $found[$type]) {
            $found[$type] = $confidence;
        }
    };

    foreach ($codeLines as $line) {
        // --- mutating: git ---
        if (preg_match('/\bgit\b.*\b(reset|checkout|clean|restore|add|commit|push|rebase|merge|tag)\b/', $line)) {
            $add('git-mutation', 85);
        }
        // --- mutating: filesystem ---
        // Skip comment lines so prose like "must not truncate them" does not
        // register as a filesystem mutation.
        $codeOnly = preg_replace('/(^|\s)#.*$/', '', $line) ?? $line;
        if (preg_match('/\b(rm|mv|cp)\s+/', $codeOnly)
            || preg_match('/\bsed\s+-i\b/', $codeOnly)
            || preg_match('/\bfind\b.*-delete\b/', $codeOnly)
            || preg_match('/(?:^|[\s;&|(])truncate\s+-/', $codeOnly)
            || preg_match('/\bchmod\s+(?:-[A-Za-z]*\s+)*-R\b|\bchmod\s+-R\b/', $codeOnly)
            || preg_match('/\bchown\s+(?:-[A-Za-z]*\s+)*-R\b|\bchown\s+-R\b/', $codeOnly)
            || preg_match('/\brsync\b.*--delete\b/', $codeOnly)
            || preg_match('/\btee\b/', $codeOnly)) {
            $add('filesystem-write', 80);
        }
        // Write redirection to a real file target. This is intentionally
        // conservative: a redirection operator (optional fd, `>`/`>>`) directly
        // followed (no space) by a filename token that looks like a path, quoted
        // string, or variable. This excludes jq/test comparisons (`> 0`, `>=`),
        // `/dev/null` sinks, `>&` fd dups, and `> $(...)`-style noise.
        if (preg_match('/(?:^|[\s;|&])\d*>>?(["\'\/~.$][^\s&|;()<]*)/', $line, $rm)) {
            $target = $rm[1];
            if ($target !== ''
                && !str_starts_with($target, '/dev/')
                && !str_starts_with($target, '$(')
                && !str_contains($target, '/dev/null')) {
                $add('filesystem-write', 70);
            }
        }

        // --- read-only: git ---
        if (preg_match('/\bgit\b.*\b(grep|log|diff|show|rev-parse|ls-files|blame|config)\b/', $line)) {
            $add('git-read', 75);
        }
        // --- read-only: filesystem ---
        if (preg_match('/\b(rg|fd|fdfind|cat|head|tail|sort)\b/', $line)
            || preg_match('/preview-file/', $line)) {
            $add('filesystem-read', 70);
        }
    }

    $out = [];
    foreach ($found as $type => $confidence) {
        $out[] = ['type' => $type, 'confidence' => $confidence];
    }
    // Deterministic ordering by type.
    usort($out, static fn(array $a, array $b): int => strcmp((string) $a['type'], (string) $b['type']));
    return $out;
}

/**
 * Compute a coarse risk_summary from side effects, sources, and dynamic
 * execution markers. Never returns null fields.
 *
 * @param array<int,string> $codeLines
 * @param array<int,array<string,mixed>> $sideEffects
 * @param array<int,array<string,mixed>> $sources
 * @return array{max_risk:string,has_mutation:bool,has_dynamic_execution:bool,has_unresolved_source:bool}
 */
function shIntrospectRiskSummary(array $codeLines, array $sideEffects, array $sources): array
{
    $hasMutation = false;
    foreach ($sideEffects as $se) {
        $type = (string) $se['type'];
        if ($type === 'git-mutation' || $type === 'filesystem-write') {
            $hasMutation = true;
            break;
        }
    }

    // Dynamic execution: eval, bash -c, sh -c, and `source $0`/`. $0` (dynamic
    // self re-execution). Detect only command-position forms so literal
    // references inside jq/printf strings (e.g. `contains("eval(")` or
    // `rule=eval`) do not produce false positives.
    $hasDynamic = false;
    foreach ($codeLines as $line) {
        // `eval` as a command word: at statement start or after a command
        // separator, followed by whitespace and an argument (not `eval(`).
        if (preg_match('/(?:^|[;&|]|\b(?:then|else|do)\s)\s*eval\s+[^(]/', $line)
            || preg_match('/(?:^\s*|[;&|(]\s*|\bxargs\s+)(?:bash|sh)\s+-c\b/', $line)
            || preg_match('/(?:^\s*|[;&|]\s*|\b(?:then|else|do)\s+)(?:source|\.)\s+["\']?(?:\$\{?0\}?|\$\{?BASH_SOURCE)/', $line)) {
            $hasDynamic = true;
            break;
        }
    }

    // Unresolved source: a `source`/`.` whose target is a variable or command
    // substitution rather than a static literal path.
    $hasUnresolvedSource = false;
    foreach ($sources as $src) {
        if (shIntrospectSourceIsUnresolved($src)) {
            $hasUnresolvedSource = true;
            break;
        }
    }

    // Derive max_risk.
    if ($hasDynamic || $hasMutation) {
        $maxRisk = 'high';
    } elseif ($hasUnresolvedSource) {
        $maxRisk = 'unknown';
    } elseif ($sideEffects !== []) {
        $maxRisk = 'low';
    } else {
        $maxRisk = 'unknown';
    }

    return [
        'max_risk' => $maxRisk,
        'has_mutation' => $hasMutation,
        'has_dynamic_execution' => $hasDynamic,
        'has_unresolved_source' => $hasUnresolvedSource,
    ];
}

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
        $lines[] = '  ' . $family . $annotation($family) . ':';
        $names = array_keys($byFamily[$family]);
        sort($names, SORT_STRING);
        foreach ($names as $n) {
            $desc = $byFamily[$family][$n];
            if ($desc !== '') {
                $lines[] = '    ' . str_pad($n, $nameWidth) . '  ' . $desc;
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
 * Build the aligned "Params:" block. Each line shows the primary flag plus any
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

        $display = $name;
        if ($aliases !== []) {
            $display .= ' | ' . implode(' | ', $aliases);
        }

        $takesValue = !empty($param['takes_value']);
        $repeatable = !empty($param['repeatable']);
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
        $line = $indent . str_pad($row['display'], $nameWidth) . '  ' . str_pad($row['type'], $typeWidth);
        // Suppress the redundant "(repeatable)" note when the description already
        // conveys repeatability (the `+` type already signals it too).
        $note = $row['note'];
        if ($note !== '' && stripos($row['description'], 'repeatable') !== false) {
            $note = '';
        }
        $tail = trim(($note !== '' ? $note . ' ' : '') . $row['description']);
        if ($tail !== '') {
            $line .= '  ' . $tail;
        }
        return rtrim($line);
    };

    // When no group metadata exists, keep the flat (legacy) layout.
    if (!$anyGroup) {
        $lines = ['Params:'];
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

    $lines = ['Params:'];
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
