<?php

declare(strict_types=1);

function aiInstallerProjectValuesPath(string $targetRoot): string
{
    return $targetRoot . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'project.yml';
}

/**
 * Map a relocated kit descriptor target path to its canonical root filename and copy-out
 * safety. Returns null for any path that is not a relocated descriptor.
 *
 * copyOutSafe is true only for manifest.json/manifest.yml: catalog.json and
 * package-lock.ai.json are resolved by the kit's descriptor resolver (.ai/ first) and copying
 * them to root can confuse the legacy root fallback in ai_catalog_lib.php, so they are marked
 * informational-only (copyOutSafe=false).
 *
 * @return array{canonicalRootName:string,copyOutSafe:bool}|null
 */
function aiInstallerDescriptorProvenance(string $target): ?array
{
    static $map = [
        '.ai/kit-manifest.json' => ['canonicalRootName' => 'manifest.json', 'copyOutSafe' => true],
        '.ai/kit-manifest.yml' => ['canonicalRootName' => 'manifest.yml', 'copyOutSafe' => true],
        '.ai/catalog.json' => ['canonicalRootName' => 'catalog.json', 'copyOutSafe' => false],
        '.ai/package-lock.ai.json' => ['canonicalRootName' => 'package-lock.ai.json', 'copyOutSafe' => false],
    ];

    return $map[$target] ?? null;
}

/**
 * P5-b: write the informational-only `.ai/local-manifest.json`.
 *
 * This is a gitignored, human-facing summary of what the kit installed. It is NEVER read
 * back to decide writes or deletes — the canonical `.ai-install-manifest.json` files{} map
 * and the lock are the only write allowlist. The self-describing flags make that explicit.
 *
 * @param array<string,mixed> $manifest The canonical install manifest.
 */
function aiInstallerWriteLocalManifest(string $targetRoot, array $manifest): void
{
    $files = [];
    foreach (($manifest['files'] ?? []) as $path => $meta) {
        if (!is_string($path) || !is_array($meta)) {
            continue;
        }
        $entry = [
            'ownership' => (string) ($meta['ownership'] ?? 'owned'),
            'installed_hash' => (string) ($meta['installed_hash'] ?? ''),
        ];
        $prov = aiInstallerDescriptorProvenance($path);
        if ($prov !== null) {
            $entry['descriptor'] = [
                'canonicalRootName' => $prov['canonicalRootName'],
                'namespacedToAvoidCollision' => true,
                'copyOutSafe' => $prov['copyOutSafe'],
            ];
        }
        $files[$path] = $entry;
    }
    ksort($files);

    $local = [
        'schemaVersion' => 1,
        'informational' => true,
        'not_a_write_allowlist' => true,
        'note' => 'Informational summary only. The canonical .ai-install-manifest.json files{} '
            . 'and .ai/manifest.lock.json are the authoritative write allowlist. Safe to delete.',
        'generatedAt' => gmdate('c'),
        'installed_version' => (string) ($manifest['package']['installed_version']
            ?? $manifest['installer_version'] ?? 'unknown'),
        'files' => $files,
    ];

    $path = $targetRoot . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'local-manifest.json';
    aiInstallerMkdir(dirname($path));
    file_put_contents($path, json_encode($local, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

function aiInstallerEnsureProjectValuesFile(string $targetRoot, string $projectName): void
{
    $path = aiInstallerProjectValuesPath($targetRoot);
    if (is_file($path)) {
        return;
    }

    aiInstallerMkdir(dirname($path));
    $values = array_merge(['schemaVersion' => '1'], aiInstallerCoreProjectValueDefaults($targetRoot, $projectName));

    $lines = [
        '# AI kit project values. Template/user-owned: edit values here, then rerun install/upgrade to re-render managed files.',
    ];
    foreach ($values as $key => $value) {
        $lines[] = $key . ': ' . aiInstallerProjectYamlQuote($value);
    }

    // P4-a: optional project-fact keys. Uncomment and set to drive the matching placeholders
    // from project.yml so they survive every re-render. Unset keys keep the kit defaults.
    $optionalKeys = [
        'targetPlatforms', 'sourceDirs', 'testDirs', 'testCommand', 'buildCommand',
        'lintCommand', 'formatCommand', 'installCommand', 'packageManager', 'ciCommands',
        'protectedPaths', 'generatedFiles', 'protectedFiles', 'reviewPriorities',
        'riskAreas', 'approvalRequiredChanges', 'inactivePaths', 'availableCapabilities',
        'primaryStack', 'filePlacementRules', 'namingRules', 'goldenExamples',
        'formatterConfigFiles', 'linterConfigFiles', 'editorconfigPath', 'ignoreFiles',
        'selectedStacks', 'detectedStacks', 'stackToolVersions', 'recommendedVerificationCommands',
    ];
    $lines[] = '# Optional project-fact values (uncomment to override kit defaults):';
    foreach ($optionalKeys as $key) {
        $lines[] = '# ' . $key . ': unknown';
    }

    file_put_contents($path, implode("\n", $lines) . "\n");
}

/**
 * The 9 core project-fact defaults shared between the initial project.yml template
 * (aiInstallerEnsureProjectValuesFile) and the in-memory load defaults
 * (aiInstallerLoadProjectValues). Single source of truth for these keys/values.
 *
 * @return array<string,string>
 */
function aiInstallerCoreProjectValueDefaults(string $targetRoot, string $projectName): array
{
    return [
        'projectName' => $projectName,
        'projectType' => aiInstallerDetectProjectType($targetRoot),
        'projectSummary' => 'AI workflow starter for ' . $projectName,
        'primaryLanguage' => 'unknown',
        'primaryRuntime' => 'unknown',
        'primaryEntrypoints' => 'README.md, docs/ai/project-context.md',
        'primaryVerifyCommand' => 'unknown',
        'primaryBuildCommand' => 'unknown',
        'primaryTestCommand' => 'unknown',
    ];
}

/** @return array<string,string> */
function aiInstallerLoadProjectValues(string $targetRoot, string $projectName): array
{
    $defaults = array_merge(aiInstallerCoreProjectValueDefaults($targetRoot, $projectName), [
        // P4-a: customizable project-fact values consolidated into project.yml so they
        // survive every re-render. Unset keys stay 'unknown' (the placeholder default).
        'targetPlatforms' => 'unknown',
        'sourceDirs' => 'unknown',
        'testDirs' => 'unknown',
        'testCommand' => 'unknown',
        'buildCommand' => 'unknown',
        'lintCommand' => 'unknown',
        'formatCommand' => 'unknown',
        'installCommand' => 'unknown',
        'packageManager' => 'unknown',
        'ciCommands' => 'unknown',
        'protectedPaths' => 'unknown',
        'generatedFiles' => 'unknown',
        'protectedFiles' => 'unknown',
        'reviewPriorities' => 'unknown',
        'riskAreas' => 'unknown',
        'approvalRequiredChanges' => 'unknown',
        'inactivePaths' => 'unknown',
        'availableCapabilities' => 'unknown',
        'primaryStack' => 'unknown',
        'filePlacementRules' => 'unknown',
        'namingRules' => 'unknown',
        'goldenExamples' => 'unknown',
        'formatterConfigFiles' => 'unknown',
        'linterConfigFiles' => 'unknown',
        'editorconfigPath' => 'unknown',
        'ignoreFiles' => 'unknown',
        // Dynamic stack selection scalar summaries (docs/tickets/arch-todo-dynamic-stack-
        // permission-selection-*, Slice 4). Comma-separated stack ids / human-readable
        // summaries only; structured evidence lives in .ai/stack-detection.json instead.
        'selectedStacks' => 'unknown',
        'detectedStacks' => 'unknown',
        'stackToolVersions' => 'unknown',
        'recommendedVerificationCommands' => 'unknown',
    ]);

    $path = aiInstallerProjectValuesPath($targetRoot);
    if (!is_file($path)) {
        return $defaults;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, ':')) {
            continue;
        }
        [$key, $value] = explode(':', $trimmed, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '' || !array_key_exists($key, $defaults)) {
            continue;
        }
        $defaults[$key] = aiInstallerProjectYamlUnquote($value);
    }

    return $defaults;
}

/**
 * Write-through selected/detected stack summaries into `.ai/project.yml` (Slice 4 of
 * docs/tickets/arch-todo-dynamic-stack-permission-selection-20260705T011906Z/plan.md).
 *
 * Only touches a key when its current value is 'unknown' or the key is absent/commented
 * out — an explicit user value (anything else) is never overwritten, matching the file's
 * "template/user-owned: edit values here" contract.
 *
 * @param array{selected:list<string>,detected:array<string,array{id:string,confidence:int,signals:list<string>}>,versions:array<string,array{id:string,tool:string,available:bool,output:string}>} $resolved
 */
function aiInstallerApplyStackSelectionToProjectValues(string $targetRoot, array $resolved): void
{
    if ($resolved['selected'] === [] && $resolved['detected'] === []) {
        return; // nothing detected or selected; do not touch the file.
    }

    $path = aiInstallerProjectValuesPath($targetRoot);
    if (!is_file($path)) {
        return; // aiInstallerEnsureProjectValuesFile() must run first.
    }

    $updates = [
        'selectedStacks' => $resolved['selected'] === [] ? 'unknown' : implode(',', $resolved['selected']),
        'detectedStacks' => $resolved['detected'] === [] ? 'unknown' : implode(',', array_map(
            static fn (array $e): string => $e['id'] . ':' . $e['confidence'],
            array_values($resolved['detected'])
        )),
        'primaryStack' => aiInstallerPrimaryStack($resolved),
        'stackToolVersions' => aiInstallerSummarizeStackVersions($resolved['versions']),
    ];

    $raw = (string) file_get_contents($path);
    $lines = explode("\n", $raw);
    $seen = [];

    foreach ($lines as $i => $line) {
        $trimmed = ltrim($line, '# ');
        foreach ($updates as $key => $value) {
            if (!str_starts_with($trimmed, $key . ':')) {
                continue;
            }
            $currentValue = aiInstallerProjectYamlUnquote(trim(substr($trimmed, strlen($key) + 1)));
            $seen[$key] = true;
            if ($currentValue !== 'unknown' && $currentValue !== '') {
                continue; // explicit user value; do not overwrite.
            }
            $lines[$i] = $key . ': ' . aiInstallerProjectYamlQuote($value);
        }
    }

    foreach ($updates as $key => $value) {
        if (isset($seen[$key])) {
            continue;
        }
        $lines[] = $key . ': ' . aiInstallerProjectYamlQuote($value);
    }

    file_put_contents($path, implode("\n", $lines));
}

/**
 * Pick the primary stack: the selected stack with the highest detection confidence
 * (ties broken alphabetically for determinism), not simply the first selected id
 * alphabetically — `sort()` order is not a confidence signal.
 *
 * @param array{selected:list<string>,detected:array<string,array{id:string,confidence:int,signals:list<string>}>} $resolved
 */
function aiInstallerPrimaryStack(array $resolved): string
{
    if ($resolved['selected'] === []) {
        return 'unknown';
    }

    $best = null;
    $bestConfidence = -1;
    foreach ($resolved['selected'] as $id) {
        $confidence = $resolved['detected'][$id]['confidence'] ?? -1;
        if ($confidence > $bestConfidence || ($confidence === $bestConfidence && ($best === null || $id < $best))) {
            $best = $id;
            $bestConfidence = $confidence;
        }
    }

    return $best ?? $resolved['selected'][0];
}

/**
 * @param array<string,array{id:string,tool:string,available:bool,output:string}> $versions
 */
function aiInstallerSummarizeStackVersions(array $versions): string
{
    if ($versions === []) {
        return 'unknown';
    }

    $parts = [];
    foreach ($versions as $entry) {
        $parts[] = $entry['tool'] . '=' . ($entry['available'] ? (aiInstallerFirstLine($entry['output']) ?: 'ok') : 'unavailable');
    }

    return implode(', ', $parts);
}

function aiInstallerFirstLine(string $text): string
{
    $lines = preg_split('/\R/', trim($text)) ?: [];

    return (string) ($lines[0] ?? '');
}

/**
 * Informational-only stack detection/version-check evidence, gitignored, never read
 * back as a write allowlist (same posture as .ai/local-manifest.json).
 *
 * @param array{selected:list<string>,detected:array<string,array{id:string,confidence:int,signals:list<string>}>,versions:array<string,array{id:string,tool:string,available:bool,output:string,error:string,required:bool}>} $resolved
 */
function aiInstallerWriteStackDetectionEvidence(string $targetRoot, array $resolved): void
{
    $payload = [
        'schemaVersion' => 1,
        'informational' => true,
        'not_a_write_allowlist' => true,
        'generatedAt' => gmdate('c'),
        'detected' => $resolved['detected'],
        'selected' => $resolved['selected'],
        'versionChecks' => $resolved['versions'],
    ];

    $path = $targetRoot . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'stack-detection.json';
    aiInstallerMkdir(dirname($path));
    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

/**
 * Read the optional `context.extraDocs:` list from .ai/project.yml. These are user-owned pointers
 * to additional project docs the AI should reference. They live in project.yml (not in rendered
 * files), so they survive every re-render: the installer regenerates the <EXTRA_DOCS> block from
 * this list each time.
 *
 * @return list<string>
 */
function aiInstallerLoadProjectExtraDocs(string $targetRoot): array
{
    $path = aiInstallerProjectValuesPath($targetRoot);
    if (!is_file($path)) {
        return [];
    }

    return aiInstallerParseProjectYamlList((string) file_get_contents($path), 'context', 'extraDocs');
}

/**
 * Render the <EXTRA_DOCS> placeholder value: a markdown bullet list of user-listed extra docs,
 * or a neutral note when none are configured. Safe to inject into any rendered markdown.
 *
 * @param list<string> $docs
 */
function aiInstallerRenderExtraDocsBlock(array $docs): string
{
    if ($docs === []) {
        return '_No additional project docs configured. Add paths under `context.extraDocs` in `.ai/project.yml`._';
    }

    $lines = [];
    foreach ($docs as $doc) {
        $lines[] = '- [`' . $doc . '`](' . $doc . ')';
    }

    return implode("\n", $lines);
}
