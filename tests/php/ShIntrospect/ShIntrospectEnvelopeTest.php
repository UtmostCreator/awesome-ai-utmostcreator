<?php

declare(strict_types=1);

namespace Tests\ShIntrospect;

use Tests\Support\ShIntrospectTestCase;

/**
 * Top-level single-file envelope/surface contract for sh-introspect.
 */
class ShIntrospectEnvelopeTest extends ShIntrospectTestCase
{
    // ---- envelope shape -------------------------------------------------

    public function testEnvelopeHasAllRequiredKeys(): void
    {
        $env = $this->targetEnvelope();
        foreach ([
            'schema', 'status', 'tool', 'kind', 'file', 'path', 'functions',
            'modes', 'mode_contracts', 'params', 'positionals', 'case_labels',
            'json_keys', 'json_key_candidates', 'json_paths', 'output_schemas',
            'env_inputs', 'sources', 'dependencies', 'unknown_option_handlers',
            'commands', 'side_effects', 'risk_summary', 'risk_findings',
            'examples', 'examples_by_mode', 'warnings', 'errors', 'meta',
        ] as $key) {
            $this->assertArrayHasKey($key, $env, "envelope missing key: {$key}");
        }
        $this->assertArrayHasKey('parser', $env['meta']);
        $this->assertArrayHasKey('confidence', $env['meta']);
        $this->assertArrayHasKey('target_executed', $env['meta']);

        // The misleading legacy `keys` field must be gone (renamed to case_labels).
        $this->assertFalse(array_key_exists('keys', $env), '`keys` must be removed');
    }

    public function testFileIsAbsolutePathStringAndPathObjectExists(): void
    {
        $env = $this->targetEnvelope();
        // `.file` stays the absolute path string (backward compatible).
        $this->assertIsString($env['file']);
        $this->assertStringEndsWith('ai-search.sh', (string) $env['file']);
        $this->assertStringStartsWith('/', (string) $env['file'], '.file must be an absolute path');

        // `.path` is the additive structured object.
        $this->assertIsArray($env['path']);
        $this->assertArrayHasKey('absolute', $env['path']);
        $this->assertArrayHasKey('relative', $env['path']);
        $this->assertSame($env['file'], $env['path']['absolute']);
        $this->assertStringEndsWith('ai-search.sh', (string) $env['path']['relative']);
    }

    public function testAllOptionalTopLevelFieldsAreArraysNotNull(): void
    {
        $env = $this->targetEnvelope();
        foreach ([
            'functions', 'modes', 'mode_contracts', 'params', 'positionals',
            'case_labels', 'json_keys', 'json_key_candidates', 'json_paths',
            'output_schemas', 'env_inputs', 'sources', 'dependencies',
            'unknown_option_handlers', 'commands', 'side_effects',
            'risk_findings', 'examples', 'examples_by_mode',
            'warnings', 'errors',
        ] as $field) {
            $this->assertIsArray($env[$field], "{$field} must be an array");
        }
    }

    public function testEnvelopeIdentityFields(): void
    {
        $env = $this->targetEnvelope();
        $this->assertSame('ai.sh-introspect/v1', $env['schema']);
        $this->assertSame('ok', $env['status']);
        $this->assertSame('sh-introspect', $env['tool']);
        $this->assertFalse($env['meta']['target_executed']);
        $this->assertStringEndsWith('ai-search.sh', (string) $env['file']);
    }

    // ---- functions ------------------------------------------------------

    public function testFunctionsIncludeKnownDefinitions(): void
    {
        $env = $this->targetEnvelope();
        $names = array_map(static fn(array $f): string => (string) $f['name'], $env['functions']);
        foreach (['usage', 'emit_json', 'run_diff_mode', 'run_history_mode', 'run_ast_mode'] as $fn) {
            $this->assertContains($fn, $names, "expected function '{$fn}' not found");
        }
    }

    // ---- modes ----------------------------------------------------------

    public function testModesIncludeExpectedNames(): void
    {
        $env = $this->targetEnvelope();
        $names = array_map(static fn(array $m): string => (string) $m['name'], $env['modes']);
        foreach (['text', 'tracked', 'diff', 'history', 'class', 'files', 'docs'] as $mode) {
            $this->assertContains($mode, $names, "expected mode '{$mode}' not found");
        }
    }

    public function testModesExcludeFlagValueTokens(): void
    {
        $env = $this->targetEnvelope();
        $names = array_map(static fn(array $m): string => (string) $m['name'], $env['modes']);
        foreach (['ignore', 'smart', 'sensitive', 'fixed', 'pcre2'] as $notMode) {
            $this->assertNotContains($notMode, $names, "'{$notMode}' must not be classified as a mode");
        }
    }

    // ---- case_labels ----------------------------------------------------

    public function testCaseLabelsIsArrayAndKeysFieldRemoved(): void
    {
        $env = $this->targetEnvelope();
        $this->assertIsArray($env['case_labels']);
        $this->assertFalse(array_key_exists('keys', $env), '`keys` must no longer exist');

        $names = array_map(static fn(array $k): string => (string) $k['name'], $env['case_labels']);
        // Noise labels must be dropped.
        foreach (['0', '1', '2'] as $intLabel) {
            $this->assertNotContains($intLabel, $names, "integer noise label '{$intLabel}' must be dropped");
        }
        foreach ($names as $name) {
            $this->assertDoesNotMatchRegularExpression('/[*"$()]/', $name, "case label '{$name}' contains glob/quote junk");
        }
        // De-duplicated.
        $this->assertSame(array_values(array_unique($names)), $names, 'case_labels must be de-duplicated');
    }

    // ---- json_keys ------------------------------------------------------

    public function testJsonKeysIncludeEnvelopeKeys(): void
    {
        $env = $this->targetEnvelope();
        $this->assertIsArray($env['json_keys']);
        $names = array_map(static fn(array $k): string => (string) $k['name'], $env['json_keys']);
        foreach ([
            'schema', 'status', 'tool', 'query', 'mode', 'matches',
            'results', 'warnings', 'errors', 'limits', 'meta',
        ] as $key) {
            $this->assertContains($key, $names, "expected json_key '{$key}' not found");
        }
    }

    // ---- unknown_option_handlers ---------------------------------------

    public function testUnknownOptionHandlersContainDashDashStarFail(): void
    {
        $env = $this->targetEnvelope();
        $this->assertIsArray($env['unknown_option_handlers']);
        $this->assertNotEmpty($env['unknown_option_handlers'], 'expected at least one unknown-option handler');

        $byPattern = [];
        foreach ($env['unknown_option_handlers'] as $u) {
            $byPattern[(string) $u['pattern']] = $u;
        }
        $this->assertArrayHasKey('--*', $byPattern, 'expected `--*` unknown-option handler');
        $this->assertSame('fail', $byPattern['--*']['action'], '`--*` handler must fail on unknown flags');
    }

    // ---- dependencies ---------------------------------------------------

    public function testDependenciesIncludeCoreTools(): void
    {
        $env = $this->targetEnvelope();
        $this->assertIsArray($env['dependencies']);
        $names = array_map(static fn(array $d): string => (string) $d['name'], $env['dependencies']);
        foreach (['git', 'rg', 'jq'] as $dep) {
            $this->assertContains($dep, $names, "expected dependency '{$dep}' not found");
        }
    }

    public function testDependenciesAreClassifiedAndCategorised(): void
    {
        $env = $this->targetEnvelope();
        $byName = [];
        foreach ($env['dependencies'] as $d) {
            $byName[(string) $d['name']] = $d;
        }
        foreach (['git', 'rg', 'jq'] as $dep) {
            $this->assertArrayHasKey($dep, $byName);
            $this->assertArrayHasKey('classification', $byName[$dep], "{$dep} must be classified");
            $this->assertContains(
                $byName[$dep]['classification'],
                ['required', 'optional', 'candidate'],
                "{$dep} classification must be a known value"
            );
            $this->assertArrayHasKey('category', $byName[$dep]);
            $this->assertContains($byName[$dep]['category'], ['base-utility', 'primary-tool']);
        }
        // Primary tools vs base utilities are distinguished.
        $this->assertSame('primary-tool', $byName['rg']['category']);
        if (isset($byName['cat'])) {
            $this->assertSame('base-utility', $byName['cat']['category']);
        }
    }

    // ---- positionals ----------------------------------------------------

    public function testPositionalsIsArray(): void
    {
        $env = $this->targetEnvelope();
        $this->assertIsArray($env['positionals']);
    }

    // ---- meta -----------------------------------------------------------

    public function testTargetExecutedRemainsFalse(): void
    {
        $env = $this->targetEnvelope();
        $this->assertFalse($env['meta']['target_executed']);
    }

    // ---- env_inputs -----------------------------------------------------

    public function testEnvInputsIncludeKnownVariables(): void
    {
        $env = $this->targetEnvelope();
        $names = array_map(static fn(array $e): string => (string) $e['name'], $env['env_inputs']);
        foreach (['AI_OUTPUT', 'AI_LANG', 'AI_SEARCH_STRICT'] as $var) {
            $this->assertContains($var, $names, "expected env input '{$var}' not found");
        }
    }

    public function testEnvInputsExcludePlainAssignments(): void
    {
        $env = $this->targetEnvelope();
        $names = array_map(static fn(array $e): string => (string) $e['name'], $env['env_inputs']);
        $this->assertNotContains(
            'DEFAULT_MAX_RESULTS',
            $names,
            'plain assignment DEFAULT_MAX_RESULTS must not be reported as an env input'
        );
    }

    // ---- sources --------------------------------------------------------

    public function testSourcesReferenceCommonSh(): void
    {
        $env = $this->targetEnvelope();
        $targets = array_map(static fn(array $s): string => (string) $s['target'], $env['sources']);
        $matched = false;
        foreach ($targets as $t) {
            if (str_contains($t, 'common.sh')) {
                $matched = true;
                break;
            }
        }
        $this->assertTrue($matched, 'sources must include a target referencing common.sh');
    }
}
