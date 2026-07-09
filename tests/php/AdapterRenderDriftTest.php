<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Byte-parity render gate for `.claude/agents/*.md` and `.github/agents/*.agent.md`
 * (plan-28 Phase 1 AC-01/AC-02 —
 * docs/tickets/claude-agent-fleet-remediation/plan-28-permission-sot-and-render-parity-sync.md).
 *
 * This repo self-hosts both rendered agent trees (does NOT skip-when-absent, unlike renderer
 * tests that guard external-install-only paths): `tools/ai/render-adapters.php --check` must
 * always exit 0 here, proving the shipped `.claude`/`.github` agent bodies are byte-identical to
 * what their canonical templates currently render.
 */
final class AdapterRenderDriftTest extends TestCase
{
    private static string $repoRoot;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;
    }

    /** @return array{stdout:string,stderr:string,exit:int} */
    private function runCheck(): array
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $cmd = escapeshellarg((string) PHP_BINARY) . ' '
            . escapeshellarg(self::$repoRoot . '/tools/ai/render-adapters.php') . ' --check';
        $process = proc_open($cmd, $descriptors, $pipes, self::$repoRoot);
        $this->assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
    }

    public function testRenderAdaptersCheckExitsZero(): void
    {
        $result = $this->runCheck();
        $this->assertSame(
            0,
            $result['exit'],
            "rendered adapter drift detected:\n" . $result['stdout'] . $result['stderr']
        );
        $this->assertStringContainsString('OK:', $result['stdout']);
    }

    public function testMutatingAnInstalledFileIsDetectedAsDrift(): void
    {
        // Negative test (plan-28 AC-02): deliberately mutating one line of a rendered file
        // must make --check exit non-zero and name that file in the diff.
        $target = self::$repoRoot . '/.claude/agents/architect.md';
        $this->assertFileExists($target);
        $original = (string) file_get_contents($target);

        file_put_contents($target, $original . "\n<!-- deliberate drift for AdapterRenderDriftTest -->\n");
        try {
            $result = $this->runCheck();
            $this->assertNotSame(0, $result['exit'], '--check must reject a mutated rendered file');
            $this->assertStringContainsString('.claude/agents/architect.md', $result['stderr']);
        } finally {
            file_put_contents($target, $original);
        }

        // Confirm restoration leaves the gate clean again.
        $this->assertSame(0, $this->runCheck()['exit']);
    }
}
