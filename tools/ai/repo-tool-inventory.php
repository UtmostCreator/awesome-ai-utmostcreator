<?php

declare(strict_types=1);

$root = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');

if ($root === false || !is_dir($root)) {
    fwrite(STDERR, "ERROR: repository root not found\n");
    exit(1);
}

$checkOnly = in_array('--check', $argv, true);
$write = in_array('--write', $argv, true) || (!$checkOnly && !in_array('--check', $argv, true));
$outputPath = cliArg($argv, 'output') ?? 'docs/ai/repo-required-tools.md';

$inventory = buildRepoToolInventory($root);
$expected = renderRepoRequiredToolsMarkdown($inventory);
$absoluteOutputPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $outputPath);

if ($checkOnly) {
    if (!is_file($absoluteOutputPath)) {
        fwrite(STDERR, "ERROR: {$outputPath} is missing\n");
        exit(1);
    }

    $current = normalizeGeneratedContent((string) file_get_contents($absoluteOutputPath));

    if ($current !== normalizeGeneratedContent($expected)) {
        fwrite(STDERR, "ERROR: {$outputPath} is out of date. Run: php tools/ai/repo-tool-inventory.php --write\n");
        exit(1);
    }

    fwrite(STDOUT, "OK: {$outputPath} is up to date\n");
    exit(0);
}

if ($write) {
    $dir = dirname($absoluteOutputPath);

    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        fwrite(STDERR, "ERROR: unable to create directory {$dir}\n");
        exit(1);
    }

    $existing = is_file($absoluteOutputPath) ? normalizeGeneratedContent((string) file_get_contents($absoluteOutputPath)) : null;
    $normalizedExpected = normalizeGeneratedContent($expected);

    if ($existing === $normalizedExpected) {
        fwrite(STDOUT, "OK: {$outputPath} is up to date\n");
        exit(0);
    }

    file_put_contents($absoluteOutputPath, $normalizedExpected);
    fwrite(STDOUT, "OK: regenerated {$outputPath}\n");
    exit(0);
}

fwrite(STDERR, "ERROR: unsupported mode\n");
exit(1);

function cliArg(array $argv, string $name): ?string
{
    foreach ($argv as $index => $arg) {
        if ($arg === '--' . $name) {
            return isset($argv[$index + 1]) ? (string) $argv[$index + 1] : null;
        }

        if (str_starts_with((string) $arg, '--' . $name . '=')) {
            return substr((string) $arg, strlen($name) + 3);
        }
    }

    return null;
}

function normalizeGeneratedContent(string $content): string
{
    return str_replace("\r\n", "\n", $content);
}

/**
 * @return array{
 *     baseline_tools: list<string>,
 *     referenced_tools: list<string>,
 *     source_scripts: list<string>
 * }
 */
function buildRepoToolInventory(string $root): array
{
    $baselineTools = [
        'bash',
        'git',
        'php',
        'rg',
        'repomix',
        'scc',
        'jq',
    ];

    $candidateTools = [
        'actionlint',
        'ast-grep',
        'awk',
        'bat',
        'composer',
        'cut',
        'delta',
        'fd',
        'find',
        'fzf',
        'gh',
        'gitleaks',
        'grep',
        'head',
        'jq',
        'mktemp',
        'node',
        'npm',
        'npx',
        'php',
        'pnpm',
        'realpath',
        'repomix',
        'rg',
        'scc',
        'sed',
        'semgrep',
        'shellcheck',
        'shfmt',
        'sort',
        'tail',
        'tr',
        'wc',
        'xargs',
        'yq',
    ];

    $scripts = collectShellScripts($root);
    $referenced = [];

    foreach ($scripts as $relativePath) {
        $absolutePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (!is_file($absolutePath)) {
            continue;
        }

        $content = (string) file_get_contents($absolutePath);

        foreach ($candidateTools as $tool) {
            if (preg_match('/(^|[^A-Za-z0-9_.-])' . preg_quote($tool, '/') . '([^A-Za-z0-9_.-]|$)/', $content) === 1) {
                $referenced[$tool] = true;
            }
        }
    }

    foreach ($baselineTools as $tool) {
        unset($referenced[$tool]);
    }

    $referencedTools = array_keys($referenced);
    sort($referencedTools, SORT_STRING);

    sort($scripts, SORT_STRING);

    return [
        'baseline_tools' => $baselineTools,
        'referenced_tools' => $referencedTools,
        'source_scripts' => $scripts,
    ];
}

/**
 * @return list<string>
 */
function collectShellScripts(string $root): array
{
    $result = runCommand(['git', 'ls-files', '*.sh'], $root);

    if ($result['exit'] === 0) {
        return array_values(array_filter(
            array_map(
                static fn (string $line): string => trim(str_replace('\\', '/', $line)),
                preg_split('/\r?\n/', $result['stdout']) ?: []
            ),
            static fn (string $line): bool => $line !== ''
        ));
    }

    $scripts = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'sh') {
            continue;
        }

        $path = str_replace('\\', '/', $file->getPathname());
        $rootPrefix = str_replace('\\', '/', $root) . '/';

        if (!str_starts_with($path, $rootPrefix)) {
            continue;
        }

        $relative = substr($path, strlen($rootPrefix));

        if (str_contains($relative, '/vendor/') || str_contains($relative, '/node_modules/') || str_contains($relative, '/.git/')) {
            continue;
        }

        $scripts[] = $relative;
    }

    sort($scripts, SORT_STRING);

    return $scripts;
}

/**
 * @param array{baseline_tools: list<string>, referenced_tools: list<string>, source_scripts: list<string>} $inventory
 */
function renderRepoRequiredToolsMarkdown(array $inventory): string
{
    $lines = [];

    $lines[] = '# Repository Required Tools';
    $lines[] = '';
    $lines[] = '_Generated by `php tools/ai/repo-tool-inventory.php`. Do not edit by hand._';
    $lines[] = '';
    $lines[] = 'This file lists baseline and referenced CLI tools used by the repository AI workflow scripts.';
    $lines[] = '';
    $lines[] = '## Baseline Required Tools';
    $lines[] = '';

    foreach ($inventory['baseline_tools'] as $tool) {
        $lines[] = '- `' . $tool . '`';
    }

    $lines[] = '';
    $lines[] = '## Referenced Tools Detected In Shell Scripts';
    $lines[] = '';

    if ($inventory['referenced_tools'] === []) {
        $lines[] = '- none';
    } else {
        foreach ($inventory['referenced_tools'] as $tool) {
            $lines[] = '- `' . $tool . '`';
        }
    }

    $lines[] = '';
    $lines[] = '## Source Scripts';
    $lines[] = '';

    foreach ($inventory['source_scripts'] as $script) {
        $lines[] = '- `' . $script . '`';
    }

    return implode("\n", $lines) . "\n";
}

/**
 * @param list<string> $command
 * @return array{stdout: string, stderr: string, exit: int}
 */
function runCommand(array $command, string $cwd): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes, $cwd);

    if (!is_resource($process)) {
        return ['stdout' => '', 'stderr' => 'failed to start command', 'exit' => 1];
    }

    fclose($pipes[0]);
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
        'stdout' => $stdout,
        'stderr' => $stderr,
        'exit' => proc_close($process),
    ];
}