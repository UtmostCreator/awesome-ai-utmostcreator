<?php

declare(strict_types=1);

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

    // 0) Inline statically-resolvable, inside-repo sourced modules so a thin
    //    facade entrypoint (e.g. scripts/ai/ai-search.sh) is introspected with
    //    its real contract. Text-only; the target is never executed. When no
    //    such modules are resolvable this is a no-op (returns $raw unchanged).
    $expanded = shIntrospectInlineSources($raw, $file);
    if ($expanded !== $raw) {
        $warnings[] = 'contract aggregated from sourced modules (see inlined markers); target not executed';
        $raw = $expanded;
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
    $riskSummary = shIntrospectRiskSummary($codeLines, $sideEffects, $sources, $commands);
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
