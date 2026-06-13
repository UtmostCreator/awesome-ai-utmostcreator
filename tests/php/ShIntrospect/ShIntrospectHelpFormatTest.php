<?php

declare(strict_types=1);

namespace Tests\ShIntrospect;

use Tests\Support\ShIntrospectTestCase;

/**
 * `--help` and `--format=help` plain-text rendering contract.
 */
class ShIntrospectHelpFormatTest extends ShIntrospectTestCase
{
    public function testHelpExitsZero(): void
    {
        $result = $this->runEngine(['--help'], false);
        $this->assertSame(0, $result['exit'], '--help must exit 0');
        $this->assertStringContainsString('Usage', $result['stdout']);
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
        $this->assertStringContainsString('Flags:', $result['stdout']);
    }

    public function testFormatHelpContainsExpectedModeNames(): void
    {
        $result = $this->helpSummaryResult();
        foreach (['text', 'diff', 'tracked'] as $mode) {
            $this->assertStringContainsString($mode, $result['stdout'], "mode '{$mode}' missing from help summary");
        }
    }

    public function testFormatHelpRendersFriendlyGroupHeadings(): void
    {
        $result = $this->helpSummaryResult();
        $this->assertStringContainsString(
            'Search repository content',
            $result['stdout'],
            'friendly group heading must replace the raw display_group key in the descriptive layout'
        );
    }

    public function testFormatHelpRendersSeeAlsoBlock(): void
    {
        $result = $this->helpSummaryResult();
        $this->assertStringContainsString(
            'See also:',
            $result['stdout'],
            'help summary must include a "See also:" pointer block'
        );
    }

    public function testFormatHelpRendersGlobAsRepeatableValueParam(): void
    {
        $result = $this->helpSummaryResult();
        $globLine = $this->lineContaining($result['stdout'], '--glob');
        $this->assertNotNull($globLine, '--glob line missing from help summary');
        $this->assertStringContainsString('value', $globLine, '--glob must render as a value param');
        $this->assertStringContainsString('+', $globLine, '--glob must show the repeatable + marker');
        // Value flags render their contract value_hint next to the name so the
        // flag is self-documenting (GNU/argparse convention).
        $this->assertStringContainsString('--glob PATTERN', $globLine, '--glob must show its PATTERN value hint');
    }

    public function testFormatHelpRendersContextAliasJoined(): void
    {
        $result = $this->helpSummaryResult();
        // The value hint attaches to the primary name; the alias follows.
        $this->assertStringContainsString('--context N | -C', $result['stdout'], 'alias + value-hint rendering missing');
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
}
