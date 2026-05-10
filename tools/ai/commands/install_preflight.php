<?php

declare(strict_types=1);

function aiRunPreflight(string $root): int
{
    $checks = [];

    $checks[] = ['name' => 'php_version', 'status' => version_compare(PHP_VERSION, '8.1.0', '>=') ? 'passed' : 'failed', 'required' => '>=8.1'];
    $checks[] = ['name' => 'ext_json', 'status' => extension_loaded('json') ? 'passed' : 'failed'];
    $checks[] = ['name' => 'ext_mbstring', 'status' => extension_loaded('mbstring') ? 'passed' : 'failed'];
    $checks[] = ['name' => 'ext_zip', 'status' => extension_loaded('zip') ? 'passed' : 'warning', 'reason' => extension_loaded('zip') ? null : 'ZipArchive unavailable; directory backup fallback will be used'];

    $gitOut = [];
    $gitExit = 0;
    exec('git --version', $gitOut, $gitExit);
    $checks[] = ['name' => 'git', 'status' => $gitExit === 0 ? 'passed' : 'failed'];

    $generated = aiCliGeneratedDir($root);
    $checks[] = ['name' => 'generated_dir_writable', 'status' => is_dir($generated) && is_writable($generated) ? 'passed' : 'failed'];

    $templates = $root . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'ai-universal-rules' . DIRECTORY_SEPARATOR . 'templates';
    $checks[] = ['name' => 'templates_readable', 'status' => is_dir($templates) && is_readable($templates) ? 'passed' : 'failed'];

    $failed = array_values(array_filter($checks, static fn(array $c): bool => ($c['status'] ?? 'failed') === 'failed'));
    $status = $failed === [] ? 'ok' : 'failed';
    $data = [
        'status' => $status,
        'checks' => $checks,
        'recommended_next_action' => $failed === [] ? 'Run package-verify then adapter-plan.' : 'Resolve failed checks before install/apply.',
    ];

    $written = aiCliWriteArtifact($root, 'preflight', 'php tools/ai/ai.php preflight', $data, $status, null, (string) $data['recommended_next_action']);
    fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
    return $failed === [] ? 0 : 1;
}

function aiRunPackageLock(string $root, array $args): int
{
    $update = in_array('--update', $args, true);
    $check = in_array('--check', $args, true) || !$update;

    $checksums = aiCollectTemplateChecksums($root);
    $payload = [
        'schema_version' => 1,
        'package' => 'ai-universal-rules',
        'source_checksums' => $checksums,
    ];

    $path = aiPackageLockPath($root);
    if ($update) {
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }

    $existing = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    $matches = is_array($existing) && ($existing['source_checksums'] ?? null) === $checksums;

    $data = [
        'path' => 'packages/ai-universal-rules/package-lock.ai.json',
        'mode' => $update ? 'update' : ($check ? 'check' : 'unknown'),
        'entry_count' => count($checksums),
        'matches' => $matches,
    ];

    $status = $matches ? 'ok' : ($update ? 'ok' : 'failed');
    $next = $matches ? 'Package lock matches template sources.' : 'Run package-lock --update to refresh checksums.';
    $written = aiCliWriteArtifact($root, 'package-lock', 'php tools/ai/ai.php package-lock', $data, $status, null, $next);
    fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
    return $status === 'ok' ? 0 : 1;
}

function aiRunPackageVerify(string $root): int
{
    $path = aiPackageLockPath($root);
    if (!is_file($path)) {
        throw new RuntimeException('Missing package lock file: packages/ai-universal-rules/package-lock.ai.json');
    }

    $lock = json_decode((string) file_get_contents($path), true);
    if (!is_array($lock)) {
        throw new RuntimeException('Invalid JSON in package lock file');
    }

    $expected = $lock['source_checksums'] ?? [];
    if (!is_array($expected)) {
        throw new RuntimeException('Invalid source_checksums in package lock file');
    }
    $current = aiCollectTemplateChecksums($root);

    $mismatches = [];
    foreach ($current as $file => $hash) {
        if (!isset($expected[$file])) {
            $mismatches[] = ['path' => $file, 'reason' => 'missing_from_lock', 'current' => $hash];
            continue;
        }
        if ((string) $expected[$file] !== $hash) {
            $mismatches[] = ['path' => $file, 'reason' => 'checksum_mismatch', 'expected' => (string) $expected[$file], 'current' => $hash];
        }
    }
    foreach ($expected as $file => $hash) {
        if (!isset($current[$file])) {
            $mismatches[] = ['path' => (string) $file, 'reason' => 'missing_from_templates', 'expected' => (string) $hash];
        }
    }

    $status = $mismatches === [] ? 'ok' : 'failed';
    $data = [
        'path' => 'packages/ai-universal-rules/package-lock.ai.json',
        'mismatch_count' => count($mismatches),
        'mismatches' => $mismatches,
    ];

    $written = aiCliWriteArtifact($root, 'package-verify', 'php tools/ai/ai.php package-verify', $data, $status, null, $status === 'ok' ? 'Source package integrity verified.' : 'Refresh lock or revert unintended template drift.');
    fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
    return $status === 'ok' ? 0 : 1;
}

function aiRunAuditInstructions(string $root): int
{
    $surfaces = [
        '.github/copilot-instructions.md',
        'AGENTS.md',
        'CLAUDE.md',
        'GEMINI.md',
        'AI.md',
    ];

    $found = [];
    foreach ($surfaces as $path) {
        if (is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path))) {
            $found[] = ['path' => $path, 'ownership_hint' => 'mixed_or_user'];
        }
    }

    $extra = [];
    exec('git -C ' . escapeshellarg($root) . ' ls-files ".github/instructions/*.instructions.md" ".opencode/**"', $extra);
    foreach ($extra as $path) {
        $found[] = ['path' => $path, 'ownership_hint' => 'runtime_adapter'];
    }

    $data = [
        'count' => count($found),
        'entries' => $found,
        'notes' => [
            'Copilot root instructions are broadly supported; sidecar support varies by surface.',
            'OpenCode project rules primarily use AGENTS.md.',
        ],
    ];
    $written = aiCliWriteArtifact($root, 'instruction-audit', 'php tools/ai/ai.php audit-instructions', $data, 'ok', null, 'Use adapter-plan to choose safe merge or sidecar-only mode.');
    fwrite(STDOUT, "OK: wrote {$written['json']} and {$written['markdown']}" . PHP_EOL);
    return 0;
}

function aiInstallerConfigFromAiArgs(string $root, array $args, bool $forceDryRun = false): array
{
    $normalized = [];
    for ($i = 0; $i < count($args); $i++) {
        $arg = (string) $args[$i];
        if (in_array($arg, ['--interactive', '--backup-only', '--apply', '--reinstall', '--no-interaction', '--agent', '--ci', '--wizard', '--yes'], true)) {
            continue;
        }
        if (in_array($arg, ['--backup', '--resolve'], true)) {
            $i++;
            continue;
        }
        if ($arg === '--targets') {
            $targetsRaw = (string) ($args[$i + 1] ?? 'copilot,opencode');
            $i++;
            $targets = array_values(array_filter(array_map('trim', explode(',', $targetsRaw)), static fn(string $v): bool => $v !== ''));
            if ($targets === ['copilot']) {
                $normalized[] = '--runtime';
                $normalized[] = 'github-copilot';
            } elseif ($targets === ['opencode']) {
                $normalized[] = '--runtime';
                $normalized[] = 'opencode';
            } else {
                $normalized[] = '--runtime';
                $normalized[] = 'both';
            }
            continue;
        }
        if (str_starts_with($arg, '--targets=')) {
            $targetsRaw = substr($arg, 10);
            $targets = array_values(array_filter(array_map('trim', explode(',', $targetsRaw)), static fn(string $v): bool => $v !== ''));
            if ($targets === ['copilot']) {
                $normalized[] = '--runtime=github-copilot';
            } elseif ($targets === ['opencode']) {
                $normalized[] = '--runtime=opencode';
            } else {
                $normalized[] = '--runtime=both';
            }
            continue;
        }
        $normalized[] = $arg;
    }

    if ($forceDryRun && !in_array('--dry-run', $normalized, true)) {
        $normalized[] = '--dry-run';
    }

    $argv = array_merge(['install-ai-kit.php', '--target', $root], $normalized);
    return aiInstallerParseArgs($argv);
}

function aiInstallerTargetsFromRuntime(string $runtime): array
{
    return match ($runtime) {
        'github-copilot' => ['copilot'],
        'opencode' => ['opencode'],
        default => ['copilot', 'opencode'],
    };
}
