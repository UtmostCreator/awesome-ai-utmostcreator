<?php

declare(strict_types=1);

namespace Tests\ShIntrospect;

use Tests\Support\ShIntrospectTestCase;

/**
 * Command extraction, read-only/mutation risk classification, source
 * resolution, and --strict-risk behaviour.
 */
class ShIntrospectRiskCommandTest extends ShIntrospectTestCase
{
    public function testDangerousCommandClassesAreDetectedWithRisk(): void
    {
        $env = $this->fixtureEnvelope($this->dangerFixture());
        $cmds = $this->commandsByName($env);

        $expected = [
            'rm'             => 'critical', // rm -rf with $expansion
            'eval'           => 'critical',
            'curl|sh'        => 'critical',
            'git reset'      => 'critical', // git reset --hard is irreversible
            'truncate'       => 'high',
            'chmod -R'       => 'high',
            'chown -R'       => 'high',
            'rsync --delete' => 'high',
            'npm-install'    => 'high',
        ];
        foreach ($expected as $name => $risk) {
            $this->assertArrayHasKey($name, $cmds, "command '{$name}' must be detected");
            $this->assertSame($risk, $cmds[$name]['risk'], "command '{$name}' must be risk={$risk}");
            $this->assertArrayHasKey('line', $cmds[$name]);
            $this->assertArrayHasKey('argv_hint', $cmds[$name]);
            $this->assertArrayHasKey('effect', $cmds[$name]);
        }
    }

    public function testCriticalCommandEscalatesMaxRisk(): void
    {
        $env = $this->fixtureEnvelope($this->dangerFixture());
        $this->assertSame('critical', $env['risk_summary']['max_risk']);
        $this->assertTrue($env['risk_summary']['has_mutation']);
        $this->assertTrue($env['risk_summary']['has_dynamic_execution']);
    }

    public function testProseMentionsDoNotCreateCommands(): void
    {
        $env = $this->fixtureEnvelope($this->dangerFixture());
        // The comment line "prose mentions truncate" must not add a 2nd truncate
        // beyond the real `truncate -s 0` invocation.
        $truncates = array_filter(
            $env['commands'],
            static fn(array $c): bool => (string) $c['name'] === 'truncate'
        );
        $this->assertCount(1, $truncates, 'only the real truncate invocation counts');
    }

    public function testReadOnlyScriptReportsOnlyLowRiskReadCommands(): void
    {
        $env = $this->targetEnvelope();
        $this->assertNotEmpty($env['commands'], 'read-only script still surfaces its read commands');
        foreach ($env['commands'] as $c) {
            $this->assertSame('low', $c['risk'], "read-only script command '{$c['name']}' must be low risk");
            $this->assertContains(
                (string) $c['effect'],
                ['git-read', 'filesystem-read'],
                "read-only script effect must be a read effect, got '{$c['effect']}'"
            );
        }
        $this->assertNotSame('critical', $env['risk_summary']['max_risk']);
        $this->assertFalse($env['risk_summary']['has_mutation']);
    }

    public function testCommandsAreDeduplicatedByNameEffectRiskKind(): void
    {
        $env = $this->targetEnvelope();
        $seen = [];
        foreach ($env['commands'] as $c) {
            // `kind` is part of the identity: a tool that appears as both a
            // dependency-check (e.g. a `for tool in ... rg` loop) and a real
            // invocation is legitimately two distinct entries.
            $key = $c['name'] . '|' . $c['effect'] . '|' . $c['risk'] . '|' . ($c['kind'] ?? '');
            $this->assertArrayNotHasKey($key, $seen, "duplicate command entry: {$key}");
            $seen[$key] = true;
        }
    }

    public function testCommandsCarrySourceAndKind(): void
    {
        $env = $this->targetEnvelope();
        $this->assertNotEmpty($env['commands']);
        foreach ($env['commands'] as $c) {
            $this->assertArrayHasKey('source', $c, "command '{$c['name']}' must carry a source");
            $this->assertArrayHasKey('kind', $c, "command '{$c['name']}' must carry a kind");
            $this->assertContains($c['kind'], ['invocation', 'dependency-check']);
        }
    }

    public function testToolLoopAndProbeSitesAreClassifiedAsDependencyChecks(): void
    {
        $env = $this->targetEnvelope();
        // ai-search.sh probes its tools with `for tool in jq git rg ast-grep`
        // and `command_exists ast-grep`; those sites must NOT be reported as
        // real executions.
        $depChecks = array_filter(
            $env['commands'],
            static fn(array $c): bool => (string) ($c['kind'] ?? '') === 'dependency-check'
        );
        $this->assertNotEmpty($depChecks, 'tool-loop / probe sites must be classified as dependency-check');

        $sources = array_map(static fn(array $c): string => (string) $c['source'], $depChecks);
        $this->assertNotEmpty(
            array_intersect(['tool-loop', 'command-check'], $sources),
            'dependency-check commands must record a tool-loop or command-check source'
        );

        // Real invocations are still surfaced (e.g. git rev-parse runs for real).
        $invocations = array_filter(
            $env['commands'],
            static fn(array $c): bool => (string) ($c['kind'] ?? '') === 'invocation'
        );
        $this->assertNotEmpty($invocations, 'real command invocations must still be reported');
    }

    public function testReadCommandsIncludeGitReadOnRealScript(): void
    {
        $env = $this->targetEnvelope();
        $byName = $this->commandsByName($env);
        $this->assertArrayHasKey('git rev-parse', $byName, 'ai-search runs git rev-parse');
        $this->assertSame('git-read', $byName['git rev-parse']['effect']);
        $this->assertSame('low', $byName['git rev-parse']['risk']);
    }

    // ---- risk_findings[] -----------------------------------------------

    public function testRiskFindingsExplainCriticalCommands(): void
    {
        $env = $this->fixtureEnvelope($this->dangerFixture());
        $this->assertIsArray($env['risk_findings']);
        $this->assertNotEmpty($env['risk_findings']);
        $codes = array_map(static fn(array $f): string => (string) $f['code'], $env['risk_findings']);
        $this->assertContains('dynamic-execution', $codes);
        $this->assertContains('filesystem-delete', $codes);
        foreach ($env['risk_findings'] as $f) {
            foreach (['code', 'risk', 'line', 'detail'] as $k) {
                $this->assertArrayHasKey($k, $f, "risk finding missing '{$k}'");
            }
        }
    }

    public function testRiskFindingsAreSortedByLine(): void
    {
        $env = $this->fixtureEnvelope($this->dangerFixture());
        $lines = array_map(static fn(array $f): int => (int) $f['line'], $env['risk_findings']);
        $sorted = $lines;
        sort($sorted, SORT_NUMERIC);
        $this->assertSame($sorted, $lines, 'risk_findings must be sorted by line');
    }

    // ---- side_effects / risk -------------------------------------------

    public function testSideEffectsAreReadOnlyForSearchScript(): void
    {
        $env = $this->targetEnvelope();
        $this->assertIsArray($env['side_effects']);
        $types = array_map(static fn(array $s): string => (string) $s['type'], $env['side_effects']);
        $this->assertNotContains('git-mutation', $types, 'read-only search script must not report git-mutation');
        $this->assertNotContains('filesystem-write', $types, 'read-only search script must not report filesystem-write');
    }

    public function testRiskSummaryHasSafeDefaults(): void
    {
        $env = $this->targetEnvelope();
        $risk = $env['risk_summary'];
        $this->assertIsArray($risk);
        foreach (['max_risk', 'has_mutation', 'has_dynamic_execution', 'has_unresolved_source'] as $k) {
            $this->assertArrayHasKey($k, $risk, "risk_summary missing key: {$k}");
        }
        $this->assertFalse($risk['has_mutation'], 'search script has no mutation');
        $this->assertFalse($risk['has_dynamic_execution'], 'search script has no eval/bash -c/sh -c');
    }

    public function testHighRiskCommandEscalatesSummaryWithoutSideEffectMatch(): void
    {
        $env = $this->fixtureEnvelope(<<<'SH'
#!/usr/bin/env bash
install_tools() {
  run_cmd npm install -g repomix
}
SH);

        $byName = $this->commandsByName($env);
        $this->assertArrayHasKey('npm-install', $byName, 'wrapped npm install must be detected');
        $this->assertSame('high', $byName['npm-install']['risk']);
        $this->assertSame('high', $env['risk_summary']['max_risk']);
        $this->assertTrue($env['risk_summary']['has_mutation']);
    }

    public function testSystemPackageInstallIsHighRiskInstaller(): void
    {
        $env = $this->fixtureEnvelope(<<<'SH'
#!/usr/bin/env bash
install_tools() {
  run_cmd sudo apt-get install -y git php-cli ripgrep jq
}
SH);

        $byName = $this->commandsByName($env);
        $this->assertArrayHasKey('apt-get-install', $byName, 'apt-get install must be detected');
        $this->assertSame('high', $byName['apt-get-install']['risk']);
        $this->assertSame('installer', $byName['apt-get-install']['effect']);
        $this->assertSame('high', $env['risk_summary']['max_risk']);
        $this->assertTrue($env['risk_summary']['has_mutation']);
    }

    // ---- source resolution + unresolved-source warning -----------------

    public function testCommonShSourceIsResolved(): void
    {
        $env = $this->targetEnvelope();
        $resolved = null;
        foreach ($env['sources'] as $src) {
            if (str_contains((string) $src['target'], 'common.sh')) {
                $resolved = $src;
                break;
            }
        }
        $this->assertNotNull($resolved, 'common.sh source must be present');
        $this->assertArrayHasKey('resolved', $resolved, 'common.sh source must be resolved');
        // resolved is the repo-relative path (keeps directory context).
        $this->assertSame('scripts/ai/common.sh', $resolved['resolved']);
        $this->assertTrue($resolved['exists'], 'resolved common.sh must exist on disk');
        $this->assertTrue($resolved['inside_repo'], 'resolved common.sh must be inside the repo');
    }

    public function testResolvedSourceDoesNotFlagUnresolved(): void
    {
        $env = $this->targetEnvelope();
        $this->assertFalse(
            $env['risk_summary']['has_unresolved_source'],
            'a resolved $(dirname ...)/common.sh must not flag has_unresolved_source'
        );
        $warnings = implode("\n", array_map('strval', $env['warnings']));
        $this->assertStringNotContainsString('has_unresolved_source', $warnings);
    }

    public function testUnresolvedDynamicSourceWarnsAndFlags(): void
    {
        $env = $this->fixtureEnvelope(<<<'SH'
#!/usr/bin/env bash
load() {
  source "$SOME_DIR/plugin.sh"
}
SH);
        $this->assertTrue($env['risk_summary']['has_unresolved_source']);
        $warnings = implode("\n", array_map('strval', $env['warnings']));
        $this->assertStringContainsString('has_unresolved_source', $warnings);
        $codes = array_map(static fn(array $f): string => (string) $f['code'], $env['risk_findings']);
        $this->assertContains('unresolved-source', $codes);
    }

    // ---- dangerous-command classification + --strict-risk ---------------

    public function testDangerFixtureClassifiesAllCriticalClasses(): void
    {
        $env = $this->dangerFixtureEnvelope();
        $byName = [];
        foreach ($env['commands'] as $c) {
            if (!isset($byName[(string) $c['name']])) {
                $byName[(string) $c['name']] = $c;
            }
        }
        $expectedCritical = ['eval', 'sh -c', 'source $0', 'rm', 'git reset', 'git clean', 'git push', 'curl|sh'];
        foreach ($expectedCritical as $name) {
            $this->assertArrayHasKey($name, $byName, "danger fixture must detect '{$name}'");
            $this->assertSame('critical', $byName[$name]['risk'], "'{$name}' must be critical");
        }
        // Non-self dynamic source is high (dynamic-source), not critical.
        $this->assertArrayHasKey('source', $byName);
        $this->assertSame('high', $byName['source']['risk']);
        $this->assertSame('dynamic-source', $byName['source']['effect']);
    }

    public function testDangerFixtureEscalatesMaxRiskAndFlags(): void
    {
        $env = $this->dangerFixtureEnvelope();
        $this->assertSame('critical', $env['risk_summary']['max_risk']);
        $this->assertTrue($env['risk_summary']['has_mutation']);
        $this->assertTrue($env['risk_summary']['has_dynamic_execution']);
    }

    public function testDangerFixtureProseMentionsDoNotCreateCommands(): void
    {
        $env = $this->dangerFixtureEnvelope();
        // The trailing comment naming rm -rf / eval must not add phantom entries:
        // each (name|effect|risk|kind) is unique already, so just assert no
        // dependency-check-kind eval/rm sneaks in from the comment.
        foreach ($env['commands'] as $c) {
            if (in_array((string) $c['name'], ['eval', 'rm'], true)) {
                $this->assertSame('invocation', $c['kind'] ?? '', 'comment text must not create a non-invocation command');
            }
        }
    }

    public function testStrictRiskExitsNonZeroOnCriticalSingleFile(): void
    {
        $danger = self::$repoRoot . '/tests/php/fixtures/danger-strict-risk.sh';
        $result = $this->runEngine(['--strict-risk', $danger], false);
        $this->assertSame(3, $result['exit'], '--strict-risk must exit 3 on a critical script');
        $this->assertStringContainsString('STRICT-RISK', $result['stderr']);
    }

    public function testStrictRiskExitsZeroOnSafeScript(): void
    {
        // The read-only target script must pass the strict-risk gate.
        $result = $this->runEngine(['--strict-risk', self::$target], false);
        $this->assertSame(0, $result['exit'], '--strict-risk must exit 0 on a low-risk script');
    }

    public function testStrictRiskAllIndexFailsOnCriticalMember(): void
    {
        // Point --all at the fixtures dir (contains the danger fixture) so the
        // index strict-risk gate trips.
        $fixturesDir = self::$repoRoot . '/tests/php/fixtures';
        $result = $this->runEngine(['--all', '--strict-risk', $fixturesDir], true);
        $this->assertSame(3, $result['exit'], '--all --strict-risk must exit 3 when any member is critical');
        $this->assertStringContainsString('STRICT-RISK', $result['stderr']);
    }

    public function testDynamicSourceVariantsAreClassified(): void
    {
        // Inline fixtures pin the source classification matrix.
        $env = $this->fixtureEnvelope(<<<'SH'
#!/usr/bin/env bash
load() {
  source "$EXT"
  . "$0"
}
SH);
        $byName = [];
        foreach ($env['commands'] as $c) {
            $byName[(string) $c['name']] = $c;
        }
        $this->assertArrayHasKey('source', $byName, 'dynamic external source must be a command');
        $this->assertSame('high', $byName['source']['risk']);
        $this->assertArrayHasKey('source $0', $byName, 'self re-source must be a critical command');
        $this->assertSame('critical', $byName['source $0']['risk']);
    }
}
