<?php

declare(strict_types=1);

function aiAdvisorValidateSignals(array $signals): array
{
    $errors = [];
    foreach (['schema_version', 'project', 'tracked_files_count', 'counts', 'ai_surface', 'toolchain'] as $k) {
        if (!array_key_exists($k, $signals)) {
            $errors[] = 'project-signals missing: ' . $k;
        }
    }
    if (!is_array($signals['counts'] ?? null)) {
        $errors[] = 'project-signals counts must be object';
    }
    return $errors;
}

function aiAdvisorValidateScorecard(array $scorecard): array
{
    $errors = [];
    foreach (['schema_version', 'scores', 'overall'] as $k) {
        if (!array_key_exists($k, $scorecard)) {
            $errors[] = 'project-scorecard missing: ' . $k;
        }
    }
    if (!is_array($scorecard['scores'] ?? null)) {
        $errors[] = 'project-scorecard scores must be object';
    }
    return $errors;
}
