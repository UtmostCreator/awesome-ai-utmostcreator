<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * P4-a: project-fact placeholder tokens read from .ai/project.yml.
 *
 * Customizable project-fact tokens (commands, dirs, package manager, review
 * priorities, etc.) are sourced from .ai/project.yml so every re-render reuses the
 * same values with zero re-customization. Kit-constant tokens are not affected.
 */
final class ProjectValuesPlaceholderTest extends TestCase
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

    public function testProjectFactTokensComeFromProjectYml(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'p4a_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        $this->writeProjectYml($root, [
            'testCommand' => 'composer test',
            'buildCommand' => 'composer build',
            'lintCommand' => 'composer lint',
            'packageManager' => 'composer',
            'sourceDirs' => 'src',
            'testDirs' => 'tests',
            'targetPlatforms' => 'linux',
            'protectedPaths' => 'vendor',
            'reviewPriorities' => 'security first',
            'riskAreas' => 'auth, billing',
        ]);

        $values = aiInstallerLoadProjectValues($root, 'demo');
        $map = aiInstallerProjectValuesPlaceholderMap($values);

        $this->assertSame('composer test', $map['<TEST_COMMAND>'] ?? null);
        $this->assertSame('composer build', $map['<BUILD_COMMAND>'] ?? null);
        $this->assertSame('composer lint', $map['<LINT_COMMAND>'] ?? null);
        $this->assertSame('composer', $map['<PACKAGE_MANAGER>'] ?? null);
        $this->assertSame('src', $map['<SOURCE_DIRS>'] ?? null);
        $this->assertSame('tests', $map['<TEST_DIRS>'] ?? null);
        $this->assertSame('linux', $map['<TARGET_PLATFORMS>'] ?? null);
        $this->assertSame('vendor', $map['<PROTECTED_PATHS>'] ?? null);
        $this->assertSame('security first', $map['<REVIEW_PRIORITIES>'] ?? null);
        $this->assertSame('auth, billing', $map['<RISK_AREAS>'] ?? null);
    }

    public function testRerenderReusesProjectYmlValuesWithZeroRecustomization(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'p4a_rerender_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        $this->writeProjectYml($root, ['testCommand' => 'phpunit', 'packageManager' => 'composer']);

        // Two independent loads (simulating install then re-render) must yield identical maps.
        $first = aiInstallerProjectValuesPlaceholderMap(aiInstallerLoadProjectValues($root, 'demo'));
        $second = aiInstallerProjectValuesPlaceholderMap(aiInstallerLoadProjectValues($root, 'demo'));

        $this->assertSame($first, $second, 're-render must reuse project.yml values deterministically');
        $this->assertSame('phpunit', $second['<TEST_COMMAND>'] ?? null);
    }

    public function testUnsetProjectFactTokensDoNotClobberBaseDefaults(): void
    {
        // A set token is emitted; an unset project-fact token is omitted entirely so the richer
        // base-map default (e.g. for review priorities) survives instead of being forced to unknown.
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'p4a_unset_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        $this->writeProjectYml($root, ['testCommand' => 'phpunit']);

        $values = aiInstallerLoadProjectValues($root, 'demo');
        $map = aiInstallerProjectValuesPlaceholderMap($values);

        $this->assertSame('phpunit', $map['<TEST_COMMAND>'] ?? null);
        $this->assertArrayNotHasKey('<LINT_COMMAND>', $map, 'unset project-fact tokens are omitted so base defaults survive');
        $this->assertArrayNotHasKey('<REVIEW_PRIORITIES>', $map, 'unset review priorities must not clobber the base default');
    }
}
