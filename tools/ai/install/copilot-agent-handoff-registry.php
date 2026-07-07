<?php

declare(strict_types=1);

require_once __DIR__ . '/copilot-agent-tool-registry.php';

/**
 * Canonical source-of-truth chain map for Copilot `handoffs:` frontmatter.
 *
 * Per `docs/ai/integration-matrix.md` ("Handoff Mechanism Per Runtime"), Copilot is the only
 * runtime with native structured handoff metadata; its `handoffs:` array shape is
 * `label`/`agent`/`prompt`/`send`/`model` (verified against VS Code's Custom Agents
 * documentation, fetched 2026-07-04). OpenCode and Claude have no native handoff frontmatter —
 * their renderers must never emit `handoffs:`; the prose "Recommended next step" sentence
 * (`docs/ai/handoff-contract.md`) remains the mandatory baseline on every runtime and this
 * registry is strictly additive to it, never a replacement.
 *
 * This is deliberately data-only (source agent ID => list of chain entries keyed by the
 * literal Copilot frontmatter field names) so it stays reusable if a future slice adds
 * per-runtime prose rendering from the same chains — see the plan's "IMPROVEMENTS TO PLAN"
 * section for that larger, deferred design (shared `agent-handoff-registry.php` +
 * per-runtime renderers + a standalone validator + golden output tests). Only the Copilot
 * renderer consults this file today.
 *
 * @return array<string, list<array{label: string, agent: string, prompt: string, send: bool, model: null}>>
 */
function aiCopilotAgentHandoffRegistry(): array
{
    return [
        'agent-fleet-assessor' => [
            [
                'label'  => 'Assess Agent',
                'agent'  => 'agent-critic',
                'prompt' => 'Assess this one agent file and return your standard output plus the fenced json summary block (score, readiness, decision, blockers, majors, minors, next_handoff, handoff_executable). Copilot has no programmatic fan-out, so this is one agent at a time; the fleet assessor aggregates each returned critic result into the ranking.',
                'send'   => true,
                'model'  => null,
            ],
        ],
        'architect' => [
            [
                'label'  => 'Write Architecture Plan',
                'agent'  => 'architecture-plan-writer',
                'prompt' => 'Convert this approved architecture design into a bounded implementation plan under docs/tickets/: ordered steps, acceptance criteria, affected paths, and a verification plan. Do not widen the design scope.',
                'send'   => true,
                'model'  => null,
            ],
        ],
        'architecture-plan-writer' => [
            [
                'label'  => 'Start Implementation',
                'agent'  => 'implementer',
                'prompt' => 'Implement the persisted plan exactly as scoped: apply the smallest safe change for the listed steps and run the verification the plan specifies.',
                'send'   => true,
                'model'  => null,
            ],
        ],
        'implementer' => [
            [
                'label'  => 'Review Implementation',
                'agent'  => 'reviewer',
                'prompt' => 'Review the current diff against the plan and acceptance criteria for correctness, regressions, policy fit, and missing verification. Do not edit.',
                'send'   => true,
                'model'  => null,
            ],
        ],
        'reviewer' => [
            [
                'label'  => 'Fix Findings',
                'agent'  => 'implementer',
                'prompt' => 'Fix only the accepted review findings, naming the finding and affected path for each fix, then rerun the verification command the reviewer specified.',
                'send'   => true,
                'model'  => null,
            ],
            [
                'label'  => 'Refactor Findings',
                'agent'  => 'refactorer',
                'prompt' => 'Refactor only the accepted structural review findings, preserving behavior, then rerun the verification command the reviewer specified.',
                'send'   => true,
                'model'  => null,
            ],
        ],
    ];
}

/**
 * Returns the registered Copilot handoffs for a source agent ID, or [] when none registered.
 * Validates the whole registry (once per process) before returning, so a misconfigured entry
 * fails loudly at render time instead of silently shipping broken frontmatter.
 *
 * @return list<array{label: string, agent: string, prompt: string, send: bool, model: null}>
 */
function aiCopilotAgentHandoffsFor(string $agentId): array
{
    static $validated = false;
    $registry = aiCopilotAgentHandoffRegistry();

    if (!$validated) {
        aiCopilotAgentHandoffRegistryValidate($registry);
        $validated = true;
    }

    return $registry[$agentId] ?? [];
}

/**
 * Lightweight registry sanity check: source/target agent names must exist among known Copilot
 * agent IDs (the tool registry's keys), label and prompt must be non-empty, and no entry may be
 * a self-handoff. Deliberately minimal — a full standalone validator script and golden
 * rendered-output test fixtures are out of scope for this slice (see the plan's
 * "IMPROVEMENTS TO PLAN" items 3-4, noted there as deferred follow-ups).
 *
 * @throws RuntimeException naming the offending entry when a check fails.
 */
function aiCopilotAgentHandoffRegistryValidate(array $registry): void
{
    $knownAgents = array_keys(aiCopilotAgentToolRegistry());

    foreach ($registry as $sourceAgent => $entries) {
        if (!in_array($sourceAgent, $knownAgents, true)) {
            throw new RuntimeException("copilot-agent-handoff-registry: unknown source agent '{$sourceAgent}'");
        }
        foreach ($entries as $entry) {
            $targetAgent = (string) ($entry['agent'] ?? '');
            if (!in_array($targetAgent, $knownAgents, true)) {
                throw new RuntimeException("copilot-agent-handoff-registry: unknown target agent '{$targetAgent}' for source '{$sourceAgent}'");
            }
            if ($targetAgent === $sourceAgent) {
                throw new RuntimeException("copilot-agent-handoff-registry: self-handoff is not allowed for '{$sourceAgent}'");
            }
            if (trim((string) ($entry['label'] ?? '')) === '') {
                throw new RuntimeException("copilot-agent-handoff-registry: empty label for '{$sourceAgent}' -> '{$targetAgent}'");
            }
            if (trim((string) ($entry['prompt'] ?? '')) === '') {
                throw new RuntimeException("copilot-agent-handoff-registry: empty prompt for '{$sourceAgent}' -> '{$targetAgent}'");
            }
        }
    }
}
