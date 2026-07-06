<?php

declare(strict_types=1);

/**
 * Resolve the machine-readable placeholder registry (placeholders.json).
 * Lookup order: kit package source, installed `.ai/` descriptor, project root.
 *
 * @return array<string,mixed>|null decoded registry or null when absent/invalid
 */
function aiPlaceholderRegistryLoad(string $root): ?array
{
    $candidates = [
        $root . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'ai-universal-rules' . DIRECTORY_SEPARATOR . 'placeholders.json',
        $root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'placeholders.json',
        $root . DIRECTORY_SEPARATOR . 'placeholders.json',
    ];
    foreach ($candidates as $path) {
        if (!is_file($path)) {
            continue;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (is_array($decoded) && is_array($decoded['tokens'] ?? null)) {
            return $decoded;
        }
    }
    return null;
}

/**
 * Required placeholder tokens. Sourced from placeholders.json; the hardcoded
 * list is a fallback for targets installed before the registry shipped.
 *
 * @return array<int,string>
 */
function aiInstallerRequiredPlaceholderTokens(string $root): array
{
    $registry = aiPlaceholderRegistryLoad($root);
    if ($registry !== null) {
        $required = [];
        foreach ($registry['tokens'] as $entry) {
            if (is_array($entry) && ($entry['required'] ?? false) === true && is_string($entry['token'] ?? null)) {
                $required[] = $entry['token'];
            }
        }
        if ($required !== []) {
            return $required;
        }
    }

    return [
        '<PROJECT_NAME>', '<PROJECT_TYPE>', '<PRIMARY_LANGUAGE>', '<PRIMARY_RUNTIME>',
        '<SOURCE_DIRS>', '<TEST_DIRS>', '<TEST_COMMAND>', '<BUILD_COMMAND>',
        '<LINT_COMMAND>', '<PACKAGE_MANAGER>', '<CI_COMMANDS>', '<PROTECTED_PATHS>',
        // Project-context extensions: an installed project must resolve these
        // before AI write-capable flows are trusted.
        '<PRIMARY_STACK>', '<FILE_PLACEMENT_RULES>', '<NAMING_RULES>',
        '<GOLDEN_EXAMPLES>', '<FORMATTER_CONFIG_FILES>', '<LINTER_CONFIG_FILES>',
        '<EDITORCONFIG_PATH>', '<IGNORE_FILES>', '<GENERATED_FILES>',
        '<PROTECTED_FILES>', '<INSTALL_COMMAND>', '<FORMAT_COMMAND>',
    ];
}

function aiInstallerCollectPlaceholderStatus(string $targetRoot): array
{
    $required = aiInstallerRequiredPlaceholderTokens($targetRoot);
    // Note: <SCRIPTS_ROOT> is resolved at install time and is intentionally not in the required list.
    // Note: <UNKNOWN>, <FILES_OR_COMMANDS_CHECKED>, <NEXT_STEP> are format slots in blocked-response examples
    //       and are not project values. They stay as placeholders in canonical docs.
    // Note: <PROJECT_FORMATTING_EXCEPTIONS>, <PROJECT_IGNORED_FILES>, <PROJECT_ALLOWED_SCRIPTS>,
    //       <PROJECT_FORBIDDEN_SCRIPTS>, <PROJECT_SECURITY_RULES> are project-specific and remain
    //       optional; they should be filled when relevant for the installed project.
    $hits = [];
    $scanRoots = ['AGENTS.md', 'docs/ai', '.github', '.opencode'];
    foreach ($scanRoots as $path) {
        $abs = $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (is_file($abs)) {
            $hits = array_merge($hits, aiInstallerExtractPlaceholders((string) file_get_contents($abs)));
            continue;
        }
        if (!is_dir($abs)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile() || strtolower($f->getExtension()) !== 'md') {
                continue;
            }
            $relative = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($targetRoot))), '/');
            if (aiInstallerShouldSkipPlaceholderScanPath($relative)) {
                continue;
            }
            $hits = array_merge($hits, aiInstallerExtractPlaceholders((string) file_get_contents($f->getPathname())));
        }
    }
    $hits = array_values(array_unique($hits));
    $unresolvedRequired = array_values(array_intersect($required, $hits));
    $unresolvedOptional = array_values(array_diff($hits, $required));
    $resolvedHash = 'sha256:' . hash('sha256', json_encode(['required' => $unresolvedRequired, 'optional' => $unresolvedOptional], JSON_UNESCAPED_SLASHES));
    return [
        'unresolved_required' => $unresolvedRequired,
        'unresolved_optional' => $unresolvedOptional,
        'resolved_values_hash' => $resolvedHash,
    ];
}

function aiInstallerShouldSkipPlaceholderScanPath(string $relativePath): bool
{
    if ($relativePath === 'docs/ai/catalog.md' || $relativePath === 'docs/ai/project-context-placeholders.md') {
        return true;
    }

    return str_starts_with($relativePath, 'docs/ai/generated/');
}

function aiInstallerExtractPlaceholders(string $content): array
{
    if (preg_match_all('/<[A-Z0-9_]+>/', $content, $m) !== 1 && (!isset($m[0]) || $m[0] === [])) {
        return [];
    }
    return array_values(array_unique($m[0] ?? []));
}

function aiInstallerApplyPlaceholders(string $targetRoot, string $projectName, array $plan, bool $allowSkippedUnmanaged = false): void
{
    $projectValues = aiInstallerLoadProjectValues($targetRoot, $projectName);
    $map = [
        '<PROJECT_NAME>' => $projectName,
        '<PROJECT_SUMMARY>' => 'AI workflow starter for ' . $projectName,
        '<PROJECT_TYPE>' => aiInstallerDetectProjectType($targetRoot),
        '<PRIMARY_LANGUAGE>' => 'unknown',
        '<PRIMARY_RUNTIME>' => 'unknown',
        '<ACTIVE_PATHS>' => aiInstallerCollectActivePaths($targetRoot),
        '<INACTIVE_PATHS>' => 'unknown',
        '<PRIMARY_ENTRYPOINTS>' => 'README.md, docs/ai/project-context.md',
        '<PRIMARY_VERIFY_COMMAND>' => 'unknown',
        '<PRIMARY_BUILD_COMMAND>' => 'unknown',
        '<PRIMARY_TEST_COMMAND>' => 'unknown',
        '<PROJECT_CONTEXT_PATH>' => 'docs/ai/project-context.md',
        '<AVAILABLE_CAPABILITIES>' => 'project-context, verify-change, review-diff',
        '<REVIEW_PRIORITIES>' => 'correctness, regressions, configuration drift',
        '<APPROVAL_REQUIRED_CHANGES>' => 'secrets, destructive changes, auth or billing changes',
        '<TARGET_PLATFORMS>' => 'unknown',
        '<SOURCE_DIRS>' => 'unknown',
        '<TEST_DIRS>' => 'unknown',
        '<TEST_COMMAND>' => 'unknown',
        '<BUILD_COMMAND>' => 'unknown',
        '<LINT_COMMAND>' => 'unknown',
        '<PACKAGE_MANAGER>' => 'unknown',
        '<CI_COMMANDS>' => 'unknown',
        '<PROTECTED_PATHS>' => 'unknown',
        '<ARCHITECTURE_NOTES>' => 'Keep policy and capability docs canonical; keep runtime adapters thin.',
        '<RISK_AREAS>' => 'stale docs, adapter drift, unsafe command usage',
        '<NARROW_VERIFY_GUIDANCE>' => 'start with the narrowest repo-local check and escalate only if needed',
        '<CAPABILITY_COMPOSITION_NOTES>' => 'start with project-context, then verify-change, then review-diff',
        '<RELEASE_SAFETY_NOTES>' => 'define rollback posture for medium/high risk changes',
        '<KNOWN_GOTCHA_THEMES>' => 'stale paths, broad edits without evidence, guessed behavior',
        '<COPILOT_SURFACE>' => 'VS Code, CLI, GitHub.com',
        '<SUPPORTED_FEATURES>' => 'repo instructions, path instructions',
        '<OPTIONAL_FEATURES>' => 'prompt files, custom agents, hooks, MCP',
        '<INSTRUCTION_PRECEDENCE_NOTES>' => 'Nearest AGENTS.md wins for agent instructions.',
        '<CONFLICT_AVOIDANCE_NOTES>' => 'Keep repo-wide and path-specific guidance complementary.',
        '<GLOBAL_OR_SHARED_RULE_SOURCES>' => 'organization instructions, user-level instructions',
        '<OPTIONAL_VERIFY_COMMAND>' => 'unknown',
        '<SCRIPTS_ROOT>' => 'scripts/ai',
        // arch-todo-copilot-claude-policy-enforcement-20260706-024750 P1-1/P1-2: these two
        // Copilot `applyTo` globs had no placeholder-map entry at all, so they rendered as the
        // literal (invalid) token string. No project.yml key exists for them (nor is one added
        // here — see the ticket's plan for the smaller-blast-radius rationale), so ship a safe,
        // broadly-applicable default glob. Still user-editable post-install like any other
        // instruction file; this only prevents shipping a broken `applyTo` value by default.
        '<TEST_PATH_GLOB>' => '**/*.test.*,**/*.spec.*,**/tests/**,**/test/**,**/__tests__/**',
        '<FRONTEND_PATH_GLOB>' => '**/*.tsx,**/*.jsx,**/*.vue,**/*.svelte,**/frontend/**,**/client/**,**/web/**,**/ui/**',
        // Project-context extension defaults. Installed projects should overwrite
        // these with concrete values during the first audit.
        '<PRIMARY_STACK>' => 'unknown',
        '<FILE_PLACEMENT_RULES>' => 'unknown',
        '<NAMING_RULES>' => 'unknown',
        '<GOLDEN_EXAMPLES>' => 'unknown',
        '<FORMATTER_CONFIG_FILES>' => 'unknown',
        '<LINTER_CONFIG_FILES>' => 'unknown',
        '<EDITORCONFIG_PATH>' => 'unknown',
        '<IGNORE_FILES>' => 'unknown',
        '<GENERATED_FILES>' => 'unknown',
        '<PROTECTED_FILES>' => 'unknown',
        '<INSTALL_COMMAND>' => 'unknown',
        '<FORMAT_COMMAND>' => 'unknown',
        '<PROJECT_FORMATTING_EXCEPTIONS>' => 'unknown',
        '<PROJECT_IGNORED_FILES>' => 'unknown',
        '<PROJECT_ALLOWED_SCRIPTS>' => 'unknown',
        '<PROJECT_FORBIDDEN_SCRIPTS>' => 'unknown',
        '<PROJECT_SECURITY_RULES>' => 'unknown',
    ];
    $map = array_merge($map, aiInstallerProjectValuesPlaceholderMap($projectValues));
    // Inject the user-owned extra-docs list from .ai/project.yml context.extraDocs.
    // It is regenerated from project.yml on every install, so user pointers survive re-render.
    $map['<EXTRA_DOCS>'] = aiInstallerRenderExtraDocsBlock(aiInstallerLoadProjectExtraDocs($targetRoot));

    foreach ($plan as $item) {
        $target = (string) ($item['target'] ?? '');
        $action = (string) ($item['action'] ?? '');
        if (
            $action === 'SKIP_PROTECTED_CORE'
            || ($action === 'SKIP_EXISTING_UNMANAGED' && !$allowSkippedUnmanaged)
        ) {
            continue;
        }
        // The shipped package source must stay verbatim so kit-level tools
        // (generate-ai-catalog, validate-ai-catalog, package-verify) keep
        // working in installed targets. PLACEHOLDERS.md is also informational
        // and must not be rewritten. Consumer installs now relocate the package
        // descriptors to the root and to docs/ai/package + policies, so those
        // relocated targets must also skip placeholder rewriting (rewriting a
        // descriptor or its docs/policies would corrupt JSON/lock/policy data).
        if (
            $target === 'PLACEHOLDERS.md'
            || $target === '.ai/kit-manifest.json'
            || $target === '.ai/kit-manifest.yml'
            || $target === '.ai/catalog.json'
            || $target === '.ai/package-lock.ai.json'
            || str_starts_with($target, 'packages/ai-universal-rules/')
            || str_starts_with($target, 'docs/ai/package/')
            || str_starts_with($target, 'policies/')
        ) {
            continue;
        }
        $abs = $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);
        if (is_file($abs) && str_ends_with(strtolower($abs), '.md')) {
            $content = (string) file_get_contents($abs);
            file_put_contents($abs, str_replace(array_keys($map), array_values($map), $content));
            continue;
        }
        if (!is_dir($abs)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') {
                continue;
            }
            $content = (string) file_get_contents($file->getPathname());
            file_put_contents($file->getPathname(), str_replace(array_keys($map), array_values($map), $content));
        }
    }
}

/** @param array<string,string> $values @return array<string,string> */
function aiInstallerProjectValuesPlaceholderMap(array $values): array
{
    // The original nine tokens always emit (their base-map defaults are 'unknown'/detected,
    // so emitting the project value — even 'unknown' — is equivalent and back-compatible).
    $map = [
        '<PROJECT_NAME>' => $values['projectName'] ?? '',
        '<PROJECT_TYPE>' => $values['projectType'] ?? '',
        '<PROJECT_SUMMARY>' => $values['projectSummary'] ?? '',
        '<PRIMARY_LANGUAGE>' => $values['primaryLanguage'] ?? '',
        '<PRIMARY_RUNTIME>' => $values['primaryRuntime'] ?? '',
        '<PRIMARY_ENTRYPOINTS>' => $values['primaryEntrypoints'] ?? '',
        '<PRIMARY_VERIFY_COMMAND>' => $values['primaryVerifyCommand'] ?? '',
        '<PRIMARY_BUILD_COMMAND>' => $values['primaryBuildCommand'] ?? '',
        '<PRIMARY_TEST_COMMAND>' => $values['primaryTestCommand'] ?? '',
    ];

    // P4-a: customizable project-fact tokens sourced from project.yml. Only emit one when the
    // user supplied a concrete (non-empty, non-'unknown') value, so the richer base-map
    // defaults (e.g. review priorities, risk areas) survive when project.yml leaves them unset.
    $projectFactTokens = [
        'targetPlatforms' => '<TARGET_PLATFORMS>',
        'sourceDirs' => '<SOURCE_DIRS>',
        'testDirs' => '<TEST_DIRS>',
        'testCommand' => '<TEST_COMMAND>',
        'buildCommand' => '<BUILD_COMMAND>',
        'lintCommand' => '<LINT_COMMAND>',
        'formatCommand' => '<FORMAT_COMMAND>',
        'installCommand' => '<INSTALL_COMMAND>',
        'packageManager' => '<PACKAGE_MANAGER>',
        'ciCommands' => '<CI_COMMANDS>',
        'protectedPaths' => '<PROTECTED_PATHS>',
        'generatedFiles' => '<GENERATED_FILES>',
        'protectedFiles' => '<PROTECTED_FILES>',
        'reviewPriorities' => '<REVIEW_PRIORITIES>',
        'riskAreas' => '<RISK_AREAS>',
        'approvalRequiredChanges' => '<APPROVAL_REQUIRED_CHANGES>',
        'inactivePaths' => '<INACTIVE_PATHS>',
        'availableCapabilities' => '<AVAILABLE_CAPABILITIES>',
        'primaryStack' => '<PRIMARY_STACK>',
        'filePlacementRules' => '<FILE_PLACEMENT_RULES>',
        'namingRules' => '<NAMING_RULES>',
        'goldenExamples' => '<GOLDEN_EXAMPLES>',
        'formatterConfigFiles' => '<FORMATTER_CONFIG_FILES>',
        'linterConfigFiles' => '<LINTER_CONFIG_FILES>',
        'editorconfigPath' => '<EDITORCONFIG_PATH>',
        'ignoreFiles' => '<IGNORE_FILES>',
    ];
    foreach ($projectFactTokens as $key => $token) {
        $value = (string) ($values[$key] ?? '');
        if ($value !== '' && $value !== 'unknown') {
            $map[$token] = $value;
        }
    }

    return $map;
}
