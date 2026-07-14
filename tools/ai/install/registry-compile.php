<?php

declare(strict_types=1);

/**
 * Compile the hand-edited registry/*.yaml sources into their committed,
 * shipped registry/*.json counterparts.
 *
 * The YAML is the authoring format; the JSON is what installed target projects
 * (which have no Composer / symfony/yaml) actually read at runtime. Run this
 * whenever a registry YAML changes. `--check` verifies the JSON is in sync and
 * exits non-zero on drift (for CI / a generated-artifact gate).
 *
 * Needs symfony/yaml, which is a dev/build dependency — run from the kit source
 * repo after `composer install`.
 */

$autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "ERROR: composer autoload missing; run 'composer install' (symfony/yaml is a dev dependency).\n");
    exit(1);
}
require_once $autoload;
require_once __DIR__ . '/registry.php';

use Symfony\Component\Yaml\Yaml;

$check = in_array('--check', $argv, true);
$dir = __DIR__ . '/registry';
$drift = [];

foreach (AI_INSTALLER_REGISTRY_FILES as $name) {
    $yamlPath = $dir . '/' . $name . '.yaml';
    $jsonPath = $dir . '/' . $name . '.json';

    if (!is_file($yamlPath)) {
        fwrite(STDERR, "ERROR: missing source registry/{$name}.yaml\n");
        exit(1);
    }

    $data = Yaml::parseFile($yamlPath);
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

    if ($check) {
        $current = is_file($jsonPath) ? (string) file_get_contents($jsonPath) : '';
        if ($current !== $encoded) {
            $drift[] = $name;
        }
        continue;
    }

    file_put_contents($jsonPath, $encoded);
    fwrite(STDOUT, "wrote registry/{$name}.json\n");
}

if ($check) {
    if ($drift !== []) {
        fwrite(STDERR, sprintf(
            "ERROR: registry JSON is out of date (%s). Run: php tools/ai/install/registry-compile.php\n",
            implode(', ', $drift),
        ));
        exit(1);
    }
    fwrite(STDOUT, "OK: registry JSON matches YAML sources\n");
}

exit(0);
