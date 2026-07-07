<?php

declare(strict_types=1);

// uninstall-prune.php: empty-directory pruning helper used by aiRunUninstallWorkflow()
// (tools/ai/commands/install_workflow.php). Extracted verbatim from install_workflow.php
// (behavior-preserving move; see
// docs/tickets/arch-todo-installer-workflow-command-extraction-20260706-220032/plan.md, Phase 3).

/**
 * Remove empty directories upward from $dir until reaching (but not removing) $root.
 *
 * P3-e: a directory is only pruned when it is empty AND the kit recorded it in the lock's
 * createdDirs allowlist. A pre-existing user directory that merely became empty is preserved,
 * and a non-empty directory is never touched. This is a single-level rmdir walk, never a
 * recursive delete.
 *
 * @param list<string> $createdDirs Repo-relative directory paths the kit created (lock createdDirs).
 */
function aiUninstallPruneEmptyParents(string $dir, string $root, array $createdDirs = []): void
{
    $root = rtrim($root, '/\\');
    $dir = rtrim($dir, '/\\');
    $allowed = array_fill_keys(array_map(
        static fn(string $d): string => trim(str_replace('\\', '/', $d), '/'),
        $createdDirs
    ), true);

    while ($dir !== '' && $dir !== $root && str_starts_with($dir, $root) && is_dir($dir)) {
        $rel = trim(str_replace('\\', '/', substr($dir, strlen($root))), '/');
        if (!isset($allowed[$rel])) {
            // Not a kit-created directory: never remove it, even if empty.
            return;
        }
        $entries = @scandir($dir);
        if ($entries === false) {
            return;
        }
        $entries = array_diff($entries, ['.', '..']);
        if ($entries !== []) {
            return;
        }
        if (!@rmdir($dir)) {
            return;
        }
        $dir = rtrim(dirname($dir), '/\\');
    }
}
