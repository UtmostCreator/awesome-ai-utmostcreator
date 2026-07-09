<?php

declare(strict_types=1);

require_once __DIR__ . '/claude-agent-tool-registry.php';
require_once __DIR__ . '/generated-header.php';
require_once __DIR__ . '/canonical-agent-frontmatter.php';
require_once __DIR__ . '/permission-layers/render-adapters.php';
// Reuses aiAgentIsHiddenInternalOnly() and aiCopilotExtractAssessmentBlock(), which already
// live in the Copilot renderer file because core.php's opencode-agents dispatch shares the
// former too (see core.php L14/L232-233).
require_once __DIR__ . '/copilot-agent-renderer.php';

/**
 * Renders a canonical OpenCode agent template as a Claude Code sub-agent .md file
 * (.claude/agents/<id>.md).
 *
 * The canonical source (OpenCode format) uses: id, mode, temperature, capabilities, permission
 * blocks with per-command bash allowlists. The Claude Code sub-agent format
 * (docs.anthropic.com/en/docs/claude-code/sub-agents, fetched 2026-07-04) requires: name,
 * description, and supports tools, disallowedTools, model, permissionMode.
 *
 * Per-command bash allowlists cannot be expressed in Claude frontmatter (tool-level allow/deny
 * only); they are converted to a "Bash Command Policy" body section, mirroring the Copilot
 * renderer's Shell Boundary approach. Hard enforcement of these lives in `.claude/settings.json`
 * `permissions.allow`/`permissions.deny` (see the Claude adapter parity plan, P1-2/P1-3).
 *
 * @param string $srcContent   Full content of the OpenCode agent .md template
 * @param string $agentId      Agent ID (e.g. 'architect') — used for tool registry lookup
 * @param string $scriptsRoot  Repository-root scripts path placeholder target (e.g. 'scripts/ai')
 * @param string $sourceLabel  Repo-relative template dir this agent was rendered from, recorded in
 *                             the generated-file header (e.g. 'packages/ai-universal-rules/templates/
 *                             optional/agents' for optional agents). Defaults to the core tier for
 *                             back-compat with direct callers that do not know the source tier.
 * @return string              Rendered Claude sub-agent .md content
 */
function aiInstallerRenderClaudeAgent(
    string $srcContent,
    string $agentId,
    string $scriptsRoot,
    string $sourceLabel = 'packages/ai-universal-rules/templates/core/agents'
): string {
    // --- Extract OpenCode frontmatter (shared parser; see canonical-agent-frontmatter.php) ---
    $parsed      = aiInstallerParseCanonicalAgentFrontmatter($srcContent);
    $rawFm       = $parsed['rawFm'];
    $body        = $parsed['body'];
    $frontMatter = $parsed['frontMatter'];
    // Slice 8 adapter seam: see the matching note in copilot-agent-renderer.php.
    $allowedBash = aiPermissionResolveAllowedBash($agentId, $parsed['allowedBash']);

    $id             = $frontMatter['id'] ?? $agentId;
    $description    = $frontMatter['description'] ?? '';
    $toolConfig     = aiClaudeAgentToolConfig($id);
    $tools          = $toolConfig['tools'];
    $disallowed     = $toolConfig['disallowedTools'];
    $permissionMode = $toolConfig['permissionMode'];

    // Optional agent_assessment rubric: carried through from the source template only when
    // present, preserving keys/values exactly (same helper contract as the Copilot renderer).
    $assessmentBlock = $rawFm !== '' ? aiCopilotExtractAssessmentBlock($rawFm) : '';

    // --- Build Claude frontmatter ---
    $claudeFm  = "---\n";
    $claudeFm .= "name: {$id}\n";
    $claudeFm .= "description: {$description}\n";
    $claudeFm .= 'tools: ' . implode(', ', $tools) . "\n";
    if ($disallowed !== []) {
        $claudeFm .= 'disallowedTools: ' . implode(', ', $disallowed) . "\n";
    }
    $claudeFm .= "model: inherit\n";
    $claudeFm .= "permissionMode: {$permissionMode}\n";
    $claudeFm .= $assessmentBlock;
    $claudeFm .= "---\n";

    // --- Build Bash Command Policy body section ---
    $bashPolicy = '';
    if ($allowedBash !== []) {
        $bashPolicy  = "\n## Bash Command Policy\n\n";
        $bashPolicy .= "Claude Code frontmatter cannot express per-command bash allowlists — only the\n";
        $bashPolicy .= "tool-level `Bash` grant above. Treat the following list as required agent policy;\n";
        $bashPolicy .= "hard enforcement depends on `.claude/settings.json` or runtime hooks.\n\n";
        $bashPolicy .= "Approved scripts (run from the repository root using `<SCRIPTS_ROOT>`):\n\n";
        // refactorer-only: collapse a few clusters of near-duplicate Bash Command Policy
        // bullets (same underlying command/script, differing only by env-var prefix or a
        // handful of read-only git-forensic verbs) into one line each, to trim rendered body
        // length toward the per-file soft line-max. Other agents keep every per-command
        // bullet unchanged, so no new render drift is introduced for them. See
        // docs/tickets/claude-agent-fleet-remediation/plan-10-refactorer-agent-critic-fixes-round2.md.
        $collapseClusters = $agentId === 'refactorer' ? [
            [
                'members' => ['git show*', 'git ls-files*', 'git blame*', 'git rev-parse*'],
                'line' => '- `git show*` / `git blame*` / `git rev-parse*` / `git ls-files*` — git-forensic read commands; see `docs/ai/agent-script-access.md` for per-script guidance.',
            ],
            [
                'members' => [
                    'bash scripts/ai/ai-search.sh *',
                    'AI_OUTPUT=json bash scripts/ai/ai-search.sh *',
                    'env AI_OUTPUT=json bash scripts/ai/ai-search.sh *',
                ],
                'line' => '- `bash <SCRIPTS_ROOT>/ai-search.sh *` — plain, `AI_OUTPUT=json`, and `env AI_OUTPUT=json` prefixed forms all approved.',
            ],
            [
                'members' => [
                    'bash scripts/ai/ai-search-multi.sh *',
                    'AI_OUTPUT=json bash scripts/ai/ai-search-multi.sh *',
                    'env AI_OUTPUT=json bash scripts/ai/ai-search-multi.sh *',
                ],
                'line' => '- `bash <SCRIPTS_ROOT>/ai-search-multi.sh *` — plain, `AI_OUTPUT=json`, and `env AI_OUTPUT=json` prefixed forms all approved.',
            ],
            [
                'members' => [
                    'bash scripts/ai/preview-file.sh *',
                    'AI_OUTPUT=json bash scripts/ai/preview-file.sh *',
                    'env AI_OUTPUT=json bash scripts/ai/preview-file.sh *',
                ],
                'line' => '- `bash <SCRIPTS_ROOT>/preview-file.sh *` — plain, `AI_OUTPUT=json`, and `env AI_OUTPUT=json` prefixed forms all approved.',
            ],
            [
                'members' => [
                    'AI_VERIFY_SCOPE=changed VERIFY_SECRETS=0 bash scripts/ai/ai-verify.sh *',
                    'env AI_VERIFY_SCOPE=changed VERIFY_SECRETS=0 bash scripts/ai/ai-verify.sh *',
                ],
                'line' => '- `AI_VERIFY_SCOPE=changed VERIFY_SECRETS=0 bash <SCRIPTS_ROOT>/ai-verify.sh *` — plain and `env`-prefixed forms both approved (see Script Access for the settings.json caveat).',
            ],
        ] : [];
        $emittedClusterKeys = [];
        foreach ($allowedBash as $cmd) {
            $matchedClusterKey = null;
            foreach ($collapseClusters as $clusterKey => $cluster) {
                if (in_array($cmd, $cluster['members'], true)) {
                    $matchedClusterKey = $clusterKey;
                    break;
                }
            }
            if ($matchedClusterKey !== null) {
                if (!isset($emittedClusterKeys[$matchedClusterKey])) {
                    $bashPolicy .= $collapseClusters[$matchedClusterKey]['line'] . "\n";
                    $emittedClusterKeys[$matchedClusterKey] = true;
                }
                continue;
            }
            $displayCmd = preg_replace('/\bscripts\/ai\//', '<SCRIPTS_ROOT>/', $cmd);
            $bashPolicy .= "- `{$displayCmd}`\n";
        }
        $bashPolicy .= "\nDo not run arbitrary shell commands. Do not run commands not in this list.\n";
        $bashPolicy .= "Any script this file's prose describes as `ask`-tier (e.g. `ai-verify.sh`, `ai-edit.sh`,\n";
        $bashPolicy .= "`ai-rollback.sh`, `session-checkpoint.sh`, `pack-context.sh`) is NOT runnable here unless it\n";
        $bashPolicy .= "also appears in the list above — the OpenCode `ask` approval tier does not exist on Claude.\n";
        $bashPolicy .= "Do not run — and `.claude/settings.json` hard-blocks — `rm -rf`, `sudo`, `git push --force`, `git reset --hard`, `git clean -f`, `curl`, `wget`. Other listed commands (`rm`, `mv`, `cp`, `chmod`, plain `git push`/`git reset`) are prose-discouraged and interactively gated, not hard-blocked.\n\n";
        $bashPolicy .= "Hard enforcement (beyond this advisory body policy) lives in `.claude/settings.json`\n";
        $bashPolicy .= "`permissions.allow`/`permissions.deny` rules. If this list and `.claude/settings.json`\n";
        $bashPolicy .= "disagree, `.claude/settings.json` wins — it is the enforced surface, not this body text.\n";
    } elseif (in_array('Bash', $tools, true)) {
        $bashPolicy  = "\n## Bash Command Policy\n\n";
        $bashPolicy .= "Only use shell execution for approved scripts listed in `docs/ai/script-registry.md`,\n";
        $bashPolicy .= "`docs/ai/script-registry.json`, and `docs/ai/scripts-reference.md`. Run scripts from the\n";
        $bashPolicy .= "repository root using `<SCRIPTS_ROOT>/...` paths. Do not run arbitrary commands.\n";
    }

    // --- Combine: Claude frontmatter + bash policy + original body ---
    $body = ltrim($body);
    // Claude Code has no `external_directory` permission field; neutralize the OpenCode-specific
    // `external_directory: ask` prose leaked from the shared body so the installed Claude copy does
    // not imply an enforcement Claude never provides. The OpenCode-format templates and `.opencode/`
    // copies keep the literal (required by their boundary policy and the AgentPermissionPolicy test).
    $body = preg_replace('/\s*\(OpenCode `external_directory: ask`\)/', '', $body);
    $body = preg_replace('/(?:the )?OpenCode `external_directory: ask` prompt/', "the runtime's external-directory approval prompt", $body);

    // The External Context Boundary / External Boundary Rule prose (architect, reviewer,
    // researcher, implementer) frames the approval prompt as if it were an enforced boundary on
    // every runtime. On Claude Code there is no `external_directory` tool permission at all — the
    // Read tool is unrestricted outside secret-file patterns — so state plainly that this is
    // instruction-only guidance here, with no enforcing tool permission behind it. See
    // docs/tickets/claude-agent-fleet-remediation/plan-15-architect-self-review-claude-fixes.md
    // (Fix B).
    $body = preg_replace(
        '/external-directory approval prompt/',
        'external-directory approval prompt (instruction-only on Claude Code; no tool permission enforces this boundary)',
        $body
    );

    // Canonical Script Access sections claim "Full per-script `allow`/`ask`/`deny` is in
    // frontmatter", which is false on Claude: Claude frontmatter only grants the `Bash` tool at
    // the tool level (no per-command allowlist syntax), contradicting this same file's own
    // correct Bash Command Policy note above. Point the sentence at the rendered Bash Command
    // Policy section instead, leaving the trailing tier-specific text (e.g. "Write tier. Use:")
    // unchanged. See docs/tickets/claude-agent-fleet-remediation/plan-13-build-config-render-drift.md.
    $body = str_replace(
        'Full per-script `allow`/`ask`/`deny` is in frontmatter; full guidance in `docs/ai/agent-script-access.md`.',
        'Full per-script `allow`/`ask`/`deny` is documented in the Bash Command Policy section above (Claude frontmatter only grants the `Bash` tool at the tool level, not per-script); full guidance in `docs/ai/agent-script-access.md`.',
        $body
    );

    // Canonical Script Access sections for `task: ask` roles (reviewer, researcher,
    // repository-researcher, release-auditor, workflow-auditor, repository-reviewer,
    // agent-critic, ...) sometimes describe a `task` (`ask`) delegation capability that
    // assumes the OpenCode `task: ask` permission. Claude's tool registry
    // (claude-agent-tool-registry.php) deliberately omits the `Agent` tool for every such
    // role by design (no safe non-interactive fallback for OpenCode's `ask` approval tier —
    // see that file's own doc comment), so any such sentence describes an unreachable
    // capability here. Rewrite it plainly whenever this agent's registry entry omits Agent,
    // rather than shipping a dangling reference to a tool this render never grants. See
    // docs/tickets/claude-agent-fleet-remediation/plan-21-claude-reviewer-remediation.md.
    if (!in_array('Agent', $tools, true)) {
        $body = preg_replace(
            '/`task`\s*\(`ask`\)\s+is only for delegating[^.]*\./',
            "Task-based delegation (`task: ask` on the canonical OpenCode template) is an "
                . 'OpenCode-only capability; it is unavailable on Claude for this role because '
                . 'the tool registry deliberately omits the `Agent` tool here (no safe '
                . "non-interactive fallback for OpenCode's `ask` approval tier — see "
                . 'claude-agent-tool-registry.php). Do not attempt to delegate a sub-review or '
                . 'spawn any subagent from this role on Claude.',
            $body
        );
    }

    // release-auditor's Bash Command Policy footer claims "Other listed commands (`rm`, `mv`,
    // `cp`, `chmod`, plain `git push`/`git reset`) are prose-discouraged and interactively
    // gated, not hard-blocked" — false and self-contradictory for this read-only auditor: none
    // of those verbs ever appear in release-auditor's own Approved-scripts list above, and its
    // Hard Rules already state an absolute no-mutation posture. Replace with an accurate
    // disclosure instead of implying this agent is merely "gated" from running them. See
    // docs/tickets/claude-agent-fleet-remediation/plan-16-release-auditor-agent-critic-fixes.md
    // (agent-critic re-run finding #2).
    if ($agentId === 'release-auditor') {
        $bashPolicy = str_replace(
            'Other listed commands (`rm`, `mv`, `cp`, `chmod`, plain `git push`/`git reset`) are prose-discouraged and interactively gated, not hard-blocked.',
            "This agent's own Approved-scripts list above never includes `rm`, `mv`, `cp`, `chmod`, `git push`, or `git reset` in any form; if `.claude/settings.json`'s shared fleet-wide gate would otherwise interactively prompt for one of them, treat that prompt as out of scope for this read-only auditor and decline it.",
            $bashPolicy
        );

        // release-auditor's plan-16 Hard Rules disclosure sentence points readers "below" to
        // the Script Access section for the full script enumeration. On Claude that section is
        // just a categorized subset (same as every other Claude render); the actual full
        // per-script list is the Bash Command Policy section rendered above. Point the
        // cross-reference at the correct location for this render only — OpenCode/Copilot keep
        // "below" since their Script Access wording differs. See the same plan-16 finding #3.
        $body = str_replace(
            "this agent's Script Access list names below",
            "this agent's Script Access list names above (Bash Command Policy)",
            $body
        );
    }

    // workflow-auditor's Bash Command Policy footer carries the same self-contradictory
    // "Other listed commands (...) are prose-discouraged and interactively gated, not
    // hard-blocked" sentence release-auditor's plan-16 fix already replaced above — false for
    // this read-only auditor too: none of `rm`, `mv`, `cp`, `chmod`, plain `git push`/`git
    // reset` appear in this agent's own Approved-scripts list, and its Hard Rules already state
    // an absolute no-mutation posture. Apply the same defect-class fix (agent-critic re-run
    // finding #1). See
    // docs/tickets/claude-agent-fleet-remediation/plan-17-workflow-auditor-render-fix.md.
    if ($agentId === 'workflow-auditor') {
        $bashPolicy = str_replace(
            'Other listed commands (`rm`, `mv`, `cp`, `chmod`, plain `git push`/`git reset`) are prose-discouraged and interactively gated, not hard-blocked.',
            "These commands are absent from this agent's approved list above and MUST NOT be run by this agent regardless of `.claude/settings.json`'s ask-tier default for other agents.",
            $bashPolicy
        );
    }

    // agent-fleet-assessor's Bash Command Policy footer carries the same self-contradictory
    // "Other listed commands (...) are prose-discouraged and interactively gated, not
    // hard-blocked" sentence release-auditor's plan-16 fix and workflow-auditor's plan-17 fix
    // already replaced above — stale for this read-only orchestrator too: none of `rm`, `mv`,
    // `cp`, `chmod`, plain `git push`/`git reset` appear in this agent's own approved list, and
    // it must never attempt them. See
    // docs/tickets/claude-agent-fleet-remediation/plan-20-agent-fleet-assessor-critic-fixes.md
    // (fresh agent-critic re-audit, MINOR #3).
    if ($agentId === 'agent-fleet-assessor') {
        $bashPolicy = str_replace(
            'Other listed commands (`rm`, `mv`, `cp`, `chmod`, plain `git push`/`git reset`) are prose-discouraged and interactively gated, not hard-blocked.',
            "For other Claude sessions in this repository, `rm`, `mv`, `cp`, `chmod`, plain `git push`/`git reset` are prose-discouraged and interactively gated, not hard-blocked — but none of them appear in this agent's own approved list above, so this agent must never attempt them.",
            $bashPolicy
        );
    }

    // config-maintainer's Bash Command Policy footer carries the same self-contradictory
    // "Other listed commands (...) are prose-discouraged and interactively gated, not
    // hard-blocked" sentence release-auditor's plan-16 fix, workflow-auditor's plan-17 fix, and
    // agent-fleet-assessor's plan-20 fix already replaced above — it falsely implies these
    // commands are "listed" for this agent when none of `rm`, `mv`, `cp`, `chmod`, plain
    // `git push`/`git reset` ever appear in config-maintainer's own Approved-scripts list. See
    // docs/tickets/claude-agent-fleet-remediation/plan-24-config-maintainer-blocker-fix.md
    // (fresh agent-critic re-audit, MINOR finding #2).
    if ($agentId === 'config-maintainer') {
        $bashPolicy = str_replace(
            'Other listed commands (`rm`, `mv`, `cp`, `chmod`, plain `git push`/`git reset`) are prose-discouraged and interactively gated, not hard-blocked.',
            "Other repository-wide commands not part of this agent's approved list (`rm`, `mv`, `cp`, `chmod`, plain `git push`/`git reset`) remain prose-discouraged and interactively gated repo-wide per `.claude/settings.json`, but are outside this agent's own bash surface regardless.",
            $bashPolicy
        );
    }

    // agent-creator-runtime-guardian's Bash Command Policy footer carries the same
    // self-contradictory "Other listed commands (...) are prose-discouraged and interactively
    // gated, not hard-blocked" sentence release-auditor's plan-16 fix, workflow-auditor's
    // plan-17 fix, agent-fleet-assessor's plan-20 fix, and config-maintainer's plan-24 fix
    // already replaced above — it falsely implies these commands are "listed" for this agent
    // when none of `rm`, `mv`, `cp`, `chmod`, plain `git push`/`git reset` ever appear in this
    // agent's own approved list. See
    // docs/tickets/claude-agent-fleet-remediation/plan-25-agent-creator-runtime-guardian-permission-fix.md
    // (fresh agent-critic re-audit, MAJOR finding #1).
    if ($agentId === 'agent-creator-runtime-guardian') {
        $bashPolicy = str_replace(
            'Other listed commands (`rm`, `mv`, `cp`, `chmod`, plain `git push`/`git reset`) are prose-discouraged and interactively gated, not hard-blocked.',
            "These commands are absent from this agent's approved list above and MUST NOT be run by this agent regardless of `.claude/settings.json`'s ask-tier default for other agents.",
            $bashPolicy
        );
    }

    // researcher's canonical Hard Rules clause claims an append-write capability
    // (`.opencode/research-sessions/`, `docs/tickets/`) that Claude's read-only tool bucket
    // (see claude-agent-tool-registry.php) structurally denies via `disallowedTools: Write, Edit`.
    // Neutralize it to a Claude-accurate handoff instruction instead of shipping a false claim.
    // See docs/tickets/claude-agent-fleet-remediation/plan-5-researcher-claude-render-fixes.md.
    if ($agentId === 'researcher') {
        $body = preg_replace(
            '/- May append only research evidence notes to approved evidence paths.*?attributable and minimal\.\n/s',
            "- Cannot append evidence notes directly on Claude Code (frontmatter denies Write/Edit for this agent); hand off write-worthy findings via Final Output instead so the receiving agent can persist them.\n",
            $body
        );

        // researcher's Script Access section frames `pack-context.sh`/repomix as an
        // `ask`-tier option. Claude Code has no `ask` approval tier (see the Bash Command
        // Policy note this renderer already injects), and pack-context.sh is not in the
        // Claude allowlist, so on Claude it is simply not runnable. Neutralize the bullet to
        // match, resolving the leftover contradiction between Script Access and Bash Command
        // Policy. See docs/tickets/claude-agent-fleet-remediation/plan-5-researcher-claude-render-fixes.md.
        $body = preg_replace(
            '/- repomix\/`pack-context\.sh` \(`ask`\)[^\n]*\n/',
            "- repomix/`pack-context.sh` — not runnable on Claude Code (no `ask` approval tier; not in the Claude allowlist). If large-context packing is needed, hand that off rather than attempting it here.\n",
            $body
        );
    }

    // docs' Script Access section frames `ai-edit.sh`/`ai-rollback.sh`/`session-checkpoint.sh`
    // as an `ask`-tier fallback — correct on OpenCode (where the `ask` approval tier exists and
    // these scripts are genuinely allowlisted at `ask`), but false on Claude: the Bash Command
    // Policy section this renderer injects above already states these three scripts are absent
    // from the Claude approved list and that no `ask` tier exists here. Neutralize the bullet
    // for the Claude render only (OpenCode/Copilot keep the ask-tier wording, which is accurate
    // for OpenCode and merely advisory-but-not-contradictory for Copilot), matching the same
    // defect-class fix already applied to researcher above. See agent-critic re-run finding #2,
    // docs/tickets/claude-agent-fleet-remediation/plan-18-docs-agent-claude-render-fix.md.
    if ($agentId === 'docs') {
        $body = str_replace(
            '- `ai-edit.sh` / `ai-rollback.sh` (`ask`) — only when the runtime\'s native file-edit permission is insufficient; `session-checkpoint.sh` (`ask`) for continuity.',
            "- `ai-edit.sh` / `ai-rollback.sh` / `session-checkpoint.sh` are NOT runnable on Claude (see Bash Command Policy above — absent from the approved list, no `ask` tier exists); if the runtime's native file-edit permission is insufficient, stop and report `needs-scope-approval` instead of invoking them.",
            $body
        );
    }

    $rendered = $claudeFm . $bashPolicy . "\n" . $body;

    return aiInstallerInsertGeneratedHeaderAfterFrontmatter(
        $rendered,
        'ai-kit installer (Claude agent renderer) from ' . $sourceLabel
    );
}

/**
 * Derives the repo-relative template-dir label (for the generated-file header) from an absolute
 * or relative source agents directory. Returns the trailing
 * `packages/ai-universal-rules/templates/<tier>/agents` portion when present so optional agents no
 * longer claim a `core/agents` origin; falls back to the core tier for unrecognized paths.
 */
function aiInstallerClaudeAgentSourceLabel(string $src): string
{
    $normalized = str_replace('\\', '/', $src);
    $anchor = 'packages/ai-universal-rules/templates/';
    $pos = strpos($normalized, $anchor);
    if ($pos !== false) {
        return rtrim(substr($normalized, $pos), '/');
    }
    return 'packages/ai-universal-rules/templates/core/agents';
}

/**
 * Copies the source agents directory to dest, rendering each .md file as a Claude sub-agent.
 *
 * Base-writer variant (identity filenames, refresh-in-place for the core adapter-claude pack).
 * The dual-writer case (optional-agents-claude-pack merging into the same dir) routes through
 * aiInstallerMergeDirAsClaudeAgents instead — mirroring the Copilot pair
 * aiInstallerCopyDirAsCopilotAgents / aiInstallerMergeDirAsCopilotAgents.
 *
 * @param string $src         Absolute path to source agents dir (OpenCode templates)
 * @param string $dest        Absolute path to destination dir (.claude/agents)
 * @param string $scriptsRoot Absolute path to scripts/ai/ in the target repo
 */
function aiInstallerCopyDirAsClaudeAgents(string $src, string $dest, string $scriptsRoot): void
{
    aiInstallerRenderClaudeAgentsInto($src, $dest, $scriptsRoot, false);
}

/**
 * Merge variant: renders each source agent into an existing .claude/agents directory WITHOUT
 * deleting the tree, so optional Claude agents coexist with the base adapter-claude agents
 * (no filename overlap between core/agents and optional/agents). Honors skip-if-exists
 * semantics: a destination agent the user already authored is preserved, never overwritten.
 *
 * @param string $src          Absolute path to source agents dir (OpenCode templates)
 * @param string $dest         Absolute path to destination dir (.claude/agents)
 * @param string $scriptsRoot  Absolute path to scripts/ai/ in the target repo
 * @param bool   $skipExisting When true, a pre-existing destination agent file is preserved.
 */
function aiInstallerMergeDirAsClaudeAgents(string $src, string $dest, string $scriptsRoot, bool $skipExisting = true): void
{
    aiInstallerRenderClaudeAgentsInto($src, $dest, $scriptsRoot, $skipExisting);
}

/**
 * Shared render loop for both claude-agents writer variants. Renders every non-hidden source
 * agent template into $dest as <id>.md via the Claude renderer. Never deletes the destination
 * tree; existing files are overwritten in place unless $skipExisting is true.
 *
 * @param string $src          Absolute path to source agents dir (OpenCode templates)
 * @param string $dest         Absolute path to destination dir (.claude/agents)
 * @param string $scriptsRoot  Absolute path to scripts/ai/ in the target repo
 * @param bool   $skipExisting When true, an existing destination .md is preserved.
 */
function aiInstallerRenderClaudeAgentsInto(string $src, string $dest, string $scriptsRoot, bool $skipExisting): void
{
    if (!is_dir($src)) {
        throw new RuntimeException('missing source directory: ' . $src);
    }
    $srcReal  = realpath($src);
    $destReal = file_exists($dest) ? realpath($dest) : false;
    if ($srcReal !== false && $destReal !== false && $srcReal === $destReal) {
        return;
    }
    aiInstallerMkdir($dest);
    $sourceLabel = aiInstallerClaudeAgentSourceLabel($src);

    foreach (glob($src . DIRECTORY_SEPARATOR . '*.md') ?: [] as $srcFile) {
        $agentId = pathinfo($srcFile, PATHINFO_FILENAME);
        $content = (string) file_get_contents($srcFile);
        if (aiAgentIsHiddenInternalOnly($content)) {
            continue;
        }
        $destFile = $dest . DIRECTORY_SEPARATOR . $agentId . '.md';
        if ($skipExisting && file_exists($destFile)) {
            continue;
        }
        $rendered = aiInstallerRenderClaudeAgent($content, $agentId, $scriptsRoot, $sourceLabel);
        if (file_put_contents($destFile, $rendered) === false) {
            throw new RuntimeException('failed to write rendered agent: ' . $destFile);
        }
    }
}
