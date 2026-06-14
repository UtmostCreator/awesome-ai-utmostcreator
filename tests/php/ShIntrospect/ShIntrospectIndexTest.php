<?php

declare(strict_types=1);

namespace Tests\ShIntrospect;

use Tests\Support\ShIntrospectTestCase;

/**
 * Repo-wide `--all` index contract and per-script --introspect coverage.
 */
class ShIntrospectIndexTest extends ShIntrospectTestCase
{
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
            if (str_contains($p, 'scripts/ai/internal/lib/')) {
                $hasLib = true;
            }
        }
        $this->assertTrue($hasTopLevel, 'index must include scripts/ai/*.sh');
        $this->assertTrue($hasLib, 'index must include scripts/ai/internal/lib/*.sh');

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
}
