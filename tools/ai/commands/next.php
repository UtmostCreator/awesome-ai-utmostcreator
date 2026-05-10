<?php

declare(strict_types=1);

function aiRunNext(string $root): int
{
    $generatedDir = aiCliGeneratedDir($root);
    $required = ['project-snapshot.json', 'freshness.json', 'budget.json', 'workflow.json'];
    $missing = [];
    foreach ($required as $artifact) {
        if (!is_file($generatedDir . DIRECTORY_SEPARATOR . $artifact)) {
            $missing[] = $artifact;
        }
    }
    if ($missing !== []) {
        $data = [
            'status' => 'blocked',
            'reason' => 'missing required predecessor artifacts',
            'missing_artifacts' => $missing,
        ];
        $written = aiCliWriteArtifact($root, 'next', 'php tools/ai/ai.php next', $data, 'blocked', null, 'Run snapshot, freshness, budget, and workflow first.');
        fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
        return 1;
    }

    $stale = aiEvaluateStaleEntries($root);
    if ($stale !== []) {
        $artifact = $stale[0];
        $baseName = pathinfo($artifact, PATHINFO_FILENAME);
        $data = [
            'status' => 'blocked',
            'reason' => 'stale artifacts detected',
            'stale_artifacts' => $stale,
            'next_action' => 'php tools/ai/ai.php ' . $baseName,
        ];
        $written = aiCliWriteArtifact($root, 'next', 'php tools/ai/ai.php next', $data, 'blocked', null, 'Regenerate stale artifact before continuing.');
        fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
        return 1;
    }

    $preflight = aiLoadArtifactData($root, 'preflight.json');
    if ($preflight !== null && ($preflight['data']['status'] ?? 'unknown') === 'failed') {
        $data = [
            'status' => 'blocked',
            'reason' => 'installer preflight failed',
            'next_action' => 'php tools/ai/ai.php preflight',
        ];
        $written = aiCliWriteArtifact($root, 'next', 'php tools/ai/ai.php next', $data, 'blocked', null, 'Fix preflight failures before install/apply commands.');
        fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
        return 1;
    }

    $packageVerify = aiLoadArtifactData($root, 'package-verify.json');
    if ($packageVerify !== null && ($packageVerify['status'] ?? 'unknown') === 'failed') {
        $data = [
            'status' => 'blocked',
            'reason' => 'source package integrity mismatch',
            'next_action' => 'php tools/ai/ai.php package-lock --update && php tools/ai/ai.php package-verify',
        ];
        $written = aiCliWriteArtifact($root, 'next', 'php tools/ai/ai.php next', $data, 'blocked', null, 'Resolve package checksum drift before installation changes.');
        fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
        return 1;
    }

    $env = aiLoadArtifactData($root, 'env-check.json');
    if ($env !== null) {
        $missingRequired = $env['data']['missing_required'] ?? [];
        if (is_array($missingRequired) && $missingRequired !== []) {
            $data = [
                'status' => 'blocked',
                'reason' => 'environment missing required tooling',
                'missing_required' => $missingRequired,
                'next_action' => 'Install required tools then rerun env-check and rebase-state.',
            ];
            $written = aiCliWriteArtifact($root, 'next', 'php tools/ai/ai.php next', $data, 'blocked', null, 'Run env-check after installing missing tools.');
            fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
            return 1;
        }
    }

    $ask = aiLoadArtifactData($root, 'ask.json');
    if ($ask !== null) {
        $askStatus = (string) ($ask['data']['status'] ?? '');
        if ($askStatus === 'blocked') {
            $data = [
                'status' => 'blocked',
                'reason' => 'open clarification question',
                'question_id' => $ask['data']['question_id'] ?? 'unknown',
                'question' => $ask['data']['question'] ?? 'unknown',
                'next_action' => 'Answer blocked question or accept documented default path.',
            ];
            $written = aiCliWriteArtifact($root, 'next', 'php tools/ai/ai.php next', $data, 'blocked', null, 'Resolve ask artifact before proceeding.');
            fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
            return 1;
        }
    }

    $budget = json_decode((string) file_get_contents($generatedDir . DIRECTORY_SEPARATOR . 'budget.json'), true);
    $remaining = (int) ($budget['data']['remaining_tokens'] ?? 0);
    if ($remaining < 0) {
        $data = [
            'status' => 'warning',
            'reason' => 'context budget exceeded',
            'remaining_tokens' => $remaining,
            'next_action' => 'php tools/ai/ai.php budget --context-window 64000',
        ];
        $written = aiCliWriteArtifact($root, 'next', 'php tools/ai/ai.php next', $data, 'warning', null, 'Reduce context scope before proceeding.');
        fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
        return 0;
    }

    $autoFix = aiLoadArtifactData($root, 'auto-fix.json');
    if ($autoFix !== null) {
        $safeFixes = $autoFix['data']['safe_fixes'] ?? [];
        if (is_array($safeFixes) && $safeFixes !== []) {
            $data = [
                'status' => 'warning',
                'reason' => 'safe auto-fix opportunities detected',
                'safe_fix_count' => count($safeFixes),
                'next_action' => 'Review auto-fix --dry-run suggestions and apply deterministic regen commands.',
            ];
            $written = aiCliWriteArtifact($root, 'next', 'php tools/ai/ai.php next', $data, 'warning', null, 'Apply safe fixes then rerun rebase-state.');
            fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
            return 0;
        }
    }

    $verifyPath = $generatedDir . DIRECTORY_SEPARATOR . 'verify.json';
    if (is_file($verifyPath)) {
        $verify = json_decode((string) file_get_contents($verifyPath), true);
        $verifyStatus = (string) ($verify['status'] ?? 'unknown');
        if ($verifyStatus === 'failed') {
            $failedChecks = $verify['data']['failed_checks'] ?? [];
            $first = is_array($failedChecks) && $failedChecks !== [] ? (string) $failedChecks[0] : 'unknown';
            $data = [
                'status' => 'blocked',
                'reason' => 'verification failed',
                'failed_check' => $first,
                'next_action' => 'Inspect docs/ai/generated/logs from verify output and fix the first failure.',
            ];
            $written = aiCliWriteArtifact($root, 'next', 'php tools/ai/ai.php next', $data, 'blocked', null, 'Fix verify failures before commit or PR steps.');
            fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
            return 1;
        }
    }

    $riskPath = $generatedDir . DIRECTORY_SEPARATOR . 'risk.json';
    if (is_file($riskPath)) {
        $risk = json_decode((string) file_get_contents($riskPath), true);
        $level = (string) ($risk['data']['risk_level'] ?? 'low');
        if ($level === 'high' && !is_file($verifyPath)) {
            $data = [
                'status' => 'blocked',
                'reason' => 'high risk change without verify evidence',
                'next_action' => 'php tools/ai/ai.php verify --changed',
            ];
            $written = aiCliWriteArtifact($root, 'next', 'php tools/ai/ai.php next', $data, 'blocked', null, 'Run verify for high risk changes.');
            fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
            return 1;
        }
    }

    $impact = aiLoadArtifactData($root, 'impact.json');
    if ($impact !== null) {
        $impactScore = (int) ($impact['data']['impact_score'] ?? 0);
        if ($impactScore >= 70 && !is_file($verifyPath)) {
            $data = [
                'status' => 'blocked',
                'reason' => 'high impact change without verify evidence',
                'impact_score' => $impactScore,
                'next_action' => 'php tools/ai/ai.php verify --changed',
            ];
            $written = aiCliWriteArtifact($root, 'next', 'php tools/ai/ai.php next', $data, 'blocked', null, 'Run verify for high impact changes.');
            fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
            return 1;
        }
    }

    $logs = aiLoadArtifactData($root, 'logs.json');
    if ($logs !== null && is_file($verifyPath)) {
        $verify = json_decode((string) file_get_contents($verifyPath), true);
        $verifyStatus = (string) ($verify['status'] ?? 'unknown');
        if ($verifyStatus === 'failed') {
            $entries = $logs['data']['entries'] ?? [];
            $data = [
                'status' => 'blocked',
                'reason' => 'verification failed; logs available for drill-down',
                'log_entries' => is_array($entries) ? $entries : [],
                'next_action' => 'php tools/ai/ai.php logs <verify-run-dir>',
            ];
            $written = aiCliWriteArtifact($root, 'next', 'php tools/ai/ai.php next', $data, 'blocked', null, 'Inspect logs and fix first failure.');
            fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
            return 1;
        }
    }

    $data = [
        'status' => 'ok',
        'reason' => 'all required workflow-control artifacts are fresh and valid',
        'next_action' => 'Prepare commit message or PR summary from current diff.',
        'recommended_commands' => [
            'php tools/ai/ai.php diff-summary --base main',
            'php tools/ai/ai.php risk --base main',
            'php tools/ai/ai.php verify --changed',
        ],
    ];

    $written = aiCliWriteArtifact($root, 'next', 'php tools/ai/ai.php next', $data, 'ok', null, 'Proceed to commit-msg or pr-summary in the next phase.');
    fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
    return 0;
}
