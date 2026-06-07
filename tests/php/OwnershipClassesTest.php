<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Phase 1: file ownership classes.
 *
 * Every installed file carries an ownership class (owned/template/rendered) plus
 * component + runtimes, derived once in the manifest builder. These drive the
 * ownership-aware upgrade/uninstall behaviour added in later phases. The install
 * manifest schema (schemas/ai/ai-install-manifest.schema.json) pins the shape.
 */
final class OwnershipClassesTest extends TestCase
{
    private static string $repoRoot;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;
        require_once $root . '/tools/ai/install/manifest.php';
    }

    public function testReplaceFilesAreOwned(): void
    {
        $this->assertSame('owned', aiInstallerResolveOwnership([], 'replace'));
    }

    public function testSkipIfExistsFilesAreTemplate(): void
    {
        $this->assertSame('template', aiInstallerResolveOwnership([], 'skip-if-exists'));
    }

    public function testExplicitOwnershipOverridesRule(): void
    {
        $this->assertSame('rendered', aiInstallerResolveOwnership(['ownership' => 'rendered'], 'replace'));
        $this->assertSame('template', aiInstallerResolveOwnership(['ownership' => 'template'], 'replace'));
        // An invalid explicit value falls back to the derivation rule.
        $this->assertSame('owned', aiInstallerResolveOwnership(['ownership' => 'bogus'], 'replace'));
    }

    public function testRuntimeDerivation(): void
    {
        $this->assertSame(['github-copilot'], aiInstallerResolveRuntimes('adapter-copilot'));
        $this->assertSame(['opencode'], aiInstallerResolveRuntimes('adapter-opencode'));
        $this->assertSame(['both'], aiInstallerResolveRuntimes('base'));
        $this->assertSame(['both'], aiInstallerResolveRuntimes('setup-docs'));
    }

    public function testInstallManifestSchemaExistsAndIsValid(): void
    {
        $schemaPath = self::$repoRoot . '/schemas/ai/ai-install-manifest.schema.json';
        $this->assertFileExists($schemaPath);

        $schema = json_decode((string) file_get_contents($schemaPath), true);
        $this->assertIsArray($schema, 'schema must be valid JSON');
        $this->assertSame('AI Install Manifest', $schema['title'] ?? null);

        $fileProps = $schema['properties']['files']['additionalProperties']['properties']['ownership']['enum'] ?? null;
        $this->assertSame(['owned', 'template', 'rendered'], $fileProps, 'schema must pin the ownership enum');

        $required = $schema['properties']['files']['additionalProperties']['required'] ?? [];
        $this->assertContains('ownership', $required, 'every files{} entry must require ownership');
    }

    /**
     * Build a manifest from the real pack registry and assert every entry conforms to the
     * ownership contract pinned by the schema enum.
     */
    public function testBuiltManifestEntriesConformToOwnershipContract(): void
    {
        require_once self::$repoRoot . '/tools/ai/install/packs.php';
        require_once self::$repoRoot . '/tools/ai/install/planner.php';

        $config = [
            'sourceRoot' => self::$repoRoot,
            'targetRoot' => self::$repoRoot,
            'profile' => 'dual',
            'force' => false,
            'adopt' => false,
        ];
        $registry = aiInstallerPackRegistry();
        $packs = array_keys($registry);
        $plan = aiInstallerBuildPlan($config, $registry, $packs);

        $manifest = aiInstallerBuildManifest($config, $packs, $plan);
        $this->assertNotEmpty($manifest['files'], 'manifest should contain files');

        $validOwnership = ['owned', 'template', 'rendered'];
        $validRuntimes = ['github-copilot', 'opencode', 'both'];
        foreach ($manifest['files'] as $path => $meta) {
            $this->assertArrayHasKey('ownership', $meta, "missing ownership for {$path}");
            $this->assertContains($meta['ownership'], $validOwnership, "invalid ownership for {$path}");
            $this->assertArrayHasKey('component', $meta, "missing component for {$path}");
            $this->assertArrayHasKey('runtimes', $meta, "missing runtimes for {$path}");
            $this->assertIsArray($meta['runtimes']);
            foreach ($meta['runtimes'] as $runtime) {
                $this->assertContains($runtime, $validRuntimes, "invalid runtime for {$path}");
            }
        }
    }
}
