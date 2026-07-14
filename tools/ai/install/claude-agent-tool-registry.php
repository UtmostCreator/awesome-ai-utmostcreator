<?php

declare(strict_types=1);

/**
 * Maps canonical agent IDs (from OpenCode templates) to Claude Code-native sub-agent
 * frontmatter: `tools`, `disallowedTools`, and `permissionMode`.
 *
 * Derived from the canonical `permission.edit` / `permission.task` values in each agent
 * template (packages/ai-universal-rules/templates/core/agents/*.md):
 *
 * - `edit: deny`      -> read-only tool set + `permissionMode: plan` (Claude's read-only
 *                        exploration mode is the closest semantic match to a design/review agent).
 * - `edit` not denied -> write-capable tool set + `permissionMode: default`.
 * - `task: allow`     -> also grants the `Agent` tool (subagent spawn capability).
 *
 * Every other agent (including canonical `task: ask`) omits the `Agent` tool as a conservative
 * default: Claude subagents cannot interactively ask for approval the way OpenCode's `ask`
 * permission does (`AskUserQuestion` is a main-session-only tool, unavailable inside a subagent —
 * see docs/ai/integration-matrix.md), so there is no safe way to honor `task: ask` inside a
 * rendered Claude subagent. Omitting `Agent` is the conservative, honestly-documented fallback
 * rather than silently widening spawn capability beyond what the canonical source proves safe.
 */
function aiClaudeAgentToolRegistry(): array
{
    $readOnlyTools = ['Read', 'Grep', 'Glob', 'Bash'];
    $writeTools = ['Read', 'Grep', 'Glob', 'Bash', 'Write', 'Edit'];
    $readOnlyDisallowed = ['Write', 'Edit'];

    return [
        // --- read-only / design / review agents (canonical permission.edit: deny) ---
        'architect'                => ['tools' => array_merge($readOnlyTools, ['Agent']), 'disallowedTools' => $readOnlyDisallowed, 'permissionMode' => 'plan'],
        'researcher'               => ['tools' => $readOnlyTools, 'disallowedTools' => $readOnlyDisallowed, 'permissionMode' => 'plan'],
        'reviewer'                 => ['tools' => $readOnlyTools, 'disallowedTools' => $readOnlyDisallowed, 'permissionMode' => 'plan'],
        'release-auditor'          => ['tools' => $readOnlyTools, 'disallowedTools' => $readOnlyDisallowed, 'permissionMode' => 'plan'],
        // Optional read-only auditor (templates/optional/agents; edit: deny, task: deny).
        // Matches the unknown-id fallback, listed explicitly so the grant is deterministic.
        'agent-definition-reviewer' => ['tools' => $readOnlyTools, 'disallowedTools' => $readOnlyDisallowed, 'permissionMode' => 'plan'],
        // Optional read-only orchestrator (templates/optional/agents; edit: deny, task: allow).
        // Grants the Agent tool so it can spawn agent-definition-reviewer per file and aggregate — its core
        // mission. Like architect, this is a task: allow agent, so the registry's
        // "task: allow -> Agent" rule applies; it is a primary orchestrator, never a nested subagent.
        'fleet-assessor'           => ['tools' => array_merge($readOnlyTools, ['Agent']), 'disallowedTools' => $readOnlyDisallowed, 'permissionMode' => 'plan'],
        // orchestrator: supervisor/coordinator that routes tasks to delivery agents via the
        // handoff contract. task: allow -> Agent (delegation); read-only otherwise (edit: deny).
        'orchestrator'             => ['tools' => array_merge($readOnlyTools, ['Agent']), 'disallowedTools' => $readOnlyDisallowed, 'permissionMode' => 'plan'],
        // agent-factory: read-only agent-creation pipeline (edit: deny, task: deny). It routes to
        // agent-definition-reviewer via the handoff contract + dispatcher (body/command), NOT by
        // spawning a subagent, so it does NOT get the Agent tool — least privilege. Merges the retired
        // agent-creator + agent-creator-supervisor; the static-validator became the
        // `validate-agent-spec.php` tool and the verifier/guardian became skills.
        'agent-factory'            => ['tools' => $readOnlyTools, 'disallowedTools' => $readOnlyDisallowed, 'permissionMode' => 'plan'],

        // --- write-capable agents (canonical permission.edit not denied) ---
        'implementer'              => ['tools' => $writeTools, 'disallowedTools' => [], 'permissionMode' => 'default'],
        'plan-writer' => ['tools' => $writeTools, 'disallowedTools' => [], 'permissionMode' => 'default'],
        'bootstrapper'             => ['tools' => $writeTools, 'disallowedTools' => [], 'permissionMode' => 'default'],
        'configuration-maintainer' => ['tools' => $writeTools, 'disallowedTools' => [], 'permissionMode' => 'default'],
    ];
}

/**
 * Returns the Claude tool config for a given agent ID.
 * Falls back to a read-only, plan-mode default for unknown agents (safe default).
 *
 * @return array{tools: string[], disallowedTools: string[], permissionMode: string}
 */
function aiClaudeAgentToolConfig(string $agentId): array
{
    $registry = aiClaudeAgentToolRegistry();
    return $registry[$agentId] ?? [
        'tools' => ['Read', 'Grep', 'Glob', 'Bash'],
        'disallowedTools' => ['Write', 'Edit'],
        'permissionMode' => 'plan',
    ];
}
