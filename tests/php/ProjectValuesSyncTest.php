<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * project-values-sync: one-click propagation of resolved `.ai/project.yml` facts
 * into already-installed rendered files, closing the gap where install/upgrade
 * --dry-run take no action on value-only project.yml edits and
 * `placeholders --apply` can no longer see already-substituted tokens.
 *
 * @see docs/tickets/arch-todo-speckit-comparison-adoption-20260704-223159/plan.md (S1 follow-up)
 */
final class ProjectValuesSyncTest extends TestCase
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
        require_once $root . '/tools/ai/install/markers.php';
        require_once $root . '/tools/ai/install/core.php';
        require_once $root . '/tools/ai/commands/project_values_sync.php';
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

    private function makeTmpRoot(string $prefix): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . uniqid('', true);
        mkdir($root, 0700, true);
        $this->tmpDirs[] = $root;
        return $root;
    }

    private function writeProjectYml(string $targetRoot, array $kv): void
    {
        mkdir($targetRoot . DIRECTORY_SEPARATOR . '.ai', 0700, true);
        $lines = ['schemaVersion: 1'];
        foreach ($kv as $k => $v) {
            $lines[] = $k . ': ' . $v;
        }
        file_put_contents(
            $targetRoot . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'project.yml',
            implode("\n", $lines) . "\n"
        );
    }

    private function writeFile(string $root, string $rel, string $content): void
    {
        $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        @mkdir(dirname($abs), 0700, true);
        file_put_contents($abs, $content);
    }

    public function testCheckModeDetectsStaleValuesWithoutWriting(): void
    {
        $root = $this->makeTmpRoot('pvs_check_');
        $this->writeProjectYml($root, [
            'primaryLanguage' => 'PHP',
            'primaryRuntime' => 'PHP >=8.2',
        ]);
        $this->writeFile($root, 'AGENTS.md', "# Repo\n\n- Primary language: `unknown`\n- Primary runtime: `unknown`\n");
        $before = file_get_contents($root . '/AGENTS.md');

        $result = aiProjectValuesSyncScan($root, false);

        $this->assertCount(2, $result['mismatches']);
        $this->assertSame('primaryLanguage', $result['mismatches'][0]['field']);
        $this->assertSame('unknown', $result['mismatches'][0]['current']);
        $this->assertSame('PHP', $result['mismatches'][0]['expected']);
        $this->assertSame([], $result['files_changed'], 'check mode must not write');
        $this->assertSame($before, file_get_contents($root . '/AGENTS.md'), 'file content must be untouched in check mode');
    }

    public function testApplyModeRewritesOnlyTheMatchingLines(): void
    {
        $root = $this->makeTmpRoot('pvs_apply_');
        $this->writeProjectYml($root, [
            'primaryLanguage' => 'PHP',
            'primaryRuntime' => 'PHP >=8.2',
            'primaryVerifyCommand' => 'composer test',
        ]);
        $this->writeFile(
            $root,
            'docs/ai/project-context.md',
            "# Context\n\n- Primary language: `unknown`\n- Primary runtime: `unknown`\n- Some unrelated line: `keep me`\n\n## 8) Verification\n\n- Main verification command: `unknown`\n"
        );

        $result = aiProjectValuesSyncScan($root, true);

        $this->assertTrue($result['applied']);
        $this->assertSame(['docs/ai/project-context.md'], $result['files_changed']);
        $written = file_get_contents($root . '/docs/ai/project-context.md');
        $this->assertStringContainsString('- Primary language: `PHP`', $written);
        $this->assertStringContainsString('- Primary runtime: `PHP >=8.2`', $written);
        $this->assertStringContainsString('- Main verification command: `composer test`', $written);
        $this->assertStringContainsString('- Some unrelated line: `keep me`', $written, 'unrelated lines must be preserved untouched');
    }

    public function testApplyIsIdempotent(): void
    {
        $root = $this->makeTmpRoot('pvs_idempotent_');
        $this->writeProjectYml($root, ['primaryLanguage' => 'PHP']);
        $this->writeFile($root, 'AGENTS.md', "- Primary language: `unknown`\n");

        $first = aiProjectValuesSyncScan($root, true);
        $this->assertSame(['AGENTS.md'], $first['files_changed']);

        $second = aiProjectValuesSyncScan($root, true);
        $this->assertSame([], $second['files_changed'], 'second apply run must be a no-op once values match');
        $this->assertSame([], $second['mismatches']);
    }

    public function testUnresolvedTemplateTokensAreNeverOverwritten(): void
    {
        $root = $this->makeTmpRoot('pvs_template_guard_');
        $this->writeProjectYml($root, ['primaryLanguage' => 'PHP']);
        // Simulates an unrendered template/placeholder-guide file that happens to sit
        // inside a scan root (e.g. docs/ai/project-context-placeholders.md-style content
        // living elsewhere) — a literal <TOKEN> value must never be clobbered.
        $this->writeFile($root, 'docs/ai/some-guide.md', "- Primary language: `<PRIMARY_LANGUAGE>`\n");

        $result = aiProjectValuesSyncScan($root, true);

        $this->assertSame([], $result['mismatches'], 'unresolved <TOKEN> lines must be skipped, not flagged or rewritten');
        $this->assertSame(
            "- Primary language: `<PRIMARY_LANGUAGE>`\n",
            file_get_contents($root . '/docs/ai/some-guide.md')
        );
    }

    public function testUnsetOrUnknownProjectValuesAreNotForceSynced(): void
    {
        $root = $this->makeTmpRoot('pvs_unset_');
        // primaryLanguage left unset entirely; primaryRuntime explicitly 'unknown'.
        $this->writeProjectYml($root, ['primaryVerifyCommand' => 'composer test']);
        $this->writeFile($root, 'AGENTS.md', "- Primary language: `unknown`\n- Primary runtime: `unknown`\n- Primary verification command: `unknown`\n");

        $result = aiProjectValuesSyncScan($root, true);

        $this->assertSame(['AGENTS.md'], $result['files_changed']);
        $written = file_get_contents($root . '/AGENTS.md');
        $this->assertStringContainsString('- Primary language: `unknown`', $written, 'unset field must not be force-synced');
        $this->assertStringContainsString('- Primary runtime: `unknown`', $written, 'unset field must not be force-synced');
        $this->assertStringContainsString('- Primary verification command: `composer test`', $written, 'set field must sync');
    }

    public function testPackagesTemplateDirectoryIsStructurallyOutOfScanScope(): void
    {
        // Documents the safety invariant relied on by aiProjectValuesSyncScanRoots():
        // 'packages' is not one of the scan roots, so files under
        // packages/ai-universal-rules/templates/** can never be touched by this
        // command regardless of content, keeping template <TOKEN> sources intact
        // for every other project that installs this kit.
        $this->assertNotContains('packages', aiProjectValuesSyncScanRoots());
        $this->assertSame(['AGENTS.md', 'docs/ai', '.github', '.opencode'], aiProjectValuesSyncScanRoots());
    }

    /**
     * Review finding (medium): a CRLF-terminated line was silently skipped in both
     * check and apply mode (str_ends_with($line, '`') is false when a trailing "\r"
     * remains after exploding on "\n" alone). Fixed by matching with the trailing
     * "\r" stripped and re-appending it on write, preserving the original ending.
     */
    public function testCrlfTerminatedLinesAreDetectedAndFixedPreservingLineEnding(): void
    {
        $root = $this->makeTmpRoot('pvs_crlf_');
        $this->writeProjectYml($root, ['primaryLanguage' => 'PHP']);
        $this->writeFile($root, 'AGENTS.md', "# Repo\r\n\r\n- Primary language: `unknown`\r\n- Unrelated: `keep`\r\n");

        $checked = aiProjectValuesSyncScan($root, false);
        $this->assertCount(1, $checked['mismatches'], 'CRLF-terminated mismatch must be detected in check mode');
        $this->assertSame('unknown', $checked['mismatches'][0]['current']);

        $applied = aiProjectValuesSyncScan($root, true);
        $this->assertSame(['AGENTS.md'], $applied['files_changed']);
        $written = file_get_contents($root . '/AGENTS.md');
        $this->assertStringContainsString("- Primary language: `PHP`\r\n", $written, 'fixed line must keep its original CRLF ending');
        $this->assertStringContainsString("- Unrelated: `keep`\r\n", $written, 'unrelated CRLF lines must be preserved untouched');
    }

    /**
     * Review finding (low): no test proved multiple mismatching fields in the same
     * file are all synced (no early-exit / only-first-match bug).
     */
    public function testMultipleMismatchesInOneFileAreAllSynced(): void
    {
        $root = $this->makeTmpRoot('pvs_multi_');
        $this->writeProjectYml($root, [
            'primaryLanguage' => 'PHP',
            'primaryRuntime' => 'PHP >=8.2',
            'primaryVerifyCommand' => 'composer test',
            'primaryBuildCommand' => 'none',
            'primaryTestCommand' => 'composer test',
            'packageManager' => 'composer',
        ]);
        $this->writeFile($root, 'AGENTS.md', implode("\n", [
            '- Primary language: `unknown`',
            '- Primary runtime: `unknown`',
            '- Primary verification command: `unknown`',
            '- Primary build command: `unknown`',
            '- Primary test command: `unknown`',
            '- Package manager: `unknown`',
            '',
        ]));

        $result = aiProjectValuesSyncScan($root, true);

        $this->assertCount(6, $result['mismatches'], 'all six mismatching fields in the same file must be reported');
        $written = file_get_contents($root . '/AGENTS.md');
        $this->assertStringContainsString('- Primary language: `PHP`', $written);
        $this->assertStringContainsString('- Primary runtime: `PHP >=8.2`', $written);
        $this->assertStringContainsString('- Primary verification command: `composer test`', $written);
        $this->assertStringContainsString('- Primary build command: `none`', $written);
        $this->assertStringContainsString('- Primary test command: `composer test`', $written);
        $this->assertStringContainsString('- Package manager: `composer`', $written);
    }

    /**
     * Review finding (low): the CLI-facing aiRunProjectValuesSync() --fail exit-code
     * contract and artifact envelope were only verified by code inspection.
     */
    public function testRunProjectValuesSyncFailFlagReturnsExitOneOnMismatch(): void
    {
        $root = $this->makeTmpRoot('pvs_cli_fail_');
        mkdir($root . '/docs/ai/generated', 0777, true);
        $this->writeProjectYml($root, ['primaryLanguage' => 'PHP']);
        $this->writeFile($root, 'AGENTS.md', "- Primary language: `unknown`\n");

        $exit = aiRunProjectValuesSync($root, ['--fail']);

        $this->assertSame(1, $exit, '--fail must exit non-zero when a mismatch exists and --apply was not passed');
        $artifact = $root . '/docs/ai/generated/project-values-sync.json';
        $this->assertFileExists($artifact);
        $decoded = json_decode((string) file_get_contents($artifact), true);
        $this->assertSame('failed', $decoded['status'] ?? null);
        $this->assertSame('primaryLanguage', $decoded['data']['mismatches'][0]['field'] ?? null);
    }

    public function testRunProjectValuesSyncApplyReturnsExitZeroAndWritesArtifact(): void
    {
        $root = $this->makeTmpRoot('pvs_cli_apply_');
        mkdir($root . '/docs/ai/generated', 0777, true);
        $this->writeProjectYml($root, ['primaryLanguage' => 'PHP']);
        $this->writeFile($root, 'AGENTS.md', "- Primary language: `unknown`\n");

        $exit = aiRunProjectValuesSync($root, ['--apply']);

        $this->assertSame(0, $exit, '--apply must exit zero after successfully applying changes');
        $decoded = json_decode((string) file_get_contents($root . '/docs/ai/generated/project-values-sync.json'), true);
        $this->assertSame('ok', $decoded['status'] ?? null);
        $this->assertSame(['AGENTS.md'], $decoded['data']['files_changed'] ?? null);
        $this->assertStringContainsString('- Primary language: `PHP`', (string) file_get_contents($root . '/AGENTS.md'));

        // Idempotency at the CLI layer too: a second --fail run must now pass (exit 0).
        $secondExit = aiRunProjectValuesSync($root, ['--fail']);
        $this->assertSame(0, $secondExit, 'no remaining mismatches after apply, so --fail must exit zero');
    }
}
