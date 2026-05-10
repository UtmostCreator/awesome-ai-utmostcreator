<?php

declare(strict_types=1);

require_once __DIR__ . '/registry.php';
require_once __DIR__ . '/SecretScanGate.php';

function aiAdvisorBuildPrompt(string $root): string
{
    aiAdvisorRequireCleanSecretScan($root);

    $dir = aiAdvisorGeneratedDir($root);
    $signals = aiAdvisorReadJson($dir . DIRECTORY_SEPARATOR . 'project-signals.json');
    $score = aiAdvisorReadJson($dir . DIRECTORY_SEPARATOR . 'project-scorecard.json');
    $budget = aiAdvisorReadJson($dir . DIRECTORY_SEPARATOR . 'advisor-token-budget.json');

    $md = "# Advisor Prompt\n\n";
    $md .= "Use attached repo evidence to provide deterministic recommendations.\n\n";
    $md .= "## Inputs\n\n";
    $md .= "- tracked files: `" . (int) ($signals['tracked_files_count'] ?? 0) . "`\n";
    $md .= "- overall score: `" . (int) ($score['overall'] ?? 0) . "`\n";
    $md .= "- token mode: `" . (string) ($budget['mode'] ?? 'unknown') . "`\n\n";
    $md .= "## Required Output\n\n";
    $md .= "## Executive verdict\n\n";
    $md .= "## Scorecard\n\n";
    $md .= "| Area | Score | Reason |\n|---|---:|---|\n\n";
    $md .= "## Recommended AI architecture\n\n";
    $md .= "## Existing files to modify\n\n";
    $md .= "## High-risk areas requiring tests\n\n";
    $md .= "## What not to build\n\n";
    $md .= "## Next 5 actions\n\n";
    $md .= "Do not recommend generic agents/prompts without evidence. Prefer improving existing surfaces first.\n";

    aiAdvisorWriteMarkdown($dir . DIRECTORY_SEPARATOR . 'advisor-prompt.md', $md);
    return $md;
}
