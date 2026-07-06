<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/tools/ai/install/copilot-agent-renderer.php';
require_once dirname(__DIR__, 2) . '/tools/ai/install/core.php';

class CopilotAgentRendererTest extends TestCase
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

    private function bugfixTemplate(): string
    {
        $path = $this->repoRoot . '/packages/ai-universal-rules/templates/optional/agents/bugfix.md';
        $this->assertFileExists($path, 'bugfix template must exist');
        return (string) file_get_contents($path);
    }

    private function architecturePlanWriterTemplate(): string
    {
        $path = $this->repoRoot . '/packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md';
        $this->assertFileExists($path, 'architecture-plan-writer template must exist');
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

    // ----- Architect (read-only) -----

    public function testArchitectOutputHasNameField(): void
    {
        $out = aiInstallerRenderCopilotAgent($this->architectTemplate(), 'architect', '/project/scripts/ai');
        $this->assertStringContainsString('name: Architect', $out);
    }

    public function testArchitectOutputHasToolsReadSearch(): void
    {
        $out = aiInstallerRenderCopilotAgent($this->architectTemplate(), 'architect', '/project/scripts/ai');
        $this->assertStringContainsString("'search/changes'", $out);
        $this->assertStringContainsString("'search/codebase'", $out);
        $this->assertStringContainsString("'read/readFile'", $out);
        $this->assertStringContainsString("'read/problems'", $out);
        $this->assertStringContainsString("'vscode/askQuestions'", $out);
    }

    public function testArchitectOutputHasNoExecuteTool(): void
    {
        $out = aiInstallerRenderCopilotAgent($this->architectTemplate(), 'architect', '/project/scripts/ai');
        // tools line must not contain any execute/* tool
        if (preg_match('/^tools:\s*(.+)$/m', $out, $m)) {
            $this->assertStringNotContainsString('execute/', $m[1]);
            $this->assertStringNotContainsString("'read'", $m[1]);
            $this->assertStringNotContainsString("'search'", $m[1]);
        }
    }

    public function testArchitectOutputHasNoOpenCodeFrontmatterFields(): void
    {
        $out = aiInstallerRenderCopilotAgent($this->architectTemplate(), 'architect', '/project/scripts/ai');
        // Extract frontmatter block
        $this->assertMatchesRegularExpression('/^---\R/m', $out);
        if (preg_match('/^---\R(.*?)\R---\R/s', $out, $fm)) {
            $this->assertStringNotContainsString('id:', $fm[1]);
            $this->assertStringNotContainsString('mode:', $fm[1]);
            $this->assertStringNotContainsString('permission:', $fm[1]);
            $this->assertStringNotContainsString('capabilities:', $fm[1]);
        }
    }

    public function testArchitectOutputHasUserInvocable(): void
    {
        $out = aiInstallerRenderCopilotAgent($this->architectTemplate(), 'architect', '/project/scripts/ai');
        $this->assertStringContainsString('user-invocable: true', $out);
    }

    public function testArchitectOutputHasEnforcementBoundarySection(): void
    {
        $out = aiInstallerRenderCopilotAgent($this->architectTemplate(), 'architect', '/project/scripts/ai');
        $this->assertStringContainsString('## Enforcement Boundary', $out);
    }

    public function testArchitectOutputHasNoShellBoundarySection(): void
    {
        // Architect has no execute tool so no Shell Boundary section
        $out = aiInstallerRenderCopilotAgent($this->architectTemplate(), 'architect', '/project/scripts/ai');
        $this->assertStringNotContainsString('## Shell Boundary', $out);
    }

    public function testArchitectOutputPreservesOriginalBody(): void
    {
        $out = aiInstallerRenderCopilotAgent($this->architectTemplate(), 'architect', '/project/scripts/ai');
        $this->assertStringContainsString('Design the solution boundary. Do not implement.', $out);
    }

    // ----- Implementer (edit + execute) -----

    public function testImplementerOutputHasEditAndExecuteTools(): void
    {
        $out = aiInstallerRenderCopilotAgent($this->implementerTemplate(), 'implementer', '/project/scripts/ai');
        if (preg_match('/^tools:\s*(.+)$/m', $out, $m)) {
            $this->assertStringContainsString('edit/editFiles', $m[1]);
            $this->assertStringContainsString('edit/createFile', $m[1]);
            $this->assertStringContainsString('edit/createDirectory', $m[1]);
            $this->assertStringNotContainsString('delete', strtolower($m[1]));
            $this->assertStringContainsString('execute/runInTerminal', $m[1]);
            $this->assertStringContainsString('execute/testFailure', $m[1]);
        } else {
            $this->fail('tools: line not found in output');
        }
    }

    public function testImplementerOutputPrefersInPlaceEdits(): void
    {
        $out = aiInstallerRenderCopilotAgent($this->implementerTemplate(), 'implementer', '/project/scripts/ai');
        $this->assertStringContainsString('Prefer in-place file edits over deleting and recreating files', $out);
    }

    public function testImplementerOutputHasShellBoundarySection(): void
    {
        $out = aiInstallerRenderCopilotAgent($this->implementerTemplate(), 'implementer', '/project/scripts/ai');
        $this->assertStringContainsString('## Shell Boundary', $out);
    }

    public function testBugfixOutputHasEditAndExecuteTools(): void
    {
        $out = aiInstallerRenderCopilotAgent($this->bugfixTemplate(), 'bugfix', '/project/scripts/ai');
        if (preg_match('/^tools:\s*(.+)$/m', $out, $m)) {
            $this->assertStringContainsString('edit/editFiles', $m[1]);
            $this->assertStringContainsString('edit/createFile', $m[1]);
            $this->assertStringContainsString('execute/runInTerminal', $m[1]);
        } else {
            $this->fail('tools: line not found in optional bugfix output');
        }
    }

    public function testCopilotAgentCopyRefreshesWithoutDeletingDestinationTree(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copilot_agent_copy_' . uniqid('', true);
        $src = $tmp . DIRECTORY_SEPARATOR . 'src';
        $dest = $tmp . DIRECTORY_SEPARATOR . 'dest';

        mkdir($src, 0777, true);
        mkdir($dest, 0777, true);
        file_put_contents($src . DIRECTORY_SEPARATOR . 'implementer.md', $this->implementerTemplate());
        file_put_contents($dest . DIRECTORY_SEPARATOR . 'user-authored.agent.md', "---\nname: User Authored\n---\n");

        try {
            aiInstallerCopyDirAsCopilotAgents($src, $dest, '/project/scripts/ai');

            $this->assertFileExists($dest . DIRECTORY_SEPARATOR . 'implementer.agent.md');
            $this->assertFileExists(
                $dest . DIRECTORY_SEPARATOR . 'user-authored.agent.md',
                'Copilot agent refresh must not delete sibling/user-authored files'
            );
        } finally {
            $this->removeTree($tmp);
        }
    }

    // ----- Researcher (execute, no edit) -----

    public function testResearcherOutputHasExecuteButNotEdit(): void
    {
        $out = aiInstallerRenderCopilotAgent($this->researcherTemplate(), 'researcher', '/project/scripts/ai');
        if (preg_match('/^tools:\s*(.+)$/m', $out, $m)) {
            $this->assertStringContainsString('execute/runInTerminal', $m[1]);
            $this->assertStringNotContainsString('edit/', $m[1]);
        } else {
            $this->fail('tools: line not found in output');
        }
    }

    // ----- SCRIPTS_ROOT placeholder -----

    public function testScriptsRootPlaceholderAppearsInShellBoundary(): void
    {
        $out = aiInstallerRenderCopilotAgent($this->researcherTemplate(), 'researcher', '/project/scripts/ai');
        $this->assertStringContainsString('<SCRIPTS_ROOT>', $out);
    }

    public function testScriptsRootResolvedAfterPlaceholderReplacement(): void
    {
        $out = aiInstallerRenderCopilotAgent($this->researcherTemplate(), 'researcher', '/project/scripts/ai');
        // Simulate the placeholder being resolved by the installer
        $resolved = str_replace('<SCRIPTS_ROOT>', '/project/scripts/ai', $out);
        $this->assertStringContainsString('/project/scripts/ai', $resolved);
        $this->assertStringNotContainsString('<SCRIPTS_ROOT>', $resolved);
    }

    // ----- Tool registry -----

    public function testToolRegistryCoversAllAgentTemplates(): void
    {
        $registry = aiCopilotAgentToolRegistry();
        $templateDir = $this->repoRoot . '/packages/ai-universal-rules/templates/core/agents';
        foreach (glob($templateDir . '/*.md') ?: [] as $tpl) {
            $content = (string) file_get_contents($tpl);
            // Hidden agents are internal-only and not rendered by the installer; skip them.
            if (preg_match('/^---\R(.*?)\R---/s', $content, $fm) && preg_match('/^hidden:\s*true\s*$/m', $fm[1])) {
                continue;
            }
            $agentId = pathinfo($tpl, PATHINFO_FILENAME);
            $this->assertArrayHasKey(
                $agentId,
                $registry,
                "Agent '{$agentId}' is not in the Copilot tool registry — add it to copilot-agent-tool-registry.php"
            );
        }
    }

    // ----- Installed agent files -----

    public function testInstalledAgentFilesAreVsCodeNativeFormat(): void
    {
        $agentsDir = $this->repoRoot . '/.github/agents';
        if (!is_dir($agentsDir)) {
            $this->markTestSkipped('.github/agents not installed yet');
        }
        foreach (glob($agentsDir . '/*.agent.md') ?: [] as $agentFile) {
            $content = (string) file_get_contents($agentFile);
            $name = basename($agentFile);
            $this->assertStringContainsString('name:', $content, "Installed agent '{$name}' must have 'name:' frontmatter");
            $this->assertStringContainsString('tools:', $content, "Installed agent '{$name}' must have 'tools:' frontmatter");
            $this->assertStringNotContainsString("\nid:", $content, "Installed agent '{$name}' must not have OpenCode 'id:' field");
            $this->assertStringNotContainsString('permission:', $content, "Installed agent '{$name}' must not have OpenCode 'permission:' block");
            $this->assertStringNotContainsString("'read'", $content, "Installed agent '{$name}' must use fine-grained read tools, not broad aliases");
            $this->assertStringNotContainsString("'search'", $content, "Installed agent '{$name}' must use fine-grained search tools, not broad aliases");
            $this->assertStringNotContainsString("'edit'", $content, "Installed agent '{$name}' must use fine-grained edit tools, not broad aliases");
            $this->assertStringNotContainsString("'execute'", $content, "Installed agent '{$name}' must use fine-grained execute tools, not broad aliases");
        }
    }

    // ----- Optional agent_assessment rubric projection (Plan D2) -----

    public function testAssessmentBlockRoundTripsWhenPresent(): void
    {
        $src = "---\nid: sample\ndescription: demo\nagent_assessment:\n  risk_level: high\n  score: 80\n---\nbody\n";
        $out = aiInstallerRenderCopilotAgent($src, 'sample', '/project/scripts/ai');
        // The rubric must appear inside the rebuilt frontmatter (before the closing ---).
        $this->assertMatchesRegularExpression('/^agent_assessment:\R\s+risk_level: high\R\s+score: 80/m', $out);
        $fmEnd = strpos($out, "\n---\n");
        $this->assertNotFalse($fmEnd);
        $this->assertStringContainsString('agent_assessment:', substr($out, 0, $fmEnd));
    }

    public function testNoAssessmentBlockWhenAbsentFromTemplate(): void
    {
        $src = "---\nid: sample\ndescription: demo\nmode: subagent\n---\nbody\n";
        $out = aiInstallerRenderCopilotAgent($src, 'sample', '/project/scripts/ai');
        $this->assertStringNotContainsString('agent_assessment', $out);
    }

    public function testLiveArchitectTemplateProjectsPilotRubric(): void
    {
        // The architect template carries the D1 pilot block; it must round-trip.
        // risk_level is reconciled to the authoritative AGENTS-MANIFEST.md value (high).
        $out = aiInstallerRenderCopilotAgent($this->architectTemplate(), 'architect', '/project/scripts/ai');
        $this->assertStringContainsString('agent_assessment:', $out);
        $this->assertStringContainsString('risk_level: high', $out);
    }

    // ----- Copilot native handoffs: frontmatter (copilot-agent-handoff-registry.php) -----

    /**
     * The four registered handoff chains must each render valid `handoffs:` frontmatter
     * AND keep the prose "Recommended next step" baseline (docs/ai/adapter-contract.md,
     * docs/ai/integration-matrix.md "Handoff Mechanism Per Runtime") — structured handoffs
     * are strictly additive, never a replacement.
     *
     * @return iterable<string, array{0: string, 1: callable(): string, 2: string}>
     */
    public static function handoffChainProvider(): iterable
    {
        yield 'architect -> architecture-plan-writer' => ['architect', 'architectTemplate', 'architecture-plan-writer'];
        yield 'implementer -> reviewer' => ['implementer', 'implementerTemplate', 'reviewer'];
    }

    #[DataProvider('handoffChainProvider')]
    public function testHandoffsFrontmatterEmittedForRegisteredChainWithProsePreserved(
        string $agentId,
        string $templateMethod,
        string $expectedTargetAgent
    ): void {
        $out = aiInstallerRenderCopilotAgent($this->$templateMethod(), $agentId, '/project/scripts/ai');

        $this->assertStringContainsString('handoffs:', $out);
        $this->assertStringContainsString("agent: {$expectedTargetAgent}", $out);

        // Prose "Recommended next step" baseline must still be present (case-insensitive
        // heading match) alongside the new structured handoffs frontmatter.
        $this->assertMatchesRegularExpression('/recommended next step/i', $out);
    }

    public function testArchitecturePlanWriterHandoffTargetsImplementer(): void
    {
        $out = aiInstallerRenderCopilotAgent(
            $this->architecturePlanWriterTemplate(),
            'architecture-plan-writer',
            '/project/scripts/ai'
        );
        $this->assertStringContainsString('handoffs:', $out);
        $this->assertStringContainsString('agent: implementer', $out);
        $this->assertMatchesRegularExpression('/recommended next step/i', $out);
    }

    public function testReviewerHandoffTargetsImplementerAndRefactorer(): void
    {
        $out = aiInstallerRenderCopilotAgent($this->reviewerTemplate(), 'reviewer', '/project/scripts/ai');
        $this->assertStringContainsString('handoffs:', $out);
        $this->assertStringContainsString('agent: implementer', $out);
        $this->assertStringContainsString('agent: refactorer', $out);
        $this->assertMatchesRegularExpression('/recommended next step/i', $out);

        // Both the Plan-3 clarification section and the Plan-4 pre-flight framing section
        // must survive the same render — regression guard against the dual-edit corrupting
        // or duplicating either sibling section.
        $this->assertStringContainsString('## Clarification And Handoff', $out);
        $this->assertStringContainsString('## Pre-Flight Framing', $out);
    }

    public function testHandoffsBlockAbsentForUnregisteredAgent(): void
    {
        // researcher has no registered handoff chain; output must render byte-identically
        // to prior behavior, i.e. no `handoffs:` key at all.
        $out = aiInstallerRenderCopilotAgent($this->researcherTemplate(), 'researcher', '/project/scripts/ai');
        $this->assertStringNotContainsString('handoffs:', $out);
    }
}
