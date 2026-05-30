<?php

declare(strict_types=1);

const AI_DIR_MODE = 0755;

function aiRepoRoot(): string
{
    $root = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');

    if ($root === false) {
        throw new RuntimeException('Could not resolve repository root.');
    }

    return $root;
}

function aiNormalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}

function aiAbsolutePath(string $root, string $relativePath): string
{
    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
}

function aiNormalizeGeneratedContent(string $content): string
{
    return str_replace("\r\n", "\n", $content);
}

function aiReadFile(string $root, string $relativePath): string
{
    $content = @file_get_contents(aiAbsolutePath($root, $relativePath));

    if ($content === false) {
        throw new RuntimeException("Unable to read {$relativePath}.");
    }

    return $content;
}

function aiLoadJson(string $root, string $relativePath): array
{
    $decoded = json_decode(aiReadFile($root, $relativePath), true);

    if (!is_array($decoded)) {
        throw new RuntimeException("Invalid JSON in {$relativePath}.");
    }

    return $decoded;
}

/**
 * @return list<string>
 */
function aiListFilesInDirectory(string $directory): array
{
    if (!is_dir($directory)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }

        $files[] = aiNormalizePath($item->getPathname());
    }

    sort($files);

    return $files;
}

function aiParseFrontMatter(string $content): array
{
    if (!str_starts_with($content, "---\n")) {
        return [];
    }

    $end = strpos($content, "\n---\n", 4);

    if ($end === false) {
        return [];
    }

    $block = substr($content, 4, $end - 4);
    $lines = preg_split('/\r?\n/', $block) ?: [];
    $data = [];

    foreach ($lines as $line) {
        if (!str_contains($line, ':')) {
            continue;
        }

        [$key, $value] = explode(':', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        $data[$key] = $value;
    }

    return $data;
}

function aiExtractTitle(string $content, string $fallback): string
{
    if (preg_match('/^#\s+(.+)$/m', $content, $matches) === 1) {
        return trim($matches[1]);
    }

    return $fallback;
}

function aiSummarizeMarkdown(string $content): ?string
{
    $lines = preg_split('/\r?\n/', $content) ?: [];

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '---')) {
            continue;
        }

        return $trimmed;
    }

    return null;
}

function aiResource(
    string $scope,
    string $type,
    string $name,
    string $path,
    ?string $description = null,
    ?string $runtime = null,
    array $extra = []
): array {
    return array_merge([
        'scope' => $scope,
        'type' => $type,
        'name' => $name,
        'path' => aiNormalizePath($path),
        'runtime' => $runtime,
        'description' => $description,
    ], $extra);
}

function aiCollectCatalog(string $root): array
{
    $manifest = aiLoadJson($root, 'packages/ai-universal-rules/manifest.json');
    $resources = [];

    foreach (aiCollectRootResources($root) as $resource) {
        $resources[] = $resource;
    }

    foreach (aiCollectPackageResources($root) as $resource) {
        $resources[] = $resource;
    }

    usort(
        $resources,
        static fn (array $left, array $right): int => [$left['scope'], $left['type'], $left['path']] <=> [$right['scope'], $right['type'], $right['path']]
    );

    $counts = [];

    foreach ($resources as $resource) {
        $key = $resource['scope'] . ':' . $resource['type'];
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    ksort($counts);

    return [
        '$schema' => '../../schemas/ai/ai-catalog.schema.json',
        'generated_by' => 'php tools/ai/generate-ai-catalog.php',
        'repository' => [
            'name' => 'app-configs',
            'summary' => 'Opinionated development configuration plus a reusable cross-tool AI workflow kit.',
            'catalog_docs' => [
                'docs/ai/catalog.md',
                'packages/ai-universal-rules/docs/BROWSE.md',
                'llms.txt',
            ],
        ],
        'package' => [
            'name' => $manifest['name'],
            'version' => $manifest['version'],
            'description' => $manifest['description'],
            'supported_tools' => $manifest['supported_tools'],
            'supported_surfaces' => $manifest['supported_surfaces'],
            'generated_outputs' => $manifest['generated_outputs'],
        ],
        'counts' => $counts,
        'resources' => $resources,
        'starter_profiles' => $manifest['starter_profiles'],
    ];
}

function aiCollectRootResources(string $root): array
{
    $resources = [];

    $rootDocMap = [
        'docs/ai/project-context.md' => ['root-doc', 'project-context-doc', 'Durable repository context for instructions, capabilities, and runtime adapters.'],
        'docs/ai/workflow.md' => ['root-doc', 'workflow', 'Default live workflow for risk, verification, context, and docs sync.'],
        'docs/ai/agent-ops.md' => ['root-doc', 'agent-ops', 'Agent operations model for observability, evaluation, optimization, IAM, and architecture routing.'],
        'docs/ai/agents.md' => ['root-doc', 'agents', 'Durable live-agent reference plus package-agent index for later lookup.'],
        'docs/ai/failure-handling.md' => ['root-doc', 'failure-handling', 'Failure taxonomy, retry policy, corrected usage guidance, and logging contract.'],
        'docs/ai/agent-ops-checklist.md' => ['root-doc', 'agent-ops-checklist', 'Phased verification checklist for auditing AI workflow integration in the live repo.'],
        'docs/ai/integration-matrix.md' => ['root-doc', 'integration-matrix', 'Coverage map that tracks which AI workflow concepts are covered, partial, or missing.'],
        'docs/ai/AI-GUARDRAILS.md' => ['root-doc', 'AI Guardrails', 'Cross-tool guardrails for approval boundaries, evidence, and recurring failure modes.'],
        'docs/ai/capabilities/agent-observability-and-evidence/event-schema.md' => ['root-doc', 'agent-evidence-schema', 'Structured evidence event model for traceable agent runs on supported runtimes.'],
        'docs/ai/capabilities/agent-observability-and-evidence/failure-taxonomy.md' => ['root-doc', 'agent-failure-taxonomy', 'Normalized failure categories for agent evidence events and taxonomy mapping guidance.'],
        'docs/ai/capabilities/evaluation-and-regression/golden-tasks.md' => ['root-doc', 'evaluation-golden-tasks', 'Golden-task patterns for behavior-regression checks in agent workflows.'],
        'docs/ai/capabilities/evaluation-and-regression/replay-rules.md' => ['root-doc', 'evaluation-replay-rules', 'Replay rules for reproducing and classifying failed or ambiguous agent runs.'],
        'docs/ai/capabilities/evaluation-and-regression/human-review-rules.md' => ['root-doc', 'evaluation-human-review-rules', 'Human-review triggers and decision record expectations for risky agent outcomes.'],
        'docs/ai/capabilities/preview-environments/lifecycle.md' => ['root-doc', 'preview-lifecycle', 'Vendor-neutral lifecycle and TTL expectations for temporary preview environments.'],
        'docs/ai/capabilities/preview-environments/data-and-secret-rules.md' => ['root-doc', 'preview-data-and-secrets', 'Data and secret isolation rules for preview environments.'],
        'docs/ai/capabilities/preview-environments/checklist.md' => ['root-doc', 'preview-checklist', 'Checklist for preview-environment readiness, evidence, and cleanup.'],
    ];

    foreach ($rootDocMap as $relativePath => [$type, $name, $description]) {
        if (!is_file(aiAbsolutePath($root, $relativePath))) {
            continue;
        }

        $resources[] = aiResource('root', $type, $name, $relativePath, $description, 'canonical');
    }

    $adapterDocMap = [
        'docs/ai/copilot-getting-started.md' => ['adapter-doc', 'copilot-getting-started', 'GitHub Copilot adapter onboarding, read order, and end-to-end task examples.', 'github-copilot'],
        'docs/ai/opencode-getting-started.md' => ['adapter-doc', 'opencode-getting-started', 'OpenCode adapter onboarding, read order, and end-to-end task examples.', 'opencode'],
    ];

    foreach ($adapterDocMap as $relativePath => [$type, $name, $description, $runtime]) {
        if (!is_file(aiAbsolutePath($root, $relativePath))) {
            continue;
        }

        $resources[] = aiResource('root', $type, $name, $relativePath, $description, $runtime);
    }

    $rootScriptMap = [
        'scripts/ai/ai-diff-context.sh' => ['ai-script', 'ai-diff-context.sh', 'Builds focused diff and change-context packs for AI review and implementation.'],
        'scripts/ai/ai-doc-check.sh' => ['ai-script', 'ai-doc-check.sh', 'Checks AI-facing documentation surfaces for required references and drift.'],
        'scripts/ai/ai-edit.sh' => ['ai-script', 'ai-edit.sh', 'Guarded edit wrapper with snapshots, dry-run behavior, visible diff, and optional verification.'],
        'scripts/ai/ai-rollback.sh' => ['ai-script', 'ai-rollback.sh', 'Rollback helper for explicit recovery work using session snapshots and refs.'],
        'scripts/ai/ai-search.sh' => ['ai-script', 'ai-search.sh', 'Unified search entrypoint for text, file, tracked, all, and structural discovery.'],
        'scripts/ai/ai-structured.sh' => ['ai-script', 'ai-structured.sh', 'Structured output helper for deterministic AI workflow data.'],
        'scripts/ai/ai-task.sh' => ['ai-script', 'ai-task.sh', 'Task-oriented AI workflow helper for routing, context, and verification steps.'],
        'scripts/ai/ai-test-select.sh' => ['ai-script', 'ai-test-select.sh', 'Selects likely relevant tests from changed files and task context.'],
        'scripts/ai/ai-verify.sh' => ['ai-script', 'ai-verify.sh', 'Project-aware verification gate for AI-driven changes across shell, PHP, JS/TS, and security checks.'],
        'scripts/ai/common.sh' => ['ai-script', 'common.sh', 'Shared helper library for AI workflow scripts, logging, snapshots, and token-budget checks.'],
        'scripts/ai/fd-files.sh' => ['ai-script', 'fd-files.sh', 'Repo-aware file discovery wrapper around fd/fdfind with rg fallback and safer defaults.'],
        'scripts/ai/gh-pr-context.sh' => ['ai-script', 'gh-pr-context.sh', 'GitHub PR context wrapper with metadata, diff, checks, reviews, and optional PR-scoped context packing.'],
        'scripts/ai/git-forensics.sh' => ['ai-script', 'git-forensics.sh', 'Git history and blame wrapper for evidence-oriented code archaeology.'],
        'scripts/ai/install-mandatory-tools.sh' => ['ai-script', 'install-mandatory-tools.sh', 'Installs mandatory CLI tools required by the AI workflow script layer.'],
        'scripts/ai/pack-context.sh' => ['ai-script', 'pack-context.sh', 'Builds bounded repository context packs for AI task execution.'],
        'scripts/ai/post-tool-use.sh' => ['ai-script', 'post-tool-use.sh', 'Post-tool hook helper for tool usage logging and failure classification.'],
        'scripts/ai/pre-tool-use.sh' => ['ai-script', 'pre-tool-use.sh', 'Pre-tool hook helper for approval boundaries and command policy enforcement.'],
        'scripts/ai/preview-file.sh' => ['ai-script', 'preview-file.sh', 'Smart file preview wrapper with text and fallback modes.'],
        'scripts/ai/query-usage.sh' => ['ai-script', 'query-usage.sh', 'Usage and repository-size query helper for AI context planning.'],
        'scripts/ai/repo-tool-inventory.sh' => ['ai-script', 'repo-tool-inventory.sh', 'Generates the required tool inventory from scripts and workflow requirements.'],
        'scripts/ai/repomix-context-tree.sh' => ['ai-script', 'repomix-context-tree.sh', 'Builds repository tree context for Repomix-based AI context packing.'],
        'scripts/ai/repomix-scc-router.sh' => ['ai-script', 'repomix-scc-router.sh', 'Ranked context router that produces TSV and JSON bundle plans with churn-aware scoring.'],
        'scripts/ai/rg-code.sh' => ['ai-script', 'rg-code.sh', 'Mode-aware ripgrep wrapper with JSON, file-list, count, and context output modes.'],
        'scripts/ai/run-repo-tests.sh' => ['ai-script', 'run-repo-tests.sh', 'Parallel-first repository test runner for PHP, shell, Bats, and validators.'],
        'scripts/ai/run-repomix-context.sh' => ['ai-script', 'run-repomix-context.sh', 'Runs Repomix context generation with repository-aware defaults.'],
        'scripts/ai/session-checkpoint.sh' => ['ai-script', 'session-checkpoint.sh', 'Creates session checkpoints for recovery and traceability.'],
        'scripts/ai/watch-loop.sh' => ['ai-script', 'watch-loop.sh', 'Watch-based verification loop with debounce and repo-local session logging.'],
    ];

    foreach ($rootScriptMap as $relativePath => [$type, $name, $description]) {
        if (!is_file(aiAbsolutePath($root, $relativePath))) {
            continue;
        }

        $resources[] = aiResource('root', $type, $name, $relativePath, $description, 'canonical');
    }

    $adapterSurfaceMap = [
        'policies/ai/policy.yaml' => ['adapter-policy', 'ai-policy', 'Declarative allow, deny, and confirm rules enforced for all runtimes by the canonical pre-tool-use hook.', 'canonical'],
        '.github/hooks/tool-policy.json' => ['adapter-hook', 'tool-policy', 'GitHub Copilot hook configuration for tool policy enforcement.', 'github-copilot'],
        '.github/hooks/tool-guardian.json' => ['adapter-hook', 'tool-guardian', 'GitHub Copilot hook configuration for guarded tool execution.', 'github-copilot'],
        '.github/hooks/scripts/tool-guardian.ps1' => ['adapter-hook-script', 'tool-guardian.ps1', 'PowerShell hook script for GitHub Copilot guarded tool execution.', 'github-copilot'],
        'schemas/ai/evidence-event.schema.json' => ['schema', 'evidence-event.schema.json', 'JSON schema for durable agent evidence events emitted by supported runtime surfaces.', 'canonical'],
    ];

    foreach ($adapterSurfaceMap as $relativePath => [$type, $name, $description, $runtime]) {
        if (!file_exists(aiAbsolutePath($root, $relativePath))) {
            continue;
        }

        $resources[] = aiResource('root', $type, $name, $relativePath, $description, $runtime);
    }

    $phpReferenceMap = [
        'reference/php/design-patterns' => ['php-reference', 'design-patterns', 'Primary local PHP design pattern corpus for agent and human lookups.'],
        'reference/php/design-principles' => ['php-reference', 'design-principles', 'Secondary PHP principles and composition examples.'],
        'reference/php/php-built-ins' => ['php-reference', 'php-built-ins', 'Supporting PHP built-in usage examples.'],
    ];

    foreach ($phpReferenceMap as $relativePath => [$type, $name, $description]) {
        if (!file_exists(aiAbsolutePath($root, $relativePath))) {
            continue;
        }

        $resources[] = aiResource('root', $type, $name, $relativePath, $description, 'php');
    }

    $capabilityPaths = glob(aiAbsolutePath($root, 'docs/ai/capabilities/*/CAPABILITY.md')) ?: [];
    sort($capabilityPaths);

    foreach ($capabilityPaths as $path) {
        $relativePath = substr(aiNormalizePath($path), strlen(aiNormalizePath($root)) + 1);
        $name = basename(dirname($path));
        $content = file_get_contents($path) ?: '';

        $resources[] = aiResource('root', 'capability', $name, $relativePath, aiSummarizeMarkdown($content), 'canonical');
    }

    $agentPaths = glob(aiAbsolutePath($root, '.github/agents/*.agent.md')) ?: [];
    sort($agentPaths);

    foreach ($agentPaths as $path) {
        $relativePath = substr(aiNormalizePath($path), strlen(aiNormalizePath($root)) + 1);
        $content = file_get_contents($path) ?: '';
        $frontMatter = aiParseFrontMatter($content);

        $resources[] = aiResource(
            'root',
            'github-copilot-agent',
            $frontMatter['name'] ?? basename($path, '.agent.md'),
            $relativePath,
            $frontMatter['description'] ?? aiSummarizeMarkdown($content),
            'github-copilot'
        );
    }

    $instructionPaths = glob(aiAbsolutePath($root, '.github/instructions/*.instructions.md')) ?: [];
    sort($instructionPaths);

    foreach ($instructionPaths as $path) {
        $relativePath = substr(aiNormalizePath($path), strlen(aiNormalizePath($root)) + 1);
        $content = file_get_contents($path) ?: '';
        $frontMatter = aiParseFrontMatter($content);

        $resources[] = aiResource(
            'root',
            'github-copilot-instruction',
            basename($path, '.instructions.md'),
            $relativePath,
            $frontMatter['description'] ?? aiSummarizeMarkdown($content),
            'github-copilot'
        );
    }

    $promptPaths = glob(aiAbsolutePath($root, '.github/prompts/*.prompt.md')) ?: [];
    sort($promptPaths);

    foreach ($promptPaths as $path) {
        $relativePath = substr(aiNormalizePath($path), strlen(aiNormalizePath($root)) + 1);
        $content = file_get_contents($path) ?: '';
        $frontMatter = aiParseFrontMatter($content);

        $resources[] = aiResource(
            'root',
            'github-copilot-prompt',
            basename($path, '.prompt.md'),
            $relativePath,
            $frontMatter['description'] ?? aiSummarizeMarkdown($content),
            'github-copilot'
        );
    }

    $copilotSkillPaths = glob(aiAbsolutePath($root, '.github/skills/*/SKILL.md')) ?: [];
    sort($copilotSkillPaths);

    foreach ($copilotSkillPaths as $path) {
        $relativePath = substr(aiNormalizePath($path), strlen(aiNormalizePath($root)) + 1);
        $content = file_get_contents($path) ?: '';
        $frontMatter = aiParseFrontMatter($content);
        $name = $frontMatter['name'] ?? basename(dirname($path));

        $resources[] = aiResource(
            'root',
            'github-copilot-skill',
            $name,
            $relativePath,
            $frontMatter['description'] ?? aiSummarizeMarkdown($content),
            'github-copilot'
        );
    }

    $opencodeResourcePatterns = [
        '.opencode/agents/*.md' => ['opencode-agent', 'opencode'],
        '.opencode/commands/*.md' => ['opencode-command', 'opencode'],
        '.opencode/skills/*/SKILL.md' => ['opencode-skill', 'opencode'],
    ];

    foreach ($opencodeResourcePatterns as $pattern => [$type, $runtime]) {
        $paths = glob(aiAbsolutePath($root, $pattern)) ?: [];
        sort($paths);

        foreach ($paths as $path) {
            $relativePath = substr(aiNormalizePath($path), strlen(aiNormalizePath($root)) + 1);
            $content = file_get_contents($path) ?: '';
            $frontMatter = aiParseFrontMatter($content);

            $name = $frontMatter['name'] ?? pathinfo($path, PATHINFO_FILENAME);

            if ($type === 'opencode-skill') {
                $name = basename(dirname($path));
            }

            $resources[] = aiResource(
                'root',
                $type,
                $name,
                $relativePath,
                $frontMatter['description'] ?? aiSummarizeMarkdown($content),
                $runtime
            );
        }
    }

    $toolMap = [
        'tools/ai/ai.php' => ['cli', 'ai', 'Main AI workflow CLI dispatcher.'],
        'tools/ai/validate-ai-config.php' => ['validator', 'validate-ai-config', 'Validates the root live AI workflow layer.'],
        'tools/ai/validate-ai-catalog.php' => ['validator', 'validate-ai-catalog', 'Validates manifest, catalog, and starter profile metadata.'],
        'tools/ai/validate-generated-artifacts.php' => ['validator', 'validate-generated-artifacts', 'Validates generated artifact presence and drift.'],
        'tools/ai/validate-install-surface.php' => ['validator', 'validate-install-surface', 'Validates install pack, profile, script, and adapter template contracts.'],
        'tools/ai/generate-ai-catalog.php' => ['generator', 'generate-ai-catalog', 'Generates catalog docs, catalog JSON, and llms.txt.'],
        'tools/ai/export-ai-universal-rules.php' => ['exporter', 'export-ai-universal-rules', 'Builds starter-profile release bundles under dist/.'],
        'tools/ai/verify-full-install.php' => ['verifier', 'verify-full-install', 'Runs full install verification flow and writes durable evidence.'],
        'tools/ai/full-install-validation.php' => ['verifier', 'full-install-validation', 'Runs broad validation across install, catalog, generated artifacts, scripts, and inventory.'],
    ];

    foreach ($toolMap as $relativePath => [$type, $name, $description]) {
        if (!is_file(aiAbsolutePath($root, $relativePath))) {
            continue;
        }

        $resources[] = aiResource('root', $type, $name, $relativePath, $description, 'php');
    }

    return $resources;
}

function aiCollectPackageResources(string $root): array
{
    $resources = [];

    $prefixMap = [
        'packages/ai-universal-rules/templates/core/' => ['core-template', 'canonical'],
        'packages/ai-universal-rules/templates/shared/' => ['shared-template', 'canonical'],
        'packages/ai-universal-rules/templates/capabilities/' => ['package-capability', 'canonical'],

        'packages/ai-universal-rules/templates/core/agents/' => ['core-agent-template', 'canonical'],
        'packages/ai-universal-rules/templates/instructions/' => ['github-copilot-instruction-template', 'github-copilot'],
        'packages/ai-universal-rules/templates/workflows/' => ['workflow-template', 'dual-runtime'],
        'packages/ai-universal-rules/templates/commands/' => ['opencode-command-template', 'opencode'],

        'packages/ai-universal-rules/templates/optional/' => ['optional-template', 'optional'],
        'packages/ai-universal-rules/docs/foundations/' => ['foundation-doc', 'canonical'],
        'packages/ai-universal-rules/docs/workflows/' => ['workflow-doc', 'canonical'],
        'packages/ai-universal-rules/docs/operations/' => ['operations-doc', 'canonical'],
    ];

    $base = aiAbsolutePath($root, 'packages/ai-universal-rules');

    if (!is_dir($base)) {
        return $resources;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $relativePath = substr(aiNormalizePath($file->getPathname()), strlen(aiNormalizePath($root)) + 1);

        if (in_array($relativePath, [
            'packages/ai-universal-rules/catalog.json',
            'packages/ai-universal-rules/docs/BROWSE.md',
            'packages/ai-universal-rules/manifest.json',
            'packages/ai-universal-rules/manifest.yml',
            'packages/ai-universal-rules/package-lock.ai.json',
        ], true)) {
            continue;
        }

        foreach ($prefixMap as $prefix => [$type, $runtime]) {
            if (!str_starts_with($relativePath, $prefix)) {
                continue;
            }

            $content = file_get_contents($file->getPathname()) ?: '';
            $frontMatter = aiParseFrontMatter($content);
            $defaultName = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $name = $frontMatter['name'] ?? aiExtractTitle($content, $defaultName);
            $description = $frontMatter['description'] ?? aiSummarizeMarkdown($content);

            $resources[] = aiResource('package', $type, $name, $relativePath, $description, $runtime);
            break;
        }
    }

    return $resources;
}

function aiRenderRootCatalogMarkdown(array $catalog): string
{
    $lines = [];
    $lines[] = '# AI Catalog';
    $lines[] = '';
    $lines[] = '_Generated by `php tools/ai/generate-ai-catalog.php`. Do not edit by hand._';
    $lines[] = '';
    $lines[] = 'This generated file is the live inventory for AI workflow assets in this repository and the reusable `packages/ai-universal-rules/` package.';
    $lines[] = '';
    $lines[] = 'Use the relevant adapter onboarding document first, then use this catalog when you need the full indexed list of agents, instructions, hooks, prompts, scripts, capabilities, and docs.';
    $lines[] = '';

    if (aiCatalogResourcePathExists($catalog, 'docs/ai/copilot-getting-started.md')) {
        $lines[] = '- GitHub Copilot adapter: `docs/ai/copilot-getting-started.md`';
    }

    if (aiCatalogResourcePathExists($catalog, 'docs/ai/opencode-getting-started.md')) {
        $lines[] = '- OpenCode adapter: `docs/ai/opencode-getting-started.md`';
    }

    $lines[] = '';
    $lines[] = '## Runtime Model';
    $lines[] = '';
    $lines[] = '- `canonical` resources are shared across all AI runtimes.';
    $lines[] = '- `github-copilot` resources belong to the `.github/` adapter surface.';
    $lines[] = '- `opencode` resources belong to the `.opencode/` adapter surface.';
    $lines[] = '- `dual-runtime` examples intentionally show both adapter surfaces together.';
    $lines[] = '';
    $lines[] = '## Highlights';
    $lines[] = '';

    foreach ($catalog['counts'] as $key => $count) {
        [$scope, $type] = explode(':', $key, 2);
        $lines[] = '- `' . $scope . ' / ' . $type . '` - ' . $count;
    }

    $lines[] = '';
    $lines[] = '## Live Repo Resources';
    $lines[] = '';
    $lines = array_merge($lines, aiRenderTableRows($catalog['resources'], 'root'));
    $lines[] = '';
    $lines[] = '## AI Universal Rules Package';
    $lines[] = '';
    $lines = array_merge($lines, aiRenderTableRows($catalog['resources'], 'package'));
    $lines[] = '';
    $lines[] = '## Starter Profiles';
    $lines[] = '';
    $lines[] = '| Profile | Description |';
    $lines[] = '| --- | --- |';

    foreach ($catalog['starter_profiles'] as $profile) {
        $lines[] = '| `' . $profile['id'] . '` | ' . aiEscapeTable((string) $profile['description']) . ' |';
    }

    $lines[] = '';
    $lines[] = '## Validation Commands';
    $lines[] = '';
    $lines[] = '- `php tools/ai/validate-ai-config.php`';
    $lines[] = '- `php tools/ai/validate-ai-catalog.php`';
    $lines[] = '- `php tools/ai/generate-ai-catalog.php --check`';
    $lines[] = '- `php tools/ai/export-ai-universal-rules.php --check`';

    return implode("\n", $lines) . "\n";
}

function aiRenderBrowseMarkdown(array $catalog): string
{
    $package = $catalog['package'];
    $lines = [];
    $lines[] = '# Browse';
    $lines[] = '';
    $lines[] = '_Generated by `php tools/ai/generate-ai-catalog.php`. Do not edit by hand._';
    $lines[] = '';
    $lines[] = '`' . $package['name'] . '` v`' . $package['version'] . '` packages the reusable workflow model behind this repository.';
    $lines[] = '';
    $lines[] = '## Runtime Model';
    $lines[] = '';
    $lines[] = '- `canonical`: shared runtime-neutral docs, capabilities, scripts, and schemas.';
    $lines[] = '- `github-copilot`: GitHub Copilot adapter templates and `.github/` surfaces.';
    $lines[] = '- `opencode`: OpenCode adapter templates and `.opencode/` surfaces.';
    $lines[] = '- `dual-runtime`: examples or profiles that intentionally include both adapter surfaces.';
    $lines[] = '';
    $lines[] = '## Package Outputs';
    $lines[] = '';

    foreach ($package['generated_outputs'] as $output) {
        $lines[] = '- `' . $output . '`';
    }

    $lines[] = '';
    $lines[] = '## Package Resources';
    $lines[] = '';
    $lines = array_merge($lines, aiRenderTableRows($catalog['resources'], 'package'));
    $lines[] = '';
    $lines[] = '## Starter Profiles';
    $lines[] = '';

    $starterProfiles = $catalog['starter_profiles'];
    $starterCount = count($starterProfiles);

    foreach ($starterProfiles as $index => $profile) {
        $lines[] = '### `' . $profile['id'] . '`';
        $lines[] = '';
        $lines[] = (string) $profile['description'];
        $lines[] = '';

        foreach ($profile['includes'] as $include) {
            $lines[] = '- `' . $include . '`';
        }

        if ($index < $starterCount - 1) {
            $lines[] = '';
        }
    }

    return implode("\n", $lines) . "\n";
}

function aiRenderLlms(array $catalog): string
{
    $lines = [];
    $lines[] = '# app-configs';
    $lines[] = '';
    $lines[] = '> Opinionated development configuration plus a reusable cross-tool AI workflow kit.';
    $lines[] = '';
    $lines[] = '## Primary Docs';
    $lines[] = '';
    $lines[] = '- [README.md](README.md): root overview, quick start, and repo layout';
    $lines[] = '- [AGENTS.md](AGENTS.md): durable repository instructions';

    if (aiCatalogResourcePathExists($catalog, 'docs/ai/copilot-getting-started.md')) {
        $lines[] = '- [docs/ai/copilot-getting-started.md](docs/ai/copilot-getting-started.md): GitHub Copilot adapter install map and read order';
    }

    if (aiCatalogResourcePathExists($catalog, 'docs/ai/opencode-getting-started.md')) {
        $lines[] = '- [docs/ai/opencode-getting-started.md](docs/ai/opencode-getting-started.md): OpenCode adapter install map and read order';
    }

    $lines[] = '- [docs/ai/project-context.md](docs/ai/project-context.md): live repository context';
    $lines[] = '- [docs/ai/workflow.md](docs/ai/workflow.md): live task flow';
    $lines[] = '- [docs/ai/agents.md](docs/ai/agents.md): live agent reference and package agent index';
    $lines[] = '- [docs/ai/failure-handling.md](docs/ai/failure-handling.md): command-failure taxonomy and retry policy';
    $lines[] = '- [docs/ai/agent-ops-checklist.md](docs/ai/agent-ops-checklist.md): phased verification checklist for integration audits';
    $lines[] = '- [docs/ai/integration-matrix.md](docs/ai/integration-matrix.md): concept coverage map for the live workflow layer';
    $lines[] = '- [docs/ai/catalog.md](docs/ai/catalog.md): generated browse index for live and package assets';
    $lines[] = '';
    $lines[] = '## Runtime Adapters';
    $lines[] = '';
    $lines[] = '- GitHub Copilot adapter resources live under `.github/` and are generated from `packages/ai-universal-rules/templates/instructions/`, `packages/ai-universal-rules/templates/workflows/`, and `packages/ai-universal-rules/templates/core/agents/`.';
    $lines[] = '- OpenCode adapter resources live under `.opencode/` and are generated from `packages/ai-universal-rules/templates/workflows/`, `packages/ai-universal-rules/templates/commands/`, and `packages/ai-universal-rules/templates/core/agents/`.';
    $lines[] = '- Canonical workflow resources stay runtime-neutral under `docs/ai/`, `docs/ai/capabilities/`, `scripts/ai/`, and `schemas/ai/`.';
    $lines[] = '';
    $lines[] = '## Reusable Kit';
    $lines[] = '';
    $lines[] = '- [packages/ai-universal-rules/README.md](packages/ai-universal-rules/README.md): package overview and operating model';
    $lines[] = '- [packages/ai-universal-rules/QUICKSTART.md](packages/ai-universal-rules/QUICKSTART.md): fastest install path';
    $lines[] = '- [packages/ai-universal-rules/docs/BROWSE.md](packages/ai-universal-rules/docs/BROWSE.md): generated package catalog';
    $lines[] = '- [packages/ai-universal-rules/manifest.json](packages/ai-universal-rules/manifest.json): machine-readable package manifest';
    $lines[] = '';
    $lines[] = '## Contribution And Trust';
    $lines[] = '';
    $lines[] = '- [CONTRIBUTING.md](CONTRIBUTING.md): contribution rules and generated file workflow';
    $lines[] = '- [SECURITY.md](SECURITY.md): security reporting';
    $lines[] = '- [SUPPORT.md](SUPPORT.md): support expectations and reporting guidance';
    $lines[] = '';
    $lines[] = '## Validation';
    $lines[] = '';
    $lines[] = '- `php tools/ai/validate-ai-config.php`';
    $lines[] = '- `php tools/ai/validate-ai-catalog.php`';
    $lines[] = '- `php tools/ai/generate-ai-catalog.php --check`';
    $lines[] = '- `php tools/ai/export-ai-universal-rules.php --check`';

    return implode("\n", $lines) . "\n";
}

function aiRenderTableRows(array $resources, string $scope): array
{
    $lines = [];
    $lines[] = '| Type | Name | Path | Description |';
    $lines[] = '| --- | --- | --- | --- |';

    foreach ($resources as $resource) {
        if ($resource['scope'] !== $scope) {
            continue;
        }

        $description = $resource['description'] ?? '';

        $lines[] = '|`'
            . $resource['type']
            . '`|'
            . aiEscapeTable((string) $resource['name'])
            . '|`'
            . $resource['path']
            . '`|'
            . aiEscapeTable((string) $description)
            . '|';
    }

    return $lines;
}

function aiEscapeTable(string $value): string
{
    return str_replace('|', '\\|', $value);
}

function aiEnsureDirectory(string $directory): void
{
    if (is_dir($directory)) {
        return;
    }

    if (!mkdir($directory, AI_DIR_MODE, true) && !is_dir($directory)) {
        throw new RuntimeException("Unable to create {$directory}.");
    }
}

function aiWriteIfChanged(string $absolutePath, string $content): bool
{
    $existing = is_file($absolutePath) ? file_get_contents($absolutePath) : false;

    if ($existing === $content) {
        return false;
    }

    aiEnsureDirectory(dirname($absolutePath));
    file_put_contents($absolutePath, $content);

    return true;
}

function aiCompareOrWrite(string $root, string $relativePath, string $content, bool $checkOnly, array &$messages): bool
{
    $absolutePath = aiAbsolutePath($root, $relativePath);
    $existing = is_file($absolutePath) ? file_get_contents($absolutePath) : false;
    $normalizedContent = aiNormalizeGeneratedContent($content);

    if ($existing !== false && aiNormalizeGeneratedContent($existing) === $normalizedContent) {
        $messages[] = "OK: {$relativePath} is up to date";
        return true;
    }

    if ($checkOnly) {
        $messages[] = "ERROR: {$relativePath} is out of date";
        return false;
    }

    aiWriteIfChanged($absolutePath, $normalizedContent);
    $messages[] = "OK: regenerated {$relativePath}";

    return true;
}

function aiValidateManifest(array $manifest, string $root): array
{
    $errors = [];

    foreach ([
        'name',
        'version',
        'description',
        'supported_tools',
        'supported_surfaces',
        'workflow_layers',
        'required_templates',
        'runtime_entrypoints',
        'generated_outputs',
        'starter_profiles',
        'release',
    ] as $key) {
        if (!array_key_exists($key, $manifest)) {
            $errors[] = "manifest.json missing {$key}";
        }
    }

    foreach ($manifest['required_templates'] ?? [] as $path) {
        if (!file_exists(aiAbsolutePath($root, 'packages/ai-universal-rules/' . ltrim((string) $path, '/')))) {
            $errors[] = "manifest.json references missing template {$path}";
        }
    }

    foreach ($manifest['generated_outputs'] ?? [] as $path) {
        if (!is_string($path) || $path === '') {
            $errors[] = 'manifest.json generated_outputs entries must be non-empty strings';
        }
    }

    foreach ($manifest['starter_profiles'] ?? [] as $profile) {
        if (!is_array($profile) || !isset($profile['id'], $profile['description'], $profile['includes'])) {
            $errors[] = 'manifest.json starter_profiles entries must contain id, description, and includes';
            continue;
        }

        foreach ($profile['includes'] as $include) {
            if (!file_exists(aiAbsolutePath($root, 'packages/ai-universal-rules/' . ltrim((string) $include, '/')))) {
                $errors[] = "starter profile {$profile['id']} references missing path {$include}";
            }
        }
    }

    return $errors;
}

function aiReadManifestYamlSummary(string $root): array
{
    $content = aiReadFile($root, 'packages/ai-universal-rules/manifest.yml');
    $summary = [];

    foreach (preg_split('/\r?\n/', $content) ?: [] as $line) {
        if (preg_match('/^(name|version|description):\s*(.+)$/', trim($line), $matches) === 1) {
            if (!array_key_exists($matches[1], $summary)) {
                $summary[$matches[1]] = trim($matches[2]);
            }
        }
    }

    return $summary;
}

function aiCopyPath(string $source, string $destination): void
{
    if (is_file($source)) {
        aiEnsureDirectory(dirname($destination));
        copy($source, $destination);
        return;
    }

    if (!is_dir($source)) {
        throw new RuntimeException("Missing export source {$source}.");
    }

    aiEnsureDirectory($destination);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

        if ($item->isDir()) {
            aiEnsureDirectory($target);
            continue;
        }

        aiEnsureDirectory(dirname($target));
        copy($item->getPathname(), $target);
    }
}

function aiCatalogResourcePathExists(array $catalog, string $path): bool
{
    foreach ($catalog['resources'] ?? [] as $resource) {
        if (is_array($resource) && ($resource['path'] ?? null) === $path) {
            return true;
        }
    }

    return false;
}
