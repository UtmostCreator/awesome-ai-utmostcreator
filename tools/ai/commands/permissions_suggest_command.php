<?php

declare(strict_types=1);

require_once __DIR__ . '/../install/permission-layers/compose.php';
require_once __DIR__ . '/helpers.php';

/**
 * Standalone `permissions-suggest` CLI verb (P4.3/P4.4/P4.8 of
 * docs/tickets/arch-todo-stack-permission-placeholder-skill-trio-20260705T151632Z/plan.md).
 *
 * Refuses to proceed without a fresh `stack-detect` scan (`.ai/stack-detection.json`,
 * written by both the standalone `stack-detect` verb and the installer). Once a
 * selection exists, previews the recommended overlay layer for human review:
 *
 * - the raw overlay entries via aiPermissionStackOverlayEntries() (deny-floor-safe:
 *   stack ids resolve permissions ONLY by referencing a reviewed language overlay,
 *   never by deriving raw patterns from package names — see stack-overlays.php);
 * - one illustrative full composed model via aiPermissionComposeFromSpec(), which
 *   self-enforces the immutable hard-deny floor (aiPermissionAssertNoHardDenyWeakening)
 *   regardless of caller.
 *
 * This command only prints a preview; it never writes agent permission
 * frontmatter. `tools/ai/generate-agent-permissions.php --write` remains the
 * separate, gated apply step for this repo's own shipped agents.
 */
function aiRunPermissionsSuggest(string $root, array $args): int
{
    $evidencePath = $root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'stack-detection.json';
    if (!is_file($evidencePath)) {
        fwrite(STDERR, "Error: no stack scan found at .ai/stack-detection.json.\n");
        fwrite(STDERR, "Run `php tools/ai/ai.php stack-detect` first, then re-run this command.\n");
        return 1;
    }

    $decoded = json_decode((string) file_get_contents($evidencePath), true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "Error: .ai/stack-detection.json is not valid JSON. Re-run `stack-detect`.\n");
        return 1;
    }

    $selected = array_values(array_unique(array_map('strval', $decoded['selected'] ?? [])));
    if ($selected === []) {
        fwrite(STDOUT, "No stacks selected in the last scan; nothing to preview.\n");
        return 0;
    }

    $entries = aiPermissionStackOverlayEntries($selected, $root);

    fwrite(STDOUT, 'PREVIEW ONLY — nothing is written by this command.' . PHP_EOL);
    fwrite(STDOUT, 'Selected stacks (from .ai/stack-detection.json): ' . implode(', ', $selected) . PHP_EOL . PHP_EOL);
    fwrite(STDOUT, 'Stack overlay entries (resolved only via reviewed language overlays):' . PHP_EOL);
    if ($entries === []) {
        fwrite(STDOUT, '  (none — selected stacks add no overlay permissions beyond their implied language overlays)' . PHP_EOL);
    }
    foreach ($entries as $entry) {
        fwrite(STDOUT, sprintf('  %-6s %-10s %s', $entry['effect'], $entry['permission'], $entry['pattern']) . PHP_EOL);
    }

    $profile = aiParseArg($args, 'profile') ?? 'readonly';
    $editSurface = aiParseArg($args, 'edit-surface') ?? 'none';
    $verifyTier = aiParseArg($args, 'verify-tier') ?? 'verify-none';

    $composed = aiPermissionComposeFromSpec([
        'profile' => $profile,
        'edit_surface' => $editSurface,
        'verify_tier' => $verifyTier,
        'stack_overlays' => $selected,
    ]);

    $counts = ['allow' => 0, 'ask' => 0, 'deny' => 0];
    foreach ($composed['model'] as $entry) {
        $counts[$entry['effect']]++;
    }

    fwrite(STDOUT, PHP_EOL . sprintf(
        'Illustrative composed model (profile=%s, edit_surface=%s, verify_tier=%s): %d allow, %d ask, %d deny across %d layers.',
        $profile,
        $editSurface,
        $verifyTier,
        $counts['allow'],
        $counts['ask'],
        $counts['deny'],
        count($composed['layers'])
    ) . PHP_EOL);
    fwrite(STDOUT, 'The immutable hard-deny floor is enforced by aiPermissionComposeFromSpec() itself' . PHP_EOL);
    fwrite(STDOUT, '(throws if any layer would weaken it) — this preview cannot bypass it.' . PHP_EOL);
    fwrite(STDOUT, PHP_EOL . 'This is a preview only. Use --profile/--edit-surface/--verify-tier to try other' . PHP_EOL);
    fwrite(STDOUT, 'combinations. Applying a real agent permission block remains a separate, gated,' . PHP_EOL);
    fwrite(STDOUT, 'human-reviewed step (see tools/ai/generate-agent-permissions.php for this kit\'s' . PHP_EOL);
    fwrite(STDOUT, 'own shipped agents).' . PHP_EOL);

    return 0;
}
