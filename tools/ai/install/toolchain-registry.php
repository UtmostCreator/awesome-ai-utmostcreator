<?php

declare(strict_types=1);

function aiInstallerToolchainRegistry(): array
{
    return [
        'bash' => [
            'label' => 'Bash',
            'check' => ['bash', '--version'],
            'safe_auto_install' => false,
            'install_hints' => [],
        ],
        'git' => [
            'label' => 'Git',
            'check' => ['git', '--version'],
            'safe_auto_install' => false,
            'install_hints' => [
                'macos' => 'brew install git',
                'linux' => 'sudo apt install git',
                'windows' => 'winget install Git.Git',
            ],
        ],
        'jq' => [
            'label' => 'jq',
            'check' => ['jq', '--version'],
            'safe_auto_install' => false,
            'install_hints' => [
                'macos' => 'brew install jq',
                'linux' => 'sudo apt install jq',
                'windows' => 'winget install jqlang.jq',
            ],
        ],
        'rg' => [
            'label' => 'ripgrep',
            'check' => ['rg', '--version'],
            'safe_auto_install' => false,
            'install_hints' => [
                'macos' => 'brew install ripgrep',
                'linux' => 'sudo apt install ripgrep',
                'windows' => 'winget install BurntSushi.ripgrep.MSVC',
            ],
        ],
        'node' => [
            'label' => 'Node.js',
            'check' => ['node', '--version'],
            'safe_auto_install' => false,
            'install_hints' => [
                'macos' => 'brew install node',
                'linux' => 'Install Node.js from your package manager or nodejs.org',
                'windows' => 'winget install OpenJS.NodeJS.LTS',
            ],
        ],
        'npm' => [
            'label' => 'npm',
            'check' => ['npm', '--version'],
            'safe_auto_install' => false,
            'install_hints' => [
                'macos' => 'Installed with Node.js',
                'linux' => 'Installed with Node.js/npm package',
                'windows' => 'Installed with Node.js',
            ],
        ],
        'repomix' => [
            'label' => 'Repomix',
            'check' => ['repomix', '--version'],
            'requires_before_install' => ['node', 'npm'],
            'safe_auto_install' => true,
            'install_commands' => [
                'npm' => ['npm', 'install', '-g', 'repomix'],
            ],
            'install_hints' => [
                'npm' => 'npm install -g repomix',
            ],
        ],
        'scc' => [
            'label' => 'SCC',
            'check' => ['scc', '--version'],
            'safe_auto_install' => false,
            'install_hints' => [
                'macos' => 'brew install scc',
                'linux' => 'Install scc from your package manager or release binary',
                'windows' => 'Install scc via package manager or release binary',
            ],
        ],
    ];
}
