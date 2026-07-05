<?php

declare(strict_types=1);

/**
 * validate-script-access.php — P10 consistency checks for the AI script-access
 * and agent-governance manifests (archived todo-agents-rework.md /
 * todo-agents-script-rework.md P10).
 *
 * Deterministic invariants enforced (no agent permission edits here — this validator
 * checks the script-access manifest, not per-agent permission blocks; permissions for the
 * 13 agents composed under tools/ai/install/permission-layers/ are generated from that
 * layered system, not inline-canonical — see docs/tickets/
 * arch-todo-permission-layer-composition-20260705T004618Z/plan.md; the two remaining
 * excluded agents, release-auditor and architecture-plan-writer, are still inline-canonical
 * per the adapter contract pending the v0.6-program coordination gate):
 *   1. Every root scripts/ai/<name>.sh (one per bin/<role>/ alias) is tiered
 *      exactly once in .github/ai-script-access.yaml.
 *   2. Dangerous scripts appear only in T5_mutation_recovery.
 *   3. Internal implementation modules (scripts/ai/internal/**) are never
 *      exposed as tier entries (agents use stable root entrypoints only).
 *   4. Every agent named in the access manifest exists in the agent inventory
 *      (docs/ai/AGENTS-MANIFEST.md).
 *   5. Only agent-creator-runtime-guardian may be granted the tool-use hooks.
 *
 * Usage: php tools/ai/validate-script-access.php [--fail-on-warn]
 * Exit 0 on success, 1 on any error.
 */

$root = realpath(__DIR__ . '/..' . '/..');
if ($root === false) {
    fwrite(STDERR, "ERROR: repository root not found\n");
    exit(1);
}

$errors = [];
$warnings = [];

$accessPath = $root . '/.github/ai-script-access.yaml';
$agentManifestPath = $root . '/docs/ai/AGENTS-MANIFEST.md';

if (!is_file($accessPath)) {
    fwrite(STDERR, "ERROR: missing .github/ai-script-access.yaml\n");
    exit(1);
}
if (!is_file($agentManifestPath)) {
    fwrite(STDERR, "ERROR: missing docs/ai/AGENTS-MANIFEST.md\n");
    exit(1);
}

$access = (string) file_get_contents($accessPath);
$agentManifest = (string) file_get_contents($agentManifestPath);

/** Root script set: one per scripts/ai/bin/<role>/<name>.sh alias. */
$binShims = glob($root . '/scripts/ai/bin/*/*.sh') ?: [];
$rootScripts = [];
foreach ($binShims as $shim) {
    $rootScripts[basename($shim)] = true;
}
ksort($rootScripts);

/**
 * Parse the top-level `tiers:` block into tier => [scripts].
 *
 * @return array<string,array<int,string>>
 */
$parseTiers = static function (string $yaml): array {
    $out = [];
    $inTiers = false;
    $curTier = null;
    foreach (preg_split('/\R/', $yaml) ?: [] as $line) {
        if (preg_match('/^tiers:\s*$/', $line)) {
            $inTiers = true;
            continue;
        }
        if (preg_match('/^agents:\s*$/', $line)) {
            break;
        }
        if (!$inTiers) {
            continue;
        }
        if (preg_match('/^  ([A-Za-z0-9_]+):\s*$/', $line, $m)) {
            $curTier = $m[1];
            $out[$curTier] = [];
            continue;
        }
        if ($curTier !== null && preg_match('/^      - (\S+\.sh)\s*$/', $line, $m)) {
            $out[$curTier][] = $m[1];
        }
    }
    return $out;
};

$tiers = $parseTiers($access);
if ($tiers === []) {
    $errors[] = 'no tiers parsed from .github/ai-script-access.yaml';
}

// 1. Exactly-once tiering of every root script.
$flat = [];
foreach ($tiers as $scripts) {
    foreach ($scripts as $s) {
        $flat[$s] = ($flat[$s] ?? 0) + 1;
    }
}
foreach ($flat as $script => $count) {
    if ($count > 1) {
        $errors[] = "script '{$script}' is tiered {$count} times (must be exactly once)";
    }
    // 3. No internal module exposure.
    if (str_contains($script, 'internal/')) {
        $errors[] = "internal module '{$script}' must not be exposed as a tier entry";
    }
}
foreach (array_keys($rootScripts) as $script) {
    if (!isset($flat[$script])) {
        $errors[] = "root script '{$script}' is not tiered in ai-script-access.yaml";
    }
}
foreach (array_keys($flat) as $script) {
    if (!isset($rootScripts[$script])) {
        $errors[] = "tiered entry '{$script}' is not a real root scripts/ai script";
    }
}

// 2. Dangerous scripts only in T5.
$dangerous = [
    'ai-edit.sh', 'ai-rollback.sh', 'prune-shipped-targets.sh',
    'install-mandatory-tools.sh', 'all_in_one.sh', 'watch-loop.sh',
    'pre-tool-use.sh', 'post-tool-use.sh',
];
$t5 = $tiers['T5_mutation_recovery'] ?? [];
foreach ($dangerous as $d) {
    foreach ($tiers as $tier => $scripts) {
        if (in_array($d, $scripts, true) && $tier !== 'T5_mutation_recovery') {
            $errors[] = "dangerous script '{$d}' must only be in T5_mutation_recovery, found in {$tier}";
        }
    }
    if (isset($rootScripts[$d]) && !in_array($d, $t5, true)) {
        $errors[] = "dangerous script '{$d}' must be listed in T5_mutation_recovery";
    }
}

// 4 + 5. Agents block: existence + hook-grant restriction.
$inAgents = false;
$curAgent = null;
$hookGrantedTo = [];
$manifestAgents = [];
// Collect agents present in the inventory by their backticked names.
if (preg_match_all('/`([a-z][a-z0-9-]*)`/', $agentManifest, $m)) {
    foreach ($m[1] as $name) {
        $manifestAgents[$name] = true;
    }
}
// Hook scripts whose grant is restricted to a single agent (invariant 5).
$hookScripts = ['pre-tool-use.sh', 'post-tool-use.sh'];
$hookPattern = '/\b(' . implode('|', array_map(
    static fn (string $s): string => preg_quote($s, '/'),
    $hookScripts
)) . ')\b/';
// True while consuming a multi-line `allowed_scripts:` block list for $curAgent.
$inAllowedScriptsBlock = false;
foreach (preg_split('/\R/', $access) ?: [] as $line) {
    if (preg_match('/^agents:\s*$/', $line)) {
        $inAgents = true;
        continue;
    }
    if (!$inAgents) {
        continue;
    }
    if (preg_match('/^  ([\w-]+):\s*$/', $line, $mm)) {
        $curAgent = $mm[1];
        $inAllowedScriptsBlock = false;
        if (!isset($manifestAgents[$curAgent])) {
            $errors[] = "access-manifest agent '{$curAgent}' is not in docs/ai/AGENTS-MANIFEST.md";
        }
        continue;
    }
    if ($curAgent === null) {
        continue;
    }
    // Continuation of a block-style list: `      - script.sh` entries.
    if ($inAllowedScriptsBlock) {
        if (preg_match('/^\s+-\s+(\S+)\s*$/', $line, $bm)) {
            if (preg_match($hookPattern, $bm[1])) {
                $hookGrantedTo[$curAgent] = true;
            }
            continue;
        }
        // Any non-list-item line ends the allowed_scripts block.
        $inAllowedScriptsBlock = false;
    }
    if (preg_match('/^\s+allowed_scripts:\s*(.*)$/', $line, $am)) {
        $rest = trim($am[1]);
        if ($rest === '') {
            // Block-style list follows on subsequent `- entry` lines.
            $inAllowedScriptsBlock = true;
        } elseif (preg_match($hookPattern, $rest)) {
            // Inline-style list on the same line.
            $hookGrantedTo[$curAgent] = true;
        }
    }
}
$allowedHookAgent = ['agent-creator-runtime-guardian'];
if (array_keys($hookGrantedTo) !== $allowedHookAgent) {
    $errors[] = 'only agent-creator-runtime-guardian may be granted pre/post-tool-use.sh; got: '
        . implode(', ', array_keys($hookGrantedTo) ?: ['(none)']);
}

// Report.
foreach ($warnings as $w) {
    fwrite(STDERR, "WARN: {$w}\n");
}
if ($errors !== []) {
    foreach ($errors as $e) {
        fwrite(STDERR, "ERROR: {$e}\n");
    }
    exit(1);
}

$failOnWarn = in_array('--fail-on-warn', $argv, true);
if ($failOnWarn && $warnings !== []) {
    exit(1);
}

fwrite(STDOUT, "OK: script-access + agent-governance consistency checks passed\n");
exit(0);
