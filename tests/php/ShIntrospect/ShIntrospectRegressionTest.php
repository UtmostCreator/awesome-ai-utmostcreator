<?php

declare(strict_types=1);

namespace Tests\ShIntrospect;

use Tests\Support\ShIntrospectTestCase;

/**
 * Golden JSON snapshot, help/JSON drift guard, and parser edge cases.
 */
class ShIntrospectRegressionTest extends ShIntrospectTestCase
{
    public function testContractMatchesGoldenJsonSnapshot(): void
    {
        $golden = self::$repoRoot . '/tests/php/fixtures/ai-search-contract.golden.json';
        $this->assertFileExists($golden, 'golden JSON contract fixture missing');

        // Re-canonicalise the committed golden the same way (line=0, sorted,
        // <REPO> placeholder is already in it) so the comparison is apples-to-apples.
        $expected = json_decode((string) file_get_contents($golden), true);
        $this->assertIsArray($expected, 'golden JSON fixture is not valid JSON');
        $this->ksortRecursive($expected);
        $expectedStr = (string) json_encode($expected, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $this->assertSame(
            $expectedStr,
            $this->canonicalContractJson(),
            "JSON contract drifted from golden snapshot; if intentional, regenerate via:\n"
            . "REPO=\$(pwd); AI_OUTPUT=json php tools/ai/sh-introspect.php scripts/ai/ai-search.sh "
            . "| jq -S --arg root \"\$REPO\" 'walk(if type==\"object\" then (if has(\"line\") then .line=0 else . end) "
            . "elif type==\"string\" then gsub(\$root;\"<REPO>\") else . end)' "
            . "> tests/php/fixtures/ai-search-contract.golden.json"
        );
    }

    public function testHelpModesAndParamsHaveNoDriftFromContract(): void
    {
        // Every mode and param token rendered in --format=help must exist in the
        // JSON contract, and every contract mode must appear in the help. This
        // is the help/JSON drift guard.
        $env = $this->targetEnvelope();
        $help = $this->runEngine(['--format=help', self::$target], false)['stdout'];

        $modeNames = array_map(static fn(array $m): string => (string) $m['name'], $env['modes']);
        foreach ($modeNames as $mode) {
            $this->assertStringContainsString(
                $mode,
                $help,
                "contract mode '{$mode}' missing from generated help (drift)"
            );
        }

        // Params shown in help (excluding unknown-option fallbacks) must be real
        // contract params.
        $paramNames = array_map(static fn(array $p): string => (string) $p['name'], $env['params']);
        foreach (preg_split('/\R/', $help) ?: [] as $line) {
            if (preg_match('/^\s{2,}(--[a-z][\w-]*)\b/', $line, $m)) {
                $this->assertContains(
                    $m[1],
                    $paramNames,
                    "help renders flag '{$m[1]}' that is not in the JSON contract (drift)"
                );
            }
        }
    }

    // ---- parser edge-case fixtures --------------------------------------

    public function testEdgeCaseHeredocBodyNotParsedAsCode(): void
    {
        // A heredoc body must NOT leak into CODE-derived surfaces: case-like
        // text must not become a mode, and danger commands inside help text must
        // not become real command findings or escalate risk. (Documented
        // `--flag` rows legitimately become usage-doc params — that is the
        // documentation surface, not a code leak, so it is intentionally allowed.)
        $env = $this->fixtureEnvelope(<<<'SH'
#!/usr/bin/env bash
usage() {
  cat <<'TXT'
fake-mode)   this is help text, not a real case branch
  rm -rf /   documented danger, not an execution
  eval "$x"  also documented, not executed
TXT
}
real() { echo hi; }
SH);
        $modeNames = array_map(static fn(array $m): string => (string) $m['name'], $env['modes']);
        $this->assertNotContains('fake-mode', $modeNames, 'heredoc text must not become a mode');
        // The `rm -rf /` and `eval` inside the heredoc must not be command findings.
        $cmdNames = array_map(static fn(array $c): string => (string) $c['name'], $env['commands']);
        $this->assertNotContains('rm', $cmdNames, 'heredoc danger text must not become a command');
        $this->assertNotContains('eval', $cmdNames, 'heredoc eval text must not become a command');
        $this->assertNotSame('critical', $env['risk_summary']['max_risk']);
        $this->assertFalse($env['risk_summary']['has_dynamic_execution'], 'heredoc eval must not flag dynamic execution');
    }

    public function testEdgeCaseNestedCaseBranchesDoNotDoubleCount(): void
    {
        $env = $this->fixtureEnvelope(<<<'SH'
#!/usr/bin/env bash
is_content_mode() {
  case "$1" in
  alpha|beta) return 0 ;;
  *) return 1 ;;
  esac
}
dispatch() {
  case "$mode" in
  alpha)
    case "$inner" in
      x) echo x ;;
      y) echo y ;;
    esac
    ;;
  beta) echo beta ;;
  esac
}
SH);
        $modeNames = array_map(static fn(array $m): string => (string) $m['name'], $env['modes']);
        $this->assertContains('alpha', $modeNames);
        $this->assertContains('beta', $modeNames);
        // Inner case labels x/y must not be promoted to modes.
        $this->assertNotContains('x', $modeNames);
        $this->assertNotContains('y', $modeNames);
        // Modes are de-duplicated (alpha appears in family guard + dispatch).
        $this->assertSame(count(array_unique($modeNames)), count($modeNames), 'modes must be unique');
    }

    public function testEdgeCaseEmptyScriptProducesValidEnvelope(): void
    {
        $env = $this->fixtureEnvelope("#!/usr/bin/env bash\n");
        $this->assertSame('ai.sh-introspect/v1', $env['schema']);
        $this->assertSame('ok', $env['status']);
        $this->assertSame([], $env['modes']);
        $this->assertSame([], $env['params']);
        $this->assertFalse($env['meta']['target_executed']);
    }

    public function testEdgeCaseFlagWithValuePlaceholderAndAlias(): void
    {
        // `--opt VALUE | -o VALUE` style with a shift => takes_value + alias.
        $env = $this->fixtureEnvelope(<<<'SH'
#!/usr/bin/env bash
parse() {
  case "$1" in
  --opt | -o) shift; OPT="$1" ;;
  --bool) FLAG=1 ;;
  --rep) REP+=("x") ;;
  esac
}
SH);
        $byName = [];
        foreach ($env['params'] as $p) {
            $byName[(string) $p['name']] = $p;
        }
        $this->assertArrayHasKey('--opt', $byName);
        $this->assertTrue($byName['--opt']['takes_value'], '--opt must take a value (shift)');
        $this->assertContains('-o', $byName['--opt']['aliases']);
        $this->assertArrayHasKey('--bool', $byName);
        $this->assertFalse($byName['--bool']['takes_value'], '--bool takes no value');
        $this->assertArrayHasKey('--rep', $byName);
        $this->assertTrue($byName['--rep']['repeatable'], '--rep must be repeatable (+=)');
    }
}
