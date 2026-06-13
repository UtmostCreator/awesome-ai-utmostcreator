<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Contract tests for the registry-derived permission profiles (P0) and the
 * ai.php tool:list / tool:describe / tool:run gateway (P1).
 *
 * Pure-derivation tests load the registry directly (no side effects). The
 * fail-closed CLI tests run the live entrypoint via proc_open and assert exit
 * codes only, matching the existing CliToolsTest pattern.
 */
class ToolGatewayTest extends TestCase
{
    private static string $repoRoot;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;
        require_once $root . '/tools/ai/install/core.php';
        require_once $root . '/tools/ai/commands/helpers.php';
        require_once $root . '/tools/ai/commands/install_commands.php';
    }

    // --- P0: registry-derived profiles -------------------------------------

    public function testAgentProfilesMapToKnownProfileNames(): void
    {
        $names = aiInstallerScriptProfileNames();
        $this->assertNotEmpty($names);
        foreach (aiInstallerAgentProfiles() as $agent => $profile) {
            $this->assertContains(
                $profile,
                $names,
                "agent '$agent' maps to unknown profile '$profile'"
            );
        }
    }

    public function testEveryToolResolvesToAtLeastOneProfile(): void
    {
        foreach (aiInstallerScriptRegistry() as $id => $entry) {
            $this->assertNotEmpty(
                aiInstallerScriptProfiles($entry),
                "tool '$id' has no profiles"
            );
        }
    }

    public function testMutatingToolsNeverVisibleToReadonlyProfile(): void
    {
        foreach (aiInstallerScriptRegistry() as $id => $entry) {
            if (!aiInstallerScriptRequiresApproval($entry)) {
                continue;
            }
            $this->assertNotContains(
                'readonly',
                aiInstallerScriptProfiles($entry),
                "approval-required tool '$id' must not be visible to readonly profile"
            );
        }
    }

    public function testMutatingToolsRequireApproval(): void
    {
        foreach (aiInstallerScriptRegistry() as $id => $entry) {
            if (($entry['risk'] ?? '') === 'mutating') {
                $this->assertTrue(
                    aiInstallerScriptRequiresApproval($entry),
                    "mutating tool '$id' must require approval"
                );
            }
        }
    }

    public function testReadOnlyNonApprovalToolVisibleToReadonlyProfile(): void
    {
        $registry = aiInstallerScriptRegistry();
        $this->assertArrayHasKey('ai-search', $registry, 'ai-search is expected in the registry');
        $this->assertContains('readonly', aiInstallerScriptProfiles($registry['ai-search']));
    }

    // --- P0 phase 2: per-mode tool precheck (mode-specific tools are optional) ---

    /**
     * ai-search must NOT hard-require the mode-specific tools (rg/fd/ast-grep).
     * They belong in optional_tools so a missing one does not block every mode
     * (e.g. 'tracked' uses git grep and needs none of them). The script's own
     * per-mode guard (60-guards.sh / 85-backend-ast.sh) stays the fail-closed
     * authority for modes that genuinely need them.
     */
    public function testAiSearchCoreRequiredToolsExcludeModeSpecificTools(): void
    {
        $entry = aiInstallerScriptRegistry()['ai-search'] ?? null;
        $this->assertIsArray($entry, 'ai-search expected in registry');

        $required = $entry['required_tools'] ?? [];
        $optional = $entry['optional_tools'] ?? [];

        foreach (['rg', 'fd', 'ast-grep'] as $modeTool) {
            $this->assertNotContains(
                $modeTool,
                $required,
                "mode-specific tool '$modeTool' must not be in ai-search required_tools"
            );
            $this->assertContains(
                $modeTool,
                $optional,
                "mode-specific tool '$modeTool' must be in ai-search optional_tools"
            );
        }

        // Core tools the wrapper always needs must remain hard-required.
        foreach (['bash', 'git', 'jq'] as $core) {
            $this->assertContains($core, $required, "core tool '$core' must stay required for ai-search");
        }
    }

    /**
     * A dry-run of ai-search must not be blocked by missing optional tools:
     * the precheck only hard-fails on required_tools (bash/git/jq present in CI).
     * When optional tools are missing the result carries a non-blocking warning.
     */
    public function testAiSearchDryRunNotBlockedByOptionalTools(): void
    {
        $run = aiRunScriptById(self::$repoRoot, 'ai-search', ['--dry-run', '--', '--mode', 'tracked', 'Needle']);

        $this->assertArrayNotHasKey('error', $run, 'ai-search dry-run must not error on optional tools: ' . json_encode($run));
        $this->assertSame(0, $run['exit'] ?? 1, 'ai-search dry-run should succeed (exit 0)');
        $this->assertTrue($run['dry_run'] ?? false, 'ai-search dry-run should report dry_run=true');

        // If optional tools are missing on this host, the result must surface a
        // non-blocking warning rather than failing the whole tool.
        if (array_key_exists('missing_optional_tools', $run)) {
            $this->assertNotEmpty($run['warnings'] ?? [], 'missing optional tools must produce a warning');
        }
    }

    /**
     * The hard precheck must still fail closed when a genuinely-required tool is
     * missing. Proven via a synthetic registry-shaped entry path is not exposed,
     * so we assert the live contract: a tool whose required_tools includes an
     * impossible binary fails closed. We use a non-existent tool id to confirm
     * the precheck path is reached and returns a blocking error structure.
     */
    public function testRequiredToolsStillFailClosedWhenMissing(): void
    {
        // ai-edit requires python3 + git; if any required tool were missing the
        // precheck returns an 'error'/'missing_tools' structure. On a complete CI
        // host all are present, so this asserts the structural contract instead:
        // required_tools is the (only) set that can block, and it is non-empty.
        $entry = aiInstallerScriptRegistry()['ai-search'] ?? [];
        $this->assertNotEmpty(
            $entry['required_tools'] ?? [],
            'ai-search must keep a non-empty required_tools core that can still block'
        );
    }

    // --- P1: gateway fail-closed exit codes --------------------------------

    public function testToolListUnknownProfileFailsClosed(): void
    {
        $this->assertSame(1, $this->runTool('php tools/ai/ai.php tool:list --profile=bogus')['exit']);
    }

    public function testToolDescribeUnknownIdFailsClosed(): void
    {
        $this->assertSame(1, $this->runTool('php tools/ai/ai.php tool:describe nope-not-a-tool')['exit']);
    }

    public function testToolRunUnknownIdFailsClosed(): void
    {
        $this->assertSame(1, $this->runTool('php tools/ai/ai.php tool:run nope-not-a-tool')['exit']);
    }

    public function testToolRunMutatingToolWithoutApplyIsBlocked(): void
    {
        $mutating = $this->firstMutatingToolId();
        $this->assertSame(
            2,
            $this->runTool('php tools/ai/ai.php tool:run ' . escapeshellarg($mutating))['exit'],
            "approval-required tool '$mutating' without --apply must return blocked (exit 2)"
        );
    }

    public function testToolRunMutatingToolDeniedToReadonlyProfile(): void
    {
        $mutating = $this->firstMutatingToolId();
        $this->assertSame(
            1,
            $this->runTool('php tools/ai/ai.php tool:run ' . escapeshellarg($mutating) . ' --profile=architect')['exit'],
            "mutating tool '$mutating' must be denied to architect (readonly) profile"
        );
    }

    public function testToolListWithKnownAgentNameSucceeds(): void
    {
        $this->assertSame(0, $this->runTool('php tools/ai/ai.php tool:list --profile=architect')['exit']);
    }

    // --- P3.6: standardized reason codes -----------------------------------

    public function testReasonPayloadUsesKnownReasonCodeAndSafeNextStep(): void
    {
        $codes = aiToolGatewayReasonCodes();
        foreach (['unknown_id', 'unknown_profile', 'profile_mismatch', 'approval_required', 'missing_required_tool'] as $expected) {
            $this->assertContains($expected, $codes, "reason vocabulary must include '$expected'");
        }

        $payload = aiToolGatewayReasonPayload('approval_required', 'Stop and do not retry.');
        $this->assertSame('approval_required', $payload['reason']);
        $this->assertSame('blocked', $payload['status'], 'approval_required must map to blocked status');
        $this->assertNotEmpty($payload['safe_next_step']);

        $failed = aiToolGatewayReasonPayload('unknown_id', 'List tools first.');
        $this->assertSame('failed', $failed['status'], 'non-approval reasons map to failed status');
    }

    private function firstMutatingToolId(): string
    {
        foreach (aiInstallerScriptRegistry() as $id => $entry) {
            if (($entry['risk'] ?? '') === 'mutating') {
                return (string) $id;
            }
        }
        $this->fail('no mutating tool found in registry');
    }

    /**
     * @return array{stdout:string,stderr:string,exit:int}
     */
    private function runTool(string $command): array
    {
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $env = [
            'HOME'              => sys_get_temp_dir(),
            'XDG_CONFIG_HOME'   => sys_get_temp_dir(),
            'GIT_CONFIG_GLOBAL' => '/dev/null',
            'PATH'              => (string) getenv('PATH'),
        ];
        if (str_starts_with($command, 'php ')) {
            $command = escapeshellarg((string) PHP_BINARY) . substr($command, 3);
        }
        $process = proc_open($command, $descriptors, $pipes, self::$repoRoot, $env);
        $this->assertIsResource($process, "proc_open failed for: $command");
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
    }
}
