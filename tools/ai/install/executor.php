<?php

declare(strict_types=1);

/**
 * Execute the write phase of an install plan: iterate every plan item and dispatch to the
 * correct writer (or skip/conflict channel) based on `action` and `install_type`. This is a
 * whole-block extraction of the former inline loop body in `aiInstallerRun()` — behavior,
 * iteration order, and all `install_type` dispatch branches are unchanged.
 *
 * @param array<string,mixed> $config
 * @param list<array<string,mixed>> $plan
 * @return array{applied:list<array<string,mixed>>,template_refreshes:list<string>}
 */
function aiInstallerExecutePlan(array $config, array $plan): array
{
    $applied = [];
    $seenDirTargets = [];
    $templateRefreshes = [];
    // Targets written by more than one copilot-agents pack (base adapter-copilot + the optional
    // pack both target .github/agents). For these, the first writer must NOT clobber the dir, so
    // the two agent sets coexist regardless of pack order. Filenames never overlap between
    // core/agents and optional/agents, so a pure merge is deterministic.
    $copilotAgentsWritersByTarget = [];
    foreach ($plan as $planItem) {
        if (($planItem['install_type'] ?? '') === 'copilot-agents') {
            $t = (string) ($planItem['target'] ?? '');
            $copilotAgentsWritersByTarget[$t] = ($copilotAgentsWritersByTarget[$t] ?? 0) + 1;
        }
    }
    foreach ($plan as $item) {
        if ($item['action'] === 'SKIP_EXISTING_UNMANAGED' || $item['action'] === 'SKIP_PROTECTED_CORE' || $item['action'] === 'SKIP_IDENTICAL_EXISTING') {
            // Force-render the optional Copilot agents even when the planner marked the shared
            // .github/agents dir SKIP (it pre-existed at plan time, e.g. on every re-install). The
            // base adapter-copilot pack re-renders that dir this run, so without this the optional
            // agents would be lost on re-install. The merge variant only writes agents that are not
            // already present, so user-authored .agent.md files are still preserved.
            if (
                !$config['dryRun']
                && ($item['install_type'] ?? '') === 'copilot-agents'
                && ($item['merge_into_existing'] ?? false) === true
            ) {
                $src = $config['sourceRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $item['source']);
                $dest = $config['targetRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $item['target']);
                $scriptsRoot = $config['targetRoot'] . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'ai';
                // Mark the target seen so the base writer (whichever order it runs) merges too.
                $seenDirTargets[$item['target']] = true;
                aiInstallerMergeDirAsCopilotAgents($src, $dest, $scriptsRoot, true);
                aiInstallerLog('merged optional copilot agents into ' . $item['target'] . ' (preserved alongside base agents)');
                continue;
            }
            // Template refresh channel: when a skip-if-exists template is preserved but the shipped
            // upstream version has changed, surface the new version under .ai/templates-new/<path>
            // so the user can diff/merge — the user's file is never overwritten.
            if (!$config['dryRun'] && $item['action'] === 'SKIP_EXISTING_UNMANAGED' && ($item['type'] ?? '') === 'file') {
                if (($item['merge_strategy'] ?? '') === 'skip-if-exists') {
                    // Template files get the refresh channel (.ai/templates-new/<path>).
                    $refreshed = aiInstallerOfferTemplateRefresh($config, $item);
                    if ($refreshed !== null) {
                        $templateRefreshes[] = $refreshed;
                        aiInstallerLog('template-new ' . $item['target'] . ' (upstream changed; see ' . $refreshed . ')');
                    }
                } else {
                    // P3-c: a non-template kit file blocked by a foreign file is surfaced under
                    // .ai/conflicts/<ts>-install/incoming/<path> so the user can diff; never overwritten.
                    $incoming = aiInstallerOfferIncomingConflict($config, $item);
                    if ($incoming !== null) {
                        $templateRefreshes[] = $incoming;
                        aiInstallerLog('incoming ' . $item['target'] . ' (foreign collision; see ' . $incoming . ')');
                    }
                }
            }
            aiInstallerLog('skip ' . $item['target'] . ' (' . strtolower($item['action']) . ')');
            continue;
        }
        if ($item['action'] === 'CONFLICT_FOREIGN') {
            // Surfaced and (for apply) already aborted by aiInstallerAssertNoForeignConflicts.
            // On dry-run we only report it; never write.
            aiInstallerLog('conflict ' . $item['target'] . ' (foreign; rerun with --adopt or resolve manually)');
            continue;
        }
        if ($config['dryRun']) {
            aiInstallerLog('plan ' . strtolower($item['type']) . ': ' . $item['source'] . ' -> ' . $item['target']);
            continue;
        }

        // Before overwriting a displaced foreign file via adoption, snapshot it so user bytes are
        // always recoverable from .ai/conflicts/ even without an explicit --backup. Only adopted
        // overwrites carry this reason; ownership-proven refreshes (identical/known-kit/recorded)
        // do not displace user data and are skipped here.
        if (
            ($item['action'] ?? '') === 'OVERWRITE_MANAGED'
            && ($item['type'] ?? '') === 'file'
            && str_contains((string) ($item['reason'] ?? ''), 'adopt')
        ) {
            $snapshot = aiInstallerSnapshotAdoptedForeign($config, $item, gmdate('Ymd\THis\Z'));
            if ($snapshot !== null) {
                aiInstallerLog('snapshot ' . $item['target'] . ' (adopted foreign overwrite; see ' . $snapshot . ')');
            }
        }

        $src = $config['sourceRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $item['source']);
        $dest = $config['targetRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $item['target']);
        // claude-settings-merge is a 'file'-type item but must never take the plain copy path:
        // it merges with a pre-existing .claude/settings.json (e.g. graphify's own hooks) instead
        // of overwriting it. Must be checked BEFORE the generic file-type branch below.
        if (($item['install_type'] ?? '') === 'claude-settings-merge') {
            aiInstallerMergeClaudeSettingsFile($src, $dest);
        } elseif ($item['type'] === 'file') {
            aiInstallerCopyFile($src, $dest);
        } elseif (($item['install_type'] ?? '') === 'copilot-agents') {
            $scriptsRoot = $config['targetRoot'] . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'ai';
            // Two packs target .github/agents: the base adapter-copilot pack and the
            // optional-agents-copilot-pack. Writers refresh rendered files in place. As soon as a
            // second writer exists (or the dir was already written this run), every writer uses the
            // merge path so neither pack skips or replaces the other's agents.
            $hasCoWriter = (int) ($copilotAgentsWritersByTarget[$item['target']] ?? 1) > 1;
            $mergeIntoExisting = ($item['merge_into_existing'] ?? false) === true
                || $hasCoWriter
                || isset($seenDirTargets[$item['target']]);
            $seenDirTargets[$item['target']] = true;
            if ($mergeIntoExisting) {
                $skipExisting = ($item['merge_strategy'] ?? '') === 'skip-if-exists';
                aiInstallerMergeDirAsCopilotAgents($src, $dest, $scriptsRoot, $skipExisting);
            } else {
                aiInstallerCopyDirAsCopilotAgents($src, $dest, $scriptsRoot);
            }
        } elseif (($item['install_type'] ?? '') === 'opencode-agents') {
            aiInstallerCopyDirAsOpenCodeAgents($src, $dest);
        } elseif (($item['install_type'] ?? '') === 'claude-agents') {
            $scriptsRoot = $config['targetRoot'] . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'ai';
            // Two packs target .claude/agents: the base adapter-claude pack (refresh in place)
            // and the optional-agents-claude-pack (merge_into_existing + skip-if-exists). The
            // merge path never deletes the tree and honors skip-if-exists, mirroring the
            // copilot-agents branch above.
            if (($item['merge_into_existing'] ?? false) === true) {
                $skipExisting = ($item['merge_strategy'] ?? '') === 'skip-if-exists';
                aiInstallerMergeDirAsClaudeAgents($src, $dest, $scriptsRoot, $skipExisting);
            } else {
                aiInstallerCopyDirAsClaudeAgents($src, $dest, $scriptsRoot);
            }
        } elseif (($item['install_type'] ?? '') === 'skill-dirs') {
            aiInstallerCopyDirAsSkillDirs($src, $dest);
        } elseif (($item['install_type'] ?? '') === 'opencode-commands') {
            $cleanFirst = !isset($seenDirTargets[$item['target']]);
            $seenDirTargets[$item['target']] = true;
            aiInstallerCopyDirAsOpenCodeCommands($src, $dest, $cleanFirst);
        } elseif (isset($item['rename_ext'])) {
            aiInstallerCopyDirWithRename($src, $dest, $item['rename_ext']);
        } else {
            $cleanFirst = !isset($seenDirTargets[$item['target']]);
            $seenDirTargets[$item['target']] = true;
            aiInstallerCopyDir($src, $dest, $cleanFirst);
        }
        $applied[] = $item;
        aiInstallerLog('copied ' . $item['type'] . ': ' . $item['target']);
    }

    return ['applied' => $applied, 'template_refreshes' => $templateRefreshes];
}
