<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * P5-c: unified 0700 private structure for kit-private state.
 *
 * Conflict subtrees use a single timestamped, operation-tagged root
 * `.ai/conflicts/<ts>-<op>/` with `files/`, `incoming/`, and `removed/` subdirs, and
 * private dirs are created mode 0700 so backup/conflict bytes are not world-readable.
 */
final class PrivateStructureTest extends TestCase
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
        require_once $root . '/tools/ai/commands/install_paths.php';
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

    public function testPrivateDirModeIsRestrictive(): void
    {
        $this->assertSame(0700, aiInstallerPrivateDirMode(), 'kit-private dirs must be created 0700');
    }

    public function testBackupRootUsesOperationTaggedPrivateStructure(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'p5c_backup_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root . DIRECTORY_SEPARATOR . 'docs', 0700, true);
        file_put_contents($root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'existing.md', "# before\n");

        $backup = aiInstallBackupCreate($root, [
            ['target' => 'docs/existing.md', 'action' => 'UPDATE'],
        ], $root, 'install');

        $this->assertMatchesRegularExpression('#^\d{8}T\d{6}Z-install$#', (string) $backup['backup_id']);
        $this->assertMatchesRegularExpression('#^\.ai/backups/\d{8}T\d{6}Z-install$#', (string) $backup['backup_dir']);
        $this->assertFileExists($root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $backup['backup_id'] . DIRECTORY_SEPARATOR . 'manifest.json');
        $this->assertDirectoryDoesNotExist($root . DIRECTORY_SEPARATOR . '.ai-backups');

        if (DIRECTORY_SEPARATOR === '/') {
            $opDir = $root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $backup['backup_id'];
            $perms = substr(sprintf('%o', fileperms($opDir)), -4);
            $this->assertSame('0700', $perms, 'backup op dir must be 0700');
        }
    }

    public function testTemplateRefreshUsesPrivateTemplatesNewRoot(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'p5c_templates_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        $sourceRoot = $root . DIRECTORY_SEPARATOR . 'source';
        $targetRoot = $root . DIRECTORY_SEPARATOR . 'target';
        mkdir($sourceRoot . DIRECTORY_SEPARATOR . 'docs', 0700, true);
        mkdir($targetRoot . DIRECTORY_SEPARATOR . 'docs', 0700, true);
        file_put_contents($sourceRoot . DIRECTORY_SEPARATOR . 'docs/template.md', "# new upstream\n");
        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . 'docs/template.md', "# user version\n");

        $routed = aiInstallerOfferTemplateRefresh(
            ['sourceRoot' => $sourceRoot, 'targetRoot' => $targetRoot],
            ['source' => 'docs/template.md', 'target' => 'docs/template.md']
        );

        $this->assertSame('.ai/templates-new/docs/template.md', $routed);
        $this->assertFileExists($targetRoot . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'templates-new' . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'template.md');

        if (DIRECTORY_SEPARATOR === '/') {
            $templateDir = $targetRoot . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'templates-new';
            $perms = substr(sprintf('%o', fileperms($templateDir)), -4);
            $this->assertSame('0700', $perms, 'templates-new root must be 0700');
        }
    }

    public function testConflictRootTagsTimestampWithOperation(): void
    {
        $root = aiInstallerPrivateConflictDir('/tmp/example', 'upgrade', 'files');
        $this->assertMatchesRegularExpression(
            '#/\.ai/conflicts/\d{8}T\d{6}Z-upgrade/files$#',
            str_replace('\\', '/', $root),
            'conflict subtree must be .ai/conflicts/<ts>-<op>/<kind>'
        );
    }

    public function testPreservedOwnedConflictUsesOperationTaggedFilesSubdir(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'p5c_files_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root . DIRECTORY_SEPARATOR . 'docs', 0700, true);
        $rel = 'docs/workflow.md';
        file_put_contents($root . DIRECTORY_SEPARATOR . $rel, "# user edit\n");

        $preserved = aiUpgradePreserveOwnedConflicts($root, [
            ['file' => $rel, 'action' => 'conflict-preserve-user', 'ownership' => 'owned'],
        ]);

        $this->assertCount(1, $preserved);
        $this->assertMatchesRegularExpression(
            '#^\.ai/conflicts/\d{8}T\d{6}Z-upgrade/files/docs/workflow\.md$#',
            $preserved[0]['preserved_to'],
            'preserved owned conflicts live under <ts>-upgrade/files/'
        );

        if (DIRECTORY_SEPARATOR === '/') {
            $conflictBase = $root . '/.ai/conflicts';
            $entries = array_values(array_diff((array) scandir($conflictBase), ['.', '..']));
            $perms = substr(sprintf('%o', fileperms($conflictBase . '/' . $entries[0])), -4);
            $this->assertSame('0700', $perms, 'conflict op dir must be 0700');
        }
    }

    public function testRemovedDeprecatedUsesOperationTaggedRemovedSubdir(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'p5c_removed_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root . DIRECTORY_SEPARATOR . 'docs', 0700, true);
        $rel = 'docs/stale.md';
        file_put_contents($root . DIRECTORY_SEPARATOR . $rel, "# user edited stale\n");

        $acted = aiUpgradeRemoveDeprecated($root, [
            ['file' => $rel, 'action' => 'route-to-removed'],
        ]);

        $this->assertCount(1, $acted);
        $this->assertMatchesRegularExpression(
            '#^\.ai/conflicts/\d{8}T\d{6}Z-upgrade/removed/docs/stale\.md$#',
            $acted[0]['routed_to'],
            'removed deprecated bytes live under <ts>-upgrade/removed/'
        );
    }

    public function testIncomingConflictUsesOperationTaggedIncomingSubdir(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'p5c_incoming_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        $sourceRoot = $root . DIRECTORY_SEPARATOR . 'source';
        $targetRoot = $root . DIRECTORY_SEPARATOR . 'target';
        mkdir($sourceRoot . DIRECTORY_SEPARATOR . 'docs', 0700, true);
        mkdir($targetRoot . DIRECTORY_SEPARATOR . 'docs', 0700, true);
        file_put_contents($sourceRoot . DIRECTORY_SEPARATOR . 'docs/x.md', "# kit\n");
        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . 'docs/x.md', "# foreign\n");

        $routed = aiInstallerOfferIncomingConflict(
            ['sourceRoot' => $sourceRoot, 'targetRoot' => $targetRoot],
            ['source' => 'docs/x.md', 'target' => 'docs/x.md', 'type' => 'file', 'merge_strategy' => 'replace']
        );

        $this->assertNotNull($routed);
        $this->assertMatchesRegularExpression(
            '#^\.ai/conflicts/\d{8}T\d{6}Z-install/incoming/docs/x\.md$#',
            $routed,
            'incoming kit files live under <ts>-install/incoming/'
        );
    }
}
