<?php

declare(strict_types=1);

/**
 * Narrow, purpose-built JSON merge for `.claude/settings.json`.
 *
 * This installer has no generic JSON deep-merge primitive (confirmed: only `replace` and
 * `skip-if-exists` merge_strategy values exist anywhere in tools/ai/install/*.php). A generic
 * deep-merge would be a needless, risky new capability; `.claude/settings.json` only ever needs
 * two things merged safely:
 *
 * - `permissions.allow` / `permissions.deny`: array union, de-duplicated, so re-running the
 *   installer is idempotent (does not grow the file on every install) and never drops a
 *   pre-existing user or third-party rule (e.g. graphify's own installer-added entries).
 * - `hooks.<Event>`: array-of-hook-block concatenation per event name, de-duplicated by exact
 *   block content, so a pre-existing hook (e.g. graphify's `PreToolUse` blocks) is concatenated
 *   with, never replaced by, the kit-managed hooks.
 *
 * Any other top-level key in the existing file (unmanaged by this kit) passes through unchanged.
 * Any other top-level key only present in the incoming template is added.
 *
 * @param string $incomingJson The kit-managed template content (permissions/hooks baseline)
 * @param string $existingJson The pre-existing target file content, or '' when none exists
 * @return string              Merged JSON, pretty-printed
 */
function aiInstallerMergeClaudeSettingsJson(string $incomingJson, string $existingJson): string
{
    $incoming = json_decode($incomingJson, true);
    if (!is_array($incoming)) {
        $incoming = [];
    }

    if (trim($existingJson) === '') {
        return json_encode($incoming, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }

    $existing = json_decode($existingJson, true);
    if (!is_array($existing)) {
        // Existing file is not valid JSON we can merge into; do not risk corrupting or
        // silently discarding it. Leave it untouched by returning it verbatim.
        return $existingJson;
    }

    $merged = $existing;

    // --- permissions.allow / permissions.deny: de-duplicated union ---
    foreach (['allow', 'deny'] as $key) {
        $existingList = (array) ($existing['permissions'][$key] ?? []);
        $incomingList = (array) ($incoming['permissions'][$key] ?? []);
        if ($existingList === [] && $incomingList === []) {
            continue;
        }
        $merged['permissions'][$key] = array_values(array_unique(array_merge($existingList, $incomingList)));
    }

    // --- hooks.<Event>: concatenate hook-block arrays, de-duplicated by content ---
    $existingHooks = (array) ($existing['hooks'] ?? []);
    $incomingHooks = (array) ($incoming['hooks'] ?? []);
    $eventNames = array_unique(array_merge(array_keys($existingHooks), array_keys($incomingHooks)));
    foreach ($eventNames as $event) {
        $existingBlocks = (array) ($existingHooks[$event] ?? []);
        $incomingBlocks = (array) ($incomingHooks[$event] ?? []);
        $combined = array_merge($existingBlocks, $incomingBlocks);
        $seen = [];
        $deduped = [];
        foreach ($combined as $block) {
            $canonical = json_encode($block, JSON_UNESCAPED_SLASHES);
            if (isset($seen[$canonical])) {
                continue;
            }
            $seen[$canonical] = true;
            $deduped[] = $block;
        }
        $merged['hooks'][$event] = $deduped;
    }

    // --- any other incoming top-level key not present in existing: add it ---
    foreach ($incoming as $topKey => $topValue) {
        if ($topKey === 'permissions' || $topKey === 'hooks') {
            continue;
        }
        if (!array_key_exists($topKey, $merged)) {
            $merged[$topKey] = $topValue;
        }
    }

    return json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

/**
 * Writes the merged `.claude/settings.json` to $dest, merging with any pre-existing file rather
 * than overwriting it. Creates the parent directory if needed.
 *
 * @param string $src  Absolute path to the source template (packages/.../templates/claude/settings.json)
 * @param string $dest Absolute path to the destination (.claude/settings.json in the target repo)
 */
function aiInstallerMergeClaudeSettingsFile(string $src, string $dest): void
{
    if (!is_file($src)) {
        throw new RuntimeException('missing source file: ' . $src);
    }
    $incoming = (string) file_get_contents($src);
    $existing = is_file($dest) ? (string) file_get_contents($dest) : '';

    $destDir = dirname($dest);
    if (!is_dir($destDir)) {
        aiInstallerMkdir($destDir);
    }

    $merged = aiInstallerMergeClaudeSettingsJson($incoming, $existing);
    if (file_put_contents($dest, $merged) === false) {
        throw new RuntimeException('failed to write merged settings file: ' . $dest);
    }
}
