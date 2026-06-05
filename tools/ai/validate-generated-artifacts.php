<?php

declare(strict_types=1);

require_once __DIR__ . '/ai_catalog_lib.php';

$root = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');

if ($root === false || !is_dir($root)) {
    fwrite(STDERR, "ERROR: repository root not found\n");
    exit(1);
}

$packageBase = aiResolvePackageBase($root);
$packageDocsBase = aiResolvePackageDocsBase($root);

$sourceRepoMode = is_file($root . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'CliToolsTest.php');
$existenceOnly = in_array('--existence-only', $argv, true) || !$sourceRepoMode;
$write = in_array('--write', $argv, true) || in_array('--fix', $argv, true);

$required = [
    'docs/ai/catalog.md' => 'php tools/ai/generate-ai-catalog.php --check',
    $packageBase . 'catalog.json' => 'php tools/ai/generate-ai-catalog.php --check',
    $packageDocsBase . 'BROWSE.md' => 'php tools/ai/generate-ai-catalog.php --check',
    'llms.txt' => 'php tools/ai/generate-ai-catalog.php --check',
    'docs/ai/repo-required-tools.md' => 'php tools/ai/repo-tool-inventory.php --check',
];

$errors = [];

foreach ($required as $path => $generator) {
    if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path))) {
        $errors[] = "missing generated artifact {$path} (generator: {$generator})";
    }
}

if (!$existenceOnly) {
    $phpBin = defined('PHP_BINARY') ? (string) PHP_BINARY : 'php';

    $catalogCheck = runCommand([
        $phpBin,
        'tools/ai/generate-ai-catalog.php',
        '--check',
    ], $root);

    if ($catalogCheck['exit'] !== 0) {
        $errors[] = 'generated artifact drift detected by generate-ai-catalog --check';
        writePrefixedLines('CHECK: ', $catalogCheck['stdout'], STDOUT);
        writePrefixedLines('CHECK: ', $catalogCheck['stderr'], STDERR);
    } else {
        writePrefixedLines('CHECK: ', $catalogCheck['stdout'], STDOUT);
    }

    if ($write) {
        $toolWrite = runCommand([
            $phpBin,
            'tools/ai/repo-tool-inventory.php',
            '--write',
        ], $root);

        writePrefixedLines('CHECK: ', $toolWrite['stdout'], STDOUT);
        writePrefixedLines('CHECK: ', $toolWrite['stderr'], STDERR);

        if ($toolWrite['exit'] !== 0) {
            $errors[] = 'failed to regenerate repo-required-tools';
        }
    }

    $toolCheck = runCommand([
        $phpBin,
        'tools/ai/repo-tool-inventory.php',
        '--check',
    ], $root);

    if ($toolCheck['exit'] !== 0) {
        $errors[] = 'generated artifact drift detected by repo-required-tools';
        writePrefixedLines('CHECK: ', $toolCheck['stdout'], STDOUT);
        writePrefixedLines('CHECK: ', $toolCheck['stderr'], STDERR);
    } else {
        writePrefixedLines('CHECK: ', $toolCheck['stdout'], STDOUT);
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "ERROR: {$error}\n");
    }

    exit(1);
}

fwrite(STDOUT, "OK: generated artifact baseline present\n");
if (!$sourceRepoMode) {
    fwrite(STDOUT, "INFO: installed target mode; skipped source-repository drift checks\n");
}
exit(0);

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
        return [
            'stdout' => '',
            'stderr' => 'failed to start command: ' . implode(' ', $command),
            'exit' => 1,
        ];
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

/**
 * @param resource $stream
 */
function writePrefixedLines(string $prefix, string $content, $stream): void
{
    foreach (preg_split('/\r?\n/', trim($content)) ?: [] as $line) {
        if ($line === '') {
            continue;
        }

        fwrite($stream, $prefix . $line . PHP_EOL);
    }
}
