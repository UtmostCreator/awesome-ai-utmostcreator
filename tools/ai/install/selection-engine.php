<?php

declare(strict_types=1);

// selection-engine.php — tiered SelectionEngine for the interactive install wizard.
//
// Procedural (`aiSelection*`) to match the codebase's `aiInstaller*` convention; no
// classes (see plan Contracts And Boundaries: procedural preferred, class not justified).
//
// P0 slice implemented the guaranteed StdinSelector path, wrapping the existing
// aiPromptLine()/aiPromptYesNo() primitives from tools/ai/commands/helpers.php.
// P1 slice adds the optional Laravel Prompts backend: it is used only when
// vendor/autoload.php exists AND the `laravel/prompts` package is installed AND a real
// TTY is available; its absence changes nothing (silent degradation to stdin). fzf (P2)
// and gum (P3) are structured to slot in the same way but are NOT built yet.
//
// helpers.php owns aiPromptLine()/aiPromptYesNo()/aiDetectRuntimeMode(); this file assumes
// they are already loaded by the wizard's include chain (install_workflow.php). It is not
// require_once'd here to avoid load-order coupling in the engine module tree.

/**
 * Backend precedence (highest -> lowest), per the plan:
 *   laravel-prompts -> fzf -> gum -> stdin.
 *
 * `stdin` is the guaranteed fallback and the FORCED choice whenever the runtime mode is
 * CI or AI_AGENT, or STDIN is not an interactive TTY.
 *
 * In this P0 slice only the `stdin` backend exists, so this function always returns
 * 'stdin'. The precedence scaffold below is where future backends (P1/P2/P3) will be
 * consulted; keeping it here means callers never change when those land.
 *
 * @return 'laravel-prompts'|'fzf'|'gum'|'stdin'
 */
function aiSelectionDetectBackend(string $runtimeMode, string $root): string
{
    // Forced-stdin gate: CI, AI agents, and non-TTY sessions must never attempt a
    // richer interactive backend. This preserves the CI/non-TTY behavior contract.
    if ($runtimeMode === 'CI' || $runtimeMode === 'AI_AGENT') {
        return 'stdin';
    }
    if (!aiSelectionStdinIsInteractive()) {
        return 'stdin';
    }

    // Precedence scaffold — richer backends are added in later slices (P1/P2/P3).
    // Each check must degrade silently to the next option and finally to 'stdin'.
    if (aiSelectionLaravelPromptsAvailable($root)) {
        return 'laravel-prompts';
    }
    // Not yet wired (P2/P3):
    //   if (aiSelectionFzfAvailable()) { return 'fzf'; }
    //   if (aiSelectionGumAvailable()) { return 'gum'; }

    return 'stdin';
}

/**
 * True when STDIN is an interactive terminal. Kept separate from
 * aiDetectRuntimeMode() so backend detection can gate on a real TTY without
 * re-deriving CI/agent classification.
 */
function aiSelectionStdinIsInteractive(): bool
{
    return function_exists('stream_isatty') && stream_isatty(STDIN);
}

/**
 * Multiselect dispatcher. Returns the subset of $options[*]['key'] the user selected.
 *
 * @param list<array{key:string,label:string,default:bool}> $options
 * @param list<string> $defaults selected-by-default keys
 * @return list<string>
 */
function aiSelectionMultiselect(string $backend, string $title, array $options, array $defaults): array
{
    return match ($backend) {
        'laravel-prompts' => aiSelectionLaravelPromptsMultiselect($title, $options, $defaults),
        // Future backends slot in here (fzf/gum) without touching callers.
        default => aiSelectionStdinMultiselect($title, $options, $defaults),
    };
}

/**
 * Confirm dispatcher.
 */
function aiSelectionConfirm(string $backend, string $prompt, bool $default): bool
{
    return match ($backend) {
        'laravel-prompts' => aiSelectionLaravelPromptsConfirm($prompt, $default),
        default => aiSelectionStdinConfirm($prompt, $default),
    };
}

/**
 * Single-choice dispatcher. Returns the chosen key, or $default when input is empty.
 *
 * @param list<array{key:string,label:string}> $options
 */
function aiSelectionChoose(string $backend, string $prompt, array $options, string $default): string
{
    return match ($backend) {
        'laravel-prompts' => aiSelectionLaravelPromptsChoose($prompt, $options, $default),
        default => aiSelectionStdinChoose($prompt, $options, $default),
    };
}

/**
 * StdinSelector multiselect: one y/N prompt per option, wrapping aiPromptYesNo().
 * Each option's per-item default follows $defaults (falling back to the option's own
 * `default` flag), so pressing Enter keeps the recommended selection.
 *
 * @param list<array{key:string,label:string,default:bool}> $options
 * @param list<string> $defaults
 * @return list<string>
 */
function aiSelectionStdinMultiselect(string $title, array $options, array $defaults): array
{
    if ($title !== '') {
        fwrite(STDOUT, $title . "\n");
    }
    $selected = [];
    foreach ($options as $option) {
        $key = (string) ($option['key'] ?? '');
        if ($key === '') {
            continue;
        }
        $label = (string) ($option['label'] ?? $key);
        $default = in_array($key, $defaults, true) || (bool) ($option['default'] ?? false);
        // aiPromptYesNo($prompt, $defaultNo): pass false to make "yes" the default.
        if (aiPromptYesNo('Install ' . $label . '?', !$default)) {
            $selected[] = $key;
        }
    }
    return $selected;
}

/**
 * StdinSelector confirm: wraps aiPromptYesNo().
 */
function aiSelectionStdinConfirm(string $prompt, bool $default): bool
{
    return aiPromptYesNo($prompt, !$default);
}

/**
 * StdinSelector single choice: prints numbered options, wraps aiPromptLine().
 * Accepts either the 1-based index or the option key; empty input keeps $default.
 *
 * @param list<array{key:string,label:string}> $options
 */
function aiSelectionStdinChoose(string $prompt, array $options, string $default): string
{
    $keys = [];
    $index = 1;
    foreach ($options as $option) {
        $key = (string) ($option['key'] ?? '');
        if ($key === '') {
            continue;
        }
        $label = (string) ($option['label'] ?? $key);
        fwrite(STDOUT, '[' . $index . '] ' . $label . "\n");
        $keys[(string) $index] = $key;
        $keys[$key] = $key;
        $index++;
    }
    $answer = strtolower(aiPromptLine($prompt));
    if ($answer === '') {
        return $default;
    }
    return $keys[$answer] ?? $default;
}

/**
 * True when the optional `laravel/prompts` backend can be used: `vendor/autoload.php`
 * exists AND the package's `Laravel\Prompts\{multiselect,confirm,select}` functions are
 * (or become, once the autoloader is required) callable.
 *
 * Guarded per the plan's graceful-degradation contract: a missing autoloader or a missing
 * package silently returns false so the caller falls through to the next backend. No
 * fatal error, no warning, for consumers who never installed the optional dependency.
 *
 * The real-TTY gate is enforced once by the caller (aiSelectionDetectBackend(), via
 * aiSelectionStdinIsInteractive()) before this function is ever consulted, so it is not
 * re-checked here.
 */
function aiSelectionLaravelPromptsAvailable(string $root): bool
{
    $autoload = rtrim($root, '/') . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        return false;
    }
    if (!function_exists('Laravel\\Prompts\\multiselect')) {
        require_once $autoload;
    }

    return function_exists('Laravel\\Prompts\\multiselect')
        && function_exists('Laravel\\Prompts\\confirm')
        && function_exists('Laravel\\Prompts\\select');
}

/**
 * Laravel Prompts multiselect: builds a [key => label] options map and passes
 * per-option defaults through, matching aiSelectionStdinMultiselect()'s default
 * resolution (explicit $defaults list OR the option's own `default` flag).
 *
 * @param list<array{key:string,label:string,default:bool}> $options
 * @param list<string> $defaults
 * @return list<string>
 */
function aiSelectionLaravelPromptsMultiselect(string $title, array $options, array $defaults): array
{
    $choices = [];
    $selectedDefaults = [];
    foreach ($options as $option) {
        $key = (string) ($option['key'] ?? '');
        if ($key === '') {
            continue;
        }
        $choices[$key] = (string) ($option['label'] ?? $key);
        if (in_array($key, $defaults, true) || (bool) ($option['default'] ?? false)) {
            $selectedDefaults[] = $key;
        }
    }

    $selected = \Laravel\Prompts\multiselect(
        label: $title !== '' ? $title : 'Select options:',
        options: $choices,
        default: $selectedDefaults
    );

    return array_values(array_map('strval', $selected));
}

/**
 * Laravel Prompts confirm: thin wrapper over Laravel\Prompts\confirm().
 */
function aiSelectionLaravelPromptsConfirm(string $prompt, bool $default): bool
{
    return (bool) \Laravel\Prompts\confirm(label: $prompt, default: $default);
}

/**
 * Laravel Prompts single choice: thin wrapper over Laravel\Prompts\select(). Falls back
 * to $default (or the first option) when $default is not among the offered keys.
 *
 * @param list<array{key:string,label:string}> $options
 */
function aiSelectionLaravelPromptsChoose(string $prompt, array $options, string $default): string
{
    $choices = [];
    foreach ($options as $option) {
        $key = (string) ($option['key'] ?? '');
        if ($key === '') {
            continue;
        }
        $choices[$key] = (string) ($option['label'] ?? $key);
    }
    if ($choices === []) {
        return $default;
    }
    $defaultKey = array_key_exists($default, $choices) ? $default : (string) array_key_first($choices);

    return (string) \Laravel\Prompts\select(label: $prompt, options: $choices, default: $defaultKey);
}

/**
 * The optional packs a human may toggle in the wizard, derived DYNAMICALLY from the live
 * pack registry (never a hardcoded literal list). Core packs that are managed by
 * profile+runtime — base, setup-docs, capabilities-core/extended, and the three runtime
 * adapters — are excluded because the wizard already chooses them via target+profile.
 *
 * Every returned key is a real registry key, so selections always survive
 * aiInstallerResolveSelectedPacks()'s registry filter (packs.php).
 *
 * @param array<string,mixed> $registry result of aiInstallerPackRegistry()
 * @return list<array{key:string,label:string,default:bool}>
 */
function aiSelectionOptionalPackOptions(array $registry): array
{
    $managedByProfileOrRuntime = [
        'base',
        'setup-docs',
        'capabilities-core',
        'capabilities-extended',
        'adapter-copilot',
        'adapter-opencode',
        'adapter-claude',
    ];

    $options = [];
    foreach (array_keys($registry) as $key) {
        $key = (string) $key;
        if (in_array($key, $managedByProfileOrRuntime, true)) {
            continue;
        }
        $options[] = ['key' => $key, 'label' => $key, 'default' => true];
    }
    return $options;
}
