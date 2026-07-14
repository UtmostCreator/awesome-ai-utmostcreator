<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/tools/ai/install/claude-agent-renderer.php';
require_once dirname(__DIR__, 2) . '/tools/ai/install/core.php';

class ClaudeAgentRendererTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root');
        }
        $this->repoRoot = $root;
    }

    private function architectTemplate(): string
    {
        $path = $this->repoRoot . '/packages/ai-universal-rules/templates/core/agents/architect.md';
        $this->assertFileExists($path, 'architect template must exist');
        return (string) file_get_contents($path);
    }

    private function implementerTemplate(): string
    {
        $path = $this->repoRoot . '/packages/ai-universal-rules/templates/core/agents/implementer.md';
        $this->assertFileExists($path, 'implementer template must exist');
        return (string) file_get_contents($path);
    }

    private function researcherTemplate(): string
    {
        $path = $this->repoRoot . '/packages/ai-universal-rules/templates/core/agents/researcher.md';
        $this->assertFileExists($path, 'researcher template must exist');
        return (string) file_get_contents($path);
    }

    private function reviewerTemplate(): string
    {
        $path = $this->repoRoot . '/packages/ai-universal-rules/templates/core/agents/reviewer.md';
        $this->assertFileExists($path, 'reviewer template must exist');
        return (string) file_get_contents($path);
    }

    private function removeTree(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            unlink($path);
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $this->removeTree($path . DIRECTORY_SEPARATOR . $entry);
        }

        rmdir($path);
    }

    // ----- Architect (read-only, task: allow) -----

    public function testArchitectOutputHasNameField(): void
    {
        $out = aiInstallerRenderClaudeAgent($this->architectTemplate(), 'architect', '/project/scripts/ai');
        $this->assertStringContainsString('name: architect', $out);
    }

    public function testArchitectOutputHasReadOnlyToolsPlusAgent(): void
    {
        $out = aiInstallerRenderClaudeAgent($this->architectTemplate(), 'architect', '/project/scripts/ai');
        if (preg_match('/^tools:\s*(.+)$/m', $out, $m)) {
            $this->assertStringContainsString('Read', $m[1]);
            $this->assertStringContainsString('Grep', $m[1]);
            $this->assertStringContainsString('Glob', $m[1]);
            $this->assertStringContainsString('Bash', $m[1]);
            $this->assertStringContainsString('Agent', $m[1]);
            $this->assertStringNotContainsString('Write', $m[1]);
            $this->assertStringNotContainsString('Edit', $m[1]);
        } else {
            $this->fail('tools: line not found in output');
        }
    }

    public function testArchitectOutputHasDisallowedWriteEdit(): void
    {
        $out = aiInstallerRenderClaudeAgent($this->architectTemplate(), 'architect', '/project/scripts/ai');
        $this->assertMatchesRegularExpression('/^disallowedTools:\s*.*Write.*Edit/m', $out);
    }

    public function testArchitectOutputHasPlanPermissionMode(): void
    {
        $out = aiInstallerRenderClaudeAgent($this->architectTemplate(), 'architect', '/project/scripts/ai');
        $this->assertStringContainsString('permissionMode: plan', $out);
    }

    public function testArchitectOutputHasNoOpenCodeFrontmatterFields(): void
    {
        $out = aiInstallerRenderClaudeAgent($this->architectTemplate(), 'architect', '/project/scripts/ai');
        $this->assertMatchesRegularExpression('/^---\R/m', $out);
        if (preg_match('/^---\R(.*?)\R---\R/s', $out, $fm)) {
            $this->assertStringNotContainsString('id:', $fm[1]);
            $this->assertStringNotContainsString('mode:', $fm[1]);
            $this->assertStringNotContainsString('permission:', $fm[1]);
            $this->assertStringNotContainsString('capabilities:', $fm[1]);
        }
    }

    public function testArchitectOutputHasNoInventedHandoffsField(): void
    {
        // Claude has no structured handoffs field (see docs/ai/integration-matrix.md
        // "Handoff Mechanism Per Runtime"); the renderer must never invent one.
        $out = aiInstallerRenderClaudeAgent($this->architectTemplate(), 'architect', '/project/scripts/ai');
        if (preg_match('/^---\R(.*?)\R---\R/s', $out, $fm)) {
            $this->assertStringNotContainsString('handoffs:', $fm[1]);
        }
    }

    public function testArchitectOutputPreservesOriginalBody(): void
    {
        $out = aiInstallerRenderClaudeAgent($this->architectTemplate(), 'architect', '/project/scripts/ai');
        $this->assertStringContainsString('Design the solution boundary. Do not implement.', $out);
    }

    public function testArchitectOutputHasBashCommandPolicySection(): void
    {
        $out = aiInstallerRenderClaudeAgent($this->architectTemplate(), 'architect', '/project/scripts/ai');
        $this->assertStringContainsString('## Bash Command Policy', $out);
        // Plan-28 Phase 3: the disclaimer states the approved-scripts list is a SUBSET of
        // `.claude/settings.json`'s enforced floor by construction, replacing the pre-Phase-3
        // "if this list and .claude/settings.json disagree, .claude/settings.json wins"
        // contradiction framing (see ClaudeCapabilityFilterTest for the fleet-wide assertion
        // that the old contradiction phrase never appears in any installed agent body).
        $this->assertStringContainsString('SUBSET of `.claude/settings.json`', $out);
    }

    // ----- Implementer (write-capable, no task grant) -----

    public function testImplementerOutputHasWriteEditTools(): void
    {
        $out = aiInstallerRenderClaudeAgent($this->implementerTemplate(), 'implementer', '/project/scripts/ai');
        if (preg_match('/^tools:\s*(.+)$/m', $out, $m)) {
            $this->assertStringContainsString('Write', $m[1]);
            $this->assertStringContainsString('Edit', $m[1]);
            $this->assertStringNotContainsString('Agent', $m[1]);
        } else {
            $this->fail('tools: line not found in output');
        }
    }

    public function testImplementerOutputHasNoDisallowedToolsLine(): void
    {
        $out = aiInstallerRenderClaudeAgent($this->implementerTemplate(), 'implementer', '/project/scripts/ai');
        $this->assertDoesNotMatchRegularExpression('/^disallowedTools:/m', $out);
    }

    public function testImplementerOutputHasDefaultPermissionMode(): void
    {
        $out = aiInstallerRenderClaudeAgent($this->implementerTemplate(), 'implementer', '/project/scripts/ai');
        $this->assertStringContainsString('permissionMode: default', $out);
    }

    public function testClaudeAgentCopyRefreshesWithoutDeletingDestinationTree(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'claude_agent_copy_' . uniqid('', true);
        $src = $tmp . DIRECTORY_SEPARATOR . 'src';
        $dest = $tmp . DIRECTORY_SEPARATOR . 'dest';

        mkdir($src, 0777, true);
        mkdir($dest, 0777, true);
        file_put_contents($src . DIRECTORY_SEPARATOR . 'implementer.md', $this->implementerTemplate());
        file_put_contents($dest . DIRECTORY_SEPARATOR . 'user-authored.md', "---\nname: user-authored\n---\n");

        try {
            aiInstallerCopyDirAsClaudeAgents($src, $dest, '/project/scripts/ai');

            $this->assertFileExists($dest . DIRECTORY_SEPARATOR . 'implementer.md');
            $this->assertFileExists(
                $dest . DIRECTORY_SEPARATOR . 'user-authored.md',
                'Claude agent refresh must not delete sibling/user-authored files'
            );
        } finally {
            $this->removeTree($tmp);
        }
    }

    public function testClaudeAgentMergeHonorsSkipIfExistsAndPreservesTree(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'claude_agent_merge_' . uniqid('', true);
        $src = $tmp . DIRECTORY_SEPARATOR . 'src';
        $dest = $tmp . DIRECTORY_SEPARATOR . 'dest';

        mkdir($src, 0777, true);
        mkdir($dest, 0777, true);
        file_put_contents($src . DIRECTORY_SEPARATOR . 'implementer.md', $this->implementerTemplate());
        file_put_contents($src . DIRECTORY_SEPARATOR . 'researcher.md', $this->researcherTemplate());
        // Pre-existing destination file sharing a source name: must be preserved verbatim.
        $userAuthored = "---\nname: implementer\n---\nuser-authored body\n";
        file_put_contents($dest . DIRECTORY_SEPARATOR . 'implementer.md', $userAuthored);
        // Pre-existing core agent with no source counterpart: must survive untouched.
        file_put_contents($dest . DIRECTORY_SEPARATOR . 'core-agent.md', "---\nname: core-agent\n---\n");

        try {
            aiInstallerMergeDirAsClaudeAgents($src, $dest, '/project/scripts/ai', true);

            $this->assertSame(
                $userAuthored,
                (string) file_get_contents($dest . DIRECTORY_SEPARATOR . 'implementer.md'),
                'skip-if-exists merge must preserve a pre-existing destination agent verbatim'
            );
            $this->assertFileExists(
                $dest . DIRECTORY_SEPARATOR . 'researcher.md',
                'merge must add source agents that have no destination counterpart'
            );
            $this->assertFileExists(
                $dest . DIRECTORY_SEPARATOR . 'core-agent.md',
                'merge must never delete sibling core agents'
            );

            aiInstallerMergeDirAsClaudeAgents($src, $dest, '/project/scripts/ai', false);

            $this->assertNotSame(
                $userAuthored,
                (string) file_get_contents($dest . DIRECTORY_SEPARATOR . 'implementer.md'),
                'non-skip merge refreshes rendered files in place'
            );
        } finally {
            $this->removeTree($tmp);
        }
    }

    // ----- Researcher (read-only, task: ask -> no Agent tool) -----

    public function testResearcherOutputHasNoAgentTool(): void
    {
        $out = aiInstallerRenderClaudeAgent($this->researcherTemplate(), 'researcher', '/project/scripts/ai');
        if (preg_match('/^tools:\s*(.+)$/m', $out, $m)) {
            $this->assertStringNotContainsString('Agent', $m[1]);
        } else {
            $this->fail('tools: line not found in output');
        }
    }

    // Regression: docs/tickets/claude-agent-fleet-remediation/plan-5-researcher-claude-render-fixes.md
    // BLOCKER — the canonical template claims researcher may append evidence notes, which is
    // structurally false on Claude (disallowedTools: Write, Edit, no write-capable Bash entry).
    public function testResearcherOutputDoesNotClaimAppendWriteCapability(): void
    {
        $out = aiInstallerRenderClaudeAgent($this->researcherTemplate(), 'researcher', '/project/scripts/ai');
        $this->assertStringNotContainsString(
            'May append only research evidence notes',
            $out,
            'rendered researcher.md must not claim a Write/Edit-dependent capability the frontmatter denies'
        );
        $this->assertStringContainsString(
            'Cannot append evidence notes directly on Claude Code',
            $out,
            'rendered researcher.md must substitute a Claude-accurate Final Output handoff instruction'
        );
    }

    // Regression: docs/tickets/claude-agent-fleet-remediation/plan-5-researcher-claude-render-fixes.md
    // MAJOR — Script Access framed pack-context.sh as an `ask`-tier option, contradicting the
    // Bash Command Policy that names it non-runnable on Claude (no `ask` tier exists there).
    public function testResearcherScriptAccessDoesNotFramePackContextAsRunnable(): void
    {
        $out = aiInstallerRenderClaudeAgent($this->researcherTemplate(), 'researcher', '/project/scripts/ai');
        $this->assertStringNotContainsString(
            '`pack-context.sh` (`ask`) — only for large context packing',
            $out,
            'rendered researcher.md must not frame pack-context.sh as an ask-tier option Claude cannot honor'
        );
        $this->assertStringContainsString(
            '`pack-context.sh` — not runnable on Claude Code',
            $out,
            'rendered researcher.md must state pack-context.sh is not runnable on Claude'
        );
    }

    // ----- Reviewer (read-only, task: ask -> no Agent tool) -----

    // Regression: docs/tickets/claude-agent-fleet-remediation/plan-21-claude-reviewer-remediation.md
    // MAJOR — Script Access described a `task` (`ask`) delegation capability the frontmatter never
    // grants (no Agent tool for this role), an unreachable instruction carried over from the
    // OpenCode-oriented canonical template without runtime adaptation.
    public function testReviewerScriptAccessDoesNotDescribeUnreachableTaskDelegation(): void
    {
        $out = aiInstallerRenderClaudeAgent($this->reviewerTemplate(), 'reviewer', '/project/scripts/ai');
        $this->assertStringNotContainsString(
            '`task` (`ask`) is only for delegating a bounded, read-only sub-review',
            $out,
            'rendered reviewer.md must not describe a task-delegation capability the frontmatter never grants'
        );
        $this->assertStringContainsString(
            'is an OpenCode-only capability; it is unavailable on Claude for this role',
            $out,
            'rendered reviewer.md must state task delegation is OpenCode-only and unreachable here'
        );
    }

    public function testReviewerOutputHasNoAgentTool(): void
    {
        $out = aiInstallerRenderClaudeAgent($this->reviewerTemplate(), 'reviewer', '/project/scripts/ai');
        if (preg_match('/^tools:\s*(.+)$/m', $out, $m)) {
            $this->assertStringNotContainsString('Agent', $m[1]);
        } else {
            $this->fail('tools: line not found in output');
        }
    }

    // Fixture-testable pass-through: an agent whose registry entry DOES grant the Agent tool
    // (architect) must not have any task-delegation sentence rewritten, since the capability is
    // reachable there. architect's canonical template has no such sentence to begin with, so this
    // proves the fix is scoped to Agent-omitting roles rather than a blanket rewrite.
    public function testArchitectOutputKeepsAgentToolAndIsUnaffectedByTaskRewrite(): void
    {
        $out = aiInstallerRenderClaudeAgent($this->architectTemplate(), 'architect', '/project/scripts/ai');
        if (preg_match('/^tools:\s*(.+)$/m', $out, $m)) {
            $this->assertStringContainsString('Agent', $m[1]);
        } else {
            $this->fail('tools: line not found in output');
        }
        $this->assertStringNotContainsString(
            'is an OpenCode-only capability; it is unavailable on Claude for this role',
            $out,
            'architect grants Agent, so the task-delegation rewrite must not fire for it'
        );
    }

    // ----- SCRIPTS_ROOT placeholder -----

    public function testScriptsRootPlaceholderAppearsInBashCommandPolicy(): void
    {
        $out = aiInstallerRenderClaudeAgent($this->architectTemplate(), 'architect', '/project/scripts/ai');
        $this->assertStringContainsString('<SCRIPTS_ROOT>', $out);
    }

    public function testScriptsRootResolvedAfterPlaceholderReplacement(): void
    {
        $out = aiInstallerRenderClaudeAgent($this->architectTemplate(), 'architect', '/project/scripts/ai');
        $resolved = str_replace('<SCRIPTS_ROOT>', '/project/scripts/ai', $out);
        $this->assertStringContainsString('/project/scripts/ai', $resolved);
        $this->assertStringNotContainsString('<SCRIPTS_ROOT>', $resolved);
    }

    // ----- Tool registry -----

    public function testToolRegistryCoversAllAgentTemplates(): void
    {
        $registry = aiClaudeAgentToolRegistry();
        $templateDir = $this->repoRoot . '/packages/ai-universal-rules/templates/core/agents';
        foreach (glob($templateDir . '/*.md') ?: [] as $tpl) {
            $content = (string) file_get_contents($tpl);
            if (preg_match('/^---\R(.*?)\R---/s', $content, $fm) && preg_match('/^hidden:\s*true\s*$/m', $fm[1])) {
                continue;
            }
            $agentId = pathinfo($tpl, PATHINFO_FILENAME);
            $this->assertArrayHasKey(
                $agentId,
                $registry,
                "Agent '{$agentId}' is not in the Claude tool registry — add it to claude-agent-tool-registry.php"
            );
        }
    }

    // ----- Installed agent files -----

    public function testInstalledAgentFilesAreClaudeNativeFormat(): void
    {
        $agentsDir = $this->repoRoot . '/.claude/agents';
        if (!is_dir($agentsDir)) {
            $this->markTestSkipped('.claude/agents not installed yet');
        }
        foreach (glob($agentsDir . '/*.md') ?: [] as $agentFile) {
            $content = (string) file_get_contents($agentFile);
            $name = basename($agentFile);
            $this->assertStringContainsString('name:', $content, "Installed agent '{$name}' must have 'name:' frontmatter");
            $this->assertStringContainsString('description:', $content, "Installed agent '{$name}' must have 'description:' frontmatter");
            $this->assertStringNotContainsString("\nid:", $content, "Installed agent '{$name}' must not have OpenCode 'id:' field");
            $this->assertStringNotContainsString('permission:', $content, "Installed agent '{$name}' must not have OpenCode 'permission:' block");
            $this->assertStringNotContainsString('handoffs:', $content, "Installed agent '{$name}' must not invent a handoffs field");
        }
    }

    // ----- Optional agent_assessment rubric projection -----

    public function testAssessmentBlockRoundTripsWhenPresent(): void
    {
        $src = "---\nid: sample\ndescription: demo\nagent_assessment:\n  risk_level: high\n  score: 80\n---\nbody\n";
        $out = aiInstallerRenderClaudeAgent($src, 'sample', '/project/scripts/ai');
        $this->assertMatchesRegularExpression('/^agent_assessment:\R\s+risk_level: high\R\s+score: 80/m', $out);
        $fmEnd = strpos($out, "\n---\n");
        $this->assertNotFalse($fmEnd);
        $this->assertStringContainsString('agent_assessment:', substr($out, 0, $fmEnd));
    }

    public function testNoAssessmentBlockWhenAbsentFromTemplate(): void
    {
        $src = "---\nid: sample\ndescription: demo\nmode: subagent\n---\nbody\n";
        $out = aiInstallerRenderClaudeAgent($src, 'sample', '/project/scripts/ai');
        $this->assertStringNotContainsString('agent_assessment', $out);
    }

    public function testLiveArchitectTemplateProjectsPilotRubric(): void
    {
        $out = aiInstallerRenderClaudeAgent($this->architectTemplate(), 'architect', '/project/scripts/ai');
        $this->assertStringContainsString('agent_assessment:', $out);
        $this->assertStringContainsString('risk_level: high', $out);
    }

    // ----- Bash tool granted but no per-command allowlist (fallback body text) -----

    public function testFallbackBashPolicyWhenNoAllowlistPresent(): void
    {
        $src = "---\nid: sample\ndescription: demo\nmode: subagent\npermission:\n  edit: deny\n  task: ask\n---\nbody\n";
        $out = aiInstallerRenderClaudeAgent($src, 'sample', '/project/scripts/ai');
        $this->assertStringContainsString('## Bash Command Policy', $out);
        $this->assertStringContainsString('docs/ai/script-registry.md', $out);
    }
}
