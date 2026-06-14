<?php

declare(strict_types=1);

function aiRunPacks(string $root, array $args): int
{
    $registry = aiInstallerPackRegistry();
    $errors = aiInstallerValidatePackRegistry($registry);
    $profiles = aiInstallerProfileDefinitions();
    $data = [
        'profiles' => $profiles,
        'all_features' => aiInstallerAllFeaturePacks(),
        'available_packs' => array_keys($registry),
        'registry_errors' => $errors,
        'validation_requested' => in_array('--validate', $args, true),
        'notes' => ['docs-reference is optional add-on only'],
    ];
    $status = $errors === [] ? 'ok' : 'failed';
    $written = aiCliWriteArtifact($root, 'packs', 'php tools/ai/ai.php packs', $data, $status, null, $errors === [] ? 'Pack contracts validated.' : 'Fix pack registry contract errors.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return $errors === [] ? 0 : 1;
}

function aiRunVersion(string $root): int
{
    $manifestPath = aiInstallManifestPath($root);
    $data = ['manifest_path' => '.ai-install-manifest.json', 'present' => is_file($manifestPath)];
    if (is_file($manifestPath)) {
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (is_array($manifest)) {
            $data['package'] = $manifest['package'] ?? [];
            $data['installer_version'] = $manifest['installer_version'] ?? 'unknown';
            $data['schema_version'] = $manifest['schema_version'] ?? 'unknown';
        }
    }
    $status = ($data['present'] ?? false) ? 'ok' : 'warning';
    $written = aiCliWriteArtifact($root, 'version', 'php tools/ai/ai.php version', $data, $status, null, is_file($manifestPath) ? 'Canonical install manifest loaded.' : 'Install manifest missing; run install first.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return is_file($manifestPath) ? 0 : 1;
}

function aiRunPlaceholders(string $root, array $args): int
{
    $fail = in_array('--fail', $args, true);
    $interactive = in_array('--interactive', $args, true);
    $apply = in_array('--apply', $args, true);
    $applyResult = null;
    if ($apply) {
        // Registry-driven substitution: replace mapped tokens with concrete
        // .ai/project.yml values before scanning, so the scan reports the
        // post-apply state. Limited to --files=<a,b,c> when provided.
        $applyResult = aiPlaceholderApplyFromProjectValues($root, aiParseArg($args, 'files'));
    }
    $setValues = [];
    foreach ($args as $arg) {
        if (str_starts_with($arg, '--set')) {
            $value = '';
            if ($arg === '--set') {
                continue;
            }
            if (str_starts_with($arg, '--set=')) {
                $value = substr($arg, 6);
            }
            if ($value !== '' && str_contains($value, '=')) {
                [$k, $v] = explode('=', $value, 2);
                $setValues['<' . strtoupper(trim($k)) . '>'] = $v;
            }
        }
    }

    $paths = ['AGENTS.md', 'docs/ai', '.github', '.opencode'];
    $hits = [];
    foreach ($paths as $path) {
        $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (is_file($abs)) {
            aiApplyPlaceholderSetsToFile($abs, $setValues);
            $content = (string) file_get_contents($abs);
            if (preg_match_all('/<[A-Z0-9_]+>/', $content, $m) === 1 || (isset($m[0]) && $m[0] !== [])) {
                $hits[] = ['path' => $path, 'placeholders' => array_values(array_unique($m[0]))];
            }
            continue;
        }
        if (!is_dir($abs)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') {
                continue;
            }
            aiApplyPlaceholderSetsToFile($file->getPathname(), $setValues);
            $content = (string) file_get_contents($file->getPathname());
            if (preg_match_all('/<[A-Z0-9_]+>/', $content, $m) === 1 || (isset($m[0]) && $m[0] !== [])) {
                $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                $hits[] = ['path' => $rel, 'placeholders' => array_values(array_unique($m[0]))];
            }
        }
    }

    if ($interactive && $hits !== []) {
        $all = [];
        foreach ($hits as $hit) {
            foreach ($hit['placeholders'] as $ph) {
                $all[$ph] = true;
            }
        }
        foreach (array_keys($all) as $token) {
            $input = aiPromptLine("Set {$token} (leave blank to skip): ");
            if ($input === '') {
                continue;
            }
            aiReplaceTokenAcrossPaths($root, $paths, $token, $input);
        }
    }

    $data = ['count' => count($hits), 'items' => $hits, 'mode' => $apply ? 'apply' : ($fail ? 'fail' : 'scan')];
    if ($applyResult !== null) {
        $data['apply'] = $applyResult;
    }
    $status = $hits === [] ? 'ok' : ($fail ? 'failed' : 'warning');
    $written = aiCliWriteArtifact($root, 'placeholders', 'php tools/ai/ai.php placeholders', $data, $status, null, $hits === [] ? 'No unresolved placeholders found.' : 'Resolve placeholders before strict verification.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return $status === 'failed' ? 1 : 0;
}

/**
 * Substitute placeholder tokens from `.ai/project.yml` values, guided by the
 * placeholders.json registry (only tokens marked substitute=true are touched;
 * values that are empty or 'unknown' are skipped).
 *
 * @param string|null $filesArg comma-separated relative paths; null applies to default scan roots
 * @return array<string,mixed> apply evidence for the artifact envelope
 */
function aiPlaceholderApplyFromProjectValues(string $root, ?string $filesArg): array
{
    $registry = aiPlaceholderRegistryLoad($root);
    $values = aiInstallerLoadProjectValues($root, basename($root));
    $map = aiInstallerProjectValuesPlaceholderMap($values);

    $substitutable = null;
    if ($registry !== null) {
        $substitutable = [];
        foreach ($registry['tokens'] as $entry) {
            if (is_array($entry) && is_string($entry['token'] ?? null) && ($entry['substitute'] ?? false) === true) {
                $substitutable[$entry['token']] = true;
            }
        }
    }

    $replacements = [];
    foreach ($map as $token => $value) {
        if ($value === '' || $value === 'unknown') {
            continue;
        }
        if ($substitutable !== null && !isset($substitutable[$token])) {
            continue;
        }
        $replacements[$token] = $value;
    }

    $targets = [];
    if ($filesArg !== null && trim($filesArg) !== '') {
        foreach (explode(',', $filesArg) as $rel) {
            $rel = trim($rel);
            if ($rel !== '') {
                $targets[] = $rel;
            }
        }
        aiInstallerAssertSafePlanTargets($root, array_map(
            static fn(string $target): array => ['target' => $target],
            $targets
        ));
    } else {
        $targets = ['AGENTS.md', 'docs/ai', '.github', '.opencode'];
    }

    $applied = [];
    foreach ($targets as $rel) {
        $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_file($abs)) {
            $count = aiPlaceholderApplyToFile($abs, $replacements);
            if ($count > 0) {
                $applied[] = ['path' => $rel, 'replacements' => $count];
            }
            continue;
        }
        if (!is_dir($abs)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') {
                continue;
            }
            $fileRel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if (aiInstallerShouldSkipPlaceholderScanPath($fileRel)) {
                continue;
            }
            $count = aiPlaceholderApplyToFile($file->getPathname(), $replacements);
            if ($count > 0) {
                $applied[] = ['path' => $fileRel, 'replacements' => $count];
            }
        }
    }

    return [
        'registry_found' => $registry !== null,
        'tokens_with_values' => array_keys($replacements),
        'files_changed' => $applied,
        'files_changed_count' => count($applied),
    ];
}

/** @param array<string,string> $replacements @return int replacement count actually written */
function aiPlaceholderApplyToFile(string $filePath, array $replacements): int
{
    if ($replacements === [] || !is_file($filePath)) {
        return 0;
    }
    $content = (string) file_get_contents($filePath);
    $count = 0;
    $updated = str_replace(array_keys($replacements), array_values($replacements), $content, $count);
    if ($updated === $content) {
        return 0;
    }
    file_put_contents($filePath, $updated);
    return $count;
}

function aiApplyPlaceholderSetsToFile(string $filePath, array $setValues): void
{
    if ($setValues === []) {
        return;
    }
    $content = (string) file_get_contents($filePath);
    $updated = str_replace(array_keys($setValues), array_values($setValues), $content);
    if ($updated !== $content) {
        file_put_contents($filePath, $updated);
    }
}

function aiReplaceTokenAcrossPaths(string $root, array $paths, string $token, string $value): void
{
    foreach ($paths as $path) {
        $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (is_file($abs)) {
            aiApplyPlaceholderSetsToFile($abs, [$token => $value]);
            continue;
        }
        if (!is_dir($abs)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') {
                continue;
            }
            aiApplyPlaceholderSetsToFile($file->getPathname(), [$token => $value]);
        }
    }
}

function aiRunHooks(string $root, array $args): int
{
    $driver = aiParseArg($args, 'driver') ?? 'none';
    $install = in_array('install', $args, true);
    $commands = [];
    if ($install) {
        if ($driver === 'husky') {
            $commands[] = 'npx husky add .husky/pre-commit "bash scripts/hooks/pre-commit.sh"';
            $commands[] = 'npx husky add .husky/commit-msg "bash scripts/hooks/commit-msg.sh"';
        } elseif ($driver === 'lefthook') {
            $commands[] = 'Map scripts/hooks/pre-commit.sh and commit-msg.sh in .lefthook.yml';
        } elseif ($driver === 'native') {
            $commands[] = 'cp scripts/hooks/pre-commit.sh .git/hooks/pre-commit && chmod +x .git/hooks/pre-commit';
            $commands[] = 'cp scripts/hooks/commit-msg.sh .git/hooks/commit-msg && chmod +x .git/hooks/commit-msg';
        }
    }
    $data = [
        'status' => $install ? 'manual-required' : 'planned',
        'install_requested' => $install,
        'driver' => $driver,
        'supported_drivers' => ['husky', 'lefthook', 'native'],
        'wiring_commands' => $commands,
        'note' => 'Hook wiring remains explicit and opt-in.',
    ];
    $written = aiCliWriteArtifact($root, 'hooks', 'php tools/ai/ai.php hooks', $data, 'ok', null, 'Install hooks explicitly per selected driver.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

function aiRunToolchain(string $root, array $args): int
{
    $withRaw = aiParseArg($args, 'with') ?? aiParseArg($args, 'toolchain-tools') ?? '';
    $with = $withRaw === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $withRaw)), static fn(string $v): bool => $v !== ''));
    $profile = aiParseArg($args, 'profile') ?? 'dual';
    $runtime = aiParseArg($args, 'runtime') ?? 'both';
    $check = in_array('--check', $args, true) || in_array('--toolchain-check', $args, true) || !in_array('--install-plan', $args, true);
    $installPlan = in_array('--install-plan', $args, true) || in_array('--toolchain-install-plan', $args, true);
    $apply = in_array('--toolchain-apply', $args, true);
    $assumeYes = in_array('--yes', $args, true);

    $cfg = aiInstallerConfigFromAiArgs($root, ['--profile', $profile, '--runtime', $runtime, '--dry-run']);
    $packs = aiInstallerResolveSelectedPacks($cfg, aiInstallerPackRegistry());
    $tools = aiInstallerSelectedToolList($packs, $with);
    $report = aiInstallerToolchainReport($tools);

    $platform = aiInstallerPlatformKey();
    $installActions = [];
    foreach ($report as $row) {
        if (($row['present'] ?? false) === true) {
            continue;
        }
        $hints = $row['install_hints'] ?? [];
        $hint = (string) ($hints[$platform] ?? ($hints['npm'] ?? 'manual install required'));
        $installActions[] = ['tool' => $row['tool'], 'hint' => $hint, 'safe_auto_install' => (bool) ($row['safe_auto_install'] ?? false)];
    }

    $applied = [];
    if ($apply) {
        if (!$assumeYes) {
            fwrite(STDOUT, "Toolchain apply is about to run safe auto-install commands (if any).\n");
            if (!aiPromptYesNo('Continue with toolchain apply?', true)) {
                $data = [
                    'status' => 'blocked',
                    'reason' => 'toolchain apply cancelled by user',
                    'profile' => $profile,
                    'runtime' => $runtime,
                    'packs' => $packs,
                    'apply_requested' => true,
                ];
                $written = aiCliWriteArtifact($root, 'toolchain', 'php tools/ai/ai.php toolchain', $data, 'blocked', null, 'Re-run with --yes to apply non-interactively.');
                fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
                return 1;
            }
        }

        foreach ($report as $row) {
            if (($row['present'] ?? false)) {
                continue;
            }
            if (!($row['safe_auto_install'] ?? false)) {
                $hints = $row['install_hints'] ?? [];
                $hint = (string) ($hints[$platform] ?? ($hints['npm'] ?? 'manual install required'));
                $applied[] = ['tool' => $row['tool'], 'status' => 'blocked', 'reason' => 'auto-install not approved', 'hint' => $hint];
                continue;
            }
            $requires = is_array($row['requires_before_install'] ?? null) ? $row['requires_before_install'] : [];
            $missingReq = aiInstallerMissingTools($requires);
            if ($missingReq !== []) {
                $applied[] = ['tool' => $row['tool'], 'status' => 'blocked', 'reason' => 'missing prerequisite tools: ' . implode(', ', $missingReq)];
                continue;
            }
            $commands = $row['install_commands'] ?? [];
            $cmd = is_array($commands['npm'] ?? null) ? $commands['npm'] : [];
            if ($cmd === []) {
                $applied[] = ['tool' => $row['tool'], 'status' => 'blocked', 'reason' => 'no safe install command'];
                continue;
            }
            $result = aiInstallerRunArgv($cmd, $root);
            $applied[] = ['tool' => $row['tool'], 'status' => $result['exit'] === 0 ? 'installed' : 'failed', 'exit' => $result['exit']];
        }
    }

    $data = [
        'status' => 'ok',
        'profile' => $profile,
        'runtime' => $runtime,
        'packs' => $packs,
        'check_requested' => $check,
        'install_plan_requested' => $installPlan,
        'apply_requested' => $apply,
        'tools' => $report,
        'install_actions' => $installActions,
        'apply_results' => $applied,
    ];
    $written = aiCliWriteArtifact($root, 'toolchain', 'php tools/ai/ai.php toolchain', $data, 'ok', null, 'Review missing tools and rerun with --toolchain-apply only when needed.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

function aiRunScriptById(string $root, string $scriptId, array $args, ?array $selectedPacks = null): array
{
    $registry = aiInstallerScriptRegistry();
    if (!isset($registry[$scriptId])) {
        return ['exit' => 1, 'error' => 'unknown script id: ' . $scriptId];
    }
    $entry = $registry[$scriptId];
    $requiredPack = (string) ($entry['pack'] ?? '');
    if (is_array($selectedPacks) && $requiredPack !== '' && !in_array($requiredPack, $selectedPacks, true)) {
        return ['exit' => 1, 'error' => 'script requires missing pack: ' . $requiredPack, 'required_pack' => $requiredPack];
    }
    $scriptPath = aiInstallerResolveScriptPath($root, $entry);
    if ($scriptPath === null) {
        return ['exit' => 1, 'error' => 'script file not found for id: ' . $scriptId];
    }

    // Hard precheck: only tools the script ALWAYS needs (required_tools). Tools that are
    // specific to a subset of modes belong in optional_tools and must not block the whole
    // tool when missing — the script's own per-mode guard fails closed for modes that need
    // them. This prevents e.g. a missing fd/ast-grep from blocking ai-search 'tracked'
    // (git grep), which needs neither.
    $requiredTools = is_array($entry['required_tools'] ?? null) ? $entry['required_tools'] : [];
    $missing = aiInstallerMissingTools($requiredTools);
    if ($missing !== []) {
        return [
            'exit' => 1,
            'reason' => 'missing_required_tool',
            'safe_next_step' => 'Install the missing tool(s) (' . implode(', ', $missing) . ') or pick a tool that does not need them.',
            'error' => 'missing required tools: ' . implode(', ', $missing),
            'missing_tools' => $missing,
        ];
    }

    $optionalTools = is_array($entry['optional_tools'] ?? null) ? $entry['optional_tools'] : [];
    $missingOptional = $optionalTools === [] ? [] : aiInstallerMissingTools($optionalTools);

    $dryRun = in_array('--dry-run', $args, true) || !in_array('--apply', $args, true);
    $scriptArgs = aiArgsAfterDoubleDash($args);
    $argv = array_merge(['bash', $scriptPath], $scriptArgs);
    if ($dryRun) {
        $result = ['exit' => 0, 'dry_run' => true, 'argv' => $argv, 'script_id' => $scriptId, 'script_path' => str_replace('\\', '/', substr($scriptPath, strlen($root) + 1))];
        if ($missingOptional !== []) {
            $result['missing_optional_tools'] = $missingOptional;
            $result['warnings'] = ['missing optional tools (only some modes need them): ' . implode(', ', $missingOptional)];
        }
        return $result;
    }

    $run = aiInstallerRunArgv($argv, $root);
    $result = [
        'exit' => $run['exit'],
        'dry_run' => false,
        'argv' => $argv,
        'script_id' => $scriptId,
        'script_path' => str_replace('\\', '/', substr($scriptPath, strlen($root) + 1)),
        'stdout_preview' => substr((string) ($run['stdout'] ?? ''), 0, 3000),
        'stderr_preview' => substr((string) ($run['stderr'] ?? ''), 0, 3000),
    ];
    if ($missingOptional !== []) {
        $result['missing_optional_tools'] = $missingOptional;
        $result['warnings'] = ['missing optional tools (only some modes need them): ' . implode(', ', $missingOptional)];
    }
    return $result;
}

function aiRunScriptCommand(string $root, array $args): int
{
    if (in_array('--list', $args, true)) {
        $registry = aiInstallerScriptRegistry();
        $items = [];
        foreach ($registry as $id => $entry) {
            $items[] = ['id' => $id, 'label' => $entry['label'] ?? $id, 'pack' => $entry['pack'] ?? 'unknown'];
        }
        $written = aiCliWriteArtifact($root, 'scripts', 'php tools/ai/ai.php run-script --list', ['scripts' => $items], 'ok', null, 'Run one with: php tools/ai/ai.php run-script <id> --dry-run');
        fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
        return 0;
    }

    $scriptId = '';
    foreach ($args as $arg) {
        if ($arg !== '' && $arg[0] !== '-') {
            $scriptId = $arg;
            break;
        }
    }
    if ($scriptId === '') {
        throw new RuntimeException('run-script requires script id or --list');
    }

    $run = aiRunScriptById($root, $scriptId, $args, null);
    $status = ($run['exit'] ?? 1) === 0 ? 'ok' : 'failed';
    $written = aiCliWriteArtifact($root, 'scripts', 'php tools/ai/ai.php run-script ' . $scriptId, $run, $status, null, $status === 'ok' ? 'Script run completed.' : 'Fix script/tool errors and retry.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return $status === 'ok' ? 0 : 1;
}

/**
 * Build a single tool descriptor for the gateway from a registry entry.
 *
 * @param array<string,mixed> $entry
 * @return array<string,mixed>
 */
/**
 * P3.6 — Canonical machine-readable gateway reason codes.
 *
 * Every non-ok gateway response carries one of these in `reason` plus a
 * `safe_next_step`, so agents can act deterministically ("stop, do not retry")
 * instead of parsing free-text error strings.
 *
 * @return list<string>
 */
function aiToolGatewayReasonCodes(): array
{
    return [
        'unknown_id',
        'unknown_profile',
        'profile_mismatch',
        'missing_required_tool',
        'approval_required',
        'mutating_requires_apply',
        'unsupported_mode',
        'external_directory_blocked',
        'secret_path_blocked',
        'timeout',
    ];
}

/**
 * Build a standardized blocked/failed gateway payload.
 *
 * @param array<string,mixed> $extra
 * @return array<string,mixed>
 */
function aiToolGatewayReasonPayload(string $reason, string $safeNextStep, array $extra = []): array
{
    $blockedReasons = ['approval_required', 'mutating_requires_apply'];

    return array_merge([
        'status' => in_array($reason, $blockedReasons, true) ? 'blocked' : 'failed',
        'reason' => $reason,
        'safe_next_step' => $safeNextStep,
    ], $extra);
}

function aiToolGatewayDescriptor(string $id, array $entry): array
{
    // P3.0: build on the single shared normalizer so the gateway view and the
    // registry:export projection never drift. The gateway adds its own 'label'
    // and keeps a stable key set for existing consumers/tests.
    $normalized = aiInstallerNormalizeScriptEntry($id, $entry);

    return array_merge(
        ['id' => $id, 'label' => $entry['label'] ?? $id],
        $normalized
    );
}

/**
 * Resolve the requested --profile value, or null when not provided.
 *
 * Accepts either a profile id (readonly/verify/impl) or an agent name, which is
 * mapped to its profile via aiInstallerAgentProfiles(). Returns the raw value
 * unchanged when it matches neither, so callers can fail closed on it.
 */
function aiToolGatewayRequestedProfile(array $args): ?string
{
    $profile = aiParseArg($args, 'profile');
    if ($profile === null || $profile === '') {
        return null;
    }

    if (in_array($profile, aiInstallerScriptProfileNames(), true)) {
        return $profile;
    }

    $agents = aiInstallerAgentProfiles();
    if (isset($agents[$profile])) {
        return $agents[$profile];
    }

    return $profile;
}

/** tool:list — thin view over the script registry, filtered by profile. */
function aiRunToolListCommand(string $root, array $args): int
{
    $profile = aiToolGatewayRequestedProfile($args);
    $validProfiles = aiInstallerScriptProfileNames();
    if ($profile !== null && !in_array($profile, $validProfiles, true)) {
        $data = aiToolGatewayReasonPayload(
            'unknown_profile',
            'Use one of the valid profiles: ' . implode(', ', $validProfiles),
            ['error' => 'unknown profile: ' . $profile, 'profile' => $profile, 'valid_profiles' => $validProfiles]
        );
        $written = aiCliWriteArtifact($root, 'tool-list', 'php tools/ai/ai.php tool:list', $data, 'failed', null, $data['safe_next_step']);
        fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
        return 1;
    }

    $registry = aiInstallerScriptRegistry();
    $tools = [];
    foreach ($registry as $id => $entry) {
        $descriptor = aiToolGatewayDescriptor($id, $entry);
        if ($profile !== null && !in_array($profile, $descriptor['profiles'], true)) {
            continue;
        }
        $tools[] = $descriptor;
    }

    $data = ['profile' => $profile, 'valid_profiles' => $validProfiles, 'tools' => $tools, 'count' => count($tools)];
    $written = aiCliWriteArtifact($root, 'tool-list', 'php tools/ai/ai.php tool:list', $data, 'ok', null, 'Inspect one with: php tools/ai/ai.php tool:describe <id>');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

/** tool:describe — single-entry projection. Fails closed on unknown id. */
function aiRunToolDescribeCommand(string $root, array $args): int
{
    $toolId = '';
    foreach ($args as $arg) {
        if ($arg !== '' && $arg[0] !== '-') {
            $toolId = $arg;
            break;
        }
    }
    if ($toolId === '') {
        throw new RuntimeException('tool:describe requires a tool id');
    }

    $registry = aiInstallerScriptRegistry();
    if (!isset($registry[$toolId])) {
        $data = aiToolGatewayReasonPayload(
            'unknown_id',
            'List tools with: php tools/ai/ai.php tool:list',
            ['error' => 'unknown tool id: ' . $toolId, 'tool' => $toolId]
        );
        $written = aiCliWriteArtifact($root, 'tool-describe', 'php tools/ai/ai.php tool:describe ' . $toolId, $data, 'failed', null, $data['safe_next_step']);
        fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
        return 1;
    }

    $data = ['tool' => aiToolGatewayDescriptor($toolId, $registry[$toolId])];
    $written = aiCliWriteArtifact($root, 'tool-describe', 'php tools/ai/ai.php tool:describe ' . $toolId, $data, 'ok', null, 'Run it with: php tools/ai/ai.php tool:run ' . $toolId . ' --dry-run');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}

/**
 * tool:run — profile- and approval-aware wrapper over aiRunScriptById.
 *
 * Fails closed when: id is unknown, id is not visible to the requested profile,
 * or an approval-required tool is invoked without --apply (stays dry-run).
 */
function aiRunToolRunCommand(string $root, array $args): int
{
    $toolId = '';
    foreach ($args as $arg) {
        if ($arg !== '' && $arg[0] !== '-') {
            $toolId = $arg;
            break;
        }
    }
    if ($toolId === '') {
        throw new RuntimeException('tool:run requires a tool id');
    }

    $registry = aiInstallerScriptRegistry();
    if (!isset($registry[$toolId])) {
        $data = aiToolGatewayReasonPayload(
            'unknown_id',
            'List tools with: php tools/ai/ai.php tool:list',
            ['error' => 'unknown tool id: ' . $toolId, 'tool' => $toolId]
        );
        $written = aiCliWriteArtifact($root, 'tool-run', 'php tools/ai/ai.php tool:run ' . $toolId, $data, 'failed', null, $data['safe_next_step']);
        fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
        return 1;
    }

    $entry = $registry[$toolId];
    $descriptor = aiToolGatewayDescriptor($toolId, $entry);

    $profile = aiToolGatewayRequestedProfile($args);
    $validProfiles = aiInstallerScriptProfileNames();
    if ($profile !== null) {
        if (!in_array($profile, $validProfiles, true)) {
            $data = aiToolGatewayReasonPayload(
                'unknown_profile',
                'Use one of the valid profiles: ' . implode(', ', $validProfiles),
                ['error' => 'unknown profile: ' . $profile, 'profile' => $profile, 'valid_profiles' => $validProfiles]
            );
            $written = aiCliWriteArtifact($root, 'tool-run', 'php tools/ai/ai.php tool:run ' . $toolId, $data, 'failed', null, $data['safe_next_step']);
            fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
            return 1;
        }
        if (!in_array($profile, $descriptor['profiles'], true)) {
            $data = aiToolGatewayReasonPayload(
                'profile_mismatch',
                'Use a profile that includes this tool (' . implode(', ', $descriptor['profiles']) . '), or pick an allowed tool.',
                ['error' => 'tool not available to profile', 'tool' => $toolId, 'profile' => $profile, 'tool_profiles' => $descriptor['profiles']]
            );
            $written = aiCliWriteArtifact($root, 'tool-run', 'php tools/ai/ai.php tool:run ' . $toolId, $data, 'failed', null, $data['safe_next_step']);
            fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
            return 1;
        }
    }

    // Approval gate: an approval-required tool must not run without explicit --apply.
    $wantsApply = in_array('--apply', $args, true);
    if ($descriptor['requires_approval'] && !$wantsApply) {
        $data = aiToolGatewayReasonPayload(
            'approval_required',
            'Stop and do not retry. Re-run with --apply only after explicit human approval.',
            ['tool' => $toolId, 'requires_approval' => true]
        );
        $written = aiCliWriteArtifact($root, 'tool-run', 'php tools/ai/ai.php tool:run ' . $toolId, $data, 'blocked', null, $data['safe_next_step']);
        fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
        return 2;
    }

    // Phase 4 — Fix B (defense-in-depth, OQ-1-independent): the OpenCode `tool:run *` allow
    // rule also matches `--apply`, so an agent could reach a mutating tool with no prompt.
    // The gateway therefore refuses to actually EXECUTE an approval-required tool via --apply
    // unless a human has explicitly opted in with AI_GATEWAY_ALLOW_APPLY=1 in their own shell.
    // Agent bash contexts do not set this env, so the no-prompt mutation lane is closed here
    // regardless of OpenCode tokenizer behavior. See docs/tickets/.../plan-phase4-apply-gate-hardening.md.
    if ($descriptor['requires_approval'] && $wantsApply && getenv('AI_GATEWAY_ALLOW_APPLY') !== '1') {
        $data = aiToolGatewayReasonPayload(
            'mutating_requires_apply',
            'Stop and do not retry. A human must run this mutating tool: set AI_GATEWAY_ALLOW_APPLY=1 in an interactive shell after explicit approval, or run the underlying ask-gated script directly.',
            ['tool' => $toolId, 'requires_approval' => true]
        );
        $written = aiCliWriteArtifact($root, 'tool-run', 'php tools/ai/ai.php tool:run ' . $toolId, $data, 'blocked', null, $data['safe_next_step']);
        fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
        return 2;
    }

    $run = aiRunScriptById($root, $toolId, $args, null);
    $status = ($run['exit'] ?? 1) === 0 ? 'ok' : 'failed';
    $written = aiCliWriteArtifact($root, 'tool-run', 'php tools/ai/ai.php tool:run ' . $toolId, $run, $status, null, $status === 'ok' ? 'Tool run completed.' : 'Fix tool errors and retry.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return $status === 'ok' ? 0 : 1;
}

/**
 * P3.1 — registry:export — project the canonical PHP registry into the generated
 * docs/ai/script-registry.json mirror.
 *
 *   registry:export                      print the generated JSON to stdout
 *   registry:export --output PATH        write the generated JSON to PATH
 *   registry:export --check [PATH]       exit non-zero if PATH differs from the
 *                                        freshly generated projection (CI drift gate);
 *                                        PATH defaults to docs/ai/script-registry.json
 *
 * The JSON is byte-stable (fixed schema_version, stable key order, trailing
 * newline) so --check is deterministic.
 */
function aiRegistryExportAbsolutePath(string $root, string $relative): string
{
    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relative, '/\\'));
}

function aiRunRegistryExportCommand(string $root, array $args): int
{
    $expected = aiInstallerRenderScriptRegistryJson();
    $defaultRel = 'docs/ai/script-registry.json';

    $check = in_array('--check', $args, true);
    $outputArg = aiParseArg($args, 'output');
    $checkArg = aiParseArg($args, 'check');

    if ($check) {
        // Allow either `--check PATH`, `--check=PATH`, or a bare positional path.
        $relTarget = $checkArg;
        if ($relTarget === null) {
            foreach ($args as $arg) {
                if ($arg !== '' && $arg[0] !== '-') {
                    $relTarget = $arg;
                    break;
                }
            }
        }
        $relTarget ??= $defaultRel;
        $absTarget = aiRegistryExportAbsolutePath($root, $relTarget);

        $actual = is_file($absTarget) ? (string) file_get_contents($absTarget) : null;
        $drift = $actual !== $expected;
        $status = $drift ? 'failed' : 'ok';
        $data = [
            'status' => $status,
            'mode' => 'check',
            'target' => $relTarget,
            'exists' => $actual !== null,
            'drift' => $drift,
        ];
        $next = $drift
            ? 'Run: php tools/ai/ai.php registry:export --output ' . $relTarget
            : 'Generated registry projection is up to date.';
        $written = aiCliWriteArtifact($root, 'registry-export', 'php tools/ai/ai.php registry:export --check ' . $relTarget, $data, $status, null, $next);
        fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
        return $drift ? 1 : 0;
    }

    if ($outputArg !== null && $outputArg !== '') {
        $absTarget = aiRegistryExportAbsolutePath($root, $outputArg);
        file_put_contents($absTarget, $expected);
        $data = ['status' => 'ok', 'mode' => 'write', 'target' => $outputArg, 'bytes' => strlen($expected)];
        $written = aiCliWriteArtifact($root, 'registry-export', 'php tools/ai/ai.php registry:export --output ' . $outputArg, $data, 'ok', null, 'Run registry:export --check in CI to prevent drift.');
        fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
        return 0;
    }

    // Default: print the projection to stdout (safe, read-only).
    fwrite(STDOUT, $expected);
    return 0;
}

function aiRunInstallDocs(string $root, array $args): int
{
    $check = in_array('--check', $args, true);
    $write = in_array('--write', $args, true) || !$check;
    $target = aiParseArg($args, 'target') ?? $root;
    $targetRoot = realpath($target);
    if ($targetRoot === false || !is_dir($targetRoot)) {
        throw new RuntimeException('target directory not found: ' . $target);
    }

    $manifestPath = aiInstallerCanonicalManifestPath($targetRoot);
    $installDocDrift = [];

    if ($check) {
        if (is_file($manifestPath)) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (is_array($manifest)) {
                $generated = $targetRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'generated';
                $jsonPath = $generated . DIRECTORY_SEPARATOR . 'install-instructions.json';
                $mdPath = $generated . DIRECTORY_SEPARATOR . 'install-instructions.md';
                $data = aiInstallerBuildInstalledInstructionsData($targetRoot, $manifest);
                $expectedJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
                $expectedMd = aiInstallerRenderInstalledInstructionsMarkdown($data);
                if (!is_file($jsonPath) || (string) file_get_contents($jsonPath) !== $expectedJson) {
                    $installDocDrift[] = 'docs/ai/generated/install-instructions.json';
                }
                if (!is_file($mdPath) || (string) file_get_contents($mdPath) !== $expectedMd) {
                    $installDocDrift[] = 'docs/ai/generated/install-instructions.md';
                }
            }
        }

        $catalogCheck = aiInstallerCheckCatalogDocs($root);
        $drift = array_values(array_unique(array_merge($installDocDrift, $catalogCheck['drift'] ?? [])));
        $status = $drift === [] ? 'ok' : 'failed';
        $data = [
            'status' => $status,
            'mode' => 'check',
            'target' => $targetRoot,
            'drift' => $drift,
        ];
        $written = aiCliWriteArtifact($root, 'install-docs', 'php tools/ai/ai.php install-docs --check', $data, $status, null, $status === 'ok' ? 'Install docs are up to date.' : 'Run install-docs --write to regenerate install docs.');
        fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
        return $status === 'ok' ? 0 : 1;
    }

    $writtenPaths = [];
    if (is_file($manifestPath)) {
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (is_array($manifest)) {
            $out = aiInstallerWriteInstallDocs($targetRoot, $manifest);
            $writtenPaths[] = aiCliToRelative($root, $out['json']);
            $writtenPaths[] = aiCliToRelative($root, $out['md']);
        }
    }
    $catalog = aiInstallerWriteCatalogDocs($root);
    $writtenPaths[] = aiCliToRelative($root, $catalog['json']);
    $writtenPaths[] = aiCliToRelative($root, $catalog['md']);
    $writtenPaths[] = aiCliToRelative($root, $catalog['package_md']);

    $data = [
        'status' => 'ok',
        'mode' => 'write',
        'target' => $targetRoot,
        'written' => array_values(array_unique($writtenPaths)),
        'manifest_found' => is_file($manifestPath),
    ];
    $written = aiCliWriteArtifact($root, 'install-docs', 'php tools/ai/ai.php install-docs --write', $data, 'ok', null, 'Run install-docs --check in CI to prevent drift.');
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);
    return 0;
}
