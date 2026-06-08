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
    aiResolveKitDescriptorPath($root, 'catalog.json') => 'php tools/ai/generate-ai-catalog.php --check',
    $packageDocsBase . 'BROWSE.md' => 'php tools/ai/generate-ai-catalog.php --check',
    'llms.txt' => 'php tools/ai/generate-ai-catalog.php --check',
    'docs/ai/repo-required-tools.md' => 'php tools/ai/repo-tool-inventory.php --check',
];

$requiredGeneratedHeaders = [
    'docs/ai/catalog.md',
    $packageDocsBase . 'BROWSE.md',
    'llms.txt',
];

$errors = [];

foreach ($required as $path => $generator) {
    if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path))) {
        $errors[] = "missing generated artifact {$path} (generator: {$generator})";
    }
}

foreach ($requiredGeneratedHeaders as $path) {
    $absolutePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (!is_file($absolutePath)) {
        continue;
    }
    $content = (string) file_get_contents($absolutePath);
    if (!str_starts_with($content, '<!-- GENERATED — DO NOT EDIT:')) {
        $errors[] = "generated artifact {$path} is missing GENERATED — DO NOT EDIT header";
    }
}

// P3: enforce the hard generated-file markers on the SOURCE surfaces that the installer
// renders from. These are deterministic to check (no install run required) and keep the
// "generated, do not edit" contract honest at its source.
//
// 1) AGENTS.md is rendered from this template; the GENERATED header must be the first line
//    of the template so it survives placeholder substitution into the installed AGENTS.md.
$agentsTemplate = 'packages/ai-universal-rules/templates/core/AGENTS.template.md';
$agentsTemplateAbs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $agentsTemplate);
if (is_file($agentsTemplateAbs)) {
    if (!str_starts_with((string) file_get_contents($agentsTemplateAbs), '<!-- GENERATED — DO NOT EDIT:')) {
        $errors[] = "source template {$agentsTemplate} must start with the GENERATED — DO NOT EDIT header (it renders the installed AGENTS.md)";
    }
}

// 2) Hook JSON has no comments, so the generated marker lives in a `_generated` metadata
//    object. Enforce `_generated.tool == "ai-kit"` on the shipped hook templates.
$hookJsonTemplates = [
    'packages/ai-universal-rules/templates/github/hooks/tool-policy.json',
    'packages/ai-universal-rules/templates/github/hooks/tool-guardian.json',
];
foreach ($hookJsonTemplates as $hookPath) {
    $hookAbs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $hookPath);
    if (!is_file($hookAbs)) {
        continue;
    }
    $decoded = json_decode((string) file_get_contents($hookAbs), true);
    if (!is_array($decoded)) {
        $errors[] = "hook config {$hookPath} is not valid JSON";
        continue;
    }
    if (($decoded['_generated']['tool'] ?? null) !== 'ai-kit') {
        $errors[] = "hook config {$hookPath} is missing the generated marker (_generated.tool == \"ai-kit\")";
    }
}

// 3) opencode.json installs to opencode.jsonc and carries a SOFT managed notice (not a hard
//    "DO NOT EDIT") because it has adopt/conflict semantics. Enforce the soft notice marker.
$opencodeTemplate = 'packages/ai-universal-rules/templates/core/opencode.json';
$opencodeTemplateAbs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $opencodeTemplate);
if (is_file($opencodeTemplateAbs)) {
    if (!str_contains((string) file_get_contents($opencodeTemplateAbs), '// Managed by ai-kit.')) {
        $errors[] = "source template {$opencodeTemplate} must contain the soft '// Managed by ai-kit.' notice";
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
