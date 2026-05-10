<?php

declare(strict_types=1);

// Split into focused modules — this file now only contains:
// aiRunAsk, aiRunEstimate, aiRunImpact, aiRunFreshness, aiRunBudget

function aiRunAsk(string $root, array $args): int
{
    $resolveId = aiParseArg($args, 'resolve');
    if ($resolveId !== null && $resolveId !== '') {
        $answer = aiParseArg($args, 'answer');
        if ($answer === null || $answer === '') {
            throw new RuntimeException('ask --resolve requires --answer');
        }

        $existing = aiLoadArtifactData($root, 'ask.json');
        if ($existing === null) {
            throw new RuntimeException('No existing ask artifact found to resolve');
        }

        $existingData = $existing['data'] ?? [];
        if (!is_array($existingData)) {
            throw new RuntimeException('Malformed ask artifact data');
        }

        $currentId = (string) ($existingData['question_id'] ?? '');
        if ($currentId === '' || $currentId !== $resolveId) {
            throw new RuntimeException('ask --resolve did not match current question_id');
        }

        $resolvedData = $existingData;
        $resolvedData['status'] = 'resolved';
        $resolvedData['resolved_at_utc'] = gmdate('c');
        $resolvedData['answer'] = $answer;
        $resolvedData['resolution_mode'] = 'explicit-answer';

        $written = aiCliWriteArtifact($root, 'ask', 'php tools/ai/ai.php ask --resolve ' . $resolveId . ' --answer ' . $answer, $resolvedData, 'ok', null, 'Clarification resolved; rerun next to continue orchestration.');
        fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
        return 0;
    }

    $question = $args[0] ?? '';
    if ($question === '') {
        throw new RuntimeException('ask requires a question as the first positional argument');
    }

    $optionsRaw = aiParseArg($args, 'options') ?? '';
    $options = $optionsRaw === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $optionsRaw)), static fn(string $v): bool => $v !== ''));
    $default = aiParseArg($args, 'default') ?? ($options[0] ?? 'unknown');
    $whyNeeded = aiParseArg($args, 'why-needed') ?? 'Decision ambiguity materially changes implementation direction.';
    $blocksRaw = aiParseArg($args, 'blocks') ?? 'next';
    $blocks = array_values(array_filter(array_map('trim', explode(',', $blocksRaw)), static fn(string $v): bool => $v !== ''));

    $questionId = 'q-' . gmdate('Ymd-His') . '-' . substr(md5($question), 0, 6);
    $data = [
        'status' => 'blocked',
        'question_id' => $questionId,
        'question' => $question,
        'options' => $options,
        'why_needed' => $whyNeeded,
        'default_if_unanswered' => $default,
        'blocks' => $blocks,
    ];

    $written = aiCliWriteArtifact($root, 'ask', 'php tools/ai/ai.php ask', $data, 'blocked', null, 'Resolve this question before relying on next for safe action sequencing.');
    fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
    return 0;
}

function aiRunEstimate(string $root, array $args): int
{
    $task = trim(implode(' ', $args));
    if ($task === '') {
        throw new RuntimeException('estimate requires a task description');
    }

    $keywords = [
        'risk' => ['auth', 'billing', 'security', 'migration', 'installer', 'policy', 'hook'],
        'scope' => ['multi', 'cross', 'adapter', 'catalog', 'workflow', 'generated', 'package'],
    ];

    $riskScore = 20;
    $complexity = 2;
    $taskLower = strtolower($task);

    foreach ($keywords['risk'] as $word) {
        if (str_contains($taskLower, $word)) {
            $riskScore += 10;
            $complexity += 1;
        }
    }
    foreach ($keywords['scope'] as $word) {
        if (str_contains($taskLower, $word)) {
            $riskScore += 6;
            $complexity += 1;
        }
    }

    $riskScore = min(100, $riskScore);
    $complexity = min(10, $complexity);
    $level = $riskScore >= 70 ? 'high' : ($riskScore >= 40 ? 'medium' : 'low');

    $data = [
        'task' => $task,
        'complexity' => $complexity,
        'risk_score' => $riskScore,
        'risk_level' => $level,
        'suggested_first_step' => 'php tools/ai/ai.php context --task "' . addslashes($task) . '"',
        'recommended_next_action' => 'php tools/ai/ai.php diff-summary --base main',
    ];

    $written = aiCliWriteArtifact($root, 'estimate', 'php tools/ai/ai.php estimate', $data, 'ok', $riskScore, 'Use context + diff-summary before implementation.');
    fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
    return 0;
}

function aiRunImpact(string $root, array $args): int
{
    $base = aiParseArg($args, 'base') ?? 'main';
    $changed = [];
    exec('git -C ' . escapeshellarg($root) . ' diff --name-only ' . escapeshellarg($base) . '...HEAD', $changed);

    $areas = [];
    $tests = [];
    foreach ($changed as $path) {
        if (str_starts_with($path, 'tools/ai/')) {
            $areas['ai-tooling'] = true;
            $tests[] = 'php tools/ai/validate-ai-config.php';
            $tests[] = 'php tools/ai/validate-ai-catalog.php';
        }
        if (str_starts_with($path, 'scripts/')) {
            $areas['automation-scripts'] = true;
            $tests[] = 'bash scripts/doctor.sh';
        }
        if (str_starts_with($path, 'docs/ai/')) {
            $areas['ai-docs'] = true;
            $tests[] = 'php tools/ai/generate-ai-catalog.php --check';
        }
        if (str_starts_with($path, 'packages/ai-universal-rules/')) {
            $areas['package-assets'] = true;
            $tests[] = 'php tools/ai/validate-ai-catalog.php';
        }
        if (str_starts_with($path, '.github/')) {
            $areas['copilot-adapter'] = true;
            $tests[] = 'bash scripts/doctor.sh';
        }
    }

    $areaList = array_keys($areas);
    sort($areaList);
    $tests = array_values(array_unique($tests));

    $impactScore = min(100, (count($areaList) * 18) + (count($changed) > 15 ? 20 : count($changed)));
    $data = [
        'base' => $base,
        'changed_files_count' => count($changed),
        'changed_files' => $changed,
        'likely_affected_areas' => $areaList,
        'related_checks' => $tests,
        'impact_score' => $impactScore,
    ];

    $written = aiCliWriteArtifact($root, 'impact', 'php tools/ai/ai.php impact --base ' . $base, $data, 'ok', $impactScore, 'Run related checks before merge or handoff.');
    fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
    return 0;
}

function aiRunFreshness(string $root): int
{
    $generatedDir = aiCliGeneratedDir($root);
    $registry = aiCliLoadArtifactsRegistry($generatedDir);
    $current = aiCliCurrentCommit($root);

    $entries = [];
    $staleCount = 0;
    $artifacts = $registry['artifacts'] ?? [];
    if (!is_array($artifacts)) {
        $artifacts = [];
    }

    foreach ($artifacts as $name => $meta) {
        if (!is_array($meta)) {
            continue;
        }
        $basedOn = (string) ($meta['based_on_commit'] ?? 'unknown');
        $isStale = $basedOn !== 'unknown' && $current !== 'unknown' && $basedOn !== $current;
        if ($isStale) {
            $staleCount++;
        }
        $entries[] = [
            'artifact' => $name,
            'based_on_commit' => $basedOn,
            'current_commit' => $current,
            'stale' => $isStale,
            'recommendation' => $isStale ? 'Regenerate this artifact before using next.' : 'Current at HEAD.',
        ];
    }

    $status = $staleCount > 0 ? 'warning' : 'ok';
    $recommended = $staleCount > 0 ? 'php tools/ai/ai.php snapshot' : 'php tools/ai/ai.php next';

    $data = [
        'status' => $status,
        'stale_count' => $staleCount,
        'artifact_count' => count($entries),
        'artifacts' => $entries,
    ];

    $written = aiCliWriteArtifact($root, 'freshness', 'php tools/ai/ai.php freshness', $data, $status, null, $recommended);
    fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
    return 0;
}

function aiRunBudget(string $root, array $args): int
{
    $contextWindow = 32000;
    $artifactFilter = null;

    for ($i = 0; $i < count($args); $i++) {
        $arg = $args[$i];
        if ($arg === '--context-window') {
            $contextWindow = (int) ($args[$i + 1] ?? $contextWindow);
            $i++;
            continue;
        }
        if (str_starts_with($arg, '--context-window=')) {
            $contextWindow = (int) substr($arg, 17);
            continue;
        }
        if ($arg === '--artifact') {
            $artifactFilter = (string) ($args[$i + 1] ?? '');
            $i++;
            continue;
        }
        if (str_starts_with($arg, '--artifact=')) {
            $artifactFilter = substr($arg, 11);
            continue;
        }
    }

    $registry = aiCliLoadArtifactsRegistry(aiCliGeneratedDir($root));
    $artifacts = $registry['artifacts'] ?? [];
    if (!is_array($artifacts)) {
        $artifacts = [];
    }

    $items = [];
    $total = 0;
    foreach ($artifacts as $name => $meta) {
        if (!is_array($meta)) {
            continue;
        }
        if ($artifactFilter !== null && $artifactFilter !== '' && $artifactFilter !== $name) {
            continue;
        }
        $tokens = (int) ($meta['estimated_tokens'] ?? 0);
        $items[] = [
            'artifact' => $name,
            'estimated_tokens' => $tokens,
            'stale' => (bool) ($meta['stale'] ?? false),
        ];
        $total += $tokens;
    }

    usort($items, static fn(array $a, array $b): int => $b['estimated_tokens'] <=> $a['estimated_tokens']);

    $remaining = $contextWindow - $total;
    $status = $remaining < 0 ? 'warning' : 'ok';
    $recommended = $remaining < 0
        ? 'Trim context by using smaller path- or changed-scoped artifacts before next.'
        : 'Context budget looks safe for a focused next step.';

    $data = [
        'context_window' => $contextWindow,
        'estimated_total_tokens' => $total,
        'remaining_tokens' => $remaining,
        'artifact_count' => count($items),
        'artifacts' => $items,
    ];

    $written = aiCliWriteArtifact($root, 'budget', 'php tools/ai/ai.php budget', $data, $status, null, $recommended);
    fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
    return 0;
}
