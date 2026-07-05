<?php

declare(strict_types=1);

require_once __DIR__ . '/../install/stack-registry.php';
require_once __DIR__ . '/../install/stack-detection.php';

/**
 * Resolve the selected/detected stack set for one install run and run safe
 * version checks for the selected stacks. Pure/non-interactive: callers decide
 * whether to prompt (wizard) or use flags/defaults (CLI, CI, agent mode).
 *
 * @param array{stacks?:list<string>,noStackDetect?:bool} $config
 * @return array{
 *   registry: array<string,array<string,mixed>>,
 *   detected: array<string,array{id:string,confidence:int,signals:list<string>}>,
 *   selected: list<string>,
 *   versions: array<string,array{id:string,tool:string,available:bool,output:string,error:string,required:bool}>
 * }
 */
function aiStackSelectionResolve(string $targetRoot, array $config): array
{
    $registry = aiStackLoadRegistry($targetRoot);
    $noStackDetect = ($config['noStackDetect'] ?? false) === true;
    $explicitStacks = array_values(array_unique(array_map('strval', $config['stacks'] ?? [])));

    $detected = $noStackDetect ? [] : aiStackDetect($targetRoot, $registry);

    // Ground rule (plan AC-5 / non-interactive behavior): explicit --stacks REPLACES
    // auto-detection for this run, it does not merge with it. Detection still runs (so
    // the summary can show what was found) unless --no-stack-detect is also set.
    if ($explicitStacks !== []) {
        aiStackAssertKnownIds($registry, $explicitStacks);
        $selected = $explicitStacks;
        sort($selected);
    } else {
        $selected = array_keys($detected);
        sort($selected);
    }

    $selectedDescriptors = [];
    foreach ($selected as $id) {
        if (isset($registry[$id])) {
            $selectedDescriptors[] = $registry[$id];
        }
    }

    $versions = aiStackRunVersionChecks($targetRoot, $selectedDescriptors);

    return [
        'registry' => $registry,
        'detected' => $detected,
        'selected' => $selected,
        'versions' => $versions,
    ];
}

/**
 * @param array<string,array<string,mixed>> $registry
 * @param list<string> $ids
 */
function aiStackAssertKnownIds(array $registry, array $ids): void
{
    $unknown = array_values(array_diff($ids, array_keys($registry)));
    if ($unknown !== []) {
        throw new InvalidArgumentException('Unknown stack id(s): ' . implode(', ', $unknown));
    }
}

/**
 * Render a short human-readable summary of detected/selected stacks, used by
 * both the wizard prompt and --stack-detect-only output.
 *
 * @param array{detected:array<string,array{id:string,confidence:int,signals:list<string>}>,selected:list<string>} $resolved
 */
function aiStackSelectionSummary(array $resolved): string
{
    $lines = [];
    if ($resolved['detected'] === []) {
        $lines[] = 'Detected stacks: none';
    } else {
        $lines[] = 'Detected stacks:';
        foreach ($resolved['detected'] as $id => $entry) {
            $signals = implode(', ', $entry['signals']);
            $lines[] = sprintf('  - %s (confidence %d): %s', $id, $entry['confidence'], $signals);
        }
    }
    $lines[] = 'Selected stacks: ' . ($resolved['selected'] === [] ? 'none' : implode(', ', $resolved['selected']));

    return implode("\n", $lines);
}
