<?php

declare(strict_types=1);

$root = realpath(__DIR__ . '/..' . '/..');
if ($root === false) {
    fwrite(STDERR, "ERROR: repository root not found\n");
    exit(1);
}

$dryRun = in_array('--dry-run', $argv, true);
$scope = '.';

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--scope' && isset($argv[$i + 1])) {
        $scope = (string) $argv[$i + 1];
    }
    if (str_starts_with($argv[$i], '--scope=')) {
        $scope = (string) substr($argv[$i], 8);
    }
}

$scope = str_replace('\\', '/', trim($scope));
if ($scope === '' || str_contains($scope, '..') || str_starts_with($scope, '/')) {
    fwrite(STDERR, "ERROR: invalid scope '{$scope}'\n");
    exit(1);
}

$scopePath = realpath($root . '/' . $scope);
if ($scope !== '.' && ($scopePath === false || !str_starts_with(str_replace('\\', '/', $scopePath), str_replace('\\', '/', $root)))) {
    fwrite(STDERR, "ERROR: scope escapes repository root\n");
    exit(1);
}

$manifestDir = $root . '/.repomix-context/tree-context';
if (!is_dir($manifestDir)) {
    @mkdir($manifestDir, 0777, true);
}

$manifest = [
    'task' => 'context-pack',
    'scope' => $scope,
    'included' => [$scope],
    'excluded' => ['.env*', '.ai-logs/**', '.repomix-context/**'],
    'secret_scan' => 'unknown',
    'budget' => [
        'files' => 0,
        'estimated_tokens' => 0,
    ],
    'dry_run' => $dryRun,
    'generated_at' => gmdate('c'),
];

$manifestPath = $manifestDir . '/context-pack-manifest.json';
file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

fwrite(STDOUT, 'OK: wrote ' . $manifestPath . PHP_EOL);

if ($dryRun) {
    fwrite(STDOUT, "DRY-RUN: no pack command executed\n");
    exit(0);
}

$cmd = 'bash scripts/ai/run-repomix-context.sh .';
passthru($cmd, $exit);
exit((int) $exit);
