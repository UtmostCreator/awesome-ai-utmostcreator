<?php

declare(strict_types=1);

$root = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
if ($root === false) {
    fwrite(STDERR, "ERROR: could not resolve repository root\n");
    exit(1);
}

$agent = $argv[1] ?? null;
if ($agent === null || $agent === '') {
    fwrite(STDERR, "Usage: php tools/ai/render-agent-permissions.php AGENT_NAME\n");
    exit(1);
}

$map = [
    'architect' => 'base-readonly',
    'researcher' => 'base-readonly',
    'workflow-auditor' => 'base-readonly',
    'release-auditor' => 'base-readonly',
    'reviewer' => 'base-verify',
    'config-maintainer' => 'base-verify',
    'implementer' => 'base-impl',
    'refactorer' => 'base-impl',
];

if (!isset($map[$agent])) {
    fwrite(STDERR, "ERROR: unknown agent mapping for {$agent}\n");
    exit(1);
}

$policyPath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'command-policy.tiers.yaml';
$yaml = file_get_contents($policyPath);
if ($yaml === false) {
    fwrite(STDERR, "ERROR: failed to read command policy tiers\n");
    exit(1);
}

$tierName = $map[$agent];
$tierData = parseTier($yaml, $tierName);
if ($tierData === null) {
    fwrite(STDERR, "ERROR: tier not found: {$tierName}\n");
    exit(1);
}

$lines = [];
$lines[] = 'permission:';
$lines[] = '  edit: ' . ($tierData['edit'] ?? 'deny');
$lines[] = '  bash:';
$lines[] = "    '*': deny";

foreach ($tierData['allow'] as $cmd) {
    $lines[] = "    '" . str_replace("'", "''", $cmd) . "': allow";
}
foreach ($tierData['ask'] as $cmd) {
    $lines[] = "    '" . str_replace("'", "''", $cmd) . "': ask";
}
foreach ($tierData['deny'] as $cmd) {
    $lines[] = "    '" . str_replace("'", "''", $cmd) . "': deny";
}

echo implode(PHP_EOL, $lines) . PHP_EOL;

function parseTier(string $yaml, string $tier): ?array
{
    if (preg_match('/^\s{2}' . preg_quote($tier, '/') . ':\s*$([\s\S]*?)(?=^\s{2}[a-z0-9-]+:\s*$|\z)/mi', $yaml, $m) !== 1) {
        return null;
    }

    $body = $m[1];
    $edit = null;
    if (preg_match('/^\s{4}edit:\s*(allow|deny)\s*$/mi', $body, $editMatch) === 1) {
        $edit = $editMatch[1];
    }

    return [
        'edit' => $edit,
        'allow' => listItems($body, 'allow'),
        'ask' => listItems($body, 'ask'),
        'deny' => listItems($body, 'deny'),
    ];
}

function listItems(string $body, string $name): array
{
    if (preg_match('/^\s{6}' . preg_quote($name, '/') . ':\s*$([\s\S]*?)(?=^\s{6}(allow|ask|deny):\s*$|^\s{2}[a-z0-9-]+:\s*$|\z)/mi', $body, $m) !== 1) {
        return [];
    }

    if (preg_match_all('/^\s{8}-\s*"([^"]+)"\s*$/mi', $m[1], $matches) <= 0) {
        return [];
    }

    return array_values(array_unique($matches[1]));
}
