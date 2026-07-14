<?php

declare(strict_types=1);

/**
 * Validation for normalized installer registry entries.
 *
 * Split out of registry.php so the rules that guard the pack data live in one
 * place. Throws on any malformed field; callers rely on the exception, not a
 * return value.
 */

if (!function_exists('aiInstallerValidateRegistryEntry')) {
    /**
     * Validate a normalized installer registry entry, throwing on any malformed field.
     *
     * @param array<string, mixed> $entry
     */
    function aiInstallerValidateRegistryEntry(array $entry): void
    {
        if (!in_array($entry['type'] ?? null, ['file', 'dir'], true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid installer entry type for "%s".',
                (string) ($entry['source'] ?? '<unknown>'),
            ));
        }

        if (!in_array($entry['merge_strategy'] ?? null, ['replace', 'skip-if-exists'], true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid merge strategy for "%s".',
                (string) ($entry['source'] ?? '<unknown>'),
            ));
        }

        foreach (['source', 'target'] as $key) {
            if (!is_string($entry[$key] ?? null) || $entry[$key] === '') {
                throw new InvalidArgumentException(sprintf('Installer entry requires a non-empty %s.', $key));
            }
        }

        foreach (['core', 'required'] as $key) {
            if (!is_bool($entry[$key] ?? null)) {
                throw new InvalidArgumentException(sprintf(
                    'Installer entry "%s" must contain a boolean %s.',
                    (string) $entry['source'],
                    $key,
                ));
            }
        }
    }
}

if (!function_exists('aiInstallerValidatePackRegistry')) {
    /**
     * Validate an assembled pack registry, returning a list of human-readable
     * problems (empty when clean). Checks the required fields per entry and that
     * no entry ships into the reserved user namespace.
     *
     * @param array<string, mixed> $registry
     * @return list<string>
     */
    function aiInstallerValidatePackRegistry(array $registry): array
    {
        $errors = [];
        foreach ($registry as $packId => $items) {
            if (!is_array($items)) {
                $errors[] = "pack {$packId} must be a list";
                continue;
            }
            foreach ($items as $index => $item) {
                foreach (['source', 'target', 'merge_strategy', 'required'] as $field) {
                    if (!array_key_exists($field, $item)) {
                        $errors[] = "pack {$packId} item {$index} missing {$field}";
                    }
                }
                $target = (string) ($item['target'] ?? '');
                if ($target !== '' && aiInstallerIsReservedUserNamespace($target)) {
                    $errors[] = "pack {$packId} item {$index} ships into the reserved user namespace: {$target}";
                }
            }
        }
        return $errors;
    }
}

if (!function_exists('aiInstallerIsReservedUserNamespace')) {
    /**
     * The kit reserves a private namespace for user-authored AI content so it never collides
     * with shipped files: `local-*` basenames, `*.local.*` files, and any path under a `local/`
     * directory. The kit must never ship into these.
     */
    function aiInstallerIsReservedUserNamespace(string $path): bool
    {
        $normalized = str_replace('\\', '/', trim($path));
        if ($normalized === '') {
            return false;
        }

        if (preg_match('#(^|/)local/#', $normalized) === 1) {
            return true;
        }

        $basename = basename($normalized);
        if (str_starts_with($basename, 'local-')) {
            return true;
        }
        if (preg_match('/\.local\./', $basename) === 1) {
            return true;
        }

        return false;
    }
}
