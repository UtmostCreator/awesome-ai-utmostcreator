<?php

declare(strict_types=1);

function aiDecisionsMarkdownPath(string $root): string
{
    return $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'decisions.md';
}

function aiDecisionsJsonlPath(string $root): string
{
    return $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'decisions.jsonl';
}

function aiEnsureDecisionsStore(string $root): void
{
    $md = aiDecisionsMarkdownPath($root);
    $jsonl = aiDecisionsJsonlPath($root);
    if (!is_file($md)) {
        file_put_contents($md, "# AI Decisions Log\n\n");
    }
    if (!is_file($jsonl)) {
        file_put_contents($jsonl, '');
    }
}

function aiRunDecision(string $root, array $args): int
{
    $sub = $args[0] ?? '';
    if ($sub !== 'add') {
        throw new RuntimeException('decision command supports only: add');
    }

    aiEnsureDecisionsStore($root);
    $file = aiParseArg($args, 'file') ?? 'unknown';
    $reason = aiParseArg($args, 'reason') ?? '';
    if ($reason === '') {
        throw new RuntimeException('decision add requires --reason');
    }

    $entry = [
        'timestamp' => aiCliIsoNow(),
        'commit' => aiCliCurrentCommit($root),
        'branch' => aiCliCurrentBranch($root),
        'file' => $file,
        'reason' => $reason,
    ];

    $encoded = json_encode($entry, JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        throw new RuntimeException('Failed to encode decision entry.');
    }
    file_put_contents(aiDecisionsJsonlPath($root), $encoded . PHP_EOL, FILE_APPEND);

    $mdBlock = "## {$entry['timestamp']} — {$file}\n\n";
    $mdBlock .= "Decision reason:\n\n- {$reason}\n\n";
    $mdBlock .= "Context:\n\n- commit: `{$entry['commit']}`\n- branch: `{$entry['branch']}`\n\n";
    file_put_contents(aiDecisionsMarkdownPath($root), $mdBlock, FILE_APPEND);

    $data = [
        'status' => 'ok',
        'entry' => $entry,
        'decision_files' => [
            'docs/ai/decisions.md',
            'docs/ai/decisions.jsonl',
        ],
    ];

    $written = aiCliWriteArtifact($root, 'decision-add', 'php tools/ai/ai.php decision add', $data, 'ok', null, 'Use why to inspect decision history.');
    fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
    return 0;
}

function aiRunWhy(string $root, array $args): int
{
    aiEnsureDecisionsStore($root);
    $filter = $args[0] ?? null;

    $lines = file(aiDecisionsJsonlPath($root), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $entries = [];
    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            continue;
        }
        if ($filter !== null && $filter !== '' && (string) ($decoded['file'] ?? '') !== $filter) {
            continue;
        }
        $entries[] = $decoded;
    }

    $data = [
        'filter' => $filter,
        'count' => count($entries),
        'entries' => $entries,
        'source' => [
            'docs/ai/decisions.md',
            'docs/ai/decisions.jsonl',
        ],
    ];

    $written = aiCliWriteArtifact($root, 'why', 'php tools/ai/ai.php why', $data, 'ok', null, 'Use session-resume for cross-artifact continuation context.');
    fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
    return 0;
}
