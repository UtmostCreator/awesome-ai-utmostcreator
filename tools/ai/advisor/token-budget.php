<?php

declare(strict_types=1);

require_once __DIR__ . '/registry.php';

function aiAdvisorTokenBudget(string $root, int $soft = 120000, int $hard = 180000): array
{
    $dir = aiAdvisorGeneratedDir($root);
    $contextPath = $dir . DIRECTORY_SEPARATOR . 'advisor-context.md';
    $content = is_file($contextPath) ? (string) file_get_contents($contextPath) : '';
    $tokens = aiAdvisorTokenEstimate($content);
    $mode = 'ok';
    if ($tokens > $hard) {
        $mode = 'hard_limit_exceeded';
    } elseif ($tokens > $soft) {
        $mode = 'soft_limit_exceeded';
    }

    $out = [
        'tokens_estimate' => $tokens,
        'soft_budget_tokens' => $soft,
        'hard_budget_tokens' => $hard,
        'mode' => $mode,
        'chunking_required' => $tokens > $soft,
    ];
    aiAdvisorWriteJson($dir . DIRECTORY_SEPARATOR . 'advisor-token-budget.json', $out);
    return $out;
}
