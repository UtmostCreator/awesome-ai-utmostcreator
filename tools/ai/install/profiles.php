<?php

declare(strict_types=1);

function aiInstallerProfileDefinitions(): array
{
    return [
        'minimal' => ['base', 'setup-docs', 'capabilities-core'],
        'copilot' => ['minimal', 'adapter-copilot', 'scripts-pack', 'policy-pack', 'hooks-pack'],
        'opencode' => ['minimal', 'adapter-opencode', 'scripts-pack', 'policy-pack', 'hooks-pack'],
        'dual' => ['minimal', 'adapter-copilot', 'adapter-opencode', 'capabilities-extended', 'scripts-pack', 'policy-pack', 'hooks-pack'],
        'guarded' => ['dual', 'policy-pack', 'hooks-pack', 'evidence-pack'],
        'accelerated' => ['dual', 'scripts-pack', 'policy-pack', 'evidence-pack'],
        'full-governance' => ['accelerated', 'capabilities-governance', 'hooks-pack', 'ci-pack', 'docs-reference-pack', 'delivery-pack', 'optional-agents-opencode-pack', 'optional-agents-copilot-pack', 'preview-environments-pack', 'evaluation-pack', 'service-boundary-pack', 'mcp-boundaries-pack', 'advisor-pack', 'target-tools-pack', 'shared-templates-pack'],
        'docs-reference' => ['docs-reference-pack'],
        'custom' => [],
        // --- Editions: user-facing aliases over the profile/pack system. ---
        // Adapters are baked into each edition definition (not left to the runtime
        // re-add string lists in packs.php) so `--runtime` overrides stay correct
        // without future drift. `base` is force-added by installBase regardless.
        'basic' => ['minimal'],
        'standard' => ['dual'],
        'creator' => ['standard', 'optional-agents-opencode-pack', 'optional-agents-copilot-pack'],
        'full' => ['full-governance'],
        // agents-only: ship the agents PLUS their hard dependencies. Agents reference
        // scripts/ai/*.sh in their permission allowlists and load capability docs, so
        // shipping adapters without scripts-pack + capabilities-core produces agents
        // whose allowlisted commands and doc refs do not exist.
        'agents-only' => ['adapter-copilot', 'adapter-opencode', 'scripts-pack', 'capabilities-core'],
    ];
}

function aiInstallerAllFeaturePacks(): array
{
    return [
        'base',
        'setup-docs',
        'capabilities-core',
        'capabilities-extended',
        'capabilities-governance',
        'adapter-copilot',
        'adapter-opencode',
        'scripts-pack',
        'policy-pack',
        'hooks-pack',
        'ci-pack',
        'evidence-pack',
        'docs-reference-pack',
        'delivery-pack',
        'optional-agents-opencode-pack',
        'optional-agents-copilot-pack',
        'preview-environments-pack',
        'evaluation-pack',
        'service-boundary-pack',
        'mcp-boundaries-pack',
        'advisor-pack',
        'target-tools-pack',
        'shared-templates-pack',
        'package-source-pack',
        'kit-authoring-pack',
    ];
}
