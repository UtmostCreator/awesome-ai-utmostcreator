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
        'repository-researcher'    => ['tools' => $readOnlyTools, 'disallowedTools' => $readOnlyDisallowed, 'permissionMode' => 'plan'],
        'reviewer'                 => ['tools' => $readOnlyTools, 'disallowedTools' => $readOnlyDisallowed, 'permissionMode' => 'plan'],
        'repository-reviewer'      => ['tools' => $readOnlyTools, 'disallowedTools' => $readOnlyDisallowed, 'permissionMode' => 'plan'],
        'release-auditor'          => ['tools' => $readOnlyTools, 'disallowedTools' => $readOnlyDisallowed, 'permissionMode' => 'plan'],
        'workflow-auditor'         => ['tools' => $readOnlyTools, 'disallowedTools' => $readOnlyDisallowed, 'permissionMode' => 'plan'],
        // Optional read-only auditor (templates/optional/agents; edit: deny, task: deny).
        // Matches the unknown-id fallback, listed explicitly so the grant is deterministic.
        'agent-critic'             => ['tools' => $readOnlyTools, 'disallowedTools' => $readOnlyDisallowed, 'permissionMode' => 'plan'],
        // Optional read-only orchestrator (templates/optional/agents; edit: deny, task: allow).
        // Grants the Agent tool so it can spawn agent-critic per file and aggregate — its core
        // mission. Like architect/post-install, this is a task: allow agent, so the registry's
        // "task: allow -> Agent" rule applies; it is a primary orchestrator, never a nested subagent.
        'agent-fleet-assessor'     => ['tools' => array_merge($readOnlyTools, ['Agent']), 'disallowedTools' => $readOnlyDisallowed, 'permissionMode' => 'plan'],

        // --- write-capable agents (canonical permission.edit not denied) ---
        'implementer'              => ['tools' => $writeTools, 'disallowedTools' => [], 'permissionMode' => 'default'],
        'architecture-plan-writer' => ['tools' => $writeTools, 'disallowedTools' => [], 'permissionMode' => 'default'],
        'bootstrapper'             => ['tools' => $writeTools, 'disallowedTools' => [], 'permissionMode' => 'default'],
        'config-maintainer'        => ['tools' => $writeTools, 'disallowedTools' => [], 'permissionMode' => 'default'],
        'refactorer'               => ['tools' => $writeTools, 'disallowedTools' => [], 'permissionMode' => 'default'],
        'post-install'             => ['tools' => array_merge($writeTools, ['Agent']), 'disallowedTools' => [], 'permissionMode' => 'default'],

        // Optional write-capable agents (templates/optional/agents; canonical permission.edit is a
        // scoped allow/deny map, not `deny`). Rendered as writers so their apply/update missions are
        // coherent on Claude. Claude frontmatter cannot express per-path edit scoping, so the
        // canonical edit-deny globs (generated/**, *.lock, *.pem/*.key/*.crt, .env*, secrets) are
        // enforced by the `.claude/settings.json` Edit/Write deny floor (see the Claude settings
        // template) rather than per-agent frontmatter.
        'docs'                     => ['tools' => $writeTools, 'disallowedTools' => [], 'permissionMode' => 'default'],
        'build-config'             => ['tools' => $writeTools, 'disallowedTools' => [], 'permissionMode' => 'default'],
        'bugfix'                   => ['tools' => $writeTools, 'disallowedTools' => [], 'permissionMode' => 'default'],
        'upgrade'                  => ['tools' => $writeTools, 'disallowedTools' => [], 'permissionMode' => 'default'],
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
