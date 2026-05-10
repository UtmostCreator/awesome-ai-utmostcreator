<?php

declare(strict_types=1);

$root = realpath(__DIR__ . '/..' . '/..');
if ($root === false) {
    fwrite(STDERR, "ERROR: repository root not found\n");
    exit(1);
}

$adapterFiles = [
    'AGENTS.md',
    'CLAUDE.md',
    '.github/copilot-instructions.md',
];

$failOnWarn = in_array('--fail-on-warn', $argv, true);
$changedOnly = in_array('--changed-only', $argv, true);

foreach (glob($root . '/.github/agents/*.md') ?: [] as $file) {
    $adapterFiles[] = str_replace('\\', '/', substr($file, strlen($root) + 1));
}
foreach (glob($root . '/.github/instructions/*.md') ?: [] as $file) {
    $adapterFiles[] = str_replace('\\', '/', substr($file, strlen($root) + 1));
}
$opencodeRoot = $root . '/.opencode';
if (is_dir($opencodeRoot)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($opencodeRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $entry) {
        if ($entry->isFile() && str_ends_with($entry->getFilename(), '.md')) {
            $path = str_replace('\\', '/', $entry->getPathname());
            if (str_contains($path, '/node_modules/') || str_contains($path, '/agents-optional/')) {
                continue;
            }
            $adapterFiles[] = str_replace('\\', '/', substr($path, strlen($root) + 1));
        }
    }
}

$adapterFiles = array_values(array_unique($adapterFiles));

if ($changedOnly) {
    $baseRef = getenv('GITHUB_BASE_REF');
    if (!is_string($baseRef) || $baseRef === '') {
        $baseRef = 'main';
    }

    $changedOutput = [];
    $exitCode = 0;
    exec('git -C ' . escapeshellarg($root) . ' diff --name-only ' . escapeshellarg($baseRef . '...HEAD'), $changedOutput, $exitCode);
    if ($exitCode === 0) {
        $changedSet = [];
        foreach ($changedOutput as $changedPath) {
            $changedSet[str_replace('\\', '/', trim($changedPath))] = true;
        }

        $adapterFiles = array_values(array_filter($adapterFiles, static function (string $path) use ($changedSet): bool {
            $normalized = str_replace('\\', '/', $path);
            return isset($changedSet[$normalized]);
        }));
    }
}

$requiredRefs = [
    'docs/ai/project-context.md',
    'docs/ai/workflow.md',
    'docs/ai/AI-GUARDRAILS.md',
];

$errors = [];
$warnings = [];

foreach ($adapterFiles as $relativePath) {
    $absolutePath = $root . '/' . $relativePath;
    if (!is_file($absolutePath)) {
        continue;
    }

    $content = file_get_contents($absolutePath);
    if (!is_string($content)) {
        $errors[] = "unable to read {$relativePath}";
        continue;
    }

    foreach ($requiredRefs as $ref) {
        if (strpos($content, $ref) === false) {
            $warnings[] = "{$relativePath} should reference {$ref}";
        }
    }

    if (preg_match('/^# .*Workflow/m', $content) === 1 && substr_count($content, "\n") > 220) {
        $warnings[] = "{$relativePath} looks too large for an adapter surface";
    }

    foreach (['Statamic', 'Nuxt', 'rabbies', 'headless-cms'] as $banned) {
        if (stripos($content, $banned) !== false) {
            $warnings[] = "{$relativePath} contains non-agnostic term '{$banned}'";
        }
    }
}

foreach ($warnings as $warning) {
    fwrite(STDOUT, "WARN: {$warning}\n");
}
foreach ($errors as $error) {
    fwrite(STDERR, "ERROR: {$error}\n");
}

if ($errors === []) {
    fwrite(STDOUT, "OK: adapter drift validation completed\n");
}

$hasFailure = $errors !== [] || ($failOnWarn && $warnings !== []);
exit($hasFailure ? 1 : 0);
