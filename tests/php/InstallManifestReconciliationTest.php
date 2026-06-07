<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Regression guard for the manifest reconciliation fix.
 *
 * The installer writes `.ai-install-manifest.json` from two code paths:
 *  - the subprocess installer (manifest.php) emits the canonical rich `files{}` map;
 *  - the orchestrator (install_workflow.php) augments it with workflow-level metadata.
 *
 * Before the fix, the orchestrator overwrote the canonical manifest with a flat
 * `managed_paths` list, silently dropping the per-file `files{}` map on the
 * `ai.php install --apply` path. These tests pin the corrected behaviour:
 * `aiInstallerMergeWorkflowManifest()` MUST preserve `files{}`.
 */
final class InstallManifestReconciliationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/tools/ai/commands/install_workflow.php';
    }

    public function testMergePreservesCanonicalFilesMap(): void
    {
        $this->assertTrue(
            function_exists('aiInstallerMergeWorkflowManifest'),
            'reconciliation helper must exist'
        );

        $canonical = [
            'schema_version' => 1,
            'installer_version' => '0.2.0',
            'installed_at' => '2026-01-01T00:00:00+00:00',
            'profile' => 'opencode',
            'package' => ['name' => 'ai-universal-rules'],
            'packs' => ['adapter-opencode'],
            'files' => [
                '.opencode/agents/architect.md' => [
                    'pack' => 'adapter-opencode',
                    'source' => 'templates/core/agents/architect.md',
                    'source_hash' => 'sha256:aaa',
                    'installed_hash' => 'sha256:bbb',
                    'managed' => true,
                    'merge_strategy' => 'skip-if-exists',
                    'required' => true,
                ],
            ],
            'pending_configuration' => ['Fill docs/ai/project-context.md'],
        ];

        $workflowFields = [
            'schema_version' => 1,
            'installer_version' => '0.2.0',
            'installed_at' => '2026-01-01T00:00:00+00:00',
            'updated_at' => '2026-01-02T00:00:00+00:00',
            'profile' => 'opencode',
            'mode' => 'dual',
            'runtime' => 'opencode',
            'package' => ['name' => 'ai-universal-rules'],
            'packs' => ['adapter-opencode'],
            'toolchain' => ['checked' => true],
            'post_install_script' => ['executed' => false],
            'package_lock_sha256' => 'sha256:ccc',
        ];

        $merged = aiInstallerMergeWorkflowManifest($canonical, $workflowFields, [
            '.opencode/agents/architect.md',
        ]);

        // files{} must survive intact with full per-file metadata.
        $this->assertArrayHasKey('files', $merged, 'merged manifest must keep files{}');
        $this->assertArrayHasKey('.opencode/agents/architect.md', $merged['files']);
        $this->assertSame(
            'skip-if-exists',
            $merged['files']['.opencode/agents/architect.md']['merge_strategy'],
            'per-file merge_strategy must be preserved'
        );
        $this->assertTrue($merged['files']['.opencode/agents/architect.md']['managed']);

        // Workflow-level fields must be layered on.
        $this->assertSame('dual', $merged['mode']);
        $this->assertSame('opencode', $merged['runtime']);
        $this->assertSame('sha256:ccc', $merged['package_lock_sha256']);

        // managed_paths is a derived convenience list, not a replacement for files{}.
        $this->assertSame(['.opencode/agents/architect.md'], $merged['managed_paths']);
    }

    public function testMergeSynthesisesFilesWhenCanonicalMissing(): void
    {
        $merged = aiInstallerMergeWorkflowManifest([], [
            'mode' => 'dual',
            'runtime' => 'opencode',
        ], ['AGENTS.md', 'opencode.jsonc', '']);

        $this->assertArrayHasKey('files', $merged);
        $this->assertArrayHasKey('AGENTS.md', $merged['files']);
        $this->assertArrayHasKey('opencode.jsonc', $merged['files']);
        $this->assertTrue($merged['files']['AGENTS.md']['managed']);

        // Empty path entries are filtered out of the derived managed_paths list and files{}.
        $this->assertArrayNotHasKey('', $merged['files']);
        $this->assertSame(['AGENTS.md', 'opencode.jsonc'], $merged['managed_paths']);
    }

    public function testMergeNeverLetsWorkflowLayerReplaceFiles(): void
    {
        $canonical = [
            'files' => ['real/file.md' => ['managed' => true, 'merge_strategy' => 'replace']],
        ];

        // A hostile/buggy workflow field set that tries to clobber files{} must not win.
        $merged = aiInstallerMergeWorkflowManifest($canonical, [
            'files' => ['fake/override.md' => ['managed' => false]],
            'mode' => 'dual',
        ], ['real/file.md']);

        $this->assertArrayHasKey('real/file.md', $merged['files']);
        $this->assertArrayNotHasKey('fake/override.md', $merged['files'], 'workflow layer must not replace files{}');
    }
}
