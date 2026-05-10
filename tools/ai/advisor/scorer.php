<?php

declare(strict_types=1);

require_once __DIR__ . '/registry.php';

function aiAdvisorScore(string $root, array $signals): array
{
    $counts = is_array($signals['counts'] ?? null) ? $signals['counts'] : [];
    $toolchain = is_array($signals['toolchain'] ?? null) ? $signals['toolchain'] : [];
    $aiSurface = is_array($signals['ai_surface'] ?? null) ? $signals['ai_surface'] : [];

    $aiSurfaceScore = min(100, 20 + (int) (array_sum(array_map(static fn($v): int => $v ? 1 : 0, $aiSurface)) * 20));
    $testScore = min(100, ((int) ($counts['tests_php'] ?? 0)) * 8 + ((int) ($counts['tests_shell'] ?? 0)) * 15);
    $scriptCount = (int) ($counts['scripts_copilot'] ?? 0) + (int) ($counts['scripts_ai'] ?? 0);
    $scriptSafetyScore = min(100, 20 + $scriptCount * 3);
    $toolReady = min(100, (int) (array_sum(array_map(static fn($v): int => $v ? 1 : 0, $toolchain)) * (100 / max(1, count($toolchain)))));
    $complexityRisk = max(0, 100 - ((int) ($counts['tools_ai_php'] ?? 0) * 2));
    $docHygiene = is_file($root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'generated' . DIRECTORY_SEPARATOR . 'artifacts.json') ? 85 : 50;

    $scorecard = [
        'schema_version' => 1,
        'scores' => [
            'ai_surface_coverage' => $aiSurfaceScore,
            'test_readiness' => $testScore,
            'script_safety' => $scriptSafetyScore,
            'toolchain_readiness' => $toolReady,
            'complexity_risk' => $complexityRisk,
            'generated_doc_hygiene' => $docHygiene,
        ],
    ];
    $scorecard['overall'] = (int) round(array_sum($scorecard['scores']) / max(1, count($scorecard['scores'])));

    $dir = aiAdvisorGeneratedDir($root);
    aiAdvisorWriteJson($dir . DIRECTORY_SEPARATOR . 'project-scorecard.json', $scorecard);

    $md = "# Project Scorecard\n\n";
    $md .= "| Area | Score |\n|---|---:|\n";
    foreach ($scorecard['scores'] as $k => $v) {
        $md .= "| {$k} | {$v} |\n";
    }
    $md .= "\n- Overall: `" . $scorecard['overall'] . "`\n";
    aiAdvisorWriteMarkdown($dir . DIRECTORY_SEPARATOR . 'project-scorecard.md', $md);

    $focus = "# Repo Focus Map\n\n";
    $focus .= "1. tools/ai/**\n2. tools/ai/install/**\n3. scripts/ai/**\n4. scripts/ai/**\n5. docs/ai/installer-architecture.md\n6. tests/**\n";
    aiAdvisorWriteMarkdown($dir . DIRECTORY_SEPARATOR . 'repo-focus-map.md', $focus);

    return $scorecard;
}
