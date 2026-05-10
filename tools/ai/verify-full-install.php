<?php

declare(strict_types=1);

$root = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
if ($root === false) {
    fwrite(STDERR, "ERROR: unable to resolve repository root\n");
    exit(1);
}

$steps = [
    ['id' => 'preflight', 'command' => 'php tools/ai/ai.php preflight', 'artifact' => 'docs/ai/generated/preflight.json', 'goal' => 'Installer prerequisites are ready.'],
    ['id' => 'package-verify', 'command' => 'php tools/ai/ai.php package-verify', 'artifact' => 'docs/ai/generated/package-verify.json', 'goal' => 'Template package lock is valid.'],
    ['id' => 'adapter-plan', 'command' => 'php tools/ai/ai.php adapter-plan --profile full-governance --mode safe-merge --force --allow-core-overwrite --reinstall', 'artifact' => 'docs/ai/generated/adapter-plan.json', 'goal' => 'Install plan is deterministic and conflict-aware.'],
    ['id' => 'install-dry-run', 'command' => 'php tools/ai/ai.php install --profile full-governance --mode safe-merge --force --allow-core-overwrite --reinstall --dry-run', 'artifact' => 'docs/ai/generated/install.json', 'goal' => 'Install workflow is planned before apply.'],
    ['id' => 'validate-config', 'command' => 'php tools/ai/validate-ai-config.php', 'artifact' => '', 'goal' => 'AI config references and workflow checks are valid.'],
    ['id' => 'validate-catalog', 'command' => 'php tools/ai/validate-ai-catalog.php', 'artifact' => '', 'goal' => 'Catalog metadata is consistent.'],
    ['id' => 'catalog-check', 'command' => 'php tools/ai/generate-ai-catalog.php --check', 'artifact' => '', 'goal' => 'Catalog outputs are up to date.'],
    ['id' => 'repomix-analyze', 'command' => 'bash scripts/ai/repomix-context-tree.sh analyze .', 'artifact' => '.repomix-context/tree-context/tree-plan.json', 'goal' => 'Repository structure/context signals are generated.'],
    ['id' => 'advisor-all', 'command' => 'php tools/ai/ai.php advisor --all', 'artifact' => 'docs/ai/generated/advisor.json', 'goal' => 'Advisor analyzes repo and suggests fixes.'],
    ['id' => 'verify-changed', 'command' => 'php tools/ai/ai.php verify --changed', 'artifact' => 'docs/ai/generated/verify.json', 'goal' => 'Changed-scope verification summary is current.'],
];

$phpBin = PHP_BINARY;
if ($phpBin === '') {
    $phpBin = 'php';
}

function runStep(string $root, string $command): array
{
    $descriptor = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptor, $pipes, $root);
    if (!is_resource($process)) {
        return ['exit' => 1, 'stdout' => '', 'stderr' => 'failed to start process'];
    }

    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);

    return ['exit' => (int) $exit, 'stdout' => $stdout, 'stderr' => $stderr];
}

function normalizeCommandForRuntime(string $command, string $phpBin): string
{
    if (str_starts_with($command, 'php ')) {
        return escapeshellarg($phpBin) . substr($command, 3);
    }
    return $command;
}

function readArtifactStatus(string $root, string $relativePath): string
{
    if ($relativePath === '') {
        return 'not-applicable';
    }
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($path)) {
        return 'missing';
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return 'present';
    }
    return (string) ($decoded['status'] ?? 'unknown');
}

$results = [];
$failures = [];

foreach ($steps as $index => $step) {
    $command = normalizeCommandForRuntime((string) $step['command'], $phpBin);
    $run = runStep($root, $command);
    $artifactStatus = readArtifactStatus($root, (string) $step['artifact']);
    $ok = $run['exit'] === 0;
    $results[] = [
        'order' => $index + 1,
        'id' => $step['id'],
        'command' => $command,
        'goal' => $step['goal'],
        'exit' => $run['exit'],
        'artifact' => $step['artifact'],
        'artifact_status' => $artifactStatus,
        'ok' => $ok,
    ];
    if (!$ok) {
        $failures[] = $step['id'];
    }
}

$full = $failures === [];

$next = [];
if (!$full) {
    $next[] = '1) Re-run failed step(s) in listed order.';
    $next[] = '2) If advisor is blocked, review docs/ai/generated/advisor-secret-findings.json.';
    $next[] = '3) Re-run: php tools/ai/ai.php verify --changed.';
} else {
    $next[] = '1) Install state is full for this verification sequence.';
    $next[] = '2) Optionally run: php tools/ai/ai.php next.';
}

$report = [
    'status' => $full ? 'full' : 'partial',
    'generated_at' => gmdate('c'),
    'root' => $root,
    'steps' => $results,
    'failed_steps' => $failures,
    'recommended_next_steps' => $next,
];

$generatedDir = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'generated';
if (!is_dir($generatedDir)) {
    mkdir($generatedDir, 0777, true);
}

$jsonPath = $generatedDir . DIRECTORY_SEPARATOR . 'full-install-verify.json';
$mdPath = $generatedDir . DIRECTORY_SEPARATOR . 'full-install-verify.md';

file_put_contents($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

$md = "# Full Install Verify\n\n";
$md .= "- Status: `" . $report['status'] . "`\n";
$md .= "- Generated at: `" . $report['generated_at'] . "`\n\n";
$md .= "## Executed Steps\n\n";
foreach ($results as $row) {
    $md .= "- Step " . $row['order'] . " (`" . $row['id'] . "`): `" . $row['command'] . "` -> exit `" . $row['exit'] . "`, artifact status `" . $row['artifact_status'] . "`\n";
    $md .= "  - Goal: " . $row['goal'] . "\n";
}

$md .= "\n## Recommended Next Steps\n\n";
foreach ($next as $line) {
    $md .= "- {$line}\n";
}

file_put_contents($mdPath, $md);

fwrite(STDOUT, "OK: wrote docs/ai/generated/full-install-verify.json and docs/ai/generated/full-install-verify.md\n");
exit($full ? 0 : 1);
