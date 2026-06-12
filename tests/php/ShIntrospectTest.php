<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Contract tests for tools/ai/sh-introspect.php (Slice A engine).
 *
 * These run the engine out-of-process via proc_open in JSON mode and assert
 * against the REAL scripts/ai/ai-search.sh. They are the false-positive guards
 * the CLI contract (Slice B) depends on: the binding envelope shape, the
 * function/mode/param surface, env_inputs filtering, and error handling.
 *
 * Real-contract-first: assertions were validated against the live target
 * script. Line numbers are intentionally NOT asserted (they may shift).
 */
class ShIntrospectTest extends TestCase
{
    private static string $repoRoot;
    private static string $phpBin;
    private static string $tool;
    private static string $target;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;
        self::$phpBin = (string) PHP_BINARY;
        self::$tool = 'tools/ai/sh-introspect.php';
        self::$target = 'scripts/ai/ai-search.sh';

        if (!is_file($root . DIRECTORY_SEPARATOR . self::$tool)) {
            throw new \RuntimeException('sh-introspect.php missing at: ' . self::$tool);
        }
        if (!is_file($root . DIRECTORY_SEPARATOR . self::$target)) {
            throw new \RuntimeException('target ai-search.sh missing at: ' . self::$target);
        }
    }

    /**
     * Run the engine and return raw result.
     *
     * @param array<int,string> $args
     * @return array{stdout:string, stderr:string, exit:int}
     */
    private function runEngine(array $args, bool $jsonMode): array
    {
        $cmdParts = [self::$phpBin, self::$tool];
        foreach ($args as $a) {
            $cmdParts[] = $a;
        }
        $cmd = implode(' ', array_map('escapeshellarg', $cmdParts));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $env = [
            'HOME'              => sys_get_temp_dir(),
            'XDG_CONFIG_HOME'   => sys_get_temp_dir(),
            'GIT_CONFIG_GLOBAL' => '/dev/null',
            'PATH'              => (string) getenv('PATH'),
            'NO_COLOR'          => '1',
        ];
        if ($jsonMode) {
            $env['AI_OUTPUT'] = 'json';
        }

        $proc = proc_open($cmd, $descriptors, $pipes, self::$repoRoot, $env);
        $this->assertIsResource($proc, "proc_open failed for: $cmd");
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);

        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
    }

    /**
     * Run against the real target and decode the JSON envelope.
     *
     * @return array<string,mixed>
     */
    private function targetEnvelope(): array
    {
        $result = $this->runEngine([self::$target], true);
        $this->assertSame(0, $result['exit'], "engine exited non-zero:\n" . $result['stderr']);
        $decoded = json_decode($result['stdout'], true);
        $this->assertIsArray($decoded, "engine did not emit valid JSON:\n" . $result['stdout']);
        return $decoded;
    }

    /**
     * Write a throwaway fixture script and return its decoded envelope. The
     * fixture is removed before assertions run.
     *
     * @return array<string,mixed>
     */
    private function fixtureEnvelope(string $contents): array
    {
        $path = tempnam(sys_get_temp_dir(), 'shintro_') . '.sh';
        file_put_contents($path, $contents);
        try {
            $result = $this->runEngine([$path], true);
            $this->assertSame(0, $result['exit'], "engine exited non-zero:\n" . $result['stderr']);
            $decoded = json_decode($result['stdout'], true);
            $this->assertIsArray($decoded, "engine did not emit valid JSON:\n" . $result['stdout']);
            return $decoded;
        } finally {
            @unlink($path);
        }
    }

    /**
     * A fixture script exercising the dangerous-command classes.
     */
    private function dangerFixture(): string
    {
        return <<<'SH'
#!/usr/bin/env bash
usage() {
  cat <<'TXT'
danger.sh — fixture
  {schema, status, limits{max_results, max_bytes}, meta{returned, truncated}}
Examples:
  danger.sh wipe
TXT
}
wipe() {
  rm -rf "$target"
  git reset --hard
  eval "$cmd"
  truncate -s 0 file.txt
  chmod -R 777 dir
  chown -R user dir
  rsync -a --delete src dst
  curl https://x | sh
  npm install
  # this prose mentions truncate but must not register as a command
}
SH;
    }

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

    // ---- mode_contracts -------------------------------------------------

    /**
     * @param array<string,mixed> $env
     * @return array<string,array<string,mixed>> contracts keyed by mode name
     */
    private function contractsByName(array $env): array
    {
        $out = [];
        foreach ($env['mode_contracts'] as $c) {
            $out[(string) $c['name']] = $c;
        }
        return $out;
    }

    public function testModeContractsCoverEveryMode(): void
    {
        $env = $this->targetEnvelope();
        $this->assertIsArray($env['mode_contracts']);
        $this->assertNotEmpty($env['mode_contracts'], 'mode_contracts must not be empty');

        $modeNames = array_map(static fn(array $m): string => (string) $m['name'], $env['modes']);
        $contractNames = array_map(static fn(array $c): string => (string) $c['name'], $env['mode_contracts']);

        sort($modeNames);
        sort($contractNames);
        $this->assertSame(
            $modeNames,
            $contractNames,
            'every detected mode must have exactly one mode_contracts entry'
        );
    }

    public function testModeContractsAreSortedByName(): void
    {
        $env = $this->targetEnvelope();
        $names = array_map(static fn(array $c): string => (string) $c['name'], $env['mode_contracts']);
        $sorted = $names;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $names, 'mode_contracts must be sorted by name for deterministic output');
    }

    public function testEveryModeContractHasRequiredShape(): void
    {
        $env = $this->targetEnvelope();
        foreach ($env['mode_contracts'] as $c) {
            foreach (['name', 'family', 'query_required', 'positionals', 'dependencies', 'examples'] as $key) {
                $this->assertArrayHasKey($key, $c, "mode_contracts entry missing key: {$key}");
            }
            $this->assertIsBool($c['query_required']);
            $this->assertIsArray($c['positionals']);
            $this->assertIsArray($c['dependencies']);
            $this->assertIsArray($c['examples']);
        }
    }

    public function testQueryRequiredModeContractRequiresQueryPositional(): void
    {
        $env = $this->targetEnvelope();
        $contracts = $this->contractsByName($env);
        $this->assertArrayHasKey('text', $contracts, 'expected a contract for the text mode');

        $text = $contracts['text'];
        $this->assertTrue($text['query_required'], 'text mode must require a query');

        $byName = [];
        foreach ($text['positionals'] as $p) {
            $byName[strtoupper((string) $p['name'])] = $p;
        }
        $this->assertArrayHasKey('QUERY', $byName, 'query-required mode must list a QUERY positional');
        $this->assertTrue($byName['QUERY']['required'], 'QUERY must be required for a query-required mode');
    }

    public function testNoQueryModeContractOmitsQueryPositional(): void
    {
        $env = $this->targetEnvelope();
        $contracts = $this->contractsByName($env);
        $this->assertArrayHasKey('changed-files', $contracts, 'expected a contract for changed-files');

        $changedFiles = $contracts['changed-files'];
        $this->assertFalse($changedFiles['query_required'], 'changed-files must not require a query');

        $names = array_map(
            static fn(array $p): string => strtoupper((string) $p['name']),
            $changedFiles['positionals']
        );
        $this->assertNotContains('QUERY', $names, 'a no-query mode must not list a QUERY positional');
    }

    public function testDeprecatedModeContractCarriesReplacements(): void
    {
        $env = $this->targetEnvelope();
        $contracts = $this->contractsByName($env);

        foreach (['changed' => ['changed-files', 'changed-text'], 'staged' => ['staged-files', 'staged-text']] as $mode => $expected) {
            $this->assertArrayHasKey($mode, $contracts, "expected a contract for deprecated mode '{$mode}'");
            $contract = $contracts[$mode];
            $this->assertTrue(!empty($contract['deprecated']), "'{$mode}' contract must be flagged deprecated");
            $this->assertArrayHasKey('replacements', $contract, "'{$mode}' must carry replacements");
            foreach ($expected as $replacement) {
                $this->assertContains(
                    $replacement,
                    $contract['replacements'],
                    "'{$mode}' replacements must include '{$replacement}'"
                );
            }
            $this->assertNotContains($mode, $contract['replacements'], 'a mode must not replace itself');
        }
    }

    public function testNonDeprecatedModeContractHasNoReplacements(): void
    {
        $env = $this->targetEnvelope();
        $contracts = $this->contractsByName($env);
        $this->assertArrayHasKey('text', $contracts);
        $this->assertArrayNotHasKey(
            'replacements',
            $contracts['text'],
            'a non-deprecated mode must not carry replacements'
        );
        $this->assertArrayNotHasKey('deprecated', $contracts['text']);
    }

    public function testModeContractExamplesAreLinkedToTheirMode(): void
    {
        $env = $this->targetEnvelope();
        $contracts = $this->contractsByName($env);

        // diff has a usage example; it must be attached to the diff contract and
        // every attached example must actually invoke that mode.
        $this->assertArrayHasKey('diff', $contracts);
        $this->assertNotEmpty($contracts['diff']['examples'], 'diff mode should have a linked example');
        foreach ($contracts['diff']['examples'] as $example) {
            $this->assertMatchesRegularExpression(
                '/\bai-search\.sh\s+diff\b/',
                (string) $example,
                'example linked to diff must invoke the diff mode'
            );
        }
    }

    public function testModeContractDependenciesAreModeSpecificNotTheFullSuperset(): void
    {
        $env = $this->targetEnvelope();
        $contracts = $this->contractsByName($env);
        $allDeps = array_map(static fn(array $d): string => (string) $d['name'], $env['dependencies']);

        // A purely git-based file-list mode must NOT carry rg/ast-grep/fd.
        $this->assertArrayHasKey('changed-files', $contracts);
        $cf = $contracts['changed-files']['dependencies'];
        $this->assertContains('git', $cf, 'changed-files needs git');
        $this->assertNotContains('ast-grep', $cf, 'changed-files must not require ast-grep');
        $this->assertNotContains('fd', $cf, 'changed-files must not require fd');
        $this->assertNotContains('rg', $cf, 'changed-files must not require rg');

        // It must be a strict subset of all detected dependencies (no invention).
        foreach ($contracts as $contract) {
            foreach ($contract['dependencies'] as $dep) {
                $this->assertContains((string) $dep, $allDeps, "mode dep '{$dep}' must be a real detected dependency");
            }
        }
    }

    public function testContentModeRequiresRgAndStructuralModeRequiresAstGrep(): void
    {
        $env = $this->targetEnvelope();
        $contracts = $this->contractsByName($env);

        $this->assertArrayHasKey('text', $contracts);
        $this->assertContains('rg', $contracts['text']['dependencies'], 'text mode runs ripgrep');
        $this->assertNotContains('ast-grep', $contracts['text']['dependencies'], 'text mode does not need ast-grep');

        foreach (['struct', 'symbols', 'class'] as $astMode) {
            $this->assertArrayHasKey($astMode, $contracts);
            $this->assertContains('ast-grep', $contracts[$astMode]['dependencies'], "{$astMode} mode runs ast-grep");
        }
    }

    public function testGitModesRequireGit(): void
    {
        $env = $this->targetEnvelope();
        $contracts = $this->contractsByName($env);
        foreach (['changed-files', 'staged-files', 'tracked', 'diff', 'history', 'changed-text', 'staged-text'] as $gitMode) {
            $this->assertArrayHasKey($gitMode, $contracts);
            $this->assertContains('git', $contracts[$gitMode]['dependencies'], "{$gitMode} mode requires git");
        }
    }

    public function testFilesModeRequiresFdNotRg(): void
    {
        $env = $this->targetEnvelope();
        $contracts = $this->contractsByName($env);
        $this->assertArrayHasKey('files', $contracts);
        $this->assertContains('fd', $contracts['files']['dependencies'], 'files mode runs fd');
        $this->assertNotContains('rg', $contracts['files']['dependencies'], 'files mode must not require rg');
    }

    // ---- json_key_candidates / json_paths / output_schemas -------------

    public function testJsonKeysAreConfirmedHighConfidenceOnly(): void
    {
        $env = $this->targetEnvelope();
        $this->assertIsArray($env['json_keys']);
        foreach ($env['json_keys'] as $key) {
            $this->assertGreaterThanOrEqual(
                80,
                (int) $key['confidence'],
                "json_keys must only carry confirmed (>=80) keys; '{$key['name']}' is lower"
            );
        }
    }

    public function testLowConfidenceKeysMoveToCandidates(): void
    {
        $env = $this->targetEnvelope();
        $this->assertIsArray($env['json_key_candidates']);
        // The real script has plenty of best-effort code-scan keys.
        $this->assertNotEmpty($env['json_key_candidates'], 'expected some json_key_candidates');
        foreach ($env['json_key_candidates'] as $key) {
            $this->assertLessThan(
                80,
                (int) $key['confidence'],
                "json_key_candidates must only carry low-confidence keys; '{$key['name']}' is high"
            );
        }
        // No name should appear in both lists.
        $confirmed = array_map(static fn(array $k): string => (string) $k['name'], $env['json_keys']);
        $candidates = array_map(static fn(array $k): string => (string) $k['name'], $env['json_key_candidates']);
        $this->assertSame([], array_intersect($confirmed, $candidates), 'a key must not be both confirmed and candidate');
    }

    public function testJsonPathsAreJsonPathFormattedWithConfidence(): void
    {
        $env = $this->targetEnvelope();
        $this->assertNotEmpty($env['json_paths'], 'ai-search must expose nested json_paths');
        foreach ($env['json_paths'] as $p) {
            $this->assertArrayHasKey('path', $p);
            $this->assertArrayHasKey('confidence', $p);
            $this->assertStringStartsWith('$.', (string) $p['path'], 'paths must be JSONPath-formatted');
            $this->assertIsInt($p['confidence']);
        }
    }

    public function testJsonPathsIncludeTopLevelNestedAndArrayElementPaths(): void
    {
        $env = $this->targetEnvelope();
        $paths = array_map(static fn(array $p): string => (string) $p['path'], $env['json_paths']);
        // Top-level.
        $this->assertContains('$.schema', $paths);
        $this->assertContains('$.status', $paths);
        // Nested (jq-object).
        $this->assertContains('$.limits.max_results', $paths);
        $this->assertContains('$.meta.truncated', $paths);
        // Array-element.
        $this->assertContains('$.results[].path', $paths);
        // No prose `$.of.*` leakage and every parent is a real key.
        foreach ($paths as $p) {
            $this->assertStringStartsNotWith('$.of.', $p, "prose path '{$p}' must not be emitted");
        }
    }

    public function testJsonPathsConstrainParentsToConfirmedKeys(): void
    {
        $env = $this->targetEnvelope();
        $topKeys = array_map(static fn(array $k): string => (string) $k['name'], $env['json_keys']);
        foreach ($env['json_paths'] as $p) {
            // Extract the parent segment of `$.parent...`.
            if (preg_match('/^\$\.([A-Za-z_][A-Za-z0-9_]*)/', (string) $p['path'], $m)) {
                $this->assertContains(
                    $m[1],
                    $topKeys,
                    "json_path parent '{$m[1]}' must be a confirmed top-level key"
                );
            }
        }
    }

    public function testOutputSchemasIsArrayAndDescribesEnvelopeWhenPresent(): void
    {
        $env = $this->targetEnvelope();
        $this->assertIsArray($env['output_schemas']);
        $this->assertNotEmpty($env['output_schemas'], 'ai-search has a documented envelope schema');
        $schema = $env['output_schemas'][0];
        $this->assertSame('envelope', $schema['name']);
        $this->assertIsArray($schema['keys']);
        $this->assertContains('schema', $schema['keys']);
        $this->assertContains('status', $schema['keys']);
    }

    public function testOutputSchemasEmptyWhenNoEnvelopeDocumented(): void
    {
        $env = $this->fixtureEnvelope(<<<'SH'
#!/usr/bin/env bash
greet() { echo "hi"; }
SH);
        $this->assertSame([], $env['output_schemas'], 'no documented envelope => empty output_schemas');
    }

    public function testOutputSchemasIncludeModeSpecificResultSchemas(): void
    {
        $env = $this->targetEnvelope();
        $byName = [];
        foreach ($env['output_schemas'] as $s) {
            $byName[(string) $s['name']] = $s;
        }

        // file-list-result covers the file-list modes and omits matches/limits.
        $this->assertArrayHasKey('file-list-result', $byName);
        $this->assertContains('changed-files', $byName['file-list-result']['modes']);
        $this->assertContains('staged-files', $byName['file-list-result']['modes']);
        $this->assertContains('results', $byName['file-list-result']['keys']);
        $this->assertNotContains('matches', $byName['file-list-result']['keys']);

        // content-search-result covers text-like modes and carries matches+meta.
        $this->assertArrayHasKey('content-search-result', $byName);
        $this->assertContains('text', $byName['content-search-result']['modes']);
        $this->assertContains('matches', $byName['content-search-result']['keys']);
        $this->assertContains('meta', $byName['content-search-result']['keys']);
    }

    // ---- examples_by_mode ----------------------------------------------

    public function testExamplesByModeLinksExamplesToModes(): void
    {
        $env = $this->targetEnvelope();
        $this->assertIsArray($env['examples_by_mode']);
        $this->assertNotEmpty($env['examples_by_mode']);

        $byMode = [];
        foreach ($env['examples_by_mode'] as $entry) {
            $this->assertArrayHasKey('mode', $entry);
            $this->assertArrayHasKey('examples', $entry);
            $this->assertNotEmpty($entry['examples'], 'examples_by_mode entries must carry >=1 example');
            $byMode[(string) $entry['mode']] = $entry['examples'];
        }
        $this->assertArrayHasKey('diff', $byMode, 'diff has a documented example');
        foreach ($byMode['diff'] as $ex) {
            $this->assertMatchesRegularExpression('/\bai-search\.sh\s+diff\b/', (string) $ex);
        }
    }

    // ---- applies_to_modes (param -> mode mapping) ----------------------

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

    public function testDiffAndHistoryCarryGitAwareDisplayGroup(): void
    {
        $env = $this->targetEnvelope();
        $byName = [];
        foreach ($env['modes'] as $m) {
            $byName[(string) $m['name']] = $m;
        }
        foreach (['diff', 'history'] as $mode) {
            $this->assertArrayHasKey($mode, $byName);
            // Machine family stays content; display_group is the human grouping.
            $this->assertSame('content', $byName[$mode]['family'], "{$mode} machine family must stay content");
            $this->assertSame('git-aware', $byName[$mode]['display_group'] ?? null, "{$mode} display_group must be git-aware");
        }
    }

    public function testModeSpecificOutputSchemasArePresent(): void
    {
        $env = $this->targetEnvelope();
        $byName = [];
        foreach ($env['output_schemas'] as $s) {
            $byName[(string) $s['name']] = $s;
        }
        $expected = [
            'diff-result' => ['path', 'marker', 'new_line', 'text', 'scope'],
            'history-result' => ['commit', 'author', 'date', 'message', 'path'],
            'symbols-result' => ['kind', 'name', 'path', 'start', 'end', 'language'],
            'todo-result' => ['tag', 'line', 'text'],
            'unsafe-patterns-result' => ['rule', 'severity'],
            'doctor-result' => ['available', 'missing', 'warnings', 'root', 'git_available'],
        ];
        foreach ($expected as $schema => $fields) {
            $this->assertArrayHasKey($schema, $byName, "expected mode-specific schema '{$schema}'");
            $this->assertSame('mode-doc', $byName[$schema]['source']);
            foreach ($fields as $field) {
                $this->assertContains(
                    $field,
                    $byName[$schema]['element_fields'],
                    "schema '{$schema}' must document element field '{$field}'"
                );
            }
        }
    }

    // ---- commands[] + risk classification ------------------------------

    /**
     * @param array<string,mixed> $env
     * @return array<string,array<string,mixed>> first command per name
     */
    private function commandsByName(array $env): array
    {
        $out = [];
        foreach ($env['commands'] as $c) {
            $name = (string) $c['name'];
            if (!isset($out[$name])) {
                $out[$name] = $c;
            }
        }
        return $out;
    }

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

    // ---- dependency classification -------------------------------------

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

    // ---- params ---------------------------------------------------------

    /**
     * @return array<string,array<string,mixed>> params keyed by name
     */
    private function paramsByName(array $env): array
    {
        $out = [];
        foreach ($env['params'] as $p) {
            $out[(string) $p['name']] = $p;
        }
        return $out;
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

    // ---- positionals ----------------------------------------------------

    public function testPositionalsIsArray(): void
    {
        $env = $this->targetEnvelope();
        $this->assertIsArray($env['positionals']);
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

    // ---- error handling -------------------------------------------------

    public function testMissingPathYieldsErrorStatusAndNonZeroExit(): void
    {
        $missing = sys_get_temp_dir() . '/nonexistent-' . bin2hex(random_bytes(4)) . '-xyz.sh';
        $this->assertFileDoesNotExist($missing);

        $result = $this->runEngine([$missing], true);
        $this->assertNotSame(0, $result['exit'], 'missing path must exit non-zero');

        $decoded = json_decode($result['stdout'], true);
        $this->assertIsArray($decoded, "error envelope was not valid JSON:\n" . $result['stdout']);
        $this->assertSame('error', $decoded['status']);
        $this->assertNotEmpty($decoded['errors'], 'error envelope must populate errors[]');

        // Full envelope must still be present even on error.
        foreach (['schema', 'status', 'tool', 'functions', 'modes', 'params', 'meta'] as $key) {
            $this->assertArrayHasKey($key, $decoded, "error envelope missing key: {$key}");
        }
    }

    public function testMissingPathHumanModeWritesStderrAndExitsNonZero(): void
    {
        $missing = sys_get_temp_dir() . '/nonexistent-' . bin2hex(random_bytes(4)) . '-xyz.sh';
        $result = $this->runEngine([$missing], false);
        $this->assertNotSame(0, $result['exit'], 'missing path must exit non-zero in human mode');
        $this->assertStringContainsString('ERROR', $result['stderr']);
    }

    // ---- help -----------------------------------------------------------

    public function testHelpExitsZero(): void
    {
        $result = $this->runEngine(['--help'], false);
        $this->assertSame(0, $result['exit'], '--help must exit 0');
        $this->assertStringContainsString('Usage', $result['stdout']);
    }

    // ---- --format=help (compact help summary) ---------------------------

    /**
     * Run `--format=help` against the real target and return the raw result.
     *
     * @return array{stdout:string, stderr:string, exit:int}
     */
    private function helpSummaryResult(): array
    {
        // jsonMode=false so AI_OUTPUT=json is NOT set; --format=help is the
        // explicit format request and must drive the output on its own.
        return $this->runEngine(['--format=help', self::$target], false);
    }

    public function testFormatHelpProducesNonEmptyPlainTextExitZero(): void
    {
        $result = $this->helpSummaryResult();
        $this->assertSame(0, $result['exit'], "--format=help must exit 0:\n" . $result['stderr']);
        $this->assertNotSame('', trim($result['stdout']), '--format=help output must be non-empty');
        // Plain text, not a JSON envelope.
        $this->assertStringNotContainsString('"schema"', $result['stdout']);
        $this->assertStringNotContainsString('ai.sh-introspect/v1', $result['stdout']);
    }

    public function testFormatHelpContainsModesAndParamsHeaders(): void
    {
        $result = $this->helpSummaryResult();
        $this->assertStringContainsString('Modes:', $result['stdout']);
        $this->assertStringContainsString('Params:', $result['stdout']);
    }

    public function testFormatHelpContainsExpectedModeNames(): void
    {
        $result = $this->helpSummaryResult();
        foreach (['text', 'diff', 'tracked'] as $mode) {
            $this->assertStringContainsString($mode, $result['stdout'], "mode '{$mode}' missing from help summary");
        }
    }

    public function testFormatHelpRendersGlobAsRepeatableValueParam(): void
    {
        $result = $this->helpSummaryResult();
        $globLine = $this->lineContaining($result['stdout'], '--glob');
        $this->assertNotNull($globLine, '--glob line missing from help summary');
        $this->assertStringContainsString('value', $globLine, '--glob must render as a value param');
        $this->assertStringContainsString('+', $globLine, '--glob must show the repeatable + marker');
    }

    public function testFormatHelpRendersContextAliasJoined(): void
    {
        $result = $this->helpSummaryResult();
        $this->assertStringContainsString('--context | -C', $result['stdout'], 'alias rendering missing');
    }

    public function testFormatHelpExcludesUnknownOptionHandler(): void
    {
        $result = $this->helpSummaryResult();
        $this->assertStringNotContainsString('--*', $result['stdout'], 'unknown-option handler must not be printed');
    }

    public function testFormatHelpGroupsToolsByCategory(): void
    {
        $result = $this->helpSummaryResult();
        // The broad single "Needs:" line is replaced by a grouped Tools block so
        // users are not misled into thinking every mode needs every tool.
        $this->assertStringNotContainsString('Needs:', $result['stdout'], 'broad Needs line must be gone');
        $this->assertStringContainsString('Tools:', $result['stdout'], 'grouped Tools block must be present');

        $primaryLine = $this->lineContaining($result['stdout'], 'primary:');
        $this->assertNotNull($primaryLine, 'primary tools line missing from Tools block');
        $this->assertStringContainsString('git', $primaryLine, 'primary tools must list git');
        $this->assertStringContainsString('rg', $primaryLine, 'primary tools must list rg');
        $this->assertStringContainsString('ast-grep', $primaryLine, 'primary tools must list ast-grep');

        // Base utilities are split out, not mixed with primary tools.
        $baseLine = $this->lineContaining($result['stdout'], 'base utilities:');
        $this->assertNotNull($baseLine, 'base utilities line missing from Tools block');
        $this->assertStringNotContainsString('rg', $baseLine, 'rg is a primary tool, not a base utility');

        $this->assertStringContainsString(
            'mode-specific tools: see mode contract via --introspect',
            $result['stdout'],
            'Tools block must point at the per-mode contract'
        );
    }

    public function testFormatHelpContainsFullContractHint(): void
    {
        $result = $this->helpSummaryResult();
        $this->assertStringContainsString('Full contract', $result['stdout'], 'full-contract hint missing');
        $this->assertStringContainsString(
            'AI_OUTPUT=json php tools/ai/sh-introspect.php',
            $result['stdout'],
            'full-contract hint must point at the JSON command'
        );
    }

    public function testFormatHelpDoesNotExecuteTargetOrEmitSearchResults(): void
    {
        $result = $this->helpSummaryResult();
        // Static parser only: there must be no runtime search-result envelope
        // keys (matches/results) and no JSON status line leaking through.
        $this->assertStringNotContainsString('"matches"', $result['stdout']);
        $this->assertStringNotContainsString('"results"', $result['stdout']);
        $this->assertStringNotContainsString('"status"', $result['stdout']);
        // No error noise on stderr for a clean run.
        $this->assertSame('', trim($result['stderr']), 'clean --format=help run must not write stderr');
    }

    public function testFormatHelpUnknownFormatValueErrors(): void
    {
        $result = $this->runEngine(['--format=bogus', self::$target], false);
        $this->assertNotSame(0, $result['exit'], 'unknown --format value must exit non-zero');
        $this->assertStringContainsString('ERROR', $result['stderr'], 'unknown --format value must report on stderr');
    }

    // ---- P2: --all repo-wide index --------------------------------------

    /**
     * Run the engine with --all and decode the index envelope.
     *
     * @return array<string,mixed>
     */
    private function indexEnvelope(): array
    {
        $result = $this->runEngine(['--all'], true);
        $this->assertSame(0, $result['exit'], "engine --all exited non-zero:\n" . $result['stderr']);
        $decoded = json_decode($result['stdout'], true);
        $this->assertIsArray($decoded, "engine --all did not emit valid JSON:\n" . $result['stdout']);
        return $decoded;
    }

    public function testAllIndexHasExpectedSchemaAndShape(): void
    {
        $env = $this->indexEnvelope();
        $this->assertSame('ai.sh-introspect-index/v1', $env['schema']);
        $this->assertSame('ok', $env['status']);
        $this->assertSame('sh-introspect', $env['tool']);
        $this->assertArrayHasKey('files', $env);
        $this->assertIsArray($env['files']);
        $this->assertGreaterThan(0, $env['count'], 'index must discover at least one script');
        $this->assertSame(count($env['files']), $env['count']);
        $this->assertFalse($env['meta']['target_executed'], '--all must never execute targets');
    }

    public function testAllIndexCoversScriptsAndLibraries(): void
    {
        $env = $this->indexEnvelope();
        $paths = array_map(static fn(array $f): string => (string) $f['path'], $env['files']);
        // A top-level script and a lib module must both be present.
        $hasTopLevel = false;
        $hasLib = false;
        foreach ($paths as $p) {
            if (preg_match('#scripts/ai/[^/]+\.sh$#', $p)) {
                $hasTopLevel = true;
            }
            if (str_contains($p, 'scripts/ai/lib/')) {
                $hasLib = true;
            }
        }
        $this->assertTrue($hasTopLevel, 'index must include scripts/ai/*.sh');
        $this->assertTrue($hasLib, 'index must include scripts/ai/lib/*.sh');

        // ai-search.sh must be in the index with a rich contract.
        $byPath = [];
        foreach ($env['files'] as $f) {
            $byPath[(string) $f['path']] = $f;
        }
        $this->assertArrayHasKey('scripts/ai/ai-search.sh', $byPath);
        $this->assertGreaterThan(10, (int) $byPath['scripts/ai/ai-search.sh']['modes']);
    }

    public function testAllIndexEntriesHaveSummaryFields(): void
    {
        $env = $this->indexEnvelope();
        foreach ($env['files'] as $f) {
            foreach (['path', 'status', 'kind', 'modes', 'params', 'max_risk', 'confidence'] as $key) {
                $this->assertArrayHasKey($key, $f, "index entry missing '{$key}'");
            }
            $this->assertFalse($f['target_executed'] ?? true, 'index entry must mark target not executed');
        }
    }

    public function testFormatJsonSpaceSeparatedFormIsSupported(): void
    {
        // `--all --format json` (space-separated) must behave like --format=json.
        $cmdParts = [self::$phpBin, self::$tool, '--all', '--format', 'json'];
        $cmd = implode(' ', array_map('escapeshellarg', $cmdParts));
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $descriptors, $pipes, self::$repoRoot, ['PATH' => (string) getenv('PATH')]);
        $this->assertIsResource($proc);
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        $decoded = json_decode($stdout, true);
        $this->assertIsArray($decoded, "space-separated --format json must emit JSON:\n" . $stdout);
        $this->assertSame('ai.sh-introspect-index/v1', $decoded['schema']);
    }

    // ---- P2: every executable script supports --introspect --------------

    public function testEveryAiScriptSupportsIntrospect(): void
    {
        $scripts = glob(self::$repoRoot . '/scripts/ai/*.sh') ?: [];
        $this->assertNotEmpty($scripts, 'expected scripts under scripts/ai');

        $failures = [];
        foreach ($scripts as $script) {
            $cmd = implode(' ', array_map('escapeshellarg', ['bash', $script, '--introspect']));
            $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $env = [
                'HOME'            => sys_get_temp_dir(),
                'XDG_CONFIG_HOME' => sys_get_temp_dir(),
                'PATH'            => (string) getenv('PATH'),
                'NO_COLOR'        => '1',
            ];
            $proc = proc_open($cmd, $descriptors, $pipes, self::$repoRoot, $env);
            if (!is_resource($proc)) {
                $failures[] = basename($script) . ': proc_open failed';
                continue;
            }
            fclose($pipes[0]);
            $stdout = (string) stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($proc);

            $decoded = json_decode($stdout, true);
            if ($exit !== 0 || !is_array($decoded) || ($decoded['schema'] ?? '') !== 'ai.sh-introspect/v1') {
                $failures[] = basename($script) . ': exit=' . $exit
                    . ' schema=' . (is_array($decoded) ? ($decoded['schema'] ?? 'none') : 'invalid-json');
                continue;
            }
            // Static parse only: the target must never be executed.
            if (($decoded['meta']['target_executed'] ?? true) !== false) {
                $failures[] = basename($script) . ': target_executed must be false';
            }
        }

        $this->assertSame([], $failures, "scripts without a working --introspect surface:\n" . implode("\n", $failures));
    }

    // ---- P3: golden JSON snapshot + help/JSON drift ---------------------

    /**
     * Canonicalise the target envelope for golden comparison: stable key order,
     * volatile `line` fields zeroed, and the repo root replaced by `<REPO>` so
     * the snapshot is machine-independent and resilient to line shifts.
     */
    private function canonicalContractJson(): string
    {
        $env = $this->targetEnvelope();
        $root = self::$repoRoot;
        $normalize = function (&$node) use (&$normalize, $root): void {
            if (is_array($node)) {
                foreach ($node as $k => &$v) {
                    if ($k === 'line' && (is_int($v) || is_numeric($v))) {
                        $v = 0;
                    } else {
                        $normalize($v);
                    }
                }
                unset($v);
            } elseif (is_string($node)) {
                $node = str_replace($root, '<REPO>', $node);
            }
        };
        $normalize($env);
        $this->ksortRecursive($env);
        return (string) json_encode($env, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /** Recursively sort associative arrays by key (mirrors `jq -S`). */
    private function ksortRecursive(array &$arr): void
    {
        foreach ($arr as &$v) {
            if (is_array($v)) {
                $this->ksortRecursive($v);
            }
        }
        unset($v);
        // Only sort associative arrays (preserve list order).
        if ($arr !== [] && array_keys($arr) !== range(0, count($arr) - 1)) {
            ksort($arr, SORT_STRING);
        }
    }

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

    // ---- P3: parser edge-case fixtures ----------------------------------

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

    // ---- P4: dangerous-command classification + --strict-risk -----------

    /**
     * @return array<string,mixed>
     */
    private function dangerFixtureEnvelope(): array
    {
        $result = $this->runEngine([self::$repoRoot . '/tests/php/fixtures/danger-strict-risk.sh'], true);
        $this->assertSame(0, $result['exit'], "danger fixture introspect failed:\n" . $result['stderr']);
        $decoded = json_decode($result['stdout'], true);
        $this->assertIsArray($decoded);
        return $decoded;
    }

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

    /**
     * Return the first stdout line containing the needle, or null.
     */
    private function lineContaining(string $stdout, string $needle): ?string
    {
        foreach (preg_split('/\R/', $stdout) ?: [] as $line) {
            if (str_contains($line, $needle)) {
                return $line;
            }
        }
        return null;
    }
    // ---- CLI format contract --------------------------------------------

    /**
     * Decode a JSON result from runEngine().
     *
     * @param array{stdout:string, stderr:string, exit:int} $result
     * @return array<string,mixed>
     */
    private function decodeJsonResult(array $result): array
    {
        $decoded = json_decode($result['stdout'], true);
        $this->assertIsArray($decoded, "output was not valid JSON:\n" . $result['stdout']);

        return $decoded;
    }

    public function testDefaultHumanModeProducesTextAndNotJson(): void
    {
        $result = $this->runEngine([self::$target], false);

        $this->assertSame(0, $result['exit'], "default human mode must exit 0:\n" . $result['stderr']);
        $this->assertNotSame('', trim($result['stdout']), 'default human output must be non-empty');

        $decoded = json_decode($result['stdout'], true);
        $this->assertNull($decoded, 'default mode must not emit a JSON envelope');
        $this->assertNotSame(JSON_ERROR_NONE, json_last_error(), 'default mode must be plain text, not valid JSON');

        $this->assertStringNotContainsString('"schema"', $result['stdout']);
        $this->assertStringNotContainsString('ai.sh-introspect/v1', $result['stdout']);
    }

    public function testFormatJsonEqualsAiOutputJsonForSingleFile(): void
    {
        $envResult = $this->runEngine([self::$target], true);
        $flagResult = $this->runEngine(['--format=json', self::$target], false);

        $this->assertSame(0, $envResult['exit'], "AI_OUTPUT=json run failed:\n" . $envResult['stderr']);
        $this->assertSame(0, $flagResult['exit'], "--format=json run failed:\n" . $flagResult['stderr']);

        $envJson = $this->decodeJsonResult($envResult);
        $flagJson = $this->decodeJsonResult($flagResult);

        $this->assertSame('ai.sh-introspect/v1', $envJson['schema']);
        $this->assertSame('ai.sh-introspect/v1', $flagJson['schema']);
        $this->assertSame('ok', $envJson['status']);
        $this->assertSame('ok', $flagJson['status']);

        // The explicit flag and AI_OUTPUT=json must expose the same contract surface.
        foreach ([
            'schema',
            'status',
            'tool',
            'kind',
            'file',
            'functions',
            'modes',
            'mode_contracts',
            'params',
            'json_keys',
            'json_paths',
            'output_schemas',
            'dependencies',
            'commands',
            'risk_summary',
            'meta',
        ] as $key) {
            $this->assertSame($envJson[$key], $flagJson[$key], "JSON mismatch for key: {$key}");
        }
    }

    public function testFormatSpaceSeparatedJsonForSingleFile(): void
    {
        $result = $this->runEngine(['--format', 'json', self::$target], false);

        $this->assertSame(0, $result['exit'], "--format json must exit 0:\n" . $result['stderr']);

        $decoded = $this->decodeJsonResult($result);
        $this->assertSame('ai.sh-introspect/v1', $decoded['schema']);
        $this->assertSame('ok', $decoded['status']);
        $this->assertSame('sh-introspect', $decoded['tool']);
        $this->assertStringEndsWith('ai-search.sh', (string) $decoded['file']);
        $this->assertFalse($decoded['meta']['target_executed']);
    }

    public function testFormatSpaceSeparatedHelpForSingleFile(): void
    {
        $result = $this->runEngine(['--format', 'help', self::$target], false);

        $this->assertSame(0, $result['exit'], "--format help must exit 0:\n" . $result['stderr']);
        $this->assertNotSame('', trim($result['stdout']), '--format help output must be non-empty');

        $decoded = json_decode($result['stdout'], true);
        $this->assertNull($decoded, '--format help must emit plain text, not JSON');

        $this->assertStringContainsString('Modes:', $result['stdout']);
        $this->assertStringContainsString('Params:', $result['stdout']);
        $this->assertStringNotContainsString('"schema"', $result['stdout']);
        $this->assertStringNotContainsString('ai.sh-introspect/v1', $result['stdout']);
    }

    public function testBareFormatWithoutValueYieldsError(): void
    {
        $result = $this->runEngine(['--format'], true);

        $this->assertNotSame(0, $result['exit'], 'bare --format must exit non-zero');

        $decoded = $this->decodeJsonResult($result);
        $this->assertSame('ai.sh-introspect/v1', $decoded['schema']);
        $this->assertSame('error', $decoded['status']);
        $this->assertSame('sh-introspect', $decoded['tool']);
        $this->assertNotEmpty($decoded['errors'], 'bare --format must populate errors[]');
        $this->assertSame(0, $decoded['meta']['confidence']);
        $this->assertFalse($decoded['meta']['target_executed']);

        $message = implode("\n", array_map('strval', $decoded['errors']));
        $this->assertMatchesRegularExpression(
            '/--format|format|input file/i',
            $message,
            'error should clearly mention --format or missing input'
        );
    }

    public function testUnknownFlagDoesNotBreakPathResolution(): void
    {
        $baseline = $this->runEngine([self::$target], true);
        $withUnknownFlag = $this->runEngine(['--future-compatible-flag', self::$target], true);

        $this->assertSame(0, $baseline['exit'], "baseline JSON run failed:\n" . $baseline['stderr']);
        $this->assertSame(0, $withUnknownFlag['exit'], "unknown flag run failed:\n" . $withUnknownFlag['stderr']);

        $baselineJson = $this->decodeJsonResult($baseline);
        $unknownJson = $this->decodeJsonResult($withUnknownFlag);

        $this->assertSame('ok', $unknownJson['status']);
        $this->assertSame($baselineJson['file'], $unknownJson['file']);
        $this->assertSame($baselineJson['path'], $unknownJson['path']);
        $this->assertStringEndsWith('ai-search.sh', (string) $unknownJson['file']);

        // Unknown forward-compatible flags must be ignored, not classified as params.
        $paramNames = array_map(static fn(array $p): string => (string) $p['name'], $unknownJson['params']);
        $this->assertNotContains('--future-compatible-flag', $paramNames);
    }
}
