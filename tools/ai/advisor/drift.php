<?php

declare(strict_types=1);

require_once __DIR__ . '/registry.php';

function aiAdvisorWriteBaseline(string $root): array
{
    $dir = aiAdvisorGeneratedDir($root);
    $score = aiAdvisorReadJson($dir . DIRECTORY_SEPARATOR . 'project-scorecard.json');
    $baseline = [
        'saved_at' => gmdate('c'),
        'overall' => (int) ($score['overall'] ?? 0),
        'scores' => $score['scores'] ?? [],
    ];
    aiAdvisorWriteJson($dir . DIRECTORY_SEPARATOR . 'advisor-baseline.json', $baseline);
    return $baseline;
}

function aiAdvisorDiffBaseline(string $root): array
{
    $dir = aiAdvisorGeneratedDir($root);
    $baselinePath = $dir . DIRECTORY_SEPARATOR . 'advisor-baseline.json';
    if (!is_file($baselinePath)) {
        throw new RuntimeException('advisor baseline not found; run advisor --baseline first');
    }
    $baseline = aiAdvisorReadJson($baselinePath);
    $current = aiAdvisorReadJson($dir . DIRECTORY_SEPARATOR . 'project-scorecard.json');

    $rows = [];
    $keys = array_values(array_unique(array_merge(array_keys((array) ($baseline['scores'] ?? [])), array_keys((array) ($current['scores'] ?? [])))));
    foreach ($keys as $k) {
        $prev = (int) (($baseline['scores'][$k] ?? 0));
        $now = (int) (($current['scores'][$k] ?? 0));
        $rows[] = ['area' => $k, 'previous' => $prev, 'current' => $now, 'change' => $now - $prev];
    }

    $md = "# Advisor Drift\n\n| Area | Previous | Current | Change |\n|---|---:|---:|---:|\n";
    foreach ($rows as $row) {
        $md .= '| ' . $row['area'] . ' | ' . $row['previous'] . ' | ' . $row['current'] . ' | ' . $row['change'] . " |\n";
    }
    aiAdvisorWriteMarkdown($dir . DIRECTORY_SEPARATOR . 'advisor-drift.md', $md);

    return ['rows' => $rows, 'baseline_overall' => (int) ($baseline['overall'] ?? 0), 'current_overall' => (int) ($current['overall'] ?? 0)];
}
