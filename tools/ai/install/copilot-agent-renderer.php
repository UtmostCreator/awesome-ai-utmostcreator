<?php

declare(strict_types=1);

require_once __DIR__ . '/copilot-agent-tool-registry.php';
require_once __DIR__ . '/copilot-agent-handoff-registry.php';
require_once __DIR__ . '/generated-header.php';
require_once __DIR__ . '/canonical-agent-frontmatter.php';
require_once __DIR__ . '/permission-layers/render-adapters.php';

/**
 * Renders a canonical OpenCode agent template as a Copilot VS Code-native .agent.md file.
 *
 * The canonical source (OpenCode format) uses: id, mode, temperature, capabilities, permission blocks.
 * Copilot VS Code format requires: name, description, tools, user-invocable.
 *
 * Per-command bash allowlists cannot be expressed in Copilot .agent.md frontmatter; they are
 * converted to a behavioural policy section in the body.
 *
 * @param string $srcContent   Full content of the OpenCode agent .md template
 * @param string $agentId      Agent ID (e.g. 'architect') — used for tool registry lookup
 * @param string $scriptsRoot  Repository-root scripts path placeholder target (e.g. 'scripts/ai')
 * @return string              Rendered Copilot .agent.md content
 */
function aiInstallerRenderCopilotAgent(string $srcContent, string $agentId, string $scriptsRoot): string
{
    // --- Extract OpenCode frontmatter (shared parser; see canonical-agent-frontmatter.php) ---
    $parsed      = aiInstallerParseCanonicalAgentFrontmatter($srcContent);
    $rawFm       = $parsed['rawFm'];
    $body        = $parsed['body'];
    $frontMatter = $parsed['frontMatter'];
    // Slice 8 adapter seam: for agents with a registered permission composition
    // (aiPermissionAgentCompositions(), keyed by filename stem), project allowedBash from the
    // composed model instead of re-parsing frontmatter text (the legacy parser is
    // single-quote-only and silently returns an empty list for any agent rendered with
    // `quote: 'double'`, e.g. researcher.md since Slice 2). Not-yet-migrated agents fall back
    // to the legacy parsed list unchanged.
    $allowedBash = aiPermissionResolveAllowedBash($agentId, $parsed['allowedBash']);

    $id          = $frontMatter['id'] ?? $agentId;
    $description = $frontMatter['description'] ?? '';
    $tools       = aiCopilotAgentTools($id);
    $toolsYaml   = '[\'' . implode("', '", $tools) . "']";

    // Optional agent_assessment rubric: carried through from the source template only
    // when present, preserving keys/values exactly. Absent in the template -> absent here.
    $assessmentBlock = $rawFm !== '' ? aiCopilotExtractAssessmentBlock($rawFm) : '';

    // Optional handoffs: chain, sourced from the registry keyed by agent ID (see
    // copilot-agent-handoff-registry.php). Additive on top of the prose "Recommended next
    // step" sentence carried through unchanged in the body — never a replacement for it
    // (docs/ai/integration-matrix.md "Handoff Mechanism Per Runtime"). Absent from the
    // registry -> absent here, so unregistered agents render byte-identically to before.
    $handoffsBlock = aiCopilotRenderHandoffsBlock(aiCopilotAgentHandoffsFor($id));

    // Format agent name: title-case from kebab-case
    $name = implode(' ', array_map('ucfirst', explode('-', $id)));

    // --- Build Copilot frontmatter ---
    $copilotFm  = "---\n";
    $copilotFm .= "name: {$name}\n";
    $copilotFm .= "description: '{$description}'\n";
    $copilotFm .= "tools: {$toolsYaml}\n";
    $copilotFm .= "user-invocable: true\n";
    $copilotFm .= "disable-model-invocation: false\n";
    $copilotFm .= $assessmentBlock;
    $copilotFm .= $handoffsBlock;
    $copilotFm .= "---\n";

    // --- Build enforcement boundary section ---
    $hasExecute = false;
    $hasEdit = false;
    foreach ($tools as $tool) {
        if (str_starts_with($tool, 'execute/')) {
            $hasExecute = true;
        }
        if (str_starts_with($tool, 'edit/')) {
            $hasEdit = true;
        }
    }
    $editStatus    = $hasEdit    ? 'available' : 'not available — this agent is read-only';
    $executeStatus = $hasExecute ? 'available — constrained by the Shell Boundary below' : 'not available — this agent is read-only';

    $enforcement  = "\n## Enforcement Boundary\n\n";
    $enforcement .= "This agent is configured for the GitHub Copilot VS Code surface.\n\n";
    $enforcement .= "Available tools: `" . implode('`, `', $tools) . "`\n\n";
    $enforcement .= "- **Edit:** {$editStatus}\n";
    $enforcement .= "- **Execute:** {$executeStatus}\n\n";

    if (!$hasEdit && !$hasExecute) {
        $enforcement .= "This agent is strictly read-only. It must not edit files, run shell commands, ";
        $enforcement .= "execute scripts, create commits, or claim that verification was executed.\n\n";
        $enforcement .= "If the task requires file edits, command execution, or repository mutation, ";
        $enforcement .= "produce a handoff plan instead of performing the action.\n";
    }

    // --- Build shell boundary section (only for execute agents) ---
    $shellBoundary = '';
    if ($hasExecute && $allowedBash !== []) {
        $shellBoundary  = "\n## Shell Boundary\n\n";
        $shellBoundary .= "You may use shell execution only for approved scripts from the repository registry. ";
        $shellBoundary .= "Before running any script:\n\n";
        $shellBoundary .= "1. Confirm the script exists in the repository.\n";
        $shellBoundary .= "2. Confirm it is listed in `docs/ai/script-registry.md` and `docs/ai/script-registry.json`.\n";
        $shellBoundary .= "3. Confirm it is also documented in `docs/ai/scripts-reference.md`.\n";
        $shellBoundary .= "4. Run it from the repository root using the repository-root path shown below.\n";
        $shellBoundary .= "5. If any condition fails, stop and report `unknown`.\n\n";
        $shellBoundary .= "Treat `scripts/ai/pre-tool-use.sh` as the canonical pre-execution policy gate and `scripts/ai/post-tool-use.sh` as the canonical post-execution evidence writer.\n";
        $shellBoundary .= "When the active runtime supports repository hooks, these scripts must remain wired through `.github/hooks/tool-policy.json` and write local evidence under `.ai-logs/` as documented in `.ai-logs/README.md`.\n";
        $shellBoundary .= "When the runtime does not auto-load repository hooks, preserve the same boundary manually and do not claim automatic enforcement.\n\n";
        $shellBoundary .= "Approved scripts (run from the repository root using `<SCRIPTS_ROOT>`):\n\n";
        foreach ($allowedBash as $cmd) {
            // Substitute scripts/ai/ with the repository-root scripts path placeholder.
            $displayCmd = preg_replace('/\bscripts\/ai\//', '<SCRIPTS_ROOT>/', $cmd);
            $shellBoundary .= "- `{$displayCmd}`\n";
        }
        $shellBoundary .= "\nDo not run arbitrary shell commands. Do not run commands not in this list.\n";
        $shellBoundary .= "Do not run: `rm`, `mv`, `cp`, `chmod`, `curl | sh`, install commands, unregistered `scripts/ai/*.sh`, `git push`, `git reset`, deploy commands.\n";
    } elseif ($hasExecute) {
        $shellBoundary  = "\n## Shell Boundary\n\n";
        $shellBoundary .= "Only use shell execution for approved scripts listed in `docs/ai/script-registry.md`, `docs/ai/script-registry.json`, and `docs/ai/scripts-reference.md`.\n";
        $shellBoundary .= "Treat `scripts/ai/pre-tool-use.sh` as the canonical pre-execution policy gate and `scripts/ai/post-tool-use.sh` as the canonical post-execution evidence writer; if hooks are unsupported on the active surface, preserve the same boundary manually and treat `.ai-logs/README.md` as the checked-in evidence contract.\n";
        $shellBoundary .= "Run scripts from the repository root using `<SCRIPTS_ROOT>/...` paths (resolved by installer). Do not run arbitrary commands.\n";
    }

    // --- Combine: Copilot frontmatter + enforcement + original body + shell boundary ---
    $body = ltrim($body);
    $rendered = $copilotFm . $enforcement . $shellBoundary . "\n" . $body;

    // P3: hard GENERATED marker placed AFTER the closing frontmatter `---` so the
    // YAML frontmatter stays parseable. Idempotent (will not double-insert).
    return aiInstallerInsertGeneratedHeaderAfterFrontmatter(
        $rendered,
        'ai-kit installer (Copilot agent renderer) from packages/ai-universal-rules/templates/core/agents'
    );
}

/**
 * Copies the source agents directory to dest, rendering each .md file as a Copilot .agent.md.
 *
 * This is the refresh variant used by the base adapter-copilot pack: each rendered agent file is
 * overwritten in place so diffs remain reviewable and sibling/user files are not deleted.
 * Optional Copilot agents that share the same .github/agents target may also use
 * aiInstallerMergeDirAsCopilotAgents when they need skip-if-exists semantics.
 *
 * @param string $src         Absolute path to source agents dir (OpenCode templates)
 * @param string $dest        Absolute path to destination dir (.github/agents)
 * @param string $scriptsRoot Absolute path to scripts/ai/ in the target repo
 */
function aiInstallerCopyDirAsCopilotAgents(string $src, string $dest, string $scriptsRoot): void
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

    aiInstallerRenderCopilotAgentsInto($src, $dest, $scriptsRoot, false);
}

/**
 * Merge variant: renders each source agent into an existing .github/agents directory WITHOUT
 * deleting the tree, so optional Copilot agents coexist with the base adapter-copilot agents
 * (no filename overlap between core/agents and optional/agents). Honors skip-if-exists semantics:
 * a destination agent the user already authored is preserved, never overwritten.
 *
 * @param string $src         Absolute path to source agents dir (OpenCode templates)
 * @param string $dest        Absolute path to destination dir (.github/agents)
 * @param string $scriptsRoot Absolute path to scripts/ai/ in the target repo
 * @param bool   $skipExisting When true, a pre-existing destination agent file is preserved.
 */
function aiInstallerMergeDirAsCopilotAgents(string $src, string $dest, string $scriptsRoot, bool $skipExisting = true): void
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

    aiInstallerRenderCopilotAgentsInto($src, $dest, $scriptsRoot, $skipExisting);
}

/**
 * Shared render loop for both copilot-agents copy variants. Renders every non-hidden source
 * agent template into $dest as <id>.agent.md via the Copilot renderer. Existing files are
 * overwritten in place unless $skipExisting is true.
 *
 * @param string $src          Absolute path to source agents dir (OpenCode templates)
 * @param string $dest         Absolute path to destination dir (.github/agents); must already exist
 * @param string $scriptsRoot  Absolute path to scripts/ai/ in the target repo
 * @param bool   $skipExisting When true, an existing destination .agent.md is preserved.
 */
function aiInstallerRenderCopilotAgentsInto(string $src, string $dest, string $scriptsRoot, bool $skipExisting): void
{
    foreach (glob($src . DIRECTORY_SEPARATOR . '*.md') ?: [] as $srcFile) {
        $agentId  = pathinfo($srcFile, PATHINFO_FILENAME);
        $content  = (string) file_get_contents($srcFile);
        if (aiAgentIsHiddenInternalOnly($content)) {
            continue;
        }
        $destFile = $dest . DIRECTORY_SEPARATOR . $agentId . '.agent.md';
        if ($skipExisting && is_file($destFile)) {
            continue;
        }
        $rendered = aiInstallerRenderCopilotAgent($content, $agentId, $scriptsRoot);
        if (file_put_contents($destFile, $rendered) === false) {
            throw new RuntimeException('failed to write rendered agent: ' . $destFile);
        }
    }
}

/**
 * Extracts the OPTIONAL `agent_assessment:` mapping from raw OpenCode frontmatter and
 * re-emits it as a normalized YAML block (without comment lines) suitable for inclusion
 * in the rebuilt Copilot frontmatter. Returns '' when the block is absent, so agents
 * without a rubric render byte-identically to the prior behavior.
 *
 * Only known scalar key: value pairs under the block are carried; the block ends at the
 * first non-indented, non-comment line.
 */
function aiCopilotExtractAssessmentBlock(string $rawFm): string
{
    $lines = explode("\n", $rawFm);
    $inBlock = false;
    $entries = [];
    foreach ($lines as $line) {
        if (preg_match('/^agent_assessment:\s*$/', trim($line))) {
            $inBlock = true;
            continue;
        }
        if (!$inBlock) {
            continue;
        }
        // End the block at the first non-indented, non-blank, non-comment line.
        if (rtrim($line) !== '' && !preg_match('/^\s/', $line) && !preg_match('/^\s*#/', $line)) {
            break;
        }
        if (trim($line) === '' || preg_match('/^\s*#/', $line)) {
            continue;
        }
        if (preg_match('/^\s+([\w-]+):\s*(.+?)\s*$/', $line, $m)) {
            $entries[] = "  {$m[1]}: {$m[2]}";
        }
    }

    if (!$inBlock || $entries === []) {
        return '';
    }

    return "agent_assessment:\n" . implode("\n", $entries) . "\n";
}

/**
 * Renders the registered Copilot `handoffs:` chain (see copilot-agent-handoff-registry.php)
 * as a YAML block suitable for inclusion in the rebuilt Copilot frontmatter. Returns '' when
 * the agent has no registered chain, so agents without one render byte-identically to prior
 * behavior. Shape per entry (`label`/`agent`/`prompt`/`send`/`model`) matches VS Code's
 * Custom Agents `handoffs:` schema (docs/ai/integration-matrix.md "Handoff Mechanism Per
 * Runtime").
 *
 * @param list<array{label: string, agent: string, prompt: string, send: bool, model: null}> $handoffs
 */
function aiCopilotRenderHandoffsBlock(array $handoffs): string
{
    if ($handoffs === []) {
        return '';
    }

    $lines = ['handoffs:'];
    foreach ($handoffs as $handoff) {
        $lines[] = "  - label: '" . str_replace("'", "''", (string) $handoff['label']) . "'";
        $lines[] = "    agent: '" . str_replace("'", "''", (string) $handoff['agent']) . "'";
        $lines[] = "    prompt: '" . str_replace("'", "''", (string) $handoff['prompt']) . "'";
        $lines[] = '    send: ' . (($handoff['send'] ?? false) ? 'true' : 'false');
        $lines[] = '    model: ' . (($handoff['model'] ?? null) === null ? 'null' : (string) $handoff['model']);
    }

    return implode("\n", $lines) . "\n";
}

/**
 * Returns true when the agent template frontmatter has hidden: true.
 * Hidden agents are internal to this kit repo and must not be shipped to installed projects.
 */
function aiAgentIsHiddenInternalOnly(string $content): bool
{
    if (!preg_match('/^---\R(.*?)\R---/s', $content, $m)) {
        return false;
    }

    foreach (explode("\n", $m[1]) as $line) {
        if (preg_match('/^hidden:\s*true\s*$/', trim($line))) {
            return true;
        }
    }

    return false;
}

/**
 * Copies the source agents directory to dest as raw OpenCode agents, skipping hidden (internal-only) agents.
 *
 * @param string $src  Absolute path to source agents dir
 * @param string $dest Absolute path to destination dir (.opencode/agents)
 */
function aiInstallerCopyDirAsOpenCodeAgents(string $src, string $dest): void
{
    if (!is_dir($src)) {
        throw new RuntimeException('missing source directory: ' . $src);
    }
    $srcReal  = realpath($src);
    $destReal = file_exists($dest) ? realpath($dest) : false;
    if ($srcReal !== false && $destReal !== false && $srcReal === $destReal) {
        return;
    }
    $preservedHiddenAgents = [];
    if (is_dir($dest)) {
        foreach (glob($dest . DIRECTORY_SEPARATOR . '*.md') ?: [] as $existingFile) {
            $existingContent = (string) file_get_contents($existingFile);
            if (aiAgentIsHiddenInternalOnly($existingContent)) {
                $preservedHiddenAgents[basename($existingFile)] = $existingContent;
            }
        }
    }

    if (file_exists($dest)) {
        aiInstallerDeleteTree($dest);
    }
    aiInstallerMkdir($dest);

    foreach (glob($src . DIRECTORY_SEPARATOR . '*.md') ?: [] as $srcFile) {
        $content = (string) file_get_contents($srcFile);
        if (aiAgentIsHiddenInternalOnly($content)) {
            continue;
        }
        // P3: hard GENERATED marker after the YAML frontmatter so the shipped
        // OpenCode agent stays parseable. Idempotent.
        $content = aiInstallerInsertGeneratedHeaderAfterFrontmatter(
            $content,
            'ai-kit installer from packages/ai-universal-rules/templates/core/agents'
        );
        $destFile = $dest . DIRECTORY_SEPARATOR . basename($srcFile);
        if (file_put_contents($destFile, $content) === false) {
            throw new RuntimeException('failed to copy agent: ' . $destFile);
        }
    }

    foreach ($preservedHiddenAgents as $name => $content) {
        $destFile = $dest . DIRECTORY_SEPARATOR . $name;
        if (file_put_contents($destFile, $content) === false) {
            throw new RuntimeException('failed to preserve hidden agent: ' . $destFile);
        }
    }
}
