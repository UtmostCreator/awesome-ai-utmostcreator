<?php

declare(strict_types=1);

/**
 * Byte-parity render gate + dogfood regen entrypoint for `.claude/agents/*.md` and
 * `.github/agents/*.agent.md` (plan-28 Phase 1 — see
 * docs/tickets/claude-agent-fleet-remediation/plan-28-permission-sot-and-render-parity-sync.md).
 *
 * Re-renders the shipped `.claude/agents` and `.github/agents` trees FROM the canonical
 * OpenCode templates (`packages/ai-universal-rules/templates/{core,optional}/agents/*.md`)
 * using the SAME renderer functions the installer uses
 * (`aiInstallerRenderClaudeAgent()` / `aiInstallerRenderCopilotAgent()`), and either
 * byte-compares (`--check`) or writes (`--write`) the result. Hidden internal-only agents
 * (`aiAgentIsHiddenInternalOnly()`) are skipped, matching the installer's own filter
 * (26 templates -> 24 shipped agents).
 *
 * This is the dogfood regen entrypoint plan-28 Class 5 identifies as CONFIRMED ABSENT: the
 * install planner marks `.claude/agents` and `.github/agents` `SKIP_EXISTING_UNMANAGED` on
 * self-install, so there was no deterministic way to regenerate this repo's own rendered
 * agent copies in place. `--write` fills that gap for exactly these two trees; it must
 * never touch union-merged root docs (`AGENTS.md`, `CLAUDE.md`) or any other installed
 * surface — this tool only ever writes `.claude/agents/<id>.md` and
 * `.github/agents/<id>.agent.md`.
 *
 * Basename-collision note (plan-28 "Affected Paths" guard): this file lives at
 * `tools/ai/render-adapters.php` (thin `--check`/`--write` CLI entrypoint). The EXISTING
 * composition-seam library at `tools/ai/install/permission-layers/render-adapters.php`
 * (`aiPermissionAllowedBashFromModel()`, `aiPermissionRenderAdapters()`, etc.) is a
 * different file with the same basename — this CLI reuses it transitively (via the
 * renderer requires below) and never re-implements or shadows it.
 *
 * Placeholder note: the renderers emit the literal token `<SCRIPTS_ROOT>` (see
 * `claude-agent-renderer.php` / `copilot-agent-renderer.php`), and several optional-tier
 * templates carry `<PROJECT_NAME>` directly in their own prose (confirmed via a full-corpus
 * grep of all 26 core+optional templates — these are the only two placeholder tokens agent
 * templates use). The real install pipeline resolves both via `aiInstallerApplyPlaceholders()`'s
 * full map (`tools/ai/install/placeholders.php`). This standalone in-place regen tool is not
 * plugged into the full install-plan placeholder pass (that pass operates on a whole install
 * plan/target-root, not a single in-memory rendered string), so it resolves these two tokens
 * directly: `<SCRIPTS_ROOT>` is fixed at `scripts/ai` for this self-hosting repo; `<PROJECT_NAME>`
 * is read from `.ai/project.yml`'s `projectName:` scalar (the same source-of-truth value the
 * full install pipeline reads), reusing `aiInstallerProjectYamlUnquote()` for quote-stripping.
 * If a future placeholder is added to an agent template, extend `aiRenderAdaptersPlaceholderMap()`
 * below and re-verify with `php tools/ai/verify-install-placeholders.php`.
 *
 * Usage:
 *   php tools/ai/render-adapters.php --check
 *   php tools/ai/render-adapters.php --write
 */

require_once __DIR__ . '/install/claude-agent-renderer.php';
require_once __DIR__ . '/install/copilot-agent-renderer.php';
require_once __DIR__ . '/install/project-yaml.php';

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

$sourceDirs = [
    $root . '/packages/ai-universal-rules/templates/core/agents',
    $root . '/packages/ai-universal-rules/templates/optional/agents',
];
$claudeDest = $root . '/.claude/agents';
$copilotDest = $root . '/.github/agents';
$scriptsRootToken = 'scripts/ai';
$placeholderMap = aiRenderAdaptersPlaceholderMap($root, $scriptsRootToken);

$drift = [];
$rewritten = [];
$errors = [];

foreach ($sourceDirs as $srcDir) {
    if (!is_dir($srcDir)) {
        $errors[] = "missing source directory: {$srcDir}";
        continue;
    }

    foreach (glob($srcDir . '/*.md') ?: [] as $srcFile) {
        $agentId = pathinfo($srcFile, PATHINFO_FILENAME);
        $content = (string) file_get_contents($srcFile);
        if (aiAgentIsHiddenInternalOnly($content)) {
            continue;
        }

        $sourceLabel = aiInstallerClaudeAgentSourceLabel($srcDir . '/');

        $claudeRendered = strtr(
            aiInstallerRenderClaudeAgent($content, $agentId, $scriptsRootToken, $sourceLabel),
            $placeholderMap
        );
        aiRenderAdaptersCompareOrWrite(
            $claudeDest . '/' . $agentId . '.md',
            $claudeRendered,
            $write,
            $drift,
            $rewritten,
            $errors
        );

        $copilotRendered = strtr(
            aiInstallerRenderCopilotAgent($content, $agentId, $scriptsRootToken),
            $placeholderMap
        );
        aiRenderAdaptersCompareOrWrite(
            $copilotDest . '/' . $agentId . '.agent.md',
            $copilotRendered,
            $write,
            $drift,
            $rewritten,
            $errors
        );
    }
}

if ($errors !== []) {
    fwrite(STDERR, "ERROR: adapter render gate failed:\n");
    foreach ($errors as $message) {
        fwrite(STDERR, " - {$message}\n");
    }
    exit(1);
}

if ($write) {
    if ($rewritten === []) {
        echo "OK: .claude/agents and .github/agents already byte-parity with the canonical templates\n";
    } else {
        echo 'OK: rewrote ' . count($rewritten) . " rendered agent file(s)\n";
        foreach ($rewritten as $p) {
            echo '  - ' . aiRenderAdaptersRelPath($root, $p) . "\n";
        }
    }
    exit(0);
}

if ($drift !== []) {
    fwrite(STDERR, "ERROR: rendered adapter drift detected (template != installed bytes). Run: php tools/ai/render-adapters.php --write\n");
    foreach ($drift as $p) {
        fwrite(STDERR, '  - ' . aiRenderAdaptersRelPath($root, $p) . "\n");
    }
    exit(1);
}

echo "OK: .claude/agents and .github/agents are byte-parity with the canonical templates\n";
exit(0);

/**
 * Compares $rendered against the current bytes of $targetFile. Missing installed files are
 * recorded as an error (an agent template with no installed counterpart means the install
 * plan never shipped it — a distinct problem from render drift, surfaced separately so it
 * is not silently skipped). No-op when bytes already match. In `--write` mode, rewrites the
 * file in place; in `--check` mode, records the path as drift.
 *
 * @param list<string> $drift
 * @param list<string> $rewritten
 * @param list<string> $errors
 */
function aiRenderAdaptersCompareOrWrite(
    string $targetFile,
    string $rendered,
    bool $write,
    array &$drift,
    array &$rewritten,
    array &$errors
): void {
    if (!is_file($targetFile)) {
        $errors[] = "missing installed file (template has no shipped counterpart): {$targetFile}";
        return;
    }

    $existing = (string) file_get_contents($targetFile);
    if ($existing === $rendered) {
        return;
    }

    if ($write) {
        if (file_put_contents($targetFile, $rendered) === false) {
            $errors[] = "failed to write {$targetFile}";
            return;
        }
        $rewritten[] = $targetFile;
        return;
    }

    $drift[] = $targetFile;
}

function aiRenderAdaptersRelPath(string $root, string $path): string
{
    return ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
}

/**
 * The two placeholder tokens agent templates actually use (confirmed by grep across all
 * 26 core+optional templates). `<PROJECT_NAME>` is read from `.ai/project.yml`'s
 * `projectName:` scalar — the same source-of-truth value the full install pipeline resolves
 * via `aiInstallerLoadProjectValues()` — rather than hardcoded, so it never drifts if the
 * project is renamed.
 *
 * @return array<string,string>
 */
function aiRenderAdaptersPlaceholderMap(string $root, string $scriptsRootToken): array
{
    $map = ['<SCRIPTS_ROOT>' => $scriptsRootToken];

    $projectYamlPath = $root . '/.ai/project.yml';
    if (is_file($projectYamlPath)) {
        $yaml = (string) file_get_contents($projectYamlPath);
        if (preg_match('/^projectName:\s*(.+)$/m', $yaml, $m) === 1) {
            $map['<PROJECT_NAME>'] = aiInstallerProjectYamlUnquote(trim($m[1]));
        }
    }

    return $map;
}
