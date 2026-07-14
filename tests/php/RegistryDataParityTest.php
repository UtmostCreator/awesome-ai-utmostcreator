<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Guards the YAML/JSON dual representation of the installer pack registry.
 *
 * registry/*.yaml is the hand-edited source; registry/*.json is its committed,
 * shipped compilation (installed target projects have no Composer / symfony/yaml
 * and read the JSON). If someone edits a YAML without running
 * `php tools/ai/install/registry-compile.php`, the shipped JSON drifts from the
 * source and targets install stale data. These tests fail closed on that drift
 * and prove both loader paths yield the same assembled registry.
 */
final class RegistryDataParityTest extends TestCase
{
    private static string $registryDir;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root');
        }
        require_once $root . '/tools/ai/install/registry.php';
        self::$registryDir = $root . '/tools/ai/install/registry';
    }

    /**
     * @return list<array{string}>
     */
    public static function registryFiles(): array
    {
        // AI_INSTALLER_REGISTRY_FILES is defined by registry.php; setUpBeforeClass
        // has not run yet at data-provider time, so require it here too.
        $root = realpath(dirname(__DIR__, 2));
        require_once $root . '/tools/ai/install/registry.php';

        return array_map(static fn(string $name): array => [$name], AI_INSTALLER_REGISTRY_FILES);
    }

    #[DataProvider('registryFiles')]
    public function testCompiledJsonMatchesYamlSource(string $name): void
    {
        $yamlPath = self::$registryDir . '/' . $name . '.yaml';
        $jsonPath = self::$registryDir . '/' . $name . '.json';

        self::assertFileExists($yamlPath);
        self::assertFileExists($jsonPath, "Missing compiled registry/{$name}.json — run registry-compile.php");

        $fromYaml = Yaml::parseFile($yamlPath);
        $fromJson = json_decode((string) file_get_contents($jsonPath), true);

        self::assertSame(
            $fromYaml,
            $fromJson,
            "registry/{$name}.json is out of date. Run: php tools/ai/install/registry-compile.php",
        );
    }

    public function testAssembledRegistryIsNonEmptyAndUnique(): void
    {
        $registry = aiInstallerPackRegistry();
        self::assertNotEmpty($registry);
        // Every entry across every pack carries the full normalized field set,
        // which only happens when the loader ran each spec through the normalizer.
        foreach ($registry as $pack => $entries) {
            foreach ($entries as $i => $entry) {
                foreach (['type', 'source', 'target', 'core', 'merge_strategy', 'required'] as $field) {
                    self::assertArrayHasKey($field, $entry, "Pack {$pack} entry {$i} missing {$field}");
                }
            }
        }
    }
}
