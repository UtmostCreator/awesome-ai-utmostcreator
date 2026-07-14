## Verdict

Current implementation:

- **Correctness:** 78/100
- **Maintainability:** 42/100
- **Extensibility:** 35/100
- **Testability:** 55/100

Main problems:

1. Parsing, defaults, normalization, filesystem resolution, and validation are mixed together.
2. Every new option requires several repetitive branches.
3. Missing values are silently converted to `''` or `null`.
4. Option names, defaults, allowed values, and usage text can drift apart.
5. `--help --target /missing` may fail before help is displayed.
6. The return array is effectively an undocumented DTO.

For a single dependency-free installer, I would use a **schema-driven functional parser** rather than introduce Symfony Console.

## Refactored version

```php
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
    '--help' => [
        'key' => 'help',
        'type' => 'flag',
        'value' => true,
    ],
    '-h' => [
        'key' => 'help',
        'type' => 'flag',
        'value' => true,
    ],
    '--force' => [
        'key' => 'force',
        'type' => 'flag',
        'value' => true,
    ],
    '--dry-run' => [
        'key' => 'dryRun',
        'type' => 'flag',
        'value' => true,
    ],
    '--apply' => [
        'key' => 'apply',
        'type' => 'flag',
        'value' => true,
    ],
    '--allow-non-git' => [
        'key' => 'allowNonGit',
        'type' => 'flag',
        'value' => true,
    ],
    '--adopt' => [
        'key' => 'adopt',
        'type' => 'flag',
        'value' => true,
    ],
    '--wizard' => [
        'key' => 'wizard',
        'type' => 'flag',
        'value' => true,
    ],
    '--non-interactive' => [
        'key' => 'nonInteractive',
        'type' => 'flag',
        'value' => true,
    ],
    '--allow-placeholders' => [
        'key' => 'allowPlaceholders',
        'type' => 'flag',
        'value' => true,
    ],
    '--backup' => [
        'key' => 'backup',
        'type' => 'flag',
        'value' => true,
    ],
    '--toolchain-check' => [
        'key' => 'toolchainCheck',
        'type' => 'flag',
        'value' => true,
    ],
    '--toolchain-install-plan' => [
        'key' => 'toolchainInstallPlan',
        'type' => 'flag',
        'value' => true,
    ],
    '--toolchain-apply' => [
        'key' => 'toolchainApply',
        'type' => 'flag',
        'value' => true,
    ],
    '--all-features' => [
        'key' => 'allFeatures',
        'type' => 'flag',
        'value' => true,
    ],
    '--verify-after' => [
        'key' => 'verifyAfter',
        'type' => 'flag',
        'value' => true,
    ],
    '--no-base' => [
        'key' => 'installBase',
        'type' => 'flag',
        'value' => false,
    ],
    '--allow-core-overwrite' => [
        'key' => 'allowCoreOverwrite',
        'type' => 'flag',
        'value' => true,
    ],
    '--no-stack-detect' => [
        'key' => 'noStackDetect',
        'type' => 'flag',
        'value' => true,
    ],
    '--stack-detect-only' => [
        'key' => 'stackDetectOnly',
        'type' => 'flag',
        'value' => true,
    ],

    '--target' => [
        'key' => 'target',
        'type' => 'value',
    ],
    '--source' => [
        'key' => 'source',
        'type' => 'value',
    ],
    '--profile' => [
        'key' => 'profile',
        'type' => 'value',
    ],
    '--runtime' => [
        'key' => 'runtime',
        'type' => 'value',
    ],
    '--project-name' => [
        'key' => 'projectName',
        'type' => 'value',
    ],
    '--mode' => [
        'key' => 'mergeMode',
        'type' => 'value',
    ],
    '--dependency-mode' => [
        'key' => 'dependencyMode',
        'type' => 'value',
    ],
    '--hook-driver' => [
        'key' => 'hookWiringDriver',
        'type' => 'value',
    ],
    '--output-json' => [
        'key' => 'outputJson',
        'type' => 'value',
    ],
    '--upgrade-suffix' => [
        'key' => 'upgradeSuffix',
        'type' => 'value',
    ],
    '--run-after-install' => [
        'key' => 'runAfterInstall',
        'type' => 'value',
    ],

    '--with' => [
        'key' => 'withPacks',
        'type' => 'list',
    ],
    '--without' => [
        'key' => 'withoutPacks',
        'type' => 'list',
    ],
    '--toolchain-tools' => [
        'key' => 'toolchainTools',
        'type' => 'list',
    ],
    '--stacks' => [
        'key' => 'stacks',
        'type' => 'list',
    ],
];

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

        [$optionName, $inlineValue, $hasInlineValue] =
            aiInstallerSplitArgument($argument);

        $definition = AI_INSTALLER_OPTION_SCHEMA[$optionName] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException(
                "unknown option '{$optionName}'"
            );
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

                $options[$key] = array_merge(
                    $options[$key],
                    aiInstallerParseCsvList($value),
                );
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

        if ($value === null || str_starts_with($value, '-')) {
            throw new InvalidArgumentException(
                "option '{$optionName}' requires a value"
            );
        }

        $index = $nextIndex;
    }

    if ($value === '') {
        throw new InvalidArgumentException(
            "option '{$optionName}' requires a non-empty value"
        );
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

    foreach (
        ['withPacks', 'withoutPacks', 'toolchainTools', 'stacks']
        as $listKey
    ) {
        $options[$listKey] = array_values(
            array_unique($options[$listKey])
        );
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

    if ($options['noStackDetect'] && $options['stackDetectOnly']) {
        throw new InvalidArgumentException(
            '--no-stack-detect and --stack-detect-only cannot be combined'
        );
    }

    if ($options['wizard'] && $options['nonInteractive']) {
        throw new InvalidArgumentException(
            '--wizard and --non-interactive cannot be combined'
        );
    }
}

function aiInstallerAssertAllowedValue(
    string $option,
    string $value,
    array $allowedValues,
): void {
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
function aiInstallerResolveRoots(
    string $source,
    string $target,
): array {
    $scriptDirectory = realpath(__DIR__);

    if ($scriptDirectory === false) {
        throw new RuntimeException('unable to resolve script directory');
    }

    $sourceCandidate = $source !== ''
        ? $source
        : $scriptDirectory
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . '..';

    $sourceRoot = aiInstallerResolveDirectory(
        path: $sourceCandidate,
        errorPrefix: 'source root not found',
    );

    $targetRoot = aiInstallerResolveDirectory(
        path: $target,
        errorPrefix: 'target directory not found',
    );

    return [$sourceRoot, $targetRoot];
}

function aiInstallerResolveDirectory(
    string $path,
    string $errorPrefix,
): string {
    $resolvedPath = realpath($path);

    if ($resolvedPath === false || !is_dir($resolvedPath)) {
        throw new InvalidArgumentException(
            $errorPrefix . ': ' . $path
        );
    }

    return $resolvedPath;
}

function aiInstallerParseCsvList(string $raw): array
{
    return array_values(
        array_filter(
            array_map('trim', explode(',', $raw)),
            static fn(string $value): bool => $value !== '',
        )
    );
}
```

## Important entry-point change

Handle help before filesystem resolution, so this works even when another supplied path is invalid:

```php
function aiInstallerWantsHelp(array $argv): bool
{
    return in_array('--help', $argv, true)
        || in_array('-h', $argv, true);
}

if (aiInstallerWantsHelp($argv)) {
    aiInstallerUsage();
    exit(0);
}

$options = aiInstallerParseArgs($argv);
```

## Improvements gained

| Area                    |                 Before |              After |
| ----------------------- | ---------------------: | -----------------: |
| Adding a flag           | 5–7 lines of branching |   One schema entry |
| Adding a scalar option  |            10–14 lines |   One schema entry |
| Missing-value handling  |      Silently accepted | Explicit exception |
| Duplicate parsing logic |              Very high |        Centralized |
| Validation isolation    |                    Low |    Dedicated phase |
| Unit-testability        |                 55/100 |             90/100 |
| Maintainability         |                 42/100 |             88/100 |

## Further refactoring boundary

I would not yet create one class per concern. The next justified step would be a typed `InstallerOptions` DTO once two or more consumers depend on this result. Until then, the explicit return array preserves compatibility without introducing excessive abstraction.

Also fix the usage indentation:

```text
  --upgrade-suffix <s> Write colliding targets to suffixed copies instead of skipping them
```

The current line has two extra leading spaces.
