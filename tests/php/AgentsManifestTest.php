<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Coverage test for docs/ai/AGENTS-MANIFEST.md — the agent inventory that
 * mirrors scripts/ai/MANIFEST.md for the two agent adapter surfaces
 * (.opencode/agents/*.md and .github/agents/*.agent.md).
 *
 * The two surfaces are NOT a 1:1 set; the manifest and these assertions treat
 * them as distinct. Every agent on either surface must be classified in the
 * manifest so the inventory cannot silently drift.
 */
class AgentsManifestTest extends TestCase
{
    private static string $repoRoot;
    private static string $manifest;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }

        self::$repoRoot = $root;
        $manifestPath = $root . '/docs/ai/AGENTS-MANIFEST.md';
        self::assertFileExists($manifestPath);
        self::$manifest = (string) file_get_contents($manifestPath);
    }

    /** @return array<int,string> */
    private function opencodeAgents(): array
    {
        $paths = glob(self::$repoRoot . '/.opencode/agents/*.md') ?: [];
        sort($paths);
        return array_map(static fn(string $p): string => basename($p, '.md'), $paths);
    }

    /** @return array<int,string> */
    private function githubAgents(): array
    {
        $paths = glob(self::$repoRoot . '/.github/agents/*.agent.md') ?: [];
        sort($paths);
        return array_map(static fn(string $p): string => basename($p, '.agent.md'), $paths);
    }

    public function testManifestClassifiesEveryOpencodeAgent(): void
    {
        $agents = $this->opencodeAgents();
        $this->assertNotEmpty($agents, 'expected .opencode/agents/*.md files');

        foreach ($agents as $agent) {
            $this->assertStringContainsString(
                "`$agent`",
                self::$manifest,
                "agent manifest must classify OpenCode agent '$agent'"
            );
        }
    }

    public function testManifestClassifiesEveryGithubAgent(): void
    {
        $agents = $this->githubAgents();
        $this->assertNotEmpty($agents, 'expected .github/agents/*.agent.md files');

        foreach ($agents as $agent) {
            $this->assertStringContainsString(
                "`$agent`",
                self::$manifest,
                "agent manifest must classify GitHub agent '$agent'"
            );
        }
    }

    public function testManifestDocumentsSurfaceCoverageDifferences(): void
    {
        // The manifest must explicitly record the surface-only agents so the
        // non-1:1 nature of the two surfaces stays visible and auditable.
        $opencodeOnly = array_values(array_diff($this->opencodeAgents(), $this->githubAgents()));
        $githubOnly = array_values(array_diff($this->githubAgents(), $this->opencodeAgents()));

        $this->assertStringContainsString(
            '## Surface coverage differences',
            self::$manifest,
            'manifest must document surface coverage differences'
        );

        foreach ($opencodeOnly as $agent) {
            $this->assertStringContainsString(
                "`$agent`",
                self::$manifest,
                "manifest must list OpenCode-only agent '$agent'"
            );
        }
        foreach ($githubOnly as $agent) {
            $this->assertStringContainsString(
                "`$agent`",
                self::$manifest,
                "manifest must list GitHub-only agent '$agent'"
            );
        }
    }
}
