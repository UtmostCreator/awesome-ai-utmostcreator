<?php

declare(strict_types=1);

/**
 * Loader for the installer pack registry data (registry/*.yaml + registry/*.json).
 *
 * The YAML files are the hand-edited source; the JSON files are their committed,
 * shipped compilation (see registry-compile.php). Each file maps pack name =>
 * list of entry specs. An entry spec is either a bare string (source == target,
 * all defaults) or a map that overrides target/type/core/merge_strategy/required
 * or adds extras (install_type, rename_ext, never_auto_merge, merge_into_existing).
 * Every spec is passed through aiInstallerRegistryEntry() (defined in registry.php),
 * whose canonical key-order rebuild yields output identical to the former
 * hand-written PHP registry.
 *
 * Parsing strategy: use symfony/yaml (a dev/build dependency) when it is
 * available — e.g. in the kit source repo — so authors edit YAML directly.
 * Installed target projects do not have Composer or symfony/yaml, so the loader
 * falls back to the shipped JSON compilation. Both paths feed the same
 * dependency-free normalizer, so the result is identical.
 */

// Best-effort Composer autoload so symfony/yaml resolves in the source repo.
// Absent in target projects; the loader then uses the JSON fallback.
$aiInstallerAutoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
if (is_file($aiInstallerAutoload)) {
    require_once $aiInstallerAutoload;
}
unset($aiInstallerAutoload);

if (!function_exists('aiInstallerLoadRegistryData')) {
    /**
     * Parse one registry data file (by extension-less base path) into normalized
     * pack entries, preferring YAML and falling back to the compiled JSON.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    function aiInstallerLoadRegistryData(string $baseNoExt): array
    {
        $yamlPath = $baseNoExt . '.yaml';
        $jsonPath = $baseNoExt . '.json';

        if (class_exists('Symfony\\Component\\Yaml\\Yaml') && is_file($yamlPath)) {
            $parsed = \Symfony\Component\Yaml\Yaml::parseFile($yamlPath);
            $sourceLabel = $yamlPath;
        } elseif (is_file($jsonPath)) {
            $parsed = json_decode((string) file_get_contents($jsonPath), true);
            $sourceLabel = $jsonPath;
        } else {
            throw new RuntimeException(sprintf(
                'Missing installer registry data for "%s" (.yaml or .json).',
                $baseNoExt,
            ));
        }

        if (!is_array($parsed)) {
            throw new RuntimeException(sprintf('Installer registry file %s must define a pack map.', $sourceLabel));
        }

        $registry = [];
        foreach ($parsed as $packName => $specs) {
            if (!is_string($packName) || $packName === '') {
                throw new RuntimeException(sprintf('Invalid pack name in %s.', $sourceLabel));
            }
            if (!is_array($specs)) {
                throw new RuntimeException(sprintf('Pack "%s" in %s must be a list of entries.', $packName, $sourceLabel));
            }

            $registry[$packName] = array_map(
                /** @param string|array<string, mixed> $spec */
                static function (string|array $spec): array {
                    return aiInstallerRegistryEntry($spec);
                },
                array_values($specs),
            );
        }

        return $registry;
    }
}
