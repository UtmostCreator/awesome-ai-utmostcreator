<?php

declare(strict_types=1);

// selection-engine.php — tiered SelectionEngine for the interactive install wizard.
//
// Procedural (`aiSelection*`) to match the codebase's `aiInstaller*` convention; no
// classes (see plan Contracts And Boundaries: procedural preferred, class not justified).
//
// P0 slice: only the guaranteed StdinSelector path is implemented, wrapping the existing
// aiPromptLine()/aiPromptYesNo() primitives from tools/ai/commands/helpers.php. Backend
// detection is structured so Laravel Prompts (P1), fzf (P2), and gum (P3) can be slotted
// in later without reworking callers — but those backends are intentionally NOT built yet,
// and aiSelectionDetectBackend() therefore always resolves to 'stdin' in this slice.
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
    // Intentionally not yet wired:
    //   if (aiSelectionLaravelPromptsAvailable($root)) { return 'laravel-prompts'; }
    //   if (aiSelectionFzfAvailable()) { return 'fzf'; }
    //   if (aiSelectionGumAvailable()) { return 'gum'; }
    unset($root);

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
        // Future backends slot in here (laravel-prompts/fzf/gum) without touching callers.
        default => aiSelectionStdinMultiselect($title, $options, $defaults),
    };
}

/**
 * Confirm dispatcher.
 */
function aiSelectionConfirm(string $backend, string $prompt, bool $default): bool
{
    return match ($backend) {
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
