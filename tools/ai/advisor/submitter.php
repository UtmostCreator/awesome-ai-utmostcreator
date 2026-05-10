<?php

declare(strict_types=1);

require_once __DIR__ . '/registry.php';

function aiAdvisorSubmitDryRun(string $root): array
{
    $dir = aiAdvisorGeneratedDir($root);
    $promptPath = $dir . DIRECTORY_SEPARATOR . 'advisor-prompt.md';
    $contextPath = $dir . DIRECTORY_SEPARATOR . 'advisor-context.md';
    $prompt = is_file($promptPath) ? (string) file_get_contents($promptPath) : '';
    $context = is_file($contextPath) ? (string) file_get_contents($contextPath) : '';

    $payload = [
        'provider' => 'dry-run',
        'network_called' => false,
        'prompt_tokens_estimate' => aiAdvisorTokenEstimate($prompt),
        'context_tokens_estimate' => aiAdvisorTokenEstimate($context),
        'would_submit' => [
            'prompt_path' => 'docs/ai/generated/advisor-prompt.md',
            'context_path' => 'docs/ai/generated/advisor-context.md',
        ],
    ];
    aiAdvisorWriteJson($dir . DIRECTORY_SEPARATOR . 'advisor-submit-dry-run.json', $payload);
    return $payload;
}
