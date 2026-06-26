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
        'architect'         => $readOnlyToolsWithQuestions,
        'architecture-plan-writer' => $editExecuteTools,
        'reviewer'          => $executeToolsWithQuestions,
        'release-auditor'   => $readOnlyTools,
        'workflow-auditor'  => $readOnlyTools,
        'researcher'        => $executeToolsWithQuestions,
        'repository-researcher' => $executeToolsWithQuestions,
        'repository-reviewer' => $executeToolsWithQuestions,
        'bootstrapper'      => $editExecuteTools,
        'implementer'       => $editExecuteTools,
        'post-install'      => $editExecuteTools,
        'config-maintainer' => $editExecuteTools,
        'refactorer'        => $editExecuteTools,
        'bugfix'            => $editExecuteTools,
        'build-config'      => $editExecuteTools,
        'docs'              => $editExecuteTools,
        'ui-builder'        => $editExecuteTools,
        'upgrade'           => $editExecuteTools,
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
