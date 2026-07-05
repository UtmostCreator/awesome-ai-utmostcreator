<?php

declare(strict_types=1);

require_once __DIR__ . '/../install/config.php';
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
 * Writes the committed `docs/ai/project/stack.md` projection in addition to
 * printing the same human-readable summary the installer already produces.
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
    }

    fwrite(STDOUT, aiStackSelectionSummary($resolved) . PHP_EOL);
    if ($writePath !== null) {
        fwrite(STDOUT, 'Wrote ' . aiStackProjectDocRelativePath() . PHP_EOL);
    }

    return 0;
}
