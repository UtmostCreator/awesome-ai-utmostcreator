<?php

declare(strict_types=1);

require_once __DIR__ . '/ai_catalog_lib.php';

$targetArg = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--target=')) {
        $targetArg = substr($arg, 9);
    }
}

$candidateRoot = dirname(__DIR__, 2);
$sourceRepoMode = is_dir($candidateRoot . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'ai-universal-rules' . DIRECTORY_SEPARATOR . 'templates');
if ($targetArg !== null || (!$sourceRepoMode && is_file($candidateRoot . DIRECTORY_SEPARATOR . '.ai-install-manifest.json'))) {
    $targetRoot = $targetArg !== null ? realpath($targetArg) : realpath(dirname(__DIR__, 2));
    if ($targetRoot === false) {
        fwrite(STDERR, "ERROR: target root not found\n");
        exit(1);
    }
    $errors = [];
    foreach (['.ai/catalog.json', '.ai/kit-manifest.json', '.ai-install-manifest.json'] as $candidate) {
        $path = $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate);
        if (is_file($path) && json_decode((string) file_get_contents($path), true) === null) {
            $errors[] = "invalid JSON: {$candidate}";
        }
    }
    foreach ($errors as $error) {
        fwrite(STDERR, "ERROR: {$error}\n");
    }
    if ($errors === []) {
        fwrite(STDOUT, "OK: target AI catalog validation passed\n");
    }
    exit($errors === [] ? 0 : 1);
}

$root = aiRepoRoot();
$errors = [];
$warnings = [];
$manifest = aiLoadJson($root, aiResolveKitDescriptorPath($root, 'manifest.json'));

foreach (aiValidateManifest($manifest, $root) as $error) {
    $errors[] = $error;
}

$yamlSummary = aiReadManifestYamlSummary($root);

foreach (['name', 'version', 'description'] as $key) {
    if (($yamlSummary[$key] ?? null) !== ($manifest[$key] ?? null)) {
        $errors[] = "manifest.yml and manifest.json disagree on {$key}";
    }
}

$catalog = aiLoadJson($root, aiResolveKitDescriptorPath($root, 'catalog.json'));

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
    if (!file_exists(aiAbsolutePath($root, $requiredPhpReferencePath))) {
        continue;
    }

    if (!aiCatalogHasPath($catalog, $requiredPhpReferencePath)) {
        $errors[] = "catalog.json should include PHP reference corpus path {$requiredPhpReferencePath}";
    }
}

foreach ([
    'docs/ai/capabilities/agent-observability-and-evidence/event-schema.md',
    'docs/ai/capabilities/agent-observability-and-evidence/failure-taxonomy.md',
    'schemas/ai/evidence-event.schema.json',
] as $requiredEvidencePath) {
    if (!aiCatalogHasPath($catalog, $requiredEvidencePath)) {
        $errors[] = "catalog.json should include evidence support path {$requiredEvidencePath}";
    }
}

foreach ([
    'docs/ai/capabilities/evaluation-and-regression/golden-tasks.md',
    'docs/ai/capabilities/evaluation-and-regression/replay-rules.md',
    'docs/ai/capabilities/evaluation-and-regression/human-review-rules.md',
] as $requiredEvaluationPath) {
    if (!aiCatalogHasPath($catalog, $requiredEvaluationPath)) {
        $errors[] = "catalog.json should include evaluation support path {$requiredEvaluationPath}";
    }
}

foreach ([
    'docs/ai/capabilities/preview-environments/lifecycle.md',
    'docs/ai/capabilities/preview-environments/data-and-secret-rules.md',
    'docs/ai/capabilities/preview-environments/checklist.md',
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
