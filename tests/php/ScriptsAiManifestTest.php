<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

class ScriptsAiManifestTest extends TestCase
{
    private static string $repoRoot;
    private static string $manifest;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }

        self::$repoRoot = $root;
        $manifestPath = $root . '/scripts/ai/MANIFEST.md';
        self::assertFileExists($manifestPath);
        self::$manifest = (string) file_get_contents($manifestPath);
    }

    public function testManifestMentionsEveryTopLevelShellScript(): void
    {
        $paths = glob(self::$repoRoot . '/scripts/ai/*.sh') ?: [];
        sort($paths);

        $this->assertNotEmpty($paths, 'expected top-level scripts/ai shell scripts');

        foreach ($paths as $path) {
            $relative = 'scripts/ai/' . basename($path);
            $this->assertStringContainsString(
                "`$relative`",
                self::$manifest,
                "manifest must classify top-level script '$relative'"
            );
        }
    }

    public function testManifestMentionsCurrentPrivateImplementationModules(): void
    {
        $paths = array_merge(
            glob(self::$repoRoot . '/scripts/ai/internal/lib/*.sh') ?: [],
            glob(self::$repoRoot . '/scripts/ai/internal/search/*.sh') ?: []
        );
        sort($paths);

        $this->assertNotEmpty($paths, 'expected current scripts/ai private implementation modules');

        foreach ($paths as $path) {
            $relative = substr($path, strlen(self::$repoRoot) + 1);
            $this->assertStringContainsString(
                "`$relative`",
                self::$manifest,
                "manifest must classify private module '$relative'"
            );
        }
    }

    public function testManifestDocumentsP2TargetPathMapping(): void
    {
        $this->assertStringContainsString(
            '## P2 target path mapping (document-only)',
            self::$manifest,
            'manifest must document the P2 target path mapping'
        );
    }

    public function testP4BinShimsAreDelegatingAliasesOfRootImpls(): void
    {
        // P4 (Option B): scripts/ai/bin/<role>/<name>.sh are additive delegating
        // shims that exec the canonical root impl at scripts/ai/<name>.sh. The
        // canonical implementation must stay at the root path; each shim must map
        // to a real root impl and must not itself become the implementation.
        $shims = glob(self::$repoRoot . '/scripts/ai/bin/*/*.sh') ?: [];

        if ($shims === []) {
            $this->markTestSkipped('no scripts/ai/bin shims present yet (P4 not started)');
        }

        foreach ($shims as $shim) {
            $name = basename($shim);
            $rootImpl = self::$repoRoot . '/scripts/ai/' . $name;
            $this->assertFileExists(
                $rootImpl,
                "bin shim '$name' must alias an existing root impl scripts/ai/$name"
            );

            $body = (string) file_get_contents($shim);
            $this->assertStringContainsString(
                'DELEGATING SHIM',
                $body,
                "bin shim '$name' must be marked as a delegating shim"
            );
            $this->assertStringContainsString(
                'exec bash "$_ai_impl"',
                $body,
                "bin shim '$name' must exec the canonical root implementation"
            );
        }
    }

    public function testP3aRelocatedAiSearchModulesIntoInternalSearch(): void
    {
        // P3a moved scripts/ai/ai-search/*.sh into scripts/ai/internal/search/.
        $this->assertDirectoryDoesNotExist(
            self::$repoRoot . '/scripts/ai/ai-search',
            'P3a must relocate the legacy scripts/ai/ai-search module directory'
        );

        $modules = glob(self::$repoRoot . '/scripts/ai/internal/search/*.sh') ?: [];
        $this->assertNotEmpty(
            $modules,
            'P3a must place ai-search modules under scripts/ai/internal/search/'
        );

        // The ai-search.sh facade must load modules from the relocated path.
        $facade = (string) file_get_contents(self::$repoRoot . '/scripts/ai/ai-search.sh');
        $this->assertStringContainsString(
            '/internal/search',
            $facade,
            'ai-search.sh must resolve its modules from scripts/ai/internal/search'
        );
    }
}
