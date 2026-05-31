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
        'php' => [
            'label' => 'PHP',
            'check' => ['php', '--version'],
            'safe_auto_install' => false,
            'install_hints' => [
                'macos' => 'brew install php',
                'linux' => 'sudo apt install php-cli',
                'windows' => 'winget install PHP.PHP',
            ],
        ],
        'fd' => [
            'label' => 'fd',
            'check' => ['fd', '--version'],
            'safe_auto_install' => false,
            'install_hints' => [
                'macos' => 'brew install fd',
                'linux' => 'sudo apt install fd-find',
                'windows' => 'winget install sharkdp.fd',
            ],
        ],
        'ast-grep' => [
            'label' => 'ast-grep',
            'check' => ['ast-grep', '--version'],
            'safe_auto_install' => false,
            'install_hints' => [
                'macos' => 'brew install ast-grep',
                'linux' => 'cargo install ast-grep  |  npm install -g @ast-grep/cli',
                'windows' => 'winget install ast-grep.ast-grep',
            ],
        ],
        'gh' => [
            'label' => 'GitHub CLI',
            'check' => ['gh', '--version'],
            'safe_auto_install' => false,
            'install_hints' => [
                'macos' => 'brew install gh',
                'linux' => 'sudo apt install gh',
                'windows' => 'winget install GitHub.cli',
            ],
        ],
        'python3' => [
            'label' => 'Python 3',
            'check' => ['python3', '--version'],
            'safe_auto_install' => false,
            'install_hints' => [
                'macos' => 'brew install python',
                'linux' => 'sudo apt install python3',
                'windows' => 'winget install Python.Python.3.12',
            ],
        ],
        'date' => [
            'label' => 'date (coreutils)',
            'check' => ['date', '--version'],
            'safe_auto_install' => false,
            'install_hints' => [
                'macos' => 'Provided by the system or: brew install coreutils',
                'linux' => 'Provided by coreutils (preinstalled)',
                'windows' => 'Provided by Git Bash/WSL coreutils',
            ],
        ],
        'shellcheck' => [
            'label' => 'ShellCheck',
            'check' => ['shellcheck', '--version'],
            'safe_auto_install' => false,
            'install_hints' => [
                'macos' => 'brew install shellcheck',
                'linux' => 'sudo apt install shellcheck',
                'windows' => 'winget install koalaman.shellcheck',
            ],
        ],
        'gitleaks' => [
            'label' => 'gitleaks',
            'check' => ['gitleaks', 'version'],
            'safe_auto_install' => false,
            'install_hints' => [
                'macos' => 'brew install gitleaks',
                'linux' => 'curl -L https://github.com/gitleaks/gitleaks/releases/latest/download/gitleaks_linux_x64.tar.gz | tar -xz -C ~/.local/bin gitleaks',
                'windows' => 'winget install gitleaks.gitleaks',
            ],
        ],
        'yq' => [
            'label' => 'yq',
            'check' => ['yq', '--version'],
            'safe_auto_install' => false,
            'install_hints' => [
                'macos' => 'brew install yq',
                'linux' => 'sudo apt install yq',
                'windows' => 'winget install MikeFarah.yq',
            ],
        ],
    ];
}
