<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../tools/ai/install/permission-layers/render-adapters.php';
require_once __DIR__ . '/../../tools/ai/install/canonical-agent-frontmatter.php';

/**
 * Proves the Slice 8 adapter seam
 * (docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/plan.md, Slice 8):
 * an agent-keyed compose wrapper, a named-adapter registry, and the fallback resolver that lets
 * migrated and not-yet-migrated agents render side by side.
 */
final class PermissionRenderAdaptersTest extends TestCase
{
    public function testAiPermissionComposeThrowsForUnknownAgent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        aiPermissionCompose('not-a-real-agent');
    }

    public function testAiPermissionComposeMatchesComposeFromSpecForResearcher(): void
    {
        $byAgent = aiPermissionCompose('researcher');
        $compositions = aiPermissionAgentCompositions();
        $bySpec = aiPermissionComposeFromSpec($compositions['researcher']['compose_spec']);

        self::assertSame($bySpec['model'], $byAgent['model']);
    }

    public function testRenderAdaptersRegistryHasOneEntryPerHarness(): void
    {
        $adapters = aiPermissionRenderAdapters();

        self::assertArrayHasKey('opencode', $adapters);
        self::assertArrayHasKey('copilot', $adapters);
        self::assertArrayHasKey('claude', $adapters);
        foreach ($adapters as $harness => $callable) {
            self::assertIsCallable($callable, "adapter for '{$harness}' must be callable");
        }
    }

    public function testAllowedBashFromModelExcludesFloorAndNonAllowEntries(): void
    {
        $result = aiPermissionCompose('researcher');
        $allowed = aiPermissionAllowedBashFromModel($result['model']);

        self::assertNotContains('*', $allowed, 'the universal deny floor entry must never appear in an allowedBash projection');
        foreach ($allowed as $pattern) {
            $key = aiPermissionModelKey('bash', $pattern);
            self::assertSame('allow', $result['model'][$key]['effect'], "pattern '{$pattern}' must be allow-effect");
        }
    }

    public function testAllowedBashFromModelExcludesTheRemovedBrokenLiteralPattern(): void
    {
        // Regression guard for the 2026-07-05 bug fix: a literal compound command embedding
        // unescaped double quotes was removed from core.php's safe-read layer (redundant with
        // git-read's git status*/git branch* globs, and it broke double-quoted YAML rendering).
        $result = aiPermissionCompose('researcher');
        $allowed = aiPermissionAllowedBashFromModel($result['model']);

        self::assertNotContains('git status --short; echo "---BRANCH---"; git branch --show-current', $allowed);
        self::assertNotContains('git status --short && git branch --show-current', $allowed);
    }

    public function testResolveAllowedBashUsesComposedModelForMigratedAgent(): void
    {
        // Ground truth for this regression: the legacy frontmatter parser is single-quote-only
        // and researcher's regenerated block uses double quotes, so it must return an empty
        // legacy list — proving the fallback seam is load-bearing, not decorative.
        $repoRoot = realpath(__DIR__ . '/../..');
        self::assertIsString($repoRoot);
        $content = (string) file_get_contents($repoRoot . '/.opencode/agents/researcher.md');
        $parsed = aiInstallerParseCanonicalAgentFrontmatter($content);

        self::assertSame([], $parsed['allowedBash'], 'legacy parser is expected to be empty for double-quoted researcher.md (proves the fallback seam is necessary)');

        $resolved = aiPermissionResolveAllowedBash('researcher', $parsed['allowedBash']);
        self::assertNotEmpty($resolved, 'composed-model projection must not be empty for a migrated agent');
        self::assertContains('command -v *', $resolved);
    }

    public function testResolveAllowedBashFallsBackForNotYetMigratedAgent(): void
    {
        $legacy = ['some-legacy-command *', 'another-one'];
        $resolved = aiPermissionResolveAllowedBash('not-a-real-agent', $legacy);

        self::assertSame($legacy, $resolved, 'non-composed agents must pass through the legacy list unchanged');
    }
}
