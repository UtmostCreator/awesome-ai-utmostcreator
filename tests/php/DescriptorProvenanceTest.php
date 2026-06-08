<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Phase 1: descriptor provenance.
 *
 * The kit relocates its package descriptors under `.ai/` (kit-manifest.json,
 * kit-manifest.yml, catalog.json, package-lock.ai.json) so they never collide with a
 * consumer project's own root files. aiInstallerDescriptorProvenance() is the pure mapping
 * from a relocated `.ai/` target back to the canonical root filename a user might expect,
 * plus whether copying it back out to root is safe. aiInstallerWriteLocalManifest() attaches
 * that provenance to the informational-only `.ai/local-manifest.json`.
 */
final class DescriptorProvenanceTest extends TestCase
{
    private static string $repoRoot;
    /** @var list<string> */
    private array $tmpDirs = [];

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root');
        }
        self::$repoRoot = $root;
        require_once $root . '/tools/ai/install/markers.php';
        require_once $root . '/tools/ai/install/core.php';
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpDirs as $dir) {
            $this->removeTree($dir);
        }
        $this->tmpDirs = [];
    }

    public function testManifestJsonMapsToCanonicalRootAndIsCopyOutSafe(): void
    {
        $prov = aiInstallerDescriptorProvenance('.ai/kit-manifest.json');
        $this->assertIsArray($prov);
        $this->assertSame('manifest.json', $prov['canonicalRootName']);
        $this->assertTrue($prov['copyOutSafe']);
    }

    public function testManifestYmlMapsToCanonicalRootAndIsCopyOutSafe(): void
    {
        $prov = aiInstallerDescriptorProvenance('.ai/kit-manifest.yml');
        $this->assertIsArray($prov);
        $this->assertSame('manifest.yml', $prov['canonicalRootName']);
        $this->assertTrue($prov['copyOutSafe']);
    }

    public function testCatalogMapsToCanonicalRootButIsNotCopyOutSafe(): void
    {
        $prov = aiInstallerDescriptorProvenance('.ai/catalog.json');
        $this->assertIsArray($prov);
        $this->assertSame('catalog.json', $prov['canonicalRootName']);
        $this->assertFalse($prov['copyOutSafe']);
    }

    public function testPackageLockMapsToCanonicalRootButIsNotCopyOutSafe(): void
    {
        $prov = aiInstallerDescriptorProvenance('.ai/package-lock.ai.json');
        $this->assertIsArray($prov);
        $this->assertSame('package-lock.ai.json', $prov['canonicalRootName']);
        $this->assertFalse($prov['copyOutSafe']);
    }

    public function testNonDescriptorPathsReturnNull(): void
    {
        $this->assertNull(aiInstallerDescriptorProvenance('AGENTS.md'));
        $this->assertNull(aiInstallerDescriptorProvenance('docs/ai/project/README.md'));
        $this->assertNull(aiInstallerDescriptorProvenance('manifest.json'));
        $this->assertNull(aiInstallerDescriptorProvenance(''));
    }

    public function testLocalManifestAttachesProvenanceOnlyForRelocatedDescriptors(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'descriptor_prov_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root, 0700, true);

        $manifest = [
            'installer_version' => '0.2.0',
            'package' => ['installed_version' => '1.2.3'],
            'files' => [
                '.ai/kit-manifest.json' => ['ownership' => 'owned', 'installed_hash' => 'sha256:aaa'],
                'AGENTS.md' => ['ownership' => 'owned', 'installed_hash' => 'sha256:bbb'],
            ],
        ];

        aiInstallerWriteLocalManifest($root, $manifest);

        $path = $root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'local-manifest.json';
        $this->assertFileExists($path);

        $decoded = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($decoded);
        $files = $decoded['files'] ?? [];
        $this->assertIsArray($files);

        // Relocated descriptor carries provenance.
        $this->assertArrayHasKey('.ai/kit-manifest.json', $files);
        $this->assertArrayHasKey('descriptor', $files['.ai/kit-manifest.json']);
        $this->assertSame('manifest.json', $files['.ai/kit-manifest.json']['descriptor']['canonicalRootName']);
        $this->assertTrue($files['.ai/kit-manifest.json']['descriptor']['namespacedToAvoidCollision']);
        $this->assertTrue($files['.ai/kit-manifest.json']['descriptor']['copyOutSafe']);

        // Ordinary managed file has no descriptor provenance.
        $this->assertArrayHasKey('AGENTS.md', $files);
        $this->assertArrayNotHasKey('descriptor', $files['AGENTS.md']);
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
