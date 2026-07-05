<?php

declare(strict_types=1);

require_once __DIR__ . '/../install/config.php';
require_once __DIR__ . '/../install/core.php'; // aiInstallerWriteStackDetectionEvidence()
require_once __DIR__ . '/../install/stack-project-doc.php';
require_once __DIR__ . '/stack_selection.php';
require_once __DIR__ . '/helpers.php';

/**
 * Standalone `stack-detect` CLI verb (P4.1 of
 * docs/tickets/arch-todo-stack-permission-placeholder-skill-trio-20260705T151632Z/plan.md).
 *
 * Wraps aiStackSelectionResolve() (which itself wraps aiStackDetect() +
 * aiStackRunVersionChecks()) — no scanning logic lives here. Prior to this
 * verb, stack detection only ran inside the installer wizard/`--stack-detect-only`.
 *
 * Writes the committed `docs/ai/project/stack.md` projection, and also writes
 * the same informational `.ai/stack-detection.json` evidence file the
 * installer writes (aiInstallerWriteStackDetectionEvidence(), reused
 * unchanged) so a standalone `stack-detect` run — not just a full install —
 * satisfies the `generate-permissions` skill's "refuse without a fresh scan"
 * gate (see the Scan-first data-flow contract in this ticket's plan.md).
 */
function aiRunStackDetect(string $root, array $args): int
{
    $config = [
        'stacks' => aiInstallerParseCsvList(aiParseArg($args, 'stacks') ?? ''),
        'noStackDetect' => in_array('--no-stack-detect', $args, true),
    ];

    $resolved = aiStackSelectionResolve($root, $config);
    $writePath = null;
    if (!in_array('--no-write', $args, true)) {
        $writePath = aiStackWriteProjectDoc($root, $resolved, basename(rtrim($root, '/\\')));
        aiInstallerWriteStackDetectionEvidence($root, $resolved);
    }

    fwrite(STDOUT, aiStackSelectionSummary($resolved) . PHP_EOL);
    if ($writePath !== null) {
        fwrite(STDOUT, 'Wrote ' . aiStackProjectDocRelativePath() . PHP_EOL);
    }

    return 0;
}
