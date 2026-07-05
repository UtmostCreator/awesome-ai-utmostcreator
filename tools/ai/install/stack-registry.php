<?php

declare(strict_types=1);

/**
 * Stack descriptor registry loader and validator.
 *
 * Shipped descriptors live under packages/ai-universal-rules/stacks/*.json;
 * project-local descriptors may be added under .ai/stacks/*.json.
 */

/** @return array<string,array<string,mixed>> */
function aiStackLoadRegistry(?string $targetRoot = null, ?string $repoRoot = null): array
{
    $repoRoot = aiStackNormalizeRoot($repoRoot ?? dirname(__DIR__, 3));
    $targetRoot = $targetRoot === null ? null : aiStackNormalizeRoot($targetRoot);

    $sources = [
        ['kind' => 'shipped', 'dir' => $repoRoot . '/packages/ai-universal-rules/stacks'],
    ];
    if ($targetRoot !== null) {
        $sources[] = ['kind' => 'local', 'dir' => $targetRoot . '/.ai/stacks'];
    }

    $registry = [];
    foreach ($sources as $source) {
        $files = glob($source['dir'] . '/*.json') ?: [];
        sort($files);
        foreach ($files as $file) {
            $descriptor = aiStackLoadDescriptorFile($file);
            $descriptor['_source'] = $source['kind'];
            $descriptor['_path'] = aiStackRelativePath($file, $source['kind'] === 'shipped' ? $repoRoot : (string) $targetRoot);
            aiStackAddDescriptor($registry, $descriptor);
        }
    }

    uasort($registry, static function (array $a, array $b): int {
        $priority = ((int) ($a['merge_priority'] ?? 50)) <=> ((int) ($b['merge_priority'] ?? 50));
        if ($priority !== 0) {
            return $priority;
        }

        return strcmp((string) $a['id'], (string) $b['id']);
    });

    return $registry;
}

/** @return array<string,mixed> */
function aiStackLoadDescriptorFile(string $file): array
{
    $raw = @file_get_contents($file);
    if ($raw === false) {
        throw new RuntimeException(sprintf('Could not read stack descriptor: %s', $file));
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException(sprintf('Invalid JSON in stack descriptor %s: %s', $file, json_last_error_msg()));
    }

    return aiStackNormalizeDescriptor($decoded, $file);
}

/**
 * @param array<string,mixed> $descriptor
 * @return array<string,mixed>
 */
function aiStackNormalizeDescriptor(array $descriptor, string $source = '<memory>'): array
{
    aiStackValidateDescriptor($descriptor, $source);

    $descriptor += [
        'description' => '',
        'category' => 'language',
        'version_checks' => [],
        'recommended_commands' => [],
        'package_managers' => [],
        'conflicts' => [],
        'implies' => [],
        'merge_priority' => 50,
    ];
    $descriptor['detection'] += [
        'files' => [],
        'globs' => [],
        'commands' => [],
        'scc_languages' => [],
        'confidence' => [],
    ];
    $descriptor['permission_overlays'] += [
        'language_overlays' => [],
        'extra_layers' => [],
    ];

    return $descriptor;
}

/** @param array<string,mixed> $descriptor */
function aiStackValidateDescriptor(array $descriptor, string $source = '<memory>'): void
{
    foreach (['schema_version', 'id', 'label', 'detection', 'permission_overlays', 'project_context'] as $field) {
        if (!array_key_exists($field, $descriptor)) {
            throw new InvalidArgumentException(sprintf('%s: stack descriptor missing required field %s', $source, $field));
        }
    }

    if (($descriptor['schema_version'] ?? null) !== 1) {
        throw new InvalidArgumentException(sprintf('%s: stack descriptor schema_version must be 1', $source));
    }

    $id = (string) $descriptor['id'];
    if (!preg_match('/^[a-z][a-z0-9-]*$/', $id)) {
        throw new InvalidArgumentException(sprintf('%s: invalid stack id %s', $source, $id));
    }

    foreach (['detection', 'permission_overlays', 'project_context'] as $objectField) {
        if (!is_array($descriptor[$objectField])) {
            throw new InvalidArgumentException(sprintf('%s: %s must be an object', $source, $objectField));
        }
    }

    foreach (($descriptor['version_checks'] ?? []) as $check) {
        if (!is_array($check)) {
            throw new InvalidArgumentException(sprintf('%s: version_checks entries must be objects', $source));
        }
        aiStackValidateVersionCheck($check, $source);
    }
}

/** @param array<string,mixed> $check */
function aiStackValidateVersionCheck(array $check, string $source = '<memory>'): void
{
    foreach (['id', 'tool', 'args', 'required'] as $field) {
        if (!array_key_exists($field, $check)) {
            throw new InvalidArgumentException(sprintf('%s: version check missing %s', $source, $field));
        }
    }

    if (!preg_match('/^[a-z][a-z0-9-]*$/', (string) $check['id'])) {
        throw new InvalidArgumentException(sprintf('%s: invalid version check id', $source));
    }
    if (!preg_match('/^[A-Za-z0-9._+-]+$/', (string) $check['tool'])) {
        throw new InvalidArgumentException(sprintf('%s: invalid version check tool', $source));
    }
    if (!is_array($check['args'])) {
        throw new InvalidArgumentException(sprintf('%s: version check args must be an array', $source));
    }
    $args = array_values(array_map('strval', $check['args']));
    $allowed = [[], ['--version'], ['-v'], ['version']];
    if (!in_array($args, $allowed, true)) {
        throw new InvalidArgumentException(sprintf('%s: unsafe version check args for %s', $source, (string) $check['tool']));
    }
    if (!is_bool($check['required'])) {
        throw new InvalidArgumentException(sprintf('%s: version check required must be boolean', $source));
    }
}

/** @param array<string,array<string,mixed>> $registry @param array<string,mixed> $descriptor */
function aiStackAddDescriptor(array &$registry, array $descriptor): void
{
    $id = (string) $descriptor['id'];
    if (!isset($registry[$id])) {
        $registry[$id] = $descriptor;
        return;
    }

    $isLocal = ($descriptor['_source'] ?? '') === 'local';
    $explicitOverride = ($descriptor['override'] ?? false) === true || isset($descriptor['extends']);
    if (!$isLocal || !$explicitOverride) {
        throw new RuntimeException(sprintf('Duplicate stack id without explicit local override: %s', $id));
    }

    $registry[$id] = array_replace_recursive($registry[$id], $descriptor);
}

function aiStackNormalizeRoot(string $root): string
{
    return rtrim(str_replace('\\', '/', (string) (realpath($root) ?: $root)), '/');
}

function aiStackRelativePath(string $path, string $root): string
{
    $path = str_replace('\\', '/', $path);
    $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
    return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
}
