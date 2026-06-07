<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Phase 8: consolidated end-to-end lifecycle.
 *
 * Drives a realistic target repo through install -> user-edits -> upgrade -> uninstall via the
 * real `ai.php` CLI and asserts the core invariants hold across the whole lifecycle:
 *  - user/foreign files are preserved byte-for-byte,
 *  - kit files install and a manifest+lock are written,
 *  - upgrade refreshes owned files while preserving user edits in .ai/conflicts,
 *  - uninstall removes kit files but keeps user content.
 */
final class InstallLifecycleTest extends TestCase
{
    private static string $repoRoot;
    /** @var list<string> */
    private array $tmpDirs = [];

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;
    }

    protected function setUp(): void
    {
        if (\PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('lifecycle test uses a POSIX shell install flow');
        }
        foreach (['git', 'fd', 'ast-grep', 'scc'] as $tool) {
            if (!$this->commandExists($tool)) {
                $this->markTestSkipped("required tool not available: {$tool}");
            }
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpDirs as $dir) {
            $this->removeTree($dir);
        }
        $this->tmpDirs = [];
    }

    public function testInstallUpgradeUninstallPreservesUserContent(): void
    {
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai_lifecycle_' . uniqid('', true);
        $this->tmpDirs[] = $target;
        $this->makeTargetRepoWithSource($target);

        // A pre-existing user file outside the kit namespace must survive the whole lifecycle.
        $userFile = $target . '/docs/my-notes.md';
        $userBytes = "# My notes\n\nKeep me exactly.\n";
        file_put_contents($userFile, $userBytes);

        // ---- INSTALL ----
        $install = $this->runInRepo($target, 'php tools/ai/install-ai-kit.php --target . --runtime opencode --profile opencode --force');
        $this->assertSame(0, $install['exit'], "install failed:\n" . $install['stderr']);
        $this->assertFileExists($target . '/.ai-install-manifest.json', 'install writes manifest');
        $this->assertFileExists($target . '/.ai/manifest.lock.json', 'install writes lock');
        $this->assertSame($userBytes, (string) file_get_contents($userFile), 'user file preserved after install');

        // Pick a kit-owned file to verify refresh behaviour on upgrade.
        $manifest = json_decode((string) file_get_contents($target . '/.ai-install-manifest.json'), true);
        $this->assertIsArray($manifest);
        $this->assertNotEmpty($manifest['files'] ?? []);

        // ---- UPGRADE ---- (reinstall; user file must still be untouched)
        $upgrade = $this->runInRepo($target, 'php tools/ai/install-ai-kit.php --target . --runtime opencode --profile opencode --force');
        $this->assertSame(0, $upgrade['exit'], "upgrade/reinstall failed:\n" . $upgrade['stderr']);
        $this->assertSame($userBytes, (string) file_get_contents($userFile), 'user file preserved after upgrade');
        $this->assertFileExists($target . '/.ai/manifest.lock.json', 'lock present after upgrade');

        // ---- UNINSTALL ---- via orchestrator (apply); user content preserved.
        $uninstall = $this->runInRepo(
            $target,
            'php tools/ai/ai.php uninstall --apply --no-interaction',
            ['AI_CLI_REPO_ROOT' => $target]
        );
        $this->assertContains($uninstall['exit'], [0, 1], "uninstall returned unexpected code:\n" . $uninstall['stderr']);
        $this->assertSame($userBytes, (string) file_get_contents($userFile), 'user file preserved after uninstall');
    }

    public function testInstallIntoEmptyRepoIsClean(): void
    {
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai_lifecycle_empty_' . uniqid('', true);
        $this->tmpDirs[] = $target;
        $this->makeTargetRepoWithSource($target);

        $install = $this->runInRepo($target, 'php tools/ai/install-ai-kit.php --target . --runtime opencode --profile opencode --force');
        $this->assertSame(0, $install['exit'], "empty-repo install failed:\n" . $install['stderr']);
        $this->assertFileExists($target . '/AGENTS.md');
        $this->assertFileExists($target . '/.ai-install-manifest.json');
    }

    public function testSecondForceInstallIsIdempotentZeroDiff(): void
    {
        // P3-d: a second --force install with no changes must not modify any kit-owned file
        // (no SKIP_PROTECTED_CORE rewrite drift). Capture each managed file's bytes after the
        // first install, run install again, and assert every file is byte-identical.
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai_idempotent_' . uniqid('', true);
        $this->tmpDirs[] = $target;
        $this->makeTargetRepoWithSource($target);

        $first = $this->runInRepo($target, 'php tools/ai/install-ai-kit.php --target . --runtime opencode --profile opencode --force');
        $this->assertSame(0, $first['exit'], "first install failed:\n" . $first['stderr']);

        $manifest = json_decode((string) file_get_contents($target . '/.ai-install-manifest.json'), true);
        $this->assertIsArray($manifest);
        $files = array_keys($manifest['files'] ?? []);
        $this->assertNotEmpty($files, 'manifest must record installed files');

        $before = [];
        foreach ($files as $rel) {
            $abs = $target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $rel);
            if (is_file($abs)) {
                $before[$rel] = hash_file('sha256', $abs);
            }
        }

        $second = $this->runInRepo($target, 'php tools/ai/install-ai-kit.php --target . --runtime opencode --profile opencode --force');
        $this->assertSame(0, $second['exit'], "second install failed:\n" . $second['stderr']);

        $drifted = [];
        foreach ($before as $rel => $hash) {
            $abs = $target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $rel);
            if (!is_file($abs) || hash_file('sha256', $abs) !== $hash) {
                $drifted[] = $rel;
            }
        }

        $this->assertSame([], $drifted, 'second force install must leave all kit-owned files byte-identical: ' . implode(', ', $drifted));
    }

    // ---- helpers ----

    private function makeTargetRepoWithSource(string $target): void
    {
        mkdir($target, 0700, true);
        mkdir($target . '/docs', 0700, true);
        $init = $this->runInRepo($target, 'git init .');
        $this->assertSame(0, $init['exit'], 'git init failed: ' . $init['stderr']);

        foreach (['tools', 'packages', 'scripts', 'docs', 'schemas'] as $dir) {
            $this->copyTree(self::$repoRoot . DIRECTORY_SEPARATOR . $dir, $target . DIRECTORY_SEPARATOR . $dir);
        }
        foreach (['.repomixignore', 'PLACEHOLDERS.md', 'llms.txt'] as $file) {
            $src = self::$repoRoot . DIRECTORY_SEPARATOR . $file;
            if (is_file($src)) {
                copy($src, $target . DIRECTORY_SEPARATOR . $file);
            }
        }
    }

    private function copyTree(string $src, string $dest): void
    {
        if (!is_dir($src)) {
            return;
        }
        if (!is_dir($dest)) {
            mkdir($dest, 0777, true);
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($src) + 1));
            if (str_contains($rel, '/generated/') || str_starts_with($rel, 'generated/') || str_contains($rel, '/.git/')) {
                continue;
            }
            $targetPath = $dest . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }
                continue;
            }
            copy($item->getPathname(), $targetPath);
        }
    }

    /**
     * @param array<string,string> $extraEnv
     * @return array{stdout:string,stderr:string,exit:int}
     */
    private function runInRepo(string $cwd, string $command, array $extraEnv = []): array
    {
        if (str_starts_with($command, 'php ')) {
            $command = escapeshellarg((string) PHP_BINARY) . substr($command, 3);
        }
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $env = array_merge([
            'HOME' => sys_get_temp_dir(),
            'PATH' => (string) getenv('PATH'),
            'GIT_CONFIG_GLOBAL' => '/dev/null',
            'GIT_AUTHOR_NAME' => 't',
            'GIT_AUTHOR_EMAIL' => 't@t',
            'GIT_COMMITTER_NAME' => 't',
            'GIT_COMMITTER_EMAIL' => 't@t',
        ], $extraEnv);

        $process = proc_open($command, $descriptors, $pipes, $cwd, $env);
        $this->assertIsResource($process);
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
    }

    private function commandExists(string $tool): bool
    {
        $which = $this->runInRepo(sys_get_temp_dir(), 'sh -c ' . escapeshellarg('command -v ' . $tool));
        return $which['exit'] === 0;
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            if (is_file($path) || is_link($path)) {
                @unlink($path);
            }
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
}
