<?php

declare(strict_types=1);

$root = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
if ($root === false) {
    fwrite(STDERR, "ERROR: could not resolve repository root\n");
    exit(1);
}

$policyPath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'command-policy.tiers.yaml';
$registryPath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'script-registry.json';
$preToolPath = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'pre-tool-use.sh';
$maintenanceToolPath = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'maintenance-mode.php';

if (!is_file($policyPath)) {
    fwrite(STDERR, "ERROR: missing docs/ai/command-policy.tiers.yaml\n");
    exit(1);
}

if (!is_file($registryPath)) {
    fwrite(STDERR, "ERROR: missing docs/ai/script-registry.json\n");
    exit(1);
}

if (!is_file($preToolPath)) {
    fwrite(STDERR, "ERROR: missing scripts/ai/pre-tool-use.sh\n");
    exit(1);
}

if (!is_file($maintenanceToolPath)) {
    fwrite(STDERR, "ERROR: missing tools/ai/maintenance-mode.php\n");
    exit(1);
}

$policyText = file_get_contents($policyPath);
if ($policyText === false) {
    fwrite(STDERR, "ERROR: failed reading policy yaml\n");
    exit(1);
}

$registryRaw = file_get_contents($registryPath);
if ($registryRaw === false) {
    fwrite(STDERR, "ERROR: failed reading script registry\n");
    exit(1);
}

$registry = json_decode($registryRaw, true);
if (!is_array($registry)) {
    fwrite(STDERR, "ERROR: invalid JSON in script registry\n");
    exit(1);
}

$preToolText = file_get_contents($preToolPath);
if ($preToolText === false) {
    fwrite(STDERR, "ERROR: failed reading scripts/ai/pre-tool-use.sh\n");
    exit(1);
}

$errors = [];
$warnings = [];

$tierNames = [];
if (preg_match_all('/^\s{2}([a-z0-9-]+):\s*$/mi', $policyText, $matches) > 0) {
    $tierNames = array_values(array_unique($matches[1]));
}

if ($tierNames === []) {
    $errors[] = 'no tiers found under docs/ai/command-policy.tiers.yaml';
}

// Unknown extends.
if (preg_match_all('/^\s{4}extends:\s*([a-z0-9-]+)\s*$/mi', $policyText, $extendsMatches) > 0) {
    foreach ($extendsMatches[1] as $targetTier) {
        if (!in_array($targetTier, $tierNames, true)) {
            $errors[] = "unknown tier inheritance target: {$targetTier}";
        }
    }
}

// Circular extends detection from simple adjacency map.
$extendsMap = [];
if (preg_match_all('/^\s{2}([a-z0-9-]+):\s*$([\s\S]*?)(?=^\s{2}[a-z0-9-]+:\s*$|\z)/mi', $policyText, $tierBlocks, PREG_SET_ORDER) > 0) {
    foreach ($tierBlocks as $block) {
        $tier = $block[1];
        $body = $block[2];
        if (preg_match('/^\s{4}extends:\s*([a-z0-9-]+)\s*$/mi', $body, $m) === 1) {
            $extendsMap[$tier] = $m[1];
        }
    }
}

foreach (array_keys($extendsMap) as $start) {
    $seen = [];
    $cursor = $start;
    while (isset($extendsMap[$cursor])) {
        if (isset($seen[$cursor])) {
            $errors[] = "circular tier inheritance detected at: {$cursor}";
            break;
        }
        $seen[$cursor] = true;
        $cursor = $extendsMap[$cursor];
    }
}

// Detect contradictory commands in each tier.
$tierSections = [];
if (preg_match_all('/^\s{2}([a-z0-9-]+):\s*$([\s\S]*?)(?=^\s{2}[a-z0-9-]+:\s*$|\z)/mi', $policyText, $tierSections, PREG_SET_ORDER) > 0) {
    foreach ($tierSections as $section) {
        $tier = $section[1];
        $body = $section[2];

        $allow = extractListItems($body, 'allow');
        $ask = extractListItems($body, 'ask');
        $deny = extractListItems($body, 'deny');

        foreach (array_intersect($allow, $deny) as $dup) {
            $errors[] = "tier {$tier} command appears in allow and deny: {$dup}";
        }
        foreach (array_intersect($allow, $ask) as $dup) {
            $warnings[] = "tier {$tier} command appears in allow and ask: {$dup}";
        }
    }
}

// Hard policy checks.
$forbiddenAllowPatterns = ['grep *', 'find *', 'cat *', 'sed *', 'awk *', 'AI_ALLOW_UNSAFE=1 bash scripts/ai/ai-search.sh *', 'bash scripts/ai/ai-search.sh secrets *'];
foreach ($forbiddenAllowPatterns as $pattern) {
    foreach ($tierSections as $section) {
        $allow = extractListItems($section[2], 'allow');
        if (in_array($pattern, $allow, true)) {
            $errors[] = "forbidden command allowed by policy: {$pattern}";
        }
    }
}

if (strpos($preToolText, 'MAINTENANCE_STATE_FILE') === false) {
    $errors[] = 'pre-tool-use.sh must define MAINTENANCE_STATE_FILE for temporary install mode governance';
}

if (strpos($preToolText, 'maintenance mode allows repository-delivered scripts only') === false) {
    $warnings[] = 'maintenance-mode external script ask-gate reason not found in pre-tool-use.sh';
}

// Minimal registry alignment checks.
if (!isset($registry['scripts']) || !is_array($registry['scripts'])) {
    $errors[] = 'script registry missing scripts object';
} else {
    foreach ($registry['scripts'] as $id => $meta) {
        if (!is_array($meta)) {
            $errors[] = "registry entry is not object: {$id}";
            continue;
        }

        foreach (['tier', 'mutates_state', 'writes_paths', 'reads_secret_values', 'supports_json', 'bounded_output', 'requires_approval', 'command', 'interface', 'autonomy_level'] as $field) {
            if (!array_key_exists($field, $meta)) {
                $warnings[] = "registry entry missing {$field} metadata: {$id}";
            }
        }

        $autonomyLevel = (string) ($meta['autonomy_level'] ?? '');
        $allowedAutonomyLevels = ['observe', 'advise', 'act_with_approval', 'act_autonomously'];
        if ($autonomyLevel !== '' && !in_array($autonomyLevel, $allowedAutonomyLevels, true)) {
            $errors[] = "registry entry has invalid autonomy_level {$autonomyLevel}: {$id}";
        }

        $isMutating = ($meta['risk'] ?? '') === 'mutating'
            || ($meta['mutates_state'] ?? false) === true
            || ($meta['requires_approval'] ?? false) === true;
        if ($isMutating && !in_array($autonomyLevel, ['act_with_approval', 'act_autonomously'], true)) {
            $errors[] = "registry entry understates mutating autonomy level: {$id}";
        }

        if ($autonomyLevel === 'act_autonomously') {
            $errors[] = "registry entry declares unsupported autonomous mutation level without project-specific controls: {$id}";
        }
    }
}

$status = $errors === [] ? 'passed' : 'failed';
$score = $errors === [] ? max(85, 100 - count($warnings)) : max(40, 90 - (count($errors) * 10) - count($warnings));

$result = [
    'status' => $status,
    'policy_score' => $score,
    'findings' => array_merge(
        array_map(static fn(string $message): array => ['severity' => 'major', 'issue' => $message], $errors),
        array_map(static fn(string $message): array => ['severity' => 'minor', 'issue' => $message], $warnings),
    ),
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($errors === [] ? 0 : 1);

function extractListItems(string $tierBody, string $section): array
{
    if (preg_match('/^\s{6}' . preg_quote($section, '/') . ':\s*$([\s\S]*?)(?=^\s{6}(allow|ask|deny):\s*$|^\s{2}[a-z0-9-]+:\s*$|\z)/mi', $tierBody, $m) !== 1) {
        return [];
    }

    $items = [];
    if (preg_match_all('/^\s{8}-\s*"([^"]+)"\s*$/mi', $m[1], $matches) > 0) {
        $items = $matches[1];
    }

    return array_values(array_unique($items));
}
