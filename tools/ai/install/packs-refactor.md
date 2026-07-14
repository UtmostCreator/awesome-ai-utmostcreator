## Recommended refactor

Keep the installer’s returned structure unchanged, but define entries through a small normalizer:

- A string means `source === target`.
- Common defaults are defined once.
- Pack-level defaults cover `required`, `core`, `type`, and `merge_strategy`.
- Exceptional entries use associative overrides.
- The large registry is split into domain-specific functions.
- Positional tuples are avoided because boolean-heavy tuples are difficult to review.

### 1. Reusable entry normalizer

```php
<?php

declare(strict_types=1);

/**
 * @phpstan-type InstallerEntry array{
 *     type: 'file'|'dir',
 *     source: non-empty-string,
 *     target: non-empty-string,
 *     core: bool,
 *     merge_strategy: 'replace'|'skip-if-exists',
 *     required: bool,
 *     install_type?: non-empty-string,
 *     rename_ext?: non-empty-string,
 *     never_auto_merge?: bool,
 *     merge_into_existing?: bool
 * }
 *
 * @phpstan-type InstallerEntrySpec non-empty-string|array{
 *     type?: 'file'|'dir',
 *     source: non-empty-string,
 *     target?: non-empty-string,
 *     core?: bool,
 *     merge_strategy?: 'replace'|'skip-if-exists',
 *     required?: bool,
 *     install_type?: non-empty-string,
 *     rename_ext?: non-empty-string,
 *     never_auto_merge?: bool,
 *     merge_into_existing?: bool
 * }
 */

/**
 * @param InstallerEntrySpec $spec
 * @param array<string, mixed> $defaults
 *
 * @return InstallerEntry
 */
function aiInstallerRegistryEntry(
    string|array $spec,
    array $defaults = [],
): array {
    $overrides = is_string($spec)
        ? ['source' => $spec]
        : $spec;

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
        throw new InvalidArgumentException(
            'Installer registry entry requires a non-empty source.'
        );
    }

    $entry['target'] ??= $source;

    aiInstallerValidateRegistryEntry($entry);

    /** @var InstallerEntry $entry */
    return $entry;
}

/**
 * @param list<InstallerEntrySpec> $specs
 * @param array<string, mixed> $defaults
 *
 * @return list<InstallerEntry>
 */
function aiInstallerRegistryEntries(
    array $specs,
    array $defaults = [],
): array {
    return array_map(
        static fn(string|array $spec): array => aiInstallerRegistryEntry(
            $spec,
            $defaults,
        ),
        $specs,
    );
}

/**
 * @param array<string, mixed> $entry
 */
function aiInstallerValidateRegistryEntry(array $entry): void
{
    if (!in_array($entry['type'] ?? null, ['file', 'dir'], true)) {
        throw new InvalidArgumentException(
            sprintf(
                'Invalid installer entry type for "%s".',
                (string) ($entry['source'] ?? '<unknown>'),
            )
        );
    }

    if (
        !in_array(
            $entry['merge_strategy'] ?? null,
            ['replace', 'skip-if-exists'],
            true,
        )
    ) {
        throw new InvalidArgumentException(
            sprintf(
                'Invalid merge strategy for "%s".',
                (string) ($entry['source'] ?? '<unknown>'),
            )
        );
    }

    foreach (['source', 'target'] as $key) {
        if (!is_string($entry[$key] ?? null) || $entry[$key] === '') {
            throw new InvalidArgumentException(
                sprintf('Installer entry requires a non-empty %s.', $key)
            );
        }
    }

    foreach (['core', 'required'] as $key) {
        if (!is_bool($entry[$key] ?? null)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Installer entry "%s" must contain a boolean %s.',
                    $entry['source'],
                    $key,
                )
            );
        }
    }
}
```

## 2. Make the main method an aggregator

```php
/**
 * @return array<string, list<array<string, mixed>>>
 */
function aiInstallerPackRegistry(): array
{
    return aiInstallerMergePackRegistries(
        aiInstallerDocumentationPackRegistry(),
        aiInstallerCapabilityPackRegistry(),
        aiInstallerBasePackRegistry(),
        aiInstallerAdapterPackRegistry(),
        aiInstallerRuntimePackRegistry(),
        aiInstallerOptionalPackRegistry(),
        aiInstallerDistributionPackRegistry(),
    );
}

/**
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
                throw new LogicException(
                    sprintf('Duplicate installer pack "%s".', $packName)
                );
            }

            $result[$packName] = $entries;
        }
    }

    return $result;
}
```

Unlike `array_merge()` or `array_replace()`, this fails when two registry sections accidentally define the same pack.

## 3. Documentation packs

The three document closures become unnecessary.

```php
/**
 * @return array<string, list<array<string, mixed>>>
 */
function aiInstallerDocumentationPackRegistry(): array
{
    return [
        'setup-docs' => [
            ...aiInstallerRegistryEntries([
                [
                    'source' => 'packages/ai-universal-rules/PLACEHOLDERS.md',
                    'target' => 'PLACEHOLDERS.md',
                ],
                'docs/ai/agents.md',
                'docs/ai/agent-script-access.md',
                'docs/ai/adapter-contract.md',
                'docs/ai/approval-boundaries.md',
                'docs/ai/architecture-locks.md',
                'docs/ai/handoff-contract.md',
                'docs/ai/integration-matrix.md',
                'docs/ai/session-reentry.md',
                'docs/ai/source-of-truth.md',
                'docs/ai/tool-policy.md',
                'docs/ai/verification-matrix.md',
                'docs/ai/index.md',
                'tools/ai/verify-install-placeholders.php',
            ]),
            ...aiInstallerRegistryEntries([
                [
                    'source' => 'packages/ai-universal-rules/placeholders.json',
                    'target' => '.ai/placeholders.json',
                ],
                'docs/ai/shipped-surface-inventory.md',
                'docs/ai/schema-ownership.md',
                'docs/ai/context-economy.md',
                'docs/ai/opencode-models.md',
                'docs/ai/copilot-getting-started.md',
                'docs/ai/copilot-tooling.md',
                'docs/ai/copilot-cli-repo-integration.md',
                'docs/ai/external-repo-install.md',
                'docs/ai/maintenance-mode.md',
                'docs/ai/available-packs.md',
                'docs/ai/command-policy.md',
                'docs/ai/command-policy.tiers.yaml',
                'docs/ai/security.md',
                'docs/ai/catalog.md',
                'llms.txt',
                'docs/ai/repo-documentation-generation.md',
                'docs/ai/capabilities/README.md',
            ], [
                'required' => false,
            ]),
            aiInstallerRegistryEntry('docs/ai/ownership.md', [
                'merge_strategy' => 'skip-if-exists',
                'required' => false,
            ]),
        ],

        'policy-pack' => [
            ...aiInstallerRegistryEntries([
                'docs/ai/command-risk-taxonomy.md',
                'docs/ai/failure-handling.md',
                'schemas/ai/evidence-event.schema.json',
            ], [
                'merge_strategy' => 'skip-if-exists',
            ]),
        ],

        'hooks-pack' => [
            ...aiInstallerRegistryEntries([
                'packages/ai-universal-rules/templates/github/hooks/tool-policy.json',
            ]),
            ...aiInstallerRegistryEntries([
                'packages/ai-universal-rules/templates/github/hooks/tool-guardian.json',
                'packages/ai-universal-rules/templates/github/hooks/scripts/tool-guardian.ps1',
                'packages/ai-universal-rules/templates/github/hooks/scripts/tool-guardian.sh',
                'packages/ai-universal-rules/templates/github/hooks/scripts/command-policy.compiled.sh',
            ], [
                'required' => false,
            ]),
            ...aiInstallerRegistryEntries([
                'scripts/hooks/pre-commit.sh',
                'scripts/hooks/commit-msg.sh',
                'docs/ai/hooks.md',
            ], [
                'merge_strategy' => 'skip-if-exists',
            ]),
        ],

        'ci-pack' => [
            aiInstallerRegistryEntry(
                'packages/ai-universal-rules/templates/github/workflows/validate-ai-surface.yml'
            ),
            aiInstallerRegistryEntry('docs/ai/validation.md', [
                'merge_strategy' => 'skip-if-exists',
            ]),
        ],

        'evidence-pack' => aiInstallerRegistryEntries([
            'docs/ai/capabilities/agent-observability-and-evidence/event-schema.md',
            'docs/ai/capabilities/agent-observability-and-evidence/failure-taxonomy.md',
        ], [
            'merge_strategy' => 'skip-if-exists',
        ]),

        'docs-reference-pack' => aiInstallerRegistryEntries([
            'docs/ai/agent-ops.md',
            'docs/ai/AGENTS-MANIFEST.md',
            'docs/ai/agent-ops-checklist.md',
            'docs/ai/failure-handling.md',
            'docs/ai/validation.md',
            'docs/ai/context-packing.md',
            'docs/ai/hooks.md',
            'docs/ai/scripts-reference.md',
            'docs/ai/toolchain-requirements.md',
            'docs/ai/recommended-optional-tools.md',
        ], [
            'merge_strategy' => 'skip-if-exists',
            'required' => false,
        ]),
    ];
}
```

## 4. Capabilities

```php
/**
 * @return array<string, list<array<string, mixed>>>
 */
function aiInstallerCapabilityPackRegistry(): array
{
    $capabilityDefaults = [
        'type' => 'dir',
    ];

    return [
        'capabilities-core' => [
            ...aiInstallerRegistryEntries([
                'docs/ai/capabilities/agent-observability-and-evidence',
            ], $capabilityDefaults),
            ...aiInstallerRegistryEntries([
                'docs/ai/capabilities/authorization-and-tool-governance',
                'docs/ai/capabilities/config-change-safety',
                'docs/ai/capabilities/docs-sync',
                'docs/ai/capabilities/adapter-drift',
            ], [
                ...$capabilityDefaults,
                'required' => false,
            ]),
        ],

        'capabilities-extended' => aiInstallerRegistryEntries([
            [
                'source' => 'packages/ai-universal-rules/templates/capabilities/bug-regression',
                'target' => 'docs/ai/capabilities/bug-regression',
            ],
            [
                'source' => 'packages/ai-universal-rules/templates/capabilities/release-safety',
                'target' => 'docs/ai/capabilities/release-safety',
            ],
        ], $capabilityDefaults),

        'capabilities-governance' => aiInstallerRegistryEntries([
            [
                'source' => 'packages/ai-universal-rules/templates/capabilities/dependency-upgrade',
                'target' => 'docs/ai/capabilities/dependency-upgrade',
            ],
            [
                'source' => 'packages/ai-universal-rules/templates/capabilities/mentor-mode',
                'target' => 'docs/ai/capabilities/mentor-mode',
            ],
        ], $capabilityDefaults),

        'preview-environments-pack' => aiInstallerRegistryEntries([
            'docs/ai/capabilities/preview-environments',
        ], [
            ...$capabilityDefaults,
            'merge_strategy' => 'skip-if-exists',
            'required' => false,
        ]),

        'evaluation-pack' => aiInstallerRegistryEntries([
            'docs/ai/capabilities/evaluation-and-regression',
        ], [
            ...$capabilityDefaults,
            'merge_strategy' => 'skip-if-exists',
            'required' => false,
        ]),

        'service-boundary-pack' => aiInstallerRegistryEntries([
            'docs/ai/capabilities/service-boundary-patterns',
        ], [
            ...$capabilityDefaults,
            'merge_strategy' => 'skip-if-exists',
            'required' => false,
        ]),
    ];
}
```

## 5. Base pack

```php
/**
 * @return array<string, list<array<string, mixed>>>
 */
function aiInstallerBasePackRegistry(): array
{
    return [
        'base' => [
            ...aiInstallerRegistryEntries([
                [
                    'source' => 'packages/ai-universal-rules/templates/core/AGENTS.template.md',
                    'target' => 'AGENTS.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/core/project-context.template.md',
                    'target' => 'docs/ai/project-context.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/core/workflow.template.md',
                    'target' => 'docs/ai/workflow.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/core/execution-protocol.template.md',
                    'target' => 'docs/ai/execution-protocol.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/core/ai-file-standards.template.md',
                    'target' => 'docs/ai/ai-file-standards.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/shared/guardrails/AI-GUARDRAILS.md',
                    'target' => 'docs/ai/AI-GUARDRAILS.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/core/generated-artifacts.template.md',
                    'target' => 'docs/ai/generated-artifacts.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/core/POST-INSTALL.template.md',
                    'target' => 'docs/ai/POST-INSTALL.md',
                ],
            ], [
                'core' => true,
            ]),

            ...aiInstallerRegistryEntries([
                [
                    'source' => 'packages/ai-universal-rules/templates/core/project-context.placeholders.md',
                    'target' => 'docs/ai/project-context-placeholders.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/core/project-stack.template.md',
                    'target' => 'docs/ai/project-stack.md',
                    'merge_strategy' => 'skip-if-exists',
                ],
            ], [
                'core' => true,
                'required' => false,
            ]),

            ...aiInstallerRegistryEntries([
                [
                    'source' => 'packages/ai-universal-rules/templates/core/project/README.md',
                    'target' => 'docs/ai/project/README.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/core/project/project-interaction.md',
                    'target' => 'docs/ai/project/project-interaction.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/core/project/conventions.md',
                    'target' => 'docs/ai/project/conventions.md',
                ],
            ], [
                'merge_strategy' => 'skip-if-exists',
                'required' => false,
            ]),

            ...aiInstallerRegistryEntries([
                [
                    'source' => 'packages/ai-universal-rules/templates/capabilities/project-context',
                    'target' => 'docs/ai/capabilities/project-context',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/capabilities/verify-change',
                    'target' => 'docs/ai/capabilities/verify-change',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/capabilities/review-diff',
                    'target' => 'docs/ai/capabilities/review-diff',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/capabilities/evidence-first-execution',
                    'target' => 'docs/ai/capabilities/evidence-first-execution',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/capabilities/clarification-and-handoff',
                    'target' => 'docs/ai/capabilities/clarification-and-handoff',
                ],
                'handoff/generated',
            ], [
                'type' => 'dir',
                'core' => true,
            ]),

            ...aiInstallerRegistryEntries([
                'handoff/dispatch.py',
                'handoff/agent-handoff.yaml',
                'handoff/gen_handoff.py',
                'handoff/gen_handoff.sh',
                'handoff/AGENT-INCLUDE.md',
            ], [
                'core' => true,
            ]),
        ],
    ];
}
```

## 6. Adapter packs

Special options remain visible, but repeated defaults disappear.

```php
/**
 * @return array<string, list<array<string, mixed>>>
 */
function aiInstallerAdapterPackRegistry(): array
{
    return [
        'adapter-copilot' => [
            ...aiInstallerRegistryEntries([
                [
                    'source' => 'packages/ai-universal-rules/templates/core/copilot-instructions.template.md',
                    'target' => '.github/copilot-instructions.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/github/pull_request_template.md',
                    'target' => '.github/pull_request_template.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/instructions/tools.instructions.md',
                    'target' => '.github/instructions/tools.instructions.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/instructions/execution-protocol.instructions.md',
                    'target' => '.github/instructions/execution-protocol.instructions.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/skills/ai-search/SKILL.md',
                    'target' => '.github/skills/ai-search/SKILL.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/skills/ai-scripts/SKILL.md',
                    'target' => '.github/skills/ai-scripts/SKILL.md',
                ],
            ]),

            aiInstallerRegistryEntry([
                'source' => 'packages/ai-universal-rules/templates/core/copilot-vscode-settings.template.json',
                'target' => '.vscode/settings.json',
            ], [
                'merge_strategy' => 'skip-if-exists',
                'required' => false,
            ]),

            ...aiInstallerRegistryEntries([
                [
                    'source' => 'packages/ai-universal-rules/templates/instructions',
                    'target' => '.github/instructions',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/core/agents',
                    'target' => '.github/agents',
                    'install_type' => 'copilot-agents',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/workflows',
                    'target' => '.github/prompts',
                    'rename_ext' => '.prompt.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/workflows',
                    'target' => '.github/skills',
                    'install_type' => 'skill-dirs',
                ],
            ], [
                'type' => 'dir',
            ]),
        ],

        'adapter-opencode' => [
            aiInstallerRegistryEntry([
                'source' => 'packages/ai-universal-rules/templates/core/opencode.json',
                'target' => 'opencode.jsonc',
                'never_auto_merge' => true,
            ]),

            ...aiInstallerRegistryEntries([
                [
                    'source' => 'packages/ai-universal-rules/templates/core/agents',
                    'target' => '.opencode/agents',
                    'install_type' => 'opencode-agents',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/workflows',
                    'target' => '.opencode/skills',
                    'install_type' => 'skill-dirs',
                ],

                // Order is significant. The first entry clears the destination.
                [
                    'source' => 'packages/ai-universal-rules/templates/workflows',
                    'target' => '.opencode/commands',
                    'install_type' => 'opencode-commands',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/commands',
                    'target' => '.opencode/commands',
                    'install_type' => 'opencode-commands',
                ],
            ], [
                'type' => 'dir',
            ]),

            ...aiInstallerRegistryEntries([
                [
                    'source' => 'packages/ai-universal-rules/templates/skills/ai-search/SKILL.md',
                    'target' => '.opencode/skills/ai-search/SKILL.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/skills/ai-scripts/SKILL.md',
                    'target' => '.opencode/skills/ai-scripts/SKILL.md',
                ],
            ]),
        ],

        'adapter-claude' => [
            aiInstallerRegistryEntry([
                'type' => 'dir',
                'source' => 'packages/ai-universal-rules/templates/core/agents',
                'target' => '.claude/agents',
                'install_type' => 'claude-agents',
            ]),
            aiInstallerRegistryEntry([
                'source' => 'packages/ai-universal-rules/templates/core/CLAUDE.template.md',
                'target' => 'CLAUDE.md',
            ]),
            aiInstallerRegistryEntry([
                'source' => 'packages/ai-universal-rules/templates/claude/settings.json',
                'target' => '.claude/settings.json',
                'install_type' => 'claude-settings-merge',
            ]),
            aiInstallerRegistryEntry([
                'type' => 'dir',
                'source' => 'packages/ai-universal-rules/templates/workflows',
                'target' => '.claude/skills',
                'install_type' => 'skill-dirs',
            ]),
            ...aiInstallerRegistryEntries([
                [
                    'source' => 'packages/ai-universal-rules/templates/skills/ai-search/SKILL.md',
                    'target' => '.claude/skills/ai-search/SKILL.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/skills/ai-scripts/SKILL.md',
                    'target' => '.claude/skills/ai-scripts/SKILL.md',
                ],
            ]),
            aiInstallerRegistryEntry([
                'type' => 'dir',
                'source' => 'packages/ai-universal-rules/templates/commands',
                'target' => '.claude/commands',
                'install_type' => 'opencode-commands',
            ]),
        ],
    ];
}
```

## 7. Runtime and scripts packs

Large homogeneous lists receive defaults once.

```php
/**
 * @return array<string, list<array<string, mixed>>>
 */
function aiInstallerRuntimePackRegistry(): array
{
    return [
        'scripts-pack' => [
            aiInstallerRegistryEntry('scripts/ai/common.sh'),

            ...aiInstallerRegistryEntries([
                'scripts/ai/internal',
                'scripts/ai/bin',
            ], [
                'type' => 'dir',
            ]),

            ...aiInstallerRegistryEntries([
                'scripts/ai/ai-search.sh',
                'scripts/ai/ai-search-multi.sh',
                'scripts/ai/ai-diff-context.sh',
                'scripts/ai/ai-verify.sh',
                'scripts/ai/ai-verify-html.sh',
                'scripts/ai/ai-verify-js.sh',
                'scripts/ai/ai-verify-php.sh',
                'scripts/ai/ai-verify-ts.sh',
                'scripts/ai/ai-verify-vue.sh',
                'scripts/ai/ai-rollback.sh',
                'scripts/ai/ai-edit.sh',
                'scripts/ai/pack-context.sh',
                'scripts/ai/pre-tool-use.sh',
                'scripts/ai/post-tool-use.sh',
                'scripts/ai/run-repomix-context.sh',
                'scripts/ai/run-repomix-file.sh',
                'scripts/ai/repomix-context-tree.sh',
                'scripts/ai/repomix-scc-router.sh',
                'scripts/ai/repomix-freshness.sh',
                'scripts/ai/repomix-ensure-fresh.sh',
                'scripts/ai/git-forensics.sh',
                'scripts/ai/git-branch-origin.sh',
                'scripts/ai/gh-pr-context.sh',
                'scripts/ai/preview-file.sh',
                'scripts/ai/query-usage.sh',
                'scripts/ai/fd-files.sh',
                'scripts/ai/rg-code.sh',
                'scripts/ai/ai-structured.sh',
                'scripts/ai/ai-task.sh',
                'scripts/ai/ai-test-select.sh',
                'scripts/ai/session-checkpoint.sh',
                'scripts/ai/watch-loop.sh',
                'docs/ai/script-registry.md',
                'docs/ai/script-registry.json',
                'docs/ai/script-registry.schema.json',
            ]),

            aiInstallerRegistryEntry('.repomixignore', [
                'merge_strategy' => 'skip-if-exists',
                'required' => false,
            ]),

            ...aiInstallerRegistryEntries([
                'scripts/ai/run-repo-tests.sh',
                'scripts/ai/run-test-focused.sh',
                'scripts/ai/ai-doc-check.sh',
                'scripts/ai/ai-file-freshness.sh',
                'scripts/ai/ai-install-coverage.sh',
                'scripts/ai/check-file-refs.sh',
                'scripts/ai/repo-stats.sh',
                'scripts/ai/ship-audit.sh',
                'scripts/ai/repo-tool-inventory.sh',
                'tools/ai/repo-tool-inventory.php',
                'scripts/ai/sh-introspect.sh',
                'tools/ai/sh-introspect.php',
                'docs/ai/repo-required-tools.md',
                'docs/ai/mandatory-tools-install.md',
            ], [
                'required' => false,
            ]),

            aiInstallerRegistryEntry('tools/ai/sh-introspect', [
                'type' => 'dir',
                'required' => false,
            ]),

            ...aiInstallerRegistryEntries([
                [
                    'source' => 'packages/ai-universal-rules/templates/docs/ai/tools/actions/preview-file.md',
                    'target' => 'docs/ai/tools/actions/preview-file.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/docs/ai/tools/ai-search.md',
                    'target' => 'docs/ai/tools/ai-search.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/docs/ai/tools/tool-map.md',
                    'target' => 'docs/ai/tools/tool-map.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/docs/ai/tools/actions/search-evidence.md',
                    'target' => 'docs/ai/tools/actions/search-evidence.md',
                ],
                [
                    'source' => 'packages/ai-universal-rules/templates/docs/ai/tools/actions/use-ai-script.md',
                    'target' => 'docs/ai/tools/actions/use-ai-script.md',
                ],
            ]),
        ],

        'advisor-pack' => [
            aiInstallerRegistryEntry('tools/ai/advisor', [
                'type' => 'dir',
                'merge_strategy' => 'skip-if-exists',
                'required' => false,
            ]),
            ...aiInstallerRegistryEntries([
                'tools/ai/command-exists.php',
                'schemas/ai/project-signals.schema.json',
                'schemas/ai/project-scorecard.schema.json',
                'schemas/ai/advisor-recommendation.schema.json',
            ], [
                'merge_strategy' => 'skip-if-exists',
                'required' => false,
            ]),
        ],
    ];
}
```

## 8. Optional agent packs

```php
/**
 * @return array<string, list<array<string, mixed>>>
 */
function aiInstallerOptionalPackRegistry(): array
{
    return [
        'optional-agents-opencode-pack' => [
            aiInstallerRegistryEntry([
                'type' => 'dir',
                'source' => 'packages/ai-universal-rules/templates/optional/agents',
                'target' => '.opencode/agents-optional',
                'install_type' => 'opencode-agents',
                'required' => false,
            ]),
        ],

        'optional-agents-copilot-pack' => [
            aiInstallerRegistryEntry([
                'type' => 'dir',
                'source' => 'packages/ai-universal-rules/templates/optional/agents',
                'target' => '.github/agents',
                'install_type' => 'copilot-agents',
                'merge_into_existing' => true,
                'merge_strategy' => 'skip-if-exists',
                'required' => false,
            ]),
        ],

        'optional-agents-claude-pack' => [
            aiInstallerRegistryEntry([
                'type' => 'dir',
                'source' => 'packages/ai-universal-rules/templates/optional/agents',
                'target' => '.claude/agents',
                'install_type' => 'claude-agents',
                'merge_into_existing' => true,
                'merge_strategy' => 'skip-if-exists',
                'required' => false,
            ]),
        ],

        'delivery-pack' => aiInstallerRegistryEntries([
            [
                'source' => 'packages/ai-universal-rules/templates/optional/delivery/README.md',
                'target' => 'docs/ai/delivery/README.md',
            ],
            [
                'source' => 'packages/ai-universal-rules/templates/optional/delivery/slice-card.template.md',
                'target' => 'docs/ai/delivery/slice-card.template.md',
            ],
        ], [
            'merge_strategy' => 'skip-if-exists',
            'required' => false,
        ]),

        'mcp-boundaries-pack' => aiInstallerRegistryEntries([
            [
                'source' => 'packages/ai-universal-rules/docs/operations/MCP-BOUNDARIES.md',
                'target' => 'docs/ai/MCP-BOUNDARIES.md',
            ],
        ], [
            'merge_strategy' => 'skip-if-exists',
            'required' => false,
        ]),
    ];
}
```

## 9. Deduplicate repeated package descriptors

`target-tools-pack` and `package-source-pack` currently repeat the same package descriptor entries. Define them once:

```php
/**
 * @return list<array<string, mixed>>
 */
function aiInstallerPackageSourceEntries(): array
{
    return [
        ...aiInstallerRegistryEntries([
            [
                'source' => 'packages/ai-universal-rules/manifest.json',
                'target' => '.ai/kit-manifest.json',
            ],
            [
                'source' => 'packages/ai-universal-rules/PLACEHOLDERS.md',
                'target' => 'PLACEHOLDERS.md',
            ],
        ]),
        ...aiInstallerRegistryEntries([
            [
                'source' => 'packages/ai-universal-rules/manifest.yml',
                'target' => '.ai/kit-manifest.yml',
            ],
            [
                'source' => 'packages/ai-universal-rules/package-lock.ai.json',
                'target' => '.ai/package-lock.ai.json',
            ],
            [
                'source' => 'packages/ai-universal-rules/placeholders.json',
                'target' => '.ai/placeholders.json',
            ],
        ], [
            'required' => false,
        ]),
        aiInstallerRegistryEntry([
            'source' => 'packages/ai-universal-rules/catalog.json',
            'target' => '.ai/catalog.json',
        ]),
        ...aiInstallerRegistryEntries([
            [
                'type' => 'dir',
                'source' => 'packages/ai-universal-rules/docs',
                'target' => 'docs/ai/package',
            ],
            [
                'type' => 'dir',
                'source' => 'packages/ai-universal-rules/policies',
                'target' => 'policies',
            ],
        ], [
            'required' => false,
        ]),
    ];
}
```

Then reuse it:

```php
'package-source-pack' => [
    ...aiInstallerPackageSourceEntries(),
],

'target-tools-pack' => [
    ...aiInstallerPackageSourceEntries(),
    ...aiInstallerTargetToolEntries(),
],
```

This removes actual semantic duplication, not merely repeated syntax.

## 10. Extract the target tool list

```php
/**
 * @return list<array<string, mixed>>
 */
function aiInstallerTargetToolEntries(): array
{
    return [
        ...aiInstallerRegistryEntries([
            'tools/ai/install/backup.php',
            'tools/ai/install/base.sh',
            'tools/ai/install/canonical-agent-frontmatter.php',
            'tools/ai/install/claude-agent-renderer.php',
            'tools/ai/install/claude-agent-tool-registry.php',
            'tools/ai/install/claude-settings-merge.php',
            'tools/ai/install/verify-install-result.php',
            'tools/ai/install/verify-manifest-args.php',
            'tools/ai/install/verify-manifest.php',
            'tools/ai/install/verify-no-overwrite.php',
            'tools/ai/compile-command-policy.php',
        ]),

        ...aiInstallerRegistryEntries([
            'tools/ai/install/permission-layers',
            'tools/ai/validation',
            'tools/ai/commands',
        ], [
            'type' => 'dir',
        ]),

        ...aiInstallerRegistryEntries([
            'tools/ai/ai.php',
            'tools/ai/ai_catalog_lib.php',
            'tools/ai/ai_output_lib.php',
            'tools/ai/build-context-pack.php',
            'tools/ai/full-install-validation.php',
            'tools/ai/generate-ai-catalog.php',
            'tools/ai/generate-ai-file-standards.php',
            'tools/ai/generate-repo-structure.php',
            'tools/ai/install-ai-kit.php',
            'tools/ai/maintenance-mode.php',
            'tools/ai/render-agent-permissions.php',
            'tools/ai/secret-scan.php',
            'tools/ai/suggest-verification.php',
            'tools/ai/validate-adapter-drift.php',
            'tools/ai/validate-ai-catalog.php',
            'tools/ai/validate-ai-config.php',
            'tools/ai/validate-command-policy.php',
            'tools/ai/validate-context-budgets.php',
            'tools/ai/validate-generated-artifacts.php',
            'tools/ai/validate-install-surface.php',
            'tools/ai/verify-install-placeholders.php',
            'tools/ai/validate-placeholders.php',
            'tools/ai/verify-full-install.php',
        ]),

        ...aiInstallerRegistryEntries([
            'tools/ai/generate-agent-snippets.php',
            'tools/ai/validate-stub-surfaces.php',
            'tools/ai/validate-catalog-drift.php',
            'tools/ai/validate-agent-spec.php',
            'tools/ai/validate-schemas.php',
            'tools/ai/validate-mentor-parity.php',
            'tools/ai/validate-agent-assessment.php',
            'tools/ai/validate-agent-assessment-values.php',
        ], [
            'required' => false,
        ]),

        ...aiInstallerRegistryEntries([
            'schemas/ai/ai-catalog.schema.json',
            'schemas/ai/ai-command-policy.schema.json',
            'schemas/ai/ai-file-standards.schema.json',
            'schemas/ai/ai-universal-rules-manifest.schema.json',
            'schemas/ai/generated-artifacts.schema.json',
            'schemas/ai/project-placeholders.schema.json',
            'schemas/ai/verification-matrix.schema.json',
        ], [
            'merge_strategy' => 'skip-if-exists',
        ]),

        ...aiInstallerRegistryEntries([
            'schemas/ai/agent-spec.schema.json',
            'schemas/ai/ai-handoff.schema.json',
            'schemas/ai/agent-assessment.schema.json',
            'schemas/ai/agent-assessment-values.schema.json',
        ], [
            'merge_strategy' => 'skip-if-exists',
            'required' => false,
        ]),
    ];
}
```

## Migration safety test

Capture the current registry before replacing it and compare the expanded result exactly:

```php
public function testRefactoredRegistryMatchesLegacyRegistry(): void
{
    self::assertSame(
        aiInstallerLegacyPackRegistry(),
        aiInstallerPackRegistry(),
    );
}
```

Also test the shorthand independently:

```php
public function testEntryUsesSourceAsDefaultTarget(): void
{
    self::assertSame(
        [
            'type' => 'file',
            'source' => 'docs/ai/index.md',
            'target' => 'docs/ai/index.md',
            'core' => false,
            'merge_strategy' => 'replace',
            'required' => true,
        ],
        aiInstallerRegistryEntry('docs/ai/index.md'),
    );
}
```

## Assessment

| Design                                                   | Maintainability |     Safety | Readability |
| -------------------------------------------------------- | --------------: | ---------: | ----------: |
| Current repeated arrays                                  |          35/100 |     70/100 |      32/100 |
| Several local closures                                   |          76/100 |     74/100 |      78/100 |
| Normalizer only                                          |          88/100 |     89/100 |      88/100 |
| **Normalizer + domain registries + shared entry groups** |      **94/100** | **94/100** |  **92/100** |

The key constraint is to keep meaningful exceptions explicit. Do not compress entries into positional tuples such as `[$path, false, true, 'replace']`; that would reduce characters while making reviews and future changes less safe.
