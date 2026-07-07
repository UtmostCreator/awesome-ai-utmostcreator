<?php

declare(strict_types=1);

require_once __DIR__ . '/core.php';
require_once __DIR__ . '/edit-surfaces.php';
require_once __DIR__ . '/verify-tiers.php';
require_once __DIR__ . '/language-overlays.php';
require_once __DIR__ . '/script-tiers.php';
require_once __DIR__ . '/stack-overlays.php';
require_once __DIR__ . '/packs.php';
require_once __DIR__ . '/pack-sets.php';
require_once __DIR__ . '/rules.php';
require_once __DIR__ . '/patterns.php';
require_once __DIR__ . '/agent-spec.php';
require_once __DIR__ . '/render-spec.php';
require_once __DIR__ . '/compositions.php';

/**
 * Compose one agent permission model by filename-stem lookup in
 * aiPermissionAgentCompositions(), for callers that only have an agent id (renderers,
 * validators) rather than a hand-built spec. Agent keys are filename stems, never
 * frontmatter `id` (super-implementer.md ships `id: implementer` while its filename
 * differs — see docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/plan.md,
 * Slice 8).
 *
 * @return array{model:array<string,array{permission:string,pattern:string,effect:string,class:string,layer:string}>,layers:list<string>}
 */
function aiPermissionCompose(string $agent): array
{
    $compositions = aiPermissionAgentCompositions();
    if (!array_key_exists($agent, $compositions)) {
        throw new InvalidArgumentException(sprintf(
            'No permission composition registered for agent: %s (checked filename stem, not frontmatter id)',
            $agent
        ));
    }

    return aiPermissionComposeFromSpec($compositions[$agent]['compose_spec']);
}

/**
 * Compose one agent permission model from explicit slice-1 spec input.
 *
 * `deny_packs`/`allow_packs`/`ask_packs` (Slice 10) are named, reusable, effect-homogeneous
 * rule groups resolved from aiPermissionPacks() — the reuse layer between generic
 * profile/verify/language/stack layers and a genuinely agent-unique `exceptions` entry. See
 * docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/plan.md, Slice 10.
 *
 * @param array{profile?:string,edit_surface?:string,verify_tier?:string,language_overlays?:list<string>,stack_overlays?:list<string>,stack_registry?:array<string,array<string,mixed>>,deny_packs?:list<string>,allow_packs?:list<string>,ask_packs?:list<string>,backstop_deny_packs?:list<string>,exceptions?:list<array{permission:string,pattern:string,effect:string}>,shipped_star_baseline?:string} $spec
 * @return array{model:array<string,array{permission:string,pattern:string,effect:string,class:string,layer:string}>,layers:list<string>}
 */
function aiPermissionComposeFromSpec(array $spec): array
{
    $profile = (string) ($spec['profile'] ?? 'readonly');
    $editSurface = (string) ($spec['edit_surface'] ?? 'none');
    $verifyTier = (string) ($spec['verify_tier'] ?? 'verify-none');
    $cliTools = (string) ($spec['cli_tools'] ?? 'readonly');
    $languageOverlays = $spec['language_overlays'] ?? [];
    $stackOverlays = $spec['stack_overlays'] ?? [];
    $stackRegistry = $spec['stack_registry'] ?? null;
    $denyPacks = $spec['deny_packs'] ?? [];
    $allowPacks = $spec['allow_packs'] ?? [];
    $askPacks = $spec['ask_packs'] ?? [];
    $backstopDenyPacks = $spec['backstop_deny_packs'] ?? [];
    $exceptions = $spec['exceptions'] ?? [];
    $starBaseline = (string) ($spec['shipped_star_baseline'] ?? 'deny');

    aiPermissionAssertEffect($starBaseline);

    $core = aiPermissionLayersCore();
    $scriptTiers = aiPermissionScriptTiers();
    $verifyTiers = aiPermissionVerifyTiers();
    $overlays = aiPermissionLanguageOverlays();
    $editSurfaces = aiPermissionEditSurfaces();

    $model = [];
    $layers = [];

    aiPermissionApplyLayer($model, $layers, 'core:safe-read', $core['safe-read'], 'layer');
    aiPermissionApplyLayer($model, $layers, 'core:git-read', $core['git-read'], 'layer');
    aiPermissionApplyLayer($model, $layers, 'script-tiers:ai-deny-dangerous', $scriptTiers['ai-deny-dangerous'], 'layer');
    aiPermissionApplyLayer($model, $layers, 'script-tiers:ai-context-ask', $scriptTiers['ai-context-ask'], 'layer');

    foreach (aiPermissionProfileLayerNames($profile) as $layerName) {
        if (str_starts_with($layerName, 'script-tiers:')) {
            $key = substr($layerName, strlen('script-tiers:'));
            aiPermissionApplyLayer($model, $layers, $layerName, $scriptTiers[$key] ?? [], 'layer');
            continue;
        }

        if (str_starts_with($layerName, 'core:')) {
            $key = substr($layerName, strlen('core:'));
            aiPermissionApplyLayer($model, $layers, $layerName, $core[$key] ?? [], 'layer');
        }
    }

    aiPermissionApplyLayer($model, $layers, 'verify-tiers:' . $verifyTier, aiPermissionNamedLayer($verifyTiers, $verifyTier, 'verify tier'), 'layer');

    $cliToolsKey = 'shipped-cli-' . $cliTools;
    aiPermissionApplyLayer($model, $layers, 'core:' . $cliToolsKey, aiPermissionNamedLayer($core, $cliToolsKey, 'cli tools variant'), 'layer');

    if ($stackOverlays !== []) {
        $stackEntries = aiPermissionStackOverlayEntries($stackOverlays, null, $stackRegistry);
        aiPermissionApplyLayer($model, $layers, 'stack-overlays:' . implode('+', $stackOverlays), $stackEntries, 'layer');
    }

    foreach ($languageOverlays as $overlay) {
        $overlayName = (string) $overlay;
        aiPermissionApplyLayer($model, $layers, 'language-overlays:' . $overlayName, aiPermissionNamedLayer($overlays, $overlayName, 'language overlay'), 'layer');
    }

    aiPermissionApplyLayer($model, $layers, 'edit-surfaces:' . $editSurface, aiPermissionNamedLayer($editSurfaces, $editSurface, 'edit surface'), 'layer');

    if ($denyPacks !== []) {
        aiPermissionApplyLayer($model, $layers, 'agent:deny-packs:' . implode('+', $denyPacks), aiPermissionResolvePacks($denyPacks), 'pack');
    }
    if ($allowPacks !== []) {
        aiPermissionApplyLayer($model, $layers, 'agent:allow-packs:' . implode('+', $allowPacks), aiPermissionResolvePacks($allowPacks), 'pack');
    }
    if ($askPacks !== []) {
        aiPermissionApplyLayer($model, $layers, 'agent:ask-packs:' . implode('+', $askPacks), aiPermissionResolvePacks($askPacks), 'pack');
    }

    // Backstop deny lane: applied AFTER allow/ask packs (and after the reader allows those packs
    // and the ai-read tier grant) so a secret-path deny inserts into the ordered $model AFTER the
    // overlapping broad reader allow. Under OpenCode's `.findLast()` file-order resolution that
    // ordering is what lets the deny win (BLOCKER B, plan-2-opencode-secret-deny-backstop).
    // Entries carry the `backstop` class so render-adapters.php retains them through the
    // same-as-floor-effect no-op filter on `'*': deny` agents (BLOCKER A). Guarded by a non-empty
    // check (mirroring the deny/allow/ask packs above) so unaffected agents stay byte-identical.
    if ($backstopDenyPacks !== []) {
        aiPermissionApplyLayer($model, $layers, 'agent:backstop-deny-packs:' . implode('+', $backstopDenyPacks), aiPermissionResolvePacks($backstopDenyPacks), 'backstop');
    }

    aiPermissionApplyLayer($model, $layers, 'agent:exceptions', $exceptions, 'exception');

    $hardDeny = aiPermissionHardDenyWithStarBaseline($core['hard-deny'], $starBaseline);
    aiPermissionAssertNoHardDenyWeakening($model, $hardDeny);
    aiPermissionApplyLayer($model, $layers, 'core:hard-deny', $hardDeny, 'floor');

    return ['model' => $model, 'layers' => $layers];
}

/** @return list<string> */
function aiPermissionProfileLayerNames(string $profile): array
{
    return match ($profile) {
        'readonly' => ['script-tiers:ai-read'],
        'verify' => ['script-tiers:ai-read', 'script-tiers:ai-verify'],
        'impl' => ['script-tiers:ai-read', 'script-tiers:ai-verify', 'script-tiers:ai-write', 'core:git-mutating-ask', 'core:package-manager-ask'],
        default => throw new InvalidArgumentException(sprintf('Unknown permission profile: %s', $profile)),
    };
}

/**
 * @param array<string,list<array{permission:string,pattern:string,effect:string}>> $layers
 * @return list<array{permission:string,pattern:string,effect:string}>
 */
function aiPermissionNamedLayer(array $layers, string $name, string $kind): array
{
    if (!array_key_exists($name, $layers)) {
        throw new InvalidArgumentException(sprintf('Unknown %s: %s', $kind, $name));
    }

    return $layers[$name];
}

/**
 * @param array<string,array{permission:string,pattern:string,effect:string,class:string,layer:string}> $model
 * @param list<string> $layers
 * @param list<array{permission:string,pattern:string,effect:string}> $entries
 */
function aiPermissionApplyLayer(array &$model, array &$layers, string $layer, array $entries, string $class): void
{
    $layers[] = $layer;
    foreach ($entries as $entry) {
        aiPermissionAssertEntry($entry);
        $key = aiPermissionModelKey($entry['permission'], $entry['pattern']);
        $model[$key] = [
            'permission' => $entry['permission'],
            'pattern' => $entry['pattern'],
            'effect' => $entry['effect'],
            'class' => $class,
            'layer' => $layer,
        ];
    }
}

function aiPermissionModelKey(string $permission, string $pattern): string
{
    return $permission . "\0" . $pattern;
}

/** @param array{permission:string,pattern:string,effect:string} $entry */
function aiPermissionAssertEntry(array $entry): void
{
    foreach (['permission', 'pattern', 'effect'] as $field) {
        if (!isset($entry[$field]) || !is_string($entry[$field]) || $entry[$field] === '') {
            throw new InvalidArgumentException(sprintf('Permission entry field %s must be a non-empty string.', $field));
        }
    }
    aiPermissionAssertEffect($entry['effect']);
}

function aiPermissionAssertEffect(string $effect): void
{
    if (!array_key_exists($effect, aiPermissionEffectRanks())) {
        throw new InvalidArgumentException(sprintf('Unknown permission effect: %s', $effect));
    }
}

/** @return array<string,int> */
function aiPermissionEffectRanks(): array
{
    return ['deny' => 0, 'ask' => 1, 'allow' => 2];
}

/**
 * @param list<array{permission:string,pattern:string,effect:string}> $hardDeny
 * @return list<array{permission:string,pattern:string,effect:string}>
 */
function aiPermissionHardDenyWithStarBaseline(array $hardDeny, string $starBaseline): array
{
    return array_map(static function (array $entry) use ($starBaseline): array {
        if ($entry['permission'] === 'bash' && $entry['pattern'] === '*') {
            $entry['effect'] = $starBaseline;
        }

        return $entry;
    }, $hardDeny);
}

/**
 * @param array<string,array{permission:string,pattern:string,effect:string,class:string,layer:string}> $model
 * @param list<array{permission:string,pattern:string,effect:string}> $hardDeny
 */
function aiPermissionAssertNoHardDenyWeakening(array $model, array $hardDeny): void
{
    $ranks = aiPermissionEffectRanks();
    foreach ($hardDeny as $entry) {
        $key = aiPermissionModelKey($entry['permission'], $entry['pattern']);
        if (!isset($model[$key])) {
            continue;
        }

        if ($ranks[$model[$key]['effect']] > $ranks[$entry['effect']]) {
            throw new RuntimeException(sprintf(
                'Layer %s weakens immutable floor %s %s from %s to %s.',
                $model[$key]['layer'],
                $entry['permission'],
                $entry['pattern'],
                $entry['effect'],
                $model[$key]['effect']
            ));
        }
    }
}
