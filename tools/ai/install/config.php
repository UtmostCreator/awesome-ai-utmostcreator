<?php

declare(strict_types=1);

const AI_INSTALLER_ALLOWED_PROFILES = [
    'minimal',
    'copilot',
    'opencode',
    'claude',
    'dual',
    'guarded',
    'accelerated',
    'full-governance',
    'docs-reference',
    'custom',
    'basic',
    'standard',
    'creator',
    'full',
    'agents-only',
];

const AI_INSTALLER_ALLOWED_RUNTIMES = [
    'github-copilot',
    'opencode',
    'claude-code',
    'both',
];

const AI_INSTALLER_ALLOWED_MERGE_MODES = [
    'sidecar-only',
    'safe-merge',
];

const AI_INSTALLER_ALLOWED_DEPENDENCY_MODES = [
    'strict',
    'warn',
];

const AI_INSTALLER_ALLOWED_HOOK_DRIVERS = [
    'none',
    'husky',
    'lefthook',
    'native',
];

/**
 * Option schema.
 *
 * Types:
 * - flag: set a fixed value
 * - value: replace the current scalar value
 * - list: parse CSV and append items
 *
 * @var array<string, array{key: string, type: string, value?: bool}>
 */
const AI_INSTALLER_OPTION_SCHEMA = [
    '--help' => ['key' => 'help', 'type' => 'flag', 'value' => true],
    '-h' => ['key' => 'help', 'type' => 'flag', 'value' => true],
    '--force' => ['key' => 'force', 'type' => 'flag', 'value' => true],
    '--dry-run' => ['key' => 'dryRun', 'type' => 'flag', 'value' => true],
    '--apply' => ['key' => 'apply', 'type' => 'flag', 'value' => true],
    '--allow-non-git' => ['key' => 'allowNonGit', 'type' => 'flag', 'value' => true],
    '--adopt' => ['key' => 'adopt', 'type' => 'flag', 'value' => true],
    '--wizard' => ['key' => 'wizard', 'type' => 'flag', 'value' => true],
    '--non-interactive' => ['key' => 'nonInteractive', 'type' => 'flag', 'value' => true],
    '--allow-placeholders' => ['key' => 'allowPlaceholders', 'type' => 'flag', 'value' => true],
    '--backup' => ['key' => 'backup', 'type' => 'flag', 'value' => true],
    '--toolchain-check' => ['key' => 'toolchainCheck', 'type' => 'flag', 'value' => true],
    '--toolchain-install-plan' => ['key' => 'toolchainInstallPlan', 'type' => 'flag', 'value' => true],
    '--toolchain-apply' => ['key' => 'toolchainApply', 'type' => 'flag', 'value' => true],
    '--all-features' => ['key' => 'allFeatures', 'type' => 'flag', 'value' => true],
    '--verify-after' => ['key' => 'verifyAfter', 'type' => 'flag', 'value' => true],
    '--no-base' => ['key' => 'installBase', 'type' => 'flag', 'value' => false],
    '--allow-core-overwrite' => ['key' => 'allowCoreOverwrite', 'type' => 'flag', 'value' => true],
    '--no-stack-detect' => ['key' => 'noStackDetect', 'type' => 'flag', 'value' => true],
    '--stack-detect-only' => ['key' => 'stackDetectOnly', 'type' => 'flag', 'value' => true],

    '--target' => ['key' => 'target', 'type' => 'value'],
    '--source' => ['key' => 'source', 'type' => 'value'],
    '--profile' => ['key' => 'profile', 'type' => 'value'],
    '--runtime' => ['key' => 'runtime', 'type' => 'value'],
    '--project-name' => ['key' => 'projectName', 'type' => 'value'],
    '--mode' => ['key' => 'mergeMode', 'type' => 'value'],
    '--dependency-mode' => ['key' => 'dependencyMode', 'type' => 'value'],
    '--hook-driver' => ['key' => 'hookWiringDriver', 'type' => 'value'],
    '--output-json' => ['key' => 'outputJson', 'type' => 'value'],
    '--upgrade-suffix' => ['key' => 'upgradeSuffix', 'type' => 'value'],
    '--run-after-install' => ['key' => 'runAfterInstall', 'type' => 'value'],

    '--with' => ['key' => 'withPacks', 'type' => 'list'],
    '--without' => ['key' => 'withoutPacks', 'type' => 'list'],
    '--toolchain-tools' => ['key' => 'toolchainTools', 'type' => 'list'],
    '--stacks' => ['key' => 'stacks', 'type' => 'list'],
];

function aiInstallerWantsHelp(array $argv): bool
{
    return in_array('--help', $argv, true) || in_array('-h', $argv, true);
}

function aiInstallerParseArgs(array $argv): array
{
    $options = aiInstallerParseRawArgs($argv);

    aiInstallerNormalizeOptions($options);
    aiInstallerValidateOptions($options);

    [$sourceRoot, $targetRoot] = aiInstallerResolveRoots(
        source: $options['source'],
        target: $options['target'],
    );

    if ($options['projectName'] === '') {
        $options['projectName'] = basename($targetRoot);
    }

    unset($options['source'], $options['target']);

    return [
        'help' => $options['help'],
        'sourceRoot' => $sourceRoot,
        'targetRoot' => $targetRoot,
        'profile' => $options['profile'],
        'runtime' => $options['runtime'],
        'projectName' => $options['projectName'],
        'force' => $options['force'],
        'dryRun' => $options['dryRun'],
        'apply' => $options['apply'],
        'backup' => $options['backup'],
        'installBase' => $options['installBase'],
        'allowCoreOverwrite' => $options['allowCoreOverwrite'],
        'allFeatures' => $options['allFeatures'],
        'withPacks' => $options['withPacks'],
        'withoutPacks' => $options['withoutPacks'],
        'mergeMode' => $options['mergeMode'],
        'verifyAfter' => $options['verifyAfter'],
        'dependencyMode' => $options['dependencyMode'],
        'hookWiringDriver' => $options['hookWiringDriver'],
        'wizard' => $options['wizard'],
        'toolchainCheck' => $options['toolchainCheck'],
        'toolchainInstallPlan' => $options['toolchainInstallPlan'],
        'toolchainApply' => $options['toolchainApply'],
        'toolchainTools' => $options['toolchainTools'],
        'runAfterInstall' => $options['runAfterInstall'],
        'allowPlaceholders' => $options['allowPlaceholders'],
        'nonInteractive' => $options['nonInteractive'],
        'outputJson' => $options['outputJson'],
        'upgradeSuffix' => $options['upgradeSuffix'],
        'allowNonGit' => $options['allowNonGit'],
        'adopt' => $options['adopt'],
        'stacks' => $options['stacks'],
        'noStackDetect' => $options['noStackDetect'],
        'stackDetectOnly' => $options['stackDetectOnly'],
    ];
}

function aiInstallerParseRawArgs(array $argv): array
{
    $options = aiInstallerDefaultOptions();
    $arguments = array_values(array_slice($argv, 1));
    $argumentCount = count($arguments);

    for ($index = 0; $index < $argumentCount; $index++) {
        $argument = $arguments[$index];

        [$optionName, $inlineValue, $hasInlineValue] = aiInstallerSplitArgument($argument);

        $definition = AI_INSTALLER_OPTION_SCHEMA[$optionName] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException("unknown option '{$optionName}'");
        }

        $key = $definition['key'];

        switch ($definition['type']) {
            case 'flag':
                if ($hasInlineValue) {
                    throw new InvalidArgumentException(
                        "option '{$optionName}' does not accept a value"
                    );
                }

                $options[$key] = $definition['value'];
                break;

            case 'value':
                $options[$key] = aiInstallerReadOptionValue(
                    arguments: $arguments,
                    index: $index,
                    optionName: $optionName,
                    inlineValue: $inlineValue,
                    hasInlineValue: $hasInlineValue,
                );
                break;

            case 'list':
                $value = aiInstallerReadOptionValue(
                    arguments: $arguments,
                    index: $index,
                    optionName: $optionName,
                    inlineValue: $inlineValue,
                    hasInlineValue: $hasInlineValue,
                );

                $options[$key] = array_merge($options[$key], aiInstallerParseCsvList($value));
                break;

            default:
                throw new LogicException(
                    "unsupported parser type '{$definition['type']}'"
                );
        }
    }

    return $options;
}

function aiInstallerDefaultOptions(): array
{
    return [
        'target' => '.',
        'source' => '',
        'profile' => 'dual',
        'runtime' => '',
        'projectName' => '',

        'force' => false,
        'dryRun' => false,
        'apply' => false,
        'backup' => false,
        'installBase' => true,
        'allowCoreOverwrite' => false,
        'help' => false,
        'allFeatures' => false,
        'verifyAfter' => false,
        'wizard' => false,
        'allowPlaceholders' => false,
        'nonInteractive' => false,
        'allowNonGit' => false,
        'adopt' => false,
        'noStackDetect' => false,
        'stackDetectOnly' => false,

        'mergeMode' => 'sidecar-only',
        'dependencyMode' => 'strict',
        'hookWiringDriver' => 'none',

        'toolchainCheck' => false,
        'toolchainInstallPlan' => false,
        'toolchainApply' => false,

        'withPacks' => [],
        'withoutPacks' => [],
        'toolchainTools' => [],
        'stacks' => [],

        'runAfterInstall' => null,
        'outputJson' => '',
        'upgradeSuffix' => '',
    ];
}

/**
 * @return array{0: string, 1: ?string, 2: bool}
 */
function aiInstallerSplitArgument(string $argument): array
{
    $separatorPosition = strpos($argument, '=');

    if ($separatorPosition === false) {
        return [$argument, null, false];
    }

    return [
        substr($argument, 0, $separatorPosition),
        substr($argument, $separatorPosition + 1),
        true,
    ];
}

function aiInstallerReadOptionValue(
    array $arguments,
    int &$index,
    string $optionName,
    ?string $inlineValue,
    bool $hasInlineValue,
): string {
    if ($hasInlineValue) {
        $value = $inlineValue ?? '';
    } else {
        $nextIndex = $index + 1;
        $value = $arguments[$nextIndex] ?? null;

        // A value is only treated as "missing" when the next token is itself a recognized
        // option (e.g. `--target --force`, a forgotten value). Values that merely start with
        // '-' but aren't a known option (e.g. `--upgrade-suffix -upgrade`) are accepted as-is.
        if ($value === null || array_key_exists($value, AI_INSTALLER_OPTION_SCHEMA)) {
            throw new InvalidArgumentException("option '{$optionName}' requires a value");
        }

        $index = $nextIndex;
    }

    if ($value === '') {
        throw new InvalidArgumentException("option '{$optionName}' requires a non-empty value");
    }

    return $value;
}

function aiInstallerNormalizeOptions(array &$options): void
{
    if ($options['runtime'] === '') {
        $options['runtime'] = match ($options['profile']) {
            'copilot' => 'github-copilot',
            'opencode' => 'opencode',
            'claude' => 'claude-code',
            default => 'both',
        };
    }

    foreach (['withPacks', 'withoutPacks', 'toolchainTools', 'stacks'] as $listKey) {
        $options[$listKey] = array_values(array_unique($options[$listKey]));
    }
}

function aiInstallerValidateOptions(array $options): void
{
    aiInstallerAssertAllowedValue(
        option: 'profile',
        value: $options['profile'],
        allowedValues: AI_INSTALLER_ALLOWED_PROFILES,
    );

    aiInstallerAssertAllowedValue(
        option: 'runtime',
        value: $options['runtime'],
        allowedValues: AI_INSTALLER_ALLOWED_RUNTIMES,
    );

    aiInstallerAssertAllowedValue(
        option: 'merge mode',
        value: $options['mergeMode'],
        allowedValues: AI_INSTALLER_ALLOWED_MERGE_MODES,
    );

    aiInstallerAssertAllowedValue(
        option: 'dependency mode',
        value: $options['dependencyMode'],
        allowedValues: AI_INSTALLER_ALLOWED_DEPENDENCY_MODES,
    );

    aiInstallerAssertAllowedValue(
        option: 'hook driver',
        value: $options['hookWiringDriver'],
        allowedValues: AI_INSTALLER_ALLOWED_HOOK_DRIVERS,
    );
}

function aiInstallerAssertAllowedValue(string $option, string $value, array $allowedValues): void
{
    if (!in_array($value, $allowedValues, true)) {
        throw new InvalidArgumentException(
            sprintf(
                'unsupported %s: %s; expected one of: %s',
                $option,
                $value,
                implode(', ', $allowedValues),
            )
        );
    }
}

/**
 * @return array{0: string, 1: string}
 */
function aiInstallerResolveRoots(string $source, string $target): array
{
    $scriptDirectory = realpath(__DIR__);

    if ($scriptDirectory === false) {
        throw new RuntimeException('unable to resolve script directory');
    }

    $sourceCandidate = $source !== ''
        ? $source
        : $scriptDirectory . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..';

    $sourceRoot = aiInstallerResolveDirectory(
        path: $sourceCandidate,
        errorPrefix: 'unable to resolve source root',
    );

    $targetRoot = aiInstallerResolveDirectory(
        path: $target,
        errorPrefix: 'target directory not found',
    );

    return [$sourceRoot, $targetRoot];
}

function aiInstallerResolveDirectory(string $path, string $errorPrefix): string
{
    $resolvedPath = realpath($path);

    if ($resolvedPath === false || !is_dir($resolvedPath)) {
        throw new InvalidArgumentException($errorPrefix . ': ' . $path);
    }

    return $resolvedPath;
}

function aiInstallerParseCsvList(string $raw): array
{
    if ($raw === '') {
        return [];
    }

    return array_values(
        array_filter(
            array_map('trim', explode(',', $raw)),
            static fn(string $value): bool => $value !== '',
        )
    );
}

function aiInstallerUsage(): void
{
    $text = <<<'TXT'
Usage:
  php tools/ai/install-ai-kit.php [options]

Options:
  --target <dir>      Target repository root (default: .)
  --source <dir>      Source package/repo root (default: this repository root)
  --profile <name>    Install profile: minimal|copilot|opencode|claude|dual|guarded|accelerated|full-governance|docs-reference (default: dual)
                      Editions (aliases): basic|standard|creator|full|agents-only
  --runtime <name>    Runtime override: github-copilot|opencode|claude-code|both
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
  --stacks <list>     Select project stacks (comma-separated ids); overrides auto-detection
  --no-stack-detect   Skip stack auto-detection entirely (empty detected set)
  --stack-detect-only Print detected stacks and exit without installing
  --dry-run           Print planned actions only
  --help              Show this help
TXT;
    fwrite(STDOUT, $text . PHP_EOL);
}
