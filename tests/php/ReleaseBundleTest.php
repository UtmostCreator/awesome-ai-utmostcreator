<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Covers the release-bundle exporter integrity layer:
 *  - SHA256SUMS generation covering every bundled file,
 *  - --verify happy path and single-byte tamper detection,
 *  - aggregate per-bundle line budget enforcement.
 *
 * Exports target the real manifest-driven dist/ root (the exporter reads
 * release.export_root from the manifest). Each test cleans up the version
 * directory it created in tearDown so dist/ stays an ephemeral build surface.
 */
final class ReleaseBundleTest extends TestCase
{
    private static string $repoRoot;
    private static string $version;
    private static string $exportRoot;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;

        $manifest = json_decode(
            (string) file_get_contents($root . '/packages/ai-universal-rules/manifest.json'),
            true
        );
        self::assertIsArray($manifest);
        self::$version = (string) $manifest['version'];
        self::$exportRoot = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, (string) $manifest['release']['export_root']);
    }

    protected function tearDown(): void
    {
        // Remove only the version dir this run produces; never touch unrelated dist content.
        $versionDir = self::$exportRoot . DIRECTORY_SEPARATOR . self::$version;
        $this->removeTree($versionDir);
    }

    /** @return array{stdout:string,stderr:string,exit:int} */
    private function runExporter(string $args): array
    {
        $command = escapeshellarg((string) PHP_BINARY) . ' tools/ai/export-ai-universal-rules.php ' . $args;
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes, self::$repoRoot);
        $this->assertIsResource($process, "proc_open failed for: $command");
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
    }

    private function bundleDir(string $profile): string
    {
        return self::$exportRoot . DIRECTORY_SEPARATOR . self::$version . DIRECTORY_SEPARATOR . $profile;
    }

    /** @return list<string> sorted bundle-relative paths */
    private function collectBundleFiles(string $bundleDir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($bundleDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        $prefix = strlen(rtrim(str_replace('\\', '/', $bundleDir), '/')) + 1;
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $files[] = substr(str_replace('\\', '/', $item->getPathname()), $prefix);
            }
        }
        sort($files);

        return $files;
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            @unlink($path);
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($path);
    }

    public function testExportProducesShasumsCoveringEveryBundledFile(): void
    {
        $result = $this->runExporter('--profile=dual-runtime-starter');
        $this->assertSame(0, $result['exit'], "export failed:\n" . $result['stderr']);

        $bundleDir = $this->bundleDir('dual-runtime-starter');
        $sumsPath = $bundleDir . DIRECTORY_SEPARATOR . 'SHA256SUMS';
        $this->assertFileExists($sumsPath, 'exporter must write SHA256SUMS');

        $listed = [];
        foreach (preg_split('/\r?\n/', (string) file_get_contents($sumsPath)) ?: [] as $line) {
            if ($line === '') {
                continue;
            }
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{64}  .+$/',
                $line,
                'each SHA256SUMS line must be 64-hex + two spaces + relative path'
            );
            [$hash, $rel] = [substr($line, 0, 64), substr($line, 66)];
            $listed[$rel] = $hash;
        }

        $bundleFiles = array_values(array_filter(
            $this->collectBundleFiles($bundleDir),
            static fn(string $rel): bool => $rel !== 'SHA256SUMS'
        ));

        // Count match: SHA256SUMS lists exactly every bundled file except itself.
        $this->assertSame(
            count($bundleFiles),
            count($listed),
            'SHA256SUMS must cover every bundled file (excluding itself)'
        );
        $this->assertContains('RELEASE-MANIFEST.json', array_keys($listed), 'RELEASE-MANIFEST.json must be checksummed');

        // Format/content match: each listed hash equals the recomputed file hash.
        foreach ($listed as $rel => $hash) {
            $abs = $bundleDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $this->assertFileExists($abs, "listed file must exist: $rel");
            $this->assertSame(hash_file('sha256', $abs), $hash, "checksum must match recomputed hash for: $rel");
        }
    }

    public function testVerifyExitsZeroOnFreshExport(): void
    {
        $export = $this->runExporter('--profile=dual-runtime-starter');
        $this->assertSame(0, $export['exit'], "export failed:\n" . $export['stderr']);

        $verify = $this->runExporter('--profile=dual-runtime-starter --verify');
        $this->assertSame(0, $verify['exit'], "verify should pass on a fresh export:\n" . $verify['stderr']);
        $this->assertStringContainsString('checksums verified', $verify['stdout']);
    }

    public function testVerifyDetectsSingleByteMutation(): void
    {
        $export = $this->runExporter('--profile=dual-runtime-starter');
        $this->assertSame(0, $export['exit'], "export failed:\n" . $export['stderr']);

        $bundleDir = $this->bundleDir('dual-runtime-starter');
        $target = $bundleDir . DIRECTORY_SEPARATOR . 'RELEASE-MANIFEST.json';
        $this->assertFileExists($target);

        // Flip a single byte (append one character) to simulate tampering.
        file_put_contents($target, (string) file_get_contents($target) . 'X');

        $verify = $this->runExporter('--profile=dual-runtime-starter --verify');
        $this->assertNotSame(0, $verify['exit'], 'verify must fail after a single-byte mutation');
        $this->assertStringContainsString('mismatch', $verify['stderr']);
    }

    public function testBudgetCheckFailsWhenDeclaredMaxIsVeryLow(): void
    {
        $export = $this->runExporter('--profile=dual-runtime-starter');
        $this->assertSame(0, $export['exit'], "export failed:\n" . $export['stderr']);

        // Temporarily lower the manifest budget to 1 line, run the read-only
        // budget check, then restore the manifest byte-for-byte.
        $manifestPath = self::$repoRoot . '/packages/ai-universal-rules/manifest.json';
        $original = (string) file_get_contents($manifestPath);
        $manifest = json_decode($original, true);
        $this->assertIsArray($manifest);

        $manifest['release']['max_bundle_lines'] = 1;
        file_put_contents(
            $manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );

        try {
            $low = $this->runExporter('--profile=dual-runtime-starter --budget-check');
            $this->assertNotSame(0, $low['exit'], 'budget check must fail when the declared max is far below the real count');
            $this->assertStringContainsString('exceeds release.max_bundle_lines', $low['stderr']);
        } finally {
            file_put_contents($manifestPath, $original);
        }

        // At the real configured value, the budget check passes.
        $ok = $this->runExporter('--profile=dual-runtime-starter --budget-check');
        $this->assertSame(0, $ok['exit'], "budget check should pass at the configured value:\n" . $ok['stderr']);
        $this->assertStringContainsString('within budget', $ok['stdout']);
    }
}
