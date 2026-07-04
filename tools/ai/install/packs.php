<?php

declare(strict_types=1);

require_once __DIR__ . '/profiles.php';

function aiInstallerPackRegistry(): array
{
    $targetToolInstallerFiles = array_map(
        static fn(string $path): array => [
            'type' => 'file',
            'source' => $path,
            'target' => $path,
            'core' => false,
            'merge_strategy' => 'replace',
            'required' => true,
        ],
        [
            'tools/ai/install/backup.php',
            'tools/ai/install/base.sh',
            'tools/ai/install/config.php',
            'tools/ai/install/copilot-agent-renderer.php',
            'tools/ai/install/copilot-agent-tool-registry.php',
            'tools/ai/install/core.php',
            'tools/ai/install/docs.php',
            'tools/ai/install/generated-header.php',
            'tools/ai/install/lib.sh',
            'tools/ai/install/manifest.php',
            'tools/ai/install/markers.php',
            'tools/ai/install/migrations.php',
            'tools/ai/install/packs.php',
            'tools/ai/install/planner.php',
            'tools/ai/install/profiles.php',
            'tools/ai/install/project-yaml.php',
            'tools/ai/install/runtime-copilot.sh',
            'tools/ai/install/runtime-opencode.sh',
            'tools/ai/install/script-registry.php',
            'tools/ai/install/script-runner.php',
            'tools/ai/install/toolchain.php',
            'tools/ai/install/toolchain-registry.php',
            'tools/ai/install/verify-install-result.php',
            'tools/ai/install/verify-manifest.php',
            'tools/ai/install/verify-no-overwrite.php',
        ]
    );

    return [
        'setup-docs' => [
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/PLACEHOLDERS.md', 'target' => 'PLACEHOLDERS.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/placeholders.json', 'target' => '.ai/placeholders.json', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/agents.md', 'target' => 'docs/ai/agents.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'docs/ai/agent-script-access.md', 'target' => 'docs/ai/agent-script-access.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'docs/ai/adapter-contract.md', 'target' => 'docs/ai/adapter-contract.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'docs/ai/approval-boundaries.md', 'target' => 'docs/ai/approval-boundaries.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'docs/ai/architecture-locks.md', 'target' => 'docs/ai/architecture-locks.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'docs/ai/handoff-contract.md', 'target' => 'docs/ai/handoff-contract.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'docs/ai/integration-matrix.md', 'target' => 'docs/ai/integration-matrix.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'docs/ai/shipped-surface-inventory.md', 'target' => 'docs/ai/shipped-surface-inventory.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/session-reentry.md', 'target' => 'docs/ai/session-reentry.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'docs/ai/source-of-truth.md', 'target' => 'docs/ai/source-of-truth.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'docs/ai/schema-ownership.md', 'target' => 'docs/ai/schema-ownership.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/context-economy.md', 'target' => 'docs/ai/context-economy.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/opencode-models.md', 'target' => 'docs/ai/opencode-models.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/tool-policy.md', 'target' => 'docs/ai/tool-policy.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'docs/ai/verification-matrix.md', 'target' => 'docs/ai/verification-matrix.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'docs/ai/ownership.md', 'target' => 'docs/ai/ownership.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            // package-boundaries.md intentionally excluded: describes this source repo's internal package layout
            ['type' => 'file', 'source' => 'docs/ai/copilot-getting-started.md', 'target' => 'docs/ai/copilot-getting-started.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/copilot-tooling.md', 'target' => 'docs/ai/copilot-tooling.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/copilot-cli-repo-integration.md', 'target' => 'docs/ai/copilot-cli-repo-integration.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/external-repo-install.md', 'target' => 'docs/ai/external-repo-install.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/maintenance-mode.md', 'target' => 'docs/ai/maintenance-mode.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/available-packs.md', 'target' => 'docs/ai/available-packs.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/command-policy.md', 'target' => 'docs/ai/command-policy.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/command-policy.tiers.yaml', 'target' => 'docs/ai/command-policy.tiers.yaml', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/security.md', 'target' => 'docs/ai/security.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/catalog.md', 'target' => 'docs/ai/catalog.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'llms.txt', 'target' => 'llms.txt', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'tools/ai/verify-install-placeholders.php', 'target' => 'tools/ai/verify-install-placeholders.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            // SETUP.md and package-boundaries.md intentionally excluded: source-repo-specific generated/meta files
            ['type' => 'file', 'source' => 'docs/ai/repo-documentation-generation.md', 'target' => 'docs/ai/repo-documentation-generation.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/capabilities/README.md', 'target' => 'docs/ai/capabilities/README.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
        ],
        'capabilities-core' => [
            ['type' => 'dir', 'source' => 'docs/ai/capabilities/agent-observability-and-evidence', 'target' => 'docs/ai/capabilities/agent-observability-and-evidence', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'dir', 'source' => 'docs/ai/capabilities/authorization-and-tool-governance', 'target' => 'docs/ai/capabilities/authorization-and-tool-governance', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'dir', 'source' => 'docs/ai/capabilities/config-change-safety', 'target' => 'docs/ai/capabilities/config-change-safety', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'dir', 'source' => 'docs/ai/capabilities/docs-sync', 'target' => 'docs/ai/capabilities/docs-sync', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'dir', 'source' => 'docs/ai/capabilities/adapter-drift', 'target' => 'docs/ai/capabilities/adapter-drift', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
        ],
        'base' => [
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/core/AGENTS.template.md', 'target' => 'AGENTS.md', 'core' => true, 'merge_strategy' => 'replace', 'required' => true],
              ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/core/project-context.template.md', 'target' => 'docs/ai/project-context.md', 'core' => true, 'merge_strategy' => 'replace', 'required' => true],
              ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/core/project-context.placeholders.md', 'target' => 'docs/ai/project-context-placeholders.md', 'core' => true, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/core/workflow.template.md', 'target' => 'docs/ai/workflow.md', 'core' => true, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/core/execution-protocol.template.md', 'target' => 'docs/ai/execution-protocol.md', 'core' => true, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/core/ai-file-standards.template.md', 'target' => 'docs/ai/ai-file-standards.md', 'core' => true, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/shared/guardrails/AI-GUARDRAILS.md', 'target' => 'docs/ai/AI-GUARDRAILS.md', 'core' => true, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/core/generated-artifacts.template.md', 'target' => 'docs/ai/generated-artifacts.md', 'core' => true, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/core/POST-INSTALL.template.md', 'target' => 'docs/ai/POST-INSTALL.md', 'core' => true, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/core/project-stack.template.md', 'target' => 'docs/ai/project-stack.md', 'core' => true, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            // P5-a: user-owned project AI notes (template class; installed once, never overwritten).
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/core/project/README.md', 'target' => 'docs/ai/project/README.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/core/project/project-interaction.md', 'target' => 'docs/ai/project/project-interaction.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/core/project/conventions.md', 'target' => 'docs/ai/project/conventions.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/capabilities/project-context', 'target' => 'docs/ai/capabilities/project-context', 'core' => true, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/capabilities/verify-change', 'target' => 'docs/ai/capabilities/verify-change', 'core' => true, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/capabilities/review-diff', 'target' => 'docs/ai/capabilities/review-diff', 'core' => true, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/capabilities/evidence-first-execution', 'target' => 'docs/ai/capabilities/evidence-first-execution', 'core' => true, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/capabilities/clarification-and-handoff', 'target' => 'docs/ai/capabilities/clarification-and-handoff', 'core' => true, 'merge_strategy' => 'replace', 'required' => true],
        ],
        'adapter-copilot' => [
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/core/copilot-instructions.template.md', 'target' => '.github/copilot-instructions.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/github/pull_request_template.md', 'target' => '.github/pull_request_template.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/core/copilot-vscode-settings.template.json', 'target' => '.vscode/settings.json', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/instructions', 'target' => '.github/instructions', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/core/agents', 'target' => '.github/agents', 'install_type' => 'copilot-agents', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/workflows', 'target' => '.github/prompts', 'rename_ext' => '.prompt.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/workflows', 'target' => '.github/skills', 'install_type' => 'skill-dirs', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/instructions/tools.instructions.md', 'target' => '.github/instructions/tools.instructions.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/instructions/execution-protocol.instructions.md', 'target' => '.github/instructions/execution-protocol.instructions.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
        ],
        'adapter-opencode' => [
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/core/opencode.json', 'target' => 'opencode.jsonc', 'core' => false, 'merge_strategy' => 'replace', 'required' => true, 'never_auto_merge' => true],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/core/agents', 'target' => '.opencode/agents', 'install_type' => 'opencode-agents', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/workflows', 'target' => '.opencode/skills', 'install_type' => 'skill-dirs', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/workflows', 'target' => '.opencode/commands', 'install_type' => 'opencode-commands', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/commands', 'target' => '.opencode/commands', 'install_type' => 'opencode-commands', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/skills/ai-search/SKILL.md', 'target' => '.opencode/skills/ai-search/SKILL.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/skills/ai-scripts/SKILL.md', 'target' => '.opencode/skills/ai-scripts/SKILL.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
        ],
        // P0 scope (Claude adapter parity plan, docs/tickets/arch-todo-claude-code-adapter-parity-20260704-120000):
        // renders the same canonical agent source as adapter-copilot/adapter-opencode into
        // .claude/agents/*.md via the Claude sub-agent renderer. CLAUDE.template.md and the
        // .claude/settings.json permission merge are P1 scope, added in a follow-up slice.
        'adapter-claude' => [
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/core/agents', 'target' => '.claude/agents', 'install_type' => 'claude-agents', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
        ],
        'capabilities-extended' => [
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/capabilities/bug-regression', 'target' => 'docs/ai/capabilities/bug-regression', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/capabilities/release-safety', 'target' => 'docs/ai/capabilities/release-safety', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
        ],
        'capabilities-governance' => [
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/capabilities/dependency-upgrade', 'target' => 'docs/ai/capabilities/dependency-upgrade', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/capabilities/mentor-mode', 'target' => 'docs/ai/capabilities/mentor-mode', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
        ],
        'policy-pack' => [
            ['type' => 'file', 'source' => 'docs/ai/command-risk-taxonomy.md', 'target' => 'docs/ai/command-risk-taxonomy.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => true],
            ['type' => 'file', 'source' => 'docs/ai/failure-handling.md', 'target' => 'docs/ai/failure-handling.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => true],
            ['type' => 'file', 'source' => 'schemas/ai/evidence-event.schema.json', 'target' => 'schemas/ai/evidence-event.schema.json', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => true],
        ],
        'scripts-pack' => [
            ['type' => 'file', 'source' => 'scripts/ai/common.sh', 'target' => 'scripts/ai/common.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            // Ship the WHOLE internal module tree as one dir entry. The root scripts are thin
            // loaders that source scripts/ai/internal/<name>/*.sh (Phase 5/7 splits), and
            // internal/search reads internal/config/*.txt; shipping only lib+search left
            // ai-verify, ai-edit, ai-diff-context, pre-tool-use, repomix-context-tree, and
            // repomix-scc-router broken in installed targets (missing sourced modules).
            ['type' => 'dir', 'source' => 'scripts/ai/internal', 'target' => 'scripts/ai/internal', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'dir', 'source' => 'scripts/ai/bin', 'target' => 'scripts/ai/bin', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/ai-search.sh', 'target' => 'scripts/ai/ai-search.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/ai-search-multi.sh', 'target' => 'scripts/ai/ai-search-multi.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/ai-diff-context.sh', 'target' => 'scripts/ai/ai-diff-context.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/ai-verify.sh', 'target' => 'scripts/ai/ai-verify.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/ai-rollback.sh', 'target' => 'scripts/ai/ai-rollback.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/ai-edit.sh', 'target' => 'scripts/ai/ai-edit.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => '.repomixignore', 'target' => '.repomixignore', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            ['type' => 'file', 'source' => 'scripts/ai/pack-context.sh', 'target' => 'scripts/ai/pack-context.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/pre-tool-use.sh', 'target' => 'scripts/ai/pre-tool-use.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/post-tool-use.sh', 'target' => 'scripts/ai/post-tool-use.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/run-repomix-context.sh', 'target' => 'scripts/ai/run-repomix-context.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/run-repomix-file.sh', 'target' => 'scripts/ai/run-repomix-file.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/repomix-context-tree.sh', 'target' => 'scripts/ai/repomix-context-tree.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/repomix-scc-router.sh', 'target' => 'scripts/ai/repomix-scc-router.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/repomix-freshness.sh', 'target' => 'scripts/ai/repomix-freshness.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/repomix-ensure-fresh.sh', 'target' => 'scripts/ai/repomix-ensure-fresh.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/git-forensics.sh', 'target' => 'scripts/ai/git-forensics.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/git-branch-origin.sh', 'target' => 'scripts/ai/git-branch-origin.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/gh-pr-context.sh', 'target' => 'scripts/ai/gh-pr-context.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/preview-file.sh', 'target' => 'scripts/ai/preview-file.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            // tests/scripts/ai/test-preview-file.sh is source-repo-only — not installed to target projects
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/docs/ai/tools/actions/preview-file.md', 'target' => 'docs/ai/tools/actions/preview-file.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            // tool docs referenced by the AGENTS.md "Read This First" pointer and tool-map See-Also closure (Phase 3.1: no longer in opencode.json instructions[])
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/docs/ai/tools/ai-search.md', 'target' => 'docs/ai/tools/ai-search.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/docs/ai/tools/tool-map.md', 'target' => 'docs/ai/tools/tool-map.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/docs/ai/tools/actions/search-evidence.md', 'target' => 'docs/ai/tools/actions/search-evidence.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/docs/ai/tools/actions/use-ai-script.md', 'target' => 'docs/ai/tools/actions/use-ai-script.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/query-usage.sh', 'target' => 'scripts/ai/query-usage.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/fd-files.sh', 'target' => 'scripts/ai/fd-files.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/rg-code.sh', 'target' => 'scripts/ai/rg-code.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/ai-structured.sh', 'target' => 'scripts/ai/ai-structured.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/ai-task.sh', 'target' => 'scripts/ai/ai-task.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/ai-test-select.sh', 'target' => 'scripts/ai/ai-test-select.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/run-repo-tests.sh', 'target' => 'scripts/ai/run-repo-tests.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'scripts/ai/run-test-focused.sh', 'target' => 'scripts/ai/run-test-focused.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'scripts/ai/session-checkpoint.sh', 'target' => 'scripts/ai/session-checkpoint.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/ai-doc-check.sh', 'target' => 'scripts/ai/ai-doc-check.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'scripts/ai/ai-file-freshness.sh', 'target' => 'scripts/ai/ai-file-freshness.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'scripts/ai/ai-install-coverage.sh', 'target' => 'scripts/ai/ai-install-coverage.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'scripts/ai/check-file-refs.sh', 'target' => 'scripts/ai/check-file-refs.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'scripts/ai/repo-stats.sh', 'target' => 'scripts/ai/repo-stats.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'scripts/ai/ship-audit.sh', 'target' => 'scripts/ai/ship-audit.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'scripts/ai/watch-loop.sh', 'target' => 'scripts/ai/watch-loop.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/ai/repo-tool-inventory.sh', 'target' => 'scripts/ai/repo-tool-inventory.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'tools/ai/repo-tool-inventory.php', 'target' => 'tools/ai/repo-tool-inventory.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'scripts/ai/sh-introspect.sh', 'target' => 'scripts/ai/sh-introspect.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'tools/ai/sh-introspect.php', 'target' => 'tools/ai/sh-introspect.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            // sh-introspect.php is a thin loader that require_once's tools/ai/sh-introspect/*.php
            // (numbered modules). Without the module dir, every --help/--introspect call in a
            // target fatals; ship the whole dir.
            ['type' => 'dir', 'source' => 'tools/ai/sh-introspect', 'target' => 'tools/ai/sh-introspect', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            // install-mandatory-tools.sh is intentionally excluded:
            // it installs workstation-level tooling and is meant to be run from this source repo only.
            ['type' => 'file', 'source' => 'docs/ai/repo-required-tools.md', 'target' => 'docs/ai/repo-required-tools.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/mandatory-tools-install.md', 'target' => 'docs/ai/mandatory-tools-install.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/script-registry.md', 'target' => 'docs/ai/script-registry.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'docs/ai/script-registry.json', 'target' => 'docs/ai/script-registry.json', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'docs/ai/script-registry.schema.json', 'target' => 'docs/ai/script-registry.schema.json', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
        ],
        'hooks-pack' => [
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/github/hooks/tool-policy.json', 'target' => '.github/hooks/tool-policy.json', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/github/hooks/tool-guardian.json', 'target' => '.github/hooks/tool-guardian.json', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/github/hooks/scripts/tool-guardian.ps1', 'target' => '.github/hooks/scripts/tool-guardian.ps1', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/github/hooks/scripts/tool-guardian.sh', 'target' => '.github/hooks/scripts/tool-guardian.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/github/hooks/scripts/command-policy.compiled.sh', 'target' => '.github/hooks/scripts/command-policy.compiled.sh', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'scripts/hooks/pre-commit.sh', 'target' => 'scripts/hooks/pre-commit.sh', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => true],
            ['type' => 'file', 'source' => 'scripts/hooks/commit-msg.sh', 'target' => 'scripts/hooks/commit-msg.sh', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => true],
            ['type' => 'file', 'source' => 'docs/ai/hooks.md', 'target' => 'docs/ai/hooks.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => true],
        ],
        'ci-pack' => [
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/github/workflows/validate-ai-surface.yml', 'target' => '.github/workflows/validate-ai-surface.yml', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'docs/ai/validation.md', 'target' => 'docs/ai/validation.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => true],
        ],
        'evidence-pack' => [
            ['type' => 'file', 'source' => 'docs/ai/capabilities/agent-observability-and-evidence/event-schema.md', 'target' => 'docs/ai/capabilities/agent-observability-and-evidence/event-schema.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => true],
            ['type' => 'file', 'source' => 'docs/ai/capabilities/agent-observability-and-evidence/failure-taxonomy.md', 'target' => 'docs/ai/capabilities/agent-observability-and-evidence/failure-taxonomy.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => true],
        ],
        'docs-reference-pack' => [
            ['type' => 'file', 'source' => 'docs/ai/agent-ops.md', 'target' => 'docs/ai/agent-ops.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/AGENTS-MANIFEST.md', 'target' => 'docs/ai/AGENTS-MANIFEST.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/agent-ops-checklist.md', 'target' => 'docs/ai/agent-ops-checklist.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/failure-handling.md', 'target' => 'docs/ai/failure-handling.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/validation.md', 'target' => 'docs/ai/validation.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/context-packing.md', 'target' => 'docs/ai/context-packing.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/hooks.md', 'target' => 'docs/ai/hooks.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/scripts-reference.md', 'target' => 'docs/ai/scripts-reference.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            ['type' => 'file', 'source' => 'docs/ai/toolchain-requirements.md', 'target' => 'docs/ai/toolchain-requirements.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
        ],
        'delivery-pack' => [
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/optional/delivery/README.md', 'target' => 'docs/ai/delivery/README.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/optional/delivery/slice-card.template.md', 'target' => 'docs/ai/delivery/slice-card.template.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
        ],
        'optional-agents-opencode-pack' => [
            // Render through the OpenCode agents renderer (not a raw dir copy) so it honors
            // `hidden: true` and skips internal-only agents (e.g. ui-builder), matching the
            // Copilot optional pack. A raw copy would ship hidden agents that must not leave
            // the kit repo. Pre-existing hidden agents in the target are preserved.
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/optional/agents', 'target' => '.opencode/agents-optional', 'install_type' => 'opencode-agents', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
        ],
        'optional-agents-copilot-pack' => [
            // Render optional agents through the Copilot renderer (VS Code-native frontmatter) and
            // MERGE into .github/agents so they coexist with the base adapter-copilot agents. A raw
            // rename copy would (a) ship invalid OpenCode frontmatter and (b) clobber the base
            // agents via delete-tree. merge_into_existing selects the non-clobbering copy path.
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/optional/agents', 'target' => '.github/agents', 'install_type' => 'copilot-agents', 'merge_into_existing' => true, 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
        ],
        'preview-environments-pack' => [
            ['type' => 'dir', 'source' => 'docs/ai/capabilities/preview-environments', 'target' => 'docs/ai/capabilities/preview-environments', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
        ],
        'evaluation-pack' => [
            ['type' => 'dir', 'source' => 'docs/ai/capabilities/evaluation-and-regression', 'target' => 'docs/ai/capabilities/evaluation-and-regression', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
        ],
        'service-boundary-pack' => [
            ['type' => 'dir', 'source' => 'docs/ai/capabilities/service-boundary-patterns', 'target' => 'docs/ai/capabilities/service-boundary-patterns', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
        ],
        'mcp-boundaries-pack' => [
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/docs/operations/MCP-BOUNDARIES.md', 'target' => 'docs/ai/MCP-BOUNDARIES.md', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
        ],
        'advisor-pack' => [
            ['type' => 'dir', 'source' => 'tools/ai/advisor', 'target' => 'tools/ai/advisor', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            ['type' => 'file', 'source' => 'schemas/ai/project-signals.schema.json', 'target' => 'schemas/ai/project-signals.schema.json', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            ['type' => 'file', 'source' => 'schemas/ai/project-scorecard.schema.json', 'target' => 'schemas/ai/project-scorecard.schema.json', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            ['type' => 'file', 'source' => 'schemas/ai/advisor-recommendation.schema.json', 'target' => 'schemas/ai/advisor-recommendation.schema.json', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
        ],
        'target-tools-pack' => [
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/PLACEHOLDERS.md', 'target' => 'PLACEHOLDERS.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/placeholders.json', 'target' => '.ai/placeholders.json', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            // Kit package descriptors are namespaced under the byte-protected .ai/ dir so they never
            // collide with a consumer project's own root manifest.json/catalog.json/package-lock.
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/manifest.json', 'target' => '.ai/kit-manifest.json', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/manifest.yml', 'target' => '.ai/kit-manifest.yml', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/package-lock.ai.json', 'target' => '.ai/package-lock.ai.json', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/catalog.json', 'target' => '.ai/catalog.json', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/docs', 'target' => 'docs/ai/package', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/policies', 'target' => 'policies', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            // Source-repo-only interactive shellcheck batches are intentionally
            // excluded from installed targets; target verification uses
            // scripts/ai/ai-verify.sh instead.
            ...$targetToolInstallerFiles,
            ['type' => 'dir', 'source' => 'tools/ai/commands', 'target' => 'tools/ai/commands', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/ai.php', 'target' => 'tools/ai/ai.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/ai_catalog_lib.php', 'target' => 'tools/ai/ai_catalog_lib.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/ai_output_lib.php', 'target' => 'tools/ai/ai_output_lib.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/build-context-pack.php', 'target' => 'tools/ai/build-context-pack.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/full-install-validation.php', 'target' => 'tools/ai/full-install-validation.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/generate-ai-catalog.php', 'target' => 'tools/ai/generate-ai-catalog.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/generate-ai-file-standards.php', 'target' => 'tools/ai/generate-ai-file-standards.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/generate-agent-snippets.php', 'target' => 'tools/ai/generate-agent-snippets.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'tools/ai/generate-repo-structure.php', 'target' => 'tools/ai/generate-repo-structure.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/install-ai-kit.php', 'target' => 'tools/ai/install-ai-kit.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/maintenance-mode.php', 'target' => 'tools/ai/maintenance-mode.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/render-agent-permissions.php', 'target' => 'tools/ai/render-agent-permissions.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/secret-scan.php', 'target' => 'tools/ai/secret-scan.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/suggest-verification.php', 'target' => 'tools/ai/suggest-verification.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/validate-adapter-drift.php', 'target' => 'tools/ai/validate-adapter-drift.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/validate-ai-catalog.php', 'target' => 'tools/ai/validate-ai-catalog.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/validate-ai-config.php', 'target' => 'tools/ai/validate-ai-config.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/validate-command-policy.php', 'target' => 'tools/ai/validate-command-policy.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/validate-context-budgets.php', 'target' => 'tools/ai/validate-context-budgets.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/validate-generated-artifacts.php', 'target' => 'tools/ai/validate-generated-artifacts.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/validate-install-surface.php', 'target' => 'tools/ai/validate-install-surface.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/validate-stub-surfaces.php', 'target' => 'tools/ai/validate-stub-surfaces.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'tools/ai/validate-catalog-drift.php', 'target' => 'tools/ai/validate-catalog-drift.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'tools/ai/validate-agent-spec.php', 'target' => 'tools/ai/validate-agent-spec.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'tools/ai/validate-schemas.php', 'target' => 'tools/ai/validate-schemas.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'tools/ai/validate-mentor-parity.php', 'target' => 'tools/ai/validate-mentor-parity.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'tools/ai/validate-agent-assessment.php', 'target' => 'tools/ai/validate-agent-assessment.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'tools/ai/validate-agent-assessment-values.php', 'target' => 'tools/ai/validate-agent-assessment-values.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'tools/ai/verify-install-placeholders.php', 'target' => 'tools/ai/verify-install-placeholders.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/validate-placeholders.php', 'target' => 'tools/ai/validate-placeholders.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/verify-full-install.php', 'target' => 'tools/ai/verify-full-install.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'schemas/ai/ai-catalog.schema.json', 'target' => 'schemas/ai/ai-catalog.schema.json', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => true],
            ['type' => 'file', 'source' => 'schemas/ai/ai-command-policy.schema.json', 'target' => 'schemas/ai/ai-command-policy.schema.json', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => true],
            ['type' => 'file', 'source' => 'schemas/ai/ai-file-standards.schema.json', 'target' => 'schemas/ai/ai-file-standards.schema.json', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => true],
            ['type' => 'file', 'source' => 'schemas/ai/agent-spec.schema.json', 'target' => 'schemas/ai/agent-spec.schema.json', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            ['type' => 'file', 'source' => 'schemas/ai/ai-handoff.schema.json', 'target' => 'schemas/ai/ai-handoff.schema.json', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            ['type' => 'file', 'source' => 'schemas/ai/ai-universal-rules-manifest.schema.json', 'target' => 'schemas/ai/ai-universal-rules-manifest.schema.json', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => true],
            ['type' => 'file', 'source' => 'schemas/ai/generated-artifacts.schema.json', 'target' => 'schemas/ai/generated-artifacts.schema.json', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => true],
            ['type' => 'file', 'source' => 'schemas/ai/project-placeholders.schema.json', 'target' => 'schemas/ai/project-placeholders.schema.json', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => true],
            ['type' => 'file', 'source' => 'schemas/ai/verification-matrix.schema.json', 'target' => 'schemas/ai/verification-matrix.schema.json', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => true],
            ['type' => 'file', 'source' => 'schemas/ai/agent-assessment.schema.json', 'target' => 'schemas/ai/agent-assessment.schema.json', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            ['type' => 'file', 'source' => 'schemas/ai/agent-assessment-values.schema.json', 'target' => 'schemas/ai/agent-assessment-values.schema.json', 'core' => false, 'merge_strategy' => 'skip-if-exists', 'required' => false],
            // NOTE: docs/ai/agent-scores.yaml (the D3a VALUES SOURCE) is intentionally NOT
            // shipped. Its keys must match the agent templates present in the target, which
            // varies by profile/runtime; shipping the source-repo file (24 keys) into a
            // core-only target (13 templates) makes its own validator report stale keys. The
            // schema + validator ship so a target can author its own agent-scores.yaml; the
            // ai-doc-check wiring is a no-op until that file exists locally.
        ],
        'shared-templates-pack' => [
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/shared/project-interaction.md', 'target' => 'docs/ai/shared/project-interaction.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/shared/approvals', 'target' => 'docs/ai/shared/approvals', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/shared/verification', 'target' => 'docs/ai/shared/verification', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates/snippets', 'target' => 'docs/ai/snippets', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
        ],
        // Ship the source package descriptor + generated catalog so installed
        // targets can run generate-ai-catalog/validate-generated-artifacts and
        // package-verify without re-downloading the source repo.
        'package-source-pack' => [
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/manifest.json', 'target' => '.ai/kit-manifest.json', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/catalog.json', 'target' => '.ai/catalog.json', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/manifest.yml', 'target' => '.ai/kit-manifest.yml', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/package-lock.ai.json', 'target' => '.ai/package-lock.ai.json', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/PLACEHOLDERS.md', 'target' => 'PLACEHOLDERS.md', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/placeholders.json', 'target' => '.ai/placeholders.json', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/docs', 'target' => 'docs/ai/package', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/policies', 'target' => 'policies', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
        ],
        // Kit-authoring / re-distribution assets. Not needed by consumer
        // projects; opt in via --with kit-authoring-pack or --all-features.
        'kit-authoring-pack' => [
            ['type' => 'dir', 'source' => 'packages/ai-universal-rules/templates', 'target' => 'packages/ai-universal-rules/templates', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'tools/ai/export-ai-universal-rules.php', 'target' => 'tools/ai/export-ai-universal-rules.php', 'core' => false, 'merge_strategy' => 'replace', 'required' => true],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/github/workflows/test-external-install.yml', 'target' => '.github/workflows/test-external-install.yml', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
            ['type' => 'file', 'source' => 'packages/ai-universal-rules/templates/github/workflows/export-ai-universal-rules-preview.yml', 'target' => '.github/workflows/export-ai-universal-rules-preview.yml', 'core' => false, 'merge_strategy' => 'replace', 'required' => false],
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
        $packs = array_values(array_filter($packs, static fn(string $p): bool => $p !== 'adapter-opencode'));
        if (in_array($profile, ['copilot', 'dual', 'accelerated', 'full-governance'], true) && !in_array('adapter-copilot', $packs, true)) {
            $packs[] = 'adapter-copilot';
        }
    } elseif ($runtime === 'opencode') {
        $packs = array_values(array_filter($packs, static fn(string $p): bool => $p !== 'adapter-copilot'));
        if (in_array($profile, ['opencode', 'dual', 'accelerated', 'full-governance'], true) && !in_array('adapter-opencode', $packs, true)) {
            $packs[] = 'adapter-opencode';
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
    $agentPacks = ['adapter-copilot', 'adapter-opencode', 'optional-agents-opencode-pack', 'optional-agents-copilot-pack'];
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
