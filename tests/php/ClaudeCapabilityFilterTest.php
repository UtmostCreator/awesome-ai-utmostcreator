<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/tools/ai/install/claude-runtime-capabilities.php';
require_once dirname(__DIR__, 2) . '/tools/ai/install/claude-agent-renderer.php';

/**
 * Plan-28 Phase 3 — data-driven Claude-capability filter (Todo item 3 / AC-05):
 * docs/tickets/claude-agent-fleet-remediation/plan-28-permission-sot-and-render-parity-sync.md.
 *
 * Proves:
 *  - the ordered capability rule table (`aiClaudeApplyRuntimeCapabilityFilters()`) neutralizes
 *    the known false-claim patterns (`no_external_directory_enforcement`, `no_ask_tier`) on
 *    synthetic fixtures, including the `task` (`ask`) delegation rule's tool-presence condition;
 *  - the Script-Access-vs-allowedBash reconciliation rule (`aiClaudeReconcileScriptAccessBullets()`)
 *    correctly rewrites a bullet naming only absent scripts, correctly LEAVES a mixed-presence
 *    bullet untouched, and is scoped strictly to the `## Script Access` section;
 *  - no installed `.claude/agents/*.md` body contains a banned-capability phrase
 *    (`no_external_directory_enforcement`, the pre-Phase-3 "settings.json wins" contradiction,
 *    or an unreachable task-delegation claim for a role that has no `Agent` tool);
 *  - no installed Script Access bullet names ONLY scripts absent from that agent's own rendered
 *    allowedBash (the "fully absent" case the reconciliation rule guarantees never survives).
 *
 * KNOWN LIMITATION (documented, not silently passed — see
 * testKnownMixedPresenceBulletsAreUnchanged): a small, pinned set of "mixed-presence" bullets
 * remains where SOME but not all named scripts are absent (e.g. bugfix.md's "`ai-verify.sh`
 * (`ask`) / `ai-test-select.sh` / `run-repo-tests.sh`" — the latter two ARE approved,
 * `ai-verify.sh` is not). The reconciliation rule deliberately does not rewrite these — doing so
 * would risk falsely claiming a genuinely-runnable script is not runnable. AC-05's literal "no
 * bullet names an absent script" therefore does not fully hold for this residual case; it is
 * pinned here so any NEW occurrence (a regression) fails loudly, and any agent that gains a
 * template-level disambiguation (like reviewer.md's own hand-authored fix already has) shrinks
 * the pinned set safely.
 */
final class ClaudeCapabilityFilterTest extends TestCase
{
    private static string $repoRoot;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root');
        }
        self::$repoRoot = $root;
    }

    // ----- Unit: capability rule table -----

    public function testExternalDirectoryChainIsNeutralized(): void
    {
        $body = 'Approval prompt (OpenCode `external_directory: ask`) applies here. '
            . 'See the OpenCode `external_directory: ask` prompt for details.';
        $out = aiClaudeApplyRuntimeCapabilityFilters($body, ['tools' => ['Read']]);
        $this->assertStringNotContainsString('OpenCode `external_directory: ask`', $out);
        $this->assertStringContainsString(
            'external-directory approval prompt (instruction-only on Claude Code; no tool permission enforces this boundary)',
            $out
        );
    }

    public function testFullPerScriptPhraseIsRewritten(): void
    {
        $body = 'Full per-script `allow`/`ask`/`deny` is in frontmatter; full guidance in `docs/ai/agent-script-access.md`.';
        $out = aiClaudeApplyRuntimeCapabilityFilters($body, ['tools' => ['Read']]);
        $this->assertStringNotContainsString('is in frontmatter; full guidance', $out);
        $this->assertStringContainsString('documented in the Bash Command Policy section above', $out);
    }

    public function testTaskDelegationRewrittenOnlyWhenAgentToolAbsent(): void
    {
        $body = 'Reviewer inspects. `task` (`ask`) is only for delegating a bounded, read-only sub-review when needed.';

        $withoutAgent = aiClaudeApplyRuntimeCapabilityFilters($body, ['tools' => ['Read', 'Bash']]);
        $this->assertStringContainsString('OpenCode-only capability', $withoutAgent);
        $this->assertStringNotContainsString('is only for delegating', $withoutAgent);

        $withAgent = aiClaudeApplyRuntimeCapabilityFilters($body, ['tools' => ['Read', 'Bash', 'Agent']]);
        $this->assertStringContainsString('is only for delegating', $withAgent);
        $this->assertStringNotContainsString('OpenCode-only capability', $withAgent);
    }

    // ----- Unit: Script-Access-vs-allowedBash reconciliation -----

    public function testFullyAbsentSingleScriptBulletIsRewritten(): void
    {
        $body = "## Script Access\n\n- repomix/`pack-context.sh` (`ask`) — only for large context packing; expect a context bundle.\n\n## Next\n";
        $out = aiClaudeReconcileScriptAccessBullets($body, ['bash scripts/ai/ai-search.sh *']);
        $this->assertStringContainsString('`pack-context.sh` — not runnable on Claude Code', $out);
        $this->assertStringNotContainsString('only for large context packing', $out);
    }

    public function testFullyAbsentMultiScriptBulletIsRewritten(): void
    {
        $body = "## Script Access\n\n- `ai-edit.sh` / `ai-rollback.sh` (`ask`) — desc; `session-checkpoint.sh` (`ask`) for continuity.\n\n## Next\n";
        $out = aiClaudeReconcileScriptAccessBullets($body, ['bash scripts/ai/ai-search.sh *']);
        $this->assertStringContainsString(
            '`ai-edit.sh` / `ai-rollback.sh` / `session-checkpoint.sh` — not runnable on Claude Code',
            $out
        );
    }

    public function testMixedPresenceBulletIsLeftUntouched(): void
    {
        $body = "## Script Access\n\n- `ai-verify.sh` (`ask`) / `ai-test-select.sh` / `run-repo-tests.sh` — to prove the fix.\n\n## Next\n";
        $allowedBash = ['bash scripts/ai/ai-test-select.sh *', 'bash scripts/ai/run-repo-tests.sh*'];
        $out = aiClaudeReconcileScriptAccessBullets($body, $allowedBash);
        $this->assertSame($body, $out, 'a bullet with at least one present script must be left untouched');
    }

    public function testScopedToScriptAccessSectionOnly(): void
    {
        // A bullet-shaped line OUTSIDE "## Script Access" containing the literal ask marker
        // must never be touched, even if it happens to name an absent script.
        $body = "## Hard Rules\n\n- `pack-context.sh` (`ask`) — unrelated mention outside Script Access.\n\n## Script Access\n\nnothing here\n";
        $out = aiClaudeReconcileScriptAccessBullets($body, []);
        $this->assertSame($body, $out);
    }

    // ----- Integration: every installed .claude/agents/*.md body -----

    /** @return list<string> */
    private function installedAgentFiles(): array
    {
        $dir = self::$repoRoot . '/.claude/agents';
        if (!is_dir($dir)) {
            $this->markTestSkipped('.claude/agents not installed');
        }
        $files = glob($dir . '/*.md') ?: [];
        // super-implementer.md is a held-back draft orphan (see .gitignore: id-collision
        // with implementer.md), not a renderer-managed shipped agent — the same reason
        // OpencodeAgentBodyParityTest skips it as an "opencode-only agent with no canonical
        // template". It carries stale Script Access bullets naming scripts that were moved
        // out of scripts/ai (commit b2fbf715), so it must not gate the installed fleet.
        $files = array_values(array_filter(
            $files,
            static fn (string $f): bool => basename($f, '.md') !== 'super-implementer'
        ));
        if ($files === []) {
            $this->markTestSkipped('.claude/agents has no rendered agent files');
        }

        return $files;
    }

    public function testNoInstalledBodyContainsExternalDirectoryBannedPhrase(): void
    {
        foreach ($this->installedAgentFiles() as $file) {
            $content = (string) file_get_contents($file);
            $this->assertStringNotContainsString(
                'OpenCode `external_directory: ask`',
                $content,
                basename($file) . ' must not assert a Claude tool permission that does not exist'
            );
        }
    }

    public function testNoInstalledBodyContainsSettingsJsonWinsContradiction(): void
    {
        foreach ($this->installedAgentFiles() as $file) {
            $content = (string) file_get_contents($file);
            $this->assertStringNotContainsString(
                'disagree, `.claude/settings.json` wins',
                $content,
                basename($file) . ' must state the subset-by-construction relationship, not the pre-Phase-3 contradiction framing'
            );
        }
    }

    public function testNoInstalledBodyContainsUnreachableTaskDelegationForNonAgentRoles(): void
    {
        $registry = aiClaudeAgentToolRegistry();
        foreach ($this->installedAgentFiles() as $file) {
            $agentId = pathinfo($file, PATHINFO_FILENAME);
            $tools = $registry[$agentId]['tools'] ?? [];
            if (in_array('Agent', $tools, true)) {
                continue;
            }
            $content = (string) file_get_contents($file);
            $this->assertStringNotContainsString(
                '`task` (`ask`) is only for delegating',
                $content,
                basename($file) . " omits the Agent tool; must not describe a task-delegation capability it doesn't grant"
            );
        }
    }

    public function testNoScriptAccessBulletNamesAnEntirelyAbsentScript(): void
    {
        foreach ($this->installedAgentFiles() as $file) {
            $content = (string) file_get_contents($file);
            $approvedBasenames = $this->extractApprovedScriptBasenames($content);

            if (preg_match('/^## Script Access\R.*?(?=\R## |\z)/ms', $content, $section) !== 1) {
                continue;
            }
            foreach (explode("\n", $section[0]) as $line) {
                if (substr_count($line, '(`ask`)') === 0) {
                    continue;
                }
                if (preg_match_all('/`(?:[A-Za-z0-9_.\/-]*?)([A-Za-z0-9_-]+\.sh)`/', $line, $names) === 0) {
                    continue;
                }
                $scriptNames = array_unique($names[1]);
                $allAbsent = true;
                foreach ($scriptNames as $name) {
                    if (isset($approvedBasenames[$name])) {
                        $allAbsent = false;
                        break;
                    }
                }
                $this->assertFalse(
                    $allAbsent,
                    basename($file) . ' has an un-reconciled Script Access bullet naming only absent scripts: ' . trim($line)
                );
            }
        }
    }

    /**
     * Known, documented residual gap (see class doc comment): "mixed-presence" bullets where
     * at least one but not all named scripts are approved. Pins the exact current set so a
     * regression (a NEW mixed bullet, or one of these becoming fully-absent) fails loudly.
     */
    public function testKnownMixedPresenceBulletsAreUnchanged(): void
    {
        $expected = [
            'configuration-maintainer.md' => '- `ai-verify.sh` (`ask`) / `ai-test-select.sh` / `run-repo-tests.sh` — for proof; expect pass/fail.',
            'implementer.md' => '- `ai-verify.sh` (`ask`) / `ai-test-select.sh` / `run-repo-tests.sh` — for proof; expect pass/fail.',
            'release-auditor.md' => '- `ai-diff-context.sh` / `ai-verify.sh` (`ask`) — to confirm verification depth; expect diff bundle and test results already produced by prior implementer/reviewer runs. Ask-tier scripts such as `ai-verify.sh` require a separate per-run approval prompt each time they run and are deliberately not part of the renderer\'s fixed "Approved scripts" allow-list; listing them here is not a contradiction of that list.',
        ];

        foreach ($expected as $agentFile => $expectedLine) {
            $path = self::$repoRoot . '/.claude/agents/' . $agentFile;
            $this->assertFileExists($path);
            $content = (string) file_get_contents($path);
            $this->assertStringContainsString(
                $expectedLine,
                $content,
                $agentFile . ' known mixed-presence bullet text changed; re-verify whether it is still a genuine mixed case or has become fully reconcilable'
            );
        }

        // No other installed agent should carry an unaccounted `(`ask`)` marker in its Script
        // Access section — if this fails, either a new mixed-presence case appeared (extend
        // $expected above after confirming it is genuinely mixed) or a fully-absent case slipped
        // through un-reconciled (a real regression — see
        // testNoScriptAccessBulletNamesAnEntirelyAbsentScript, which would also fail in that case).
        foreach ($this->installedAgentFiles() as $file) {
            $name = basename($file);
            if (isset($expected[$name])) {
                continue;
            }
            $content = (string) file_get_contents($file);
            if (preg_match('/^## Script Access\R.*?(?=\R## |\z)/ms', $content, $section) !== 1) {
                continue;
            }
            $this->assertStringNotContainsString(
                '(`ask`)',
                $section[0],
                $name . ' has a new, un-pinned `(`ask`)` marker in Script Access — update this test'
            );
        }
    }

    /** @return array<string,true> */
    private function extractApprovedScriptBasenames(string $content): array
    {
        if (preg_match('/Approved scripts \(run from the repository root.*?\):\R\R(.*?)\R\RDo not run arbitrary/s', $content, $m) !== 1) {
            return [];
        }
        $basenames = [];
        if (preg_match_all('#scripts/ai/([A-Za-z0-9_.-]+\.sh)#', $m[1], $names) > 0) {
            foreach ($names[1] as $name) {
                $basenames[$name] = true;
            }
        }

        return $basenames;
    }
}
