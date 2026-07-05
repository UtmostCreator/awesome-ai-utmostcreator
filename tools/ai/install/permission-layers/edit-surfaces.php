<?php

declare(strict_types=1);

require_once __DIR__ . '/core.php';

/** @return array<string,list<array{permission:string,pattern:string,effect:string}>> */
function aiPermissionEditSurfaces(): array
{
    $denyTail = [
        'vendor/**' => 'deny',
        'node_modules/**' => 'deny',
        '.git/**' => 'deny',
        'dist/**' => 'deny',
        'build/**' => 'deny',
        'coverage/**' => 'deny',
        '.cache/**' => 'deny',
        'docs/ai/generated/**' => 'deny',
        'docs/generated/**' => 'deny',
        '*.generated.*' => 'deny',
        '*.lock' => 'deny',
        'composer.lock' => 'deny',
        'package-lock.json' => 'deny',
        'pnpm-lock.yaml' => 'deny',
        'yarn.lock' => 'deny',
        'bun.lockb' => 'deny',
        '*.pem' => 'deny',
        '*.key' => 'deny',
        '*.crt' => 'deny',
        '.env*' => 'deny',
        'secrets.*' => 'deny',
        'credentials.*' => 'deny',
        'auth.json' => 'deny',
    ];

    return [
        'none' => aiPermissionEntries('edit', ['*' => 'deny']),
        // Reserved for the one internal, pinned-open power agent (super-implementer.md ships
        // 'edit: "*": allow' with no path denials at all, same rationale as its shipped_star_baseline
        // 'allow' bash pin — see docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/
        // plan.md, N-3). Do not reuse this surface for any other agent without an explicit,
        // reviewed decision: it grants edit access to every path, including secrets/lockfiles.
        'unrestricted' => aiPermissionEntries('edit', ['*' => 'allow']),
        'tickets' => aiPermissionEntries('edit', ['docs/tickets/**' => 'allow']),
        'research-sessions' => aiPermissionEntries('edit', ['.opencode/research-sessions/**' => 'allow']),
        'code' => aiPermissionEntries('edit', array_merge([
            'src/**' => 'allow',
            'app/**' => 'allow',
            'packages/**' => 'allow',
            'configs/**' => 'allow',
            'scripts/**' => 'allow',
            'tools/**' => 'allow',
            'tests/**' => 'allow',
            'docs/**' => 'allow',
        ], $denyTail)),
        'docs' => aiPermissionEntries('edit', array_merge([
            'docs/**' => 'allow',
            '*.md' => 'allow',
            'README.md' => 'allow',
            'AGENTS.md' => 'allow',
            'CLAUDE.md' => 'allow',
        ], $denyTail)),
        'config' => aiPermissionEntries('edit', array_merge([
            'configs/**' => 'allow',
            '.editorconfig' => 'allow',
            '.eslintrc.json' => 'allow',
            '.prettierrc.json' => 'allow',
            '.stylelintrc.json' => 'allow',
            '.markdownlint-cli2.yaml' => 'allow',
            '.shellcheckrc' => 'allow',
            'configs/php/**' => 'allow',
            'configs/shell/**' => 'allow',
            'configs/vscode/**' => 'allow',
            'configs/nvim/**' => 'allow',
            'configs/ghostty/**' => 'allow',
            'configs/karabiner/**' => 'allow',
            'packages/**' => 'deny',
        ], $denyTail)),
        'install' => aiPermissionEntries('edit', array_merge([
            'AGENTS.md' => 'allow',
            'README.md' => 'allow',
            'PLACEHOLDERS.md' => 'allow',
            '.ai-install-manifest.json' => 'allow',
            'docs/ai/**' => 'allow',
            'docs/**/*.md' => 'allow',
            '.github/agents/**' => 'allow',
            '.github/instructions/**' => 'allow',
            '.github/prompts/**' => 'allow',
            '.opencode/agents/**' => 'allow',
            '.opencode/commands/**' => 'allow',
            '.opencode/skills/**' => 'allow',
            'opencode.jsonc' => 'allow',
            'scripts/ai/**' => 'allow',
            'tools/ai/**' => 'allow',
        ], $denyTail)),
    ];
}
