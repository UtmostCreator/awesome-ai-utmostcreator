<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * P0 slice coverage for the tiered SelectionEngine and the registry-sourced wizard
 * selection. Verifies:
 *  (a) selection keys are sourced from the live registry (no hardcoded stale keys),
 *  (b) previously-unreachable optional packs are now selectable,
 *  (c) unknown keys are rejected by aiInstallerResolveSelectedPacks(),
 *  (d) dependency warnings surface for agent-pack-without-scripts-pack,
 *  (e) CI / AI_AGENT / non-TTY forces the stdin backend.
 *
 * @see docs/tickets/arch-todo-installer-tiered-selector-20260704-230000/plan.md
 */
class InstallerSelectionEngineTest extends TestCase
{
    private static string $repoRoot;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;

        require_once $root . '/tools/ai/commands/helpers.php';
        require_once $root . '/tools/ai/install/registry.php';
        require_once $root . '/tools/ai/install/selection-engine.php';
    }

    // (e) CI / AI_AGENT / non-TTY forces the stdin backend.

    public function testDetectBackendForcesStdinInCiMode(): void
    {
        $this->assertSame('stdin', aiSelectionDetectBackend('CI', self::$repoRoot));
    }

    public function testDetectBackendForcesStdinForAiAgent(): void
    {
        $this->assertSame('stdin', aiSelectionDetectBackend('AI_AGENT', self::$repoRoot));
    }

    public function testDetectBackendForcesStdinForNonTtyHuman(): void
    {
        // Under PHPUnit, STDIN is not a TTY, so even HUMAN_TTY resolves to stdin.
        // This locks the non-TTY degradation contract (AC-01/AC-07).
        $this->assertSame('stdin', aiSelectionDetectBackend('HUMAN_TTY', self::$repoRoot));
    }

    public function testDetectBackendOnlyResolvesToStdinInP0Slice(): void
    {
        // Under PHPUnit, STDIN is never a real TTY, so the forced-stdin gate in
        // aiSelectionDetectBackend() short-circuits before the Laravel Prompts
        // precedence check is ever consulted — this holds regardless of whether the
        // optional laravel/prompts package is installed (P1) or not.
        foreach (['CI', 'AI_AGENT', 'HUMAN_TTY', 'anything-else'] as $mode) {
            $this->assertSame('stdin', aiSelectionDetectBackend($mode, self::$repoRoot));
        }
    }

    // (f) P1: Laravel Prompts backend availability + graceful degradation.

    public function testLaravelPromptsAvailableWhenPackageIsInstalled(): void
    {
        // laravel/prompts is a require-dev dependency (never `require`); the repo's own
        // vendor/ has it installed, so availability must resolve true here.
        $this->assertTrue(aiSelectionLaravelPromptsAvailable(self::$repoRoot));
    }

    public function testLaravelPromptsUnavailableWhenAutoloadMissingFallsThroughToStdin(): void
    {
        // A root with no vendor/autoload.php at all (as in a fresh consumer repo that
        // never installed the optional dependency) must degrade silently: no fatal
        // error, no warning, availability simply resolves false (AC-07).
        $bareRoot = sys_get_temp_dir() . '/ai-selection-no-autoload-' . uniqid('', true);
        mkdir($bareRoot, 0777, true);
        try {
            $this->assertFalse(aiSelectionLaravelPromptsAvailable($bareRoot));
            // Detection still degrades to stdin end-to-end (non-TTY under PHPUnit).
            $this->assertSame('stdin', aiSelectionDetectBackend('HUMAN_TTY', $bareRoot));
        } finally {
            rmdir($bareRoot);
        }
    }

    // (a) selection keys are sourced from the live registry (no hardcoded stale keys).

    public function testOptionalPackOptionsAreSourcedFromLiveRegistry(): void
    {
        $registry = aiInstallerPackRegistry();
        $options = aiSelectionOptionalPackOptions($registry);
        $this->assertNotEmpty($options);

        foreach ($options as $option) {
            $this->assertArrayHasKey('key', $option);
            $this->assertArrayHasKey('label', $option);
            $this->assertArrayHasKey('default', $option);
            // Every offered key MUST be a real registry key.
            $this->assertArrayHasKey(
                $option['key'],
                $registry,
                "Selection offered non-registry key: {$option['key']}"
            );
        }
    }

    public function testStaleHardcodedKeysAreNotOffered(): void
    {
        // The old wizard hardcoded these; the last two never existed in the registry.
        $offered = array_map(
            static fn(array $o): string => (string) $o['key'],
            aiSelectionOptionalPackOptions(aiInstallerPackRegistry())
        );
        $this->assertNotContains('optional-agents-pack', $offered);
        $this->assertNotContains('optional-prompts-pack', $offered);
    }

    public function testStaleHardcodedListRemovedFromWizardSource(): void
    {
        // AC-03: no hardcoded pack-key literal list remains in aiRunInstallWizard().
        $source = (string) file_get_contents(
            self::$repoRoot . '/tools/ai/commands/install_workflow.php'
        );
        $this->assertStringNotContainsString('optional-agents-pack', $source);
        $this->assertStringNotContainsString('optional-prompts-pack', $source);
    }

    // (b) previously-unreachable optional packs are now selectable.

    public function testPreviouslyUnreachableOptionalPacksAreSelectable(): void
    {
        $offered = array_map(
            static fn(array $o): string => (string) $o['key'],
            aiSelectionOptionalPackOptions(aiInstallerPackRegistry())
        );
        // These real registry keys were unreachable under the stale hardcoded list.
        $this->assertContains('optional-agents-opencode-pack', $offered);
        $this->assertContains('optional-agents-copilot-pack', $offered);
    }

    public function testCoreAndAdapterPacksAreNotOfferedAsOptional(): void
    {
        $offered = array_map(
            static fn(array $o): string => (string) $o['key'],
            aiSelectionOptionalPackOptions(aiInstallerPackRegistry())
        );
        // Managed by profile+runtime, not user-toggleable in the optional list.
        foreach (['base', 'setup-docs', 'capabilities-core', 'capabilities-extended', 'adapter-copilot', 'adapter-opencode', 'adapter-claude'] as $managed) {
            $this->assertNotContains($managed, $offered);
        }
    }

    // (c) unknown keys are rejected by aiInstallerResolveSelectedPacks().

    public function testUnknownSelectedKeyIsRejected(): void
    {
        $registry = aiInstallerPackRegistry();
        $config = [
            'profile' => 'minimal',
            'runtime' => 'both',
            'allFeatures' => false,
            'installBase' => true,
            'withPacks' => ['optional-agents-pack', 'definitely-not-a-real-pack'],
            'withoutPacks' => [],
        ];
        $resolved = aiInstallerResolveSelectedPacks($config, $registry);
        $this->assertNotContains('optional-agents-pack', $resolved);
        $this->assertNotContains('definitely-not-a-real-pack', $resolved);
        // Every resolved pack is a real registry key.
        foreach ($resolved as $pack) {
            $this->assertArrayHasKey($pack, $registry);
        }
    }

    public function testRealOptionalPackSurvivesResolution(): void
    {
        // AC-04: a real optional pack passed via selection appears in the resolved set.
        $registry = aiInstallerPackRegistry();
        $config = [
            'profile' => 'minimal',
            'runtime' => 'both',
            'allFeatures' => false,
            'installBase' => true,
            'withPacks' => ['optional-agents-opencode-pack'],
            'withoutPacks' => [],
        ];
        $resolved = aiInstallerResolveSelectedPacks($config, $registry);
        $this->assertContains('optional-agents-opencode-pack', $resolved);
    }

    // (d) dependency warnings surface for agent-pack-without-scripts-pack.

    public function testDependencyWarningForAgentPackWithoutScripts(): void
    {
        $warnings = aiInstallerAgentDependencyWarnings(['optional-agents-opencode-pack']);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('scripts-pack', $warnings[0]);
    }

    public function testNoDependencyWarningWhenScriptsPackPresent(): void
    {
        $warnings = aiInstallerAgentDependencyWarnings(['optional-agents-opencode-pack', 'scripts-pack']);
        $this->assertSame([], $warnings);
    }

    // StdinSelector primitives behave correctly on piped (non-TTY) input.
    //
    // aiPromptLine() reads the STDIN constant (fgets(STDIN)), which cannot be
    // reassigned in-process, so these run a tiny PHP harness via proc_open with the
    // input on FD 0 — mirroring InstallerSafetyTest's subprocess convention.

    public function testStdinMultiselectRespectsPipedYesNoInput(): void
    {
        // Two options: answer "y" then "n" -> only the first key selected.
        $php = <<<'PHP'
            require $argv[1] . '/tools/ai/commands/helpers.php';
            require $argv[1] . '/tools/ai/install/selection-engine.php';
            $options = [
                ['key' => 'alpha', 'label' => 'alpha', 'default' => true],
                ['key' => 'beta', 'label' => 'beta', 'default' => true],
            ];
            $selected = aiSelectionMultiselect('stdin', 'Optional packs:', $options, ['alpha', 'beta']);
            fwrite(STDERR, 'RESULT=' . implode(',', $selected));
            PHP;
        $result = $this->runStdinHarness($php, "y\nn\n");
        $this->assertStringContainsString('RESULT=alpha', $result['stderr']);
        $this->assertStringNotContainsString('RESULT=alpha,beta', $result['stderr']);
    }

    public function testStdinConfirmDefaultOnEmptyInput(): void
    {
        $php = <<<'PHP'
            require $argv[1] . '/tools/ai/commands/helpers.php';
            require $argv[1] . '/tools/ai/install/selection-engine.php';
            $r = aiSelectionConfirm('stdin', 'Proceed?', true);
            fwrite(STDERR, 'RESULT=' . ($r ? 'true' : 'false'));
            PHP;
        $result = $this->runStdinHarness($php, "\n");
        $this->assertStringContainsString('RESULT=true', $result['stderr']);
    }

    public function testStdinChooseReturnsDefaultOnEmptyInput(): void
    {
        $php = <<<'PHP'
            require $argv[1] . '/tools/ai/commands/helpers.php';
            require $argv[1] . '/tools/ai/install/selection-engine.php';
            $options = [['key' => 'a', 'label' => 'a'], ['key' => 'b', 'label' => 'b']];
            $r = aiSelectionChoose('stdin', 'Pick: ', $options, 'b');
            fwrite(STDERR, 'RESULT=' . $r);
            PHP;
        $result = $this->runStdinHarness($php, "\n");
        $this->assertStringContainsString('RESULT=b', $result['stderr']);
    }

    /**
     * Execute a small PHP snippet in a child process with $input piped to FD 0.
     * The snippet receives the repo root as $argv[1] and prints its result to STDERR.
     *
     * @return array{stdout:string,stderr:string,exit:int}
     */
    private function runStdinHarness(string $php, string $input): array
    {
        $command = escapeshellarg((string) PHP_BINARY)
            . ' -r ' . escapeshellarg($php)
            . ' ' . escapeshellarg(self::$repoRoot);

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes, self::$repoRoot);
        $this->assertIsResource($process, "proc_open failed for: $command");
        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
    }
}
