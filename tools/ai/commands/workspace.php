<?php

declare(strict_types=1);

function aiRunList(string $root): int
{
    $data = [
        'commands' => [
            'list',
            'freshness',
            'budget',
            'workflow',
            'snapshot',
            'diff-summary',
            'risk',
            'verify',
            'next',
            'rebase-state',
            'decision',
            'why',
            'session-resume',
            'commit-msg',
            'pr-summary',
            'logs',
            'env-check',
            'file-context',
            'orphans',
            'auto-fix',
            'impact',
            'ask',
            'estimate',
            'conflicts',
            'find',
            'symbols',
            'preflight',
            'package-lock',
            'package-verify',
            'audit-instructions',
            'adapter-plan',
            'plan',
            'install',
            'upgrade',
            'adapter-validate',
            'rollback',
            'packs',
            'placeholders',
            'hooks',
            'toolchain',
            'run-script',
            'install-docs',
            'advisor',
            'version',
        ],
    ];

    $written = aiCliWriteArtifact($root, 'ai-commands', 'php tools/ai/ai.php list', $data, 'ok');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

function aiRunFind(string $root, array $args): int
{
    $query = trim(implode(' ', $args));
    if ($query === '') {
        throw new RuntimeException('find requires a search query');
    }

    $files = [];
    exec('git -C ' . escapeshellarg($root) . ' ls-files', $files);
    $files = array_values(array_filter($files, static fn(string $f): bool => $f !== ''));

    $pathMatches = [];
    $q = strtolower($query);
    foreach ($files as $path) {
        $pathLower = strtolower($path);
        if (str_contains($pathLower, $q)) {
            $score = str_starts_with(strtolower(basename($path)), $q) ? 100 : 70;
            $pathMatches[] = ['path' => $path, 'score' => $score, 'match' => 'path'];
        }
    }
    usort($pathMatches, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    $pathMatches = array_slice($pathMatches, 0, 80);

    $contentMatchesRaw = [];
    exec('git -C ' . escapeshellarg($root) . ' grep -n -I -- ' . escapeshellarg($query) . ' --' . aiShellNullRedirect(), $contentMatchesRaw);
    $contentMatches = [];
    foreach (array_slice($contentMatchesRaw, 0, 120) as $line) {
        $parts = explode(':', $line, 3);
        if (count($parts) < 3) {
            continue;
        }
        $contentMatches[] = [
            'path' => $parts[0],
            'line' => (int) $parts[1],
            'preview' => trim($parts[2]),
        ];
    }

    $data = [
        'query' => $query,
        'path_matches_count' => count($pathMatches),
        'path_matches' => $pathMatches,
        'content_matches_count' => count($contentMatches),
        'content_matches' => $contentMatches,
    ];

    $written = aiCliWriteArtifact($root, 'find', 'php tools/ai/ai.php find ' . $query, $data, 'ok', null, 'Open highest scoring match first, then refine query if needed.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

function aiRunSymbols(string $root, array $args): int
{
    $filter = trim(implode(' ', $args));
    $files = [];
    exec('git -C ' . escapeshellarg($root) . ' ls-files "*.php" "*.sh" "*.md" "*.json" "*.yml" "*.yaml"', $files);

    $symbols = [];
    foreach ($files as $relPath) {
        $absPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
        if (!is_file($absPath)) {
            continue;
        }
        $lines = file($absPath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            continue;
        }

        foreach ($lines as $idx => $line) {
            $name = null;
            $kind = null;

            if (preg_match('/^\s*function\s+([A-Za-z0-9_]+)\s*\(/', $line, $m) === 1) {
                $name = $m[1];
                $kind = 'function';
            } elseif (preg_match('/^\s*class\s+([A-Za-z0-9_]+)/', $line, $m) === 1) {
                $name = $m[1];
                $kind = 'class';
            } elseif (preg_match('/^\s*(?:public|private|protected)?\s*function\s+([A-Za-z0-9_]+)\s*\(/', $line, $m) === 1) {
                $name = $m[1];
                $kind = 'method';
            } elseif (preg_match('/^#\s+(.+)$/', $line, $m) === 1) {
                $name = trim($m[1]);
                $kind = 'heading';
            }

            if ($name === null || $kind === null) {
                continue;
            }
            if ($filter !== '' && stripos($name, $filter) === false) {
                continue;
            }

            $symbols[] = [
                'path' => $relPath,
                'line' => $idx + 1,
                'kind' => $kind,
                'name' => $name,
            ];
            if (count($symbols) >= 300) {
                break 2;
            }
        }
    }

    $data = [
        'filter' => $filter === '' ? null : $filter,
        'count' => count($symbols),
        'symbols' => $symbols,
    ];

    $written = aiCliWriteArtifact($root, 'symbols', 'php tools/ai/ai.php symbols' . ($filter === '' ? '' : ' ' . $filter), $data, 'ok', null, 'Jump to symbol locations directly for faster edits.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

function aiRunLogs(string $root, array $args): int
{
    $logsDir = aiCliGeneratedDir($root) . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logsDir)) {
        throw new RuntimeException('No logs directory found at docs/ai/generated/logs');
    }

    $target = $args[0] ?? null;
    if ($target === null || $target === '') {
        $entries = array_values(array_filter(scandir($logsDir) ?: [], static fn(string $e): bool => $e !== '.' && $e !== '..'));
        sort($entries);
        $data = [
            'log_root' => 'docs/ai/generated/logs',
            'entries' => $entries,
            'count' => count($entries),
        ];
        $written = aiCliWriteArtifact($root, 'logs', 'php tools/ai/ai.php logs', $data, 'ok', null, 'Use logs <entry-or-file> to inspect details.');
        fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
        return 0;
    }

    $candidate = $logsDir . DIRECTORY_SEPARATOR . $target;
    if (!file_exists($candidate)) {
        throw new RuntimeException('Log target not found: ' . $target);
    }

    if (is_dir($candidate)) {
        $files = array_values(array_filter(scandir($candidate) ?: [], static fn(string $e): bool => $e !== '.' && $e !== '..'));
        sort($files);
        $data = [
            'target' => 'docs/ai/generated/logs/' . $target,
            'files' => $files,
        ];
    } else {
        $content = (string) file_get_contents($candidate);
        $data = [
            'target' => 'docs/ai/generated/logs/' . $target,
            'bytes' => strlen($content),
            'preview' => substr($content, 0, 4000),
        ];
    }

    $written = aiCliWriteArtifact($root, 'logs', 'php tools/ai/ai.php logs ' . $target, $data, 'ok', null, 'Inspect verify digest and resolve first failing check.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

function aiRunEnvCheck(string $root): int
{
    $required = ['bash', 'git', 'php', 'rg'];
    $contextRequired = ['repomix', 'scc', 'jq'];
    $optional = ['just', 'yq', 'shellcheck', 'shfmt', 'actionlint', 'lychee', 'gitleaks'];

    $check = static function (string $bin): array {
        $path = '';
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            $out = [];
            $exit = 0;
            exec('where.exe ' . escapeshellarg($bin) . ' 2>NUL', $out, $exit);
            if ($exit === 0 && $out !== []) {
                $path = trim((string) $out[0]);
            }
        } else {
            $path = trim((string) shell_exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null'));
        }

        return ['tool' => $bin, 'found' => $path !== '', 'path' => $path === '' ? null : $path];
    };

    $req = array_map($check, $required);
    $ctx = array_map($check, $contextRequired);
    $opt = array_map($check, $optional);

    $missingRequired = array_values(array_filter($req, static fn(array $r): bool => $r['found'] === false));
    $status = $missingRequired === [] ? 'ok' : 'warning';
    $next = $missingRequired === [] ? 'Environment is ready for core AI workflow commands.' : 'Install missing required tools before running full workflow.';

    $data = [
        'required' => $req,
        'context_required' => $ctx,
        'optional' => $opt,
        'missing_required' => array_map(static fn(array $r): string => $r['tool'], $missingRequired),
    ];

    $written = aiCliWriteArtifact($root, 'env-check', 'php tools/ai/ai.php env-check', $data, $status, null, $next);
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

function aiRunFileContext(string $root, array $args): int
{
    $target = $args[0] ?? '';
    if ($target === '') {
        throw new RuntimeException('file-context requires a target path argument');
    }
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);
    if (!is_file($path)) {
        throw new RuntimeException('file-context target not found: ' . $target);
    }

    $content = (string) file_get_contents($path);
    $lines = substr_count($content, "\n") + 1;
    $bytes = strlen($content);

    $related = [];
    exec('git -C ' . escapeshellarg($root) . ' grep -n ' . escapeshellarg(basename($target)) . ' --' . aiShellNullRedirect(), $related);
    $related = array_slice($related, 0, 30);

    $data = [
        'target' => $target,
        'bytes' => $bytes,
        'lines' => $lines,
        'estimated_tokens' => aiCliEstimateTokens($content),
        'related_references_preview' => $related,
        'content_preview' => substr($content, 0, 4000),
    ];

    $written = aiCliWriteArtifact($root, 'file-context', 'php tools/ai/ai.php file-context ' . $target, $data, 'ok', null, 'Read this file first, then open top related references if needed.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

function aiRunOrphans(string $root): int
{
    $candidates = [];
    exec('git -C ' . escapeshellarg($root) . ' ls-files "scripts/*.sh" "scripts/ai/*.sh" "tools/ai/*" "docs/ai/*.md"', $candidates);

    $possiblyOrphan = [];
    foreach ($candidates as $path) {
        if (str_starts_with($path, 'docs/ai/generated/')) {
            continue;
        }
        $refs = [];
        exec('git -C ' . escapeshellarg($root) . ' grep -n ' . escapeshellarg($path) . ' -- "README.md" "justfile" "docs" "scripts" "tools" ".github"' . aiShellNullRedirect(), $refs);
        $refs = array_values(array_filter($refs, static fn(string $line): bool => !str_contains($line, $path . ':')));
        if ($refs === []) {
            $possiblyOrphan[] = [
                'path' => $path,
                'reason' => 'no references found in key surfaces',
                'confidence' => 70,
            ];
        }
    }

    $status = $possiblyOrphan === [] ? 'ok' : 'warning';
    $data = [
        'orphan_score' => count($possiblyOrphan),
        'findings' => $possiblyOrphan,
    ];

    $written = aiCliWriteArtifact($root, 'orphans', 'php tools/ai/ai.php orphans', $data, $status, null, 'Review orphan candidates before deletion or context inclusion changes.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

function aiRunAutoFix(string $root, array $args): int
{
    $dryRun = in_array('--dry-run', $args, true);
    if (!$dryRun) {
        throw new RuntimeException('auto-fix currently supports only --dry-run');
    }

    $actions = [];
    $status = aiRunCommand($root, 'php tools/ai/generate-ai-catalog.php --check');
    if ($status['exit'] !== 0) {
        $actions[] = [
            'type' => 'generated-output',
            'action' => 'php tools/ai/generate-ai-catalog.php',
            'reason' => 'catalog drift detected',
            'safe' => true,
        ];
    }

    $status2 = aiRunCommand($root, 'php tools/ai/generate-repo-structure.php --check --with-scc');
    if ($status2['exit'] !== 0) {
        $actions[] = [
            'type' => 'generated-output',
            'action' => 'php tools/ai/generate-repo-structure.php --with-scc',
            'reason' => 'repo-structure drift detected',
            'safe' => true,
        ];
    }

    $data = [
        'mode' => 'dry-run',
        'safe_fixes' => $actions,
        'unsafe_fixes_skipped' => [
            [
                'type' => 'logic-change',
                'reason' => 'auto-fix does not modify production/workflow logic in this phase',
            ],
        ],
    ];

    $written = aiCliWriteArtifact($root, 'auto-fix', 'php tools/ai/ai.php auto-fix --dry-run', $data, 'ok', null, 'Apply listed safe regeneration commands manually, then run rebase-state.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

function aiRunWorkflow(string $root): int
{
    $graphPath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'workflow-graph.json';
    if (!is_file($graphPath)) {
        throw new RuntimeException('Missing docs/ai/workflow-graph.json');
    }

    $decoded = json_decode((string) file_get_contents($graphPath), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid JSON in docs/ai/workflow-graph.json');
    }

    $commands = $decoded['commands'] ?? [];
    $count = is_array($commands) ? count($commands) : 0;
    $data = [
        'workflow_graph' => 'docs/ai/workflow-graph.json',
        'command_count' => $count,
        'commands' => $commands,
    ];

    $written = aiCliWriteArtifact($root, 'workflow', 'php tools/ai/ai.php workflow', $data, 'ok', null, 'Use workflow dependencies to choose the next required command.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

function aiRunSnapshot(string $root): int
{
    $statusOut = [];
    $exit = 0;
    exec('git -C ' . escapeshellarg($root) . ' status --short', $statusOut, $exit);
    $dirty = $exit === 0 && $statusOut !== [];

    $data = [
        'branch' => aiCliCurrentBranch($root),
        'commit' => aiCliCurrentCommit($root),
        'dirty' => $dirty,
        'changed_files_count' => count($statusOut),
        'changed_files' => $statusOut,
    ];

    $written = aiCliWriteArtifact($root, 'project-snapshot', 'php tools/ai/ai.php snapshot', $data, 'ok', null, 'Run freshness, budget, then next.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

function aiRunSessionResume(string $root): int
{
    $snapshot = aiLoadArtifactData($root, 'project-snapshot.json');
    $diff = aiLoadArtifactData($root, 'diff-summary.json');
    $risk = aiLoadArtifactData($root, 'risk.json');
    $verify = aiLoadArtifactData($root, 'verify.json');
    $next = aiLoadArtifactData($root, 'next.json');
    $freshness = aiLoadArtifactData($root, 'freshness.json');

    $data = [
        'snapshot' => [
            'branch' => $snapshot['data']['branch'] ?? 'unknown',
            'commit' => $snapshot['data']['commit'] ?? 'unknown',
            'dirty' => $snapshot['data']['dirty'] ?? null,
            'changed_files_count' => $snapshot['data']['changed_files_count'] ?? null,
        ],
        'diff' => [
            'changed_files_count' => $diff['data']['changed_files_count'] ?? null,
            'base' => $diff['data']['base'] ?? 'unknown',
        ],
        'risk' => [
            'risk_level' => $risk['data']['risk_level'] ?? 'unknown',
            'risk_score' => $risk['data']['risk_score'] ?? null,
        ],
        'verify' => [
            'status' => $verify['data']['status'] ?? 'unknown',
            'failed_checks' => $verify['data']['failed_checks'] ?? [],
        ],
        'freshness' => [
            'stale_count' => $freshness['data']['stale_count'] ?? null,
        ],
        'next' => [
            'status' => $next['data']['status'] ?? 'unknown',
            'next_action' => $next['data']['next_action'] ?? null,
        ],
    ];

    $written = aiCliWriteArtifact($root, 'session-resume', 'php tools/ai/ai.php session-resume', $data, 'ok', null, 'Resume work from next_action and current verify/risk posture.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}
