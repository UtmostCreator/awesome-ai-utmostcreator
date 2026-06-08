<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Catalog drift validator: the committed INSTALL-CATALOG must match a fresh render of the
 * installer pack registry. This guards against pack edits that leave the catalog stale (which
 * the install-time-only regeneration previously allowed to slip through CI).
 */
final class CatalogDriftValidatorTest extends TestCase
{
    private static string $repoRoot;
    private static string $validator;
    private static string $catalogPath;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;
        self::$validator = $root . '/tools/ai/validate-catalog-drift.php';
        self::$catalogPath = $root . '/packages/ai-universal-rules/docs/INSTALL-CATALOG.md';
    }

    /** @return array{stdout:string,stderr:string,exit:int} */
    private function runValidator(): array
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $cmd = escapeshellarg((string) PHP_BINARY) . ' ' . escapeshellarg(self::$validator) . ' --root=' . escapeshellarg(self::$repoRoot);
        $process = proc_open($cmd, $descriptors, $pipes, self::$repoRoot);
        $this->assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
    }

    public function testCommittedCatalogIsUpToDate(): void
    {
        $result = $this->runValidator();
        $this->assertSame(0, $result['exit'], "catalog is stale; run: php tools/ai/ai.php install-docs --write\n" . $result['stderr']);
    }

    public function testValidatorDetectsCatalogDrift(): void
    {
        $original = (string) file_get_contents(self::$catalogPath);
        $mutated = preg_replace('/`target-tools-pack` \(\d+ items\)/', '`target-tools-pack` (999 items)', $original, 1);
        $this->assertIsString($mutated);
        $this->assertNotSame($original, $mutated, 'precondition: catalog must contain the target-tools-pack line');

        file_put_contents(self::$catalogPath, $mutated);
        try {
            $result = $this->runValidator();
            $this->assertSame(1, $result['exit'], 'validator must detect a stale catalog');
            $this->assertStringContainsString('drift detected', $result['stderr']);
        } finally {
            file_put_contents(self::$catalogPath, $original);
        }

        // Confirm restoration leaves the catalog clean again.
        $this->assertSame(0, $this->runValidator()['exit']);
    }

    public function testValidatorUsesInstalledPackageCatalogWhenSourcePackageIsAbsent(): void
    {
        require_once self::$repoRoot . '/tools/ai/install/core.php';

        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'catalog_drift_installed_' . uniqid('', true);
        mkdir($target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'package', 0700, true);

        try {
            \aiInstallerWriteCatalogDocs($target);

            $this->assertFileDoesNotExist($target . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'ai-universal-rules' . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'INSTALL-CATALOG.md');
            $this->assertFileExists($target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'package' . DIRECTORY_SEPARATOR . 'INSTALL-CATALOG.md');

            $cmd = escapeshellarg((string) PHP_BINARY) . ' ' . escapeshellarg(self::$validator) . ' --root=' . escapeshellarg($target);
            $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $process = proc_open($cmd, $descriptors, $pipes, self::$repoRoot);
            $this->assertIsResource($process);
            $stdout = (string) stream_get_contents($pipes[1]);
            $stderr = (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);

            $this->assertSame(0, $exit, $stdout . $stderr);
        } finally {
            $this->removeTree($target);
        }
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($path);
    }
}
