<?php

declare(strict_types=1);

$root = realpath(__DIR__ . '/..' . '/..');
if ($root === false) {
    fwrite(STDERR, "ERROR: repository root not found\n");
    exit(1);
}

$checkOnly = in_array('--check', $argv, true);
$policyPath = $root . '/packages/ai-universal-rules/policies/ai-file-standards.json';

$policy = loadAiFileStandardsPolicy($policyPath);
if ($policy === null) {
    exit(1);
}

$lineLimits = (array) ($policy['line_limits'] ?? []);
if ($lineLimits === []) {
    fwrite(STDERR, "ERROR: ai-file-standards policy contains no line_limits\n");
    exit(1);
}

$targets = [
    'docs/ai/ai-file-standards.md' => renderAiFileStandardsMarkdown($lineLimits, false),
    'packages/ai-universal-rules/templates/core/ai-file-standards.template.md' => renderAiFileStandardsMarkdown($lineLimits, true),
];

$ok = true;
foreach ($targets as $relativePath => $content) {
    $ok = compareOrWrite($root, $relativePath, $content, $checkOnly) && $ok;
}

exit($ok ? 0 : 1);

function loadAiFileStandardsPolicy(string $path): ?array
{
    if (!is_file($path)) {
        fwrite(STDERR, "ERROR: missing ai-file-standards policy at {$path}\n");
        return null;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "ERROR: invalid ai-file-standards policy JSON\n");
        return null;
    }

    return $decoded;
}

function compareOrWrite(string $root, string $relativePath, string $content, bool $checkOnly): bool
{
    $fullPath = $root . '/' . $relativePath;
    $existing = is_file($fullPath) ? (string) file_get_contents($fullPath) : null;

    if ($existing !== null && normalizeNewlines($existing) === normalizeNewlines($content)) {
        fwrite(STDOUT, "OK: {$relativePath} is up to date\n");
        return true;
    }

    if ($checkOnly) {
        fwrite(STDERR, "ERROR: {$relativePath} is stale; run php tools/ai/generate-ai-file-standards.php\n");
        return false;
    }

    $dir = dirname($fullPath);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        fwrite(STDERR, "ERROR: failed to create directory {$dir}\n");
        return false;
    }

    if (file_put_contents($fullPath, $content) === false) {
        fwrite(STDERR, "ERROR: failed to write {$relativePath}\n");
        return false;
    }

    fwrite(STDOUT, "WROTE: {$relativePath}\n");
    return true;
}

function normalizeNewlines(string $value): string
{
    return str_replace(["\r\n", "\r"], "\n", $value);
}

function renderAiFileStandardsMarkdown(array $lineLimits, bool $isTemplate): string
{
    $title = $isTemplate ? '# <PROJECT_NAME> AI File Standards' : '# AI File Standards';
    $intro = $isTemplate
        ? "Use this file as the installed repository's canonical content and size contract for AI workflow files."
        : 'Use this file as the canonical content and size contract for AI workflow files in this repository and in installed target repositories.';

    $md = $title . "\n\n";
    $md .= $intro . "\n\n";
    $md .= "## Purpose\n\n";
    $md .= "Keep AI workflow files small, single-purpose, reference-driven, and generated from one source where practical.\n\n";

    $md .= "## Primitive Roles\n\n";
    $md .= "| Primitive | Owns | Must not own |\n";
    $md .= "| --- | --- | --- |\n";
    $md .= "| `AGENTS.md` | global rules, source-of-truth routing, safety defaults | full workflows, role bodies, long test matrices |\n";
    $md .= "| `.github/copilot-instructions.md` | Copilot entry routing and repo summary | canonical policy duplicated from `docs/ai/` |\n";
    $md .= "| `.github/instructions/*.instructions.md` | path or topic rules with deterministic `applyTo` | task workflows or agent personas |\n";
    $md .= "| `.github/agents/*.agent.md` | persistent Copilot role, tool boundary, handoff contract | capability examples or long checklists |\n";
    $md .= "| `.github/prompts/*.prompt.md` | one-shot task launchers | durable policy, tool permissions, full procedures |\n";
    $md .= "| `.github/skills/*/SKILL.md` | Copilot-loadable capability adapter | project-wide rules or unrelated workflows |\n";
    $md .= "| `.opencode/agents/*.md` | OpenCode role, mode, permission, handoff contract | duplicated capability procedure |\n";
    $md .= "| `.opencode/commands/*.md` | short slash-command wrapper | long procedures or permission policy |\n";
    $md .= "| `.opencode/skills/*/SKILL.md` | OpenCode-loadable capability adapter | global instructions or agent persona |\n";
    $md .= "| `docs/ai/capabilities/*/CAPABILITY.md` | canonical reusable behavior | adapter-specific syntax |\n";
    $md .= "| `checklist.md` | pass/fail execution steps | examples or reference essays |\n";
    $md .= "| `gotchas.md` | recurring traps and safe response | generic warnings without detection |\n";
    $md .= "| `reference.md` or `references.md` | ranked links to source docs, scripts, schemas | copied external content |\n";
    $md .= "| `examples.md` | 2-4 high-signal examples | exhaustive transcripts |\n\n";

    $md .= "## Source Hierarchy\n\n";
    $md .= "When files disagree, resolve conflicts in this order:\n\n";
    $md .= "1. user request\n";
    $md .= "2. current git diff and working tree\n";
    $md .= "3. source code\n";
    $md .= "4. tests\n";
    $md .= "5. schemas and contracts\n";
    $md .= "6. runtime and config files\n";
    $md .= "7. canonical docs under `docs/ai/`\n";
    $md .= "8. adapter files under `.github/` and `.opencode/`\n";
    $md .= "9. generated files\n";
    $md .= "10. historical notes\n\n";
    $md .= "Generated files are context unless their generator contract explicitly marks them as committed canonical artifacts.\n\n";

    $md .= "## Line Budgets\n\n";
    $md .= "Generated outputs are excluded from line limits, but must not be manually edited.\n\n";
    $md .= "| File type | Ideal | Soft max | Hard max |\n";
    $md .= "| --- | ---: | ---: | ---: |\n";
    foreach ($lineLimits as $rule) {
        $label = markdownLabelForRule((string) ($rule['id'] ?? ''), (string) ($rule['label'] ?? ''));
        $ideal = (int) ($rule['target_min'] ?? 0) . '-' . (int) ($rule['target_max'] ?? 0);
        $soft = (string) ((int) ($rule['warn_above'] ?? 0));
        $hard = (string) ((int) ($rule['fail_above'] ?? 0));
        $md .= "| {$label} | {$ideal} | {$soft} | {$hard} |\n";
    }
    $md .= "\n";

    $md .= "## Split Rules\n\n";
    $md .= "Split a file before adding more when any of these are true:\n\n";
    $md .= "- it has more than three responsibilities\n";
    $md .= "- it has more than five top-level modes or subcommands\n";
    $md .= "- repeated blocks exceed 25 lines\n";
    $md .= "- a script mixes parsing, validation, rendering, and filesystem writes\n";
    $md .= "- a prompt, command, skill, or agent repeats a full capability body\n\n";

    $md .= "## Adapter Rules\n\n";
    $md .= "- Capabilities are canonical procedure.\n";
    $md .= "- Skills adapt capabilities for runtime loading.\n";
    $md .= "- Prompts and commands launch one task and point to capabilities or skills.\n";
    $md .= "- Agents define role posture, tool or permission boundaries, and handoff outputs.\n";
    $md .= "- Instructions define stable defaults and path-specific rules.\n";
    $md .= "- Runtime adapters may repeat short critical routing, but must not duplicate long procedure.\n\n";

    $md .= "## Enforcement Expectations\n\n";
    $md .= "- Hard max violations should fail validation unless the path is generated or explicitly allowlisted.\n";
    $md .= "- Soft max violations should warn and include a split recommendation.\n";
    $md .= "- Adapter drift checks should fail when rendered surfaces disagree with their source.\n";
    $md .= "- Broken references should fail for installable docs, instructions, agents, skills, prompts, and commands.\n";
    $md .= "- OpenCode agents should use `permission`, not deprecated `tools`.\n";
    $md .= "- Copilot instruction files should use `applyTo` when deterministic application matters.\n";

    return $md;
}

function markdownLabelForRule(string $id, string $fallback): string
{
    $map = [
        'root-instructions' => 'Root `AGENTS.md`',
        'copilot-instructions' => '`.github/copilot-instructions.md`',
        'copilot-path-instructions' => '`.github/instructions/*.instructions.md`',
        'copilot-agents' => '`.github/agents/*.agent.md`',
        'copilot-prompts' => '`.github/prompts/*.prompt.md`',
        'copilot-skills' => '`.github/skills/*/SKILL.md`',
        'opencode-agents' => '`.opencode/agents/*.md`',
        'opencode-commands' => '`.opencode/commands/*.md`',
        'opencode-skills' => '`.opencode/skills/*/SKILL.md`',
        'package-root-instructions-template' => 'Root `AGENTS.md` template',
        'package-copilot-instructions-template' => '`.github/copilot-instructions.md` template',
        'package-instruction-templates' => 'Instruction templates',
        'package-agent-templates' => 'Agent templates',
        'package-workflow-templates' => 'Workflow templates',
        'package-command-templates' => 'Command templates',
        'package-capability-docs' => '`CAPABILITY.md`',
        'package-capability-checklists' => '`checklist.md`',
        'package-capability-gotchas' => '`gotchas.md`',
        'package-capability-references' => '`reference.md` / `references.md`',
        'package-capability-examples' => '`examples.md`'
    ];

    return $map[$id] ?? $fallback;
}
