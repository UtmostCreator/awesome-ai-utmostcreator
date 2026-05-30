<?php

declare(strict_types=1);

$changedArg = '';
for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--changed' && isset($argv[$i + 1])) {
        $changedArg = (string) $argv[$i + 1];
        break;
    }
    if (str_starts_with($argv[$i], '--changed=')) {
        $changedArg = (string) substr($argv[$i], 10);
        break;
    }
}

if ($changedArg === '') {
    fwrite(STDERR, "Usage: php tools/ai/suggest-verification.php --changed \"file1,file2\"\n");
    exit(1);
}

$paths = array_values(array_filter(array_map('trim', explode(',', $changedArg)), static fn(string $v): bool => $v !== ''));

$recommend = [];
$risk = 'low';
$matchedAny = false;

foreach ($paths as $path) {
    if (str_starts_with($path, 'tools/ai/') || str_starts_with($path, 'scripts/')) {
        $matchedAny = true;
        $recommend['php tools/ai/validate-ai-config.php'] = true;
        $recommend['bash scripts/doctor.sh'] = true;
        $risk = $risk === 'high' ? 'high' : 'medium';
    }
    if (str_starts_with($path, '.github/') || str_starts_with($path, '.opencode/')) {
        $matchedAny = true;
        $recommend['php tools/ai/validate-adapter-drift.php'] = true;
        $risk = $risk === 'high' ? 'high' : 'medium';
    }
    if (str_starts_with($path, 'docs/ai/')) {
        $matchedAny = true;
        $recommend['php tools/ai/validate-ai-config.php'] = true;
    }
    if (str_starts_with($path, 'policies/') || str_starts_with($path, 'schemas/ai/')) {
        $matchedAny = true;
        $recommend['php tools/ai/validate-generated-artifacts.php'] = true;
        $risk = 'high';
    }
}

if ($recommend === []) {
    $recommend['php tools/ai/validate-ai-config.php'] = true;
}

if (!$matchedAny) {
    $risk = 'medium';
}

fwrite(STDOUT, "Risk: {$risk}\n");
fwrite(STDOUT, "Recommended checks:\n");
foreach (array_keys($recommend) as $cmd) {
    fwrite(STDOUT, "- {$cmd}\n");
}

exit(0);
