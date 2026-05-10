<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/tools/ai/install/copilot-agent-renderer.php';

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
            $this->assertStringContainsString('execute/runInTerminal', $m[1]);
            $this->assertStringContainsString('execute/testFailure', $m[1]);
        } else {
            $this->fail('tools: line not found in output');
        }
    }

    public function testImplementerOutputHasShellBoundarySection(): void
    {
        $out = aiInstallerRenderCopilotAgent($this->implementerTemplate(), 'implementer', '/project/scripts/ai');
        $this->assertStringContainsString('## Shell Boundary', $out);
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
}
