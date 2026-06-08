<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Phase 2: descriptor copy-out gate.
 *
 * `descriptors --copy-out` lets a user copy a copyOutSafe relocated descriptor (manifest.json /
 * manifest.yml) from `.ai/` back to its canonical root filename. The copy is gated EXACTLY like
 * the installer's foreign-file protection: a differing root file is NEVER overwritten; instead the
 * incoming kit copy is snapshotted under `.ai/conflicts/<ts>-descriptors/incoming/`. catalog.json
 * and package-lock.ai.json are not copy-out safe and are refused. `--dry-run` is the default;
 * writes require `--apply`.
 *
 * These tests drive aiRunDescriptors() directly (it returns an int exit code) and assert
 * filesystem side effects + return code.
 */
final class DescriptorCopyOutTest extends TestCase
{
    /** @var list<string> */
    private array $tmpDirs = [];

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root');
        }
        require_once $root . '/tools/ai/ai_output_lib.php';
        require_once $root . '/tools/ai/install/core.php';
        require_once $root . '/tools/ai/commands/descriptors_command.php';
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpDirs as $dir) {
            $this->removeTree($dir);
        }
        $this->tmpDirs = [];
    }

    /**
     * Build a temp target root carrying the four relocated `.ai/` descriptors plus a minimal
     * `.ai/local-manifest.json` recording their provenance.
     */
    private function makeInstalledRoot(string $label): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $label . '_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root . DIRECTORY_SEPARATOR . '.ai', 0700, true);

        $descriptors = [
            '.ai/kit-manifest.json' => "{\"kit\":\"manifest\",\"v\":1}\n",
            '.ai/kit-manifest.yml' => "kit: manifest\nv: 1\n",
            '.ai/catalog.json' => "{\"kit\":\"catalog\"}\n",
            '.ai/package-lock.ai.json' => "{\"kit\":\"lock\"}\n",
        ];
        foreach ($descriptors as $rel => $bytes) {
            file_put_contents($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel), $bytes);
        }

        // Reuse the Phase 1 writer so provenance is recorded exactly as the installer would.
        $manifest = [
            'installer_version' => '0.2.0',
            'package' => ['installed_version' => '1.0.0'],
            'files' => [],
        ];
        foreach (array_keys($descriptors) as $rel) {
            $manifest['files'][$rel] = ['ownership' => 'owned', 'installed_hash' => 'sha256:test'];
        }
        aiInstallerWriteLocalManifest($root, $manifest);

        return $root;
    }

    private function aiPath(string $root, string $rel): string
    {
        return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    }

    public function testApplyCopyOutIntoCleanRootWritesAndVerifiesSha256(): void
    {
        $root = $this->makeInstalledRoot('copyout_clean');
        $source = $this->aiPath($root, '.ai/kit-manifest.json');
        $target = $root . DIRECTORY_SEPARATOR . 'manifest.json';
        $this->assertFileDoesNotExist($target);

        $code = aiRunDescriptors($root, ['--copy-out', '--name', 'manifest.json', '--apply']);

        $this->assertSame(0, $code, 'clean-root apply copy-out should succeed');
        $this->assertFileExists($target);
        $this->assertSame(
            hash_file('sha256', $source),
            hash_file('sha256', $target),
            'written root file must be byte-identical to the .ai source'
        );
    }

    public function testApplyCopyOutDoesNotOverwriteDifferingRootAndSnapshotsIncoming(): void
    {
        $root = $this->makeInstalledRoot('copyout_conflict');
        $target = $root . DIRECTORY_SEPARATOR . 'manifest.json';
        $foreignBytes = "{\"name\":\"consumer-app\",\"version\":\"9.9.9\"}\n";
        file_put_contents($target, $foreignBytes);

        $code = aiRunDescriptors($root, ['--copy-out', '--name', 'manifest.json', '--apply']);

        $this->assertSame(1, $code, 'differing root file must make copy-out fail non-zero');
        $this->assertSame(
            $foreignBytes,
            (string) file_get_contents($target),
            'the foreign root manifest.json must NOT be overwritten'
        );

        $conflictRoot = $root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'conflicts';
        $incoming = $this->findIncomingCopy($conflictRoot, 'manifest.json');
        $this->assertNotNull($incoming, 'an incoming kit copy must be snapshotted under .ai/conflicts/<ts>-descriptors/incoming/');
        $this->assertSame(
            hash_file('sha256', $this->aiPath($root, '.ai/kit-manifest.json')),
            hash_file('sha256', (string) $incoming),
            'the incoming snapshot must equal the .ai kit copy'
        );
    }

    public function testCopyOutCatalogIsRefusedAndRootNotCreated(): void
    {
        $root = $this->makeInstalledRoot('copyout_catalog');
        $target = $root . DIRECTORY_SEPARATOR . 'catalog.json';

        $code = aiRunDescriptors($root, ['--copy-out', '--name', 'catalog.json', '--apply']);

        $this->assertSame(1, $code, 'copy-out of a non-copyOutSafe descriptor must be refused');
        $this->assertFileDoesNotExist($target, 'catalog.json must not be created at root');
    }

    public function testDryRunIsDefaultAndWritesNothing(): void
    {
        $root = $this->makeInstalledRoot('copyout_dryrun');
        $target = $root . DIRECTORY_SEPARATOR . 'manifest.json';
        $this->assertFileDoesNotExist($target);

        // No --apply: dry-run is the default.
        $code = aiRunDescriptors($root, ['--copy-out', '--name', 'manifest.json']);

        $this->assertSame(0, $code, 'default dry-run copy-out into a clean root returns 0');
        $this->assertFileDoesNotExist($target, 'dry-run must not write anything at root');
    }

    public function testListReportsFourDescriptorsWithCorrectFlags(): void
    {
        $root = $this->makeInstalledRoot('copyout_list');

        // The command writes to the STDOUT file descriptor (repo convention), which output
        // buffering cannot capture; assert exit code from the command and content from the pure
        // row builder it uses internally.
        $code = aiRunDescriptors($root, ['--list']);
        $this->assertSame(0, $code, '--list on an installed root returns 0');

        $rows = aiDescriptorsListRows($root);
        $this->assertIsArray($rows, 'list rows must build from an installed root');

        $byName = [];
        foreach ($rows as $row) {
            $byName[$row['canonicalRootName']] = $row;
        }

        foreach (['manifest.json', 'manifest.yml', 'catalog.json', 'package-lock.ai.json'] as $name) {
            $this->assertArrayHasKey($name, $byName, "--list must include {$name}");
        }
        // copyOutSafe flags: manifest.* are safe, catalog/lock are not.
        $this->assertTrue($byName['manifest.json']['copyOutSafe']);
        $this->assertTrue($byName['manifest.yml']['copyOutSafe']);
        $this->assertFalse($byName['catalog.json']['copyOutSafe']);
        $this->assertFalse($byName['package-lock.ai.json']['copyOutSafe']);
    }

    public function testListReturnsNonZeroWhenLocalManifestMissing(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copyout_nomanifest_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root, 0700, true);

        $code = aiRunDescriptors($root, ['--list']);

        $this->assertSame(1, $code, 'missing .ai/local-manifest.json must return non-zero');
    }

    private function findIncomingCopy(string $conflictRoot, string $name): ?string
    {
        if (!is_dir($conflictRoot)) {
            return null;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($conflictRoot, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $item) {
            $path = $item->getPathname();
            $normalized = str_replace('\\', '/', $path);
            if ($item->isFile()
                && str_contains($normalized, '-descriptors/incoming/')
                && str_ends_with($normalized, '/' . $name)) {
                return $path;
            }
        }

        return null;
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
}
