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
        require_once $root . '/tools/ai/install/core.php';
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

    // ---- P3-d: install idempotency (no SKIP_PROTECTED_CORE drift on a clean re-run) ----

    private function buildCorePlan(string $sourceBytes, string $targetBytes): array
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'idempotency_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root . DIRECTORY_SEPARATOR . 'source', 0700, true);
        mkdir($root . DIRECTORY_SEPARATOR . 'target', 0700, true);
        file_put_contents($root . DIRECTORY_SEPARATOR . 'source' . DIRECTORY_SEPARATOR . 'core.md', $sourceBytes);
        file_put_contents($root . DIRECTORY_SEPARATOR . 'target' . DIRECTORY_SEPARATOR . 'core.md', $targetBytes);

        return aiInstallerBuildPlan([
            'sourceRoot' => $root . DIRECTORY_SEPARATOR . 'source',
            'targetRoot' => $root . DIRECTORY_SEPARATOR . 'target',
            'force' => true,
            'adopt' => false,
            'allowCoreOverwrite' => false,
            'upgradeSuffix' => '',
        ], [
            'pack' => [[
                'type' => 'file',
                'source' => 'core.md',
                'target' => 'core.md',
                'merge_strategy' => 'replace',
                'core' => true,
            ]],
        ], ['pack']);
    }

    public function testForceReinstallOfIdenticalCoreFileIsNoOp(): void
    {
        // Second run with no changes: an identical core file must be a true no-op, not
        // SKIP_PROTECTED_CORE (which would report drift on every idempotent re-run).
        $plan = $this->buildCorePlan("# core\n", "# core\n");
        $this->assertSame('SKIP_IDENTICAL_EXISTING', $plan[0]['action'] ?? null, 'identical core file under force is an idempotent no-op');
    }

    public function testForceReinstallOfModifiedCoreFileStaysProtected()
    {
        // A genuinely differing core file is still protected unless --allow-core-overwrite.
        $plan = $this->buildCorePlan("# new core\n", "# user changed core\n");
        $this->assertSame('SKIP_PROTECTED_CORE', $plan[0]['action'] ?? null, 'differing core file remains protected');
    }

    private function buildManagedFilePlan(string $sourceBytes, string $targetBytes): array
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'identical_noop_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root . DIRECTORY_SEPARATOR . 'source', 0700, true);
        mkdir($root . DIRECTORY_SEPARATOR . 'target', 0700, true);
        file_put_contents($root . DIRECTORY_SEPARATOR . 'source' . DIRECTORY_SEPARATOR . 'managed.sh', $sourceBytes);
        file_put_contents($root . DIRECTORY_SEPARATOR . 'target' . DIRECTORY_SEPARATOR . 'managed.sh', $targetBytes);

        return aiInstallerBuildPlan([
            'sourceRoot' => $root . DIRECTORY_SEPARATOR . 'source',
            'targetRoot' => $root . DIRECTORY_SEPARATOR . 'target',
            'force' => true,
            'adopt' => false,
            'allowCoreOverwrite' => false,
            'upgradeSuffix' => '',
            // --backup makes a displaced kit-managed file safe to overwrite, so a
            // genuinely-different file reaches OVERWRITE_MANAGED rather than CONFLICT_FOREIGN.
            'backup' => true,
        ], [
            'pack' => [[
                'type' => 'file',
                'source' => 'managed.sh',
                'target' => 'managed.sh',
                'merge_strategy' => 'replace',
                'core' => false,
            ]],
        ], ['pack']);
    }

    public function testForceReinstallOfIdenticalManagedFileIsNoOp(): void
    {
        // A byte-identical non-core managed file under --force must be a true no-op so a
        // clean reinstall is zero-diff and the backup does not snapshot unchanged files.
        $plan = $this->buildManagedFilePlan("#!/usr/bin/env bash\necho hi\n", "#!/usr/bin/env bash\necho hi\n");
        $this->assertSame('SKIP_IDENTICAL_EXISTING', $plan[0]['action'] ?? null, 'identical managed file under force is an idempotent no-op');
        $this->assertSame('target matches source', $plan[0]['reason'] ?? null);
    }

    public function testForceReinstallOfModifiedManagedFileStillOverwrites(): void
    {
        // A genuinely-changed managed file must still be rewritten under --force --backup.
        $plan = $this->buildManagedFilePlan("#!/usr/bin/env bash\necho new\n", "#!/usr/bin/env bash\necho old\n");
        $this->assertSame('OVERWRITE_MANAGED', $plan[0]['action'] ?? null, 'changed managed file under force is overwritten');
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

    // ---- P0-b Slice 3: per-class upgrade action routing ----

    public function testResolveFileActionMissingTarget(): void
    {
        $r = aiUpgradeResolveFileAction('owned', false, false, true, false);
        $this->assertSame('missing', $r['status']);
        $this->assertSame('restore or remove from manifest', $r['action']);
    }

    public function testResolveFileActionTemplateAlwaysPreserved(): void
    {
        $unchanged = aiUpgradeResolveFileAction('template', false, true, false, false);
        $this->assertSame('template-unchanged', $unchanged['status']);
        $this->assertSame('preserve', $unchanged['action']);

        $modified = aiUpgradeResolveFileAction('template', true, true, false, false);
        $this->assertSame('template-user-owned', $modified['status']);
        $this->assertSame('preserve', $modified['action'], 'template files are never overwritten on upgrade');
    }

    public function testResolveFileActionRenderedAlwaysRegenerates(): void
    {
        $r = aiUpgradeResolveFileAction('rendered', false, false, false, false);
        $this->assertSame('rendered', $r['status']);
        $this->assertSame('regenerate', $r['action'], 'rendered files regenerate from project.yml each upgrade');

        // Even when the user touched it, rendered regenerates (user sections preserved by renderer).
        $modified = aiUpgradeResolveFileAction('rendered', true, false, false, false);
        $this->assertSame('regenerate', $modified['action']);
    }

    public function testResolveFileActionPatchManagedUpdatesBlock(): void
    {
        $r = aiUpgradeResolveFileAction('patch-managed', true, true, false, false);
        $this->assertSame('patch-managed', $r['status']);
        $this->assertSame('update-managed-block', $r['action'], 'patch-managed updates only the marker block, never user content outside it');
    }

    public function testResolveFileActionOwnedUserModifiedConflicts(): void
    {
        $r = aiUpgradeResolveFileAction('owned', true, false, false, false);
        $this->assertSame('owned-user-modified', $r['status']);
        $this->assertSame('conflict-preserve-user', $r['action']);

        $both = aiUpgradeResolveFileAction('owned', true, true, false, false);
        $this->assertSame('owned-both-changed', $both['status']);
        $this->assertSame('conflict-preserve-user', $both['action']);
    }

    public function testResolveFileActionOwnedForceOverwritesInPlace(): void
    {
        // --force-owned: user-modified owned file is preserved to conflicts then overwritten.
        $r = aiUpgradeResolveFileAction('owned', true, true, false, true);
        $this->assertSame('owned-force-overwrite', $r['status']);
        $this->assertSame('force-overwrite', $r['action'], '--force-owned overwrites in place after preserving user bytes');
    }

    public function testResolveFileActionOwnedSourceUpdatedAutoUpdates(): void
    {
        $r = aiUpgradeResolveFileAction('owned', false, true, false, false);
        $this->assertSame('source-updated', $r['status']);
        $this->assertSame('auto-update', $r['action']);
    }

    public function testResolveFileActionUnchangedSkips(): void
    {
        $r = aiUpgradeResolveFileAction('owned', false, false, false, false);
        $this->assertSame('unchanged', $r['status']);
        $this->assertSame('skip', $r['action']);
    }

    // ---- P0-b Slice 4: apply-path deprecated removal + route-to-removed ----

    public function testRemoveDeprecatedDeletesUnchangedAndRoutesModified(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'upgrade_remove_dep_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai', 0700, true);

        $unchanged = 'docs/ai/stale-hook.md';
        $modified = 'docs/ai/stale-policy.md';
        file_put_contents($root . DIRECTORY_SEPARATOR . $unchanged, "# stale\n");
        file_put_contents($root . DIRECTORY_SEPARATOR . $modified, "# user edited stale\n");

        $deprecated = [
            ['file' => $unchanged, 'ownership' => 'deprecated', 'status' => 'deprecated-unchanged', 'action' => 'delete'],
            ['file' => $modified, 'ownership' => 'deprecated', 'status' => 'deprecated-user-modified', 'action' => 'route-to-removed'],
        ];

        $result = aiUpgradeRemoveDeprecated($root, $deprecated);

        // Unchanged deprecated file is deleted (already in backup).
        $this->assertFileDoesNotExist($root . DIRECTORY_SEPARATOR . $unchanged);
        // User-modified deprecated file is removed from its place but its bytes are routed.
        $this->assertFileDoesNotExist($root . DIRECTORY_SEPARATOR . $modified);

        $routed = null;
        foreach ($result as $entry) {
            if (($entry['file'] ?? '') === $modified) {
                $routed = $entry;
            }
        }
        $this->assertNotNull($routed, 'modified deprecated file must be reported as routed');
        $this->assertArrayHasKey('routed_to', $routed);
        $this->assertStringStartsWith('.ai/conflicts/', $routed['routed_to']);
        $this->assertStringContainsString('/removed/', $routed['routed_to']);

        $routedAbs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $routed['routed_to']);
        $this->assertFileExists($routedAbs);
        $this->assertSame("# user edited stale\n", (string) file_get_contents($routedAbs));
    }

    public function testRemoveDeprecatedIgnoresAlreadyMissingFiles(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'upgrade_remove_dep_missing_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root, 0700, true);

        $result = aiUpgradeRemoveDeprecated($root, [
            ['file' => 'docs/ai/gone.md', 'action' => 'delete'],
        ]);

        $this->assertSame([], $result, 'a deprecated file already gone needs no removal');
        $this->assertDirectoryDoesNotExist($root . DIRECTORY_SEPARATOR . '.ai');
    }

    public function testRemoveDeprecatedDoesNothingForEmptyList(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'upgrade_remove_dep_empty_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root, 0700, true);

        $this->assertSame([], aiUpgradeRemoveDeprecated($root, []));
        $this->assertDirectoryDoesNotExist($root . DIRECTORY_SEPARATOR . '.ai');
    }
}
