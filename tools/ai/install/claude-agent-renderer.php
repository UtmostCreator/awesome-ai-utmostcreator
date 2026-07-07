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
        $bashPolicy .= "tool-level `Bash` grant above. Treat the following as the enforced boundary anyway.\n\n";
        $bashPolicy .= "Approved scripts (run from the repository root using `<SCRIPTS_ROOT>`):\n\n";
        foreach ($allowedBash as $cmd) {
            $displayCmd = preg_replace('/\bscripts\/ai\//', '<SCRIPTS_ROOT>/', $cmd);
            $bashPolicy .= "- `{$displayCmd}`\n";
        }
        $bashPolicy .= "\nDo not run arbitrary shell commands. Do not run commands not in this list.\n";
        $bashPolicy .= "Any script this file's prose describes as `ask`-tier (e.g. `ai-verify.sh`, `ai-edit.sh`,\n";
        $bashPolicy .= "`ai-rollback.sh`, `session-checkpoint.sh`, `pack-context.sh`) is NOT runnable here unless it\n";
        $bashPolicy .= "also appears in the list above — the OpenCode `ask` approval tier does not exist on Claude.\n";
        $bashPolicy .= "Do not run: `rm`, `mv`, `cp`, `chmod`, `curl | sh`, install commands, unregistered `scripts/ai/*.sh`, `git push`, `git reset`, deploy commands.\n\n";
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
