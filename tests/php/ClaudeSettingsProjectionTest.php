<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/tools/ai/install/permission-layers/render-adapters.php';
require_once dirname(__DIR__, 2) . '/tools/ai/install/canonical-agent-frontmatter.php';
require_once dirname(__DIR__, 2) . '/tools/ai/install/copilot-agent-renderer.php'; // aiAgentIsHiddenInternalOnly
require_once dirname(__DIR__, 2) . '/tools/ai/install/core.php'; // aiInstallerMergeClaudeSettingsJson

/**
 * plan-28 Phase 2 AC-03/AC-04 —
 * docs/tickets/claude-agent-fleet-remediation/plan-28-permission-sot-and-render-parity-sync.md.
 *
 * Covers: `aiPermissionClaudeSettingsFromModels()` (the pure adapter), the
 * `tools/ai/generate-claude-settings.php --check` CLI, the subset-by-construction invariant
 * (every shipped agent's resolved allowedBash / the composed hard-deny floor are subsets of
 * the generated `permissions.allow`/`permissions.deny`), and that a synthetic third-party
 * allow entry survives the unchanged install-time union-merge (AC-04).
 */
final class ClaudeSettingsProjectionTest extends TestCase
{
    private static string $repoRoot;
    private static string $settingsPath;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;
        self::$settingsPath = $root . '/packages/ai-universal-rules/templates/claude/settings.json';
    }

    /** @return array{stdout:string,stderr:string,exit:int} */
    private function runGenerator(string $flag): array
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $cmd = escapeshellarg((string) PHP_BINARY) . ' '
            . escapeshellarg(self::$repoRoot . '/tools/ai/generate-claude-settings.php') . ' ' . $flag;
        $process = proc_open($cmd, $descriptors, $pipes, self::$repoRoot);
        $this->assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
    }

    public function testGeneratorCheckExitsZero(): void
    {
        $result = $this->runGenerator('--check');
        $this->assertSame(
            0,
            $result['exit'],
            "settings projection drift detected:\n" . $result['stdout'] . $result['stderr']
        );
        $this->assertStringContainsString('OK:', $result['stdout']);
    }

    /**
     * The same 24-shipped-agent enumeration Phase 1's tools/ai/render-adapters.php uses,
     * resolved through aiPermissionResolveAllowedBash() — the SAME per-agent resolution the
     * Claude/Copilot renderers themselves call, so this is exactly what each agent's rendered
     * Bash Command Policy body contains.
     *
     * @return array<string,list<string>>
     */
    private function shippedAgentAllowedBash(): array
    {
        $sourceDirs = [
            self::$repoRoot . '/packages/ai-universal-rules/templates/core/agents',
            self::$repoRoot . '/packages/ai-universal-rules/templates/optional/agents',
        ];

        $out = [];
        foreach ($sourceDirs as $srcDir) {
            foreach (glob($srcDir . '/*.md') ?: [] as $srcFile) {
                $agentId = pathinfo($srcFile, PATHINFO_FILENAME);
                $content = (string) file_get_contents($srcFile);
                if (aiAgentIsHiddenInternalOnly($content)) {
                    continue;
                }
                $parsed = aiInstallerParseCanonicalAgentFrontmatter($content);
                $out[$agentId] = aiPermissionResolveAllowedBash($agentId, $parsed['allowedBash']);
            }
        }

        return $out;
    }

    public function testEveryShippedAgentAllowedBashIsSubsetOfGeneratedAllow(): void
    {
        $decoded = json_decode((string) file_get_contents(self::$settingsPath), true);
        $this->assertIsArray($decoded, 'settings template must be valid JSON');
        $allow = $decoded['permissions']['allow'] ?? [];
        $this->assertNotEmpty($allow);

        $agents = $this->shippedAgentAllowedBash();
        // 26 templates - 2 hidden (bootstrapper, ui-builder) = 24 shipped.
        $this->assertCount(24, $agents, 'expected exactly 24 shipped Claude agents');

        $missing = [];
        foreach ($agents as $agentId => $patterns) {
            foreach ($patterns as $pattern) {
                if (!in_array('Bash(' . $pattern . ')', $allow, true)) {
                    $missing[] = "{$agentId}: {$pattern}";
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            "agent allowedBash pattern(s) missing from generated permissions.allow:\n" . implode("\n", $missing)
        );
    }

    public function testComposedHardDenyFloorIsSubsetOfGeneratedDeny(): void
    {
        $decoded = json_decode((string) file_get_contents(self::$settingsPath), true);
        $deny = $decoded['permissions']['deny'] ?? [];
        $this->assertNotEmpty($deny);

        $missing = [];
        foreach (aiPermissionLayersCore()['hard-deny'] as $entry) {
            if ($entry['permission'] !== 'bash' || $entry['effect'] !== 'deny' || $entry['pattern'] === '*') {
                continue;
            }
            if (!in_array('Bash(' . $entry['pattern'] . ')', $deny, true)) {
                $missing[] = $entry['pattern'];
            }
        }

        $this->assertSame(
            [],
            $missing,
            "immutable hard-deny pattern(s) missing from generated permissions.deny:\n" . implode("\n", $missing)
        );
    }

    /**
     * Regression: this repo develops the AI kit whose source lives under `packages/`, and the
     * editor agents (implementer/refactorer/bootstrapper, editSurface:'code') must be able to
     * edit it on every provider. A `packages/**` Edit/Write deny in the Claude settings floor
     * silently overrides OpenCode's per-agent `packages/**: allow` grant (Claude has no
     * per-agent path-scoped edit), so it must NOT be present in the generated template. See the
     * generate-claude-settings.php header note and the auto-mode denial this fixed.
     */
    public function testPackagesEditWriteIsNotDeniedInTemplate(): void
    {
        $decoded = json_decode((string) file_get_contents(self::$settingsPath), true);
        $this->assertIsArray($decoded, 'settings template must be valid JSON');
        $deny = $decoded['permissions']['deny'] ?? [];

        $this->assertNotContains(
            'Edit(packages/**)',
            $deny,
            'Edit(packages/**) must not be denied — kit editor agents develop packages/ source'
        );
        $this->assertNotContains(
            'Write(packages/**)',
            $deny,
            'Write(packages/**) must not be denied — kit editor agents develop packages/ source'
        );
    }

    public function testSyntheticThirdPartyAllowEntrySurvivesUnionMerge(): void
    {
        $incoming = (string) file_get_contents(self::$settingsPath);

        $existingDecoded = json_decode($incoming, true);
        $this->assertIsArray($existingDecoded);
        // Simulate a pre-existing installed .claude/settings.json carrying one synthetic
        // third-party (e.g. graphify-style) allow entry a separate tool added after install.
        $existingDecoded['permissions']['allow'][] = 'Bash(synthetic-third-party-tool *)';
        $existingWithThirdParty = (string) json_encode($existingDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $merged = aiInstallerMergeClaudeSettingsJson($incoming, $existingWithThirdParty);
        $mergedDecoded = json_decode($merged, true);

        $this->assertContains(
            'Bash(synthetic-third-party-tool *)',
            $mergedDecoded['permissions']['allow'],
            'a synthetic third-party allow entry must survive the install-time union-merge unchanged'
        );
    }

    public function testClaudeSettingsFromModelsUnionsWrapsSortsAndDedupes(): void
    {
        $perAgentModels = [
            'agent-a' => [
                aiPermissionModelKey('bash', 'foo *') => ['permission' => 'bash', 'pattern' => 'foo *', 'effect' => 'allow', 'class' => 'layer', 'layer' => 'x'],
                aiPermissionModelKey('bash', '*') => ['permission' => 'bash', 'pattern' => '*', 'effect' => 'deny', 'class' => 'floor', 'layer' => 'core:hard-deny'],
            ],
            'agent-b' => [
                aiPermissionModelKey('bash', 'foo *') => ['permission' => 'bash', 'pattern' => 'foo *', 'effect' => 'allow', 'class' => 'layer', 'layer' => 'x'],
                aiPermissionModelKey('bash', 'bar *') => ['permission' => 'bash', 'pattern' => 'bar *', 'effect' => 'allow', 'class' => 'layer', 'layer' => 'x'],
            ],
        ];

        $result = aiPermissionClaudeSettingsFromModels($perAgentModels);

        // duplicate 'foo *' allow entry (shared by both agents) must appear exactly once.
        $this->assertSame(['Bash(bar *)', 'Bash(foo *)'], $result['allow']);
        // the immutable core:hard-deny bash-deny floor (independent of the input models'
        // own content), excluding the universal '*' entry.
        $this->assertNotContains('Bash(*)', $result['deny']);
        $this->assertContains('Bash(rm -rf *)', $result['deny']);
        $this->assertContains('Bash(sudo *)', $result['deny']);
        // sorted, deterministic output.
        $sorted = $result['deny'];
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $result['deny']);
    }
}
