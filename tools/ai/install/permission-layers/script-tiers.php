<?php

declare(strict_types=1);

require_once __DIR__ . '/core.php';
require_once __DIR__ . '/../script-registry.php';

/**
 * Build AI script permission tiers from the canonical script registry.
 *
 * Script metadata (path, risk, requires_approval, ...) stays in
 * tools/ai/install/script-registry.php as the single source of truth. This file
 * turns that metadata into layer-shaped bash entries, but does NOT grant a script
 * to a tier purely because the registry marks it read-only: ground truth across
 * shipped agents shows real per-agent variation even within one profile (e.g.
 * `reviewer` allows `ai-test-select`/`run-repo-tests`/`gh-pr-context` that
 * `researcher`/`architect`/`workflow-auditor` deny, despite sharing the
 * `readonly` profile). Tiers here represent the common baseline only; agent-
 * specific grants/denials belong in that agent's `compositions.php` exceptions.
 *
 * @return array<string,list<array{permission:string,pattern:string,effect:string}>>
 */
function aiPermissionScriptTiers(): array
{
    $registry = aiInstallerScriptRegistry();

    $tiers = [
        'ai-read' => aiPermissionTierFromIds($registry, aiPermissionAiReadBaselineIds(), 'allow'),
        'ai-context-ask' => aiPermissionTierFromIds($registry, aiPermissionContextPackingIds(), 'ask'),
        'ai-verify' => array_merge(
            aiPermissionTierFromIds($registry, ['ai-test-select', 'run-repo-tests'], 'allow'),
            aiPermissionTierFromIds($registry, ['ai-verify'], 'ask'),
            aiPermissionEntries('bash', [
                'AI_VERIFY_SCOPE=changed VERIFY_SECRETS=0 bash scripts/ai/ai-verify.sh *' => 'allow',
                'env AI_VERIFY_SCOPE=changed VERIFY_SECRETS=0 bash scripts/ai/ai-verify.sh *' => 'allow',
            ])
        ),
        'ai-write' => aiPermissionTierFromIds($registry, ['ai-edit', 'ai-rollback', 'session-checkpoint', 'install-mandatory-tools'], 'ask'),
        'ai-deny-dangerous' => aiPermissionTierFromIds($registry, aiPermissionDangerousScriptIds(), 'deny'),
    ];

    return array_map('aiPermissionUniqueEntries', $tiers);
}

/**
 * Common read-only baseline shared by researcher/architect/workflow-auditor (and,
 * by inclusion, every readonly/verify/impl profile). Agent-specific extras (e.g.
 * reviewer's ai-test-select/run-repo-tests/gh-pr-context) are exceptions, not tier
 * membership, so adding a new script to the registry never silently widens every
 * agent's access.
 *
 * @return list<string>
 */
function aiPermissionAiReadBaselineIds(): array
{
    return [
        'ai-search', 'ai-search-multi', 'preview-file', 'rg-code', 'fd-files', 'query-usage',
        'git-branch-origin', 'git-forensics', 'repo-stats', 'repo-tool-inventory',
        'ai-file-freshness', 'check-file-refs', 'ai-diff-context', 'ai-doc-check',
        'ai-structured', 'repomix-freshness',
    ];
}

/**
 * Context-packing/generation scripts are ask-gated for every profile (ground truth:
 * both researcher and implementer render these as `ask`), regardless of the
 * registry's requires_approval flag for these specific ids.
 *
 * @return list<string>
 */
function aiPermissionContextPackingIds(): array
{
    // NOTE: 'repomix-file' (run-repomix-file.sh) is intentionally excluded — it is not
    // wired into any shipped agent's permissions yet (ground truth check across all
    // agents found zero references); adding it here would silently grant new access.
    return ['pack-context', 'repomix-context', 'repomix-tree', 'repomix-scc-router'];
}

/** @return list<string> */
function aiPermissionDangerousScriptIds(): array
{
    return ['ai-task', 'gh-pr-context', 'pre-tool-use', 'post-tool-use', 'prune-shipped-targets', 'watch-loop', 'common'];
}

/**
 * @param array<string,array<string,mixed>> $registry
 * @param list<string> $ids
 * @return list<array{permission:string,pattern:string,effect:string}>
 */
function aiPermissionTierFromIds(array $registry, array $ids, string $effect): array
{
    $entries = [];
    foreach ($ids as $id) {
        if (!isset($registry[$id])) {
            throw new InvalidArgumentException(sprintf('Unknown script id in permission tier: %s', $id));
        }
        $entry = $registry[$id];
        $path = (string) ($entry['installed_path'] ?? $entry['source_path'] ?? '');
        if ($path === '' || !str_starts_with($path, 'scripts/ai/')) {
            continue;
        }
        foreach (aiPermissionScriptCommandPatterns($path, (bool) ($entry['supports_json'] ?? false)) as $pattern) {
            $entries[] = aiPermissionEntry('bash', $pattern, $effect);
        }
    }

    return $entries;
}

/** @return list<string> */
function aiPermissionScriptCommandPatterns(string $path, bool $supportsJson): array
{
    $patterns = ['bash ' . $path . ($path === 'scripts/ai/run-repo-tests.sh' ? '*' : ' *')];
    if ($supportsJson && in_array(basename($path), ['ai-search.sh', 'ai-search-multi.sh', 'preview-file.sh'], true)) {
        $patterns[] = 'AI_OUTPUT=json bash ' . $path . ' *';
        $patterns[] = 'env AI_OUTPUT=json bash ' . $path . ' *';
    }

    return $patterns;
}

/** @return array{permission:string,pattern:string,effect:string} */
function aiPermissionEntry(string $permission, string $pattern, string $effect): array
{
    return ['permission' => $permission, 'pattern' => $pattern, 'effect' => $effect];
}

/**
 * @param list<array{permission:string,pattern:string,effect:string}> $entries
 * @return list<array{permission:string,pattern:string,effect:string}>
 */
function aiPermissionUniqueEntries(array $entries): array
{
    $seen = [];
    $out = [];
    foreach ($entries as $entry) {
        $key = $entry['permission'] . "\0" . $entry['pattern'] . "\0" . $entry['effect'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $entry;
    }

    return $out;
}
