<?php

declare(strict_types=1);

require_once __DIR__ . '/ai_catalog_lib.php';

$root = aiRepoRoot();
$errors = [];
$warnings = [];
$manifest = aiLoadJson($root, 'packages/ai-universal-rules/manifest.json');

foreach (aiValidateManifest($manifest, $root) as $error) {
    $errors[] = $error;
}

$yamlSummary = aiReadManifestYamlSummary($root);

foreach (['name', 'version', 'description'] as $key) {
    if (($yamlSummary[$key] ?? null) !== ($manifest[$key] ?? null)) {
        $errors[] = "manifest.yml and manifest.json disagree on {$key}";
    }
}

$catalog = aiLoadJson($root, 'packages/ai-universal-rules/catalog.json');

foreach (['generated_by', 'repository', 'package', 'counts', 'resources', 'starter_profiles'] as $key) {
    if (!array_key_exists($key, $catalog)) {
        $errors[] = "catalog.json missing {$key}";
    }
}

foreach ($catalog['resources'] ?? [] as $resource) {
    if (!is_array($resource)) {
        $errors[] = 'catalog.json resources entries must be objects';
        continue;
    }

    foreach (['scope', 'type', 'name', 'path'] as $requiredKey) {
        if (!array_key_exists($requiredKey, $resource)) {
            $errors[] = "catalog.json resource missing {$requiredKey}";
        }
    }

    if (isset($resource['path']) && !file_exists(aiAbsolutePath($root, $resource['path']))) {
        $errors[] = "catalog.json references missing resource {$resource['path']}";
    }
}

foreach (['reference/php/design-patterns', 'reference/php/design-principles', 'reference/php/php-built-ins'] as $requiredPhpReferencePath) {
    if (!aiCatalogHasPath($catalog, $requiredPhpReferencePath)) {
        $errors[] = "catalog.json should include PHP reference corpus path {$requiredPhpReferencePath}";
    }
}

foreach ([
    'docs/ai/capabilities/agent-observability-and-evidence/EVENT_SCHEMA.md',
    'docs/ai/capabilities/agent-observability-and-evidence/FAILURE_TAXONOMY.md',
    '.schemas/evidence-event.schema.json',
] as $requiredEvidencePath) {
    if (!aiCatalogHasPath($catalog, $requiredEvidencePath)) {
        $errors[] = "catalog.json should include evidence support path {$requiredEvidencePath}";
    }
}

foreach ([
    'docs/ai/capabilities/evaluation-and-regression/GOLDEN_TASKS.md',
    'docs/ai/capabilities/evaluation-and-regression/REPLAY_RULES.md',
    'docs/ai/capabilities/evaluation-and-regression/HUMAN_REVIEW_RULES.md',
] as $requiredEvaluationPath) {
    if (!aiCatalogHasPath($catalog, $requiredEvaluationPath)) {
        $errors[] = "catalog.json should include evaluation support path {$requiredEvaluationPath}";
    }
}

foreach ([
    'docs/ai/capabilities/preview-environments/LIFECYCLE.md',
    'docs/ai/capabilities/preview-environments/DATA_AND_SECRET_RULES.md',
    'docs/ai/capabilities/preview-environments/CHECKLIST.md',
] as $requiredPreviewPath) {
    if (!aiCatalogHasPath($catalog, $requiredPreviewPath)) {
        $errors[] = "catalog.json should include preview support path {$requiredPreviewPath}";
    }
}

foreach ($manifest['generated_outputs'] ?? [] as $path) {
    if (!file_exists(aiAbsolutePath($root, $path))) {
        $errors[] = "generated output missing {$path}";
    }
}

if (($catalog['package']['version'] ?? null) !== ($manifest['version'] ?? null)) {
    $errors[] = 'catalog.json package version does not match manifest.json';
}

if ($errors === [] && $warnings === []) {
    fwrite(STDOUT, "OK: AI catalog metadata validation passed\n");
}

foreach ($warnings as $warning) {
    fwrite(STDOUT, "WARN: {$warning}\n");
}

foreach ($errors as $error) {
    fwrite(STDERR, "ERROR: {$error}\n");
}

exit($errors === [] ? 0 : 1);

function aiCatalogHasPath(array $catalog, string $path): bool
{
    foreach ($catalog['resources'] ?? [] as $resource) {
        if (is_array($resource) && ($resource['path'] ?? null) === $path) {
            return true;
        }
    }

    return false;
}
