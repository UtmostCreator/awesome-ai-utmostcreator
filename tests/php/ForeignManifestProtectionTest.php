<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Foreign-file protection under --force.
 *
 * Regression guard for: `--force` overwriting an unrelated foreign file at a kit-claimed path
 * (e.g. a consumer project's own root manifest.json) with no recovery. The planner must only
 * overwrite under --force when it can prove kit ownership (identical to source, known prior-kit
 * checksum, or recorded in the install manifest); otherwise it surfaces CONFLICT_FOREIGN. Explicit
 * --adopt remains the opt-in to overwrite, and the apply path snapshots the displaced bytes.
 *
 * Also guards that the kit no longer ships collision-prone generic root filenames.
 */
final class ForeignManifestProtectionTest extends TestCase
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
        require_once $root . '/tools/ai/install/packs.php';
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpDirs as $dir) {
            $this->removeTree($dir);
        }
        $this->tmpDirs = [];
    }

    /** @return array{0:string,1:string} [sourceRoot, targetRoot] */
    private function makeRoots(string $label): array
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $label . '_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        $sourceRoot = $root . DIRECTORY_SEPARATOR . 'source';
        $targetRoot = $root . DIRECTORY_SEPARATOR . 'target';
        mkdir($sourceRoot, 0700, true);
        mkdir($targetRoot, 0700, true);

        return [$sourceRoot, $targetRoot];
    }

    /** @param array<string,mixed> $configOverride */
    private function planSingleFile(string $sourceRoot, string $targetRoot, array $configOverride): array
    {
        $config = array_merge([
            'sourceRoot' => $sourceRoot,
            'targetRoot' => $targetRoot,
            'force' => true,
            'adopt' => false,
            'allowCoreOverwrite' => false,
            'upgradeSuffix' => '',
            'knownKitChecksums' => [],
        ], $configOverride);

        return aiInstallerBuildPlan($config, [
            'pack' => [[
                'type' => 'file',
                'source' => 'manifest.json',
                'target' => 'manifest.json',
                'merge_strategy' => 'replace',
                'core' => false,
            ]],
        ], ['pack']);
    }

    public function testForceDoesNotOverwriteForeignFileOnManagedReinstall(): void
    {
        [$sourceRoot, $targetRoot] = $this->makeRoots('fm_foreign');
        // Kit source differs from a foreign file the user added at a kit-claimed path.
        file_put_contents($sourceRoot . '/manifest.json', "{\"kit\":true}\n");
        file_put_contents($targetRoot . '/manifest.json', "{\"name\":\"consumer-app\",\"version\":\"1.0.0\"}\n");
        // The kit already manages this target (prior install) but never recorded manifest.json:
        // it is genuinely foreign and --force must not clobber it.
        file_put_contents(
            $targetRoot . '/.ai-install-manifest.json',
            json_encode(['files' => ['AGENTS.md' => ['ownership' => 'owned']]]) . "\n"
        );

        $plan = $this->planSingleFile($sourceRoot, $targetRoot, ['force' => true, 'adopt' => false]);

        $this->assertSame(
            'CONFLICT_FOREIGN',
            $plan[0]['action'] ?? null,
            '--force on a kit-managed target must not silently overwrite an unrecorded foreign file'
        );
    }

    public function testFirstInstallForceOverwritesPreSeededFile(): void
    {
        [$sourceRoot, $targetRoot] = $this->makeRoots('fm_first');
        // No .ai-install-manifest.json: a true first install. --force keeps overwrite semantics.
        file_put_contents($sourceRoot . '/manifest.json', "{\"kit\":2}\n");
        file_put_contents($targetRoot . '/manifest.json', "{\"kit\":1}\n");

        $plan = $this->planSingleFile($sourceRoot, $targetRoot, ['force' => true, 'adopt' => false]);

        $this->assertSame(
            'OVERWRITE_MANAGED',
            $plan[0]['action'] ?? null,
            'first install --force overwrites (no prior ownership context); first-install collision is prevented by .ai/ namespacing'
        );
    }

    public function testForceRefreshesKitOwnedFileRecordedInManifest(): void
    {
        [$sourceRoot, $targetRoot] = $this->makeRoots('fm_owned');
        file_put_contents($sourceRoot . '/manifest.json', "{\"kit\":2}\n");
        file_put_contents($targetRoot . '/manifest.json', "{\"kit\":1}\n");
        // Prior install recorded this path as kit-managed.
        file_put_contents(
            $targetRoot . '/.ai-install-manifest.json',
            json_encode(['files' => ['manifest.json' => ['ownership' => 'owned']]]) . "\n"
        );

        $plan = $this->planSingleFile($sourceRoot, $targetRoot, ['force' => true, 'adopt' => false]);

        $this->assertSame(
            'OVERWRITE_MANAGED',
            $plan[0]['action'] ?? null,
            'a kit-owned file recorded in the manifest is refreshed under --force'
        );
    }

    public function testForceRefreshesFileMatchingKnownKitChecksum(): void
    {
        [$sourceRoot, $targetRoot] = $this->makeRoots('fm_known');
        file_put_contents($sourceRoot . '/manifest.json', "{\"kit\":2}\n");
        $oldKitBytes = "{\"kit\":1}\n";
        file_put_contents($targetRoot . '/manifest.json', $oldKitBytes);

        $plan = $this->planSingleFile($sourceRoot, $targetRoot, [
            'force' => true,
            'adopt' => false,
            'knownKitChecksums' => ['manifest.json' => ['sha256:' . hash('sha256', $oldKitBytes)]],
        ]);

        $this->assertSame(
            'OVERWRITE_MANAGED',
            $plan[0]['action'] ?? null,
            'a file matching a known prior-kit checksum is refreshed under --force'
        );
    }

    public function testForceAdoptOverwritesForeignManifest(): void
    {
        [$sourceRoot, $targetRoot] = $this->makeRoots('fm_adopt');
        file_put_contents($sourceRoot . '/manifest.json', "{\"kit\":true}\n");
        file_put_contents($targetRoot . '/manifest.json', "{\"name\":\"consumer-app\"}\n");

        $plan = $this->planSingleFile($sourceRoot, $targetRoot, ['force' => true, 'adopt' => true]);

        $this->assertSame(
            'OVERWRITE_MANAGED',
            $plan[0]['action'] ?? null,
            '--force --adopt explicitly overwrites a foreign file'
        );
        $this->assertStringContainsString('adopt', (string) ($plan[0]['reason'] ?? ''), 'adopt overwrite reason drives the foreign snapshot');
    }

    public function testDefaultInstallPreservesForeignManifest(): void
    {
        [$sourceRoot, $targetRoot] = $this->makeRoots('fm_default');
        file_put_contents($sourceRoot . '/manifest.json', "{\"kit\":true}\n");
        file_put_contents($targetRoot . '/manifest.json', "{\"name\":\"consumer-app\"}\n");

        $plan = $this->planSingleFile($sourceRoot, $targetRoot, ['force' => false, 'adopt' => false]);

        $this->assertSame(
            'SKIP_EXISTING_UNMANAGED',
            $plan[0]['action'] ?? null,
            'default install must preserve a foreign manifest.json'
        );
    }

    public function testPackRegistryShipsNoCollisionProneRootFilenames(): void
    {
        $packs = aiInstallerPackRegistry();
        $offenders = [];
        $collisionProne = ['manifest.json', 'manifest.yml', 'manifest.yaml', 'catalog.json', 'package-lock.ai.json', 'config.json', 'settings.json'];

        foreach ($packs as $packId => $items) {
            foreach ($items as $item) {
                $target = str_replace('\\', '/', (string) ($item['target'] ?? ''));
                if (!str_contains($target, '/') && in_array(strtolower($target), $collisionProne, true)) {
                    $offenders[] = "{$packId}: {$target}";
                }
            }
        }

        $this->assertSame([], $offenders, 'kit must not ship generic collision-prone root filenames: ' . implode(', ', $offenders));
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
}
