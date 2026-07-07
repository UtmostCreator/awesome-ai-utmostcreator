<?php

declare(strict_types=1);

// workflow-manifest.php: install-manifest workflow helpers used by aiRunInstallWorkflow()
// (tools/ai/commands/install_workflow.php). Extracted verbatim from install_workflow.php
// (behavior-preserving move; see
// docs/tickets/arch-todo-installer-workflow-command-extraction-20260706-220032/plan.md, Phase 2).

/**
 * Merge workflow-level install metadata onto the canonical manifest written by the
 * subprocess installer (install-ai-kit.php -> manifest.php).
 *
 * The subprocess is the single source of truth for the rich per-file `files{}` map
 * (which carries per-file ownership/merge metadata). The orchestrator must augment that
 * manifest with workflow-level fields (mode, runtime, toolchain, post_install_script,
 * package_lock_sha256, managed_paths) WITHOUT discarding `files{}`. Previously the
 * orchestrator overwrote the canonical manifest with a flat `managed_paths` shape, which
 * silently dropped the per-file map on the `ai.php install --apply` path.
 *
 * @param array<string,mixed> $canonical Canonical manifest read back from disk (may be empty).
 * @param array<string,mixed> $workflowFields Workflow-level fields to layer on top.
 * @param list<string>        $managedPaths Flat list of managed target paths (fallback only).
 * @return array<string,mixed> Merged manifest with `files{}` preserved.
 */
function aiInstallerMergeWorkflowManifest(array $canonical, array $workflowFields, array $managedPaths): array
{
    // Preserve the authoritative per-file map. If the subprocess did not produce one
    // (e.g. its shape changed), synthesise a minimal map so downstream ownership/upgrade
    // logic still has a per-file structure to rely on.
    if (!is_array($canonical['files'] ?? null)) {
        $canonical['files'] = [];
        foreach ($managedPaths as $managedPath) {
            if (is_string($managedPath) && $managedPath !== '') {
                $canonical['files'][$managedPath] = ['managed' => true, 'ownership' => 'owned'];
            }
        }
    }

    $merged = array_merge($canonical, $workflowFields);
    // `files` must never be replaced by the workflow layer.
    $merged['files'] = $canonical['files'];
    $merged['managed_paths'] = array_values(array_filter(
        $managedPaths,
        static fn($p): bool => is_string($p) && $p !== ''
    ));

    return $merged;
}

/** @param array<string,mixed> $installConfig */
function aiInstallerBuildSubprocessInstallCommand(string $runtime, string $profile, string $mode, array $installConfig): string
{
    $cmd = 'php tools/ai/install-ai-kit.php --target . --runtime ' . escapeshellarg($runtime) . ' --profile ' . escapeshellarg($profile);
    if ($mode === 'sidecar-only') {
        $cmd .= ' --no-base';
    }
    // A reinstall implies overwriting existing managed files, so force the subprocess
    // installer; otherwise it skips them and the reinstall is a no-op for managed dirs.
    if (!empty($installConfig['force']) || !empty($installConfig['reinstall'])) {
        $cmd .= ' --force';
    }
    if (!empty($installConfig['allowCoreOverwrite'])) {
        $cmd .= ' --allow-core-overwrite';
    }
    if (!empty($installConfig['adopt'])) {
        $cmd .= ' --adopt';
    }
    if (!empty($installConfig['allowNonGit'])) {
        $cmd .= ' --allow-non-git';
    }
    if (!empty($installConfig['allFeatures'])) {
        $cmd .= ' --all-features';
    }
    if (!empty($installConfig['withPacks'])) {
        $cmd .= ' --with ' . escapeshellarg(implode(',', $installConfig['withPacks']));
    }
    if (!empty($installConfig['withoutPacks'])) {
        $cmd .= ' --without ' . escapeshellarg(implode(',', $installConfig['withoutPacks']));
    }
    if (!empty($installConfig['allowPlaceholders'])) {
        $cmd .= ' --allow-placeholders';
    }

    return $cmd;
}
