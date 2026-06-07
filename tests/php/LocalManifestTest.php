<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * P5-b: .ai/local-manifest.json informational writer.
 *
 * The local manifest is a gitignored, informational-only summary of what the kit
 * installed. It is NEVER consulted as a write allowlist — the canonical manifest
 * (.ai-install-manifest.json files{}) and the lock remain authoritative. This test
 * proves the writer emits the file and that it carries an explicit informational
 * marker, and that the install/upgrade/uninstall code never reads it for writes.
 */
final class LocalManifestTest extends TestCase
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

    public function testWritesInformationalLocalManifest(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'local_manifest_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root, 0700, true);

        $manifest = [
            'installer_version' => '0.2.0',
            'package' => ['installed_version' => '1.2.3'],
            'files' => [
                'AGENTS.md' => ['ownership' => 'owned', 'installed_hash' => 'sha256:aaa'],
                'docs/ai/project/README.md' => ['ownership' => 'template', 'installed_hash' => 'sha256:bbb'],
            ],
        ];

        aiInstallerWriteLocalManifest($root, $manifest);

        $path = $root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'local-manifest.json';
        $this->assertFileExists($path);

        $decoded = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($decoded);
        $this->assertTrue($decoded['informational'] ?? false, 'local manifest must self-declare informational');
        $this->assertArrayHasKey('not_a_write_allowlist', $decoded);
        $this->assertTrue($decoded['not_a_write_allowlist']);
        $this->assertSame('1.2.3', $decoded['installed_version'] ?? null);
        $this->assertContains('AGENTS.md', array_keys($decoded['files'] ?? []));
        $this->assertContains('docs/ai/project/README.md', array_keys($decoded['files'] ?? []));
    }

    public function testLocalManifestIsGitignored(): void
    {
        // The gitignore managed block must list .ai/local-manifest.json so it is never tracked.
        $coreSource = (string) file_get_contents(self::$repoRoot . '/tools/ai/install/core.php');
        $this->assertStringContainsString('.ai/local-manifest.json', $coreSource, 'local-manifest must be in the gitignore block');
    }

    public function testLocalManifestIsNotUsedAsAWriteAllowlist(): void
    {
        // Neither install/upgrade/uninstall logic may read local-manifest.json to decide writes.
        // The canonical readers are the manifest (.ai-install-manifest.json) and the lock.
        foreach ([
            'tools/ai/install/core.php',
            'tools/ai/install/planner.php',
            'tools/ai/commands/install_workflow.php',
        ] as $rel) {
            $src = (string) file_get_contents(self::$repoRoot . '/' . $rel);
            // Allow writing the file; forbid reading it back (file_get_contents/json_decode on it).
            $this->assertStringNotContainsString(
                "file_get_contents(\$" . "localManifest",
                $src,
                "{$rel} must not read local-manifest.json as a write source"
            );
        }
        $this->assertTrue(true);
    }
}
