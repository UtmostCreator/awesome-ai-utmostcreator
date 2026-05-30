<?php

declare(strict_types=1);

function aiGitLines(string $root, string $args): array
{
    $result = aiRunCommand(
        $root,
        'git -C ' . escapeshellarg($root) . ' ' . $args
    );

    $lines = preg_split('/\R/', $result['stdout']) ?: [];
    return array_values(array_filter(array_map(static fn(string $line): string => trim($line), $lines), static fn(string $line): bool => $line !== ''));
}

function aiRunDiffSummary(string $root, array $args): int
{
    $base = 'main';
    for ($i = 0; $i < count($args); $i++) {
        $arg = $args[$i];
        if ($arg === '--base') {
            $base = (string) ($args[$i + 1] ?? $base);
            $i++;
            continue;
        }
        if (str_starts_with($arg, '--base=')) {
            $base = (string) substr($arg, 7);
        }
    }

    $changed = aiGitLines($root, 'diff --name-only ' . escapeshellarg($base) . '...HEAD');
    $staged = aiGitLines($root, 'diff --name-only --cached');
    $unstaged = aiGitLines($root, 'diff --name-only');

    $classify = static function (string $path): string {
        if (str_starts_with($path, 'docs/')) {
            return 'docs';
        }
        if (str_starts_with($path, 'scripts/')) {
            return 'script';
        }
        if (str_starts_with($path, 'tools/')) {
            return 'tool';
        }
        if (str_starts_with($path, '.github/')) {
            return 'adapter';
        }
        if (str_starts_with($path, 'packages/')) {
            return 'package';
        }
        return 'other';
    };

    $byType = [];
    foreach ($changed as $path) {
        $type = $classify($path);
        if (!isset($byType[$type])) {
            $byType[$type] = [];
        }
        $byType[$type][] = $path;
    }

    $data = [
        'base' => $base,
        'changed_files_count' => count($changed),
        'changed_files' => $changed,
        'staged_files_count' => count($staged),
        'unstaged_files_count' => count($unstaged),
        'changed_by_type' => $byType,
    ];

    $written = aiCliWriteArtifact(
        $root,
        'diff-summary',
        'php tools/ai/ai.php diff-summary --base ' . $base,
        $data,
        'ok',
        null,
        'Run risk and verify on this diff.'
    );
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

function aiRunRisk(string $root, array $args): int
{
    $base = 'main';
    for ($i = 0; $i < count($args); $i++) {
        $arg = $args[$i];
        if ($arg === '--base') {
            $base = (string) ($args[$i + 1] ?? $base);
            $i++;
            continue;
        }
        if (str_starts_with($arg, '--base=')) {
            $base = (string) substr($arg, 7);
        }
    }

    $changed = aiGitLines($root, 'diff --name-only ' . escapeshellarg($base) . '...HEAD');

    $score = 0;
    $reasons = [];
    foreach ($changed as $path) {
        if (str_starts_with($path, 'scripts/ai/pre-tool-use.sh')) {
            $score += 30;
            $reasons[] = 'command approval policy changed';
            continue;
        }
        if (str_starts_with($path, 'tools/ai/install-ai-kit.php') || str_starts_with($path, 'tools/ai/install-copilot-kit.sh') || str_starts_with($path, 'tools/ai/install-opencode-kit.sh')) {
            $score += 25;
            $reasons[] = 'installer behavior changed';
            continue;
        }
        if (str_starts_with($path, 'schemas/ai/')) {
            $score += 20;
            $reasons[] = 'schema contract changed';
            continue;
        }
        if (str_starts_with($path, 'docs/ai/generated/')) {
            $score += 10;
            $reasons[] = 'generated output touched';
            continue;
        }
        if (str_starts_with($path, 'docs/ai/')) {
            $score += 8;
            $reasons[] = 'ai workflow docs changed';
            continue;
        }
        if (str_starts_with($path, 'packages/ai-universal-rules/manifest.json')) {
            $score += 20;
            $reasons[] = 'package manifest changed';
            continue;
        }
        $score += 3;
    }

    $score = min(100, $score);
    $level = $score >= 70 ? 'high' : ($score >= 35 ? 'medium' : 'low');

    $data = [
        'base' => $base,
        'risk_score' => $score,
        'risk_level' => $level,
        'changed_files_count' => count($changed),
        'risk_reasons' => array_values(array_unique($reasons)),
    ];

    $written = aiCliWriteArtifact(
        $root,
        'risk',
        'php tools/ai/ai.php risk --base ' . $base,
        $data,
        'ok',
        $score,
        'Run verify to validate this risk posture with command evidence.'
    );
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

function aiRunRebaseState(string $root): int
{
    $commands = [
        'php tools/ai/ai.php snapshot',
        'php tools/ai/ai.php diff-summary --base main',
        'php tools/ai/ai.php risk --base main',
        'php tools/ai/ai.php verify --changed',
        'php tools/ai/ai.php freshness',
        'php tools/ai/ai.php budget',
        'php tools/ai/ai.php next',
    ];

    $runs = [];
    foreach ($commands as $command) {
        $result = aiRunCommand($root, $command);
        $runs[] = [
            'command' => $command,
            'exit' => $result['exit'],
        ];
        if ($result['exit'] !== 0 && !str_contains($command, 'next')) {
            $data = [
                'status' => 'failed',
                'failed_command' => $command,
                'runs' => $runs,
            ];
            aiCliWriteArtifact($root, 'rebase-state', 'php tools/ai/ai.php rebase-state', $data, 'failed', null, 'Fix the failed step and rerun rebase-state.');
            fwrite(STDOUT, 'Error: rebase-state failed at command: ' . $command . PHP_EOL);
            return 2;
        }
    }

    $data = [
        'status' => 'ok',
        'runs' => $runs,
        'next_artifact' => 'docs/ai/generated/next.json',
    ];
    $written = aiCliWriteArtifact($root, 'rebase-state', 'php tools/ai/ai.php rebase-state', $data, 'ok', null, 'Open next.json and execute the recommended action.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

function aiRunCommitMsg(string $root): int
{
    $diff = aiLoadArtifactData($root, 'diff-summary.json');
    $risk = aiLoadArtifactData($root, 'risk.json');
    $verify = aiLoadArtifactData($root, 'verify.json');

    $changedCount = (int) ($diff['data']['changed_files_count'] ?? 0);
    $riskLevel = (string) ($risk['data']['risk_level'] ?? 'unknown');
    $verifyStatus = (string) ($verify['data']['status'] ?? 'unknown');

    $prefix = 'chore(ai)';
    if ($riskLevel === 'high') {
        $prefix = 'feat(ai)';
    } elseif ($riskLevel === 'medium') {
        $prefix = 'refactor(ai)';
    }

    $message = sprintf('%s update workflow artifacts and checks (%d files, verify:%s)', $prefix, $changedCount, $verifyStatus);
    $txtPath = aiCliGeneratedDir($root) . DIRECTORY_SEPARATOR . 'commit-msg.txt';
    file_put_contents($txtPath, $message . PHP_EOL);

    $data = [
        'message' => $message,
        'changed_files_count' => $changedCount,
        'risk_level' => $riskLevel,
        'verify_status' => $verifyStatus,
        'output' => 'docs/ai/generated/commit-msg.txt',
    ];

    $written = aiCliWriteArtifact($root, 'commit-msg', 'php tools/ai/ai.php commit-msg', $data, 'ok', null, 'Use suggested commit message or adapt to final diff intent.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

function aiRunPrSummary(string $root): int
{
    $diff = aiLoadArtifactData($root, 'diff-summary.json');
    $risk = aiLoadArtifactData($root, 'risk.json');
    $verify = aiLoadArtifactData($root, 'verify.json');

    $changed = (int) ($diff['data']['changed_files_count'] ?? 0);
    $riskLevel = (string) ($risk['data']['risk_level'] ?? 'unknown');
    $riskScore = $risk['data']['risk_score'] ?? null;
    $verifyStatus = (string) ($verify['data']['status'] ?? 'unknown');

    $summaryMd = "## Summary\n\n";
    $summaryMd .= "- Updated AI workflow artifacts and automation surfaces for current diff.\n";
    $summaryMd .= "- Changed files: {$changed}.\n\n";
    $summaryMd .= "## Risk\n\n";
    $summaryMd .= "- Risk level: {$riskLevel}" . ($riskScore !== null ? " ({$riskScore}/100)" : '') . "\n\n";
    $summaryMd .= "## Verification\n\n";
    $summaryMd .= "- Verify status: {$verifyStatus}\n";

    $prMdPath = aiCliGeneratedDir($root) . DIRECTORY_SEPARATOR . 'pr-summary.md';
    file_put_contents($prMdPath, $summaryMd);

    $data = [
        'summary_markdown_path' => 'docs/ai/generated/pr-summary.md',
        'changed_files_count' => $changed,
        'risk_level' => $riskLevel,
        'risk_score' => $riskScore,
        'verify_status' => $verifyStatus,
    ];

    $written = aiCliWriteArtifact($root, 'pr-summary', 'php tools/ai/ai.php pr-summary', $data, 'ok', null, 'Use generated PR summary as base and refine task-specific details.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

function aiRunConflicts(string $root): int
{
    $statusOut = aiGitLines($root, 'status --porcelain');
    $conflicts = [];
    foreach ($statusOut as $line) {
        $prefix = substr($line, 0, 2);
        if (in_array($prefix, ['UU', 'AA', 'DD', 'AU', 'UA', 'DU', 'UD'], true)) {
            $conflicts[] = [
                'status' => $prefix,
                'path' => trim(substr($line, 3)),
            ];
        }
    }

    $status = $conflicts === [] ? 'ok' : 'conflicts_found';
    $data = [
        'status' => $status,
        'conflict_count' => count($conflicts),
        'files' => $conflicts,
    ];

    $written = aiCliWriteArtifact($root, 'conflicts', 'php tools/ai/ai.php conflicts', $data, $status, null, $conflicts === [] ? 'No merge conflicts detected.' : 'Resolve conflicts, then run rebase-state.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return $conflicts === [] ? 0 : 1;
}
