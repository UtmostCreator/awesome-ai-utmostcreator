<?php

declare(strict_types=1);

$root = realpath(__DIR__ . '/..' . '/..');
if ($root === false) {
    fwrite(STDERR, "ERROR: repository root not found\n");
    exit(1);
}

function hasBin(string $name): bool
{
    if (PHP_OS_FAMILY === 'Windows') {
        $output = [];
        $exit = 0;
        exec('where ' . escapeshellarg($name) . ' >NUL 2>NUL', $output, $exit);
        if ($exit === 0) {
            return true;
        }
    }

    $output = [];
    $exit = 0;
    exec('command -v ' . escapeshellarg($name) . ' >/dev/null 2>&1', $output, $exit);
    return $exit === 0;
}

$strict = false;
$scope = '--all';

for ($i = 1; $i < $argc; $i++) {
    $arg = (string) $argv[$i];
    if ($arg === '--strict') {
        $strict = true;
        continue;
    }
    if ($arg === '--staged' || $arg === '--all') {
        $scope = $arg;
    }
}

$isCi = strtolower((string) getenv('CI')) === 'true' || getenv('GITHUB_ACTIONS') === 'true';
$allowNoScanner = strtolower((string) getenv('AI_ALLOW_NO_SECRET_SCANNER')) === '1'
    || strtolower((string) getenv('AI_ALLOW_NO_SECRET_SCANNER')) === 'true';

if (hasBin('gitleaks')) {
    $cmd = 'gitleaks detect --source ' . escapeshellarg($root) . ' --redact --no-banner';
    passthru($cmd, $exit);
    exit((int) $exit);
}

if (hasBin('trufflehog')) {
    $cmd = 'trufflehog git file://' . escapeshellarg($root) . ' --results=verified,unknown --fail';
    passthru($cmd, $exit);
    exit((int) $exit);
}

$message = "no secret scanner found (gitleaks/trufflehog). scope={$scope}";

if (($strict || $isCi) && !$allowNoScanner) {
    fwrite(STDERR, "ERROR: {$message}\n");
    fwrite(STDERR, "Hint: install gitleaks (see docs/ai/mandatory-tools-install.md) or set AI_ALLOW_NO_SECRET_SCANNER=1 to skip in non-publishing contexts.\n");
    exit(1);
}

fwrite(STDOUT, "WARN: {$message}\n");
exit(0);
