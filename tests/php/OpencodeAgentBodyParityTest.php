<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../tools/ai/install/generated-header.php';
require_once __DIR__ . '/../../tools/ai/install/copilot-agent-renderer.php';

/**
 * Cross-provider content-parity gate for the `.opencode/agents/*.md` dogfood tree.
 *
 * The three provider agent surfaces all derive from ONE canonical source
 * (`packages/ai-universal-rules/templates/{core,optional}/agents/*.md`). The Claude and
 * Copilot renders are covered by `AdapterRenderDriftTest` (`render-adapters.php --check`),
 * but that tool never covered OpenCode — so `.opencode/agents/*.md` had no re-render gate and
 * could (and did) silently drift from canonical while the other two stayed in sync. OpenCode
 * is a near-verbatim passthrough: the shipped file is the canonical template with a single
 * GENERATED header comment inserted after the frontmatter (see
 * `aiInstallerCopyDirAsOpenCodeAgents()`), so parity here is exact byte-equality against
 * `canonical + header` — no per-provider transform to normalize.
 *
 * Scope: this gate covers ONLY the `.opencode/agents/` dir. The sibling `.opencode/agents-optional/`
 * tree is deliberately out of scope here — its files are the installed/dogfooded form with
 * `<PROJECT_NAME>` placeholder substitution applied, so they are NOT a pure `canonical + header`
 * passthrough and need a different comparison basis. Extending coverage to it is tracked as a
 * follow-up in `docs/tickets/IDEAS/plan-provider-agent-parity-assessment.md` (§4, §Handoff item 3:
 * normalize the core-vs-optional layout, then extend `render-adapters.php` to cover OpenCode).
 *
 * Skips (by construction, not by exception):
 *  - opencode-only agents with no canonical template (`script-runner`, `super-implementer`)
 *  - hidden internal-only agents, which the installer PRESERVES verbatim rather than
 *    re-rendering (`aiAgentIsHiddenInternalOnly()`), so they are not canonical-derived
 *
 * Documented exception (temporary): agents in RECONCILIATION_PENDING have `.claude`/`.github`
 * renders that `render-adapters.php --check` already flags as drifted (a separate, in-flight
 * render-reconciliation). Their `.opencode` copy is intentionally left to that holistic
 * re-render rather than patched piecemeal here. The test asserts each pending agent is STILL
 * drifted, so the exception self-expires: once its parity is restored the assertion fails,
 * forcing the id to be removed from the list rather than silently masking a clean file.
 */
final class OpencodeAgentBodyParityTest extends TestCase
{
    private const HEADER_SOURCE_LABEL =
        'ai-kit installer from packages/ai-universal-rules/templates/core/agents';

    /** @var list<string> */
    private const RECONCILIATION_PENDING = [];

    private static string $repoRoot;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;
    }

    public function testOpencodeAgentsAreByteParityWithCanonical(): void
    {
        $checked = 0;
        $drift = [];

        foreach (glob(self::$repoRoot . '/.opencode/agents/*.md') ?: [] as $installed) {
            $id = pathinfo($installed, PATHINFO_FILENAME);
            $content = (string) file_get_contents($installed);

            // Hidden internal-only agents are preserved verbatim, not re-rendered.
            if (aiAgentIsHiddenInternalOnly($content)) {
                continue;
            }

            $canonical = $this->canonicalTemplateFor($id);
            // Opencode-only agents (no canonical source) are not parity-checkable here.
            if ($canonical === null) {
                continue;
            }

            $expected = aiInstallerInsertGeneratedHeaderAfterFrontmatter(
                (string) file_get_contents($canonical),
                self::HEADER_SOURCE_LABEL
            );

            if (in_array($id, self::RECONCILIATION_PENDING, true)) {
                // Self-expiring guard: a pending agent MUST still be drifted. If it has come
                // back into parity, fail here so the id is removed from the exception list
                // rather than silently masking a now-clean file.
                self::assertNotSame(
                    $expected,
                    $content,
                    "'{$id}' is in RECONCILIATION_PENDING but is now byte-parity with its "
                    . 'canonical template — remove it from the exception list.'
                );
                continue;
            }

            if ($content !== $expected) {
                $drift[] = ".opencode/agents/{$id}.md";
            }
            $checked++;
        }

        self::assertSame(
            [],
            $drift,
            "OpenCode agent(s) drifted from their canonical template (run the opencode dogfood "
            . "regen to restore parity):\n  - " . implode("\n  - ", $drift)
        );
        self::assertGreaterThan(
            0,
            $checked,
            'expected at least one canonical-derived OpenCode agent to be parity-checked'
        );
    }

    private function canonicalTemplateFor(string $id): ?string
    {
        foreach (['core', 'optional'] as $tier) {
            $path = self::$repoRoot
                . "/packages/ai-universal-rules/templates/{$tier}/agents/{$id}.md";
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
