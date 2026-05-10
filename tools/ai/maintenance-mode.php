<?php

declare(strict_types=1);

$root = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
if ($root === false) {
    fwrite(STDERR, "ERROR: could not resolve repository root\n");
    exit(1);
}

$statePath = $root . DIRECTORY_SEPARATOR . '.ai-logs' . DIRECTORY_SEPARATOR . 'maintenance-mode.json';

$argv = $_SERVER['argv'] ?? [];
$command = $argv[1] ?? '';

if (!in_array($command, ['enable', 'disable', 'status'], true)) {
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php tools/ai/maintenance-mode.php enable --reason \"...\" [--ttl-seconds 1800]\n");
    fwrite(STDERR, "  php tools/ai/maintenance-mode.php disable\n");
    fwrite(STDERR, "  php tools/ai/maintenance-mode.php status\n");
    exit(1);
}

if ($command === 'status') {
    outputStatus($statePath);
    exit(0);
}

if ($command === 'disable') {
    writeState($statePath, [
        'enabled' => false,
        'reason' => 'manual-disable',
        'enabled_at_epoch' => null,
        'expires_at_epoch' => null,
        'ttl_seconds' => null,
        'updated_at_epoch' => time(),
        'actor' => get_current_user(),
        'warnings' => [
            'Maintenance mode disabled. Default strict policy is active.',
        ],
    ]);
    outputStatus($statePath);
    exit(0);
}

$reason = getOptionValue($argv, '--reason');
if ($reason === null || trim($reason) === '') {
    fwrite(STDERR, "ERROR: enable requires --reason\n");
    exit(1);
}

$ttlRaw = getOptionValue($argv, '--ttl-seconds') ?? '1800';
if (!ctype_digit((string) $ttlRaw) || (int) $ttlRaw < 60 || (int) $ttlRaw > 14400) {
    fwrite(STDERR, "ERROR: --ttl-seconds must be an integer between 60 and 14400\n");
    exit(1);
}

$ttl = (int) $ttlRaw;
$now = time();

writeState($statePath, [
    'enabled' => true,
    'reason' => $reason,
    'enabled_at_epoch' => $now,
    'expires_at_epoch' => $now + $ttl,
    'ttl_seconds' => $ttl,
    'updated_at_epoch' => $now,
    'actor' => get_current_user(),
    'warnings' => [
        'Maintenance mode is temporary and should be used only for repository install/verify workflows.',
        'Destructive commands remain blocked by scripts/ai/pre-tool-use.sh.',
        'Back up and review git status before running apply/install commands.',
    ],
]);

outputStatus($statePath);
exit(0);

function getOptionValue(array $argv, string $key): ?string
{
    foreach ($argv as $idx => $part) {
        if ($part === $key) {
            return $argv[$idx + 1] ?? null;
        }
        if (str_starts_with($part, $key . '=')) {
            return substr($part, strlen($key) + 1);
        }
    }

    return null;
}

function writeState(string $path, array $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        fwrite(STDERR, "ERROR: could not create directory: {$dir}\n");
        exit(1);
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        fwrite(STDERR, "ERROR: could not encode state json\n");
        exit(1);
    }

    if (file_put_contents($path, $json . PHP_EOL) === false) {
        fwrite(STDERR, "ERROR: could not write state file: {$path}\n");
        exit(1);
    }
}

function outputStatus(string $path): void
{
    if (!is_file($path)) {
        echo json_encode([
            'status' => 'inactive',
            'state_file' => '.ai-logs/maintenance-mode.json',
            'message' => 'maintenance mode state file not found',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        return;
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        fwrite(STDERR, "ERROR: unable to read state file\n");
        exit(1);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "ERROR: invalid maintenance state json\n");
        exit(1);
    }

    $now = time();
    $expiresAt = (int) ($decoded['expires_at_epoch'] ?? 0);
    $active = ($decoded['enabled'] ?? false) === true && $expiresAt > $now;

    $decoded['status'] = $active ? 'active' : 'inactive';
    $decoded['state_file'] = '.ai-logs/maintenance-mode.json';
    $decoded['now_epoch'] = $now;

    echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
