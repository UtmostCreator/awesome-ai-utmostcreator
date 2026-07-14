<?php

declare(strict_types=1);

require_once __DIR__ . '/profiles.php';

/**
 * Normalize one installer registry entry spec into a full entry array.
 *
 * A string spec means `source === target`. Common defaults (`type=file`,
 * `core=false`, `merge_strategy=replace`, `required=true`) are applied once,
 * then per-group `$defaults`, then the spec's own `$overrides` (highest
 * precedence). The returned entry is emitted in a canonical key order
 * (`type, source, target, core, merge_strategy, required, <extras>`) so the
 * whole registry is uniform; consumers read entries by key name, never by
 * position (see aiInstallerValidatePackRegistry and executor.php).
 *
 * @param string|array<string, mixed> $spec
 * @param array<string, mixed> $defaults
 *
 * @return array<string, mixed>
 */
function aiInstallerRegistryEntry(string|array $spec, array $defaults = []): array
{
    $overrides = is_string($spec) ? ['source' => $spec] : $spec;

    $entry = array_replace(
        [
            'type' => 'file',
            'core' => false,
            'merge_strategy' => 'replace',
            'required' => true,
        ],
        $defaults,
        $overrides,
    );

    $source = $entry['source'] ?? null;
    if (!is_string($source) || $source === '') {
        throw new InvalidArgumentException('Installer registry entry requires a non-empty source.');
    }
    $entry['target'] ??= $source;

    aiInstallerValidateRegistryEntry($entry);

    // Emit in canonical key order. Known keys first, then any remaining
    // extras (install_type, rename_ext, never_auto_merge, merge_into_existing)
    // in first-seen order.
    $ordered = [];
    foreach (['type', 'source', 'target', 'core', 'merge_strategy', 'required'] as $key) {
        $ordered[$key] = $entry[$key];
    }
    foreach ($entry as $key => $value) {
        if (!array_key_exists($key, $ordered)) {
            $ordered[$key] = $value;
        }
    }

    return $ordered;
}

/**
 * Normalize a list of entry specs with a shared default set.
 *
 * @param list<string|array<string, mixed>> $specs
 * @param array<string, mixed> $defaults
 *
 * @return list<array<string, mixed>>
 */
function aiInstallerRegistryEntries(array $specs, array $defaults = []): array
{
    return array_map(
        static fn(string|array $spec): array => aiInstallerRegistryEntry($spec, $defaults),
        $specs,
    );
}

/**
 * Validate a normalized installer registry entry, throwing on any malformed field.
 *
 * @param array<string, mixed> $entry
 */
function aiInstallerValidateRegistryEntry(array $entry): void
{
    if (!in_array($entry['type'] ?? null, ['file', 'dir'], true)) {
        throw new InvalidArgumentException(sprintf(
            'Invalid installer entry type for "%s".',
            (string) ($entry['source'] ?? '<unknown>'),
        ));
    }

    if (!in_array($entry['merge_strategy'] ?? null, ['replace', 'skip-if-exists'], true)) {
        throw new InvalidArgumentException(sprintf(
            'Invalid merge strategy for "%s".',
            (string) ($entry['source'] ?? '<unknown>'),
        ));
    }

    foreach (['source', 'target'] as $key) {
        if (!is_string($entry[$key] ?? null) || $entry[$key] === '') {
            throw new InvalidArgumentException(sprintf('Installer entry requires a non-empty %s.', $key));
        }
    }

    foreach (['core', 'required'] as $key) {
        if (!is_bool($entry[$key] ?? null)) {
            throw new InvalidArgumentException(sprintf(
                'Installer entry "%s" must contain a boolean %s.',
                (string) $entry['source'],
                $key,
            ));
        }
    }
}

/**
 * Merge domain-specific pack registries into one map, failing on duplicate pack
 * names. Unlike array_merge()/array_replace(), a pack defined in two sections is
 * a hard error rather than a silent overwrite.
 *
 * @param array<string, list<array<string, mixed>>> ...$registries
 *
 * @return array<string, list<array<string, mixed>>>
 */
function aiInstallerMergePackRegistries(array ...$registries): array
{
    $result = [];
    foreach ($registries as $registry) {
        foreach ($registry as $packName => $entries) {
            if (array_key_exists($packName, $result)) {
                throw new LogicException(sprintf('Duplicate installer pack "%s".', $packName));
            }
            $result[$packName] = $entries;
        }
    }
    return $result;
}

/**
 * @return array<string, list<array<string, mixed>>>
 */
function aiInstallerPackRegistry(): array
{
    return aiInstallerMergePackRegistries(
        aiInstallerSetupDocsPackRegistry(),
        aiInstallerCapabilityPackRegistry(),
        aiInstallerBasePackRegistry(),
        aiInstallerAdapterPackRegistry(),
        aiInstallerRuntimePackRegistry(),
        aiInstallerDocsPolicyPackRegistry(),
        aiInstallerOptionalPackRegistry(),
        aiInstallerDistributionPackRegistry(),
    );
}

/**
 * @return array<string, list<array<string, mixed>>>
 */
function aiInstallerSetupDocsPackRegistry(): array
{
    $opt = ['required' => false];

    return [
        // Entry order preserved verbatim from the pre-refactor registry: required
        // and optional docs are interleaved, so per-entry flags are inline rather
        // than grouped.
        'setup-docs' => [
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/PLACEHOLDERS.md', 'target' => 'PLACEHOLDERS.md']),
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/placeholders.json', 'target' => '.ai/placeholders.json', 'required' => false]),
            aiInstallerRegistryEntry('docs/ai/agents.md'),
            aiInstallerRegistryEntry('docs/ai/agent-script-access.md'),
            aiInstallerRegistryEntry('docs/ai/adapter-contract.md'),
            aiInstallerRegistryEntry('docs/ai/approval-boundaries.md'),
            aiInstallerRegistryEntry('docs/ai/architecture-locks.md'),
            aiInstallerRegistryEntry('docs/ai/handoff-contract.md'),
            aiInstallerRegistryEntry('docs/ai/integration-matrix.md'),
            aiInstallerRegistryEntry('docs/ai/shipped-surface-inventory.md', $opt),
            aiInstallerRegistryEntry('docs/ai/session-reentry.md'),
            aiInstallerRegistryEntry('docs/ai/source-of-truth.md'),
            aiInstallerRegistryEntry('docs/ai/schema-ownership.md', $opt),
            aiInstallerRegistryEntry('docs/ai/context-economy.md', $opt),
            aiInstallerRegistryEntry('docs/ai/opencode-models.md', $opt),
            aiInstallerRegistryEntry('docs/ai/tool-policy.md'),
            aiInstallerRegistryEntry('docs/ai/verification-matrix.md'),
            aiInstallerRegistryEntry('docs/ai/ownership.md', ['merge_strategy' => 'skip-if-exists', 'required' => false]),
            // package-boundaries.md intentionally excluded: describes this source repo's internal package layout
            aiInstallerRegistryEntry('docs/ai/copilot-getting-started.md', $opt),
            aiInstallerRegistryEntry('docs/ai/copilot-tooling.md', $opt),
            aiInstallerRegistryEntry('docs/ai/copilot-cli-repo-integration.md', $opt),
            aiInstallerRegistryEntry('docs/ai/external-repo-install.md', $opt),
            aiInstallerRegistryEntry('docs/ai/maintenance-mode.md', $opt),
            aiInstallerRegistryEntry('docs/ai/available-packs.md', $opt),
            aiInstallerRegistryEntry('docs/ai/command-policy.md', $opt),
            aiInstallerRegistryEntry('docs/ai/command-policy.tiers.yaml', $opt),
            aiInstallerRegistryEntry('docs/ai/security.md', $opt),
            aiInstallerRegistryEntry('docs/ai/catalog.md', $opt),
            aiInstallerRegistryEntry('docs/ai/index.md'),
            aiInstallerRegistryEntry('llms.txt', $opt),
            aiInstallerRegistryEntry('tools/ai/verify-install-placeholders.php'),
            // SETUP.md and package-boundaries.md intentionally excluded: source-repo-specific generated/meta files
            aiInstallerRegistryEntry('docs/ai/repo-documentation-generation.md', $opt),
            aiInstallerRegistryEntry('docs/ai/capabilities/README.md', $opt),
        ],
    ];
}

/**
 * @return array<string, list<array<string, mixed>>>
 */
function aiInstallerCapabilityPackRegistry(): array
{
    $dir = ['type' => 'dir'];

    return [
        'capabilities-core' => [
            ...aiInstallerRegistryEntries([
                ['source' => 'docs/ai/capabilities/agent-observability-and-evidence'],
            ], $dir),
            ...aiInstallerRegistryEntries([
                ['source' => 'docs/ai/capabilities/authorization-and-tool-governance'],
                ['source' => 'docs/ai/capabilities/config-change-safety'],
                ['source' => 'docs/ai/capabilities/docs-sync'],
                ['source' => 'docs/ai/capabilities/adapter-drift'],
            ], [...$dir, 'required' => false]),
        ],
    ];
}

/**
 * @return array<string, list<array<string, mixed>>>
 */
function aiInstallerBasePackRegistry(): array
{
    return [
        'base' => [
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/templates/core/AGENTS.template.md', 'target' => 'AGENTS.md', 'core' => true]),
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/templates/core/project-context.template.md', 'target' => 'docs/ai/project-context.md', 'core' => true]),
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/templates/core/project-context.placeholders.md', 'target' => 'docs/ai/project-context-placeholders.md', 'core' => true, 'required' => false]),
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/templates/core/workflow.template.md', 'target' => 'docs/ai/workflow.md', 'core' => true]),
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/templates/core/execution-protocol.template.md', 'target' => 'docs/ai/execution-protocol.md', 'core' => true]),
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/templates/core/ai-file-standards.template.md', 'target' => 'docs/ai/ai-file-standards.md', 'core' => true]),
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/templates/shared/guardrails/AI-GUARDRAILS.md', 'target' => 'docs/ai/AI-GUARDRAILS.md', 'core' => true]),
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/templates/core/generated-artifacts.template.md', 'target' => 'docs/ai/generated-artifacts.md', 'core' => true]),
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/templates/core/POST-INSTALL.template.md', 'target' => 'docs/ai/POST-INSTALL.md', 'core' => true]),
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/templates/core/project-stack.template.md', 'target' => 'docs/ai/project-stack.md', 'core' => true, 'merge_strategy' => 'skip-if-exists', 'required' => false]),
            // P5-a: user-owned project AI notes (template class; installed once, never overwritten).
            ...aiInstallerRegistryEntries([
                ['source' => 'packages/ai-universal-rules/templates/core/project/README.md', 'target' => 'docs/ai/project/README.md'],
                ['source' => 'packages/ai-universal-rules/templates/core/project/project-interaction.md', 'target' => 'docs/ai/project/project-interaction.md'],
                ['source' => 'packages/ai-universal-rules/templates/core/project/conventions.md', 'target' => 'docs/ai/project/conventions.md'],
            ], ['merge_strategy' => 'skip-if-exists', 'required' => false]),
            ...aiInstallerRegistryEntries([
                ['source' => 'packages/ai-universal-rules/templates/capabilities/project-context', 'target' => 'docs/ai/capabilities/project-context'],
                ['source' => 'packages/ai-universal-rules/templates/capabilities/verify-change', 'target' => 'docs/ai/capabilities/verify-change'],
                ['source' => 'packages/ai-universal-rules/templates/capabilities/review-diff', 'target' => 'docs/ai/capabilities/review-diff'],
                ['source' => 'packages/ai-universal-rules/templates/capabilities/evidence-first-execution', 'target' => 'docs/ai/capabilities/evidence-first-execution'],
                ['source' => 'packages/ai-universal-rules/templates/capabilities/clarification-and-handoff', 'target' => 'docs/ai/capabilities/clarification-and-handoff'],
            ], ['type' => 'dir', 'core' => true]),
            // Handoff runtime: every shipped agent body references `python handoff/dispatch.py`,
            // and the dispatcher reads handoff/generated/allowed_next.json while agent bodies point
            // at handoff/generated/HANDOFF-PROTOCOL.md. Installed projects need this runtime tooling,
            // not just the dogfood repo. agent-handoff.yaml is the routing source of truth; the
            // gen_handoff.{py,sh} regenerators + AGENT-INCLUDE.md ship so a target can re-derive
            // handoff/generated/ after local edits.
            ...aiInstallerRegistryEntries([
                ['source' => 'handoff/dispatch.py'],
                ['source' => 'handoff/agent-handoff.yaml'],
                ['source' => 'handoff/gen_handoff.py'],
                ['source' => 'handoff/gen_handoff.sh'],
                ['source' => 'handoff/AGENT-INCLUDE.md'],
            ], ['core' => true]),
            aiInstallerRegistryEntry(['type' => 'dir', 'source' => 'handoff/generated', 'target' => 'handoff/generated', 'core' => true]),
        ],
    ];
}

/**
 * @return array<string, list<array<string, mixed>>>
 */
function aiInstallerAdapterPackRegistry(): array
{
    return [
        'adapter-copilot' => [
            ...aiInstallerRegistryEntries([
                ['source' => 'packages/ai-universal-rules/templates/core/copilot-instructions.template.md', 'target' => '.github/copilot-instructions.md'],
                ['source' => 'packages/ai-universal-rules/templates/github/pull_request_template.md', 'target' => '.github/pull_request_template.md'],
            ]),
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/templates/core/copilot-vscode-settings.template.json', 'target' => '.vscode/settings.json', 'merge_strategy' => 'skip-if-exists', 'required' => false]),
            aiInstallerRegistryEntry(['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/instructions', 'target' => '.github/instructions']),
            aiInstallerRegistryEntry(['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/core/agents', 'target' => '.github/agents', 'install_type' => 'copilot-agents']),
            aiInstallerRegistryEntry(['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/workflows', 'target' => '.github/prompts', 'rename_ext' => '.prompt.md']),
            aiInstallerRegistryEntry(['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/workflows', 'target' => '.github/skills', 'install_type' => 'skill-dirs']),
            // Skill parity fix: ai-search/ai-scripts were previously OpenCode-only (see
            // adapter-opencode below); both SKILL.md sources are pure runtime-agnostic
            // markdown (no OpenCode-specific dispatch mechanics), so ship them to Copilot too.
            ...aiInstallerRegistryEntries([
                ['source' => 'packages/ai-universal-rules/templates/skills/ai-search/SKILL.md', 'target' => '.github/skills/ai-search/SKILL.md'],
                ['source' => 'packages/ai-universal-rules/templates/skills/ai-scripts/SKILL.md', 'target' => '.github/skills/ai-scripts/SKILL.md'],
                ['source' => 'packages/ai-universal-rules/templates/instructions/tools.instructions.md', 'target' => '.github/instructions/tools.instructions.md'],
                ['source' => 'packages/ai-universal-rules/templates/instructions/execution-protocol.instructions.md', 'target' => '.github/instructions/execution-protocol.instructions.md'],
            ]),
        ],
        'adapter-opencode' => [
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/templates/core/opencode.json', 'target' => 'opencode.jsonc', 'never_auto_merge' => true]),
            aiInstallerRegistryEntry(['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/core/agents', 'target' => '.opencode/agents', 'install_type' => 'opencode-agents']),
            aiInstallerRegistryEntry(['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/workflows', 'target' => '.opencode/skills', 'install_type' => 'skill-dirs']),
            // Order matters for these two .opencode/commands dir entries: executor.php's
            // aiInstallerCopyDirAsOpenCodeCommands tracks $seenDirTargets and only clears
            // (cleanFirst) the destination for the FIRST writer to a given target in plan
            // order, so the entry below clears .opencode/commands before copying, and the
            // one after it merges into the same directory without clearing. Do not reorder
            // these two or insert a third .opencode/commands dir entry between them without
            // checking that $seenDirTargets/$cleanFirst logic in executor.php first.
            aiInstallerRegistryEntry(['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/workflows', 'target' => '.opencode/commands', 'install_type' => 'opencode-commands']),
            aiInstallerRegistryEntry(['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/commands', 'target' => '.opencode/commands', 'install_type' => 'opencode-commands']),
            ...aiInstallerRegistryEntries([
                ['source' => 'packages/ai-universal-rules/templates/skills/ai-search/SKILL.md', 'target' => '.opencode/skills/ai-search/SKILL.md'],
                ['source' => 'packages/ai-universal-rules/templates/skills/ai-scripts/SKILL.md', 'target' => '.opencode/skills/ai-scripts/SKILL.md'],
            ]),
        ],
        // Claude adapter parity plan: docs/tickets/arch-todo-claude-code-adapter-parity-20260704-120000.
        // Renders the same canonical agent source as adapter-copilot/adapter-opencode into
        // .claude/agents/*.md, a rendered CLAUDE.md (P1), and a merged .claude/settings.json (P1)
        // that never clobbers a pre-existing file (e.g. graphify's own hooks additions).
        'adapter-claude' => [
            aiInstallerRegistryEntry(['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/core/agents', 'target' => '.claude/agents', 'install_type' => 'claude-agents']),
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/templates/core/CLAUDE.template.md', 'target' => 'CLAUDE.md']),
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/templates/claude/settings.json', 'target' => '.claude/settings.json', 'install_type' => 'claude-settings-merge']),
            // P2-1/P2-2: reuses skill-dirs / opencode-commands as-is (both are already
            // runtime-agnostic - confirmed by inspecting core.php: aiInstallerCopyDirAsSkillDirs
            // and aiInstallerCopyDirAsOpenCodeCommands do a generic marker-inject copy with no
            // Copilot/OpenCode-specific frontmatter transform). Claude's own docs (fetched
            // 2026-07-04) confirm `.claude/skills/<name>/SKILL.md` is the identical directory
            // shape, and its `name`/`description`/`argument-hint` frontmatter fields already match
            // the canonical source verbatim. Unlike OpenCode, templates/workflows is NOT also
            // dual-shipped to .claude/commands: Claude's own docs state skills and commands
            // register the same `/name` slash command and "skills are recommended", so
            // dual-shipping would just register duplicate commands for no benefit.
            aiInstallerRegistryEntry(['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/workflows', 'target' => '.claude/skills', 'install_type' => 'skill-dirs']),
            // Skill parity fix: ai-search/ai-scripts were previously OpenCode-only (see
            // adapter-opencode below); both SKILL.md sources are pure runtime-agnostic
            // markdown (no OpenCode-specific dispatch mechanics), so ship them to Claude too.
            ...aiInstallerRegistryEntries([
                ['source' => 'packages/ai-universal-rules/templates/skills/ai-search/SKILL.md', 'target' => '.claude/skills/ai-search/SKILL.md'],
                ['source' => 'packages/ai-universal-rules/templates/skills/ai-scripts/SKILL.md', 'target' => '.claude/skills/ai-scripts/SKILL.md'],
            ]),
            aiInstallerRegistryEntry(['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/commands', 'target' => '.claude/commands', 'install_type' => 'opencode-commands']),
        ],
        'capabilities-extended' => aiInstallerRegistryEntries([
            ['source' => 'packages/ai-universal-rules/templates/capabilities/bug-regression', 'target' => 'docs/ai/capabilities/bug-regression'],
            ['source' => 'packages/ai-universal-rules/templates/capabilities/release-safety', 'target' => 'docs/ai/capabilities/release-safety'],
        ], ['type' => 'dir']),
        'capabilities-governance' => aiInstallerRegistryEntries([
            ['source' => 'packages/ai-universal-rules/templates/capabilities/dependency-upgrade', 'target' => 'docs/ai/capabilities/dependency-upgrade'],
            ['source' => 'packages/ai-universal-rules/templates/capabilities/mentor-mode', 'target' => 'docs/ai/capabilities/mentor-mode'],
        ], ['type' => 'dir']),
    ];
}

/**
 * @return array<string, list<array<string, mixed>>>
 */
function aiInstallerRuntimePackRegistry(): array
{
    return [
        'scripts-pack' => [
            aiInstallerRegistryEntry('scripts/ai/common.sh'),
            // Ship the WHOLE internal module tree as one dir entry. The root scripts are thin
            // loaders that source scripts/ai/internal/<name>/*.sh (Phase 5/7 splits), and
            // internal/search reads internal/config/*.txt; shipping only lib+search left
            // ai-verify, ai-edit, ai-diff-context, pre-tool-use, repomix-context-tree, and
            // repomix-scc-router broken in installed targets (missing sourced modules).
            ...aiInstallerRegistryEntries([
                ['source' => 'scripts/ai/internal'],
                ['source' => 'scripts/ai/bin'],
            ], ['type' => 'dir']),
            ...aiInstallerRegistryEntries([
                ['source' => 'scripts/ai/ai-search.sh'],
                ['source' => 'scripts/ai/ai-search-multi.sh'],
                ['source' => 'scripts/ai/ai-diff-context.sh'],
                ['source' => 'scripts/ai/ai-verify.sh'],
                // Manual-only per-language convenience wrappers around ai-verify.sh --language
                // <lang>; not wired into any agent permission tier (see
                // docs/tickets/arch-todo-safe-language-verify-scripts-20260706-003959/plan.md §8-P3).
                ['source' => 'scripts/ai/ai-verify-html.sh'],
                ['source' => 'scripts/ai/ai-verify-js.sh'],
                ['source' => 'scripts/ai/ai-verify-php.sh'],
                ['source' => 'scripts/ai/ai-verify-ts.sh'],
                ['source' => 'scripts/ai/ai-verify-vue.sh'],
                ['source' => 'scripts/ai/ai-rollback.sh'],
                ['source' => 'scripts/ai/ai-edit.sh'],
            ]),
            aiInstallerRegistryEntry(['source' => '.repomixignore', 'merge_strategy' => 'skip-if-exists', 'required' => false]),
            ...aiInstallerRegistryEntries([
                ['source' => 'scripts/ai/pack-context.sh'],
                ['source' => 'scripts/ai/pre-tool-use.sh'],
                ['source' => 'scripts/ai/post-tool-use.sh'],
                ['source' => 'scripts/ai/run-repomix-context.sh'],
                ['source' => 'scripts/ai/run-repomix-file.sh'],
                ['source' => 'scripts/ai/repomix-context-tree.sh'],
                ['source' => 'scripts/ai/repomix-scc-router.sh'],
                ['source' => 'scripts/ai/repomix-freshness.sh'],
                ['source' => 'scripts/ai/repomix-ensure-fresh.sh'],
                ['source' => 'scripts/ai/git-forensics.sh'],
                ['source' => 'scripts/ai/git-branch-origin.sh'],
                ['source' => 'scripts/ai/gh-pr-context.sh'],
                ['source' => 'scripts/ai/preview-file.sh'],
                // tests/scripts/ai/test-preview-file.sh is source-repo-only — not installed to target projects
                ['source' => 'packages/ai-universal-rules/templates/docs/ai/tools/actions/preview-file.md', 'target' => 'docs/ai/tools/actions/preview-file.md'],
                // tool docs referenced by the AGENTS.md "Read This First" pointer and tool-map See-Also closure (Phase 3.1: no longer in opencode.json instructions[])
                ['source' => 'packages/ai-universal-rules/templates/docs/ai/tools/ai-search.md', 'target' => 'docs/ai/tools/ai-search.md'],
                ['source' => 'packages/ai-universal-rules/templates/docs/ai/tools/tool-map.md', 'target' => 'docs/ai/tools/tool-map.md'],
                ['source' => 'packages/ai-universal-rules/templates/docs/ai/tools/actions/search-evidence.md', 'target' => 'docs/ai/tools/actions/search-evidence.md'],
                ['source' => 'packages/ai-universal-rules/templates/docs/ai/tools/actions/use-ai-script.md', 'target' => 'docs/ai/tools/actions/use-ai-script.md'],
                ['source' => 'scripts/ai/query-usage.sh'],
                ['source' => 'scripts/ai/fd-files.sh'],
                ['source' => 'scripts/ai/rg-code.sh'],
                ['source' => 'scripts/ai/ai-structured.sh'],
                ['source' => 'scripts/ai/ai-task.sh'],
                ['source' => 'scripts/ai/ai-test-select.sh'],
            ]),
            ...aiInstallerRegistryEntries([
                ['source' => 'scripts/ai/run-repo-tests.sh'],
                ['source' => 'scripts/ai/run-test-focused.sh'],
            ], ['required' => false]),
            aiInstallerRegistryEntry('scripts/ai/session-checkpoint.sh'),
            ...aiInstallerRegistryEntries([
                ['source' => 'scripts/ai/ai-doc-check.sh'],
                ['source' => 'scripts/ai/ai-file-freshness.sh'],
                ['source' => 'scripts/ai/ai-install-coverage.sh'],
                ['source' => 'scripts/ai/check-file-refs.sh'],
                ['source' => 'scripts/ai/repo-stats.sh'],
                ['source' => 'scripts/ai/ship-audit.sh'],
            ], ['required' => false]),
            aiInstallerRegistryEntry('scripts/ai/watch-loop.sh'),
            ...aiInstallerRegistryEntries([
                ['source' => 'scripts/ai/repo-tool-inventory.sh'],
                ['source' => 'tools/ai/repo-tool-inventory.php'],
                ['source' => 'scripts/ai/sh-introspect.sh'],
                ['source' => 'tools/ai/sh-introspect.php'],
                // sh-introspect.php is a thin loader that require_once's tools/ai/sh-introspect/*.php
                // (numbered modules). Without the module dir, every --help/--introspect call in a
                // target fatals; ship the whole dir.
                ['type' => 'dir', 'source' => 'tools/ai/sh-introspect'],
                // install-mandatory-tools.sh is intentionally excluded:
                // it installs workstation-level tooling and is meant to be run from this source repo only.
                ['source' => 'docs/ai/repo-required-tools.md'],
                ['source' => 'docs/ai/mandatory-tools-install.md'],
            ], ['required' => false]),
            ...aiInstallerRegistryEntries([
                ['source' => 'docs/ai/script-registry.md'],
                ['source' => 'docs/ai/script-registry.json'],
                ['source' => 'docs/ai/script-registry.schema.json'],
            ]),
        ],
        'advisor-pack' => [
            aiInstallerRegistryEntry(['type' => 'dir', 'source' => 'tools/ai/advisor', 'target' => 'tools/ai/advisor', 'merge_strategy' => 'skip-if-exists', 'required' => false]),
            // tools/ai/advisor/registry.php require_once's this one directory up; without it,
            // `php tools/ai/ai.php advisor --all` fatals in-target (found via
            // tools/ai/export-install-bundle.php end-to-end verification).
            ...aiInstallerRegistryEntries([
                ['source' => 'tools/ai/command-exists.php'],
                ['source' => 'schemas/ai/project-signals.schema.json'],
                ['source' => 'schemas/ai/project-scorecard.schema.json'],
                ['source' => 'schemas/ai/advisor-recommendation.schema.json'],
            ], ['merge_strategy' => 'skip-if-exists', 'required' => false]),
        ],
    ];
}

/**
 * @return array<string, list<array<string, mixed>>>
 */
function aiInstallerDocsPolicyPackRegistry(): array
{
    return [
        'policy-pack' => [
            aiInstallerRegistryEntry(['source' => 'docs/ai/command-risk-taxonomy.md', 'merge_strategy' => 'skip-if-exists']),
            aiInstallerRegistryEntry(['source' => 'docs/ai/failure-handling.md', 'merge_strategy' => 'skip-if-exists']),
            aiInstallerRegistryEntry(['source' => 'schemas/ai/evidence-event.schema.json', 'merge_strategy' => 'skip-if-exists']),
        ],
        'hooks-pack' => [
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/templates/github/hooks/tool-policy.json', 'target' => '.github/hooks/tool-policy.json']),
            ...aiInstallerRegistryEntries([
                ['source' => 'packages/ai-universal-rules/templates/github/hooks/tool-guardian.json', 'target' => '.github/hooks/tool-guardian.json'],
                ['source' => 'packages/ai-universal-rules/templates/github/hooks/scripts/tool-guardian.ps1', 'target' => '.github/hooks/scripts/tool-guardian.ps1'],
                ['source' => 'packages/ai-universal-rules/templates/github/hooks/scripts/tool-guardian.sh', 'target' => '.github/hooks/scripts/tool-guardian.sh'],
                ['source' => 'packages/ai-universal-rules/templates/github/hooks/scripts/command-policy.compiled.sh', 'target' => '.github/hooks/scripts/command-policy.compiled.sh'],
            ], ['required' => false]),
            ...aiInstallerRegistryEntries([
                ['source' => 'scripts/hooks/pre-commit.sh'],
                ['source' => 'scripts/hooks/commit-msg.sh'],
                ['source' => 'docs/ai/hooks.md'],
            ], ['merge_strategy' => 'skip-if-exists']),
        ],
        'ci-pack' => [
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/templates/github/workflows/validate-ai-surface.yml', 'target' => '.github/workflows/validate-ai-surface.yml']),
            aiInstallerRegistryEntry(['source' => 'docs/ai/validation.md', 'merge_strategy' => 'skip-if-exists']),
        ],
        'evidence-pack' => aiInstallerRegistryEntries([
            ['source' => 'docs/ai/capabilities/agent-observability-and-evidence/event-schema.md'],
            ['source' => 'docs/ai/capabilities/agent-observability-and-evidence/failure-taxonomy.md'],
        ], ['merge_strategy' => 'skip-if-exists']),
        'docs-reference-pack' => aiInstallerRegistryEntries([
            ['source' => 'docs/ai/agent-ops.md'],
            ['source' => 'docs/ai/AGENTS-MANIFEST.md'],
            ['source' => 'docs/ai/agent-ops-checklist.md'],
            ['source' => 'docs/ai/failure-handling.md'],
            ['source' => 'docs/ai/validation.md'],
            ['source' => 'docs/ai/context-packing.md'],
            ['source' => 'docs/ai/hooks.md'],
            ['source' => 'docs/ai/scripts-reference.md'],
            ['source' => 'docs/ai/toolchain-requirements.md'],
            ['source' => 'docs/ai/recommended-optional-tools.md'],
        ], ['merge_strategy' => 'skip-if-exists', 'required' => false]),
    ];
}

/**
 * @return array<string, list<array<string, mixed>>>
 */
function aiInstallerOptionalPackRegistry(): array
{
    return [
        'delivery-pack' => aiInstallerRegistryEntries([
            ['source' => 'packages/ai-universal-rules/templates/optional/delivery/README.md', 'target' => 'docs/ai/delivery/README.md'],
            ['source' => 'packages/ai-universal-rules/templates/optional/delivery/slice-card.template.md', 'target' => 'docs/ai/delivery/slice-card.template.md'],
        ], ['merge_strategy' => 'skip-if-exists', 'required' => false]),
        'optional-agents-opencode-pack' => [
            // Render through the OpenCode agents renderer (not a raw dir copy) so it honors
            // `hidden: true` and skips internal-only agents (e.g. ui-builder), matching the
            // Copilot optional pack. A raw copy would ship hidden agents that must not leave
            // the kit repo. Pre-existing hidden agents in the target are preserved.
            aiInstallerRegistryEntry(['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/optional/agents', 'target' => '.opencode/agents-optional', 'install_type' => 'opencode-agents', 'required' => false]),
        ],
        'optional-agents-copilot-pack' => [
            // Render optional agents through the Copilot renderer (VS Code-native frontmatter) and
            // MERGE into .github/agents so they coexist with the base adapter-copilot agents. A raw
            // rename copy would (a) ship invalid OpenCode frontmatter and (b) clobber the base
            // agents via delete-tree. merge_into_existing selects the non-clobbering copy path.
            aiInstallerRegistryEntry(['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/optional/agents', 'target' => '.github/agents', 'install_type' => 'copilot-agents', 'merge_into_existing' => true, 'merge_strategy' => 'skip-if-exists', 'required' => false]),
        ],
        'optional-agents-claude-pack' => [
            // Render optional agents through the Claude renderer (claude-agents) and MERGE them into
            // .claude/agents alongside the base adapter-claude agents. merge_into_existing routes the
            // executor to aiInstallerMergeDirAsClaudeAgents (no delete-tree, skip-if-exists honored,
            // hidden internal-only agents skipped), so this second pack adds the optional agents
            // without clobbering the core agents — mirroring the Copilot optional pack.
            aiInstallerRegistryEntry(['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/optional/agents', 'target' => '.claude/agents', 'install_type' => 'claude-agents', 'merge_into_existing' => true, 'merge_strategy' => 'skip-if-exists', 'required' => false]),
        ],
        'preview-environments-pack' => [
            aiInstallerRegistryEntry(['type' => 'dir', 'source' => 'docs/ai/capabilities/preview-environments', 'target' => 'docs/ai/capabilities/preview-environments', 'merge_strategy' => 'skip-if-exists', 'required' => false]),
        ],
        'evaluation-pack' => [
            aiInstallerRegistryEntry(['type' => 'dir', 'source' => 'docs/ai/capabilities/evaluation-and-regression', 'target' => 'docs/ai/capabilities/evaluation-and-regression', 'merge_strategy' => 'skip-if-exists', 'required' => false]),
        ],
        'service-boundary-pack' => [
            aiInstallerRegistryEntry(['type' => 'dir', 'source' => 'docs/ai/capabilities/service-boundary-patterns', 'target' => 'docs/ai/capabilities/service-boundary-patterns', 'merge_strategy' => 'skip-if-exists', 'required' => false]),
        ],
        'mcp-boundaries-pack' => [
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/docs/operations/MCP-BOUNDARIES.md', 'target' => 'docs/ai/MCP-BOUNDARIES.md', 'merge_strategy' => 'skip-if-exists', 'required' => false]),
        ],
    ];
}

/**
 * Package descriptors shared by target-tools-pack and package-source-pack.
 *
 * @return list<array<string, mixed>>
 */
function aiInstallerPackageSourceEntries(): array
{
    return [
        aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/PLACEHOLDERS.md', 'target' => 'PLACEHOLDERS.md']),
        aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/placeholders.json', 'target' => '.ai/placeholders.json', 'required' => false]),
        // Kit package descriptors are namespaced under the byte-protected .ai/ dir so they never
        // collide with a consumer project's own root manifest.json/catalog.json/package-lock.
        aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/manifest.json', 'target' => '.ai/kit-manifest.json']),
        ...aiInstallerRegistryEntries([
            ['source' => 'packages/ai-universal-rules/manifest.yml', 'target' => '.ai/kit-manifest.yml'],
            ['source' => 'packages/ai-universal-rules/package-lock.ai.json', 'target' => '.ai/package-lock.ai.json'],
        ], ['required' => false]),
        aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/catalog.json', 'target' => '.ai/catalog.json', 'required' => false]),
        ...aiInstallerRegistryEntries([
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/docs', 'target' => 'docs/ai/package'],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/policies', 'target' => 'policies'],
        ], ['required' => false]),
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function aiInstallerTargetToolEntries(): array
{
    $targetToolInstallerFiles = aiInstallerRegistryEntries([
        ['source' => 'tools/ai/install/backup.php'],
        ['source' => 'tools/ai/install/base.sh'],
        // canonical-agent-frontmatter + the claude renderers/registry + claude-settings-merge
        // are part of core.php's require_once closure (via copilot/claude-agent-renderer.php).
        // Without them, `php tools/ai/ai.php <cmd>` fatals in-target. See
        // docs/tickets/arch-todo-install-verification-fixes-20260706-011500.
        ['source' => 'tools/ai/install/canonical-agent-frontmatter.php'],
        ['source' => 'tools/ai/install/claude-agent-renderer.php'],
        ['source' => 'tools/ai/install/claude-agent-tool-registry.php'],
        ['source' => 'tools/ai/install/claude-settings-merge.php'],
        ['source' => 'tools/ai/install/config.php'],
        // arch-todo-installer-core-executor-extraction-20260706-170757: core.php's helper
        // groups were extracted into these procedural modules, all part of core.php's
        // require_once closure. Without them, `php tools/ai/ai.php <cmd>` fatals in-target.
        ['source' => 'tools/ai/install/conflict-channels.php'],
        // Pre-existing gap found during verification of this ticket's fix: renderer requires
        // this since 2f1a866 (Copilot handoffs) but it was never added to this list.
        ['source' => 'tools/ai/install/copilot-agent-handoff-registry.php'],
        ['source' => 'tools/ai/install/copilot-agent-renderer.php'],
        ['source' => 'tools/ai/install/copilot-agent-tool-registry.php'],
        ['source' => 'tools/ai/install/core.php'],
        ['source' => 'tools/ai/install/docs.php'],
        ['source' => 'tools/ai/install/executor.php'],
        ['source' => 'tools/ai/install/fs-writers.php'],
        ['source' => 'tools/ai/install/generated-header.php'],
        ['source' => 'tools/ai/install/gitignore.php'],
        ['source' => 'tools/ai/install/install-lock.php'],
        ['source' => 'tools/ai/install/lib.sh'],
        ['source' => 'tools/ai/install/manifest.php'],
        ['source' => 'tools/ai/install/markers.php'],
        ['source' => 'tools/ai/install/migrations.php'],
        ['source' => 'tools/ai/install/packs.php'],
        ['source' => 'tools/ai/install/placeholders.php'],
        ['source' => 'tools/ai/install/plan-guards.php'],
        ['source' => 'tools/ai/install/planner.php'],
        ['source' => 'tools/ai/install/profiles.php'],
        ['source' => 'tools/ai/install/project-values.php'],
        ['source' => 'tools/ai/install/project-yaml.php'],
        ['source' => 'tools/ai/install/runtime-copilot.sh'],
        ['source' => 'tools/ai/install/runtime-opencode.sh'],
        ['source' => 'tools/ai/install/script-registry.php'],
        ['source' => 'tools/ai/install/script-runner.php'],
        // selection-engine + stack-* are required by core.php's stack-selection path
        // (commands/stack_selection.php -> stack-registry/stack-detection) and by the
        // permission-layer stack overlays.
        ['source' => 'tools/ai/install/selection-engine.php'],
        ['source' => 'tools/ai/install/stack-detection.php'],
        ['source' => 'tools/ai/install/stack-project-doc.php'],
        ['source' => 'tools/ai/install/stack-registry.php'],
        ['source' => 'tools/ai/install/toolchain.php'],
        ['source' => 'tools/ai/install/toolchain-registry.php'],
        // arch-todo-installer-workflow-command-extraction-20260706-220032: install_workflow.php's
        // helper groups were extracted into these procedural modules, part of
        // install_workflow.php's require_once closure. Without them, `php tools/ai/ai.php
        // install/upgrade/uninstall/restore` fatals in-target. Pre-existing gap found and
        // fixed during the validate-ai-config.php extraction ticket's review.
        ['source' => 'tools/ai/install/upgrade-file-actions.php'],
        ['source' => 'tools/ai/install/workflow-manifest.php'],
        ['source' => 'tools/ai/install/uninstall-prune.php'],
        ['source' => 'tools/ai/install/restore-audit.php'],
        ['source' => 'tools/ai/install/user-sections.php'],
        ['source' => 'tools/ai/install/verify-install-result.php'],
        // verify-manifest-args.php is a shared helper required by both verify-manifest.php
        // and verify-no-overwrite.php below (arch-todo-jscpd-duplication-fix-20260706).
        ['source' => 'tools/ai/install/verify-manifest-args.php'],
        ['source' => 'tools/ai/install/verify-manifest.php'],
        ['source' => 'tools/ai/install/verify-no-overwrite.php'],
        // compile-command-policy.php lives at tools/ai/ (not tools/ai/install/); core.php
        // require_once's it (guarded by is_file) when recompiling the command policy.
        ['source' => 'tools/ai/compile-command-policy.php'],
    ]);

    return [
        // Source-repo-only interactive shellcheck batches are intentionally
        // excluded from installed targets; target verification uses
        // scripts/ai/ai-verify.sh instead.
        ...$targetToolInstallerFiles,
        // Permission-layer composition modules are part of core.php's require_once closure
        // (via the agent renderers -> permission-layers/render-adapters.php -> compose.php).
        // Shipped as a whole dir so in-target ai.php commands do not fatal on a missing file.
        aiInstallerRegistryEntry(['type' => 'dir', 'source' => 'tools/ai/install/permission-layers', 'target' => 'tools/ai/install/permission-layers']),
        // validate-ai-config.php's require_once closure (validation/config-loader.php,
        // config-validator.php, reporter.php). Shipped as a whole dir so in-target
        // validate-ai-config.php does not fatal on a missing file. See
        // docs/tickets/arch-todo-validate-ai-config-extraction-20260706-223128.
        ...aiInstallerRegistryEntries([
            ['type' => 'dir', 'source' => 'tools/ai/validation', 'target' => 'tools/ai/validation'],
            ['type' => 'dir', 'source' => 'tools/ai/commands', 'target' => 'tools/ai/commands'],
        ]),
        ...aiInstallerRegistryEntries([
            ['source' => 'tools/ai/ai.php'],
            ['source' => 'tools/ai/ai_catalog_lib.php'],
            ['source' => 'tools/ai/ai_output_lib.php'],
            ['source' => 'tools/ai/build-context-pack.php'],
            ['source' => 'tools/ai/full-install-validation.php'],
            ['source' => 'tools/ai/generate-ai-catalog.php'],
            ['source' => 'tools/ai/generate-ai-file-standards.php'],
        ]),
        aiInstallerRegistryEntry(['source' => 'tools/ai/generate-agent-snippets.php', 'required' => false]),
        ...aiInstallerRegistryEntries([
            ['source' => 'tools/ai/generate-repo-structure.php'],
            ['source' => 'tools/ai/install-ai-kit.php'],
            ['source' => 'tools/ai/maintenance-mode.php'],
            ['source' => 'tools/ai/render-agent-permissions.php'],
            ['source' => 'tools/ai/secret-scan.php'],
            ['source' => 'tools/ai/suggest-verification.php'],
            ['source' => 'tools/ai/validate-adapter-drift.php'],
            ['source' => 'tools/ai/validate-ai-catalog.php'],
            ['source' => 'tools/ai/validate-ai-config.php'],
            ['source' => 'tools/ai/validate-command-policy.php'],
            ['source' => 'tools/ai/validate-context-budgets.php'],
            ['source' => 'tools/ai/validate-generated-artifacts.php'],
            ['source' => 'tools/ai/validate-install-surface.php'],
        ]),
        ...aiInstallerRegistryEntries([
            ['source' => 'tools/ai/validate-stub-surfaces.php'],
            ['source' => 'tools/ai/validate-catalog-drift.php'],
            ['source' => 'tools/ai/validate-agent-spec.php'],
            ['source' => 'tools/ai/validate-schemas.php'],
            ['source' => 'tools/ai/validate-mentor-parity.php'],
            ['source' => 'tools/ai/validate-agent-assessment.php'],
            ['source' => 'tools/ai/validate-agent-assessment-values.php'],
        ], ['required' => false]),
        ...aiInstallerRegistryEntries([
            ['source' => 'tools/ai/verify-install-placeholders.php'],
            ['source' => 'tools/ai/validate-placeholders.php'],
            ['source' => 'tools/ai/verify-full-install.php'],
        ]),
        ...aiInstallerRegistryEntries([
            ['source' => 'schemas/ai/ai-catalog.schema.json'],
            ['source' => 'schemas/ai/ai-command-policy.schema.json'],
            ['source' => 'schemas/ai/ai-file-standards.schema.json'],
        ], ['merge_strategy' => 'skip-if-exists']),
        ...aiInstallerRegistryEntries([
            ['source' => 'schemas/ai/agent-spec.schema.json'],
            ['source' => 'schemas/ai/ai-handoff.schema.json'],
        ], ['merge_strategy' => 'skip-if-exists', 'required' => false]),
        ...aiInstallerRegistryEntries([
            ['source' => 'schemas/ai/ai-universal-rules-manifest.schema.json'],
            ['source' => 'schemas/ai/generated-artifacts.schema.json'],
            ['source' => 'schemas/ai/project-placeholders.schema.json'],
            ['source' => 'schemas/ai/verification-matrix.schema.json'],
        ], ['merge_strategy' => 'skip-if-exists']),
        ...aiInstallerRegistryEntries([
            ['source' => 'schemas/ai/agent-assessment.schema.json'],
            ['source' => 'schemas/ai/agent-assessment-values.schema.json'],
        ], ['merge_strategy' => 'skip-if-exists', 'required' => false]),
        // NOTE: docs/ai/agent-scores.yaml (the D3a VALUES SOURCE) is intentionally NOT
        // shipped. Its keys must match the agent templates present in the target, which
        // varies by profile/runtime; shipping the source-repo file (24 keys) into a
        // core-only target (13 templates) makes its own validator report stale keys. The
        // schema + validator ship so a target can author its own agent-scores.yaml; the
        // ai-doc-check wiring is a no-op until that file exists locally.
    ];
}

/**
 * @return array<string, list<array<string, mixed>>>
 */
function aiInstallerDistributionPackRegistry(): array
{
    return [
        'target-tools-pack' => [
            ...aiInstallerPackageSourceEntries(),
            ...aiInstallerTargetToolEntries(),
        ],
        'shared-templates-pack' => [
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/templates/shared/project-interaction.md', 'target' => 'docs/ai/shared/project-interaction.md', 'required' => false]),
            ...aiInstallerRegistryEntries([
                ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/shared/approvals', 'target' => 'docs/ai/shared/approvals'],
                ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/shared/verification', 'target' => 'docs/ai/shared/verification'],
                ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/snippets', 'target' => 'docs/ai/snippets'],
            ], ['required' => false]),
        ],
        // Ship the source package descriptor + generated catalog so installed
        // targets can run generate-ai-catalog/validate-generated-artifacts and
        // package-verify without re-downloading the source repo.
        'package-source-pack' => [
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/manifest.json', 'target' => '.ai/kit-manifest.json']),
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/catalog.json', 'target' => '.ai/catalog.json']),
            ...aiInstallerRegistryEntries([
                ['source' => 'packages/ai-universal-rules/manifest.yml', 'target' => '.ai/kit-manifest.yml'],
                ['source' => 'packages/ai-universal-rules/package-lock.ai.json', 'target' => '.ai/package-lock.ai.json'],
            ], ['required' => false]),
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/PLACEHOLDERS.md', 'target' => 'PLACEHOLDERS.md']),
            aiInstallerRegistryEntry(['source' => 'packages/ai-universal-rules/placeholders.json', 'target' => '.ai/placeholders.json', 'required' => false]),
            ...aiInstallerRegistryEntries([
                ['type' => 'dir', 'source' => 'packages/ai-universal-rules/docs', 'target' => 'docs/ai/package'],
                ['type' => 'dir', 'source' => 'packages/ai-universal-rules/policies', 'target' => 'policies'],
            ], ['required' => false]),
        ],
        // Kit-authoring / re-distribution assets. Not needed by consumer
        // projects; opt in via --with kit-authoring-pack or --all-features.
        'kit-authoring-pack' => [
            aiInstallerRegistryEntry(['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates', 'target' => 'packages/ai-universal-rules/templates']),
            aiInstallerRegistryEntry(['source' => 'tools/ai/export-ai-universal-rules.php']),
            aiInstallerRegistryEntry(['source' => 'tools/ai/export-install-bundle.php', 'required' => false]),
            ...aiInstallerRegistryEntries([
                ['source' => 'packages/ai-universal-rules/templates/github/workflows/test-external-install.yml', 'target' => '.github/workflows/test-external-install.yml'],
                ['source' => 'packages/ai-universal-rules/templates/github/workflows/export-ai-universal-rules-preview.yml', 'target' => '.github/workflows/export-ai-universal-rules-preview.yml'],
            ], ['required' => false]),
        ],
    ];
}

function aiInstallerValidatePackRegistry(array $registry): array
{
    $errors = [];
    foreach ($registry as $packId => $items) {
        if (!is_array($items)) {
            $errors[] = "pack {$packId} must be a list";
            continue;
        }
        foreach ($items as $index => $item) {
            foreach (['source', 'target', 'merge_strategy', 'required'] as $field) {
                if (!array_key_exists($field, $item)) {
                    $errors[] = "pack {$packId} item {$index} missing {$field}";
                }
            }
            $target = (string) ($item['target'] ?? '');
            if ($target !== '' && aiInstallerIsReservedUserNamespace($target)) {
                $errors[] = "pack {$packId} item {$index} ships into the reserved user namespace: {$target}";
            }
        }
    }
    return $errors;
}

/**
 * The kit reserves a private namespace for user-authored AI content so it never collides
 * with shipped files: `local-*` basenames, `*.local.*` files, and any path under a `local/`
 * directory. The kit must never ship into these.
 */
function aiInstallerIsReservedUserNamespace(string $path): bool
{
    $normalized = str_replace('\\', '/', trim($path));
    if ($normalized === '') {
        return false;
    }

    if (preg_match('#(^|/)local/#', $normalized) === 1) {
        return true;
    }

    $basename = basename($normalized);
    if (str_starts_with($basename, 'local-')) {
        return true;
    }
    if (preg_match('/\.local\./', $basename) === 1) {
        return true;
    }

    return false;
}

function aiInstallerResolveSelectedPacks(array $config, array $registry): array
{
    $profileDefs = aiInstallerProfileDefinitions();
    $profile = (string) ($config['profile'] ?? 'dual');
    $runtime = (string) ($config['runtime'] ?? 'both');
    $allFeatures = (bool) ($config['allFeatures'] ?? false);

    $packs = $allFeatures
        ? aiInstallerAllFeaturePacks()
        : aiInstallerExpandProfilePacks((array) ($profileDefs[$profile] ?? []), $profileDefs, $registry);

    if (($config['installBase'] ?? true) && !in_array('base', $packs, true)) {
        $packs[] = 'base';
    }

    if ($runtime === 'github-copilot') {
        $packs = array_values(array_filter($packs, static fn(string $p): bool => $p !== 'adapter-opencode' && $p !== 'adapter-claude'));
        if (in_array($profile, ['copilot', 'dual', 'accelerated', 'full-governance'], true) && !in_array('adapter-copilot', $packs, true)) {
            $packs[] = 'adapter-copilot';
        }
    } elseif ($runtime === 'opencode') {
        $packs = array_values(array_filter($packs, static fn(string $p): bool => $p !== 'adapter-copilot' && $p !== 'adapter-claude'));
        if (in_array($profile, ['opencode', 'dual', 'accelerated', 'full-governance'], true) && !in_array('adapter-opencode', $packs, true)) {
            $packs[] = 'adapter-opencode';
        }
    } elseif ($runtime === 'claude-code') {
        // Defense-in-depth only (Claude adapter parity plan, Chosen approach (b)): every profile
        // that ships adapter-claude already bakes it directly into its own definition above, so
        // this branch's job is narrower than the two above it — it only needs to strip the OTHER
        // two adapters when a caller explicitly forces --runtime claude-code, and re-add
        // adapter-claude for profiles that imply "ship some adapter" but do not bake it in
        // (dual/accelerated), mirroring the copilot/opencode branches' shape exactly.
        $packs = array_values(array_filter($packs, static fn(string $p): bool => $p !== 'adapter-copilot' && $p !== 'adapter-opencode'));
        if (in_array($profile, ['claude', 'dual', 'accelerated', 'full-governance'], true) && !in_array('adapter-claude', $packs, true)) {
            $packs[] = 'adapter-claude';
        }
    }

    foreach (($config['withPacks'] ?? []) as $pack) {
        if (!in_array($pack, $packs, true)) {
            $packs[] = $pack;
        }
    }
    foreach (($config['withoutPacks'] ?? []) as $pack) {
        $packs = array_values(array_filter($packs, static fn(string $v): bool => $v !== $pack));
    }

    $packs = aiInstallerExpandProfilePacks($packs, $profileDefs, $registry);
    $packs = array_values(array_unique($packs));
    $packs = array_values(array_filter($packs, static fn(string $pack): bool => isset($registry[$pack])));
    return $packs;
}

function aiInstallerExpandProfilePacks(array $items, array $profileDefs, array $registry): array
{
    $expanded = [];
    $queue = array_values($items);
    $seenProfiles = [];

    while ($queue !== []) {
        $item = (string) array_shift($queue);
        if ($item === '') {
            continue;
        }
        if (isset($registry[$item])) {
            $expanded[] = $item;
            continue;
        }
        if (isset($profileDefs[$item])) {
            if (isset($seenProfiles[$item])) {
                continue;
            }
            $seenProfiles[$item] = true;
            foreach ((array) $profileDefs[$item] as $nested) {
                $queue[] = (string) $nested;
            }
        }
    }

    return array_values(array_unique($expanded));
}

/**
 * Agents reference scripts/ai/*.sh in their permission allowlists and load capability
 * docs. When an agent pack is selected without scripts-pack, the installed agents will
 * reference commands that are not present. Returns operator-facing warning strings
 * (empty when the selection is coherent). Shared by the install-ai-kit and ai.php
 * install surfaces so the detection stays in one place.
 *
 * @param list<string> $selectedPacks
 * @return list<string>
 */
function aiInstallerAgentDependencyWarnings(array $selectedPacks): array
{
    $warnings = [];
    $agentPacks = ['adapter-copilot', 'adapter-opencode', 'optional-agents-opencode-pack', 'optional-agents-copilot-pack', 'optional-agents-claude-pack'];
    if (array_intersect($agentPacks, $selectedPacks) !== [] && !in_array('scripts-pack', $selectedPacks, true)) {
        $warnings[] = 'Agents were installed without scripts-pack: agent permission allowlists reference scripts/ai/*.sh that are not present. Re-run with --with scripts-pack or use an edition that includes it (standard, creator, full, agents-only).';
    }
    return $warnings;
}

function aiInstallerPackToolRequirements(array $selectedPacks): array
{
    $required = [];
    $optional = [];
    if (in_array('scripts-pack', $selectedPacks, true)) {
        $required = array_merge($required, ['bash', 'git', 'jq', 'rg', 'fd', 'ast-grep', 'repomix', 'scc']);
        $optional = array_merge($optional, ['gh', 'fzf', 'bat', 'delta', 'yq', 'shellcheck', 'semgrep']);
    }
    return [
        'required' => array_values(array_unique($required)),
        'optional' => array_values(array_unique($optional)),
    ];
}
