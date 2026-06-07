<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * P1: checksum-set adoption.
 *
 * A file installed by an older kit version (byte-identical to a known prior kit
 * checksum but not the current source) must be adopted into the lock rather than
 * flagged as a foreign conflict or silently skipped. The known-checksum registry
 * (tools/ai/known-kit-checksums.json) is the source of historical hashes.
 */
final class KnownKitChecksumAdoptionTest extends TestCase
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

    public function testMatchesKnownKitChecksumDetectsHistoricalHash(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'p1_match_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root, 0700, true);
        $abs = $root . DIRECTORY_SEPARATOR . 'file.md';
        $bytes = "# old kit bytes\n";
        file_put_contents($abs, $bytes);
        $hash = hash('sha256', $bytes);

        $known = ['docs/ai/file.md' => ['sha256:' . $hash, 'sha256:deadbeef']];

        $this->assertTrue(
            aiInstallerMatchesKnownKitChecksum('docs/ai/file.md', $abs, $known),
            'a file matching a recorded historical hash must be recognized'
        );
        $this->assertFalse(
            aiInstallerMatchesKnownKitChecksum('docs/ai/other.md', $abs, $known),
            'a target with no recorded history must not match'
        );
    }

    public function testMatchesKnownKitChecksumAcceptsBareHashes(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'p1_bare_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        mkdir($root, 0700, true);
        $abs = $root . DIRECTORY_SEPARATOR . 'file.md';
        $bytes = "# bytes\n";
        file_put_contents($abs, $bytes);

        // Registry value without the sha256: prefix must still match.
        $known = ['docs/ai/file.md' => [hash('sha256', $bytes)]];
        $this->assertTrue(aiInstallerMatchesKnownKitChecksum('docs/ai/file.md', $abs, $known));
    }

    public function testPlannerAdoptsKnownKitChecksumInsteadOfConflict(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'p1_planner_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        $sourceRoot = $root . DIRECTORY_SEPARATOR . 'source';
        $targetRoot = $root . DIRECTORY_SEPARATOR . 'target';
        mkdir($sourceRoot, 0700, true);
        mkdir($targetRoot, 0700, true);

        // Current source differs from what the user has on disk.
        file_put_contents($sourceRoot . DIRECTORY_SEPARATOR . 'cfg.jsonc', "{\"v\":2}\n");
        $oldBytes = "{\"v\":1}\n";
        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . 'cfg.jsonc', $oldBytes);

        // The on-disk file matches a known prior kit checksum.
        $known = ['cfg.jsonc' => ['sha256:' . hash('sha256', $oldBytes)]];

        $plan = aiInstallerBuildPlan([
            'sourceRoot' => $sourceRoot,
            'targetRoot' => $targetRoot,
            'force' => false,
            'adopt' => false,
            'allowCoreOverwrite' => false,
            'upgradeSuffix' => '',
            'knownKitChecksums' => $known,
        ], [
            'pack' => [[
                'type' => 'file',
                'source' => 'cfg.jsonc',
                'target' => 'cfg.jsonc',
                'merge_strategy' => 'replace',
                'never_auto_merge' => true,
                'core' => false,
            ]],
        ], ['pack']);

        $this->assertSame('ADOPT_KNOWN_KIT', $plan[0]['action'] ?? null, 'a file matching a known prior kit checksum is adopted, not a foreign conflict');
    }

    public function testPlannerStillConflictsForTrulyForeignFile(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'p1_foreign_' . uniqid('', true);
        $this->tmpDirs[] = $root;
        $sourceRoot = $root . DIRECTORY_SEPARATOR . 'source';
        $targetRoot = $root . DIRECTORY_SEPARATOR . 'target';
        mkdir($sourceRoot, 0700, true);
        mkdir($targetRoot, 0700, true);

        file_put_contents($sourceRoot . DIRECTORY_SEPARATOR . 'cfg.jsonc', "{\"v\":2}\n");
        file_put_contents($targetRoot . DIRECTORY_SEPARATOR . 'cfg.jsonc', "{\"user\":\"hand-written\"}\n");

        // No known checksum matches the user's foreign bytes.
        $plan = aiInstallerBuildPlan([
            'sourceRoot' => $sourceRoot,
            'targetRoot' => $targetRoot,
            'force' => false,
            'adopt' => false,
            'allowCoreOverwrite' => false,
            'upgradeSuffix' => '',
            'knownKitChecksums' => ['cfg.jsonc' => ['sha256:' . hash('sha256', "something-else\n")]],
        ], [
            'pack' => [[
                'type' => 'file',
                'source' => 'cfg.jsonc',
                'target' => 'cfg.jsonc',
                'merge_strategy' => 'replace',
                'never_auto_merge' => true,
                'core' => false,
            ]],
        ], ['pack']);

        $this->assertSame('CONFLICT_FOREIGN', $plan[0]['action'] ?? null, 'a genuinely foreign never_auto_merge file still conflicts');
    }

    public function testKnownChecksumRegistryFileIsValid(): void
    {
        $path = self::$repoRoot . '/tools/ai/known-kit-checksums.json';
        $this->assertFileExists($path, 'the known-kit-checksums registry must ship with the kit');
        $decoded = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('schemaVersion', $decoded, 'registry must declare schemaVersion');
        $this->assertSame(1, $decoded['schemaVersion']);
        $this->assertArrayHasKey('checksums', $decoded);
        $this->assertIsArray($decoded['checksums']);
    }
}
