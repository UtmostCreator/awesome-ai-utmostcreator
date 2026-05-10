<?php

declare(strict_types=1);

require_once __DIR__ . '/copilot-agent-tool-registry.php';

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
    // --- Extract OpenCode frontmatter ---
    $frontMatter = [];
    $body = $srcContent;
    if (preg_match('/^---\R(.*?)\R---\R?/s', $srcContent, $fm)) {
        $rawFm = $fm[1];
        $body = substr($srcContent, strlen($fm[0]));

        // Parse simple key: value lines (not nested YAML — we only need top-level scalars)
        foreach (explode("\n", $rawFm) as $line) {
            if (preg_match('/^(\w[\w-]*):\s+(.+)$/', trim($line), $m)) {
                $frontMatter[$m[1]] = trim($m[2]);
            }
        }

        // Collect allowed bash commands for the policy section
        $allowedBash = [];
        if (preg_match('/bash:\s*\R((?:\s+.+\R)*)/s', $rawFm, $bashMatch)) {
            foreach (explode("\n", $bashMatch[1]) as $bashLine) {
                if (preg_match("/^\\s+'([^']+)':\\s*allow/", $bashLine, $bm)) {
                    $cmd = $bm[1];
                    if ($cmd !== '*') {
                        $allowedBash[] = $cmd;
                    }
                }
            }
        }
    } else {
        $allowedBash = [];
    }

    $id          = $frontMatter['id'] ?? $agentId;
    $description = $frontMatter['description'] ?? '';
    $tools       = aiCopilotAgentTools($id);
    $toolsYaml   = '[\'' . implode("', '", $tools) . "']";

    // Format agent name: title-case from kebab-case
    $name = implode(' ', array_map('ucfirst', explode('-', $id)));

    // --- Build Copilot frontmatter ---
    $copilotFm  = "---\n";
    $copilotFm .= "name: {$name}\n";
    $copilotFm .= "description: '{$description}'\n";
    $copilotFm .= "tools: {$toolsYaml}\n";
    $copilotFm .= "user-invocable: true\n";
    $copilotFm .= "disable-model-invocation: false\n";
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
        $shellBoundary .= "Run scripts from the repository root using repository-root paths. Do not run arbitrary commands.\n";
    }

    // --- Combine: Copilot frontmatter + enforcement + original body + shell boundary ---
    $body = ltrim($body);
    return $copilotFm . $enforcement . $shellBoundary . "\n" . $body;
}

/**
 * Copies the source agents directory to dest, rendering each .md file as a Copilot .agent.md.
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
    if (file_exists($dest)) {
        aiInstallerDeleteTree($dest);
    }
    aiInstallerMkdir($dest);

    foreach (glob($src . DIRECTORY_SEPARATOR . '*.md') ?: [] as $srcFile) {
        $agentId  = pathinfo($srcFile, PATHINFO_FILENAME);
        $content  = (string) file_get_contents($srcFile);
        if (aiAgentIsHiddenInternalOnly($content)) {
            continue;
        }
        $rendered = aiInstallerRenderCopilotAgent($content, $agentId, $scriptsRoot);
        $destFile = $dest . DIRECTORY_SEPARATOR . $agentId . '.agent.md';
        if (file_put_contents($destFile, $rendered) === false) {
            throw new RuntimeException('failed to write rendered agent: ' . $destFile);
        }
    }
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
    if (file_exists($dest)) {
        aiInstallerDeleteTree($dest);
    }
    aiInstallerMkdir($dest);

    foreach (glob($src . DIRECTORY_SEPARATOR . '*.md') ?: [] as $srcFile) {
        $content = (string) file_get_contents($srcFile);
        if (aiAgentIsHiddenInternalOnly($content)) {
            continue;
        }
        $destFile = $dest . DIRECTORY_SEPARATOR . basename($srcFile);
        if (file_put_contents($destFile, $content) === false) {
            throw new RuntimeException('failed to copy agent: ' . $destFile);
        }
    }
}
