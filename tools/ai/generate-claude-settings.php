<?php

declare(strict_types=1);

/**
 * Generates `permissions.allow`/`permissions.deny` in
 * `packages/ai-universal-rules/templates/claude/settings.json` from the composed permission
 * model (plan-28 Phase 2 — see
 * docs/tickets/claude-agent-fleet-remediation/plan-28-permission-sot-and-render-parity-sync.md).
 *
 * `$schema` and `hooks` remain hand-authored; only `permissions.allow`/`permissions.deny`
 * become generated output. The install-time union-merge (`claude-settings-merge.php`) is
 * UNCHANGED — it still unions this GENERATED template floor with any third-party (e.g.
 * graphify) entries an installed project's `.claude/settings.json` already carries.
 *
 * `allow` = union, across every shipped Claude agent (the same 24-agent enumeration Phase
 * 1's `tools/ai/render-adapters.php` uses: the two agent-template source dirs filtered by
 * `aiAgentIsHiddenInternalOnly()`), of that agent's resolved allowedBash list. Agents with a
 * registered composition (`aiPermissionAgentCompositions()`) use the real composed model;
 * the remaining shipped agents that have not yet migrated to the composed model (currently:
 * `agent-definition-reviewer`, `fleet-assessor`) fall back to their legacy frontmatter-parsed
 * allowedBash list (`aiInstallerParseCanonicalAgentFrontmatter()`) wrapped as a minimal
 * single-layer model — the SAME source-frontmatter parse `aiPermissionResolveAllowedBash()`
 * itself falls back to inside the Claude/Copilot renderers, so this stays a projection of
 * source frontmatter, never rendered output. Either way every agent's resolved allowedBash
 * feeds into `aiPermissionClaudeSettingsFromModels()`, so every agent's rendered Bash
 * Command Policy body is a subset of the generated floor by construction.
 *
 * `deny` = the immutable `core:hard-deny` bash-deny floor.
 *
 * Union-with-existing, never full replacement (safety-critical): the composed model only
 * covers OpenCode-shaped `bash`/`edit` permission entries. It has no equivalent at all for
 * Claude's separate `Read(...)`/`Edit(...)`/`Write(...)` path-glob deny syntax (secret files,
 * generated files, lockfiles, `tools/ai/**`, ...; NOTE: `packages/**` is deliberately NOT in
 * this deny list — this repo develops the AI kit whose source lives under `packages/`, and the
 * editor agents (implementer/refactorer/bootstrapper, editSurface:'code') must be able to edit
 * it on every provider, so no `Edit(packages/**)`/`Write(packages/**)` guard is curated here),
 * and several hand-curated
 * bash entries (`curl *`, `wget *`, `git branch -d/-D/--delete/-m/-M/--move *`,
 * `git push --force*`, `git reset --hard*`, `git clean -f*`, various `npm`/`pnpm`/`yarn`/`bun`
 * test invocations, alternate script-invocation spellings, ...) predate the composed model and
 * are not yet derivable from any agent's frontmatter. A full replacement from the projection
 * alone was verified (2026-07-09, plan-28 Phase 2 implementation) to DROP 75 deny entries
 * (including every secret/generated-file `Read`/`Edit`/`Write` guard) and narrow several allow
 * entries — an unacceptable regression for a MEDIUM/HIGH-risk enforced-floor generator. This
 * tool therefore computes `existing ∪ composed` for both `allow` and `deny`, so the on-disk
 * template only ever grows (a true superset-or-equal of whatever it already allowed/denied);
 * it never removes or narrows a prior grant or guard. Removing an entry that composition later
 * supersedes remains a deliberate, human-reviewed edit to this file, not something `--write`
 * does automatically.
 *
 * Usage:
 *   php tools/ai/generate-claude-settings.php --check
 *   php tools/ai/generate-claude-settings.php --write
 */

require_once __DIR__ . '/install/permission-layers/render-adapters.php';
require_once __DIR__ . '/install/canonical-agent-frontmatter.php';
require_once __DIR__ . '/install/copilot-agent-renderer.php'; // aiAgentIsHiddenInternalOnly

$root = realpath(__DIR__ . '/../..');
if ($root === false) {
    fwrite(STDERR, "ERROR: could not resolve repository root\n");
    exit(1);
}

$check = in_array('--check', $argv, true);
$write = in_array('--write', $argv, true);
if (!$check && !$write) {
    $check = true;
}

$settingsPath = $root . '/packages/ai-universal-rules/templates/claude/settings.json';
if (!is_file($settingsPath)) {
    fwrite(STDERR, "ERROR: missing settings template: {$settingsPath}\n");
    exit(1);
}

$existingRaw = (string) file_get_contents($settingsPath);
$existing = json_decode($existingRaw, true);
if (!is_array($existing)) {
    fwrite(STDERR, "ERROR: failed to parse existing settings JSON: {$settingsPath}\n");
    exit(1);
}

$perAgentModels = aiGenerateClaudeSettingsPerAgentModels($root);
$projection = aiPermissionClaudeSettingsFromModels($perAgentModels);

if ($perAgentModels === []) {
    fwrite(STDERR, "ERROR: no shipped agent templates found under packages/ai-universal-rules/templates/{core,optional}/agents\n");
    exit(1);
}

$existingAllow = is_array($existing['permissions']['allow'] ?? null) ? $existing['permissions']['allow'] : [];
$existingDeny = is_array($existing['permissions']['deny'] ?? null) ? $existing['permissions']['deny'] : [];

// Union-with-existing (see file header): never a full replacement, so the template only ever
// grows and no prior allow/deny entry is ever silently dropped or narrowed.
$finalAllow = array_values(array_unique(array_merge($existingAllow, $projection['allow'])));
$finalDeny = array_values(array_unique(array_merge($existingDeny, $projection['deny'])));
sort($finalAllow, SORT_STRING);
sort($finalDeny, SORT_STRING);

$removedAllow = array_values(array_diff($existingAllow, $finalAllow));
$removedDeny = array_values(array_diff($existingDeny, $finalDeny));
if ($removedAllow !== [] || $removedDeny !== []) {
    // Structurally unreachable given the union-merge above (finalAllow/finalDeny always
    // contain every existing entry), but asserted explicitly so a future refactor of this
    // file cannot silently reintroduce a narrowing full-replacement without tripping this.
    fwrite(STDERR, "ERROR: internal invariant violated — union-merge would remove existing entries:\n");
    foreach ($removedAllow as $e) {
        fwrite(STDERR, "  - allow: {$e}\n");
    }
    foreach ($removedDeny as $e) {
        fwrite(STDERR, "  - deny: {$e}\n");
    }
    exit(1);
}

$existing['permissions']['allow'] = $finalAllow;
$existing['permissions']['deny'] = $finalDeny;

$rendered = json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

if ($write) {
    if (file_put_contents($settingsPath, $rendered) === false) {
        fwrite(STDERR, "ERROR: failed to write {$settingsPath}\n");
        exit(1);
    }
    echo 'OK: wrote permissions.allow/permissions.deny (' . count($finalAllow) . ' allow, ' . count($finalDeny) . " deny; existing ∪ composed floor) to {$settingsPath}\n";
    exit(0);
}

if ($rendered !== $existingRaw) {
    fwrite(STDERR, "ERROR: {$settingsPath} permissions.allow/permissions.deny are out of date with the composed projection. Run: php tools/ai/generate-claude-settings.php --write\n");
    exit(1);
}

echo "OK: {$settingsPath} permissions.allow/permissions.deny match the composed projection\n";
exit(0);

/**
 * Builds the per-agent composed-model input for `aiPermissionClaudeSettingsFromModels()`:
 * one entry per shipped Claude agent (the same 24-agent enumeration Phase 1's
 * `tools/ai/render-adapters.php` uses), keyed by agent id.
 *
 * @return array<string,array<string,array{permission:string,pattern:string,effect:string,class:string,layer:string}>>
 */
function aiGenerateClaudeSettingsPerAgentModels(string $root): array
{
    $sourceDirs = [
        $root . '/packages/ai-universal-rules/templates/core/agents',
        $root . '/packages/ai-universal-rules/templates/optional/agents',
    ];
    $compositions = aiPermissionAgentCompositions();

    $models = [];
    foreach ($sourceDirs as $srcDir) {
        foreach (glob($srcDir . '/*.md') ?: [] as $srcFile) {
            $agentId = pathinfo($srcFile, PATHINFO_FILENAME);
            $content = (string) file_get_contents($srcFile);
            if (aiAgentIsHiddenInternalOnly($content)) {
                continue;
            }

            if (array_key_exists($agentId, $compositions)) {
                $models[$agentId] = aiPermissionCompose($agentId)['model'];
                continue;
            }

            // Not-yet-migrated shipped agent (currently: agent-definition-reviewer, fleet-assessor):
            // fall back to a minimal single-layer model built from its own legacy
            // frontmatter-parsed allowedBash list, mirroring aiPermissionResolveAllowedBash()'s
            // own fallback so this stays a projection of source frontmatter, not rendered text.
            $parsed = aiInstallerParseCanonicalAgentFrontmatter($content);
            $legacyModel = [];
            foreach ($parsed['allowedBash'] as $pattern) {
                $legacyModel[aiPermissionModelKey('bash', $pattern)] = [
                    'permission' => 'bash',
                    'pattern' => $pattern,
                    'effect' => 'allow',
                    'class' => 'legacy',
                    'layer' => 'legacy:' . $agentId,
                ];
            }
            $models[$agentId] = $legacyModel;
        }
    }

    return $models;
}
