<?php

declare(strict_types=1);

require_once __DIR__ . '/compose.php';

/**
 * Harness-agnostic projection adapters over a composed permission model
 * (tools/ai/install/permission-layers/compose.php).
 *
 * Contract (docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/plan.md,
 * Slice 8): each adapter is a pure function of the composed model — no adapter re-reads
 * frontmatter. Adding a new harness is one callable in aiPermissionRenderAdapters(), one
 * renderer file, and one round-trip test; no change to layers, compositions, or the
 * generator itself.
 *
 * @return array<string,callable>
 */
function aiPermissionRenderAdapters(): array
{
    return [
        'opencode' => 'aiPermissionRenderOpenCodeBlock',
        'copilot' => 'aiPermissionAllowedBashFromModel',
        'claude' => 'aiPermissionAllowedBashFromModel',
    ];
}

/**
 * Renders the OpenCode `permission:` frontmatter block from a composed model.
 *
 * Moved from tools/ai/generate-agent-permissions.php (Slice 2) into this shared adapter
 * seam (Slice 8) without behavior change; the generator now requires this file instead of
 * defining the function locally, so all three runtime projections share one adapter home.
 *
 * Slice B (docs/tickets/arch-todo-complete-permission-composition-migration/plan.md) adds
 * two optional, additive render-spec keys, both defaulting to `[]` so every one of the 13
 * pre-existing render specs (none of which set them) renders byte-identically to before:
 *   - `extra_scalars_before_edit`: an ordered `key: value` map emitted between `todowrite:`
 *     and `edit:` (only `architecture-plan-writer` needs `task:` in this position — every
 *     other migrated agent's `task`/other scalars render via the existing `extra_scalars`
 *     key, unchanged, after `edit:`).
 *   - `external_directory`: an ordered `pattern: effect` map emitted as a nested mapping
 *     immediately after `edit:` and before `extra_scalars`/`bash:`. This is a pure render-spec
 *     concept, not part of the composed model — it carries no reuse across agents today (only
 *     `architecture-plan-writer` needs a nested mapping; `script-runner` already renders a
 *     scalar `external_directory: allow` via the pre-existing flat `extra_scalars` mechanism,
 *     untouched here) — so it does not need layer/pack/exception machinery or hard-deny-floor
 *     participation.
 *
 * @param array<string,array{permission:string,pattern:string,effect:string,class:string,layer:string}> $model
 * @param array{extra_scalars:array<string,string>,quote:string,extra_scalars_before_edit?:array<string,string>,external_directory?:array<string,string>} $render
 */
function aiPermissionRenderOpenCodeBlock(array $model, array $render): string
{
    $quote = $render['quote'] === 'double' ? '"' : "'";
    $lines = ['permission:'];
    $lines[] = '  todowrite: allow';

    foreach ($render['extra_scalars_before_edit'] ?? [] as $key => $value) {
        $lines[] = '  ' . $key . ': ' . $value;
    }

    $editEntries = array_values(array_filter($model, static fn (array $e): bool => $e['permission'] === 'edit'));
    // Cosmetic normalization (matches every currently shipped read-only agent's convention):
    // when the edit surface reduces to exactly the universal deny entry, render the scalar
    // shorthand `edit: deny` instead of a one-entry mapping. Semantically identical either way.
    $isUniversalEditDenyOnly = count($editEntries) === 1
        && $editEntries[0]['pattern'] === '*'
        && $editEntries[0]['effect'] === 'deny';
    if ($editEntries === [] || $isUniversalEditDenyOnly) {
        $lines[] = '  edit: deny';
    } else {
        $lines[] = '  edit:';
        foreach ($editEntries as $entry) {
            $lines[] = '    ' . $quote . $entry['pattern'] . $quote . ': ' . $entry['effect'];
        }
    }

    $externalDirectory = $render['external_directory'] ?? [];
    if ($externalDirectory !== []) {
        $lines[] = '  external_directory:';
        foreach ($externalDirectory as $pattern => $effect) {
            $lines[] = '    ' . $quote . $pattern . $quote . ': ' . $effect;
        }
    }

    foreach ($render['extra_scalars'] as $key => $value) {
        $lines[] = '  ' . $key . ': ' . $value;
    }

    // Any bash entry whose effect exactly matches the '*' wildcard's effect is a no-op
    // restatement — omitting it shrinks the rendered file with zero behavior change. This
    // mirrors what hand-authored agent files already did (they never explicitly restated
    // every hard-deny entry either), and is required to keep composed agents under the
    // shipped .opencode/agents/*.md line budget (docs/ai/ai-file-standards.md).
    $starEffect = $model[aiPermissionModelKey('bash', '*')]['effect'] ?? null;

    // OpenCode's permission engine resolves bash rules by `.findLast()` over the declared
    // ruleset in file order (confirmed against opencode's own permission/index.ts
    // `evaluate()` and its docs: "Rules are evaluated by pattern match, with the last
    // matching rule winning... put the catch-all rule first, more specific rules after
    // it"). The '*' entry is therefore emitted FIRST, immediately after `bash:` — every
    // more specific entry that follows it in the array (in unchanged relative order)
    // correctly overrides it. Emitting '*' anywhere else (previously: wherever it fell in
    // $model's composition order, which was last for every affected agent) would make it
    // silently override every specific allow/ask rule declared before it instead of the
    // other way around, since '*' matches every possible command string.
    $lines[] = '  bash:';
    if ($starEffect !== null) {
        $lines[] = '    ' . $quote . '*' . $quote . ': ' . $starEffect;
    }
    foreach ($model as $entry) {
        if ($entry['permission'] !== 'bash' || $entry['pattern'] === '*') {
            continue;
        }
        // A bash entry whose effect equals the '*' floor is normally a no-op restatement and is
        // dropped (line-budget optimization above). The one exception is a `backstop`-class entry:
        // under OpenCode's `.findLast()` file-order resolution, a deny placed AFTER an overlapping
        // broad allow (e.g. a secret-path deny after `preview-file.sh *: allow`) is load-bearing —
        // it flips the match result from allow to deny — even when its effect equals the floor's
        // deny. Retaining it is what makes the secret-read backstop a real permission-level guard
        // rather than a stripped no-op. Retention is keyed on the model `class` only, never on pack
        // name, preserving this renderer's pure-function-of-the-model contract (see file header).
        $isLoadBearingBackstop = $entry['class'] === 'backstop';
        if ($starEffect !== null && $entry['effect'] === $starEffect && !$isLoadBearingBackstop) {
            continue;
        }
        $lines[] = '    ' . $quote . $entry['pattern'] . $quote . ': ' . $entry['effect'];
    }

    return implode("\n", $lines);
}

/**
 * Returns the bash patterns that are `allow`-effect in a composed model, in merge order,
 * excluding the universal `*` floor entry — the shared "allowedBash" projection consumed by
 * both Copilot's Shell Boundary section and Claude's Bash Command Policy body. This replaces
 * re-parsing rendered frontmatter text (tools/ai/install/canonical-agent-frontmatter.php's
 * `allowedBash`, which is single-quote-only and silently returns an empty list for any agent
 * rendered with `quote: 'double'`, e.g. researcher.md since Slice 2 — see the Slice 8 bug-fix
 * note in the plan).
 *
 * @param array<string,array{permission:string,pattern:string,effect:string,class:string,layer:string}> $model
 * @return list<string>
 */
function aiPermissionAllowedBashFromModel(array $model): array
{
    $allowed = [];
    foreach ($model as $entry) {
        if ($entry['permission'] !== 'bash' || $entry['effect'] !== 'allow') {
            continue;
        }
        if ($entry['pattern'] === '*') {
            continue;
        }
        $allowed[] = $entry['pattern'];
    }

    return $allowed;
}

/**
 * Resolves the `allowedBash` list a Copilot/Claude renderer should use for one agent:
 * the composed-model projection when that agent has a registered composition
 * (aiPermissionAgentCompositions()), otherwise the legacy frontmatter-parsed list unchanged.
 * This is the fallback seam that lets migrated and not-yet-migrated agents render correctly
 * side by side during the Slice 3/4 rollout (agent keys are filename stems, never frontmatter
 * `id`; callers must pass the filename stem, matching the compositions keying rule).
 *
 * @param list<string> $legacyAllowedBash
 * @return list<string>
 */
function aiPermissionResolveAllowedBash(string $agentId, array $legacyAllowedBash): array
{
    $compositions = aiPermissionAgentCompositions();
    if (!array_key_exists($agentId, $compositions)) {
        return $legacyAllowedBash;
    }

    $composed = aiPermissionCompose($agentId);

    return aiPermissionAllowedBashFromModel($composed['model']);
}

/**
 * Projects a set of per-agent composed permission models into the Claude Code
 * `.claude/settings.json` `permissions.allow`/`permissions.deny` shape (plan-28 Phase 2 —
 * docs/tickets/claude-agent-fleet-remediation/plan-28-permission-sot-and-render-parity-sync.md).
 *
 * `allow` is the union, across every supplied agent model, of that agent's allowed bash
 * patterns via `aiPermissionAllowedBashFromModel()` — the SAME per-agent projection each
 * agent's own rendered Bash Command Policy body already uses (aiPermissionResolveAllowedBash()
 * above). Because every agent's rendered allowedBash list is built from exactly this
 * function, unioning it here makes every agent's rendered list a subset of this floor by
 * construction (Contracts And Boundaries: "Enforced-vs-advisory").
 *
 * `deny` is the immutable `core:hard-deny` bash-deny floor (aiPermissionLayersCore()),
 * excluding the universal `*` catch-all entry — a literal `Bash(*)` deny would contradict
 * the allow list entirely, and Claude's schema has no floor-wildcard concept the way
 * OpenCode's `bash: '*': deny` does.
 *
 * Pure function of its input models: never re-parses rendered text, never re-reads
 * frontmatter itself (callers resolve that, exactly as the Claude/Copilot renderers already
 * do). Output arrays are sorted for deterministic, byte-stable generation.
 *
 * @param array<string,array<string,array{permission:string,pattern:string,effect:string,class:string,layer:string}>> $perAgentModels
 * @return array{allow:list<string>,deny:list<string>}
 */
function aiPermissionClaudeSettingsFromModels(array $perAgentModels): array
{
    $allowSet = [];
    foreach ($perAgentModels as $model) {
        foreach (aiPermissionAllowedBashFromModel($model) as $pattern) {
            $allowSet[$pattern] = true;
        }
    }

    $denySet = [];
    foreach (aiPermissionLayersCore()['hard-deny'] as $entry) {
        if ($entry['permission'] !== 'bash' || $entry['effect'] !== 'deny' || $entry['pattern'] === '*') {
            continue;
        }
        $denySet[$entry['pattern']] = true;
    }

    $wrapBash = static fn (string $pattern): string => 'Bash(' . $pattern . ')';
    $allow = array_map($wrapBash, array_keys($allowSet));
    $deny = array_map($wrapBash, array_keys($denySet));
    sort($allow, SORT_STRING);
    sort($deny, SORT_STRING);

    return ['allow' => $allow, 'deny' => $deny];
}
