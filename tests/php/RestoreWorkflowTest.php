<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * P4-b: `restore --from <ts> [--path]` checksum-gated copy-back.
 *
 * Restore reuses the canonical backup snapshot machinery (checksum-gated) to copy a
 * prior backup's bytes back into place. Dry-run writes nothing; apply restores and
 * appends an audit entry to .ai/logs/. --path narrows the restore to one file.
 */
final class RestoreWorkflowTest extends TestCase
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
        require_once $root . '/tools/ai/ai_output_lib.php';
        require_once $root . '/tools/ai/commands/helpers.php';
        require_once $root . '/tools/ai/install/core.php';
        require_once $root . '/tools/ai/install/backup.php';
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

    /**
     * Build a target with a recorded backup whose snapshot differs from the current file.
     * Returns [targetRoot, backupId, relPath].
     */
    private function makeBackupFixture(): array
    {
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'restore_target_' . uniqid('', true);
        $source = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'restore_source_' . uniqid('', true);
        $this->tmpDirs[] = $target;
        $this->tmpDirs[] = $source;

        mkdir($target . DIRECTORY_SEPARATOR . 'docs', 0700, true);
        mkdir($source . DIRECTORY_SEPARATOR . 'templates', 0700, true);
        file_put_contents($target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'existing.md', "before\n");
        file_put_contents($source . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'existing.md', "after\n");

        $plan = [[
            'type' => 'dir',
            'source' => 'templates',
            'target' => 'docs',
            'action' => 'OVERWRITE_MANAGED',
        ]];

        $backup = aiInstallBackupCreate($target, $plan, $source, 'test');
        $backupId = (string) $backup['backup_id'];

        // Install overwrote the file; record the post-install state.
        file_put_contents($target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'existing.md', "after\n");
        aiInstallBackupRecordAfter($target, $backupId, $plan, $source, 'applied');

        return [$target, $backupId, 'docs/existing.md'];
    }

    public function testRestoreDryRunWritesNothing(): void
    {
        [$target, $backupId, $rel] = $this->makeBackupFixture();

        $exit = aiRunRestoreWorkflow($target, ['--from', $backupId]);
        $this->assertSame(0, $exit);

        // File still holds the post-install bytes; no audit log written on dry-run.
        $this->assertSame("after\n", (string) file_get_contents($target . DIRECTORY_SEPARATOR . $rel));
        $this->assertDirectoryDoesNotExist($target . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'logs');
    }

    public function testRestoreApplyRestoresBytesAndLogs(): void
    {
        [$target, $backupId, $rel] = $this->makeBackupFixture();

        $exit = aiRunRestoreWorkflow($target, ['--from', $backupId, '--apply']);
        $this->assertSame(0, $exit);

        // The pre-install snapshot bytes are restored.
        $this->assertSame("before\n", (string) file_get_contents($target . DIRECTORY_SEPARATOR . $rel));

        // An audit entry is appended under .ai/logs/.
        $logsDir = $target . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'logs';
        $this->assertDirectoryExists($logsDir);
        $logs = glob($logsDir . DIRECTORY_SEPARATOR . 'restore-*.json') ?: [];
        $this->assertNotEmpty($logs, 'restore --apply must write an audit log under .ai/logs/');
        $entry = json_decode((string) file_get_contents($logs[0]), true);
        $this->assertIsArray($entry);
        $this->assertSame($backupId, $entry['from'] ?? null);
        $this->assertContains($rel, $entry['restored_targets'] ?? []);
    }

    public function testRestorePathNarrowsToSingleFile(): void
    {
        [$target, $backupId] = $this->makeBackupFixture();

        // --path docs/existing.md narrows the restore (same single file here).
        $exit = aiRunRestoreWorkflow($target, ['--from', $backupId, '--apply', '--path', 'docs/existing.md']);
        $this->assertSame(0, $exit);
        $this->assertSame("before\n", (string) file_get_contents($target . DIRECTORY_SEPARATOR . 'docs/existing.md'));
    }

    public function testRestoreRequiresFrom(): void
    {
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'restore_nofrom_' . uniqid('', true);
        $this->tmpDirs[] = $target;
        mkdir($target, 0700, true);

        $this->expectException(\RuntimeException::class);
        aiRunRestoreWorkflow($target, ['--apply']);
    }
}
