<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * P3-c: incoming-path collision with a foreign file.
 *
 * When an incoming kit file would collide with an existing foreign (non-template,
 * user-authored) file and is therefore skipped, the kit version is surfaced under
 * .ai/conflicts/<ts>/incoming/<path> so the user can diff/merge. The foreign file
 * on disk is never overwritten.
 */
final class IncomingConflictTest extends TestCase
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

    private function makeRoots(): array
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'incoming_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        $sourceRoot = $root . DIRECTORY_SEPARATOR . 'source';
        $targetRoot = $root . DIRECTORY_SEPARATOR . 'target';
        mkdir($sourceRoot . DIRECTORY_SEPARATOR . 'docs', 0700, true);
        mkdir($targetRoot . DIRECTORY_SEPARATOR . 'docs', 0700, true);
        return [$sourceRoot, $targetRoot];
    }

    public function testRoutesIncomingKitFileToConflictsIncoming(): void
    {
        [$sourceRoot, $targetRoot] = $this->makeRoots();
        $rel = 'docs/kit-file.md';
        file_put_contents($sourceRoot . DIRECTORY_SEPARATOR . $rel, "# incoming kit version\n");
        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . $rel, "# user's foreign file\n");

        $config = ['sourceRoot' => $sourceRoot, 'targetRoot' => $targetRoot];
        $item = ['source' => $rel, 'target' => $rel, 'type' => 'file', 'merge_strategy' => 'replace'];

        $routed = aiInstallerOfferIncomingConflict($config, $item);

        $this->assertNotNull($routed, 'a foreign collision must be routed to incoming/');
        $this->assertStringStartsWith('.ai/conflicts/', $routed);
        $this->assertStringContainsString('/incoming/', $routed);

        $routedAbs = $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $routed);
        $this->assertFileExists($routedAbs);
        $this->assertSame("# incoming kit version\n", (string) file_get_contents($routedAbs), 'incoming copy holds the kit version');

        // The user's foreign file is never overwritten.
        $this->assertSame("# user's foreign file\n", (string) file_get_contents($targetRoot . DIRECTORY_SEPARATOR . $rel));
    }

    public function testNoIncomingWhenFilesAreIdentical(): void
    {
        [$sourceRoot, $targetRoot] = $this->makeRoots();
        $rel = 'docs/same.md';
        $bytes = "# identical\n";
        file_put_contents($sourceRoot . DIRECTORY_SEPARATOR . $rel, $bytes);
        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . $rel, $bytes);

        $routed = aiInstallerOfferIncomingConflict(
            ['sourceRoot' => $sourceRoot, 'targetRoot' => $targetRoot],
            ['source' => $rel, 'target' => $rel, 'type' => 'file', 'merge_strategy' => 'replace']
        );

        $this->assertNull($routed, 'no incoming copy when the existing file already matches the kit version');
    }

    public function testNoIncomingWhenTargetMissing(): void
    {
        [$sourceRoot, $targetRoot] = $this->makeRoots();
        $rel = 'docs/new.md';
        file_put_contents($sourceRoot . DIRECTORY_SEPARATOR . $rel, "# new\n");

        $routed = aiInstallerOfferIncomingConflict(
            ['sourceRoot' => $sourceRoot, 'targetRoot' => $targetRoot],
            ['source' => $rel, 'target' => $rel, 'type' => 'file', 'merge_strategy' => 'replace']
        );

        $this->assertNull($routed, 'no collision means no incoming copy (the file just installs normally)');
    }
}
