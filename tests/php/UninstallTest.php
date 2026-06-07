<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Phase 3b: uninstall.
 *
 * Uninstall removes owned/rendered files but preserves template files and .ai/ state
 * unless --purge. Dry-run writes nothing. Tests build a synthetic manifest + files so they
 * are fast and do not depend on the full installer toolchain.
 */
final class UninstallTest extends TestCase
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
        require_once $root . '/tools/ai/commands/install_paths.php';
        require_once $root . '/tools/ai/install/core.php';
        require_once $root . '/tools/ai/commands/install_workflow.php';
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpDirs as $dir) {
            aiInstallerDeleteTree($dir);
        }
        $this->tmpDirs = [];
    }

    private function makeInstall(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uninstall_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'generated', 0700, true);

        // owned file, rendered file, template file.
        file_put_contents($root . DIRECTORY_SEPARATOR . 'AGENTS.md', "# agents\n");
        file_put_contents($root . DIRECTORY_SEPARATOR . 'docs/ai/workflow.md', "# workflow\n");
        file_put_contents($root . DIRECTORY_SEPARATOR . 'docs/ai/project-stack.md', "# user stack\n");
        mkdir($root . DIRECTORY_SEPARATOR . '.ai', 0700, true);
        file_put_contents($root . DIRECTORY_SEPARATOR . '.ai/project.yml', "stack: php\n");

        $manifest = [
            'schema_version' => 1,
            'installer_version' => '0.2.0',
            'profile' => 'opencode',
            'packs' => ['base'],
            'files' => [
                'AGENTS.md' => ['managed' => true, 'ownership' => 'owned'],
                'docs/ai/workflow.md' => ['managed' => true, 'ownership' => 'rendered'],
                'docs/ai/project-stack.md' => ['managed' => true, 'ownership' => 'template'],
            ],
        ];
        file_put_contents(
            $root . DIRECTORY_SEPARATOR . '.ai-install-manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $root;
    }

    /** @return array<string,mixed> */
    private function readUninstallArtifact(string $root): array
    {
        $decoded = json_decode((string) file_get_contents($root . '/docs/ai/generated/uninstall.json'), true);
        return is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
    }

    public function testDryRunWritesNothing(): void
    {
        $root = $this->makeInstall();
        ob_start();
        $exit = aiRunUninstallWorkflow($root, []);
        ob_end_clean();

        $this->assertSame(0, $exit);
        $data = $this->readUninstallArtifact($root);
        $this->assertSame('dry-run', $data['mode']);
        // Everything still on disk.
        $this->assertFileExists($root . '/AGENTS.md');
        $this->assertFileExists($root . '/docs/ai/workflow.md');
        $this->assertFileExists($root . '/docs/ai/project-stack.md');
        $this->assertFileExists($root . '/.ai-install-manifest.json');
        // Planned removals exclude the template file.
        $this->assertContains('AGENTS.md', $data['planned_removals']);
        $this->assertContains('docs/ai/workflow.md', $data['planned_removals']);
        $this->assertNotContains('docs/ai/project-stack.md', $data['planned_removals']);
    }

    public function testApplyRemovesOwnedAndRenderedButPreservesTemplate(): void
    {
        $root = $this->makeInstall();
        ob_start();
        $exit = aiRunUninstallWorkflow($root, ['--apply']);
        ob_end_clean();

        $this->assertSame(0, $exit);
        $this->assertFileDoesNotExist($root . '/AGENTS.md', 'owned file removed');
        $this->assertFileDoesNotExist($root . '/docs/ai/workflow.md', 'rendered file removed');
        $this->assertFileExists($root . '/docs/ai/project-stack.md', 'template file preserved');
        $this->assertFileExists($root . '/.ai/project.yml', 'project.yml preserved');
        $this->assertFileDoesNotExist($root . '/.ai-install-manifest.json', 'manifest removed');
    }

    public function testPurgeRemovesTemplateAndAiState(): void
    {
        $root = $this->makeInstall();
        ob_start();
        aiRunUninstallWorkflow($root, ['--apply', '--purge']);
        ob_end_clean();

        $this->assertFileDoesNotExist($root . '/docs/ai/project-stack.md', 'template removed with --purge');
        $this->assertDirectoryDoesNotExist($root . '/.ai', '.ai state removed with --purge');
    }

    public function testUninstallWithoutManifestIsBlocked(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uninstall_none_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'generated', 0700, true);

        ob_start();
        $exit = aiRunUninstallWorkflow($root, ['--apply']);
        ob_end_clean();

        $this->assertSame(1, $exit, 'uninstall without a manifest must be blocked');
    }
}
