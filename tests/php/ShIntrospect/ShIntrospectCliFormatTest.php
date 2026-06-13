<?php

declare(strict_types=1);

namespace Tests\ShIntrospect;

use Tests\Support\ShIntrospectTestCase;

/**
 * CLI format argument parsing and user-facing invocation/error handling.
 */
class ShIntrospectCliFormatTest extends ShIntrospectTestCase
{
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

    // ---- CLI format contract --------------------------------------------

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
        $this->assertStringContainsString('Flags:', $result['stdout']);
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

    // ---- --output redirection -------------------------------------------

    public function testOutputFlagWritesReportToFileAndKeepsStdoutClean(): void
    {
        $out = sys_get_temp_dir() . '/shintro-out-' . bin2hex(random_bytes(4)) . '.txt';
        $this->assertFileDoesNotExist($out);

        try {
            $result = $this->runEngine([self::$target, '--output', $out], false);

            $this->assertSame(0, $result['exit'], "--output run must exit 0:\n" . $result['stderr']);
            $this->assertSame('', trim($result['stdout']), 'report must not also go to STDOUT');
            $this->assertStringContainsString($out, $result['stderr'], 'stderr must confirm the written path');

            $this->assertFileExists($out);
            $written = (string) file_get_contents($out);
            $this->assertStringContainsString('Modes', $written, 'file must contain the text report');
        } finally {
            @unlink($out);
        }
    }

    public function testOutputEqualsFormCreatesParentDirectories(): void
    {
        $dir = sys_get_temp_dir() . '/shintro-nested-' . bin2hex(random_bytes(4));
        $out = $dir . '/deep/report.json';
        $this->assertDirectoryDoesNotExist($dir);

        try {
            $result = $this->runEngine(['--format=json', self::$target, '--output=' . $out], false);

            $this->assertSame(0, $result['exit'], "--output= run must exit 0:\n" . $result['stderr']);
            $this->assertFileExists($out);

            $decoded = json_decode((string) file_get_contents($out), true);
            $this->assertIsArray($decoded, 'written file must be the JSON envelope');
            $this->assertSame('ok', $decoded['status']);
            $this->assertSame('ai.sh-introspect/v1', $decoded['schema']);
        } finally {
            if (is_file($out)) {
                @unlink($out);
            }
            @rmdir($dir . '/deep');
            @rmdir($dir);
        }
    }

    public function testAllModeHonoursOutputFlag(): void
    {
        $out = sys_get_temp_dir() . '/shintro-all-' . bin2hex(random_bytes(4)) . '.json';

        try {
            $result = $this->runEngine(['--all', '--format', 'json', '--output', $out], false);

            $this->assertSame(0, $result['exit'], "--all --output run must exit 0:\n" . $result['stderr']);
            $this->assertSame('', trim($result['stdout']), 'index report must not also go to STDOUT');
            $this->assertFileExists($out);

            $decoded = json_decode((string) file_get_contents($out), true);
            $this->assertIsArray($decoded, 'written file must be the index envelope');
            $this->assertSame('ai.sh-introspect-index/v1', $decoded['schema']);
            $this->assertGreaterThan(0, (int) $decoded['count']);
        } finally {
            @unlink($out);
        }
    }

    public function testBareOutputWithoutValueYieldsError(): void
    {
        $result = $this->runEngine([self::$target, '--output'], true);

        $this->assertNotSame(0, $result['exit'], 'bare --output must exit non-zero');

        $decoded = $this->decodeJsonResult($result);
        $this->assertSame('error', $decoded['status']);
        $this->assertNotEmpty($decoded['errors'], 'bare --output must populate errors[]');
        $message = implode("\n", array_map('strval', $decoded['errors']));
        $this->assertMatchesRegularExpression('/--output/i', $message);
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
