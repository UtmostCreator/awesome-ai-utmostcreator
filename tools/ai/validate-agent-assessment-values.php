<?php

declare(strict_types=1);

/**
 * Validates the human-authored agent_assessment VALUES SOURCE file (D3a),
 * docs/ai/agent-scores.yaml, against schemas/ai/agent-assessment-values.schema.json.
 *
 * D3a contract enforced here (no YAML extension required — restricted-subset
 * line parser, matching tools/ai/validate-script-access.php):
 *   - top-level `schema: ai.agent_assessment.values/v1` and `approved: <bool>`
 *   - keyed by CANONICAL agent TEMPLATE key (basename under
 *     packages/ai-universal-rules/templates/{core,optional}/agents/*.md), NEVER a
 *     generated display name
 *   - every live agent template has EXACTLY ONE source entry (no missing/stale)
 *   - every entry defines agent_assessment.risk_level (low|medium|high|critical)
 *   - every entry defines agent_assessment.decision (approve|approve_with_minor_fixes|
 *     needs_refactor|block)
 *   - every entry has a non-empty rationale
 *   - NO numeric rubric field is present (score/confidence/role_clarity/...), unless
 *     --numeric-enabled is passed (reserved for the D3c numeric pass)
 *
 * The `approved` flag is reported but is NOT a validation failure: a draft
 * (`approved: false`) file is valid as a draft; downstream renderers (D3b) are
 * responsible for refusing to consume an unapproved source.
 *
 * Usage:
 *   php tools/ai/validate-agent-assessment-values.php [PATH] [--root=PATH] [--numeric-enabled]
 * Exit: 0 valid, 1 violation, 2 usage/IO error.
 */

/** @return list<string> allowed categorical field names in v1 */
function aiAavCategoricalFields(): array
{
    return ['risk_level', 'decision'];
}

/** @return list<string> numeric rubric field names gated to D3c */
function aiAavNumericFields(): array
{
    return [
        'score', 'confidence', 'role_clarity', 'scope_control', 'permission_safety',
        'output_contract', 'evidence_required', 'verification_strength', 'handoff_quality',
    ];
}

/** @return list<string> valid risk_level enum */
function aiAavRiskEnum(): array
{
    return ['low', 'medium', 'high', 'critical'];
}

/** @return list<string> valid decision enum */
function aiAavDecisionEnum(): array
{
    return ['approve', 'approve_with_minor_fixes', 'needs_refactor', 'block'];
}

/**
 * Discovers canonical agent template keys (basenames) under the core+optional
 * agent template dirs.
 *
 * @return list<string>
 */
function aiAavTemplateKeys(string $root): array
{
    $patterns = [
        $root . '/packages/ai-universal-rules/templates/core/agents/*.md',
        $root . '/packages/ai-universal-rules/templates/optional/agents/*.md',
    ];
    $keys = [];
    foreach ($patterns as $pattern) {
        foreach (glob($pattern) ?: [] as $file) {
            $keys[basename($file, '.md')] = true;
        }
    }
    $list = array_keys($keys);
    sort($list);

    return $list;
}

/**
 * Parses the restricted-subset values source file.
 *
 * Recognised shape (2-space indent steps):
 *   schema: <str>
 *   approved: <true|false>
 *   agents:
 *     <key>:
 *       agent_assessment:
 *         <field>: <value>
 *       rationale: "<str>"
 *
 * @return array{schema:?string,approved:?string,agents:array<string,array{assessment:array<string,string>,rationale:?string}>}
 */
function aiAavParse(string $yaml): array
{
    $out = ['schema' => null, 'approved' => null, 'agents' => []];
    $section = null;       // null | 'agents'
    $curAgent = null;
    $inAssessment = false;

    foreach (preg_split('/\R/', $yaml) ?: [] as $line) {
        // strip full-line comments and blank lines
        if (preg_match('/^\s*#/', $line) || trim($line) === '') {
            continue;
        }
        // strip inline comments outside quotes (rationale strings are quoted, so a
        // ' #' inside a quoted rationale is preserved; an unquoted trailing comment
        // such as `approved: false  # draft` is removed before matching).
        if (!preg_match('/["\']/', $line)) {
            $line = (string) preg_replace('/\s+#.*$/', '', $line);
            if (trim($line) === '') {
                continue;
            }
        }
        // top-level keys
        if (preg_match('/^schema:\s*(\S.*?)\s*$/', $line, $m)) {
            $out['schema'] = trim($m[1]);
            $section = null;
            continue;
        }
        if (preg_match('/^approved:\s*(\S+)\s*$/', $line, $m)) {
            $out['approved'] = trim($m[1]);
            $section = null;
            continue;
        }
        if (preg_match('/^agents:\s*$/', $line)) {
            $section = 'agents';
            continue;
        }
        if ($section !== 'agents') {
            continue;
        }
        // agent key (2-space indent)
        if (preg_match('/^  ([A-Za-z0-9][A-Za-z0-9_-]*):\s*$/', $line, $m)) {
            $curAgent = $m[1];
            $out['agents'][$curAgent] = ['assessment' => [], 'rationale' => null];
            $inAssessment = false;
            continue;
        }
        if ($curAgent === null) {
            continue;
        }
        // agent_assessment: block opener (4-space indent)
        if (preg_match('/^    agent_assessment:\s*$/', $line)) {
            $inAssessment = true;
            continue;
        }
        // rationale (4-space indent) — closes the assessment block
        if (preg_match('/^    rationale:\s*(.+?)\s*$/', $line, $m)) {
            $inAssessment = false;
            $val = trim($m[1]);
            // strip surrounding quotes if present
            if (preg_match('/^"(.*)"$/', $val, $q) || preg_match("/^'(.*)'$/", $val, $q)) {
                $val = $q[1];
            }
            $out['agents'][$curAgent]['rationale'] = $val;
            continue;
        }
        // assessment field (6-space indent)
        if ($inAssessment && preg_match('/^      ([A-Za-z0-9_]+):\s*(\S.*?)\s*$/', $line, $m)) {
            $out['agents'][$curAgent]['assessment'][$m[1]] = trim($m[2]);
            continue;
        }
    }

    return $out;
}

/**
 * @param list<string> $argv
 */
function aiAavMain(array $argv): int
{
    // Script lives in tools/ai/, so the repo root is two levels up.
    $root = realpath(__DIR__ . '/..' . '/..');
    $path = null;
    $numericEnabled = false;
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--numeric-enabled') {
            $numericEnabled = true;
        } elseif (str_starts_with($arg, '--root=')) {
            $candidate = realpath(substr($arg, 7));
            if ($candidate === false) {
                fwrite(STDERR, "ERROR: --root path not found\n");
                return 2;
            }
            $root = $candidate;
        } elseif (!str_starts_with($arg, '--')) {
            $path = $arg;
        }
    }
    if ($root === false) {
        fwrite(STDERR, "ERROR: repository root not found\n");
        return 2;
    }
    if ($path === null) {
        $path = $root . '/docs/ai/agent-scores.yaml';
    }
    if (!is_file($path)) {
        fwrite(STDERR, "ERROR: source file not found: {$path}\n");
        return 2;
    }

    $parsed = aiAavParse((string) file_get_contents($path));
    $errors = [];

    // top-level schema id
    if ($parsed['schema'] !== 'ai.agent_assessment.values/v1') {
        $errors[] = "schema must be 'ai.agent_assessment.values/v1', got '" . ($parsed['schema'] ?? '(missing)') . "'";
    }
    // approved flag must be an explicit boolean literal
    $approvedRaw = $parsed['approved'];
    if ($approvedRaw !== 'true' && $approvedRaw !== 'false') {
        $errors[] = "approved must be a boolean (true|false), got '" . ($approvedRaw ?? '(missing)') . "'";
    }
    $approved = $approvedRaw === 'true';

    $templateKeys = aiAavTemplateKeys($root);
    if ($templateKeys === []) {
        $errors[] = 'no agent templates discovered (cannot check coverage)';
    }
    $templateSet = array_fill_keys($templateKeys, true);
    $sourceKeys = array_keys($parsed['agents']);

    // coverage: missing source entries
    foreach ($templateKeys as $key) {
        if (!isset($parsed['agents'][$key])) {
            $errors[] = "missing source entry for live agent template '{$key}'";
        }
    }
    // coverage: stale source entries (key not a live template = also catches generated display names)
    foreach ($sourceKeys as $key) {
        if (!isset($templateSet[$key])) {
            $errors[] = "stale/unknown source key '{$key}' (no matching live agent template; do not use generated display names)";
        }
    }

    $numericFields = array_fill_keys(aiAavNumericFields(), true);
    $riskEnum = aiAavRiskEnum();
    $decisionEnum = aiAavDecisionEnum();

    foreach ($parsed['agents'] as $key => $entry) {
        $a = $entry['assessment'];
        // required categorical fields
        if (!isset($a['risk_level'])) {
            $errors[] = "{$key}: missing agent_assessment.risk_level";
        } elseif (!in_array($a['risk_level'], $riskEnum, true)) {
            $errors[] = "{$key}: risk_level '{$a['risk_level']}' not in [" . implode('|', $riskEnum) . ']';
        }
        if (!isset($a['decision'])) {
            $errors[] = "{$key}: missing agent_assessment.decision";
        } elseif (!in_array($a['decision'], $decisionEnum, true)) {
            $errors[] = "{$key}: decision '{$a['decision']}' not in [" . implode('|', $decisionEnum) . ']';
        }
        // numeric fields gated to D3c
        foreach (array_keys($a) as $field) {
            if (isset($numericFields[$field]) && !$numericEnabled) {
                $errors[] = "{$key}: numeric rubric field '{$field}' is not allowed in v1 (D3a); gated to the D3c numeric pass (--numeric-enabled)";
            }
            if (!in_array($field, aiAavCategoricalFields(), true) && !isset($numericFields[$field])) {
                $errors[] = "{$key}: unknown agent_assessment field '{$field}'";
            }
        }
        // rationale required + non-empty
        if ($entry['rationale'] === null || $entry['rationale'] === '') {
            $errors[] = "{$key}: missing non-empty rationale (source-only; never rendered)";
        }
    }

    if ($errors !== []) {
        fwrite(STDERR, "ERROR: agent-assessment values source violation(s) in {$path}:\n");
        foreach ($errors as $message) {
            fwrite(STDERR, ' - ' . $message . "\n");
        }
        return 1;
    }

    $count = count($parsed['agents']);
    $state = $approved ? 'APPROVED (renderers may consume)' : 'DRAFT (approved: false — renderers MUST NOT consume yet)';
    fwrite(STDOUT, "OK: {$count} agent entries valid; 1:1 with live templates; categorical-only. State: {$state}\n");

    return 0;
}

if (PHP_SAPI === 'cli' && isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit(aiAavMain($argv));
}
