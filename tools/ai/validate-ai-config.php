<?php

declare(strict_types=1);

$targetArg = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--target=')) {
        $targetArg = substr($arg, 9);
    }
}

$root = $targetArg !== null ? realpath($targetArg) : realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');

if ($root === false) {
    fwrite(STDERR, "ERROR: could not resolve repository root\n");
    exit(1);
}

if ($targetArg !== null || (!is_dir($root . DIRECTORY_SEPARATOR . 'packages') && is_file($root . DIRECTORY_SEPARATOR . '.ai-install-manifest.json'))) {
    $errors = [];
    foreach (['AGENTS.md', 'docs/ai/project-context.md', 'docs/ai/POST-INSTALL.md', 'scripts/ai/ai-search.sh', 'tools/ai/validate-install-surface.php', '.ai-install-manifest.json'] as $required) {
        if (!file_exists($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $required))) {
            $errors[] = "missing required target AI config path: {$required}";
        }
    }
    $manifest = json_decode((string) @file_get_contents($root . DIRECTORY_SEPARATOR . '.ai-install-manifest.json'), true);
    if (!is_array($manifest) || !isset($manifest['profile'], $manifest['packs'], $manifest['files'])) {
        $errors[] = 'target .ai-install-manifest.json is missing required fields';
    }
    foreach ($errors as $error) {
        fwrite(STDERR, "ERROR: {$error}\n");
    }
    if ($errors === []) {
        fwrite(STDOUT, "OK: target AI config validation passed\n");
    }
    exit($errors === [] ? 0 : 1);
}

$requiredFiles = [
    'README.md',
    'AGENTS.md',
    'CLAUDE.md',
    'docs/ai/project-context.md',
    'docs/ai/workflow.md',
    'docs/ai/execution-protocol.md',
    'docs/ai/agents.md',
    'docs/ai/failure-handling.md',
    'docs/ai/agent-ops-checklist.md',
    'docs/ai/integration-matrix.md',
    'docs/ai/script-registry.md',
    'docs/ai/script-registry.json',
    'docs/ai/command-policy.md',
    'docs/ai/command-policy.tiers.yaml',
    '.schemas/ai-command-policy.schema.json',
    'docs/ai/script-registry.schema.json',
    'docs/ai/scripts-reference.md',
    'docs/ai/tools/actions/use-ai-script.md',
    'docs/ai/AI-GUARDRAILS.md',
    'docs/ai/catalog.md',
    'docs/ai/capabilities/README.md',
    'docs/ai/capabilities/authorization-and-tool-governance/CAPABILITY.md',
    'docs/ai/capabilities/agent-observability-and-evidence/CAPABILITY.md',
    'docs/ai/capabilities/agent-observability-and-evidence/EVENT_SCHEMA.md',
    'docs/ai/capabilities/agent-observability-and-evidence/FAILURE_TAXONOMY.md',
    'docs/ai/capabilities/evaluation-and-regression/CAPABILITY.md',
    'docs/ai/capabilities/evaluation-and-regression/GOLDEN_TASKS.md',
    'docs/ai/capabilities/evaluation-and-regression/REPLAY_RULES.md',
    'docs/ai/capabilities/evaluation-and-regression/HUMAN_REVIEW_RULES.md',
    'docs/ai/capabilities/preview-environments/CAPABILITY.md',
    'docs/ai/capabilities/preview-environments/LIFECYCLE.md',
    'docs/ai/capabilities/preview-environments/DATA_AND_SECRET_RULES.md',
    'docs/ai/capabilities/preview-environments/CHECKLIST.md',
    'docs/ai/capabilities/service-boundary-patterns/CAPABILITY.md',
    'docs/ai/capabilities/evidence-first-execution/CAPABILITY.md',
    '.github/copilot-instructions.md',
    '.github/instructions/ai-workflow.instructions.md',
    '.github/instructions/architecture.instructions.md',
    '.github/instructions/base.instructions.md',
    '.github/instructions/security.instructions.md',
    '.github/instructions/approval-boundaries.instructions.md',
    '.github/instructions/generated-artifacts.instructions.md',
    '.github/instructions/ai-tooling.instructions.md',
    '.github/instructions/php.instructions.md',
    '.github/instructions/shell.instructions.md',
    '.github/instructions/composer.instructions.md',
    '.github/instructions/config-infra.instructions.md',
    '.github/instructions/ci-workflows.instructions.md',
    '.github/instructions/frontend.instructions.md',
    '.github/instructions/targets.instructions.md',
    '.github/instructions/testing.instructions.md',
    '.github/instructions/execution-protocol.instructions.md',
    'scripts/ai/common.sh',
    'scripts/ai/ai-diff-context.sh',
    'scripts/ai/ai-search.sh',
    'scripts/ai/ai-edit.sh',
    'scripts/ai/ai-verify.sh',
    'scripts/ai/ai-rollback.sh',
    'tools/ai/validate-command-policy.php',
    'tools/ai/render-agent-permissions.php',
    'policies/copilot/policy.yaml',
    '.schemas/evidence-event.schema.json',
    '.ai-logs/README.md',
    'packages/ai-universal-rules/manifest.json',
    'packages/ai-universal-rules/catalog.json',
    'packages/ai-universal-rules/docs/BROWSE.md',
    'llms.txt',
    'opencode.jsonc',
];

$requiredDirectories = [
    // 'reference/php/design-patterns',
    // 'reference/php/design-principles',
    // 'reference/php/php-built-ins',
];

$liveFiles = [
    'README.md',
    'AGENTS.md',
    'CLAUDE.md',
    'docs/ai/project-context.md',
    'docs/ai/workflow.md',
    'docs/ai/execution-protocol.md',
    'docs/ai/agents.md',
    'docs/ai/failure-handling.md',
    'docs/ai/agent-ops-checklist.md',
    'docs/ai/integration-matrix.md',
    'docs/ai/AI-GUARDRAILS.md',
    'docs/ai/catalog.md',
    'docs/ai/script-registry.md',
    'docs/ai/script-registry.json',
    'docs/ai/command-policy.md',
    'docs/ai/command-policy.tiers.yaml',
    '.schemas/ai-command-policy.schema.json',
    'docs/ai/script-registry.schema.json',
    'docs/ai/scripts-reference.md',
    'docs/ai/tools/actions/use-ai-script.md',
    'docs/ai/capabilities/README.md',
    '.github/copilot-instructions.md',
    '.github/instructions/ai-workflow.instructions.md',
    '.github/instructions/architecture.instructions.md',
    '.github/instructions/base.instructions.md',
    '.github/instructions/security.instructions.md',
    '.github/instructions/approval-boundaries.instructions.md',
    '.github/instructions/generated-artifacts.instructions.md',
    '.github/instructions/ai-tooling.instructions.md',
    '.github/instructions/php.instructions.md',
    '.github/instructions/shell.instructions.md',
    '.github/instructions/composer.instructions.md',
    '.github/instructions/config-infra.instructions.md',
    '.github/instructions/ci-workflows.instructions.md',
    '.github/instructions/frontend.instructions.md',
    '.github/instructions/targets.instructions.md',
    '.github/instructions/testing.instructions.md',
    '.github/instructions/execution-protocol.instructions.md',
    '.github/agents/config-maintainer.agent.md',
    '.github/agents/workflow-auditor.agent.md',
    'docs/ai/capabilities/project-context/CAPABILITY.md',
    'docs/ai/capabilities/verify-change/CAPABILITY.md',
    'docs/ai/capabilities/review-diff/CAPABILITY.md',
    'docs/ai/capabilities/bug-regression/CAPABILITY.md',
    'docs/ai/capabilities/docs-sync/CAPABILITY.md',
    'docs/ai/capabilities/config-change-safety/CAPABILITY.md',
    'docs/ai/capabilities/authorization-and-tool-governance/CAPABILITY.md',
    'docs/ai/capabilities/agent-observability-and-evidence/CAPABILITY.md',
    'docs/ai/capabilities/evaluation-and-regression/CAPABILITY.md',
    'docs/ai/capabilities/preview-environments/CAPABILITY.md',
    'docs/ai/capabilities/service-boundary-patterns/CAPABILITY.md',
    'docs/ai/capabilities/evidence-first-execution/CAPABILITY.md',
    'packages/ai-universal-rules/manifest.json',
    'packages/ai-universal-rules/catalog.json',
    'packages/ai-universal-rules/docs/BROWSE.md',
    'CONTRIBUTING.md',
    'SECURITY.md',
    'SUPPORT.md',
    'llms.txt',
    'opencode.jsonc',
];

$agnosticRules = loadAgnosticLeakRules($root);
$bannedTerms = $agnosticRules['banned_terms'];
$allowedLeakPaths = $agnosticRules['allowed_paths'];

$generatedCatalogFiles = [
    'docs/ai/catalog.md',
    'packages/ai-universal-rules/catalog.json',
    'packages/ai-universal-rules/docs/BROWSE.md',
];

$allowedLivePlaceholderFiles = [
    'README.md',
    '.github/instructions/ai-tooling.instructions.md',
    '.github/instructions/frontend.instructions.md',
    '.github/instructions/testing.instructions.md',
];

/**
 * Load the canonical placeholder dictionary so we can distinguish intentional
 * template tokens from typo-style leaks. The dictionary is the union of every
 * `<UPPERCASE_TOKEN>` referenced in PLACEHOLDERS.md.
 *
 * @return array<int, string> Sorted list of documented placeholder tokens.
 */
function loadDocumentedPlaceholders(string $root): array
{
    $path = $root . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'ai-universal-rules' . DIRECTORY_SEPARATOR . 'PLACEHOLDERS.md';
    if (!is_file($path)) {
        return [];
    }
    $content = (string) file_get_contents($path);
    if (!preg_match_all('/`(<[A-Z0-9_]+>)`/', $content, $matches)) {
        return [];
    }
    $tokens = array_values(array_unique($matches[1]));
    sort($tokens);
    return $tokens;
}

$documentedPlaceholders = loadDocumentedPlaceholders($root);

$errors = [];
$warnings = [];
$oks = [];

foreach ($requiredFiles as $relativePath) {
    if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath))) {
        $errors[] = "missing required file: {$relativePath}";
    }
}

foreach ($liveFiles as $relativePath) {
    $absolutePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (!is_file($absolutePath)) {
        continue;
    }

    $content = file_get_contents($absolutePath);

    if ($content === false) {
        $errors[] = "unable to read file: {$relativePath}";
        continue;
    }

    $contentForPlaceholderScan = preg_replace('/<!--.*?-->/s', '', $content) ?: $content;

    if (
        !in_array($relativePath, $generatedCatalogFiles, true)
        && !in_array($relativePath, $allowedLivePlaceholderFiles, true)
        && preg_match_all('/<[^>\s]+>/', $contentForPlaceholderScan, $angleMatches) > 0
    ) {
        // Dictionary-aware placeholder check.
        //
        // This repo is a template kit; canonical docs deliberately carry
        // `<UPPERCASE_TOKEN>` placeholders so downstream installers can
        // substitute project-specific values. A token is only a "leak" when
        // it is NOT documented in packages/ai-universal-rules/PLACEHOLDERS.md
        // and is NOT a recognised non-placeholder construct (HTML-style tags,
        // generic CLI usage hints, code-fence echoes, etc.).
        $undocumented = [];
        foreach ($angleMatches[0] as $rawToken) {
            // Documented uppercase placeholder, e.g. <PROJECT_NAME>.
            if (in_array($rawToken, $documentedPlaceholders, true)) {
                continue;
            }
            // Lowercase or mixed-case angle constructs are CLI usage hints or HTML, not placeholders.
            if (!preg_match('/^<[A-Z][A-Z0-9_]*>$/', $rawToken)) {
                continue;
            }
            $undocumented[] = $rawToken;
        }
        if ($undocumented !== []) {
            $errors[] = 'undocumented placeholder token(s) in ' . $relativePath . ': ' . implode(', ', array_values(array_unique($undocumented)));
        }
    }

    $allowLeakScan = false;
    foreach ($allowedLeakPaths as $allowedPathPrefix) {
        if (str_starts_with($relativePath, $allowedPathPrefix)) {
            $allowLeakScan = true;
            break;
        }
    }

    if (!$allowLeakScan) {
        foreach ($bannedTerms as $term) {
            if (stripos($content, $term) !== false) {
                $warnings[] = "unexpected stack term '{$term}' in {$relativePath}";
            }
        }
    }

    if (in_array($relativePath, $generatedCatalogFiles, true)) {
        continue;
    }

    foreach (extractBacktickPaths($content) as $path) {
        if (shouldSkipPathCheck($path)) {
            continue;
        }

        $normalizedPath = trim($path);
        $candidates = [];
        $candidates[] = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);

        $baseDir = dirname($relativePath);
        if ($baseDir !== '.' && $baseDir !== '') {
            $candidates[] = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $baseDir . '/' . $normalizedPath);
        }

        $exists = false;
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $errors[] = "broken path reference in {$relativePath}: {$path}";
        }
    }
}

$agentsContent = safeRead($root, 'AGENTS.md');
$claudeContent = safeRead($root, 'CLAUDE.md');
$readmeContent = safeRead($root, 'README.md');
$copilotContent = safeRead($root, '.github/copilot-instructions.md');
$projectContextContent = safeRead($root, 'docs/ai/project-context.md');
$agentsReferenceContent = safeRead($root, 'docs/ai/agents.md');

$liveAgentPaths = glob($root . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR . '*.agent.md') ?: [];

foreach ($liveAgentPaths as $path) {
    $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root) + 1));

    if ($agentsReferenceContent !== null && strpos($agentsReferenceContent, $relativePath) === false) {
        $errors[] = "docs/ai/agents.md must reference live agent {$relativePath}";
    }
}

foreach ($requiredDirectories as $relativePath) {
    if (!is_dir($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath))) {
        $errors[] = "missing required directory: {$relativePath}";
    }
}

$hookTargets = [
    '.github/hooks/tool-policy.json' => [
        'scripts/ai/pre-tool-use.sh',
        'scripts/ai/post-tool-use.sh',
    ],
    '.github/hooks/tool-guardian.json' => [
        '.github/hooks/scripts/tool-guardian.ps1',
    ],
];

foreach ($hookTargets as $hookConfig => $targets) {
    if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $hookConfig))) {
        continue;
    }

    foreach ($targets as $target) {
        if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target))) {
            $errors[] = "hook target missing for {$hookConfig}: {$target}";
        }
    }
}

if ($agentsContent !== null && strpos($agentsContent, 'docs/ai/project-context.md') === false) {
    $errors[] = 'AGENTS.md must reference docs/ai/project-context.md';
}

if ($agentsContent !== null && strpos($agentsContent, 'docs/ai/agents.md') === false) {
    $errors[] = 'AGENTS.md must reference docs/ai/agents.md';
}

if ($agentsContent !== null && strpos($agentsContent, 'docs/ai/failure-handling.md') === false) {
    $errors[] = 'AGENTS.md must reference docs/ai/failure-handling.md';
}

if ($agentsContent !== null && strpos($agentsContent, 'docs/ai/agent-ops-checklist.md') === false) {
    $warnings[] = 'AGENTS.md should reference docs/ai/agent-ops-checklist.md';
}

if ($agentsContent !== null && strpos($agentsContent, 'docs/ai/integration-matrix.md') === false) {
    $warnings[] = 'AGENTS.md should reference docs/ai/integration-matrix.md';
}

if ($copilotContent !== null && strpos($copilotContent, 'docs/ai/') === false) {
    $errors[] = '.github/copilot-instructions.md must reference docs/ai/';
}

if ($copilotContent !== null && stripos($copilotContent, 'approval-free') === false) {
    $warnings[] = '.github/copilot-instructions.md should document approval-free read-only commands';
}

if ($copilotContent !== null && strpos($copilotContent, 'docs/ai/failure-handling.md') === false) {
    $warnings[] = '.github/copilot-instructions.md should reference docs/ai/failure-handling.md';
}

if ($copilotContent !== null && strpos($copilotContent, 'docs/ai/script-registry.md') === false) {
    $warnings[] = '.github/copilot-instructions.md should reference docs/ai/script-registry.md';
}

if ($copilotContent !== null && strpos($copilotContent, 'docs/ai/script-registry.json') === false) {
    $warnings[] = '.github/copilot-instructions.md should reference docs/ai/script-registry.json';
}

if ($copilotContent !== null && strpos($copilotContent, 'docs/ai/agent-ops-checklist.md') === false) {
    $warnings[] = '.github/copilot-instructions.md should reference docs/ai/agent-ops-checklist.md';
}

if ($copilotContent !== null && strpos($copilotContent, 'docs/ai/integration-matrix.md') === false) {
    $warnings[] = '.github/copilot-instructions.md should reference docs/ai/integration-matrix.md';
}

if ($copilotContent !== null && strpos($copilotContent, 'docs/ai/capabilities/agent-observability-and-evidence/CAPABILITY.md') === false) {
    $warnings[] = '.github/copilot-instructions.md should reference docs/ai/capabilities/agent-observability-and-evidence/CAPABILITY.md for traceable agent output expectations';
}

if ($copilotContent !== null && strpos($copilotContent, 'docs/ai/capabilities/evaluation-and-regression/CAPABILITY.md') === false) {
    $warnings[] = '.github/copilot-instructions.md should reference docs/ai/capabilities/evaluation-and-regression/CAPABILITY.md for behavior-regression expectations';
}

if ($copilotContent !== null && strpos($copilotContent, 'docs/ai/capabilities/preview-environments/CAPABILITY.md') === false) {
    $warnings[] = '.github/copilot-instructions.md should reference docs/ai/capabilities/preview-environments/CAPABILITY.md for temporary environment validation guidance';
}

if ($copilotContent !== null) {
//     foreach (['reference/php/design-patterns/', 'reference/php/design-principles/', 'reference/php/php-built-ins/'] as $phpReferencePath) {
//         if (strpos($copilotContent, $phpReferencePath) === false) {
//             $warnings[] = ".github/copilot-instructions.md should reference {$phpReferencePath} for PHP guidance routing";
//         }
//     }
}

if ($projectContextContent !== null) {
//     foreach (['reference/php/design-patterns/', 'reference/php/design-principles/', 'reference/php/php-built-ins/'] as $phpReferencePath) {
//         if (strpos($projectContextContent, $phpReferencePath) === false) {
//             $warnings[] = "docs/ai/project-context.md should reference {$phpReferencePath}";
//         }
//     }
}

$copilotToolingContent = safeRead($root, 'docs/ai/copilot-tooling.md');

if ($copilotToolingContent !== null) {
    foreach (['scripts/ai/common.sh', 'scripts/ai/ai-search.sh', 'scripts/ai/ai-edit.sh', 'scripts/ai/ai-verify.sh', 'scripts/ai/ai-diff-context.sh', 'scripts/ai/ai-rollback.sh', 'scripts/ai/rg-code.sh', 'scripts/ai/gh-pr-context.sh'] as $scriptReference) {
        if (strpos($copilotToolingContent, $scriptReference) === false) {
            $warnings[] = "docs/ai/copilot-tooling.md should reference {$scriptReference}";
        }
    }

    foreach (['bundle-plan.json', 'WATCH_DEBOUNCE_MS', 'failureCategory'] as $capabilityReference) {
        if (strpos($copilotToolingContent, $capabilityReference) === false) {
            $warnings[] = "docs/ai/copilot-tooling.md should mention {$capabilityReference} now that the stronger tool layer supports it";
        }
    }
}

$justfileContent = safeRead($root, 'justfile');

if ($justfileContent !== null) {
    foreach (['scripts/ai/ai-search.sh', 'scripts/ai/ai-edit.sh', 'scripts/ai/ai-verify.sh', 'scripts/ai/ai-diff-context.sh', 'scripts/ai/ai-rollback.sh', 'scripts/ai/gh-pr-context.sh', 'scripts/ai/rg-code.sh', 'scripts/ai/repomix-scc-router.sh'] as $scriptReference) {
        if (strpos($justfileContent, $scriptReference) === false) {
            $warnings[] = "justfile should expose {$scriptReference} when the script is part of the supported tool layer";
        }
    }

    foreach (['context-plan-since', 'context-pack-all-since', 'context-plan-json', 'verify', 'rollback-list'] as $recipeReference) {
        if (strpos($justfileContent, $recipeReference) === false) {
            $warnings[] = "justfile should expose {$recipeReference} for the stronger guarded tool surface";
        }
    }

    foreach (['php-patterns-search', 'php-principles-search', 'php-builtins-search', 'php-examples-map'] as $recipeReference) {
        if (strpos($justfileContent, $recipeReference) === false) {
            $warnings[] = "justfile should expose {$recipeReference} for PHP example corpus navigation";
        }
    }
}

if ($readmeContent !== null) {
    if (stripos($readmeContent, 'AI workflow') === false) {
        $warnings[] = 'README.md should describe the repo AI workflow purpose';
    }

    if (stripos($readmeContent, 'configuration') === false) {
        $warnings[] = 'README.md should describe the repo config purpose';
    }

//     foreach (['reference/php/design-patterns/', 'reference/php/design-principles/', 'reference/php/php-built-ins/'] as $phpReferencePath) {
//         if (strpos($readmeContent, $phpReferencePath) === false) {
//             $warnings[] = "README.md should reference {$phpReferencePath} in AI workflow and tooling guidance";
//         }
//     }
}

if ($claudeContent !== null && strpos($claudeContent, 'docs/ai/') === false) {
    $warnings[] = 'CLAUDE.md should point back to canonical docs/ai guidance';
}

if ($claudeContent !== null && strpos($claudeContent, 'docs/ai/failure-handling.md') === false) {
    $warnings[] = 'CLAUDE.md should reference docs/ai/failure-handling.md';
}

if ($claudeContent !== null && strpos($claudeContent, 'docs/ai/agent-ops-checklist.md') === false) {
    $warnings[] = 'CLAUDE.md should reference docs/ai/agent-ops-checklist.md';
}

if ($claudeContent !== null && strpos($claudeContent, 'docs/ai/integration-matrix.md') === false) {
    $warnings[] = 'CLAUDE.md should reference docs/ai/integration-matrix.md';
}

$aiWiringRequiredFiles = [
    'docs/ai/tools/ai-search.md',
    'docs/ai/tools/tool-map.md',
    'docs/ai/tools/actions/search-evidence.md',
    'docs/ai/tools/actions/preview-file.md',
    'scripts/ai/preview-file.sh',
    'tests/scripts/ai/test-preview-file.sh',
    '.github/instructions/ai-search.instructions.md',
    '.github/instructions/ai-tooling.instructions.md',
    '.github/prompts/search-evidence.prompt.md',
    '.github/agents/repository-researcher.agent.md',
    '.opencode/opencode.json',
    '.opencode/commands/search-evidence.md',
    '.opencode/commands/verify-ai-wiring.md',
    '.opencode/agents/repository-researcher.md',
    '.opencode/agents/repository-reviewer.md',
    '.opencode/skills/ai-search/SKILL.md',
    'packages/ai-universal-rules/templates/core/opencode.json',
    'packages/ai-universal-rules/templates/core/agents/repository-researcher.md',
    'packages/ai-universal-rules/templates/core/agents/repository-reviewer.md',
    'packages/ai-universal-rules/templates/commands/search-evidence.md',
    'packages/ai-universal-rules/templates/commands/verify-ai-wiring.md',
    'packages/ai-universal-rules/templates/skills/ai-search/SKILL.md',
    'packages/ai-universal-rules/templates/instructions/ai-search.instructions.md',
    'packages/ai-universal-rules/templates/instructions/ai-tooling.instructions.md',
    'packages/ai-universal-rules/templates/workflows/search-evidence.md',
    'packages/ai-universal-rules/templates/workflows/review-search-tool.md',
    'packages/ai-universal-rules/templates/github/pull_request_template.md',
    'packages/ai-universal-rules/templates/github/workflows/validate-ai-surface.yml',
    'packages/ai-universal-rules/templates/github/workflows/test-external-install.yml',
    'packages/ai-universal-rules/templates/github/workflows/export-ai-universal-rules-preview.yml',
];

foreach ($aiWiringRequiredFiles as $relativePath) {
    if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath))) {
        $errors[] = "missing required AI wiring file: {$relativePath}";
    }
}

$requiredSnippets = ['AI_OUTPUT=json bash scripts/ai/ai-search.sh', 'changed', 'staged', 'tracked', 'schema', 'unsafe-all', 'AI_ALLOW_UNLIMITED=1', 'unsafe_blocked', 'dry_run', 'bash scripts/ai/query-usage.sh', 'bash scripts/ai/ai-verify.sh', 'secrets'];
$previewRequiredSnippets = ['AI_OUTPUT=json bash scripts/ai/preview-file.sh', '--range', '--around', '--max-columns', '--max-bytes', '--force', 'schema', 'status', 'tool', 'path', 'range', 'content', 'warnings', 'errors'];
$wiringSources = ['AGENTS.md', 'docs/ai/tools/ai-search.md', 'docs/ai/tools/tool-map.md', 'docs/ai/tools/actions/preview-file.md', '.github/copilot-instructions.md', '.github/instructions/ai-tooling.instructions.md', '.opencode/commands/search-evidence.md', '.opencode/commands/verify-ai-wiring.md', '.opencode/agents/repository-researcher.md', '.opencode/agents/repository-reviewer.md', '.opencode/skills/ai-search/SKILL.md', 'scripts/ai/preview-file.sh'];
$combined = '';
foreach ($wiringSources as $src) {
    $combined .= "\n" . (safeRead($root, $src) ?? '');
}
foreach ($requiredSnippets as $snippet) {
    if (strpos($combined, $snippet) === false) {
        $errors[] = "missing required AI wiring content snippet: {$snippet}";
    }
}

foreach ($previewRequiredSnippets as $snippet) {
    if (strpos($combined, $snippet) === false) {
        $errors[] = "missing required preview-file wiring content snippet: {$snippet}";
    }
}

$opencodeConfig = loadJsonFile($root, '.opencode/opencode.json', $errors);
if (is_array($opencodeConfig)) {
    validateOpenCodePermissions($opencodeConfig, $errors);
}

if ($errors === []) {
    $oks[] = $warnings === []
        ? 'rootAIworkflowvalidationpassed'
        : 'rootAIworkflowvalidationpassedwithwarnings';
}

foreach ($oks as $message) {
    fwrite(STDOUT, "OK: {$message}\n");
}

foreach ($warnings as $message) {
    fwrite(STDOUT, "WARN: {$message}\n");
}

foreach ($errors as $message) {
    fwrite(STDERR, "ERROR: {$message}\n");
}

exit($errors === [] ? 0 : 1);

function loadJsonFile(string $root, string $relativePath, array &$errors): ?array
{
    $content = safeRead($root, $relativePath);

    if ($content === null) {
        $errors[] = "missing JSON config file: {$relativePath}";
        return null;
    }

    $decoded = json_decode($content, true);

    if (!is_array($decoded)) {
        $errors[] = "invalid JSON config file: {$relativePath}";
        return null;
    }

    return $decoded;
}

function validateOpenCodePermissions(array $config, array &$errors): void
{
    $permission = $config['permission'] ?? null;

    if (!is_array($permission)) {
        $errors[] = '.opencode/opencode.json missing permission object';
        return;
    }

    if (($permission['*'] ?? null) !== 'ask') {
        $errors[] = '.opencode/opencode.json permission.* must be ask';
    }

    $bash = $permission['bash'] ?? null;
    if (!is_array($bash)) {
        $errors[] = '.opencode/opencode.json permission.bash must be an object';
    } else {
        requirePermissionValue($bash, '*', ['ask', 'deny'], 'permission.bash.*', $errors);

        foreach (['git status*', 'git diff*', 'git log*'] as $pattern) {
            requirePermissionValue($bash, $pattern, ['allow'], "permission.bash {$pattern}", $errors);
        }

        foreach ([
            'bash scripts/ai/ai-search.sh *',
            'AI_OUTPUT=json bash scripts/ai/ai-search.sh *',
            'env AI_OUTPUT=json bash scripts/ai/ai-search.sh *',
            'bash scripts/ai/preview-file.sh *',
            'AI_OUTPUT=json bash scripts/ai/preview-file.sh *',
            'env AI_OUTPUT=json bash scripts/ai/preview-file.sh *',
            'bash scripts/ai/query-usage.sh *',
            'bash scripts/ai/ai-diff-context.sh *',
            'bash scripts/ai/git-forensics.sh *',
            'bash scripts/ai/ai-doc-check.sh *',
            'bash scripts/ai/ai-test-select.sh *',
            'bash scripts/ai/ai-task.sh *',
            'bash scripts/ai/ai-structured.sh *',
        ] as $pattern) {
            requirePermissionValue($bash, $pattern, ['allow', 'ask'], "safe wrapper {$pattern}", $errors);
        }

        foreach ([
            'bash scripts/ai/ai-verify.sh *',
            'bash scripts/ai/pack-context.sh *',
            'bash scripts/ai/run-repomix-context.sh *',
            'bash scripts/ai/repomix-context-tree.sh *',
            'bash scripts/ai/repomix-scc-router.sh *',
            'bash scripts/ai/ai-edit.sh *',
            'bash scripts/ai/ai-rollback.sh *',
            'bash scripts/ai/install-mandatory-tools.sh *',
        ] as $pattern) {
            requirePermissionValue($bash, $pattern, ['ask'], "mutating or broad wrapper {$pattern}", $errors);
        }

        foreach (['grep *', 'rg *', 'find *', 'fd *', 'cat *', 'sed *', 'awk *'] as $pattern) {
            requirePermissionValue($bash, $pattern, ['ask'], "raw search/read command {$pattern}", $errors);
        }

        foreach (['rm *', 'chown *', 'sudo *', 'git push*'] as $pattern) {
            requirePermissionValue($bash, $pattern, ['deny'], "destructive command {$pattern}", $errors);
        }

        foreach (['mv *', 'cp *', 'chmod *', 'git reset*', 'git clean*'] as $pattern) {
            requirePermissionValue($bash, $pattern, ['ask'], "mutating command {$pattern}", $errors);
        }
    }

    $read = $permission['read'] ?? null;
    if (!is_array($read)) {
        $errors[] = '.opencode/opencode.json permission.read must be an object';
    } else {
        requirePermissionValue($read, '*', ['allow'], 'permission.read.*', $errors);
        foreach (['.env', '.env.*', '*.pem', '*.key', '*.crt'] as $pattern) {
            requirePermissionValue($read, $pattern, ['deny'], "permission.read {$pattern}", $errors);
        }
    }

    $edit = $permission['edit'] ?? null;
    if (!is_array($edit)) {
        $errors[] = '.opencode/opencode.json permission.edit must be an object';
    } else {
        requirePermissionValue($edit, '*', ['ask', 'deny'], 'permission.edit.*', $errors);
        foreach (['docs/ai/generated/**', '.opencode/**', '.github/agents/**', '.github/instructions/**', '.github/prompts/**', '.github/prompts-optional/**', '.github/skills/**', '.github/workflows/**', '.github/copilot-instructions.md', '.github/pull_request_template.md', '.env', '.env.*', '*.pem', '*.key', '*.crt'] as $pattern) {
            requirePermissionValue($edit, $pattern, ['deny'], "permission.edit {$pattern}", $errors);
        }
    }

    foreach (['grep', 'glob', 'list'] as $tool) {
        $toolPermission = $permission[$tool] ?? null;
        if (!is_array($toolPermission)) {
            $errors[] = ".opencode/opencode.json permission.{$tool} must be an object";
            continue;
        }

        requirePermissionValue($toolPermission, '*', ['ask', 'deny'], "permission.{$tool}.*", $errors);
    }

    $skill = $permission['skill'] ?? null;
    if (!is_array($skill)) {
        $errors[] = '.opencode/opencode.json permission.skill must be an object';
    } else {
        requirePermissionValue($skill, '*', ['ask'], 'permission.skill.*', $errors);
        foreach (['ai-search', 'ai-verification', 'ai-context'] as $skillName) {
            requirePermissionValue($skill, $skillName, ['allow'], "permission.skill {$skillName}", $errors);
        }
    }

    foreach (['external_directory', 'doom_loop'] as $guard) {
        if (($permission[$guard] ?? null) !== 'ask') {
            $errors[] = ".opencode/opencode.json permission.{$guard} must be ask";
        }
    }
}

function requirePermissionValue(array $permissions, string $pattern, array $allowedValues, string $label, array &$errors): void
{
    $value = $permissions[$pattern] ?? null;

    if (!is_string($value) || !in_array($value, $allowedValues, true)) {
        $errors[] = $label . ' must be ' . implode(' or ', $allowedValues);
    }
}

function safeRead(string $root, string $relativePath): ?string
{
    $absolutePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (!is_file($absolutePath)) {
        return null;
    }

    $content = file_get_contents($absolutePath);

    return $content === false ? null : $content;
}

function extractBacktickPaths(string $content): array
{
    preg_match_all('/`([^`]+)`/', $content, $matches);

    $paths = [];

    foreach ($matches[1] as $candidate) {
        $trimmed = trim($candidate);

        if (
            str_contains($trimmed, '/') ||
            str_ends_with($trimmed, '.md') ||
            str_ends_with($trimmed, '.json') ||
            str_ends_with($trimmed, '.php') ||
            str_ends_with($trimmed, '.ps1')
        ) {
            $paths[] = $trimmed;
        }
    }

    return array_values(array_unique($paths));
}

function shouldSkipPathCheck(string $path): bool
{
    if ($path === '' || preg_match('/\s/', $path) === 1) {
        return true;
    }

    if (preg_match('#^(search|read|edit|execute|vscode|agent|web|todo)/#', $path) === 1) {
        return true;
    }

    if (str_starts_with($path, 'docs/ai/generated/task-context/')) {
        return true;
    }

    if (in_array($path, ['.agent.md', '.prompt.md', 'tools:'], true)) {
        return true;
    }

    foreach (['*', '{', '}', '<', '>', 'http://', 'https://', ',', '->'] as $fragment) {
        if (str_contains($path, $fragment)) {
            return true;
        }
    }

    return false;
}

function loadAgnosticLeakRules(string $root): array
{
    $defaults = [
        'banned_terms' => ['Statamic', 'Nuxt', 'Vue 3', 'PHPUnit 11'],
        'allowed_paths' => [],
    ];

    $rulesPath = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'rules' . DIRECTORY_SEPARATOR . 'agnostic-leak-rules.json';

    if (!is_file($rulesPath)) {
        return $defaults;
    }

    $raw = file_get_contents($rulesPath);
    if ($raw === false) {
        return $defaults;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $defaults;
    }

    $bannedTerms = $decoded['banned_terms'] ?? $defaults['banned_terms'];
    $allowedPaths = $decoded['allowed_paths'] ?? [];

    return [
        'banned_terms' => is_array($bannedTerms) ? array_values(array_filter($bannedTerms, 'is_string')) : $defaults['banned_terms'],
        'allowed_paths' => is_array($allowedPaths) ? array_values(array_filter($allowedPaths, 'is_string')) : [],
    ];
}
