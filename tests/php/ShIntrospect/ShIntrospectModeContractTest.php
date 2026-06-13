<?php

declare(strict_types=1);

namespace Tests\ShIntrospect;

use Tests\Support\ShIntrospectTestCase;

/**
 * Mode discovery, mode_contracts, and per-mode dependency contract.
 */
class ShIntrospectModeContractTest extends ShIntrospectTestCase
{
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
}
