<?php

declare(strict_types=1);

require_once __DIR__ . '/../command-exists.php';

function aiShellNullRedirect(): string
{
    return PHP_OS_FAMILY === 'Windows' ? ' 2>NUL' : ' 2>/dev/null';
}

function aiCliCommandExists(string $command): bool
{
    return aiCommandExists($command);
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

function aiResolveWritableTempDir(string $root): string
{
    foreach ([sys_get_temp_dir(), getenv('TMPDIR'), getenv('TEMP'), getenv('TMP')] as $candidate) {
        if (is_string($candidate) && $candidate !== '' && is_dir($candidate) && is_writable($candidate)) {
            return $candidate;
        }
    }
    $fallback = $root . DIRECTORY_SEPARATOR . '.ai-logs';
    if (!is_dir($fallback)) {
        @mkdir($fallback, 0777, true);
    }
    return $fallback;
}

function aiRunCommand(string $root, string $command): array
{
    // Use temp files for stdout/stderr instead of pipes to avoid Windows
    // proc_open pipe-buffer deadlocks when a child writes more than the
    // OS pipe buffer (commonly ~4-64KiB) to either stream.
    // stream_set_blocking() does not work on pipes from proc_open on Windows.
    $tempDir = aiResolveWritableTempDir($root);
    $stdoutFile = @tempnam($tempDir, 'ai_cmd_out_');
    $stderrFile = @tempnam($tempDir, 'ai_cmd_err_');
    if ($stdoutFile === false || $stderrFile === false) {
        // Fallback: use a writable directory inside the repo so the run still works
        // even when the system temp directory is not writable (e.g. tests that
        // replace the entire child environment on Windows).
        $fallbackDir = $root . DIRECTORY_SEPARATOR . '.ai-logs';
        if (!is_dir($fallbackDir)) {
            @mkdir($fallbackDir, 0777, true);
        }
        if ($stdoutFile === false) {
            $stdoutFile = $fallbackDir . DIRECTORY_SEPARATOR . 'ai_cmd_out_' . uniqid('', true) . '.log';
        }
        if ($stderrFile === false) {
            $stderrFile = $fallbackDir . DIRECTORY_SEPARATOR . 'ai_cmd_err_' . uniqid('', true) . '.log';
        }
        @touch($stdoutFile);
        @touch($stderrFile);
        if (!is_file($stdoutFile) || !is_file($stderrFile)) {
            throw new RuntimeException('Failed to allocate temp files for command output');
        }
    }

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['file', $stdoutFile, 'w'],
        2 => ['file', $stderrFile, 'w'],
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
        @unlink($stdoutFile);
        @unlink($stderrFile);
        throw new RuntimeException('Failed to run command: ' . $command);
    }

    if (isset($pipes[0]) && is_resource($pipes[0])) {
        fclose($pipes[0]);
    }
    $exit = proc_close($process);

    $stdout = (string) @file_get_contents($stdoutFile);
    $stderr = (string) @file_get_contents($stderrFile);

    @unlink($stdoutFile);
    @unlink($stderrFile);

    return [
        'command' => $command,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'exit' => (int) $exit,
    ];
}

function aiGitCommand(string $root, string $args): string
{
    return 'git -C ' . escapeshellarg($root) . ' ' . $args;
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

function aiGitRefExists(string $root, string $ref): bool
{
    if ($ref === '') {
        return false;
    }
    $command = 'rev-parse --verify --quiet ' . escapeshellarg($ref . '^{commit}');
    return aiGitLines($root, $command) !== [];
}

/**
 * Resolve the base ref to diff against, in priority order:
 *   1. explicit --base value (honoured verbatim when non-empty)
 *   2. the remote default branch via origin/HEAD symbolic-ref
 *   3. the first existing of origin/main, origin/master, main, master, develop, trunk
 *   4. the immediate parent commit (HEAD~1) when one exists
 *   5. the empty-tree object so callers still get a usable diff in a fresh repo
 *
 * Avoids hardcoding "main" so the tooling works on repos whose default branch
 * is master/develop/trunk or whose branch was cut from a non-main base.
 */
function aiResolveBaseRef(string $root, ?string $explicit = null): string
{
    $explicit = $explicit !== null ? trim($explicit) : '';
    if ($explicit !== '') {
        return $explicit;
    }

    $originHead = '';
    $lines = aiGitLines($root, 'symbolic-ref --quiet --short refs/remotes/origin/HEAD');
    if ($lines !== []) {
        $originHead = $lines[0];
    }
    if ($originHead !== '' && aiGitRefExists($root, $originHead)) {
        return $originHead;
    }

    foreach (['origin/main', 'origin/master', 'main', 'master', 'develop', 'trunk'] as $candidate) {
        if (aiGitRefExists($root, $candidate)) {
            return $candidate;
        }
    }

    if (aiGitRefExists($root, 'HEAD~1')) {
        return 'HEAD~1';
    }

    // Git's well-known empty-tree object id: diffs the entire tree as a fallback.
    return '4b825dc642cb6eb9a060e54bf8d69288fbee4904';
}

function aiBaseRefFromArgs(string $root, array $args): string
{
    return aiResolveBaseRef($root, aiParseArg($args, 'base'));
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
    $dir = $root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'backups';
    if (!is_dir($dir)) {
        $legacy = $root . DIRECTORY_SEPARATOR . '.ai-backups';
        $dir = is_dir($legacy) ? $legacy : $dir;
    }
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
