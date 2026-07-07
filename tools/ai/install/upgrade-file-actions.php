<?php

declare(strict_types=1);

// upgrade-file-actions.php: the cohesive upgrade file-action/deprecation engine used by
// aiRunUpgradeWorkflow() (tools/ai/commands/install_workflow.php). Extracted verbatim from
// install_workflow.php (behavior-preserving move; see
// docs/tickets/arch-todo-installer-workflow-command-extraction-20260706-220032/plan.md, Phase 1).
// Depends only on functions already loaded via install_workflow.php's require_once chain
// (core.php: aiInstallerPrivateConflictDir, aiInstallerPrivateConflictRel,
// aiInstallerPrivateDirMode, aiInstallerPackRegistry, aiHashPath).

/**
 * Copy user-modified owned files to .ai/conflicts/<timestamp>-upgrade/files/ before an upgrade reinstall
 * overwrites them, so the user's edits are recoverable. Returns the list of preserved files.
 *
 * @param list<array<string,mixed>> $fileActions
 * @return list<array{file:string,preserved_to:string}>
 */
function aiUpgradePreserveOwnedConflicts(string $root, array $fileActions): array
{
    $conflicts = [];
    foreach ($fileActions as $fa) {
        if (($fa['action'] ?? '') === 'conflict-preserve-user') {
            $conflicts[] = (string) ($fa['file'] ?? '');
        }
    }
    $conflicts = array_values(array_filter($conflicts, static fn($f): bool => $f !== ''));
    if ($conflicts === []) {
        return [];
    }

    $stamp = gmdate('Ymd\THis\Z');
    $conflictRoot = aiInstallerPrivateConflictDir($root, 'upgrade', 'files', $stamp);

    $preserved = [];
    foreach ($conflicts as $rel) {
        $srcAbs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($srcAbs)) {
            continue;
        }
        $destAbs = $conflictRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $destDir = dirname($destAbs);
        if (!is_dir($destDir)) {
            mkdir($destDir, aiInstallerPrivateDirMode(), true);
        }
        if (copy($srcAbs, $destAbs)) {
            $preserved[] = ['file' => $rel, 'preserved_to' => aiInstallerPrivateConflictRel('upgrade', 'files', $stamp) . '/' . $rel];
        }
    }

    return $preserved;
}

/**
 * Flatten every target path the current pack registry ships, across all packs.
 * Used to compute the `deprecated` class (manifest files no longer in this set).
 *
 * @return list<string>
 */
function aiUpgradeCurrentRegistryTargets(): array
{
    $targets = [];
    foreach (aiInstallerPackRegistry() as $items) {
        foreach ($items as $item) {
            $target = (string) ($item['target'] ?? '');
            if ($target !== '') {
                $targets[$target] = true;
            }
        }
    }

    return array_keys($targets);
}

/**
 * Resolve the upgrade status/action for a single installed file by ownership class.
 *
 * Per-class routing (deprecated is computed separately by aiUpgradeComputeDeprecated):
 *  - missing target      -> restore or remove from manifest
 *  - template            -> preserve (never overwritten; --reset-templates handles refresh)
 *  - rendered            -> regenerate from project.yml (user marker sections preserved)
 *  - patch-managed       -> update-managed-block (only the marker block; user content kept)
 *  - owned + user-modified + --force-owned -> force-overwrite (after preserving user bytes)
 *  - owned + user-modified                 -> conflict-preserve-user
 *  - owned + source-updated (clean)        -> auto-update
 *  - otherwise                             -> skip (unchanged)
 *
 * @return array{status:string,action:string}
 */
function aiUpgradeResolveFileAction(
    string $ownership,
    bool $userModified,
    bool $sourceUpdated,
    bool $targetMissing,
    bool $forceOwned
): array {
    if ($targetMissing) {
        return ['status' => 'missing', 'action' => 'restore or remove from manifest'];
    }
    if ($ownership === 'template') {
        return [
            'status' => $userModified ? 'template-user-owned' : 'template-unchanged',
            'action' => 'preserve',
        ];
    }
    if ($ownership === 'rendered') {
        return ['status' => 'rendered', 'action' => 'regenerate'];
    }
    if ($ownership === 'patch-managed') {
        return ['status' => 'patch-managed', 'action' => 'update-managed-block'];
    }
    if ($ownership === 'owned' && $userModified) {
        if ($forceOwned) {
            return ['status' => 'owned-force-overwrite', 'action' => 'force-overwrite'];
        }
        return [
            'status' => $sourceUpdated ? 'owned-both-changed' : 'owned-user-modified',
            'action' => 'conflict-preserve-user',
        ];
    }
    if ($sourceUpdated && !$userModified) {
        return ['status' => 'source-updated', 'action' => 'auto-update'];
    }

    return ['status' => 'unchanged', 'action' => 'skip'];
}

/**
 * Apply-path removal of computed `deprecated` files (see aiUpgradeComputeDeprecated).
 *
 * Only operates on the deprecated entries it is given (each derived from the install
 * manifest), so the write-allowlist invariant holds — foreign files are never touched.
 *  - action `delete`          -> remove the file (a byte-identical copy is already in backup)
 *  - action `route-to-removed` -> copy user bytes to .ai/conflicts/<ts>-upgrade/removed/ then remove
 *
 * Files already absent from disk are skipped. Returns the list of files actually acted on.
 *
 * @param list<array{file:string,action:string}> $deprecated
 * @return list<array{file:string,action:string,routed_to?:string}>
 */
function aiUpgradeRemoveDeprecated(string $root, array $deprecated): array
{
    if ($deprecated === []) {
        return [];
    }

    $stamp = gmdate('Ymd\THis\Z');
    $removedRoot = aiInstallerPrivateConflictDir($root, 'upgrade', 'removed', $stamp);

    $acted = [];
    foreach ($deprecated as $entry) {
        $rel = (string) ($entry['file'] ?? '');
        $action = (string) ($entry['action'] ?? '');
        if ($rel === '') {
            continue;
        }
        $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($abs)) {
            // Already gone from disk: nothing to remove or route.
            continue;
        }

        if ($action === 'route-to-removed') {
            $destAbs = $removedRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $destDir = dirname($destAbs);
            if (!is_dir($destDir)) {
                mkdir($destDir, aiInstallerPrivateDirMode(), true);
            }
            if (!copy($abs, $destAbs)) {
                // Never delete user bytes we failed to preserve.
                continue;
            }
            unlink($abs);
            $acted[] = [
                'file' => $rel,
                'action' => $action,
                'routed_to' => aiInstallerPrivateConflictRel('upgrade', 'removed', $stamp) . '/' . $rel,
            ];
            continue;
        }

        // Default: delete (deprecated-unchanged; already backed up).
        unlink($abs);
        $acted[] = ['file' => $rel, 'action' => 'delete'];
    }

    return $acted;
}

/**
 * Compute the `deprecated` ownership class at plan time (never stored).
 *
 * A deprecated file is one recorded in the installed manifest but no longer shipped by the
 * current pack registry (e.g. a stale hook or policy the kit dropped). Upgrade routes each:
 *  - deprecated-unchanged     -> delete           (the byte-identical copy is already in backup)
 *  - deprecated-user-modified -> route-to-removed (user edits go to conflicts/<ts>-upgrade/removed/)
 *
 * Files already absent from disk produce no action. Invariant 1 (write-allowlist) holds:
 * only manifest-recorded paths are ever considered, never foreign files.
 *
 * @param array<string,mixed> $manifestFiles  Canonical files{} map from the install manifest.
 * @param list<string>        $registryTargets Target paths the current kit still ships.
 * @return list<array{file:string,ownership:string,status:string,action:string}>
 */
function aiUpgradeComputeDeprecated(array $manifestFiles, array $registryTargets, string $root): array
{
    $shipped = array_fill_keys(array_map('strval', $registryTargets), true);
    $deprecated = [];

    foreach ($manifestFiles as $target => $meta) {
        $target = (string) $target;
        if (isset($shipped[$target])) {
            continue;
        }
        $targetAbs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);
        $currentHash = aiHashPath($targetAbs);
        if ($currentHash === 'missing') {
            // Already gone from disk: nothing to delete or route.
            continue;
        }
        $installedHash = is_array($meta) ? (string) ($meta['installed_hash'] ?? 'unknown') : 'unknown';
        $userModified = $currentHash !== $installedHash;

        $deprecated[] = [
            'file' => $target,
            'ownership' => 'deprecated',
            'status' => $userModified ? 'deprecated-user-modified' : 'deprecated-unchanged',
            'action' => $userModified ? 'route-to-removed' : 'delete',
        ];
    }

    return $deprecated;
}

/** @param list<string> $args @return list<string> */
function aiUpgradeBuildApplyInstallArgs(string $mode, string $backupId, array $args): array
{
    // Force the reinstall so owned/auto-update files are actually rewritten with the new kit
    // version. Without --force the planner marks differing files SKIP_EXISTING_UNMANAGED and the
    // upgrade becomes a no-op. User edits to owned files are already preserved above.
    $installArgs = ['--apply', '--reinstall', '--force', '--mode', $mode, '--backup', $backupId, '--no-interaction'];
    if (in_array('--agent', $args, true)) {
        $installArgs[] = '--agent';
    }
    if (in_array('--ci', $args, true)) {
        $installArgs[] = '--ci';
    }

    return $installArgs;
}
