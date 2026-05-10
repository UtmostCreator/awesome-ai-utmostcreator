<?php

declare(strict_types=1);

function aiRunAdvisor(string $root, array $args): int
{
    $flags = [
        'scan' => in_array('--scan', $args, true),
        'score' => in_array('--score', $args, true),
        'validate' => in_array('--validate', $args, true),
        'secret-scan' => in_array('--secret-scan', $args, true),
        'pack' => in_array('--pack', $args, true),
        'token-budget' => in_array('--token-budget', $args, true),
        'prompt' => in_array('--prompt', $args, true),
        'baseline' => in_array('--baseline', $args, true),
        'diff' => in_array('--diff', $args, true),
        'submit' => in_array('--submit', $args, true),
        'check' => in_array('--check', $args, true),
        'all' => in_array('--all', $args, true),
    ];

    if (!in_array(true, $flags, true)) {
        $flags['all'] = true;
    }

    $dir = aiAdvisorGeneratedDir($root);
    $events = [];

    if ($flags['all'] || $flags['scan']) {
        $signals = aiAdvisorScan($root);
        $events[] = ['step' => 'scan', 'tracked_files_count' => $signals['tracked_files_count'] ?? 0];
    }

    if ($flags['all'] || $flags['validate']) {
        $signals = aiAdvisorReadJson($dir . DIRECTORY_SEPARATOR . 'project-signals.json');
        $errors = aiAdvisorValidateSignals($signals);
        if ($errors !== []) {
            throw new RuntimeException('advisor validate failed: ' . implode('; ', $errors));
        }
        if (is_file($dir . DIRECTORY_SEPARATOR . 'project-scorecard.json')) {
            $score = aiAdvisorReadJson($dir . DIRECTORY_SEPARATOR . 'project-scorecard.json');
            $errors2 = aiAdvisorValidateScorecard($score);
            if ($errors2 !== []) {
                throw new RuntimeException('advisor scorecard validate failed: ' . implode('; ', $errors2));
            }
        }
        $events[] = ['step' => 'validate', 'status' => 'ok'];
    }

    if ($flags['all'] || $flags['score']) {
        $signals = aiAdvisorReadJson($dir . DIRECTORY_SEPARATOR . 'project-signals.json');
        $score = aiAdvisorScore($root, $signals);
        $events[] = ['step' => 'score', 'overall' => $score['overall'] ?? 0];
    }

    if ($flags['all'] || $flags['secret-scan']) {
        $secret = aiAdvisorSecretScan($root);
        $events[] = ['step' => 'secret-scan', 'blocked' => $secret['blocked'] ?? false, 'findings' => $secret['count'] ?? 0];
        if (!empty($secret['blocked'])) {
            $data = ['status' => 'blocked', 'reason' => 'potential secrets detected', 'events' => $events, 'findings_file' => 'docs/ai/generated/advisor-secret-findings.json'];
            $written = aiCliWriteArtifact($root, 'advisor', 'php tools/ai/ai.php advisor', $data, 'blocked', null, 'Resolve secret findings before advisor pack/submit.');
            fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
            return 1;
        }
    }

    if ($flags['all'] || $flags['pack']) {
        $pack = aiAdvisorPackContext($root);
        $events[] = ['step' => 'pack', 'file_count' => count($pack['files'] ?? [])];
    }

    if ($flags['all'] || $flags['token-budget']) {
        $budget = aiAdvisorTokenBudget($root);
        $events[] = ['step' => 'token-budget', 'tokens_estimate' => $budget['tokens_estimate'] ?? 0, 'mode' => $budget['mode'] ?? 'unknown'];
    }

    if ($flags['all'] || $flags['prompt']) {
        aiAdvisorBuildPrompt($root);
        $events[] = ['step' => 'prompt', 'status' => 'ok'];
    }

    if ($flags['baseline']) {
        $baseline = aiAdvisorWriteBaseline($root);
        $events[] = ['step' => 'baseline', 'overall' => $baseline['overall'] ?? 0];
    }

    if ($flags['diff']) {
        $diff = aiAdvisorDiffBaseline($root);
        $events[] = ['step' => 'diff', 'baseline_overall' => $diff['baseline_overall'] ?? 0, 'current_overall' => $diff['current_overall'] ?? 0];
    }

    if ($flags['submit']) {
        $provider = aiParseArg($args, 'provider') ?? 'dry-run';
        if ($provider !== 'dry-run') {
            throw new RuntimeException('advisor submit supports only --provider=dry-run in v1');
        }
        $submit = aiAdvisorSubmitDryRun($root);
        $events[] = ['step' => 'submit', 'provider' => $submit['provider'] ?? 'dry-run', 'network_called' => $submit['network_called'] ?? false];
    }

    if ($flags['check']) {
        $required = [
            $dir . DIRECTORY_SEPARATOR . 'project-signals.json',
            $dir . DIRECTORY_SEPARATOR . 'project-scorecard.json',
            $dir . DIRECTORY_SEPARATOR . 'advisor-secret-findings.json',
        ];

        $secretBlocked = false;
        $secretPath = $dir . DIRECTORY_SEPARATOR . 'advisor-secret-findings.json';
        if (is_file($secretPath)) {
            $secretData = aiAdvisorReadJson($secretPath);
            $secretBlocked = !empty($secretData['blocked']);
        }

        $promptArtifactGateReason = null;
        $canGeneratePromptArtifacts = !$secretBlocked && aiAdvisorCanGeneratePromptArtifacts($root, $promptArtifactGateReason);
        if ($canGeneratePromptArtifacts) {
            $required[] = $dir . DIRECTORY_SEPARATOR . 'advisor-token-budget.json';
            $required[] = $dir . DIRECTORY_SEPARATOR . 'advisor-context.md';
            $required[] = $dir . DIRECTORY_SEPARATOR . 'advisor-prompt.md';
        } elseif ($secretBlocked) {
            $events[] = [
                'step' => 'check',
                'secret_blocked' => true,
                'note' => 'token-budget/context/prompt outputs optional while blocked',
            ];
        } else {
            $events[] = [
                'step' => 'check',
                'prompt_artifacts_optional' => true,
                'note' => $promptArtifactGateReason ?? 'token-budget/context/prompt outputs optional until the secret-scan gate succeeds',
            ];
        }

        $missing = [];
        foreach ($required as $path) {
            if (!is_file($path)) {
                $missing[] = aiCliToRelative($root, $path);
            }
        }
        if ($missing !== []) {
            $data = ['status' => 'failed', 'mode' => 'check', 'missing' => $missing, 'events' => $events];
            $written = aiCliWriteArtifact($root, 'advisor', 'php tools/ai/ai.php advisor --check', $data, 'failed', null, 'Run advisor --all to generate missing artifacts.');
            fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
            return 1;
        }
        $signals = aiAdvisorReadJson($dir . DIRECTORY_SEPARATOR . 'project-signals.json');
        $score = aiAdvisorReadJson($dir . DIRECTORY_SEPARATOR . 'project-scorecard.json');
        $errors = array_merge(aiAdvisorValidateSignals($signals), aiAdvisorValidateScorecard($score));
        if ($errors !== []) {
            $data = ['status' => 'failed', 'mode' => 'check', 'errors' => $errors, 'events' => $events];
            $written = aiCliWriteArtifact($root, 'advisor', 'php tools/ai/ai.php advisor --check', $data, 'failed', null, 'Fix invalid advisor JSON shapes.');
            fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
            return 1;
        }
    }

    $data = [
        'status' => 'ok',
        'events' => $events,
        'outputs' => [
            'project_signals' => 'docs/ai/generated/project-signals.json',
            'project_scorecard' => 'docs/ai/generated/project-scorecard.json',
            'secret_findings' => 'docs/ai/generated/advisor-secret-findings.json',
            'token_budget' => 'docs/ai/generated/advisor-token-budget.json',
            'context' => 'docs/ai/generated/advisor-context.md',
            'prompt' => 'docs/ai/generated/advisor-prompt.md',
            'baseline' => 'docs/ai/generated/advisor-baseline.json',
            'drift' => 'docs/ai/generated/advisor-drift.md',
            'submit_dry_run' => 'docs/ai/generated/advisor-submit-dry-run.json',
        ],
    ];
    $written = aiCliWriteArtifact($root, 'advisor', 'php tools/ai/ai.php advisor', $data, 'ok', null, 'Run advisor --check to enforce deterministic advisor outputs.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}
