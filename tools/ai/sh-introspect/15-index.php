<?php

declare(strict_types=1);

/**
 * Repo-wide index mode (`--all`). Discovers the AI shell scripts and libraries,
 * statically introspects each (never executing any), and emits a compact index
 * envelope (schema ai.sh-introspect-index/v1). No target is ever executed.
 *
 * Discovery root: $root when given, else the directory containing this tool's
 * sibling `scripts/ai`. Globs: `scripts/ai/*.sh` and `scripts/ai/internal/lib/*.sh`.
 *
 * In human mode a short table is printed; in JSON mode the full index envelope.
 * When $strictRisk is true, the run exits 3 if any indexed file is critical.
 */
function shIntrospectAllMain(bool $jsonMode, ?string $root, bool $strictRisk = false, ?string $outputPath = null, string $pagerMode = 'auto'): int
{
    $baseDir = shIntrospectResolveScanRoot($root);
    if ($baseDir === null) {
        return shIntrospectFailIndex($jsonMode, "could not resolve scan root for --all (looked for a scripts/ai directory)");
    }

    $files = shIntrospectDiscoverScripts($baseDir);
    if ($files === []) {
        // Still a valid, successful empty index. The empty index is always JSON
        // and therefore never paged.
        $envelope = shIntrospectIndexEnvelope([], $baseDir);
        $envelope['warnings'][] = 'no shell scripts discovered under ' . $baseDir;
        if (!shIntrospectEmitReport(shIntrospectEncode($envelope) . "\n", $outputPath, $jsonMode)) {
            return 2;
        }
        return 0;
    }

    $entries = [];
    foreach ($files as $file) {
        $entries[] = shIntrospectIndexEntry($file);
    }

    // Deterministic ordering by relative path.
    usort($entries, static fn(array $a, array $b): int => strcmp((string) $a['path'], (string) $b['path']));

    $envelope = shIntrospectIndexEnvelope($entries, $baseDir);

    $report = $jsonMode
        ? shIntrospectEncode($envelope) . "\n"
        : shIntrospectRenderIndexText($envelope);
    $pageReport = shIntrospectShouldPage($pagerMode, $jsonMode, $outputPath);
    if (!shIntrospectEmitReport($report, $outputPath, $jsonMode, $pageReport)) {
        return 2;
    }

    // --strict-risk gate across the whole index.
    if ($strictRisk) {
        $critical = array_values(array_filter(
            $entries,
            static fn(array $e): bool => (string) ($e['max_risk'] ?? '') === 'critical'
        ));
        if ($critical !== []) {
            $names = implode(', ', array_map(static fn(array $e): string => (string) $e['path'], $critical));
            fwrite(STDERR, "STRICT-RISK: critical risk detected in: {$names}\n");
            return 3;
        }
    }

    return 0;
}

/**
 * Resolve the directory to scan for `--all`. Accepts an explicit root (a
 * `scripts/ai` dir, its parent, or the repo root) or falls back to the tool's
 * own `<repo>/scripts/ai`. Returns the absolute `scripts/ai` dir or null.
 */
function shIntrospectResolveScanRoot(?string $root): ?string
{
    $candidates = [];
    if ($root !== null && $root !== '') {
        $abs = realpath($root);
        if ($abs !== false) {
            $candidates[] = $abs;                       // given dir itself
            $candidates[] = $abs . '/scripts/ai';       // given repo root
            $candidates[] = $abs . '/ai';               // given scripts/ dir
        }
    }
    // Tool is at <repo>/tools/ai/sh-introspect/15-index.php -> <repo>/scripts/ai.
    $repoRoot = realpath(__DIR__ . '/../../..');
    if ($repoRoot !== false) {
        $candidates[] = $repoRoot . '/scripts/ai';
    }

    foreach ($candidates as $cand) {
        if (is_dir($cand) && basename($cand) === 'ai' && is_dir(dirname($cand))) {
            return $cand;
        }
        // Accept a directory that directly contains .sh files even if not named ai.
        if (is_dir($cand) && glob(rtrim($cand, '/') . '/*.sh') !== []) {
            return $cand;
        }
    }
    return null;
}

/**
 * Discover the introspection targets under a `scripts/ai` directory:
 * `*.sh` directly inside it and `internal/lib/*.sh` (shared library modules).
 * Symlinks and non-readable files are skipped. Returns absolute paths.
 *
 * @return array<int,string>
 */
function shIntrospectDiscoverScripts(string $baseDir): array
{
    $found = [];
    foreach ([rtrim($baseDir, '/') . '/*.sh', rtrim($baseDir, '/') . '/internal/lib/*.sh'] as $glob) {
        $matches = glob($glob);
        if ($matches === false) {
            continue;
        }
        foreach ($matches as $m) {
            if (is_file($m) && is_readable($m)) {
                $found[$m] = true;
            }
        }
    }
    return array_keys($found);
}

/**
 * Build one compact index entry for a script by running the full static parse
 * and projecting the high-signal summary fields. The target is NOT executed.
 *
 * @return array<string,mixed>
 */
function shIntrospectIndexEntry(string $file): array
{
    $abs = realpath($file) ?: $file;
    $raw = @file_get_contents($abs);
    if ($raw === false || strpos((string) $raw, "\0") !== false) {
        return [
            'path' => shIntrospectPathObject($abs)['relative'],
            'absolute' => $abs,
            'status' => 'error',
            'errors' => [$raw === false ? 'could not read file' : 'binary file (NUL byte)'],
        ];
    }

    $env = shIntrospectParse((string) $raw, $abs);
    $risk = is_array($env['risk_summary'] ?? null) ? $env['risk_summary'] : [];

    return [
        'path' => shIntrospectPathObject($abs)['relative'],
        'absolute' => $abs,
        'status' => (string) ($env['status'] ?? 'ok'),
        'kind' => (string) ($env['kind'] ?? 'unknown'),
        'modes' => count(is_array($env['modes'] ?? null) ? $env['modes'] : []),
        'params' => count(is_array($env['params'] ?? null) ? $env['params'] : []),
        'functions' => count(is_array($env['functions'] ?? null) ? $env['functions'] : []),
        'max_risk' => (string) ($risk['max_risk'] ?? 'unknown'),
        'has_mutation' => (bool) ($risk['has_mutation'] ?? false),
        'has_dynamic_execution' => (bool) ($risk['has_dynamic_execution'] ?? false),
        'confidence' => (int) ($env['meta']['confidence'] ?? 0),
        'target_executed' => false,
    ];
}

/**
 * Wrap index entries in the index envelope.
 *
 * @param array<int,array<string,mixed>> $entries
 * @return array<string,mixed>
 */
function shIntrospectIndexEnvelope(array $entries, string $baseDir): array
{
    return [
        'schema' => SH_INTROSPECT_INDEX_SCHEMA,
        'status' => 'ok',
        'tool' => SH_INTROSPECT_TOOL,
        'root' => shIntrospectPathObject(rtrim($baseDir, '/'))['relative'],
        'count' => count($entries),
        'files' => $entries,
        'warnings' => [],
        'errors' => [],
        'meta' => [
            'parser' => 'php-static',
            'target_executed' => false,
        ],
    ];
}

/**
 * Emit an index-mode error envelope (or stderr line) and return exit code 2.
 */
function shIntrospectFailIndex(bool $jsonMode, string $message): int
{
    if ($jsonMode) {
        $envelope = shIntrospectIndexEnvelope([], '');
        $envelope['status'] = 'error';
        $envelope['errors'] = [$message];
        fwrite(STDOUT, shIntrospectEncode($envelope) . "\n");
    } else {
        fwrite(STDERR, "ERROR: {$message}\n");
    }
    return 2;
}

/**
 * Render a short human-readable table for the repo-wide index.
 *
 * @param array<string,mixed> $envelope
 */
function shIntrospectRenderIndexText(array $envelope): string
{
    $files = is_array($envelope['files'] ?? null) ? $envelope['files'] : [];
    $out = [];
    $out[] = sprintf(
        'sh-introspect index: %d file(s) under %s',
        (int) ($envelope['count'] ?? count($files)),
        (string) ($envelope['root'] ?? '')
    );
    foreach ((array) ($envelope['warnings'] ?? []) as $w) {
        $out[] = 'WARNING: ' . $w;
    }
    $out[] = '';
    foreach ($files as $f) {
        $out[] = sprintf(
            '  %-40s kind=%-8s modes=%-2d params=%-2d risk=%-8s conf=%d',
            (string) ($f['path'] ?? ''),
            (string) ($f['kind'] ?? 'unknown'),
            (int) ($f['modes'] ?? 0),
            (int) ($f['params'] ?? 0),
            (string) ($f['max_risk'] ?? 'unknown'),
            (int) ($f['confidence'] ?? 0)
        );
    }
    return implode("\n", $out) . "\n";
}
