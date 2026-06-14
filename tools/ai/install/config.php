<?php

declare(strict_types=1);

function aiInstallerParseArgs(array $argv): array
{
    $target = '.';
    $source = '';
    $profile = 'dual';
    $runtime = '';
    $projectName = '';
    $force = false;
    $dryRun = false;
    $installBase = true;
    $allowCoreOverwrite = false;
    $help = false;
    $allFeatures = false;
    $withPacks = [];
    $withoutPacks = [];
    $mergeMode = 'sidecar-only';
    $verifyAfter = false;
    $dependencyMode = 'strict';
    $hookWiringDriver = 'none';
    $backup = false;
    $apply = false;
    $wizard = false;
    $toolchainCheck = false;
    $toolchainInstallPlan = false;
    $toolchainApply = false;
    $toolchainTools = [];
    $runAfterInstall = null;
    $allowPlaceholders = false;
    $nonInteractive = false;
    $outputJson = '';
    $upgradeSuffix = '';
    $allowNonGit = false;
    $adopt = false;

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if ($arg === '--help' || $arg === '-h') {
            $help = true;
            continue;
        }
        if ($arg === '--force') {
            $force = true;
            continue;
        }
        if ($arg === '--dry-run') {
            $dryRun = true;
            continue;
        }
        if ($arg === '--apply') {
            $apply = true;
            continue;
        }
        if ($arg === '--allow-non-git') {
            $allowNonGit = true;
            continue;
        }
        if ($arg === '--adopt') {
            $adopt = true;
            continue;
        }
        if ($arg === '--wizard') {
            $wizard = true;
            continue;
        }
        if ($arg === '--non-interactive') {
            $nonInteractive = true;
            continue;
        }
        if ($arg === '--allow-placeholders') {
            $allowPlaceholders = true;
            continue;
        }
        if (str_starts_with($arg, '--output-json=')) {
            $outputJson = substr($arg, 14);
            continue;
        }
        if ($arg === '--output-json') {
            $outputJson = $argv[++$i] ?? '';
            continue;
        }
        if (str_starts_with($arg, '--upgrade-suffix=')) {
            $upgradeSuffix = substr($arg, 17);
            continue;
        }
        if ($arg === '--upgrade-suffix') {
            $upgradeSuffix = $argv[++$i] ?? '';
            continue;
        }
        if ($arg === '--backup') {
            $backup = true;
            continue;
        }
        if ($arg === '--toolchain-check') {
            $toolchainCheck = true;
            continue;
        }
        if ($arg === '--toolchain-install-plan') {
            $toolchainInstallPlan = true;
            continue;
        }
        if ($arg === '--toolchain-apply') {
            $toolchainApply = true;
            continue;
        }
        if (str_starts_with($arg, '--toolchain-tools=')) {
            $toolchainTools = array_merge($toolchainTools, aiInstallerParseCsvList(substr($arg, 18)));
            continue;
        }
        if ($arg === '--toolchain-tools') {
            $toolchainTools = array_merge($toolchainTools, aiInstallerParseCsvList($argv[++$i] ?? ''));
            continue;
        }
        if (str_starts_with($arg, '--run-after-install=')) {
            $runAfterInstall = substr($arg, 20);
            continue;
        }
        if ($arg === '--run-after-install') {
            $runAfterInstall = $argv[++$i] ?? null;
            continue;
        }
        if ($arg === '--all-features') {
            $allFeatures = true;
            continue;
        }
        if ($arg === '--verify-after') {
            $verifyAfter = true;
            continue;
        }
        if ($arg === '--no-base') {
            $installBase = false;
            continue;
        }
        if (str_starts_with($arg, '--with=')) {
            $withPacks = array_merge($withPacks, aiInstallerParseCsvList(substr($arg, 7)));
            continue;
        }
        if ($arg === '--with') {
            $withPacks = array_merge($withPacks, aiInstallerParseCsvList($argv[++$i] ?? ''));
            continue;
        }
        if (str_starts_with($arg, '--without=')) {
            $withoutPacks = array_merge($withoutPacks, aiInstallerParseCsvList(substr($arg, 10)));
            continue;
        }
        if ($arg === '--without') {
            $withoutPacks = array_merge($withoutPacks, aiInstallerParseCsvList($argv[++$i] ?? ''));
            continue;
        }
        if (str_starts_with($arg, '--mode=')) {
            $mergeMode = substr($arg, 7);
            continue;
        }
        if ($arg === '--mode') {
            $mergeMode = $argv[++$i] ?? 'sidecar-only';
            continue;
        }
        if (str_starts_with($arg, '--dependency-mode=')) {
            $dependencyMode = substr($arg, 18);
            continue;
        }
        if ($arg === '--dependency-mode') {
            $dependencyMode = $argv[++$i] ?? 'strict';
            continue;
        }
        if (str_starts_with($arg, '--hook-driver=')) {
            $hookWiringDriver = substr($arg, 14);
            continue;
        }
        if ($arg === '--hook-driver') {
            $hookWiringDriver = $argv[++$i] ?? 'none';
            continue;
        }
        if ($arg === '--allow-core-overwrite') {
            $allowCoreOverwrite = true;
            continue;
        }
        if (str_starts_with($arg, '--target=')) {
            $target = substr($arg, 9);
            continue;
        }
        if ($arg === '--target') {
            $target = $argv[++$i] ?? '';
            continue;
        }
        if (str_starts_with($arg, '--profile=')) {
            $profile = substr($arg, 10);
            continue;
        }
        if (str_starts_with($arg, '--source=')) {
            $source = substr($arg, 9);
            continue;
        }
        if ($arg === '--source') {
            $source = $argv[++$i] ?? '';
            continue;
        }
        if ($arg === '--profile') {
            $profile = $argv[++$i] ?? '';
            continue;
        }
        if (str_starts_with($arg, '--runtime=')) {
            $runtime = substr($arg, 10);
            continue;
        }
        if ($arg === '--runtime') {
            $runtime = $argv[++$i] ?? '';
            continue;
        }
        if (str_starts_with($arg, '--project-name=')) {
            $projectName = substr($arg, 15);
            continue;
        }
        if ($arg === '--project-name') {
            $projectName = $argv[++$i] ?? '';
            continue;
        }
        throw new InvalidArgumentException("unknown option '{$arg}'");
    }

    $scriptDir = realpath(__DIR__);
    if ($scriptDir === false) {
        throw new RuntimeException('unable to resolve script dir');
    }
    $sourceRoot = realpath($scriptDir . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
    if ($source !== '') {
        $sourceRoot = realpath($source);
    }
    if ($sourceRoot === false) {
        throw new RuntimeException('unable to resolve source root');
    }
    $targetRoot = realpath($target);
    if ($targetRoot === false || !is_dir($targetRoot)) {
        throw new InvalidArgumentException('target directory not found: ' . $target);
    }

    $allowedProfiles = ['minimal', 'copilot', 'opencode', 'dual', 'guarded', 'accelerated', 'full-governance', 'docs-reference', 'custom', 'basic', 'standard', 'creator', 'full', 'agents-only'];
    if (!in_array($profile, $allowedProfiles, true)) {
        throw new InvalidArgumentException('unsupported profile: ' . $profile);
    }

    if ($runtime === '') {
        $runtime = match ($profile) {
            'copilot' => 'github-copilot',
            'opencode' => 'opencode',
            default => 'both',
        };
    }
    $allowedRuntimes = ['github-copilot', 'opencode', 'both'];
    if (!in_array($runtime, $allowedRuntimes, true)) {
        throw new InvalidArgumentException('unsupported runtime: ' . $runtime);
    }

    if ($projectName === '') {
        $projectName = basename($targetRoot);
    }

    $allowedMergeModes = ['sidecar-only', 'safe-merge'];
    if (!in_array($mergeMode, $allowedMergeModes, true)) {
        throw new InvalidArgumentException('unsupported merge mode: ' . $mergeMode);
    }

    $allowedDependencyModes = ['strict', 'warn'];
    if (!in_array($dependencyMode, $allowedDependencyModes, true)) {
        throw new InvalidArgumentException('unsupported dependency mode: ' . $dependencyMode);
    }

    $allowedHookDrivers = ['none', 'husky', 'lefthook', 'native'];
    if (!in_array($hookWiringDriver, $allowedHookDrivers, true)) {
        throw new InvalidArgumentException('unsupported hook driver: ' . $hookWiringDriver);
    }

    return [
        'help' => $help,
        'sourceRoot' => $sourceRoot,
        'targetRoot' => $targetRoot,
        'profile' => $profile,
        'runtime' => $runtime,
        'projectName' => $projectName,
        'force' => $force,
        'dryRun' => $dryRun,
        'apply' => $apply,
        'backup' => $backup,
        'installBase' => $installBase,
        'allowCoreOverwrite' => $allowCoreOverwrite,
        'allFeatures' => $allFeatures,
        'withPacks' => array_values(array_unique($withPacks)),
        'withoutPacks' => array_values(array_unique($withoutPacks)),
        'mergeMode' => $mergeMode,
        'verifyAfter' => $verifyAfter,
        'dependencyMode' => $dependencyMode,
        'hookWiringDriver' => $hookWiringDriver,
        'wizard' => $wizard,
        'toolchainCheck' => $toolchainCheck,
        'toolchainInstallPlan' => $toolchainInstallPlan,
        'toolchainApply' => $toolchainApply,
        'toolchainTools' => array_values(array_unique($toolchainTools)),
        'runAfterInstall' => $runAfterInstall,
        'allowPlaceholders' => $allowPlaceholders,
        'nonInteractive' => $nonInteractive,
        'outputJson' => $outputJson,
        'upgradeSuffix' => $upgradeSuffix,
        'allowNonGit' => $allowNonGit,
        'adopt' => $adopt,
    ];
}

function aiInstallerParseCsvList(string $raw): array
{
    if ($raw === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn(string $v): bool => $v !== ''));
}

function aiInstallerUsage(): void
{
    $text = <<<'TXT'
Usage:
  php tools/ai/install-ai-kit.php [options]

Options:
  --target <dir>      Target repository root (default: .)
  --source <dir>      Source package/repo root (default: this repository root)
  --profile <name>    Install profile: minimal|copilot|opencode|dual|guarded|accelerated|full-governance|docs-reference (default: dual)
                      Editions (aliases): basic|standard|creator|full|agents-only
  --runtime <name>    Runtime override: github-copilot|opencode|both
  --project-name <n>  Override inferred project name
  --force             Overwrite existing files
  --adopt             Adopt pre-existing foreign files at kit-claimed paths (record + overwrite)
  --allow-non-git     Permit install into a directory that is not a git repository root
  --no-base           Skip base-layer install
  --allow-core-overwrite  Permit force-overwrite of core base policy files
  --with <packs>      Add optional packs (comma-separated)
  --without <packs>   Remove packs from selected profile
  --all-features      Enable all available feature packs
  --mode <name>       Merge mode: sidecar-only|safe-merge
  --hook-driver <d>   Hook wiring driver: none|husky|lefthook|native
  --dependency-mode <m> Dependency checks: strict|warn
  --verify-after      Run verify after apply
  --wizard            Interactive wizard mode
  --non-interactive   Disable interactive prompts and rely on flags/defaults
  --allow-placeholders Allow unresolved placeholders in strict profiles
    --upgrade-suffix <s> Write colliding targets to suffixed copies instead of skipping them
  --output-json <file> Write install summary JSON
  --toolchain-check   Check toolchain for selected packs
  --toolchain-install-plan Print install guidance for missing tools
  --toolchain-apply   Apply safe tool installs only
  --toolchain-tools <list> Extra tools to include in toolchain check
  --run-after-install <id> Run registered helper script after successful apply
  --dry-run           Print planned actions only
  --help              Show this help
TXT;
    fwrite(STDOUT, $text . PHP_EOL);
}
