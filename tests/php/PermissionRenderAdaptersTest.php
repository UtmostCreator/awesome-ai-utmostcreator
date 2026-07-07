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

    /**
     * Slice B (docs/tickets/arch-todo-complete-permission-composition-migration/plan.md):
     * proves the new optional `extra_scalars_before_edit` / `external_directory` render-spec
     * keys render in the exact order architecture-plan-writer.md needs — todowrite, task,
     * edit, external_directory, doom_loop, bash — and that omitting them changes nothing
     * (back-compat for the 13 pre-existing render specs).
     */
    public function testRenderOpenCodeBlockEmitsNestedExternalDirectoryMappingInOrder(): void
    {
        $model = [
            aiPermissionModelKey('edit', '*') => ['permission' => 'edit', 'pattern' => '*', 'effect' => 'deny', 'class' => 'layer', 'layer' => 'test'],
            aiPermissionModelKey('edit', 'docs/tickets/**') => ['permission' => 'edit', 'pattern' => 'docs/tickets/**', 'effect' => 'allow', 'class' => 'layer', 'layer' => 'test'],
            aiPermissionModelKey('bash', '*') => ['permission' => 'bash', 'pattern' => '*', 'effect' => 'deny', 'class' => 'floor', 'layer' => 'test'],
        ];
        $render = aiPermissionRenderArchitecturePlanWriter();

        $rendered = aiPermissionRenderOpenCodeBlock($model, $render);
        $lines = explode("\n", $rendered);

        self::assertSame('permission:', $lines[0]);
        self::assertSame('  todowrite: allow', $lines[1]);
        self::assertSame('  task: deny', $lines[2], 'task must render BEFORE edit for this agent shape');
        self::assertSame('  edit:', $lines[3]);
        self::assertSame("    '*': deny", $lines[4]);
        self::assertSame("    'docs/tickets/**': allow", $lines[5]);
        self::assertSame('  external_directory:', $lines[6], 'external_directory mapping must render immediately after edit');
        self::assertSame("    '~/Projects/awesome-ai-utmostcreator/docs/tickets/**': allow", $lines[7]);
        self::assertSame("    '\$HOME/Projects/awesome-ai-utmostcreator/docs/tickets/**': allow", $lines[8]);
        self::assertSame('  doom_loop: ask', $lines[9], 'doom_loop must render after external_directory, before bash');
        self::assertSame('  bash:', $lines[10]);
    }

    public function testRenderOpenCodeBlockOmitsNewKeysWhenRenderSpecDoesNotSetThem(): void
    {
        // Back-compat proof: an existing-shape render spec (no extra_scalars_before_edit,
        // no external_directory) must render identically to before Slice B.
        $model = [
            aiPermissionModelKey('bash', '*') => ['permission' => 'bash', 'pattern' => '*', 'effect' => 'deny', 'class' => 'floor', 'layer' => 'test'],
        ];
        $render = aiPermissionRenderTaskAsk();

        $rendered = aiPermissionRenderOpenCodeBlock($model, $render);

        self::assertStringNotContainsString('external_directory:', $rendered);
        self::assertSame("permission:\n  todowrite: allow\n  edit: deny\n  task: ask\n  bash:\n    '*': deny", $rendered);
    }

    /**
     * Regression: OpenCode's permission engine resolves bash rules via `.findLast()` over
     * the declared ruleset in file order (confirmed against opencode's own
     * permission/index.ts `evaluate()` and docs.opencode.ai/docs/permissions -- "last
     * matching rule winning... put the catch-all rule first, more specific rules after
     * it"). The '*' bash entry must therefore always render as the FIRST entry immediately
     * after `bash:`, regardless of where it falls in the composed $model array's own
     * internal order -- otherwise it silently overrides every specific allow/ask rule
     * declared before it, since '*' matches every possible command string. This was a real,
     * repo-wide bug: every composed agent previously rendered '*' last.
     */
    public function testRenderOpenCodeBlockAlwaysEmitsStarBashEntryFirstRegardlessOfModelOrder(): void
    {
        // Deliberately construct $model with '*' LAST in insertion order (the exact shape
        // that produced the bug) to prove the renderer does not simply preserve model order.
        $model = [
            aiPermissionModelKey('bash', 'bash scripts/ai/query-usage.sh *') => [
                'permission' => 'bash', 'pattern' => 'bash scripts/ai/query-usage.sh *', 'effect' => 'allow', 'class' => 'layer', 'layer' => 'test',
            ],
            aiPermissionModelKey('bash', 'git status*') => [
                'permission' => 'bash', 'pattern' => 'git status*', 'effect' => 'allow', 'class' => 'layer', 'layer' => 'test',
            ],
            aiPermissionModelKey('bash', '*') => [
                'permission' => 'bash', 'pattern' => '*', 'effect' => 'deny', 'class' => 'floor', 'layer' => 'test',
            ],
        ];
        $render = aiPermissionRenderTaskAsk();

        $rendered = aiPermissionRenderOpenCodeBlock($model, $render);
        $lines = explode("\n", $rendered);

        $bashIdx = array_search('  bash:', $lines, true);
        self::assertNotFalse($bashIdx, 'rendered output must contain a bash: block');
        self::assertSame(
            "    '*': deny",
            $lines[$bashIdx + 1],
            "'*' must be the first entry immediately after 'bash:', not wherever it fell in \$model's order"
        );
        self::assertStringContainsString("    'bash scripts/ai/query-usage.sh *': allow", $rendered);
        self::assertStringContainsString("    'git status*': allow", $rendered);

        // The specific allow entries must appear AFTER the star (this is what makes them
        // correctly override it under findLast() semantics).
        $starPos = strpos($rendered, "    '*': deny");
        $queryUsagePos = strpos($rendered, "'bash scripts/ai/query-usage.sh *': allow");
        self::assertLessThan($queryUsagePos, $starPos, 'the star entry must be positioned before the specific overrides');
    }

    /**
     * Regression: confirms the fix holds for a real, live composed agent (not just a
     * synthetic model), since a subtle change to aiPermissionComposeFromSpec's internal
     * ordering could theoretically still produce '*' first in some but not all agents.
     */
    public function testResearcherComposedModelRendersStarBashEntryFirst(): void
    {
        $result = aiPermissionCompose('researcher');
        $compositions = aiPermissionAgentCompositions();
        $rendered = aiPermissionRenderOpenCodeBlock($result['model'], $compositions['researcher']['render']);
        $lines = explode("\n", $rendered);

        $bashIdx = array_search('  bash:', $lines, true);
        self::assertNotFalse($bashIdx);
        self::assertMatchesRegularExpression(
            '/^\s+["\']\*["\']:\s*(allow|ask|deny)$/',
            $lines[$bashIdx + 1],
            "researcher's rendered bash: block must have '*' as its first entry"
        );
    }

    /**
     * Slice B: proves the extended `tickets` edit surface produces all 8 path spellings
     * (plus the explicit '*': deny baseline) that architecture-plan-writer.md ships.
     */
    public function testTicketsEditSurfaceHasAllEightSpellingsPlusDenyBaseline(): void
    {
        $surfaces = aiPermissionEditSurfaces();
        self::assertArrayHasKey('tickets', $surfaces);

        $patterns = array_column($surfaces['tickets'], 'pattern');
        self::assertSame([
            '*',
            'docs/tickets/**',
            './docs/tickets/**',
            '~/Projects/awesome-ai-utmostcreator/docs/tickets/**',
            '$HOME/Projects/awesome-ai-utmostcreator/docs/tickets/**',
            'docs/tickets/arch-todo-*/**',
            './docs/tickets/arch-todo-*/**',
            '~/Projects/awesome-ai-utmostcreator/docs/tickets/arch-todo-*/**',
            '$HOME/Projects/awesome-ai-utmostcreator/docs/tickets/arch-todo-*/**',
        ], $patterns);

        foreach ($surfaces['tickets'] as $entry) {
            $expected = $entry['pattern'] === '*' ? 'deny' : 'allow';
            self::assertSame($expected, $entry['effect'], "pattern '{$entry['pattern']}' has unexpected effect");
        }
    }

    /**
     * plan-2-opencode-secret-deny-backstop (BLOCKER A / AC-05): on a `'*': deny` agent, a
     * `layer`-class deny whose effect equals the floor is stripped as a no-op restatement, but
     * a `backstop`-class deny is RETAINED — under `.findLast()` it overrides an earlier allow
     * and is therefore load-bearing, not redundant. Retention is keyed on model `class` only.
     */
    public function testBackstopClassDenySurvivesNoOpFilterWhileLayerClassDenyIsStripped(): void
    {
        $model = [
            aiPermissionModelKey('bash', '*') => [
                'permission' => 'bash', 'pattern' => '*', 'effect' => 'deny', 'class' => 'floor', 'layer' => 'test',
            ],
            // A broad reader allow the backstop must override.
            aiPermissionModelKey('bash', 'bash scripts/ai/preview-file.sh *') => [
                'permission' => 'bash', 'pattern' => 'bash scripts/ai/preview-file.sh *', 'effect' => 'allow', 'class' => 'layer', 'layer' => 'test',
            ],
            // layer-class redundant deny (same as floor) — must be stripped.
            aiPermissionModelKey('bash', 'git branch*') => [
                'permission' => 'bash', 'pattern' => 'git branch*', 'effect' => 'deny', 'class' => 'layer', 'layer' => 'test',
            ],
            // backstop-class deny (same effect as floor) — must survive.
            aiPermissionModelKey('bash', '*scripts/ai/preview-file.sh *.env*') => [
                'permission' => 'bash', 'pattern' => '*scripts/ai/preview-file.sh *.env*', 'effect' => 'deny', 'class' => 'backstop', 'layer' => 'test',
            ],
        ];

        $rendered = aiPermissionRenderOpenCodeBlock($model, aiPermissionRenderTaskAsk());

        self::assertStringContainsString("    '*scripts/ai/preview-file.sh *.env*': deny", $rendered);
        self::assertStringNotContainsString("    'git branch*': deny", $rendered);

        // And the surviving backstop must render AFTER the broad allow (findLast ordering).
        $allowPos = strpos($rendered, "'bash scripts/ai/preview-file.sh *': allow");
        $denyPos = strpos($rendered, "'*scripts/ai/preview-file.sh *.env*': deny");
        self::assertNotFalse($allowPos);
        self::assertNotFalse($denyPos);
        self::assertGreaterThan($allowPos, $denyPos, 'backstop deny must render after the broad allow');
    }

    /**
     * plan-2 AC-06 / N3: the Copilot/Claude `allowedBash` projection filters to allow-effect
     * entries only, so the OpenCode secret-deny backstop entries are inertly skipped — those
     * runtimes gain no false OpenCode-enforcement claim and keep the prompt-level rule.
     */
    public function testAllowedBashProjectionExcludesSecretDenyBackstopEntries(): void
    {
        $result = aiPermissionCompose('reviewer');
        $allowed = aiPermissionAllowedBashFromModel($result['model']);

        foreach ($allowed as $pattern) {
            self::assertStringNotContainsString('*.env*', $pattern);
            self::assertStringNotContainsString('*.pem', $pattern);
        }
        // Sanity: the broad reader allow is still projected (it is an allow).
        self::assertContains('bash scripts/ai/preview-file.sh *', $allowed);
    }
}
