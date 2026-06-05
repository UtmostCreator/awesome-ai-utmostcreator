<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

class AiResolvePackageBaseTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ai_resolve_pkg_base_test_' . uniqid('', true);
        mkdir($this->tmpDir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }

    // ---- aiResolvePackageBase: 4 branches ----

    public function testSourceRepoReturnsPackagesPrefixWhenTemplatesDirPresent(): void
    {
        mkdir($this->tmpDir . '/packages/ai-universal-rules/templates', 0700, true);
        $this->assertSame('packages/ai-universal-rules/', aiResolvePackageBase($this->tmpDir));
    }

    public function testNewConsumerReturnsEmptyStringForRootManifestWithoutTemplates(): void
    {
        file_put_contents($this->tmpDir . '/manifest.json', '{}');
        file_put_contents($this->tmpDir . '/.ai-install-manifest.json', '{}');
        $this->assertSame('', aiResolvePackageBase($this->tmpDir));
    }

    public function testLegacyConsumerReturnsPackagesPrefixForPackageManifestWithoutTemplates(): void
    {
        mkdir($this->tmpDir . '/packages/ai-universal-rules', 0700, true);
        file_put_contents($this->tmpDir . '/packages/ai-universal-rules/manifest.json', '{}');
        $this->assertSame('packages/ai-universal-rules/', aiResolvePackageBase($this->tmpDir));
    }

    public function testDefaultReturnsPackagesPrefixWhenNoMarkersPresent(): void
    {
        $this->assertSame('packages/ai-universal-rules/', aiResolvePackageBase($this->tmpDir));
    }

    public function testNewConsumerRequiresBothRootMarkers(): void
    {
        // Only root manifest.json, no .ai-install-manifest.json -> falls through to default.
        file_put_contents($this->tmpDir . '/manifest.json', '{}');
        $this->assertSame('packages/ai-universal-rules/', aiResolvePackageBase($this->tmpDir));
    }

    // ---- aiResolvePackageDocsBase ----

    public function testDocsBaseUsesPackagesDocsWhenBaseIsPackages(): void
    {
        mkdir($this->tmpDir . '/packages/ai-universal-rules/templates', 0700, true);
        $this->assertSame('packages/ai-universal-rules/docs/', aiResolvePackageDocsBase($this->tmpDir));
    }

    public function testDocsBaseUsesRootDocsWhenBaseIsEmpty(): void
    {
        file_put_contents($this->tmpDir . '/manifest.json', '{}');
        file_put_contents($this->tmpDir . '/.ai-install-manifest.json', '{}');
        $this->assertSame('docs/ai/package/', aiResolvePackageDocsBase($this->tmpDir));
    }

    // ---- contract: composes via aiAbsolutePath ----

    public function testBaseComposesWithAiAbsolutePath(): void
    {
        file_put_contents($this->tmpDir . '/manifest.json', '{}');
        file_put_contents($this->tmpDir . '/.ai-install-manifest.json', '{}');
        $expected = $this->tmpDir . DIRECTORY_SEPARATOR . 'manifest.json';
        $this->assertSame(
            $expected,
            aiAbsolutePath($this->tmpDir, aiResolvePackageBase($this->tmpDir) . 'manifest.json')
        );
    }
}
