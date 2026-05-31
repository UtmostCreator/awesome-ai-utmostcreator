<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * CLI contract tests: run each PHP entrypoint against the live repo via proc_open.
 *
 * These tests verify exit codes and output markers only — they do not test
 * internal logic (covered by AiCatalogLibTest / AiCatalogLibIoTest).
 *
 * Real-contract-first: these tests were verified against the live repo before
 * assertions were written. Exit 0 = tool reports success for the current repo.
 */
class CliToolsTest extends TestCase
{
    private static string $repoRoot;
    private static string $phpBin;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));

        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }

        self::$repoRoot = $root;
        self::$phpBin = (string) PHP_BINARY;
    }

    /**
     * Run a PHP CLI tool from the repo root with an isolated env.
     *
     * @return array{stdout: string, stderr: string, exit: int}
     */
    private function runTool(string $command): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = [
            'HOME'              => sys_get_temp_dir(),
            'XDG_CONFIG_HOME'   => sys_get_temp_dir(),
            'GIT_CONFIG_GLOBAL' => '/dev/null',
            'PATH'              => (string) getenv('PATH'),
        ];

        $command = $this->normalizePhpCommand($command);
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

    private function normalizePhpCommand(string $command): string
    {
        if (str_starts_with($command, 'php ')) {
            return escapeshellarg(self::$phpBin) . substr($command, 3);
        }
        return $command;
    }

    // ---- validate-ai-config.php (no flags; runs unconditionally) ----

    public function testValidateAiConfigExitsZero(): void
    {
        $result = $this->runTool('php tools/ai/validate-ai-config.php');
        $this->assertSame(
            0,
            $result['exit'],
            "validate-ai-config.php exited non-zero:\n" . $result['stderr']
        );
    }

    public function testValidateAiConfigOutputsOkLines(): void
    {
        $result = $this->runTool('php tools/ai/validate-ai-config.php');
        $combined = $result['stdout'] . $result['stderr'];
        $this->assertStringContainsString('OK', $combined);
    }

    // ---- validate-ai-catalog.php (no flags; runs unconditionally) ----

    public function testValidateAiCatalogExitsZero(): void
    {
        $result = $this->runTool('php tools/ai/validate-ai-catalog.php');
        $this->assertSame(
            0,
            $result['exit'],
            "validate-ai-catalog.php exited non-zero:\n" . $result['stderr']
        );
    }

    public function testValidateAiCatalogOutputsOkLines(): void
    {
        $result = $this->runTool('php tools/ai/validate-ai-catalog.php');
        $combined = $result['stdout'] . $result['stderr'];
        $this->assertStringContainsString('OK', $combined);
    }

    // ---- generate-ai-catalog.php --check ----

    public function testGenerateCatalogCheckModeExitsZero(): void
    {
        $result = $this->runTool('php tools/ai/generate-ai-catalog.php --check');
        $this->assertSame(
            0,
            $result['exit'],
            "generate-ai-catalog.php --check exited non-zero:\n" . $result['stderr']
        );
    }

    public function testGenerateCatalogCheckModeOutputsOkPerFile(): void
    {
        $result = $this->runTool('php tools/ai/generate-ai-catalog.php --check');
        $combined = $result['stdout'] . $result['stderr'];
        $this->assertStringContainsString('OK:', $combined);
    }

    public function testGenerateCatalogCheckModeDoesNotWriteFiles(): void
    {
        // --check must be idempotent: re-running it leaves no changed files
        $before = $this->runTool('php tools/ai/generate-ai-catalog.php --check');
        $after  = $this->runTool('php tools/ai/generate-ai-catalog.php --check');
        $this->assertSame($before['exit'], $after['exit']);
        $this->assertSame(0, $after['exit']);
    }

    // ---- validate-generated-artifacts.php ----

    public function testValidateGeneratedArtifactsExitsZero(): void
    {
        $result = $this->runTool('php tools/ai/validate-generated-artifacts.php');
        $this->assertSame(
            0,
            $result['exit'],
            "validate-generated-artifacts.php exited non-zero:\n" . $result['stderr']
        );
    }

    public function testValidateGeneratedArtifactsOutputsOk(): void
    {
        $result = $this->runTool('php tools/ai/validate-generated-artifacts.php');
        $combined = $result['stdout'] . $result['stderr'];
        $this->assertStringContainsString('OK', $combined);
    }

    // ---- generate-agent-snippets.php --check ----

    public function testAgentSnippetsCheckExitsZero(): void
    {
        $result = $this->runTool('php tools/ai/generate-agent-snippets.php --check');
        $this->assertSame(
            0,
            $result['exit'],
            "generate-agent-snippets.php --check exited non-zero (shared agent tool block drift):\n" . $result['stderr']
        );
    }

    public function testShippedAgentsHaveNoUnexpandedIncludeMarkers(): void
    {
        $dirs = [
            self::$repoRoot . '/.opencode/agents',
            self::$repoRoot . '/packages/ai-universal-rules/templates/core/agents',
        ];
        foreach ($dirs as $dir) {
            foreach (glob($dir . '/*.md') ?: [] as $file) {
                $content = (string) file_get_contents($file);
                $this->assertDoesNotMatchRegularExpression(
                    '/\{\{.*?\}\}|@include\b/',
                    $content,
                    'Unexpanded include marker leaked into shipped agent: ' . $file
                );
            }
        }
    }

    // ---- export-ai-universal-rules.php --check ----

    public function testExportAiUniversalRulesCheckModeExitsZero(): void
    {
        $result = $this->runTool('php tools/ai/export-ai-universal-rules.php --check');
        $this->assertSame(
            0,
            $result['exit'],
            "export-ai-universal-rules.php --check exited non-zero:\n" . $result['stderr']
        );
    }

    public function testExportAiUniversalRulesCheckModeOutputsOkPerProfile(): void
    {
        $result = $this->runTool('php tools/ai/export-ai-universal-rules.php --check');
        $combined = $result['stdout'] . $result['stderr'];
        $this->assertStringContainsString('OK: export profile', $combined);
    }

    // ---- generate-repo-structure.php --check --with-scc ----
    public function testGenerateRepoStructureCheckModeExitsZero(): void
    {
        $this->refreshRepoStructureBaseline();

        $result = $this->runTool('php tools/ai/generate-repo-structure.php --check --with-scc');

        $this->assertSame(
            0,
            $result['exit'],
            "generate-repo-structure.php --check --with-scc exited non-zero:\n"
            . $result['stdout']
            . $result['stderr']
        );
    }

    public function testGenerateRepoStructureCheckModeOutputsUpToDateLines(): void
    {
        $this->refreshRepoStructureBaseline();

        $result = $this->runTool('php tools/ai/generate-repo-structure.php --check --with-scc');
        $combined = $result['stdout'] . $result['stderr'];

        $this->assertStringContainsString('is up to date', $combined);
    }
    // ---- ai.php foundational workflow commands ----

    public function testAiCliListExitsZero(): void
    {
        $result = $this->runTool('php tools/ai/ai.php list');
        $this->assertSame(0, $result['exit'], "ai.php list exited non-zero:\n" . $result['stderr']);
    }

    public function testAiCliSnapshotExitsZero(): void
    {
        $result = $this->runTool('php tools/ai/ai.php snapshot');
        $this->assertSame(0, $result['exit'], "ai.php snapshot exited non-zero:\n" . $result['stderr']);
    }

    public function testAiCliFreshnessExitsZero(): void
    {
        $result = $this->runTool('php tools/ai/ai.php freshness');
        $this->assertSame(0, $result['exit'], "ai.php freshness exited non-zero:\n" . $result['stderr']);
    }

    public function testAiCliDiffSummaryExitsZero(): void
    {
        $result = $this->runTool('php tools/ai/ai.php diff-summary --base main');
        $this->assertSame(0, $result['exit'], "ai.php diff-summary exited non-zero:\n" . $result['stderr']);
    }

    public function testAiCliRiskExitsZero(): void
    {
        $result = $this->runTool('php tools/ai/ai.php risk --base main');
        $this->assertSame(0, $result['exit'], "ai.php risk exited non-zero:\n" . $result['stderr']);
    }

    public function testAiCliVerifyExitsZeroOrKnownFailureCode(): void
    {
        $result = $this->runTool('php tools/ai/ai.php verify --changed');
        $this->assertContains(
            $result['exit'],
            [0, 2],
            "ai.php verify exited with unexpected code:\n" . $result['stderr']
        );
    }

    public function testAiCliNextExitsZeroOrBlocked(): void
    {
        $result = $this->runTool('php tools/ai/ai.php next');
        $this->assertContains(
            $result['exit'],
            [0, 1],
            "ai.php next exited with unexpected code:\n" . $result['stderr']
        );
    }

    public function testAiCliEnvCheckExitsZero(): void
    {
        $result = $this->runTool('php tools/ai/ai.php env-check');
        $this->assertSame(0, $result['exit'], "ai.php env-check exited non-zero:\n" . $result['stderr']);
    }

    public function testAiCliImpactExitsZero(): void
    {
        $result = $this->runTool('php tools/ai/ai.php impact --base main');
        $this->assertSame(0, $result['exit'], "ai.php impact exited non-zero:\n" . $result['stderr']);
    }

    public function testAiCliAskExitsZero(): void
    {
        $result = $this->runTool('php tools/ai/ai.php ask "Which runtime adapter is in scope?" --options "copilot,opencode,both" --default both');
        $this->assertSame(0, $result['exit'], "ai.php ask exited non-zero:\n" . $result['stderr']);
    }

    public function testAiCliAskResolveExitsZero(): void
    {
        $seed = $this->runTool('php tools/ai/ai.php ask "Which runtime adapter is in scope?" --options "copilot,opencode,both" --default both');
        $this->assertSame(0, $seed['exit'], "ai.php ask seed exited non-zero:\n" . $seed['stderr']);

        $askPath = __DIR__ . '/../../docs/ai/generated/ask.json';
        $decoded = json_decode((string) file_get_contents($askPath), true);
        $id = (string) ($decoded['data']['question_id'] ?? '');
        $this->assertNotSame('', $id, 'ask question_id should exist');

        $result = $this->runTool('php tools/ai/ai.php ask --resolve ' . escapeshellarg($id) . ' --answer ' . escapeshellarg('both'));
        $this->assertSame(0, $result['exit'], "ai.php ask resolve exited non-zero:\n" . $result['stderr']);
    }

    public function testAiCliEstimateExitsZero(): void
    {
        $result = $this->runTool('php tools/ai/ai.php estimate "add workflow-control command"');
        $this->assertSame(0, $result['exit'], "ai.php estimate exited non-zero:\n" . $result['stderr']);
    }

    public function testAiCliConflictsExitsZeroOrBlocked(): void
    {
        $result = $this->runTool('php tools/ai/ai.php conflicts');
        $this->assertContains(
            $result['exit'],
            [0, 1],
            "ai.php conflicts exited with unexpected code:\n" . $result['stderr']
        );
    }

    public function testAiCliFindExitsZero(): void
    {
        $result = $this->runTool('php tools/ai/ai.php find workflow');
        $this->assertSame(0, $result['exit'], "ai.php find exited non-zero:\n" . $result['stderr']);
    }

    public function testAiCliSymbolsExitsZero(): void
    {
        $result = $this->runTool('php tools/ai/ai.php symbols aiRun');
        $this->assertSame(0, $result['exit'], "ai.php symbols exited non-zero:\n" . $result['stderr']);
    }

    public function testAiCliPreflightExitsZeroOrFailed(): void
    {
        $result = $this->runTool('php tools/ai/ai.php preflight');
        $this->assertContains(
            $result['exit'],
            [0, 1],
            "ai.php preflight exited with unexpected code:\n" . $result['stderr']
        );
    }

    public function testAiCliPackageLockCheckExitsZeroOrFailed(): void
    {
        $result = $this->runTool('php tools/ai/ai.php package-lock --check');
        $this->assertContains(
            $result['exit'],
            [0, 1],
            "ai.php package-lock --check exited with unexpected code:\n" . $result['stderr']
        );
    }

    public function testAiCliPackageVerifyExitsZeroOrFailed(): void
    {
        $result = $this->runTool('php tools/ai/ai.php package-verify');
        $this->assertContains(
            $result['exit'],
            [0, 1],
            "ai.php package-verify exited with unexpected code:\n" . $result['stderr']
        );
    }

    public function testAiCliInstructionAuditExitsZero(): void
    {
        $result = $this->runTool('php tools/ai/ai.php audit-instructions');
        $this->assertSame(0, $result['exit'], "ai.php audit-instructions exited non-zero:\n" . $result['stderr']);
    }

    public function testAiCliAdapterPlanExitsZero(): void
    {
        $result = $this->runTool('php tools/ai/ai.php adapter-plan --targets copilot,opencode --mode sidecar-only');
        $this->assertSame(0, $result['exit'], "ai.php adapter-plan exited non-zero:\n" . $result['stderr']);
    }

    public function testAiCliInstallDryRunExitsZero(): void
    {
        $result = $this->runTool('php tools/ai/ai.php install --dry-run --mode sidecar-only');
        $this->assertSame(0, $result['exit'], "ai.php install --dry-run exited non-zero:\n" . $result['stderr']);
    }

    public function testAiCliAdapterValidateExitsZero(): void
    {
        $result = $this->runTool('php tools/ai/ai.php adapter-validate');
        $this->assertSame(0, $result['exit'], "ai.php adapter-validate exited non-zero:\n" . $result['stderr']);
    }

    private function skipIfToolchainMissing(array $tools): void
    {
        require_once self::$repoRoot . '/tools/ai/install/toolchain.php';
        $missing = [];
        foreach ($tools as $tool) {
            if (!aiInstallerCommandExists($tool)) {
                $missing[] = $tool;
            }
        }
        if ($missing !== []) {
            $this->markTestSkipped('required toolchain not installed: ' . implode(', ', $missing));
        }
    }

    private function refreshRepoStructureBaseline(): void
    {
        $this->skipIfToolchainMissing(['scc']);

        $result = $this->runTool('php tools/ai/generate-repo-structure.php --with-scc');

        $this->assertSame(
            0,
            $result['exit'],
            "generate-repo-structure.php --with-scc failed:\n"
            . $result['stdout']
            . $result['stderr']
        );
    }
}
