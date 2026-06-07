<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * P3-e: empty-directory removal on uninstall is gated to lock `createdDirs`.
 *
 * The uninstaller may only remove a now-empty directory that the kit itself created
 * (recorded in .ai/manifest.lock.json createdDirs). A pre-existing user directory that
 * merely becomes empty must be preserved, and no recursive directory delete ever runs
 * through the empty-parent prune path.
 */
final class UninstallDirPruneTest extends TestCase
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
        require_once $root . '/tools/ai/commands/install_workflow.php';
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

    public function testPrunesEmptyKitCreatedDirectory(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'prune_created_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        $dir = $root . DIRECTORY_SEPARATOR . '.opencode' . DIRECTORY_SEPARATOR . 'agents';
        mkdir($dir, 0700, true);

        aiUninstallPruneEmptyParents($dir, $root, ['.opencode/agents', '.opencode']);

        $this->assertDirectoryDoesNotExist($dir, 'kit-created empty dir is pruned');
    }

    public function testPreservesEmptyNonCreatedDirectory(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'prune_foreign_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        $userDir = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'user-area';
        mkdir($userDir, 0700, true);

        // docs/user-area is empty but NOT a kit-created dir: must be preserved.
        aiUninstallPruneEmptyParents($userDir, $root, ['.opencode/agents']);

        $this->assertDirectoryExists($userDir, 'a non-kit-created empty dir must be preserved');
    }

    public function testNeverRemovesNonEmptyDirectory(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'prune_nonempty_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        $dir = $root . DIRECTORY_SEPARATOR . '.opencode' . DIRECTORY_SEPARATOR . 'agents';
        mkdir($dir, 0700, true);
        // A user file lives alongside kit content: the dir is kit-created but not empty.
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'my-agent.md', "user\n");

        aiUninstallPruneEmptyParents($dir, $root, ['.opencode/agents', '.opencode']);

        $this->assertDirectoryExists($dir, 'a non-empty kit-created dir must never be removed');
        $this->assertFileExists($dir . DIRECTORY_SEPARATOR . 'my-agent.md', 'user file in a kit dir survives');
    }

    public function testStopsAtFirstNonCreatedParent(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'prune_chain_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        $leaf = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'kitsub';
        mkdir($leaf, 0700, true);

        // Only the leaf is kit-created; docs/ai and docs are pre-existing user dirs.
        aiUninstallPruneEmptyParents($leaf, $root, ['docs/ai/kitsub']);

        $this->assertDirectoryDoesNotExist($leaf, 'kit-created leaf is pruned');
        $this->assertDirectoryExists($root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai', 'non-created parent preserved');
        $this->assertDirectoryExists($root . DIRECTORY_SEPARATOR . 'docs', 'non-created grandparent preserved');
    }
}
