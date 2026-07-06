<?php

declare(strict_types=1);

/**
 * Capture `<!-- BEGIN ai-kit:user -->...<!-- END ai-kit:user -->` blocks from existing managed
 * `.md` targets before they are overwritten. Returns a map of relative path => user block text.
 *
 * @return array<string,string>
 */
function aiInstallerCaptureUserSections(string $targetRoot, array $plan): array
{
    $pattern = '/<!-- BEGIN ai-kit:user -->.*?<!-- END ai-kit:user -->/s';
    $captured = [];
    foreach ($plan as $item) {
        if (($item['type'] ?? '') !== 'file') {
            continue;
        }
        $rel = (string) ($item['target'] ?? '');
        if ($rel === '' || !str_ends_with(strtolower($rel), '.md')) {
            continue;
        }
        $abs = $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($abs)) {
            continue;
        }
        $content = (string) file_get_contents($abs);
        if (preg_match($pattern, $content, $m) === 1) {
            $captured[$rel] = $m[0];
        }
    }

    return $captured;
}

/**
 * Re-inject previously captured user blocks into freshly rendered files. If the rendered file
 * already has a user block (from the shipped template), it is replaced byte-for-byte with the
 * user's preserved block; otherwise the block is appended so user content is never lost.
 *
 * @param array<string,string> $userSections
 */
function aiInstallerRestoreUserSections(string $targetRoot, array $userSections): void
{
    $pattern = '/<!-- BEGIN ai-kit:user -->.*?<!-- END ai-kit:user -->/s';
    foreach ($userSections as $rel => $block) {
        $abs = $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($abs)) {
            continue;
        }
        $content = (string) file_get_contents($abs);
        if (preg_match($pattern, $content) === 1) {
            $updated = preg_replace($pattern, addcslashes($block, '\\$'), $content, 1);
            if (is_string($updated) && $updated !== $content) {
                file_put_contents($abs, $updated);
            }
            continue;
        }
        if ($content !== '' && !str_ends_with($content, "\n")) {
            $content .= "\n";
        }
        file_put_contents($abs, $content . "\n" . $block . "\n");
    }
}

function aiInstallerEnsureAgentsMarkedSectionForSkippedUserFile(string $targetRoot, array $plan): void
{
    $shouldPatch = false;
    foreach ($plan as $item) {
        if (($item['target'] ?? '') === 'AGENTS.md' && ($item['action'] ?? '') === 'SKIP_EXISTING_UNMANAGED') {
            $shouldPatch = true;
            break;
        }
    }
    if (!$shouldPatch) {
        return;
    }

    $path = $targetRoot . DIRECTORY_SEPARATOR . 'AGENTS.md';
    if (!is_file($path)) {
        return;
    }

    $content = (string) file_get_contents($path);
    $begin = AI_MARKER_HTML_BEGIN;
    $end = AI_MARKER_HTML_END;
    $section = implode("\n", [
        $begin,
        'AI kit instructions are installed for this repository. Keep your project-specific guidance outside this managed block.',
        '',
        '- Canonical project context: `docs/ai/project-context.md`',
        '- Workflow defaults: `docs/ai/workflow.md`',
        '- Execution protocol: `docs/ai/execution-protocol.md`',
        $end,
    ]);

    $pattern = '/<!-- BEGIN ai-kit -->.*?<!-- END ai-kit -->/s';
    if (preg_match($pattern, $content) === 1) {
        $updated = preg_replace($pattern, $section, $content);
        if (is_string($updated) && $updated !== $content) {
            file_put_contents($path, $updated);
        }
        return;
    }

    if ($content !== '' && !str_ends_with($content, "\n")) {
        $content .= "\n";
    }
    file_put_contents($path, $content . "\n" . $section . "\n");
}
