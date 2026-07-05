<?php

declare(strict_types=1);

/**
 * Generate the `permission:` frontmatter block for agents managed by the layered
 * permission composition system (tools/ai/install/permission-layers/).
 *
 * Only agents listed in aiPermissionAgentCompositions() are regenerated; every other
 * shipped agent keeps its hand-maintained inline permission block until a later slice
 * migrates it. This mirrors the proven generate-agent-snippets.php --check/--write
 * pattern (byte-identical drift gate) but replaces the whole `permission:` block
 * instead of a delimited sub-section, per the full-rendered-frontmatter decision in
 * docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/plan.md.
 *
 * Usage:
 *   php tools/ai/generate-agent-permissions.php --check
 *   php tools/ai/generate-agent-permissions.php --write
 *
 * The OpenCode rendering function (aiPermissionRenderOpenCodeBlock) lives in
 * install/permission-layers/render-adapters.php (Slice 8 adapter seam) so Copilot and Claude
 * renderers can share the same composed-model projection point instead of each re-parsing
 * rendered frontmatter text.
 */

require_once __DIR__ . '/install/permission-layers/compositions.php';
require_once __DIR__ . '/install/permission-layers/render-adapters.php';

$root = realpath(__DIR__ . '/../..');
if ($root === false) {
    fwrite(STDERR, "ERROR: could not resolve repository root\n");
    exit(1);
}

$check = in_array('--check', $argv, true);
$write = in_array('--write', $argv, true);
if (!$check && !$write) {
    $check = true;
}

$dirs = [
    $root . '/packages/ai-universal-rules/templates/core/agents',
    $root . '/.opencode/agents',
    // Optional-agent pair (docs/tickets/arch-todo-optional-agent-permission-composition-
    // 20260705T221434Z/plan.md): mirrors the core-agent pair above. Additive-only for the
    // 15 core agents (is_file() below skips paths with no matching file); only agents with
    // a compositions.php entry get spliced here, so adding these dirs alone is a no-op
    // until an optional agent is actually composed.
    $root . '/packages/ai-universal-rules/templates/optional/agents',
    $root . '/.opencode/agents-optional',
];

$drift = [];
$rewritten = [];
$errors = [];

foreach (aiPermissionAgentCompositions() as $agent => $composition) {
    $result = aiPermissionComposeFromSpec($composition['compose_spec']);
    $rendered = aiPermissionRenderOpenCodeBlock($result['model'], $composition['render']);

    foreach ($dirs as $dir) {
        $file = $dir . '/' . $agent . '.md';
        if (!is_file($file)) {
            continue;
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            $errors[] = "could not read {$file}";
            continue;
        }

        $spliced = aiPermissionSpliceBlock($raw, $rendered, $file, $errors);
        if ($spliced === null) {
            continue;
        }

        if ($spliced === $raw) {
            continue;
        }

        if ($write) {
            if (file_put_contents($file, $spliced) === false) {
                $errors[] = "failed to write {$file}";
                continue;
            }
            $rewritten[] = aiPermissionRelPath($root, $file);
        } else {
            $drift[] = aiPermissionRelPath($root, $file);
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "ERROR: agent permission generation failed:\n");
    foreach ($errors as $message) {
        fwrite(STDERR, " - {$message}\n");
    }
    exit(1);
}

if ($write) {
    if ($rewritten === []) {
        echo "OK: managed agent permission blocks already in sync\n";
    } else {
        echo 'OK: rewrote permission block in ' . count($rewritten) . " file(s)\n";
        foreach ($rewritten as $p) {
            echo "  - {$p}\n";
        }
    }
    exit(0);
}

if ($drift !== []) {
    fwrite(STDERR, "ERROR: managed agent permission block drift detected. Run: php tools/ai/generate-agent-permissions.php --write\n");
    foreach ($drift as $p) {
        fwrite(STDERR, "  - {$p}\n");
    }
    exit(1);
}

echo "OK: managed agent permission blocks in sync\n";
exit(0);

/**
 * Replace the `permission:` key and its own block with $rendered, preserving any
 * subsequent top-level frontmatter key (e.g. `agent_assessment:`) unchanged. Line-based
 * (not regex-offset-based) so CRLF/edge cases fail loudly instead of silently corrupting
 * the file.
 *
 * The end of the permission block is the first following unindented, non-comment,
 * non-blank line (the next top-level key) if one exists, else the closing `---`
 * delimiter. This lets agents that carry an `agent_assessment:` block after `permission:`
 * (e.g. architect.md) regenerate correctly instead of being rejected as "unsupported
 * layout" (see docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/plan.md,
 * Slice 3/4 ground-truth composition work).
 *
 * Errors (via $errors by-ref, returns null) if: the file does not open with a
 * `---` frontmatter delimiter, no closing `---` line is found, or no top-level
 * `permission:` key exists.
 *
 * @param list<string> $errors
 */
function aiPermissionSpliceBlock(string $raw, string $rendered, string $file, array &$errors): ?string
{
    $hasTrailingNewline = str_ends_with($raw, "\n");
    $lines = explode("\n", $raw);
    if ($hasTrailingNewline) {
        array_pop($lines); // drop the empty trailing element from explode()
    }

    if (($lines[0] ?? null) !== '---') {
        $errors[] = "{$file}: does not start with a '---' frontmatter delimiter";
        return null;
    }

    $closingIndex = null;
    for ($i = 1, $n = count($lines); $i < $n; $i++) {
        if ($lines[$i] === '---') {
            $closingIndex = $i;
            break;
        }
    }
    if ($closingIndex === null) {
        $errors[] = "{$file}: no closing '---' frontmatter delimiter found";
        return null;
    }

    $permissionIndex = null;
    for ($i = 1; $i < $closingIndex; $i++) {
        if (preg_match('/^permission:\s*$/', $lines[$i]) === 1) {
            $permissionIndex = $i;
            break;
        }
    }
    if ($permissionIndex === null) {
        $errors[] = "{$file}: no top-level 'permission:' key found in frontmatter";
        return null;
    }

    // Find the end of the permission block: the first following unindented,
    // non-comment, non-blank line (the next top-level key), else the closing delimiter.
    $permissionEndIndex = $closingIndex;
    for ($i = $permissionIndex + 1; $i < $closingIndex; $i++) {
        $line = $lines[$i];
        if ($line === '' || preg_match('/^\s/', $line) === 1 || preg_match('/^\s*#/', $line) === 1) {
            continue;
        }
        $permissionEndIndex = $i;
        break;
    }

    $newLines = array_merge(
        array_slice($lines, 0, $permissionIndex),
        explode("\n", $rendered),
        array_slice($lines, $permissionEndIndex)
    );

    return implode("\n", $newLines) . ($hasTrailingNewline ? "\n" : '');
}

function aiPermissionRelPath(string $root, string $path): string
{
    return ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
}
