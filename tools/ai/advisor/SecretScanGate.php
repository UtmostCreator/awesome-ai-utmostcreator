<?php

declare(strict_types=1);

function aiAdvisorRequireCleanSecretScan(string $root): void
{
    $php = defined('PHP_BINARY') ? (string) PHP_BINARY : 'php';
    $cmd = escapeshellarg($php) . ' tools/ai/secret-scan.php --strict';
    $output = [];
    $exit = 0;
    exec('cd ' . escapeshellarg($root) . ' && ' . $cmd . ' 2>&1', $output, $exit);

    if ($exit !== 0) {
        throw new RuntimeException('advisor secret-scan gate blocked context/prompt generation');
    }
}

function aiAdvisorCanGeneratePromptArtifacts(string $root, ?string &$reason = null): bool
{
    try {
        aiAdvisorRequireCleanSecretScan($root);
        return true;
    } catch (RuntimeException $exception) {
        $reason = $exception->getMessage();
        return false;
    }
}
