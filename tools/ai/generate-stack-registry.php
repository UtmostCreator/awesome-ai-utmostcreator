<?php

declare(strict_types=1);

/**
 * Generate the deterministic docs/ai/stack-registry.json projection from the
 * canonical shipped stack descriptors under packages/ai-universal-rules/stacks/*.json.
 *
 * This is a projection/check target ONLY — it is never read back as the source of
 * truth (tools/ai/install/stack-registry.php + the shipped *.json descriptors are
 * canonical). See docs/tickets/arch-todo-dynamic-stack-permission-selection-20260705T011906Z/plan.md,
 * Slice 5.
 *
 * Usage:
 *   php tools/ai/generate-stack-registry.php --check
 *   php tools/ai/generate-stack-registry.php --write
 */

require_once __DIR__ . '/install/stack-registry.php';

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

try {
    $registry = aiStackLoadRegistry(null, $root);
} catch (\Throwable $e) {
    fwrite(STDERR, 'ERROR: failed to load stack registry: ' . $e->getMessage() . "\n");
    exit(1);
}

// Drop internal loader bookkeeping keys (_source, _path) from the generated projection;
// they are loader provenance, not part of the descriptor contract.
$stacks = [];
foreach ($registry as $id => $descriptor) {
    unset($descriptor['_source'], $descriptor['_path']);
    $stacks[$id] = $descriptor;
}
ksort($stacks);

$payload = [
    'schema_version' => '1.0.0',
    'stacks' => $stacks,
];

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fwrite(STDERR, "ERROR: failed to encode stack registry JSON\n");
    exit(1);
}
$rendered = $json . "\n";

$targetPath = $root . '/docs/ai/stack-registry.json';
$current = is_file($targetPath) ? (string) file_get_contents($targetPath) : null;

if ($current === $rendered) {
    echo "OK: docs/ai/stack-registry.json is up to date\n";
    exit(0);
}

if ($write) {
    if (file_put_contents($targetPath, $rendered) === false) {
        fwrite(STDERR, "ERROR: failed to write docs/ai/stack-registry.json\n");
        exit(1);
    }
    echo "OK: wrote docs/ai/stack-registry.json\n";
    exit(0);
}

fwrite(STDERR, "ERROR: docs/ai/stack-registry.json is stale. Run: php tools/ai/generate-stack-registry.php --write\n");
exit(1);
