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
