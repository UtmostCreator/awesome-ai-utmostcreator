<?php

declare(strict_types=1);

/**
 * Agent-composition-spec builders that collapse the repeated
 * `{compose_spec: {profile, edit_surface, verify_tier, shipped_star_baseline, deny_packs,
 * allow_packs, ask_packs, exceptions}, render: {...}}` shape in compositions.php into one
 * call per agent (readonly-profile vs impl-profile), using PHP 8 named arguments so an
 * agent entry states only what differs from its profile's defaults instead of restating
 * every key verbatim. Pure structural sugar over aiPermissionComposeFromSpec()'s existing
 * spec shape (compose.php) — no new composition semantics, no behavior change.
 */

/**
 * @param array{extra_scalars:array<string,string>,quote:string} $render
 * @param list<string> $denyPacks
 * @param list<string> $allowPacks
 * @param list<string> $askPacks
 * @param list<array{permission:string,pattern:string,effect:string,why?:string}> $exceptions
 * @return array{compose_spec:array<string,mixed>,render:array{extra_scalars:array<string,string>,quote:string}}
 */
function aiPermissionAgentSpecReadonly(
    string $editSurface,
    array $render,
    string $starBaseline = 'deny',
    array $denyPacks = [],
    array $allowPacks = [],
    array $askPacks = [],
    array $exceptions = [],
    string $verifyTier = 'verify-none',
    string $cliTools = '',
    array $languageOverlays = []
): array {
    $composeSpec = [
        'profile' => 'readonly',
        'edit_surface' => $editSurface,
        'verify_tier' => $verifyTier,
        'shipped_star_baseline' => $starBaseline,
        'deny_packs' => $denyPacks,
        'allow_packs' => $allowPacks,
        'ask_packs' => $askPacks,
        'exceptions' => $exceptions,
    ];
    if ($cliTools !== '') {
        $composeSpec['cli_tools'] = $cliTools;
    }
    if ($languageOverlays !== []) {
        $composeSpec['language_overlays'] = $languageOverlays;
    }

    return [
        'compose_spec' => $composeSpec,
        'render' => $render,
    ];
}

/**
 * profile 'verify' — currently only `config-maintainer` uses this profile; kept as its own
 * small builder (mirroring readonly/impl) rather than a generalized profile parameter, so
 * every builder's name states its profile unambiguously and a future agent cannot silently
 * end up on the wrong profile via a typo'd string argument.
 *
 * @param array{extra_scalars:array<string,string>,quote:string} $render
 * @param list<string> $denyPacks
 * @param list<string> $allowPacks
 * @param list<string> $askPacks
 * @param list<array{permission:string,pattern:string,effect:string,why?:string}> $exceptions
 * @return array{compose_spec:array<string,mixed>,render:array{extra_scalars:array<string,string>,quote:string}}
 */
function aiPermissionAgentSpecVerify(
    string $editSurface,
    array $render,
    string $starBaseline = 'deny',
    array $denyPacks = [],
    array $allowPacks = [],
    array $askPacks = [],
    array $exceptions = [],
    string $verifyTier = 'verify-none',
    array $languageOverlays = []
): array {
    $composeSpec = [
        'profile' => 'verify',
        'edit_surface' => $editSurface,
        'verify_tier' => $verifyTier,
        'shipped_star_baseline' => $starBaseline,
        'deny_packs' => $denyPacks,
        'allow_packs' => $allowPacks,
        'ask_packs' => $askPacks,
        'exceptions' => $exceptions,
    ];
    if ($languageOverlays !== []) {
        $composeSpec['language_overlays'] = $languageOverlays;
    }

    return [
        'compose_spec' => $composeSpec,
        'render' => $render,
    ];
}

/**
 * @param array{extra_scalars:array<string,string>,quote:string} $render
 * @param list<string> $denyPacks
 * @param list<string> $allowPacks
 * @param list<string> $askPacks
 * @param list<array{permission:string,pattern:string,effect:string,why?:string}> $exceptions
 * @return array{compose_spec:array<string,mixed>,render:array{extra_scalars:array<string,string>,quote:string}}
 */
function aiPermissionAgentSpecImpl(
    string $editSurface,
    array $render,
    string $starBaseline = 'deny',
    array $denyPacks = [],
    array $allowPacks = [],
    array $askPacks = [],
    array $exceptions = [],
    string $verifyTier = 'verify-none',
    string $cliTools = '',
    array $languageOverlays = []
): array {
    $composeSpec = [
        'profile' => 'impl',
        'edit_surface' => $editSurface,
        'verify_tier' => $verifyTier,
        'shipped_star_baseline' => $starBaseline,
        'deny_packs' => $denyPacks,
        'allow_packs' => $allowPacks,
        'ask_packs' => $askPacks,
        'exceptions' => $exceptions,
    ];
    if ($cliTools !== '') {
        $composeSpec['cli_tools'] = $cliTools;
    }
    if ($languageOverlays !== []) {
        $composeSpec['language_overlays'] = $languageOverlays;
    }

    return [
        'compose_spec' => $composeSpec,
        'render' => $render,
    ];
}
