<?php

declare(strict_types=1);

/**
 * validate-agent-spec.php
 *
 * Deterministic Static Validator for the Agent Creator Edition pipeline.
 *
 *   Supervisor -> Creator -> AgentSpec JSON -> [this validator] -> Semantic Verifier -> approval -> render
 *
 * This layer is intentionally NOT an LLM. It enforces structure, required
 * fields, allow-listed tools, autonomy ceilings, the non-negotiable forbidden
 * baseline, and the human-approval hard gate before any agent is rendered and
 * shipped. It complements (does not replace) the Semantic Verifier and the
 * runtime guardrails.
 *
 * Usage:
 *   php tools/ai/validate-agent-spec.php path/to/agent-spec.json   # validate one spec
 *   php tools/ai/validate-agent-spec.php --self-test               # prove the detector works
 *
 * Exit codes: 0 = ok (ship-eligible), 1 = violations found, 2 = usage/IO error.
 */

const AGENT_SPEC_TOOL_ALLOWLIST = [
    'read_repo_files',
    'preview_file',
    'ai_search',
    'query_usage',
    'git_readonly',
    'run_validator',
    'write_repo_files',
    'run_tests',
    'web_search',
    'execute_code',
];

const AGENT_SPEC_REQUIRED_FORBIDDEN = [
    'self-modification',
    'create agents',
    'access secrets',
];

const AGENT_SPEC_BANNED_INSTRUCTION_SUBSTRINGS = [
    'ignore previous instructions',
    'ignore all previous',
    'disregard the rules',
    'bypass validation',
    'disable guardrails',
    'exfiltrate',
    'read secrets',
];

/** Maximum risk_level the static validator will pass without escalation. */
const AGENT_SPEC_RISK_RANK = ['low' => 1, 'medium' => 2, 'high' => 3];

function agentSpecRepoRoot(): string
{
    return dirname(__DIR__, 1) === ''
        ? getcwd() ?: '.'
        : dirname(__DIR__, 2);
}

/**
 * @param array<int,string> $errors
 * @param array<int,string> $warnings
 */
function agentSpecValidate(array $spec, string $root, array &$errors, array &$warnings): void
{
    // --- required top-level fields ---
    $required = [
        'spec_version', 'name', 'purpose', 'mode', 'risk_level',
        'allowed_tasks', 'forbidden_tasks', 'tools', 'capabilities',
        'output_format', 'success_criteria', 'autonomy', 'approval',
    ];
    foreach ($required as $field) {
        if (!array_key_exists($field, $spec)) {
            $errors[] = "missing required field: {$field}";
        }
    }
    if ($errors !== []) {
        return; // structural gaps make deeper checks meaningless
    }

    // --- name shape (renders to a filename) ---
    if (!is_string($spec['name']) || preg_match('/^[a-z][a-z0-9-]{2,48}[a-z0-9]$/', $spec['name']) !== 1) {
        $errors[] = "name must be lowercase hyphen-separated id (got: " . var_export($spec['name'], true) . ')';
    }

    // --- purpose ---
    if (!is_string($spec['purpose']) || strlen(trim($spec['purpose'])) < 12) {
        $errors[] = 'purpose must be a descriptive string of at least 12 characters';
    }

    // --- mode ---
    if (!in_array($spec['mode'] ?? null, ['all', 'subagent'], true)) {
        $errors[] = "mode must be 'all' or 'subagent'";
    }

    // --- risk_level ---
    $risk = is_string($spec['risk_level'] ?? null) ? $spec['risk_level'] : '';
    if (!isset(AGENT_SPEC_RISK_RANK[$risk])) {
        $errors[] = "risk_level must be low, medium, or high";
    }

    // --- output_format ---
    if (!in_array($spec['output_format'] ?? null, ['structured_markdown', 'structured_json', 'diff', 'plain_text'], true)) {
        $errors[] = 'output_format must be one of structured_markdown, structured_json, diff, plain_text';
    }

    // --- tools allow-list ---
    $tools = is_array($spec['tools'] ?? null) ? $spec['tools'] : null;
    if ($tools === null) {
        $errors[] = 'tools must be an array (use [] for a text-only agent)';
    } else {
        foreach ($tools as $tool) {
            if (!is_string($tool) || !in_array($tool, AGENT_SPEC_TOOL_ALLOWLIST, true)) {
                $errors[] = "tool not in allow-list: " . var_export($tool, true);
            }
        }
    }

    // --- forbidden baseline must be present ---
    $forbidden = is_array($spec['forbidden_tasks'] ?? null) ? $spec['forbidden_tasks'] : [];
    $forbiddenJoined = strtolower(implode(' || ', array_map('strval', $forbidden)));
    foreach (AGENT_SPEC_REQUIRED_FORBIDDEN as $needle) {
        if (!str_contains($forbiddenJoined, $needle)) {
            $errors[] = "forbidden_tasks must explicitly include a '{$needle}' entry";
        }
    }

    // --- banned instruction phrases anywhere in task text ---
    $taskBlob = strtolower(implode(' ', array_merge(
        is_array($spec['allowed_tasks'] ?? null) ? array_map('strval', $spec['allowed_tasks']) : [],
        is_array($spec['success_criteria'] ?? null) ? array_map('strval', $spec['success_criteria']) : [],
        [is_string($spec['purpose'] ?? null) ? $spec['purpose'] : ''],
    )));
    foreach (AGENT_SPEC_BANNED_INSTRUCTION_SUBSTRINGS as $banned) {
        if (str_contains($taskBlob, $banned)) {
            $errors[] = "banned instruction phrase detected: '{$banned}'";
        }
    }

    // --- autonomy ceilings ---
    $autonomy = is_array($spec['autonomy'] ?? null) ? $spec['autonomy'] : null;
    if ($autonomy === null) {
        $errors[] = 'autonomy must be an object';
    } else {
        $maxSteps = $autonomy['max_steps'] ?? null;
        if (!is_int($maxSteps) || $maxSteps < 1 || $maxSteps > 50) {
            $errors[] = 'autonomy.max_steps must be an integer between 1 and 50';
        }
        if (($autonomy['self_modification'] ?? null) !== false) {
            $errors[] = 'autonomy.self_modification must be false';
        }
        if (($autonomy['may_create_agents'] ?? null) !== false) {
            $errors[] = 'autonomy.may_create_agents must be false (only the Creator pipeline may create agents)';
        }
        if (!array_key_exists('network_access', $autonomy) || !is_bool($autonomy['network_access'])) {
            $errors[] = 'autonomy.network_access must be a boolean';
        }
        if (!array_key_exists('file_write', $autonomy) || !is_bool($autonomy['file_write'])) {
            $errors[] = 'autonomy.file_write must be a boolean';
        }

        // Capability/tool/autonomy coherence.
        $declaredTools = $tools ?? [];
        if (($autonomy['file_write'] ?? false) === true && !in_array('write_repo_files', $declaredTools, true)) {
            $errors[] = 'autonomy.file_write is true but tools does not include write_repo_files';
        }
        if (($autonomy['network_access'] ?? false) === true && !in_array('web_search', $declaredTools, true)) {
            $errors[] = 'autonomy.network_access is true but tools does not include web_search';
        }
    }

    // --- approval hard gate ---
    $approval = is_array($spec['approval'] ?? null) ? $spec['approval'] : null;
    if ($approval === null) {
        $errors[] = 'approval must be an object';
    } else {
        if (($approval['requires_human_approval'] ?? null) !== true) {
            $errors[] = 'approval.requires_human_approval must be true (hard gate)';
        }
        if (!array_key_exists('approved_by', $approval)) {
            $errors[] = 'approval.approved_by must be present (null while pending)';
        } elseif ($approval['approved_by'] === null) {
            $warnings[] = 'spec is structurally valid but not yet approved (approval.approved_by is null) — Supervisor/human must approve before ship';
        }
    }

    // --- risk vs autonomy escalation ---
    if (($risk === 'high') && ($autonomy['file_write'] ?? false) === true) {
        $warnings[] = "risk_level high with file_write true — Semantic Verifier and human approval are mandatory before ship";
    }

    // --- capabilities must exist in repo when resolvable ---
    $capDir = $root . '/docs/ai/capabilities';
    $caps = is_array($spec['capabilities'] ?? null) ? $spec['capabilities'] : [];
    if (is_dir($capDir)) {
        foreach ($caps as $cap) {
            if (is_string($cap) && !is_dir($capDir . '/' . $cap)) {
                $warnings[] = "capability '{$cap}' has no docs/ai/capabilities/{$cap} folder in this repo";
            }
        }
    }
}

function agentSpecLoad(string $path): array
{
    if (!is_file($path)) {
        fwrite(STDERR, "ERROR: spec file not found: {$path}\n");
        exit(2);
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "ERROR: spec file is not valid JSON object: {$path}\n");
        exit(2);
    }

    return $decoded;
}

function agentSpecSelfTest(): int
{
    $root = agentSpecRepoRoot();

    $valid = [
        'spec_version' => '1.0.0',
        'name' => 'repo-readme-reviewer',
        'purpose' => 'Review README structure for a configuration repository',
        'mode' => 'subagent',
        'risk_level' => 'low',
        'allowed_tasks' => ['review documentation', 'identify missing sections'],
        'forbidden_tasks' => ['edit files', 'commit changes', 'self-modification', 'create agents', 'access secrets'],
        'tools' => ['read_repo_files', 'preview_file'],
        'capabilities' => [],
        'output_format' => 'structured_markdown',
        'success_criteria' => ['find missing README sections'],
        'autonomy' => [
            'max_steps' => 8,
            'self_modification' => false,
            'may_create_agents' => false,
            'network_access' => false,
            'file_write' => false,
        ],
        'approval' => ['requires_human_approval' => true, 'approved_by' => 'maintainer'],
    ];

    $errors = [];
    $warnings = [];
    agentSpecValidate($valid, $root, $errors, $warnings);
    if ($errors !== []) {
        fwrite(STDERR, "self-test FAIL: clean spec rejected: " . implode('; ', $errors) . "\n");
        return 1;
    }

    // Planted violations: missing forbidden baseline, self-modification true, bad tool, no approval.
    $dirty = $valid;
    $dirty['forbidden_tasks'] = ['edit files'];
    $dirty['tools'] = ['delete_everything'];
    $dirty['autonomy']['self_modification'] = true;
    $dirty['autonomy']['may_create_agents'] = true;
    $dirty['approval']['requires_human_approval'] = false;

    $errors = [];
    $warnings = [];
    agentSpecValidate($dirty, $root, $errors, $warnings);
    if ($errors === []) {
        fwrite(STDERR, "self-test FAIL: planted violations not detected\n");
        return 1;
    }

    fwrite(STDOUT, "self-test OK: detector passes a clean spec and rejects planted violations\n");
    return 0;
}

$arg = $argv[1] ?? '';
if ($arg === '--self-test') {
    exit(agentSpecSelfTest());
}
if ($arg === '' || $arg === '--help' || $arg === '-h') {
    fwrite(STDOUT, "Usage: php tools/ai/validate-agent-spec.php <spec.json> | --self-test\n");
    exit($arg === '' ? 2 : 0);
}

$spec = agentSpecLoad($arg);
$root = agentSpecRepoRoot();
$errors = [];
$warnings = [];
agentSpecValidate($spec, $root, $errors, $warnings);

foreach ($warnings as $warning) {
    fwrite(STDOUT, "WARN: {$warning}\n");
}
foreach ($errors as $error) {
    fwrite(STDERR, "ERROR: {$error}\n");
}

if ($errors === []) {
    $name = is_string($spec['name'] ?? null) ? $spec['name'] : 'agent';
    fwrite(STDOUT, "OK: agent spec '{$name}' passed static validation\n");
}

exit($errors === [] ? 0 : 1);
