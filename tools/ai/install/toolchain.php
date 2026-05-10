<?php

declare(strict_types=1);

require_once __DIR__ . '/toolchain-registry.php';

function aiInstallerPlatformKey(): string
{
    $family = PHP_OS_FAMILY;
    return match ($family) {
        'Windows' => 'windows',
        'Darwin' => 'macos',
        default => 'linux',
    };
}

function aiInstallerSelectedToolList(array $selectedPacks, array $withTools): array
{
    $dep = aiInstallerPackToolRequirements($selectedPacks);
    $tools = array_values(array_unique(array_merge($dep['required'] ?? [], $withTools)));
    return $tools;
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
        exec('where ' . escapeshellarg($cmd) . ' >NUL 2>&1', $out, $exit);
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
