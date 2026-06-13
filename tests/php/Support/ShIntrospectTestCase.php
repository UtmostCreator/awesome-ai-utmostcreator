<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * Shared base for the split sh-introspect contract suites.
 *
 * Hosts the out-of-process engine runner and the decode helpers that the
 * focused ShIntrospect\* test classes share. Assertions are intentionally NOT
 * changed from the original monolithic ShIntrospectTest; only helper visibility
 * was widened (private -> protected) and the expensive real-target reads are
 * memoised per process so the split classes do not each re-run the same engine
 * invocation.
 *
 * These run the engine via proc_open in JSON mode and assert against the REAL
 * scripts/ai/ai-search.sh. Line numbers are intentionally NOT asserted.
 */
abstract class ShIntrospectTestCase extends TestCase
{
    protected static string $repoRoot;
    protected static string $phpBin;
    protected static string $tool;
    protected static string $target;

    /** @var array<string,mixed>|null */
    private static ?array $targetEnvelopeCache = null;
    /** @var array<string,mixed>|null */
    private static ?array $indexEnvelopeCache = null;
    /** @var array{stdout:string, stderr:string, exit:int}|null */
    private static ?array $helpSummaryCache = null;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 3));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/Support/');
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
    protected function runEngine(array $args, bool $jsonMode): array
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
     * Memoised per process: the engine output for the real target is
     * deterministic, so the split classes share one invocation.
     *
     * @return array<string,mixed>
     */
    protected function targetEnvelope(): array
    {
        if (self::$targetEnvelopeCache !== null) {
            return self::$targetEnvelopeCache;
        }
        $result = $this->runEngine([self::$target], true);
        $this->assertSame(0, $result['exit'], "engine exited non-zero:\n" . $result['stderr']);
        $decoded = json_decode($result['stdout'], true);
        $this->assertIsArray($decoded, "engine did not emit valid JSON:\n" . $result['stdout']);
        self::$targetEnvelopeCache = $decoded;
        return $decoded;
    }

    /**
     * Write a throwaway fixture script and return its decoded envelope. The
     * fixture is removed before assertions run.
     *
     * @return array<string,mixed>
     */
    protected function fixtureEnvelope(string $contents): array
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
    protected function dangerFixture(): string
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

    /**
     * @param array<string,mixed> $env
     * @return array<string,array<string,mixed>> contracts keyed by mode name
     */
    protected function contractsByName(array $env): array
    {
        $out = [];
        foreach ($env['mode_contracts'] as $c) {
            $out[(string) $c['name']] = $c;
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $env
     * @return array<string,array<string,mixed>> first command per name
     */
    protected function commandsByName(array $env): array
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

    /**
     * @param array<string,mixed> $env
     * @return array<string,array<string,mixed>> params keyed by name
     */
    protected function paramsByName(array $env): array
    {
        $out = [];
        foreach ($env['params'] as $p) {
            $out[(string) $p['name']] = $p;
        }
        return $out;
    }

    /**
     * Run `--format=help` against the real target and return the raw result.
     *
     * Memoised per process.
     *
     * @return array{stdout:string, stderr:string, exit:int}
     */
    protected function helpSummaryResult(): array
    {
        if (self::$helpSummaryCache !== null) {
            return self::$helpSummaryCache;
        }
        // jsonMode=false so AI_OUTPUT=json is NOT set; --format=help is the
        // explicit format request and must drive the output on its own.
        self::$helpSummaryCache = $this->runEngine(['--format=help', self::$target], false);
        return self::$helpSummaryCache;
    }

    /**
     * Run the engine with --all and decode the index envelope.
     *
     * Memoised per process.
     *
     * @return array<string,mixed>
     */
    protected function indexEnvelope(): array
    {
        if (self::$indexEnvelopeCache !== null) {
            return self::$indexEnvelopeCache;
        }
        $result = $this->runEngine(['--all'], true);
        $this->assertSame(0, $result['exit'], "engine --all exited non-zero:\n" . $result['stderr']);
        $decoded = json_decode($result['stdout'], true);
        $this->assertIsArray($decoded, "engine --all did not emit valid JSON:\n" . $result['stdout']);
        self::$indexEnvelopeCache = $decoded;
        return $decoded;
    }

    /**
     * Canonicalise the target envelope for golden comparison: stable key order,
     * volatile `line` fields zeroed, and the repo root replaced by `<REPO>` so
     * the snapshot is machine-independent and resilient to line shifts.
     */
    protected function canonicalContractJson(): string
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
    protected function ksortRecursive(array &$arr): void
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

    /**
     * @return array<string,mixed>
     */
    protected function dangerFixtureEnvelope(): array
    {
        $result = $this->runEngine([self::$repoRoot . '/tests/php/fixtures/danger-strict-risk.sh'], true);
        $this->assertSame(0, $result['exit'], "danger fixture introspect failed:\n" . $result['stderr']);
        $decoded = json_decode($result['stdout'], true);
        $this->assertIsArray($decoded);
        return $decoded;
    }

    /**
     * Return the first stdout line containing the needle, or null.
     */
    protected function lineContaining(string $stdout, string $needle): ?string
    {
        foreach (preg_split('/\R/', $stdout) ?: [] as $line) {
            if (str_contains($line, $needle)) {
                return $line;
            }
        }
        return null;
    }

    /**
     * Decode a JSON result from runEngine().
     *
     * @param array{stdout:string, stderr:string, exit:int} $result
     * @return array<string,mixed>
     */
    protected function decodeJsonResult(array $result): array
    {
        $decoded = json_decode($result['stdout'], true);
        $this->assertIsArray($decoded, "output was not valid JSON:\n" . $result['stdout']);

        return $decoded;
    }
}
