<?php

declare(strict_types=1);

namespace Tests\ShIntrospect;

use Tests\Support\ShIntrospectTestCase;

/**
 * Flag/param contract: aliases, value requirements, scope, mode mapping.
 */
class ShIntrospectParamContractTest extends ShIntrospectTestCase
{
    public function testParamsMayCarryAppliesToModesWithValidModeNames(): void
    {
        $env = $this->targetEnvelope();
        $modeNames = array_map(static fn(array $m): string => (string) $m['name'], $env['modes']);
        $sawMapping = false;
        foreach ($env['params'] as $param) {
            if (!array_key_exists('applies_to_modes', $param)) {
                continue;
            }
            $sawMapping = true;
            $this->assertIsArray($param['applies_to_modes']);
            $this->assertNotEmpty($param['applies_to_modes']);
            foreach ($param['applies_to_modes'] as $m) {
                $this->assertContains((string) $m, $modeNames, "applies_to_modes '{$m}' must be a real mode");
            }
        }
        $this->assertTrue($sawMapping, 'ai-search has explicit mode-scoped flags');
    }

    public function testAppliesToModesIsStrictAndAccurate(): void
    {
        $env = $this->targetEnvelope();
        $params = $this->paramsByName($env);

        // Correct, explicit `mode:` qualifiers in the usage doc.
        $this->assertArrayHasKey('--messages', $params);
        $this->assertSame(['history'], $params['--messages']['applies_to_modes'] ?? null);
        $this->assertArrayHasKey('--patch', $params);
        $this->assertSame(['history'], $params['--patch']['applies_to_modes'] ?? null);
        $this->assertArrayHasKey('--staged', $params);
        $this->assertSame(['diff'], $params['--staged']['applies_to_modes'] ?? null);

        // --lang is genuinely structural-mode-scoped (documented as
        // `struct/symbols/class language`), detected via the slash-mode-run
        // signal — not a false positive.
        $this->assertArrayHasKey('--lang', $params);
        $this->assertSame(['class', 'struct', 'symbols'], $params['--lang']['applies_to_modes'] ?? null);
        $this->assertSame('mode-specific', $params['--lang']['scope'] ?? null);

        // Old over-matching false positives must be gone: global pattern flags
        // must NOT be scoped to a single incidental mode.
        foreach (['--fixed', '--regex', '--ignore-case', '--files-with-matches'] as $globalFlag) {
            if (isset($params[$globalFlag])) {
                $this->assertArrayNotHasKey(
                    'applies_to_modes',
                    $params[$globalFlag],
                    "global flag '{$globalFlag}' must not be falsely scoped to a mode"
                );
            }
        }
    }

    public function testEveryParamCarriesScope(): void
    {
        $env = $this->targetEnvelope();
        // P1: no ambiguity for public flags — every param must declare a scope of
        // either `global` or `mode-specific`.
        foreach ($env['params'] as $param) {
            $this->assertArrayHasKey('scope', $param, "param '{$param['name']}' must carry a scope");
            $this->assertContains($param['scope'], ['global', 'mode-specific']);
            // mode-specific implies applies_to_modes; global implies it is absent.
            if ($param['scope'] === 'mode-specific') {
                $this->assertArrayHasKey('applies_to_modes', $param, "{$param['name']} mode-specific must list modes");
            } else {
                $this->assertArrayNotHasKey('applies_to_modes', $param, "{$param['name']} global must not list modes");
            }
        }
    }

    public function testParamsIncludeExpectedFlags(): void
    {
        $env = $this->targetEnvelope();
        $params = $this->paramsByName($env);
        foreach (['--fixed', '--glob', '--context', '--lang'] as $flag) {
            $this->assertArrayHasKey($flag, $params, "expected param '{$flag}' not found");
        }
    }

    public function testFixedFlagTakesNoValue(): void
    {
        $env = $this->targetEnvelope();
        $params = $this->paramsByName($env);
        $this->assertArrayHasKey('--fixed', $params);
        $this->assertFalse($params['--fixed']['takes_value'], '--fixed must not take a value');
    }

    public function testGlobFlagTakesValueAndIsRepeatable(): void
    {
        $env = $this->targetEnvelope();
        $params = $this->paramsByName($env);
        $this->assertArrayHasKey('--glob', $params);
        $this->assertTrue($params['--glob']['takes_value'], '--glob must take a value');
        $this->assertTrue($params['--glob']['repeatable'], '--glob must be repeatable');
    }

    public function testContextFlagHasShortAlias(): void
    {
        $env = $this->targetEnvelope();
        $params = $this->paramsByName($env);
        $this->assertArrayHasKey('--context', $params);
        $this->assertContains('-C', $params['--context']['aliases'], '--context must alias -C');
    }

    public function testBaseAndLangFlagsTakeValue(): void
    {
        $env = $this->targetEnvelope();
        $params = $this->paramsByName($env);
        $this->assertArrayHasKey('--base', $params);
        $this->assertArrayHasKey('--lang', $params);
        $this->assertTrue($params['--base']['takes_value'], '--base must take a value');
        $this->assertTrue($params['--lang']['takes_value'], '--lang must take a value');
    }

    public function testParamsDoNotIncludeUnknownOptionFallback(): void
    {
        $env = $this->targetEnvelope();
        $names = array_map(static fn(array $p): string => (string) $p['name'], $env['params']);
        $this->assertNotContains('--*', $names, '`--*` must not be a param');
        $this->assertNotContains('-*', $names, '`-*` must not be a param');
    }
}
