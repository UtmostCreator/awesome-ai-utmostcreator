<?php

declare(strict_types=1);

// restore-audit.php: restore audit-log helper used by aiRunRestoreWorkflow()
// (tools/ai/commands/install_workflow.php). Extracted verbatim from install_workflow.php
// (behavior-preserving move; see
// docs/tickets/arch-todo-installer-workflow-command-extraction-20260706-220032/plan.md, Phase 4).
// Depends on the AI_DIR_MODE constant, already defined via install_workflow.php's existing
// require_once chain (core.php).

/**
 * Append an append-only restore audit entry under .ai/logs/restore-<ts>.json.
 *
 * @param array<string,mixed> $data
 */
function aiRestoreAppendAuditLog(string $root, array $data): void
{
    $logsDir = $root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logsDir)) {
        mkdir($logsDir, AI_DIR_MODE, true);
    }
    $stamp = gmdate('Ymd\THis\Z');
    $entry = [
        'op' => 'restore',
        'at' => gmdate('c'),
        'from' => (string) ($data['from'] ?? ''),
        'path' => $data['path'] ?? null,
        'status' => (string) ($data['status'] ?? 'ok'),
        'restored_targets' => array_values($data['restored_targets'] ?? []),
        'deleted_targets' => array_values($data['deleted_targets'] ?? []),
    ];
    $file = $logsDir . DIRECTORY_SEPARATOR . 'restore-' . $stamp . '.json';
    file_put_contents($file, json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
}
