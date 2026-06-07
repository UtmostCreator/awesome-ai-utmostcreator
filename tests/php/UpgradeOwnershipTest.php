<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Phase 2: ownership-aware upgrade.
 *
 * Upgrade must preserve user-modified owned files (route to .ai/conflicts/) and never
 * overwrite template files. These tests cover the conflict-preservation helper and the
 * idempotency contract.
 */
final class UpgradeOwnershipTest extends TestCase
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

    public function testPreservesUserModifiedOwnedFileToConflicts(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'upgrade_conflict_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai', 0700, true);
        $rel = 'docs/ai/workflow.md';
        file_put_contents($root . DIRECTORY_SEPARATOR . $rel, "# user edited workflow\n");

        $fileActions = [
            ['file' => $rel, 'action' => 'conflict-preserve-user', 'ownership' => 'owned'],
            ['file' => 'docs/ai/project-stack.md', 'action' => 'preserve', 'ownership' => 'template'],
        ];

        $preserved = aiUpgradePreserveOwnedConflicts($root, $fileActions);

        $this->assertCount(1, $preserved, 'only the owned conflict should be preserved');
        $this->assertSame($rel, $preserved[0]['file']);

        // The preserved copy exists under .ai/conflicts/ and matches the user content.
        $copyAbs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $preserved[0]['preserved_to']);
        $this->assertFileExists($copyAbs);
        $this->assertSame("# user edited workflow\n", (string) file_get_contents($copyAbs));
        $this->assertStringStartsWith('.ai/conflicts/', $preserved[0]['preserved_to']);
    }

    public function testTemplateFilesAreNotPreservedAsConflicts(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'upgrade_template_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root, 0700, true);

        // Only template/preserve actions: nothing should be copied to .ai/conflicts/.
        $preserved = aiUpgradePreserveOwnedConflicts($root, [
            ['file' => 'docs/ai/project-stack.md', 'action' => 'preserve', 'ownership' => 'template'],
            ['file' => 'AGENTS.md', 'action' => 'auto-update', 'ownership' => 'owned'],
        ]);

        $this->assertSame([], $preserved);
        $this->assertDirectoryDoesNotExist($root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'conflicts');
    }

    public function testNoConflictsProducesNoConflictDirectory(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'upgrade_clean_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root, 0700, true);

        $preserved = aiUpgradePreserveOwnedConflicts($root, []);
        $this->assertSame([], $preserved);
        $this->assertDirectoryDoesNotExist($root . DIRECTORY_SEPARATOR . '.ai');
    }
}
