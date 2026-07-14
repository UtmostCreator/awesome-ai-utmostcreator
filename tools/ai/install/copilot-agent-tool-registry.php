<?php

declare(strict_types=1);

/**
 * Maps canonical agent IDs (from OpenCode templates) to Copilot-native fine-grained tools.
 *
 * Broad aliases such as `read`, `search`, `edit`, and `execute` leave too much room for drift.
 * The registry keeps the VS Code Copilot surface as narrow as practical while still allowing
 * the intended workflow for each agent.
 */
function aiCopilotAgentToolRegistry(): array
{
    $readOnlyTools = [
        'search/changes',
        'search/codebase',
        'search/fileSearch',
        'search/listDirectory',
        'search/textSearch',
        'search/usages',
        'read/readFile',
        'read/problems',
    ];

    $readOnlyToolsWithQuestions = array_merge($readOnlyTools, ['vscode/askQuestions']);
    $executeToolsWithQuestions = array_merge($readOnlyTools, [
        'execute/runInTerminal',
        'vscode/askQuestions',
    ]);
    $editExecuteTools = array_merge($readOnlyTools, [
        'edit/editFiles',
        'edit/createFile',
        'edit/createDirectory',
        'execute/runInTerminal',
        'execute/testFailure',
        'vscode/askQuestions',
    ]);

    return [
        'agent-definition-reviewer' => $executeToolsWithQuestions,
        'fleet-assessor'    => $readOnlyToolsWithQuestions,
        // orchestrator: read-only supervisor/coordinator that routes tasks to delivery
        // agents via the handoff contract (edit: deny, task: allow). Mirrors
        // fleet-assessor — a read-only coordinator that delegates rather than edits.
        'orchestrator'      => $readOnlyToolsWithQuestions,
        // agent-factory: read-only agent-creation pipeline (edit: deny, task: deny). It routes to
        // agent-definition-reviewer via the native `handoffs:` button (a handoff, not a spawn) and
        // asks clarifying questions — so it keeps read + askQuestions but is granted NO subagent/Agent
        // tool (the Copilot tool registry never carried one). Merges the retired agent-creator family.
        'agent-factory'     => $readOnlyToolsWithQuestions,
        'architect'         => $readOnlyToolsWithQuestions,
        'plan-writer' => $editExecuteTools,
        'reviewer'          => $executeToolsWithQuestions,
        'release-auditor'   => $readOnlyTools,
        'researcher'        => $executeToolsWithQuestions,
        'bootstrapper'      => $editExecuteTools,
        'implementer'       => $editExecuteTools,
        'configuration-maintainer' => $editExecuteTools,
        'ui-builder'        => $editExecuteTools,
    ];
}

/**
 * Returns the Copilot tools array for a given agent ID.
 * Falls back to read+search for unknown agents (safe default).
 *
 * @return string[]
 */
function aiCopilotAgentTools(string $agentId): array
{
    $registry = aiCopilotAgentToolRegistry();
    return $registry[$agentId] ?? [
        'search/changes',
        'search/codebase',
        'search/fileSearch',
        'search/listDirectory',
        'search/textSearch',
        'search/usages',
        'read/readFile',
        'read/problems',
    ];
}
