<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Contract tests for .github/ai-script-access.yaml — the risk-tiered script
 * access manifest (Phase 4 of the remaining-rework plan).
 *
 * The manifest is parsed with a minimal line scanner (no yaml extension
 * dependency) sufficient for these structural invariants:
 *   - every ROOT script (one per scripts/ai/bin/<role>/<name>.sh alias) is
 *     listed in exactly one tier;
 *   - dangerous scripts appear only in T5_mutation_recovery;
 *   - only the runtime guardian is granted pre-tool-use.sh / post-tool-use.sh.
 */
class AiScriptAccessManifestTest extends TestCase
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
        $path = $root . '/.github/ai-script-access.yaml';
        self::assertFileExists($path);
        self::$manifest = (string) file_get_contents($path);
    }

    /** @return array<int,string> root .sh names from the bin alias tree */
    private function rootScripts(): array
    {
        $paths = glob(self::$repoRoot . '/scripts/ai/bin/*/*.sh') ?: [];
        $names = array_map(static fn(string $p): string => basename($p), $paths);
        $names = array_values(array_unique($names));
        sort($names);
        return $names;
    }

    /**
     * Map each tier name -> list of script entries, scanning only the top-level
     * `tiers:` block (stops at `agents:`).
     *
     * @return array<string,array<int,string>>
     */
    private function tierScripts(): array
    {
        $out = [];
        $inTiers = false;
        $curTier = null;
        foreach (preg_split('/\R/', self::$manifest) ?: [] as $line) {
            if (preg_match('/^tiers:\s*$/', $line)) {
                $inTiers = true;
                continue;
            }
            if (preg_match('/^agents:\s*$/', $line)) {
                break;
            }
            if (!$inTiers) {
                continue;
            }
            if (preg_match('/^  ([A-Za-z0-9_]+):\s*$/', $line, $m)) {
                $curTier = $m[1];
                $out[$curTier] = [];
                continue;
            }
            if ($curTier !== null && preg_match('/^      - ([\w.-]+\.sh)\s*$/', $line, $m)) {
                $out[$curTier][] = $m[1];
            }
        }
        return $out;
    }

    public function testEveryRootScriptIsInExactlyOneTier(): void
    {
        $tierScripts = $this->tierScripts();
        $this->assertNotEmpty($tierScripts, 'manifest must define tiers');

        $flat = [];
        foreach ($tierScripts as $scripts) {
            foreach ($scripts as $s) {
                $flat[] = $s;
            }
        }

        // No duplicates across tiers.
        $this->assertSame(
            array_values(array_unique($flat)),
            array_values($flat),
            'no script may appear in more than one tier'
        );

        // Exact 1:1 coverage of the root script set.
        $rootSorted = $this->rootScripts();
        $flatSorted = $flat;
        sort($flatSorted);
        $this->assertSame(
            $rootSorted,
            $flatSorted,
            'every root scripts/ai script must be tiered exactly once and vice versa'
        );
    }

    public function testDangerousScriptsAreOnlyInT5(): void
    {
        $dangerous = [
            'ai-edit.sh', 'ai-rollback.sh', 'prune-shipped-targets.sh',
            'install-mandatory-tools.sh', 'all_in_one.sh', 'watch-loop.sh',
            'pre-tool-use.sh', 'post-tool-use.sh',
        ];
        $tierScripts = $this->tierScripts();
        $t5 = $tierScripts['T5_mutation_recovery'] ?? [];

        foreach ($dangerous as $d) {
            foreach ($tierScripts as $tier => $scripts) {
                if (in_array($d, $scripts, true)) {
                    $this->assertSame(
                        'T5_mutation_recovery',
                        $tier,
                        "dangerous script '$d' must only appear in T5_mutation_recovery"
                    );
                }
            }
            $this->assertContains($d, $t5, "dangerous script '$d' must be in T5_mutation_recovery");
        }
    }

    public function testNoAgentIsGrantedToolHooks(): void
    {
        // pre-tool-use.sh / post-tool-use.sh are harness-invoked runtime hooks.
        // Their former sole owner (agent-creator-runtime-guardian) was retired in
        // the agent-handoff governance migration, so no agent may hold an explicit
        // allowed_scripts grant for them. Scan the agents block.
        $lines = preg_split('/\R/', self::$manifest) ?: [];
        $inAgents = false;
        $curAgent = null;
        $grantedHookTo = [];
        foreach ($lines as $line) {
            if (preg_match('/^agents:\s*$/', $line)) {
                $inAgents = true;
                continue;
            }
            if (!$inAgents) {
                continue;
            }
            if (preg_match('/^  ([\w-]+):\s*$/', $line, $m)) {
                $curAgent = $m[1];
                continue;
            }
            if ($curAgent !== null
                && preg_match('/allowed_scripts:.*(pre-tool-use\.sh|post-tool-use\.sh)/', $line)) {
                $grantedHookTo[$curAgent] = true;
            }
        }

        $this->assertSame(
            [],
            array_keys($grantedHookTo),
            'no agent may be granted the harness-invoked pre/post-tool-use hooks'
        );
    }
}
