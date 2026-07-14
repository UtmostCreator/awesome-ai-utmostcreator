<?php

declare(strict_types=1);

require_once __DIR__ . '/profiles.php';
require_once __DIR__ . '/registry-validator.php';
require_once __DIR__ . '/registry-loader.php';

// Retained for backward compatibility with any caller that referenced these
// path prefixes; the pack data itself now lives in registry/*.yaml.
if (!defined('PATH_TO_CORE')) {
    define('PATH_TO_CORE', 'packages/ai-universal-rules/templates/core/');
    define('PATH_TO_TEMPLATES', 'packages/ai-universal-rules/templates/');
    define('PATH_TO_CAP', 'packages/ai-universal-rules/templates/capabilities/');
    define('PATH_TO_SHARED', 'packages/ai-universal-rules/templates/shared/');
    define('TOOLS_AI_INSTALL', 'tools/ai/install/');
    define('SCRIPTS_AI', 'scripts/ai/');
    define('DOCS_CAP', 'docs/ai/capabilities/');
}

/**
 * The registry/*.yaml files, in merge order. Order is significant: it fixes the
 * pack order in the merged registry and is asserted by consumers/tests.
 */
const AI_INSTALLER_REGISTRY_FILES = [
    'setup-docs',
    'capabilities',
    'base',
    'adapters',
    'runtime',
    'policies',
    'optional',
    'distribution',
];

if (!function_exists('aiInstallerRegistryEntry')) {
    /**
     * Normalize one installer registry entry spec into a full entry array.
     *
     * A string spec means `source === target`. Common defaults (`type=file`,
     * `core=false`, `merge_strategy=replace`, `required=true`) are applied once,
     * then per-group `$defaults`, then the spec's own `$overrides` (highest
     * precedence). The returned entry is emitted in a canonical key order
     * (`type, source, target, core, merge_strategy, required, <extras>`) so the
     * whole registry is uniform; consumers read entries by key name, never by
     * position (see aiInstallerValidatePackRegistry and executor.php).
     *
     * @param string|array<string, mixed> $spec
     * @param array<string, mixed> $defaults
     *
     * @return array<string, mixed>
     */
    function aiInstallerRegistryEntry(string|array $spec, array $defaults = []): array
    {
        $overrides = is_string($spec) ? ['source' => $spec] : $spec;

        $entry = array_replace(
            [
                'type' => 'file',
                'core' => false,
                'merge_strategy' => 'replace',
                'required' => true,
            ],
            $defaults,
            $overrides,
        );

        $source = $entry['source'] ?? null;
        if (!is_string($source) || $source === '') {
            throw new InvalidArgumentException('Installer registry entry requires a non-empty source.');
        }
        $entry['target'] ??= $source;

        aiInstallerValidateRegistryEntry($entry);

        // Emit in canonical key order. Known keys first, then any remaining
        // extras (install_type, rename_ext, never_auto_merge, merge_into_existing)
        // in first-seen order.
        $ordered = [];
        foreach (['type', 'source', 'target', 'core', 'merge_strategy', 'required'] as $key) {
            $ordered[$key] = $entry[$key];
        }
        foreach ($entry as $key => $value) {
            if (!array_key_exists($key, $ordered)) {
                $ordered[$key] = $value;
            }
        }

        return $ordered;
    }
}

if (!function_exists('aiInstallerRegistryEntries')) {
    /**
     * Normalize a list of entry specs with a shared default set.
     *
     * @param list<string|array<string, mixed>> $specs
     * @param array<string, mixed> $defaults
     *
     * @return list<array<string, mixed>>
     */
    function aiInstallerRegistryEntries(array $specs, array $defaults = []): array
    {
        return array_map(
            static fn(string|array $spec): array => aiInstallerRegistryEntry($spec, $defaults),
            $specs,
        );
    }
}

if (!function_exists('aiInstallerMergePackRegistries')) {
    /**
     * Merge domain-specific pack registries into one map, failing on duplicate
     * pack names. Unlike array_merge()/array_replace(), a pack defined in two
     * sections is a hard error rather than a silent overwrite.
     *
     * @param array<string, list<array<string, mixed>>> ...$registries
     *
     * @return array<string, list<array<string, mixed>>>
     */
    function aiInstallerMergePackRegistries(array ...$registries): array
    {
        $result = [];
        foreach ($registries as $registry) {
            foreach ($registry as $packName => $entries) {
                if (array_key_exists($packName, $result)) {
                    throw new LogicException(sprintf('Duplicate installer pack "%s".', $packName));
                }
                $result[$packName] = $entries;
            }
        }
        return $result;
    }
}

if (!function_exists('aiInstallerPackRegistry')) {
    /**
     * Assemble the full installer pack registry from the YAML data files.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    function aiInstallerPackRegistry(): array
    {
        $registries = [];
        foreach (AI_INSTALLER_REGISTRY_FILES as $name) {
            $registries[] = aiInstallerLoadRegistryData(__DIR__ . '/registry/' . $name);
        }

        return aiInstallerMergePackRegistries(...$registries);
    }
}

if (!function_exists('aiInstallerResolveSelectedPacks')) {
    /**
     * Resolve the concrete pack ids to install for a config against a registry:
     * expand the profile, apply runtime adapter filtering, add base, and apply
     * explicit --with/--without overrides.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $registry
     * @return list<string>
     */
    function aiInstallerResolveSelectedPacks(array $config, array $registry): array
    {
        $profileDefs = aiInstallerProfileDefinitions();
        $profile = (string) ($config['profile'] ?? 'dual');
        $runtime = (string) ($config['runtime'] ?? 'both');
        $allFeatures = (bool) ($config['allFeatures'] ?? false);

        $packs = $allFeatures
            ? aiInstallerAllFeaturePacks()
            : aiInstallerExpandProfilePacks((array) ($profileDefs[$profile] ?? []), $profileDefs, $registry);

        if (($config['installBase'] ?? true) && !in_array('base', $packs, true)) {
            $packs[] = 'base';
        }

        if ($runtime === 'github-copilot') {
            $packs = array_values(array_filter($packs, static fn(string $p): bool => $p !== 'adapter-opencode' && $p !== 'adapter-claude'));
            if (in_array($profile, ['copilot', 'dual', 'accelerated', 'full-governance'], true) && !in_array('adapter-copilot', $packs, true)) {
                $packs[] = 'adapter-copilot';
            }
        } elseif ($runtime === 'opencode') {
            $packs = array_values(array_filter($packs, static fn(string $p): bool => $p !== 'adapter-copilot' && $p !== 'adapter-claude'));
            if (in_array($profile, ['opencode', 'dual', 'accelerated', 'full-governance'], true) && !in_array('adapter-opencode', $packs, true)) {
                $packs[] = 'adapter-opencode';
            }
        } elseif ($runtime === 'claude-code') {
            // Defense-in-depth only (Claude adapter parity plan, Chosen approach (b)): every profile
            // that ships adapter-claude already bakes it directly into its own definition above, so
            // this branch's job is narrower than the two above it — it only needs to strip the OTHER
            // two adapters when a caller explicitly forces --runtime claude-code, and re-add
            // adapter-claude for profiles that imply "ship some adapter" but do not bake it in
            // (dual/accelerated), mirroring the copilot/opencode branches' shape exactly.
            $packs = array_values(array_filter($packs, static fn(string $p): bool => $p !== 'adapter-copilot' && $p !== 'adapter-opencode'));
            if (in_array($profile, ['claude', 'dual', 'accelerated', 'full-governance'], true) && !in_array('adapter-claude', $packs, true)) {
                $packs[] = 'adapter-claude';
            }
        }

        foreach (($config['withPacks'] ?? []) as $pack) {
            if (!in_array($pack, $packs, true)) {
                $packs[] = $pack;
            }
        }
        foreach (($config['withoutPacks'] ?? []) as $pack) {
            $packs = array_values(array_filter($packs, static fn(string $v): bool => $v !== $pack));
        }

        $packs = aiInstallerExpandProfilePacks($packs, $profileDefs, $registry);
        $packs = array_values(array_unique($packs));
        $packs = array_values(array_filter($packs, static fn(string $pack): bool => isset($registry[$pack])));
        return $packs;
    }
}

if (!function_exists('aiInstallerExpandProfilePacks')) {
    /**
     * Expand any profile names in $items into their constituent pack ids,
     * recursively, de-duplicated, keeping only ids resolvable to a pack or profile.
     *
     * @param list<string> $items
     * @param array<string, mixed> $profileDefs
     * @param array<string, mixed> $registry
     * @return list<string>
     */
    function aiInstallerExpandProfilePacks(array $items, array $profileDefs, array $registry): array
    {
        $expanded = [];
        $queue = array_values($items);
        $seenProfiles = [];

        while ($queue !== []) {
            $item = (string) array_shift($queue);
            if ($item === '') {
                continue;
            }
            if (isset($registry[$item])) {
                $expanded[] = $item;
                continue;
            }
            if (isset($profileDefs[$item])) {
                if (isset($seenProfiles[$item])) {
                    continue;
                }
                $seenProfiles[$item] = true;
                foreach ((array) $profileDefs[$item] as $nested) {
                    $queue[] = (string) $nested;
                }
            }
        }

        return array_values(array_unique($expanded));
    }
}

if (!function_exists('aiInstallerAgentDependencyWarnings')) {
    /**
     * Agents reference scripts/ai/*.sh in their permission allowlists and load capability
     * docs. When an agent pack is selected without scripts-pack, the installed agents will
     * reference commands that are not present. Returns operator-facing warning strings
     * (empty when the selection is coherent). Shared by the install-ai-kit and ai.php
     * install surfaces so the detection stays in one place.
     *
     * @param list<string> $selectedPacks
     * @return list<string>
     */
    function aiInstallerAgentDependencyWarnings(array $selectedPacks): array
    {
        $warnings = [];
        $agentPacks = ['adapter-copilot', 'adapter-opencode', 'optional-agents-opencode-pack', 'optional-agents-copilot-pack', 'optional-agents-claude-pack'];
        if (array_intersect($agentPacks, $selectedPacks) !== [] && !in_array('scripts-pack', $selectedPacks, true)) {
            $warnings[] = 'Agents were installed without scripts-pack: agent permission allowlists reference scripts/ai/*.sh that are not present. Re-run with --with scripts-pack or use an edition that includes it (standard, creator, full, agents-only).';
        }
        return $warnings;
    }
}

if (!function_exists('aiInstallerPackToolRequirements')) {
    /**
     * @param list<string> $selectedPacks
     * @return array{required: list<string>, optional: list<string>}
     */
    function aiInstallerPackToolRequirements(array $selectedPacks): array
    {
        $required = [];
        $optional = [];
        if (in_array('scripts-pack', $selectedPacks, true)) {
            $required = array_merge($required, ['bash', 'git', 'jq', 'rg', 'fd', 'ast-grep', 'repomix', 'scc']);
            $optional = array_merge($optional, ['gh', 'fzf', 'bat', 'delta', 'yq', 'shellcheck', 'semgrep']);
        }
        return [
            'required' => array_values(array_unique($required)),
            'optional' => array_values(array_unique($optional)),
        ];
    }
}
