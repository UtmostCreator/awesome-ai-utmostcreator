<?php

declare(strict_types=1);

require_once __DIR__ . '/toolchain-registry.php';

/**
 * Windows cmd.exe cannot use a UNC path (e.g. \\wsl.localhost\...) as its
 * working directory and prints "UNC paths are not supported" before every
 * spawned command. These tool-detection calls are PATH-based and cwd-agnostic,
 * so temporarily switch to a non-UNC directory around them. No-op elsewhere.
 */
function aiInstallerSafeCwdEnter(): ?string
{
    if (PHP_OS_FAMILY !== 'Windows') {
        return null;
    }
    $cwd = getcwd();
    if ($cwd === false || !str_starts_with($cwd, '\\\\')) {
        return null;
    }
    $tmp = sys_get_temp_dir();
    if ($tmp !== '' && is_dir($tmp) && @chdir($tmp)) {
        return $cwd;
    }
    return null;
}

function aiInstallerSafeCwdLeave(?string $prev): void
{
    if ($prev !== null) {
        @chdir($prev);
    }
}

function aiInstallerPlatformKey(): string
{
    $family = PHP_OS_FAMILY;
    return match ($family) {
        'Windows' => 'windows',
        'Darwin' => 'macos',
        default => 'linux',
    };
}

/**
 * Ensure common user-local and platform tool directories are on PATH so the
 * installer's `command -v` checks see tools that the user installed under
 * their home directory (e.g. ~/.local/bin, ~/go/bin, ~/.cargo/bin) or via
 * npm prefix changes. Idempotent. Called once at installer entry.
 */
function aiInstallerBootstrapPath(): void
{
    $home = (string) (getenv('HOME') ?: getenv('USERPROFILE') ?: '');
    if ($home === '') {
        return;
    }

    $sep = PHP_OS_FAMILY === 'Windows' ? ';' : ':';
    $candidates = PHP_OS_FAMILY === 'Windows'
        ? [
            $home . DIRECTORY_SEPARATOR . '.local' . DIRECTORY_SEPARATOR . 'bin',
            $home . DIRECTORY_SEPARATOR . 'go' . DIRECTORY_SEPARATOR . 'bin',
            $home . DIRECTORY_SEPARATOR . '.cargo' . DIRECTORY_SEPARATOR . 'bin',
            $home . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Local' . DIRECTORY_SEPARATOR . 'Microsoft' . DIRECTORY_SEPARATOR . 'WinGet' . DIRECTORY_SEPARATOR . 'Links',
        ]
        : [
            $home . '/.local/bin',
            $home . '/go/bin',
            $home . '/.cargo/bin',
            '/opt/homebrew/bin',
            '/usr/local/bin',
        ];

    $path = (string) getenv('PATH');
    $existing = $path === '' ? [] : (preg_split('/' . preg_quote($sep, '/') . '/', $path) ?: []);
    $existingNormalized = array_map(
        static fn(string $p): string => rtrim(str_replace('\\', '/', $p), '/'),
        $existing
    );

    $additions = [];
    foreach ($candidates as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $norm = rtrim(str_replace('\\', '/', $dir), '/');
        if (in_array($norm, $existingNormalized, true)) {
            continue;
        }
        $additions[] = $dir;
    }

    if ($additions === []) {
        return;
    }

    $newPath = implode($sep, array_merge($additions, $existing === false ? [] : $existing));
    putenv('PATH=' . $newPath);
    $_SERVER['PATH'] = $newPath;
    $_ENV['PATH'] = $newPath;
}

function aiInstallerSelectedToolList(array $selectedPacks, array $withTools): array
{
    $dep = aiInstallerPackToolRequirements($selectedPacks);
    $tools = array_values(array_unique(array_merge($dep['required'] ?? [], $withTools)));
    return $tools;
}

/**
 * Render a per-tool install hint block for the missing required tools.
 * Always prints PATH bootstrap reminder + the cross-platform install commands.
 */
function aiInstallerMissingToolsHint(array $missing): string
{
    $home = (string) (getenv('HOME') ?: getenv('USERPROFILE') ?: '~');
    $lines = [];
    $lines[] = 'How to fix:';
    $lines[] = '  1) Ensure user bins are on PATH:';
    if (PHP_OS_FAMILY === 'Windows') {
        $lines[] = '       setx PATH "%USERPROFILE%\\.local\\bin;%PATH%"';
    } else {
        $lines[] = '       export PATH="' . $home . '/.local/bin:$PATH"';
    }
    $hintMap = [
        'fd' => 'apt: sudo apt install -y fd-find && ln -s "$(command -v fdfind)" ~/.local/bin/fd  |  brew: brew install fd  |  winget: winget install sharkdp.fd',
        'ast-grep' => 'npm: npm config set prefix "$HOME/.local" && npm install -g @ast-grep/cli  |  brew: brew install ast-grep  |  winget: winget install ast-grep',
        'scc' => 'release: curl -L https://github.com/boyter/scc/releases/latest/download/scc_Linux_x86_64.tar.gz | tar -xz -C ~/.local/bin scc  |  brew: brew install scc  |  winget: winget install BenBoyter.scc',
        'repomix' => 'npm: npm install -g repomix',
        'rg' => 'apt: sudo apt install -y ripgrep  |  brew: brew install ripgrep  |  winget: winget install BurntSushi.ripgrep.MSVC',
        'jq' => 'apt: sudo apt install -y jq  |  brew: brew install jq  |  winget: winget install jqlang.jq',
        'git' => 'apt: sudo apt install -y git  |  brew: brew install git  |  winget: winget install Git.Git',
        'bash' => 'install your distro\'s bash package; on Windows use WSL or Git Bash',
        'gitleaks' => 'release: curl -L https://github.com/gitleaks/gitleaks/releases/latest/download/gitleaks_linux_x64.tar.gz | tar -xz -C ~/.local/bin gitleaks',
        'shellcheck' => 'apt: sudo apt install -y shellcheck  |  brew: brew install shellcheck  |  winget: winget install koalaman.shellcheck',
        'yq' => 'apt: sudo apt install -y yq  |  brew: brew install yq  |  winget: winget install MikeFarah.yq',
    ];
    $lines[] = '  2) Install missing tools:';
    foreach ($missing as $tool) {
        $hint = $hintMap[$tool] ?? 'see docs/ai/mandatory-tools-install.md';
        $lines[] = '       - ' . $tool . ': ' . $hint;
    }
    $lines[] = '  3) Or rerun with --dependency-mode warn to proceed without strict checks (not recommended for full-governance).';
    $lines[] = '     Reference: docs/ai/mandatory-tools-install.md, docs/ai/toolchain-requirements.md.';
    return implode(PHP_EOL, $lines);
}

function aiInstallerCheckTool(string $tool, array $meta): array
{
    $cmd = (string) (($meta['check'][0] ?? $tool));
    $version = 'unknown';
    $present = aiInstallerCommandExists($cmd);

    if ($present) {
        $check = $meta['check'] ?? [$cmd, '--version'];
        $result = aiInstallerRunArgv($check, null);
        if (($result['exit'] ?? 1) === 0) {
            $line = trim((string) explode("\n", trim((string) ($result['stdout'] ?? '')))[0]);
            if ($line !== '') {
                $version = $line;
            }
        }
    }

    return [
        'tool' => $tool,
        'label' => (string) ($meta['label'] ?? $tool),
        'present' => $present,
        'version' => $version,
        'safe_auto_install' => (bool) ($meta['safe_auto_install'] ?? false),
        'requires_before_install' => $meta['requires_before_install'] ?? [],
        'install_hints' => $meta['install_hints'] ?? [],
        'install_commands' => $meta['install_commands'] ?? [],
    ];
}

function aiInstallerCommandExists(string $cmd): bool
{
    if ($cmd === 'ast-grep') {
        if (aiInstallerCommandExists('ast-grep.exe') || aiInstallerCommandExists('sg') || aiInstallerCommandExists('sg.exe')) {
            return true;
        }
    }

    if ($cmd === 'repomix') {
        if (aiInstallerCommandExists('repomix.cmd') || aiInstallerCommandExists('repomix.exe')) {
            return true;
        }
    }

    $out = [];
    $exit = 0;
    if (PHP_OS_FAMILY === 'Windows') {
        $safePrev = aiInstallerSafeCwdEnter();
        exec('where ' . escapeshellarg($cmd) . ' >NUL 2>&1', $out, $exit);
        aiInstallerSafeCwdLeave($safePrev);
        if ($exit === 0) {
            return true;
        }
        $user = getenv('USERPROFILE');
        if (is_string($user) && $user !== '') {
            $base = $user . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Local' . DIRECTORY_SEPARATOR . 'Microsoft' . DIRECTORY_SEPARATOR . 'WinGet' . DIRECTORY_SEPARATOR . 'Packages';
            if (is_dir($base)) {
                $wanted = strtolower($cmd . '.exe');
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
                foreach ($it as $entry) {
                    if (!$entry->isFile()) {
                        continue;
                    }
                    if (strtolower($entry->getFilename()) === $wanted) {
                        $dir = (string) $entry->getPath();
                        $path = (string) getenv('PATH');
                        $parts = preg_split('/;/', $path) ?: [];
                        $hasDir = false;
                        foreach ($parts as $part) {
                            if (strcasecmp(trim($part), $dir) === 0) {
                                $hasDir = true;
                                break;
                            }
                        }
                        if (!$hasDir) {
                            $newPath = $dir . ';' . $path;
                            putenv('PATH=' . $newPath);
                            $_SERVER['PATH'] = $newPath;
                            $_ENV['PATH'] = $newPath;
                        }
                        return true;
                    }
                }
            }
        }
        return false;
    }
    exec('command -v ' . escapeshellarg($cmd) . ' >/dev/null 2>&1', $out, $exit);
    return $exit === 0;
}

function aiInstallerToolchainReport(array $tools): array
{
    $registry = aiInstallerToolchainRegistry();
    $report = [];
    foreach ($tools as $tool) {
        if (!isset($registry[$tool])) {
            $report[] = ['tool' => $tool, 'label' => $tool, 'present' => false, 'version' => 'unknown', 'unknown' => true];
            continue;
        }
        $report[] = aiInstallerCheckTool($tool, $registry[$tool]);
    }
    return $report;
}

function aiInstallerRunArgv(array $argv, ?string $cwd): array
{
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($argv, $descriptors, $pipes, $cwd);
    $command = null;
    if (!is_resource($process)) {
        $parts = array_map(static fn($v): string => escapeshellarg((string) $v), $argv);
        $command = implode(' ', $parts);
        $process = proc_open($command, $descriptors, $pipes, $cwd);
    }
    if (!is_resource($process)) {
        throw new RuntimeException('failed to start process');
    }
    fclose($pipes[0]);
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = (int) proc_close($process);
    return ['exit' => $exit, 'stdout' => $stdout, 'stderr' => $stderr, 'argv' => $argv, 'command' => $command];
}
