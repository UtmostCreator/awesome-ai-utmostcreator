<?php

declare(strict_types=1);

function aiInstallerProfileDefinitions(): array
{
    return [
        'minimal' => ['base', 'setup-docs', 'capabilities-core'],
        'copilot' => ['minimal', 'adapter-copilot', 'scripts-pack', 'policy-pack', 'hooks-pack'],
        'opencode' => ['minimal', 'adapter-opencode', 'scripts-pack', 'policy-pack', 'hooks-pack'],
        'dual' => ['minimal', 'adapter-copilot', 'adapter-opencode', 'capabilities-extended-lite', 'scripts-pack', 'policy-pack', 'hooks-pack'],
        'guarded' => ['dual', 'policy-pack', 'hooks-pack', 'evidence-pack'],
        'accelerated' => ['dual', 'scripts-pack', 'policy-pack', 'evidence-pack'],
        'full-governance' => ['accelerated', 'capabilities-extended-full', 'hooks-pack', 'ci-pack', 'docs-reference-pack', 'delivery-pack', 'optional-agents-opencode-pack', 'optional-agents-copilot-pack', 'preview-environments-pack', 'evaluation-pack', 'service-boundary-pack', 'mcp-boundaries-pack', 'shared-templates-pack'],
        'docs-reference' => ['docs-reference-pack'],
        'custom' => [],
    ];
}

function aiInstallerAllFeaturePacks(): array
{
    return [
        'base',
        'setup-docs',
        'capabilities-core',
        'capabilities-extended-lite',
        'capabilities-extended-full',
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
        'shared-templates-pack',
    ];
}
