<?php

declare(strict_types=1);

function aiShellNullRedirect(): string
{
    return PHP_OS_FAMILY === 'Windows' ? ' 2>NUL' : ' 2>/dev/null';
}

function aiCliCommandExists(string $command): bool
{
    $out = [];
    $exit = 0;
    if (PHP_OS_FAMILY === 'Windows') {
        exec('where ' . escapeshellarg($command) . ' >NUL 2>&1', $out, $exit);
        if ($exit === 0) {
            return true;
        }
        $user = getenv('USERPROFILE');
        if (is_string($user) && $user !== '') {
            $base = $user . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Local' . DIRECTORY_SEPARATOR . 'Microsoft' . DIRECTORY_SEPARATOR . 'WinGet' . DIRECTORY_SEPARATOR . 'Packages';
            if (is_dir($base)) {
                $wanted = strtolower($command . '.exe');
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
    exec('command -v ' . escapeshellarg($command) . ' >/dev/null 2>&1', $out, $exit);
    return $exit === 0;
}

function aiEvaluateStaleEntries(string $root): array
{
    $registry = aiCliLoadArtifactsRegistry(aiCliGeneratedDir($root));
    $current = aiCliCurrentCommit($root);
    $stale = [];

    $artifacts = $registry['artifacts'] ?? [];
    if (!is_array($artifacts)) {
        return [];
    }

    foreach ($artifacts as $name => $meta) {
        if (!is_array($meta)) {
            continue;
        }
        $basedOn = (string) ($meta['based_on_commit'] ?? 'unknown');
        if ($basedOn !== 'unknown' && $current !== 'unknown' && $basedOn !== $current) {
            $stale[] = $name;
        }
    }

    return $stale;
}

function aiRunCommand(string $root, string $command): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $env = [
        'HOME' => sys_get_temp_dir(),
        'XDG_CONFIG_HOME' => sys_get_temp_dir(),
        'GIT_CONFIG_GLOBAL' => '/dev/null',
        'PATH' => (string) getenv('PATH'),
    ];

    if (str_starts_with($command, 'php ')) {
        $phpBin = defined('PHP_BINARY') ? (string) PHP_BINARY : 'php';
        $command = escapeshellarg($phpBin) . substr($command, 3);
    }

    $process = proc_open($command, $descriptors, $pipes, $root, $env);
    if (!is_resource($process)) {
        throw new RuntimeException('Failed to run command: ' . $command);
    }

    fclose($pipes[0]);
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);

    return [
        'command' => $command,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'exit' => (int) $exit,
    ];
}

function aiParseArg(array $args, string $name): ?string
{
    for ($i = 0; $i < count($args); $i++) {
        $arg = $args[$i];
        if ($arg === '--' . $name) {
            return isset($args[$i + 1]) ? (string) $args[$i + 1] : null;
        }
        if (str_starts_with($arg, '--' . $name . '=')) {
            return (string) substr($arg, strlen($name) + 3);
        }
    }

    return null;
}

function aiLoadArtifactData(string $root, string $artifactName): ?array
{
    $path = aiCliGeneratedDir($root) . DIRECTORY_SEPARATOR . $artifactName;
    if (!is_file($path)) {
        return null;
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}

function aiPromptLine(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    $line = fgets(STDIN);
    return $line === false ? '' : trim($line);
}

function aiPromptYesNo(string $prompt, bool $defaultNo = true): bool
{
    $suffix = $defaultNo ? ' [y/N]: ' : ' [Y/n]: ';
    $value = strtolower(aiPromptLine($prompt . $suffix));
    if ($value === '') {
        return !$defaultNo;
    }
    return in_array($value, ['y', 'yes'], true);
}

function aiLatestBackupId(string $root): ?string
{
    $dir = $root . DIRECTORY_SEPARATOR . '.ai-backups';
    if (!is_dir($dir)) {
        return null;
    }
    $entries = array_values(array_filter(scandir($dir) ?: [], static fn(string $e): bool => $e !== '.' && $e !== '..'));
    if ($entries === []) {
        return null;
    }
    rsort($entries, SORT_STRING);
    return $entries[0];
}

function aiArgsAfterDoubleDash(array $args): array
{
    $idx = array_search('--', $args, true);
    if ($idx === false) {
        return [];
    }
    return array_slice($args, $idx + 1);
}

function aiDetectRuntimeMode(array $args): string
{
    if (in_array('--agent', $args, true)) {
        return 'AI_AGENT';
    }
    if (in_array('--ci', $args, true)) {
        return 'CI';
    }
    if (in_array('--interactive', $args, true)) {
        return 'HUMAN_TTY';
    }

    $ci = (string) getenv('CI');
    $gh = (string) getenv('GITHUB_ACTIONS');
    if (strtolower($ci) === 'true' || strtolower($gh) === 'true') {
        return 'CI';
    }

    $envKeys = array_keys($_ENV + $_SERVER);
    foreach ($envKeys as $key) {
        if (str_starts_with((string) $key, 'OPENCODE_') || str_starts_with((string) $key, 'CLAUDE_') || str_starts_with((string) $key, 'COPILOT_')) {
            return 'AI_AGENT';
        }
    }

    if (function_exists('stream_isatty') && stream_isatty(STDIN)) {
        return 'HUMAN_TTY';
    }
    return 'CI';
}
