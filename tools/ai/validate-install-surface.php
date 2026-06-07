<?php

declare(strict_types=1);

require_once __DIR__ . '/install/packs.php';
require_once __DIR__ . '/install/profiles.php';
require_once __DIR__ . '/install/script-registry.php';

$targetArg = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--target=')) {
        $targetArg = substr($arg, 9);
    }
}

$root = $targetArg !== null ? realpath($targetArg) : realpath(__DIR__ . '/..' . '/..');
if ($root === false) {
    fwrite(STDERR, "ERROR: repository root not found\n");
    exit(1);
}

$strict = in_array('--strict', $argv, true);
$errors = [];
$warnings = [];

if ($targetArg !== null || (is_file($root . '/.ai-install-manifest.json') && !is_dir($root . '/packages/ai-universal-rules/templates'))) {
    $manifestPath = $root . '/.ai-install-manifest.json';
    $manifest = json_decode((string) @file_get_contents($manifestPath), true);
    if (!is_array($manifest)) {
        fwrite(STDERR, "ERROR: target install manifest is missing or invalid: .ai-install-manifest.json\n");
        exit(1);
    }
    $files = $manifest['files'] ?? null;
    if (!is_array($files) || $files === []) {
        fwrite(STDERR, "ERROR: target install manifest has no managed files\n");
        exit(1);
    }
    $installerGeneratedPaths = [
        'docs/ai/POST-INSTALL.md',
        'docs/ai/available-packs.md',
        'docs/ai/SETUP.md',
        'docs/ai/installed-files.md',
        'docs/ai/project-configuration.md',
        'docs/ai/generated/install-summary.md',
        'docs/ai/generated/install-instructions.md',
        'docs/ai/generated/install-instructions.json',
        'docs/ai/generated/install-manifest.json',
    ];
    $validOwnership = ['owned', 'template', 'rendered', 'patch-managed'];
    foreach ($files as $relative => $meta) {
        $path = $root . '/' . str_replace('\\', '/', (string) $relative);
        if (!file_exists($path)) {
            $errors[] = "managed install path is missing: {$relative}";
        }
        // Ownership contract: every managed file must declare a valid ownership class so
        // upgrade/uninstall can decide overwrite vs preserve. See
        // schemas/ai/ai-install-manifest.schema.json.
        if (is_array($meta)) {
            $ownership = (string) ($meta['ownership'] ?? '');
            if ($ownership === '') {
                $errors[] = "managed install file missing ownership class: {$relative}";
            } elseif (!in_array($ownership, $validOwnership, true)) {
                $errors[] = "managed install file has invalid ownership '{$ownership}': {$relative}";
            }
        }
        $expected = is_array($meta) ? (string) ($meta['installed_hash'] ?? '') : '';
        if ($expected !== '' && str_starts_with($expected, 'sha256:') && is_file($path)) {
            $actual = 'sha256:' . hash_file('sha256', $path);
            if ($actual !== $expected && !in_array((string) $relative, $installerGeneratedPaths, true)) {
                $warnings[] = "managed install file changed after install: {$relative}";
            }
        }
    }
    foreach (['scripts/ai/ai-search.sh', 'scripts/ai/preview-file.sh', 'tools/ai/validate-install-surface.php', 'docs/ai/POST-INSTALL.md'] as $required) {
        if (!file_exists($root . '/' . $required)) {
            $errors[] = "required target install path missing: {$required}";
        }
    }
    foreach ($warnings as $warning) {
        fwrite(STDOUT, "WARN: {$warning}\n");
    }
    foreach ($errors as $error) {
        fwrite(STDERR, "ERROR: {$error}\n");
    }
    if ($errors === []) {
        fwrite(STDOUT, "OK: target install surface validation passed\n");
    }
    exit($errors === [] ? 0 : 1);
}

$packs = aiInstallerPackRegistry();
$profiles = aiInstallerProfileDefinitions();
$scripts = aiInstallerScriptRegistry();

foreach (aiInstallerValidatePackRegistry($packs) as $error) {
    $errors[] = $error;
}

foreach ($packs as $packId => $items) {
    foreach ($items as $index => $item) {
        $type = (string) ($item['type'] ?? '');
        $source = (string) ($item['source'] ?? '');
        $target = (string) ($item['target'] ?? '');

        if (!in_array($type, ['file', 'dir'], true)) {
            $errors[] = "pack {$packId} item {$index} has unsupported type '{$type}'";
        }

        if ($source === '') {
            $errors[] = "pack {$packId} item {$index} has empty source";
        } else {
            $sourceAbs = $root . '/' . str_replace('\\', '/', $source);
            if ($type === 'file' && !is_file($sourceAbs)) {
                $errors[] = "pack {$packId} item {$index} missing file source {$source}";
            }
            if ($type === 'dir' && !is_dir($sourceAbs)) {
                $errors[] = "pack {$packId} item {$index} missing dir source {$source}";
            }
        }

        if ($target === '') {
            $errors[] = "pack {$packId} item {$index} has empty target";
        }
        if (str_starts_with($target, '/') || str_starts_with($target, './') || str_contains($target, '..')) {
            $errors[] = "pack {$packId} item {$index} has non-normalized target {$target}";
        }
        if ($target !== '' && aiInstallerIsReservedUserNamespace($target)) {
            $errors[] = "pack {$packId} item {$index} ships into the reserved user namespace: {$target}";
        }
    }
}

$knownProfiles = array_fill_keys(array_keys($profiles), true);
$knownPacks = array_fill_keys(array_keys($packs), true);
foreach ($profiles as $profileId => $items) {
    foreach ((array) $items as $item) {
        $key = (string) $item;
        if (!isset($knownProfiles[$key]) && !isset($knownPacks[$key])) {
            $errors[] = "profile {$profileId} references unknown pack/profile {$key}";
        }
    }

    $expanded = aiInstallerExpandProfilePacks((array) $items, $profiles, $packs);
    if ($profileId !== 'custom' && $expanded === []) {
        $errors[] = "profile {$profileId} resolves to no packs";
    }
}

$scriptEnforcedProfiles = ['copilot', 'opencode', 'dual'];
foreach ($scriptEnforcedProfiles as $profileId) {
    $expanded = aiInstallerExpandProfilePacks((array) ($profiles[$profileId] ?? []), $profiles, $packs);
    foreach (['scripts-pack', 'policy-pack', 'hooks-pack'] as $requiredPack) {
        if (!in_array($requiredPack, $expanded, true)) {
            $errors[] = "profile {$profileId} must include {$requiredPack} for script-governed runtime enforcement";
        }
    }
}

$packSources = [];
$packTargets = [];
foreach ($packs as $items) {
    foreach ($items as $item) {
        $packSources[] = (string) ($item['source'] ?? '');
        $packTargets[] = (string) ($item['target'] ?? '');
    }
}

foreach ($scripts as $id => $script) {
    // source_repo_only scripts are intentionally not shipped to target projects; skip pack-registry checks
    if (!empty($script['source_repo_only'])) {
        continue;
    }

    $pack = (string) ($script['pack'] ?? '');
    $sourcePath = (string) ($script['source_path'] ?? '');
    $installedPath = (string) ($script['installed_path'] ?? '');

    if (!isset($packs[$pack])) {
        $errors[] = "script {$id} references unknown pack {$pack}";
    }

    if ($sourcePath === '' || !is_file($root . '/' . $sourcePath)) {
        $errors[] = "script {$id} source_path missing {$sourcePath}";
    }

    if ($installedPath === '') {
        $errors[] = "script {$id} has empty installed_path";
    }

    if (!in_array($sourcePath, $packSources, true)) {
        $errors[] = "script {$id} source_path is not listed in pack registry: {$sourcePath}";
    }
    if (!in_array($installedPath, $packTargets, true)) {
        $errors[] = "script {$id} installed_path is not listed in pack registry: {$installedPath}";
    }
}

validateScriptRegistryJsonParity($root, $scripts, $errors);
validateAdapterScriptReferences($root, $scripts, $errors);
validateScriptsPackCoverage($packs, $scripts, $errors);

$opencodeAgentNames = collectAgentNames($root . '/packages/ai-universal-rules/templates/core/agents', '.md');
$githubAgentNames = $opencodeAgentNames;

$opencodeCommands = array_merge(
    glob($root . '/packages/ai-universal-rules/templates/workflows/*.md') ?: [],
    glob($root . '/packages/ai-universal-rules/templates/commands/*.md') ?: []
);

$advertisedPostInstallSetupSources = [
    'packages/ai-universal-rules/templates/workflows/post-install-setup.md',
    'packages/ai-universal-rules/templates/commands/post-install-setup.md',
];
foreach ($advertisedPostInstallSetupSources as $relativePath) {
    if (!is_file($root . '/' . $relativePath)) {
        $errors[] = "missing advertised post-install helper source: {$relativePath}";
    }
}

foreach ($opencodeCommands as $commandFile) {
    $content = (string) file_get_contents($commandFile);
    $agent = frontmatterField($content, 'agent');
    if ($agent !== null && $agent !== '' && !in_array($agent, $opencodeAgentNames, true)) {
        $errors[] = relativePath($root, $commandFile) . " references missing opencode agent '{$agent}'";
    }
}

$allowedNext = ['verify', 'user', 'planner', 'implement', 'refactorer'];

foreach (glob($root . '/packages/ai-universal-rules/templates/core/agents/*.md') ?: [] as $agentFile) {
    $agentContent = (string) file_get_contents($agentFile);
    // Hidden agents are internal-only and not shipped to installed projects; skip install-surface checks.
    if (preg_match('/^---\R(.*?)\R---/s', $agentContent, $fm) && preg_match('/^hidden:\s*true\s*$/m', $fm[1])) {
        continue;
    }
    $mode = frontmatterField($agentContent, 'mode');
    $agentName = pathinfo($agentFile, PATHINFO_FILENAME);
    $expectedMode = in_array($agentName, ['architect', 'implementer', 'reviewer'], true) ? 'all' : 'subagent';
    if ($mode !== $expectedMode) {
        $errors[] = relativePath($root, $agentFile) . " must set frontmatter mode: {$expectedMode}";
    }

    foreach (extractRecommendedNextSteps($agentContent) as $candidate) {
        if (!in_array($candidate, $allowedNext, true) && !in_array($candidate, $opencodeAgentNames, true)) {
            $errors[] = relativePath($root, $agentFile) . " has unknown Recommended Next Step '{$candidate}'";
        }
    }
}

$hasReviewerCommand = false;
foreach ($opencodeCommands as $commandFile) {
    if (basename($commandFile) === 'review-diff.md') {
        $hasReviewerCommand = true;
    }
}
if (in_array('reviewer', $opencodeAgentNames, true) && !$hasReviewerCommand) {
    $warnings[] = 'opencode reviewer agent exists but review-diff command is missing';
}

// Verify workflow template source parity. The installer renders these templates into
// Copilot prompts/skills and OpenCode skills in target repositories, but a clean
// source checkout does not need to be self-installed under .github/ to be valid.
$workflowTemplates = glob($root . '/packages/ai-universal-rules/templates/workflows/*.md') ?: [];
foreach ($workflowTemplates as $tpl) {
    $name = pathinfo($tpl, PATHINFO_FILENAME);
    if ($name === '') {
        $errors[] = 'workflow template has an empty name: ' . relativePath($root, $tpl);
    }
    $tplContent = (string) file_get_contents($tpl);
    if (str_contains($tplContent, 'compatibility: opencode')) {
        $errors[] = "workflow template '{$name}' has runtime-specific 'compatibility: opencode' which limits Copilot use — remove it";
    }
}

// Verify Copilot agent surface: every .github/agents/*.agent.md must use VS Code-native format
$copilotAgentFiles = glob($root . '/.github/agents/*.agent.md') ?: [];
foreach ($copilotAgentFiles as $agentFile) {
    $content = (string) file_get_contents($agentFile);
    $agentName = basename($agentFile);
    // Extract frontmatter block between first --- markers
    $fmBlock = '';
    if (preg_match('/^---\n([\s\S]*?)\n---/', $content, $fmMatch)) {
        $fmBlock = $fmMatch[1];
    }
    if (!preg_match('/^name:\s+\S/m', $fmBlock)) {
        $errors[] = "Copilot agent '{$agentName}' is missing 'name:' frontmatter field — must use VS Code-native format, not OpenCode format";
    }
    if (!preg_match('/^tools:\s*\[/m', $fmBlock)) {
        $errors[] = "Copilot agent '{$agentName}' is missing 'tools:' frontmatter field — add a Copilot tools list";
    }
    if (preg_match('/^tools:\s*\[[^\]]*(?:^|,\s*)["\'](read|search|edit|execute)["\'](?:\s*,|\s*$)/m', $fmBlock)) {
        $errors[] = "Copilot agent '{$agentName}' still uses broad tool aliases — switch to fine-grained VS Code tool names";
    }
    if (preg_match('/^id:\s+\S/m', $fmBlock)) {
        $errors[] = "Copilot agent '{$agentName}' has 'id:' frontmatter — this is OpenCode format; Copilot agents use 'name:'";
    }
    if (preg_match('/^permission:/m', $fmBlock)) {
        $errors[] = "Copilot agent '{$agentName}' has 'permission:' block — this is OpenCode format; remove it from Copilot agents";
    }
    // Check for unresolved SCRIPTS_ROOT placeholder
    if (str_contains($content, '<SCRIPTS_ROOT>')) {
        $errors[] = "Copilot agent '{$agentName}' still has unresolved '<SCRIPTS_ROOT>' placeholder — run install with placeholder resolution";
    }
}

// Verify stronger Copilot enforcement files are present when Copilot agents are installed.
if ($copilotAgentFiles !== []) {
    $scriptRegistryMd = $root . '/docs/ai/script-registry.md';
    $scriptRegistryJson = $root . '/docs/ai/script-registry.json';
    $toolPolicyFile = $root . '/.github/hooks/tool-policy.json';
    $workspaceSettingsFile = $root . '/.vscode/settings.json';

    if (!is_file($scriptRegistryMd)) {
        $errors[] = 'docs/ai/script-registry.md is missing — Copilot script allowlisting docs are required for the stronger enforcement surface';
    }
    if (!is_file($scriptRegistryJson)) {
        $errors[] = 'docs/ai/script-registry.json is missing — Copilot script allowlisting data is required for the stronger enforcement surface';
    }
    if (!is_file($toolPolicyFile)) {
        $errors[] = '.github/hooks/tool-policy.json is missing — Copilot hook policy is required for stronger terminal enforcement';
    }
    if (!is_file($workspaceSettingsFile)) {
        $warnings[] = '.vscode/settings.json is missing — VS Code sandbox and terminal auto-approval defaults are not installed';
    } else {
        $workspaceSettings = (string) file_get_contents($workspaceSettingsFile);
        foreach ([
            'chat.tools.terminal.ignoreDefaultAutoApproveRules',
            'chat.tools.terminal.blockDetectedFileWrites',
            'chat.agent.sandbox.enabled',
            'chat.agent.networkFilter',
        ] as $settingKey) {
            if (!str_contains($workspaceSettings, $settingKey)) {
                $warnings[] = ".vscode/settings.json should set {$settingKey} for a stronger Copilot enforcement posture";
            }
        }
    }
}

// Execution protocol baseline checks.
$executionProtocolTemplate = $root . '/packages/ai-universal-rules/templates/core/execution-protocol.template.md';
$executionCapabilityTemplate = $root . '/packages/ai-universal-rules/templates/capabilities/evidence-first-execution/CAPABILITY.md';
$executionInstructionTemplate = $root . '/packages/ai-universal-rules/templates/instructions/execution-protocol.instructions.md';
$executionWorkflowTemplate = $root . '/packages/ai-universal-rules/templates/workflows/evidence-first-execution.md';

foreach ([
    'packages/ai-universal-rules/templates/core/execution-protocol.template.md' => $executionProtocolTemplate,
    'packages/ai-universal-rules/templates/capabilities/evidence-first-execution/CAPABILITY.md' => $executionCapabilityTemplate,
    'packages/ai-universal-rules/templates/instructions/execution-protocol.instructions.md' => $executionInstructionTemplate,
    'packages/ai-universal-rules/templates/workflows/evidence-first-execution.md' => $executionWorkflowTemplate,
] as $label => $path) {
    if (!is_file($path)) {
        $errors[] = "missing required execution protocol file: {$label}";
    }
}

foreach ([
    'AGENTS.md' => $root . '/AGENTS.md',
    '.github/copilot-instructions.md' => $root . '/.github/copilot-instructions.md',
] as $label => $path) {
    if (is_file($path)) {
        $content = (string) file_get_contents($path);
        if (!str_contains($content, 'docs/ai/execution-protocol.md')) {
            $errors[] = "{$label} must reference docs/ai/execution-protocol.md";
        }
    }
}

// Prompt files should not widen tool grants beyond the selected agent.
$copilotPromptFiles = glob($root . '/.github/prompts/*.prompt.md') ?: [];
foreach ($copilotPromptFiles as $promptFile) {
    $promptContent = (string) file_get_contents($promptFile);
    $promptName = basename($promptFile);
    if (preg_match('/^tools:\s*\[[^\]]*(?:^|,\s*)["\'](\*|read|search|edit|execute|agent|web|todo)["\'](?:\s*,|\s*$)/m', $promptContent)) {
        $errors[] = "Copilot prompt '{$promptName}' declares broad tools — prompt files must not widen the target agent tool surface";
    }
}

// Enforce installable AI surface hard line limits. Generated outputs are intentionally excluded.
foreach (aiFileLineLimitRules($root, $errors) as $rule) {
    foreach (glob((string) $rule['pattern']) ?: [] as $path) {
        if (!is_file($path)) {
            continue;
        }
        $lines = count(preg_split('/\R/', rtrim((string) file_get_contents($path), "\r\n")) ?: []);
        $relative = relativePath($root, $path);
        if ($lines > (int) $rule['hard']) {
            $errors[] = "{$relative} has {$lines} lines, above hard max {$rule['hard']} for {$rule['label']}";
        } elseif ($lines > (int) $rule['soft']) {
            $warnings[] = "{$relative} has {$lines} lines, above soft max {$rule['soft']} for {$rule['label']}";
        }
    }
}

// Verify tools.instructions.md is present for Copilot
$toolsInstructionsFile = $root . '/.github/instructions/tools.instructions.md';
if (!is_file($toolsInstructionsFile) && is_dir($root . '/.github/instructions')) {
    $warnings[] = 'tools.instructions.md is missing from .github/instructions/ — tool enforcement may not be active';
}

foreach ($warnings as $warning) {
    fwrite(STDOUT, "WARN: {$warning}\n");
}
foreach ($errors as $error) {
    fwrite(STDERR, "ERROR: {$error}\n");
}

if ($errors === []) {
    fwrite(STDOUT, "OK: install surface validation passed\n");
}

exit($errors !== [] ? 1 : 0);

function collectAgentNames(string $directory, string $suffix): array
{
    $names = [];
    foreach (glob($directory . '/*' . $suffix) ?: [] as $path) {
        $filename = basename($path);
        $names[] = str_ends_with($filename, $suffix)
            ? substr($filename, 0, -strlen($suffix))
            : $filename;
    }
    sort($names);
    return array_values(array_unique($names));
}

function frontmatterField(string $content, string $field): ?string
{
    if (preg_match('/^---\R(.*?)\R---\R/s', $content, $matches) !== 1) {
        return null;
    }

    if (preg_match('/^' . preg_quote($field, '/') . ':\s*(.+)$/m', $matches[1], $fieldMatch) !== 1) {
        return null;
    }

    return trim((string) $fieldMatch[1], " \t\n\r\0\x0B\"'");
}

function extractRecommendedNextSteps(string $content): array
{
    $lines = preg_split('/\R/', $content) ?: [];
    $capture = false;
    $steps = [];

    foreach ($lines as $line) {
        if (preg_match('/^##\s+Recommended Next Step\b/i', $line) === 1) {
            $capture = true;
            continue;
        }

        if ($capture && preg_match('/^##\s+/', $line) === 1) {
            break;
        }

        if (!$capture) {
            continue;
        }

        if (preg_match('/^\s*-\s+(.+)$/', $line, $m) !== 1) {
            continue;
        }

        $value = trim((string) $m[1]);
        $value = preg_replace('/\s+if\s+blocked.*$/i', '', $value) ?? $value;
        $value = trim($value);
        if ($value !== '' && preg_match('/^[a-z][a-z-]*$/', $value) === 1) {
            $steps[] = $value;
        }
    }

    return array_values(array_unique($steps));
}

function relativePath(string $root, string $absolute): string
{
    return str_replace('\\', '/', substr($absolute, strlen($root) + 1));
}

function aiFileLineLimitRules(string $root, array &$errors): array
{
    $policyPath = $root . '/packages/ai-universal-rules/policies/ai-file-standards.json';
    if (!is_file($policyPath)) {
        $errors[] = 'missing ai-file-standards policy: packages/ai-universal-rules/policies/ai-file-standards.json';
        return [];
    }

    $decoded = json_decode((string) file_get_contents($policyPath), true);
    if (!is_array($decoded)) {
        $errors[] = 'invalid ai-file-standards policy JSON';
        return [];
    }

    $lineLimits = $decoded['line_limits'] ?? null;
    if (!is_array($lineLimits) || $lineLimits === []) {
        $errors[] = 'ai-file-standards policy has no line_limits';
        return [];
    }

    $rules = [];
    foreach ($lineLimits as $rule) {
        if (!is_array($rule)) {
            continue;
        }
        $patterns = $rule['patterns'] ?? null;
        if (!is_array($patterns) || $patterns === []) {
            continue;
        }
        $label = (string) ($rule['label'] ?? $rule['id'] ?? 'line-limit rule');
        $soft = (int) ($rule['warn_above'] ?? 0);
        $hard = (int) ($rule['fail_above'] ?? 0);
        foreach ($patterns as $pattern) {
            $pattern = (string) $pattern;
            if ($pattern === '') {
                continue;
            }
            $rules[] = [
                'label' => $label,
                'pattern' => $root . '/' . ltrim(str_replace('\\', '/', $pattern), '/'),
                'soft' => $soft,
                'hard' => $hard,
            ];
        }
    }

    if ($rules === []) {
        $errors[] = 'ai-file-standards policy produced no usable line-limit rules';
    }

    return $rules;
}

function validateScriptRegistryJsonParity(string $root, array $scripts, array &$errors): void
{
    $registryPath = $root . '/docs/ai/script-registry.json';
    if (!is_file($registryPath)) {
        $errors[] = 'missing script registry JSON: docs/ai/script-registry.json';
        return;
    }

    $decoded = json_decode((string) file_get_contents($registryPath), true);
    if (!is_array($decoded) || !isset($decoded['scripts']) || !is_array($decoded['scripts'])) {
        $errors[] = 'invalid script registry JSON structure in docs/ai/script-registry.json';
        return;
    }

    /** @var array<string,array<string,mixed>> $jsonScripts */
    $jsonScripts = $decoded['scripts'];

    foreach ($scripts as $id => $entry) {
        if (!isset($jsonScripts[$id]) || !is_array($jsonScripts[$id])) {
            $errors[] = "script registry JSON missing script id {$id}";
            continue;
        }
        $json = $jsonScripts[$id];
        foreach (['source_path', 'installed_path', 'pack', 'risk'] as $field) {
            $expected = (string) ($entry[$field] ?? '');
            $actual = (string) ($json[$field] ?? '');
            if ($expected !== $actual) {
                $errors[] = "script registry JSON mismatch for {$id}.{$field}: expected '{$expected}', got '{$actual}'";
            }
        }
    }

    foreach (array_keys($jsonScripts) as $id) {
        if (!array_key_exists((string) $id, $scripts)) {
            $errors[] = "script registry JSON has unknown script id {$id}";
        }
    }
}

function validateAdapterScriptReferences(string $root, array $scripts, array &$errors): void
{
    $registeredPaths = [];
    foreach ($scripts as $entry) {
        $installed = (string) ($entry['installed_path'] ?? '');
        $source = (string) ($entry['source_path'] ?? '');
        if ($installed !== '') {
            $registeredPaths[$installed] = true;
        }
        if ($source !== '') {
            $registeredPaths[$source] = true;
        }
    }

    $targets = array_merge(
        listMarkdownFilesUnder($root . '/packages/ai-universal-rules/templates/core'),
        listMarkdownFilesUnder($root . '/packages/ai-universal-rules/templates/instructions'),
        listMarkdownFilesUnder($root . '/packages/ai-universal-rules/templates/workflows'),
        listMarkdownFilesUnder($root . '/.github'),
        listMarkdownFilesUnder($root . '/.opencode')
    );

    $targets = array_values(array_unique($targets));

    foreach ($targets as $path) {
        $content = (string) file_get_contents($path);
        if (preg_match_all('#(?:<SCRIPTS_ROOT>|scripts/ai)/([A-Za-z0-9._-]+\.sh)#', $content, $matches) !== 1) {
            continue;
        }
        foreach ($matches[1] as $scriptFile) {
            $scriptPath = 'scripts/ai/' . $scriptFile;
            if (!isset($registeredPaths[$scriptPath])) {
                $errors[] = relativePath($root, $path) . " references unregistered script {$scriptPath} — add it to tools/ai/install/script-registry.php and docs/ai/script-registry.json";
            }
        }
    }
}

function listMarkdownFilesUnder(string $directory): array
{
    if (!is_dir($directory)) {
        return [];
    }

    $paths = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if ($item instanceof SplFileInfo && $item->isFile() && str_ends_with($item->getFilename(), '.md')) {
            $paths[] = str_replace('\\', '/', $item->getPathname());
        }
    }

    return $paths;
}

function validateScriptsPackCoverage(array $packs, array $scripts, array &$errors): void
{
    $scriptsPack = $packs['scripts-pack'] ?? null;
    if (!is_array($scriptsPack)) {
        $errors[] = 'scripts-pack is missing from pack registry';
        return;
    }

    $packScriptPaths = [];
    foreach ($scriptsPack as $item) {
        $source = (string) ($item['source'] ?? '');
        $target = (string) ($item['target'] ?? '');
        if (str_starts_with($source, 'scripts/ai/') && str_ends_with($source, '.sh')) {
            $packScriptPaths[$source] = true;
        }
        if (str_starts_with($target, 'scripts/ai/') && str_ends_with($target, '.sh')) {
            $packScriptPaths[$target] = true;
        }
    }

    $registryScriptPaths = [];
    foreach ($scripts as $entry) {
        // source_repo_only scripts are intentionally not shipped to target projects
        if (!empty($entry['source_repo_only'])) {
            continue;
        }
        $source = (string) ($entry['source_path'] ?? '');
        $target = (string) ($entry['installed_path'] ?? '');
        if ($source !== '') {
            $registryScriptPaths[$source] = true;
        }
        if ($target !== '') {
            $registryScriptPaths[$target] = true;
        }
    }

    foreach (array_keys($registryScriptPaths) as $path) {
        if (!isset($packScriptPaths[$path])) {
            $errors[] = "script registry path {$path} is not installed by scripts-pack";
        }
    }

    foreach (array_keys($packScriptPaths) as $path) {
        if (!isset($registryScriptPaths[$path])) {
            $errors[] = "scripts-pack path {$path} is not registered in tools/ai/install/script-registry.php";
        }
    }
}
