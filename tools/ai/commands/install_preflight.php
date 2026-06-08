<?php

declare(strict_types=1);

require_once __DIR__ . '/../install/toolchain.php';

function aiRunPreflight(string $root): int
{
    $checks = aiInstallerEnvironmentChecks($root, true, false);

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
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return $failed === [] ? 0 : 1;
}

/**
 * Read-only health check: environment prerequisites, required tools, and install state.
 * Composes the preflight environment checks with install-manifest awareness so a user can
 * answer "is my environment ready and is the kit installed/healthy here?" in one command.
 */
function aiRunDoctor(string $root): int
{
    // Environment prerequisites (same baseline as preflight).
    $checks = aiInstallerEnvironmentChecks($root, false, true);

    // Git repository root (the installer refuses non-git targets without --allow-non-git).
    $isGitRepo = is_dir($root . DIRECTORY_SEPARATOR . '.git') || is_file($root . DIRECTORY_SEPARATOR . '.git');
    $checks[] = ['name' => 'git_repo_root', 'category' => 'env', 'status' => $isGitRepo ? 'passed' : 'warning', 'reason' => $isGitRepo ? null : 'not a git repo root; install needs --allow-non-git here'];

    // Recommended tools used by the AI workflow scripts.
    foreach (['rg', 'jq', 'fd', 'git'] as $tool) {
        $present = aiInstallerCommandExists($tool);
        $checks[] = ['name' => "tool_{$tool}", 'category' => 'tools', 'status' => $present ? 'passed' : 'warning', 'reason' => $present ? null : "{$tool} not found on PATH"];
    }

    // Install state.
    $manifestPath = $root . DIRECTORY_SEPARATOR . '.ai-install-manifest.json';
    $installed = is_file($manifestPath);
    $manifest = $installed ? json_decode((string) file_get_contents($manifestPath), true) : null;
    $fileCount = is_array($manifest) && is_array($manifest['files'] ?? null) ? count($manifest['files']) : 0;
    $checks[] = [
        'name' => 'install_manifest',
        'category' => 'install',
        'status' => $installed ? 'passed' : 'warning',
        'reason' => $installed ? null : 'kit not installed in this repo (run install)',
        'detail' => $installed ? "{$fileCount} managed files" : null,
    ];

    // Interrupted-install detection: a left-behind .ai/install.lock means a writing install
    // did not release the lock (crash/SIGKILL). Read-only warning; never auto-removed here.
    $lockPath = $root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'install.lock';
    $lockPresent = is_file($lockPath);
    $checks[] = [
        'name' => 'install_lock',
        'category' => 'install',
        'status' => $lockPresent ? 'warning' : 'passed',
        'reason' => $lockPresent ? 'stale .ai/install.lock present; a prior install may have been interrupted (no install running? remove it and rerun install)' : null,
    ];

    // Checksum drift: compare each managed file against its recorded installed_hash. Drifted or
    // missing managed files are reported as warnings so the user can re-run install if needed.
    if ($installed && is_array($manifest)) {
        $driftResult = aiInstallerCollectChecksumDrift($root, $manifest);
        $checks[] = [
            'name' => 'checksum_integrity',
            'category' => 'install',
            'status' => ($driftResult['drifted'] === [] && $driftResult['missing'] === []) ? 'passed' : 'warning',
            'reason' => ($driftResult['drifted'] === [] && $driftResult['missing'] === [])
                ? null
                : 'managed files changed since install: ' . count($driftResult['drifted']) . ' modified, ' . count($driftResult['missing']) . ' missing',
            'detail' => [
                'checked' => $driftResult['checked'],
                'drifted' => $driftResult['drifted'],
                'missing' => $driftResult['missing'],
            ],
        ];
    }

    $failed = array_values(array_filter($checks, static fn(array $c): bool => ($c['status'] ?? 'failed') === 'failed'));
    $warnings = array_values(array_filter($checks, static fn(array $c): bool => ($c['status'] ?? '') === 'warning'));
    $status = $failed !== [] ? 'failed' : ($warnings !== [] ? 'warning' : 'ok');

    $next = $failed !== []
        ? 'Resolve failed environment checks before installing.'
        : ($installed ? 'Environment ready; kit installed.' : 'Environment ready; run install to set up the kit.');

    $data = [
        'status' => $status,
        'installed' => $installed,
        'managed_file_count' => $fileCount,
        'checks' => $checks,
        'failed_count' => count($failed),
        'warning_count' => count($warnings),
        'recommended_next_action' => $next,
    ];

    $written = aiCliWriteArtifact($root, 'doctor', 'php tools/ai/ai.php doctor', $data, $status, null, $next);
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);

    // Read-only diagnostic: only hard environment failures are non-zero; warnings are exit 0.
    return $failed === [] ? 0 : 1;
}

/**
 * Read-only install status (Phase C / P5). Reports kit install state, kit-owned file integrity,
 * pending template updates, preserved user-owned files, conflict subtrees, and an HONESTLY
 * classified policy-enforcement picture. Reuses aiInstallerCollectChecksumDrift and the manifest
 * read used by doctor; it never reimplements drift logic and writes nothing except the standard
 * CLI artifact (docs/ai/generated/status.json), exactly like doctor.
 *
 * Invariant: no false enforcement claims. Policy sections are derived only from real filesystem
 * evidence (hook config presence + runtime reference). When evidence is incomplete the section
 * is classified `partial`/`unknown`/`advisory`/`absent`, never `enforced`.
 */
function aiRunStatus(string $root): int
{
    $sep = DIRECTORY_SEPARATOR;
    $abs = static fn(string $rel): string => $root . $sep . str_replace('/', $sep, $rel);

    // --- Section 1: install state (manifest presence is the source of truth) ---
    $manifestPath = $abs('.ai-install-manifest.json');
    $installed = is_file($manifestPath);
    $manifestRaw = $installed ? file_get_contents($manifestPath) : false;
    $manifest = ($manifestRaw !== false) ? json_decode((string) $manifestRaw, true) : null;

    // Hard error only when the manifest exists but cannot be parsed; otherwise read-only/exit 0.
    if ($installed && !is_array($manifest)) {
        $data = [
            'installed' => true,
            'error' => 'install manifest present but unreadable or invalid JSON: .ai-install-manifest.json',
        ];
        $written = aiCliWriteArtifact($root, 'status', 'php tools/ai/ai.php status', $data, 'failed', null, 'Repair or regenerate .ai-install-manifest.json (reinstall).');
        fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
        return 1;
    }

    $files = (is_array($manifest) && is_array($manifest['files'] ?? null)) ? $manifest['files'] : [];
    $managedFileCount = count($files);
    $profile = is_array($manifest) ? ($manifest['profile'] ?? null) : null;

    // Optional, cheap worktree cleanliness via porcelain. Guarded for non-git targets; if git is
    // unavailable or this is not a repo, report 'unknown' rather than overclaiming.
    $worktree = 'unknown';
    $isGitRepo = is_dir($abs('.git')) || is_file($abs('.git'));
    if ($isGitRepo) {
        $porcelain = [];
        $gitExit = 0;
        $prev = aiInstallerSafeCwdEnter();
        exec('git -C ' . escapeshellarg($root) . ' status --porcelain 2>/dev/null', $porcelain, $gitExit);
        aiInstallerSafeCwdLeave($prev);
        if ($gitExit === 0) {
            $worktree = ($porcelain === []) ? 'clean' : 'dirty';
        }
    }

    // Install lock: a left-behind .ai/install.lock means a prior writing install was interrupted.
    $lockPath = $abs('.ai/install.lock');
    $lockPresent = is_file($lockPath);

    $installState = [
        'installed' => $installed,
        'profile' => $profile,
        'managed_file_count' => $managedFileCount,
        'install_lock_present' => $lockPresent,
        'worktree' => $worktree,
    ];

    // --- Section 2: kit-owned files (REUSE drift helper; same data doctor surfaces) ---
    if ($installed && is_array($manifest)) {
        $drift = aiInstallerCollectChecksumDrift($root, $manifest);
    } else {
        $drift = ['checked' => 0, 'drifted' => [], 'missing' => []];
    }
    $kitOwned = [
        'clean' => max(0, $drift['checked'] - count($drift['drifted'])),
        'checked' => $drift['checked'],
        'modified' => $drift['drifted'],
        'missing' => $drift['missing'],
        'checksum_drift' => ($drift['drifted'] !== [] || $drift['missing'] !== []),
    ];

    // --- Section 3 & 6: rendered/stale == pending template updates under .ai/templates-new/* ---
    // This repo's only cheap "template update available" signal is the templates-new refresh
    // channel populated by the installer; there is no separate re-render diff to invent.
    // Canonical refresh-channel root (mirrors aiInstallerPrivateTemplatesNewRel() in install/core.php;
    // inlined here to keep status self-contained and free of cross-file load-order coupling).
    $templatesNewRel = '.ai/templates-new';
    $templatesNewDir = $abs($templatesNewRel);
    $templateUpdatePaths = aiStatusRelFiles($templatesNewDir, $templatesNewRel);
    $renderedStale = [
        'signal' => 'templates-new presence',
        'template_updates_available' => $templateUpdatePaths !== [],
        'pending_paths' => $templateUpdatePaths,
        'note' => 'No separate re-render diff exists in this repo; .ai/templates-new/* presence is the template-update signal.',
    ];

    // --- Section 4: preserved user files (ownership == template) that exist on disk ---
    $preserved = [];
    foreach ($files as $rel => $meta) {
        if (!is_string($rel) || !is_array($meta)) {
            continue;
        }
        if ((string) ($meta['ownership'] ?? 'owned') === 'template' && is_file($abs($rel))) {
            $preserved[] = $rel;
        }
    }
    sort($preserved, SORT_STRING);
    $projectFiles = aiStatusRelFiles($abs('docs/ai/project'), 'docs/ai/project');
    $preservedUserFiles = [
        'template_owned' => $preserved,
        'project_files_present' => $projectFiles !== [],
        'project_files' => $projectFiles,
    ];

    // --- Section 5: conflicts (.ai/conflicts/<ts>-<op> subtrees) ---
    $conflictsDir = $abs('.ai/conflicts');
    $conflictDirs = [];
    if (is_dir($conflictsDir)) {
        foreach (scandir($conflictsDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_dir($conflictsDir . $sep . $entry)) {
                $conflictDirs[] = $entry;
            }
        }
    }
    sort($conflictDirs, SORT_STRING);
    $conflicts = [
        'count' => count($conflictDirs),
        'subtrees' => $conflictDirs,
    ];

    // --- Section 7: policy enforcement (HONEST classification) ---
    $policy = aiStatusPolicyEnforcement($root);

    // Status rollup: warnings (drift/conflicts/stale/lock) are exit 0. Read-only diagnostic.
    $warnings = [];
    if ($kitOwned['checksum_drift']) {
        $warnings[] = 'kit-owned file drift: ' . count($drift['drifted']) . ' modified, ' . count($drift['missing']) . ' missing';
    }
    if ($conflicts['count'] > 0) {
        $warnings[] = $conflicts['count'] . ' conflict subtree(s) under .ai/conflicts/';
    }
    if ($renderedStale['template_updates_available']) {
        $warnings[] = count($templateUpdatePaths) . ' pending template update(s) under .ai/templates-new/';
    }
    if ($lockPresent) {
        $warnings[] = 'stale .ai/install.lock present (prior install may have been interrupted)';
    }
    if (!$installed) {
        $warnings[] = 'kit not installed in this repo (no .ai-install-manifest.json)';
    }

    $status = $warnings === [] ? 'ok' : 'warning';
    $next = !$installed
        ? 'Kit not installed here; run install to set up the kit.'
        : ($warnings === [] ? 'Install healthy; no drift, conflicts, or pending template updates.' : 'Review reported warnings (drift/conflicts/template updates) and act as needed.');

    $data = [
        'status' => $status,
        'install_state' => $installState,
        'kit_owned_files' => $kitOwned,
        'rendered_stale' => $renderedStale,
        'preserved_user_files' => $preservedUserFiles,
        'conflicts' => $conflicts,
        'template_updates' => [
            'available' => $renderedStale['template_updates_available'],
            'pending_paths' => $templateUpdatePaths,
        ],
        'policy_enforcement' => $policy,
        'warnings' => $warnings,
        'recommended_next_action' => $next,
    ];

    $written = aiCliWriteArtifact($root, 'status', 'php tools/ai/ai.php status', $data, $status, null, $next);
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);

    // Read-only diagnostic: warnings are exit 0; only a hard manifest error (handled above) is non-zero.
    return 0;
}

/**
 * List repo-relative file paths under a directory (recursive), sorted. Returns [] when the
 * directory is absent. Used for templates-new and project-file presence reporting.
 *
 * @return list<string>
 */
function aiStatusRelFiles(string $absDir, string $relPrefix): array
{
    if (!is_dir($absDir)) {
        return [];
    }
    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    $prefixLen = strlen($absDir);
    foreach ($it as $info) {
        /** @var SplFileInfo $info */
        if (!$info->isFile()) {
            continue;
        }
        $tail = str_replace(DIRECTORY_SEPARATOR, '/', substr($info->getPathname(), $prefixLen + 1));
        $out[] = rtrim($relPrefix, '/') . '/' . $tail;
    }
    sort($out, SORT_STRING);
    return $out;
}

/**
 * HONEST policy-enforcement classification from filesystem evidence only.
 *
 * OpenCode: `enforced` requires BOTH the runtime hook wiring (.github/hooks/tool-policy.json or
 * tool-guardian.json) AND a compiled policy present AND a runtime reference to that wiring from a
 * loaded config (opencode.jsonc or .vscode/settings.json). With policy files but no runtime
 * reference -> `advisory`. With neither -> `absent`. Anything ambiguous -> `partial`.
 *
 * Copilot: Copilot has no enforced hook runtime, so instructions are advisory by nature; classify
 * `advisory` when present, `absent` otherwise. Never `enforced`.
 *
 * Runtime hooks: factual presence checks only (no behavioral claim).
 *
 * @return array<string,mixed>
 */
function aiStatusPolicyEnforcement(string $root): array
{
    $sep = DIRECTORY_SEPARATOR;
    $abs = static fn(string $rel): string => $root . $sep . str_replace('/', $sep, $rel);
    $has = static fn(string $rel): bool => is_file($abs($rel));

    // Factual runtime-hook presence.
    $guardianSh = $has('.github/hooks/scripts/tool-guardian.sh');
    $guardianPs1 = $has('.github/hooks/scripts/tool-guardian.ps1');
    $compiledPolicy = $has('.github/hooks/scripts/command-policy.compiled.sh');
    $runtimeHooks = [
        'tool_guardian_sh' => $guardianSh,
        'tool_guardian_ps1' => $guardianPs1,
        'command_policy_compiled_sh' => $compiledPolicy,
    ];

    // Hook config files (the wiring that maps a tool event to a guard command).
    $toolPolicyCfg = $has('.github/hooks/tool-policy.json');
    $toolGuardianCfg = $has('.github/hooks/tool-guardian.json');
    $hookConfigPresent = $toolPolicyCfg || $toolGuardianCfg;

    // Runtime reference: is the hook wiring actually referenced by a loaded config? We look for a
    // literal reference to the hook config or guard scripts in opencode.jsonc / .vscode/settings.json.
    $referenced = false;
    foreach (['opencode.jsonc', '.vscode/settings.json'] as $cfgRel) {
        $cfgAbs = $abs($cfgRel);
        if (!is_file($cfgAbs)) {
            continue;
        }
        $body = (string) file_get_contents($cfgAbs);
        if (
            str_contains($body, 'tool-policy.json')
            || str_contains($body, 'tool-guardian')
            || str_contains($body, '.github/hooks')
        ) {
            $referenced = true;
            break;
        }
    }

    // OpenCode classification.
    if ($hookConfigPresent && $compiledPolicy && $referenced) {
        $opencode = 'enforced';
    } elseif ($hookConfigPresent || $compiledPolicy) {
        // Policy files exist but no runtime reference wires them into an enforced runtime.
        $opencode = $referenced ? 'partial' : 'advisory';
    } else {
        $opencode = 'absent';
    }

    // Copilot: advisory-by-nature; presence only flips advisory vs absent.
    $copilotPresent = $has('.github/copilot-instructions.md');
    $copilot = $copilotPresent ? 'advisory' : 'absent';

    return [
        'opencode' => [
            'classification' => $opencode,
            'hook_config_present' => $hookConfigPresent,
            'compiled_policy_present' => $compiledPolicy,
            'runtime_reference_present' => $referenced,
        ],
        'copilot' => [
            'classification' => $copilot,
            'instructions_present' => $copilotPresent,
            'note' => 'Copilot has no enforced hook runtime; instructions are advisory by nature.',
        ],
        'runtime_hooks' => $runtimeHooks,
    ];
}

/**
 * Compare managed files against their recorded installed_hash. Returns counts and the lists of
 * drifted (content changed) and missing files. Installer-generated/volatile artifacts and
 * user-owned template files are excluded so the check reflects real integrity drift only.
 *
 * @param array<string,mixed> $manifest
 * @return array{checked:int,drifted:list<string>,missing:list<string>}
 */
function aiInstallerCollectChecksumDrift(string $root, array $manifest): array
{
    $skip = [
        'docs/ai/POST-INSTALL.md',
        'docs/ai/available-packs.md',
        'docs/ai/SETUP.md',
        'docs/ai/installed-files.md',
        'docs/ai/project-configuration.md',
        'docs/ai/generated/install-summary.md',
        'docs/ai/generated/install-instructions.md',
        'docs/ai/generated/install-instructions.json',
        'docs/ai/generated/install-manifest.json',
        '.ai/project.yml',
    ];

    $checked = 0;
    $drifted = [];
    $missing = [];

    foreach (($manifest['files'] ?? []) as $rel => $meta) {
        if (!is_string($rel) || !is_array($meta)) {
            continue;
        }
        if (in_array($rel, $skip, true) || str_starts_with($rel, 'docs/ai/generated/')) {
            continue;
        }
        // Only verify files whose content the kit owns; template files are user-editable.
        if ((string) ($meta['ownership'] ?? 'owned') === 'template') {
            continue;
        }
        $expected = (string) ($meta['installed_hash'] ?? '');
        if ($expected === '' || !str_starts_with($expected, 'sha256:')) {
            continue;
        }
        $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($abs)) {
            $missing[] = $rel;
            continue;
        }
        $checked++;
        $actual = 'sha256:' . hash_file('sha256', $abs);
        if ($actual !== $expected) {
            $drifted[] = $rel;
        }
    }

    sort($drifted, SORT_STRING);
    sort($missing, SORT_STRING);

    return ['checked' => $checked, 'drifted' => $drifted, 'missing' => $missing];
}

/**
 * Shared environment checks consumed by preflight and doctor so their common baseline cannot drift.
 *
 * @return list<array<string,mixed>>
 */
function aiInstallerEnvironmentChecks(string $root, bool $includeZip, bool $withCategory): array
{
    $category = $withCategory ? ['category' => 'env'] : [];

    $checks = [];
    $checks[] = array_merge(
        ['name' => 'php_version', 'status' => version_compare(PHP_VERSION, '8.1.0', '>=') ? 'passed' : 'failed', 'required' => '>=8.1'],
        $category,
        $withCategory ? ['detail' => PHP_VERSION] : []
    );
    $checks[] = array_merge(['name' => 'ext_json', 'status' => extension_loaded('json') ? 'passed' : 'failed'], $category);
    $checks[] = array_merge(['name' => 'ext_mbstring', 'status' => extension_loaded('mbstring') ? 'passed' : 'failed'], $category);

    if ($includeZip) {
        $checks[] = ['name' => 'ext_zip', 'status' => extension_loaded('zip') ? 'passed' : 'warning', 'reason' => extension_loaded('zip') ? null : 'ZipArchive unavailable; directory backup fallback will be used'];
    }

    $gitOut = [];
    $gitExit = 0;
    $gitSafePrev = aiInstallerSafeCwdEnter();
    exec('git --version', $gitOut, $gitExit);
    aiInstallerSafeCwdLeave($gitSafePrev);
    $checks[] = array_merge(['name' => 'git', 'status' => $gitExit === 0 ? 'passed' : 'failed'], $category);

    return $checks;
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
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
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
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
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
    $auditSafePrev = aiInstallerSafeCwdEnter();
    exec('git -C ' . escapeshellarg($root) . ' ls-files ".github/instructions/*.instructions.md" ".opencode/**"', $extra);
    aiInstallerSafeCwdLeave($auditSafePrev);
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
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
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
