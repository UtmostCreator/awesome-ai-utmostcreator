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
        $this->assertSame(['github-copilot'], aiInstallerResolveRuntimes('optional-agents-copilot-pack'));
        $this->assertSame(['opencode'], aiInstallerResolveRuntimes('optional-agents-opencode-pack'));
        $this->assertSame(['both'], aiInstallerResolveRuntimes('base'));
        $this->assertSame(['both'], aiInstallerResolveRuntimes('setup-docs'));
        $this->assertSame(['both'], aiInstallerResolveRuntimes('future-copilot-opencode-pack'), 'runtime resolution must not infer from substrings in future pack names');
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

    public function testManifestLockSchemaExistsAndPinsRequiredFields(): void
    {
        $schemaPath = self::$repoRoot . '/schemas/ai/ai-manifest-lock.schema.json';
        $this->assertFileExists($schemaPath);

        $schema = json_decode((string) file_get_contents($schemaPath), true);
        $this->assertIsArray($schema, 'lock schema must be valid JSON');
        $this->assertSame('AI Manifest Lock', $schema['title'] ?? null);
        $this->assertContains('schemaVersion', $schema['required'] ?? []);
        $this->assertContains('createdDirs', $schema['required'] ?? []);
        $this->assertContains('entries', $schema['required'] ?? []);

        $entryRequired = $schema['properties']['entries']['items']['required'] ?? [];
        foreach (['path', 'ownership', 'component', 'runtimes', 'source', 'generator', 'sha256', 'mode', 'lineEnding', 'kitVersion', 'schemaVersion'] as $field) {
            $this->assertContains($field, $entryRequired, "lock entry must require {$field}");
        }
    }

    public function testManifestLockIncludesCreatedDirsAndEntryMetadata(): void
    {
        $manifest = [
            'schema_version' => 1,
            'installer_version' => '0.2.0',
            'package' => ['installed_version' => 'test-version'],
            'created_dirs' => ['.opencode/agents'],
            'files' => [
                'README.md' => [
                    'ownership' => 'owned',
                    'component' => 'setup-docs',
                    'runtimes' => ['both'],
                    'source' => 'README.md',
                    'installed_hash' => is_file(self::$repoRoot . '/README.md') ? 'sha256:' . hash_file('sha256', self::$repoRoot . '/README.md') : 'unknown',
                ],
            ],
        ];

        $lock = aiInstallerBuildManifestLock(self::$repoRoot, $manifest);
        $this->assertSame(1, $lock['schemaVersion']);
        $this->assertSame(['.opencode/agents'], $lock['createdDirs']);
        $this->assertSame('README.md', $lock['entries'][0]['path'] ?? null);
        $this->assertSame('owned', $lock['entries'][0]['ownership'] ?? null);
        $this->assertSame('setup-docs', $lock['entries'][0]['component'] ?? null);
        $this->assertSame(['both'], $lock['entries'][0]['runtimes'] ?? null);
        $this->assertSame('installer', $lock['entries'][0]['generator'] ?? null);
        $this->assertSame('test-version', $lock['entries'][0]['kitVersion'] ?? null);
        $this->assertSame(1, $lock['entries'][0]['schemaVersion'] ?? null);
    }

    public function testNewerManifestLockIsRejected(): void
    {
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai_lock_forward_' . uniqid('', true);
        mkdir($target . DIRECTORY_SEPARATOR . '.ai', 0700, true);
        file_put_contents($target . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'manifest.lock.json', json_encode([
            'schemaVersion' => AI_INSTALLER_LOCK_SCHEMA_VERSION + 1,
            'createdDirs' => [],
            'entries' => [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('newer than this CLI supports');
            aiInstallerAssertLockCompatible($target);
        } finally {
            @unlink($target . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'manifest.lock.json');
            @rmdir($target . DIRECTORY_SEPARATOR . '.ai');
            @rmdir($target);
        }
    }

    public function testBuildManifestPreservesExistingManagedEntriesForSkippedReinstallPaths(): void
    {
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai_manifest_preserve_' . uniqid('', true);
        mkdir($target, 0700, true);
        file_put_contents($target . DIRECTORY_SEPARATOR . '.ai-install-manifest.json', json_encode([
            'schema_version' => 1,
            'installer_version' => '0.2.0',
            'profile' => 'dual',
            'packs' => ['base'],
            'files' => [
                'AGENTS.md' => [
                    'managed' => true,
                    'ownership' => 'owned',
                    'installed_hash' => 'sha256:old',
                    'source_hash' => 'sha256:old-source',
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        try {
            $manifest = aiInstallerBuildManifest([
                'sourceRoot' => self::$repoRoot,
                'targetRoot' => $target,
                'profile' => 'dual',
            ], ['base'], [[
                'type' => 'file',
                'target' => 'AGENTS.md',
                'source' => 'packages/ai-universal-rules/templates/core/AGENTS.template.md',
                'action' => 'SKIP_PROTECTED_CORE',
            ]]);

            $this->assertArrayHasKey('AGENTS.md', $manifest['files']);
            $this->assertSame('sha256:old', $manifest['files']['AGENTS.md']['installed_hash'] ?? null);
            $this->assertSame('owned', $manifest['files']['AGENTS.md']['ownership'] ?? null);
        } finally {
            @unlink($target . DIRECTORY_SEPARATOR . '.ai-install-manifest.json');
            @rmdir($target);
        }
    }

    public function testMigrationsAreDiscoveredInNaturalOrder(): void
    {
        require_once self::$repoRoot . '/tools/ai/install/migrations.php';

        $source = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai_migrations_' . uniqid('', true);
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai_migrations_target_' . uniqid('', true);
        mkdir($source . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '002-second', 0700, true);
        mkdir($source . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '001-first', 0700, true);
        mkdir($target, 0700, true);
        file_put_contents($source . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '001-first' . DIRECTORY_SEPARATOR . 'migrate.php', <<<'PHP'
<?php
return static function (string $targetRoot, string $sourceRoot): void {
    file_put_contents($targetRoot . DIRECTORY_SEPARATOR . 'migration-ran.txt', basename($sourceRoot));
};
PHP);

        try {
            $this->assertSame(['001-first', '002-second'], aiInstallerDiscoverMigrations($source));
            $result = aiInstallerRunMigrations($source, $target, 'unknown', 'unknown');
            $this->assertSame(1, $result['schemaVersion']);
            $this->assertSame('unknown', $result['fromVersion']);
            $this->assertSame('unknown', $result['targetVersion']);
            $this->assertSame(['001-first', '002-second'], $result['discovered']);
            $this->assertSame(['001-first'], $result['applied']);
            $this->assertSame(basename($source), (string) file_get_contents($target . DIRECTORY_SEPARATOR . 'migration-ran.txt'));
        } finally {
            @unlink($target . DIRECTORY_SEPARATOR . 'migration-ran.txt');
            @rmdir($target);
            @unlink($source . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '001-first' . DIRECTORY_SEPARATOR . 'migrate.php');
            @rmdir($source . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '002-second');
            @rmdir($source . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '001-first');
            @rmdir($source . DIRECTORY_SEPARATOR . 'migrations');
            @rmdir($source);
        }
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
