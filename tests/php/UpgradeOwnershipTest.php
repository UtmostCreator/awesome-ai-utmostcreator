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
        require_once $root . '/tools/ai/install/planner.php';
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

    public function testUpgradeApplyReinstallArgsForceOwnedFileRefresh(): void
    {
        $this->assertTrue(
            function_exists('aiUpgradeBuildApplyInstallArgs'),
            'upgrade apply install args must be testable'
        );

        $args = aiUpgradeBuildApplyInstallArgs('sidecar-only', 'backup-123', ['--agent', '--ci']);

        $this->assertContains('--apply', $args);
        $this->assertContains('--reinstall', $args);
        $this->assertContains('--force', $args, 'upgrade --apply must force reinstall so owned files are refreshed after conflict preservation');
        $this->assertContains('--agent', $args);
        $this->assertContains('--ci', $args);
    }

    public function testForcePreservesSkipIfExistsTemplatesInPlan(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'upgrade_force_template_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root . DIRECTORY_SEPARATOR . 'source', 0700, true);
        mkdir($root . DIRECTORY_SEPARATOR . 'target', 0700, true);

        $source = $root . DIRECTORY_SEPARATOR . 'source' . DIRECTORY_SEPARATOR . 'template.md';
        $target = $root . DIRECTORY_SEPARATOR . 'target' . DIRECTORY_SEPARATOR . 'template.md';
        file_put_contents($source, "# kit template\n");
        file_put_contents($target, "# user template edit\n");

        $plan = aiInstallerBuildPlan([
            'sourceRoot' => $root . DIRECTORY_SEPARATOR . 'source',
            'targetRoot' => $root . DIRECTORY_SEPARATOR . 'target',
            'force' => true,
            'adopt' => false,
            'allowCoreOverwrite' => false,
            'upgradeSuffix' => '',
        ], [
            'template-pack' => [[
                'type' => 'file',
                'source' => 'template.md',
                'target' => 'template.md',
                'merge_strategy' => 'skip-if-exists',
                'core' => false,
            ]],
        ], ['template-pack']);

        $this->assertSame('SKIP_EXISTING_UNMANAGED', $plan[0]['action'] ?? null);
        $this->assertSame('template (skip-if-exists) preserved under force', $plan[0]['reason'] ?? null);
    }

    // ---- P0-b Slice 2: computed `deprecated` class (never stored) ----

    public function testComputeDeprecatedFlagsManifestFilesAbsentFromRegistry(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'upgrade_deprecated_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai', 0700, true);

        // A stale file installed previously but no longer shipped by the kit.
        $stale = 'docs/ai/stale-hook.md';
        file_put_contents($root . DIRECTORY_SEPARATOR . $stale, "# installed bytes\n");
        $staleHash = 'sha256:' . hash('sha256', "# installed bytes\n");

        // A still-current file the kit continues to ship.
        $current = 'docs/ai/workflow.md';
        file_put_contents($root . DIRECTORY_SEPARATOR . $current, "# current\n");

        $manifestFiles = [
            $stale => ['ownership' => 'owned', 'installed_hash' => $staleHash],
            $current => ['ownership' => 'owned', 'installed_hash' => 'sha256:' . hash('sha256', "# current\n")],
        ];
        $registryTargets = [$current];

        $deprecated = aiUpgradeComputeDeprecated($manifestFiles, $registryTargets, $root);

        $this->assertCount(1, $deprecated, 'only the unshipped manifest file is deprecated');
        $this->assertSame($stale, $deprecated[0]['file']);
        $this->assertSame('deprecated', $deprecated[0]['ownership']);
        $this->assertSame('deprecated-unchanged', $deprecated[0]['status']);
        $this->assertSame('delete', $deprecated[0]['action'], 'unchanged deprecated file is deleted (already in backup)');
    }

    public function testComputeDeprecatedRoutesUserModifiedToRemoved(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'upgrade_deprecated_mod_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai', 0700, true);

        $stale = 'docs/ai/stale-policy.md';
        file_put_contents($root . DIRECTORY_SEPARATOR . $stale, "# user edited the stale file\n");
        // installed_hash differs from current bytes -> user modified.
        $manifestFiles = [
            $stale => ['ownership' => 'owned', 'installed_hash' => 'sha256:' . hash('sha256', "# original installed\n")],
        ];

        $deprecated = aiUpgradeComputeDeprecated($manifestFiles, [], $root);

        $this->assertCount(1, $deprecated);
        $this->assertSame('deprecated-user-modified', $deprecated[0]['status']);
        $this->assertSame('route-to-removed', $deprecated[0]['action'], 'user-modified deprecated bytes must be routed to conflicts/removed/, never silently deleted');
    }

    public function testComputeDeprecatedIgnoresMissingFiles(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'upgrade_deprecated_missing_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root, 0700, true);

        // File recorded in manifest but already gone from disk: nothing to delete or route.
        $manifestFiles = ['docs/ai/gone.md' => ['ownership' => 'owned', 'installed_hash' => 'sha256:abc']];
        $deprecated = aiUpgradeComputeDeprecated($manifestFiles, [], $root);

        $this->assertSame([], $deprecated, 'a deprecated file already absent from disk needs no action');
    }
}
