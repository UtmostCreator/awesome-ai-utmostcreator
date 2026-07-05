<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/../../tools/ai/install/config.php';
require_once __DIR__ . '/../../tools/ai/commands/stack_selection.php';

/**
 * Slice 2 of docs/tickets/arch-todo-dynamic-stack-permission-selection-20260705T011906Z/plan.md:
 * CLI flags (--stacks, --no-stack-detect, --stack-detect-only) and the pure
 * aiStackSelectionResolve() resolver used by both the wizard and --stack-detect-only.
 */
final class StackInstallWorkflowTest extends TestCase
{
    private static string $repoRoot;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new RuntimeException('Could not resolve repo root.');
        }
        self::$repoRoot = $root;
    }

    public function testParseArgsAcceptsStacksFlag(): void
    {
        $config = aiInstallerParseArgs(['install-ai-kit.php', '--target', self::$repoRoot, '--stacks', 'php,markdown']);
        self::assertSame(['php', 'markdown'], $config['stacks']);
        self::assertFalse($config['noStackDetect']);
        self::assertFalse($config['stackDetectOnly']);
    }

    public function testParseArgsAcceptsNoStackDetectAndDetectOnlyFlags(): void
    {
        $config = aiInstallerParseArgs(['install-ai-kit.php', '--target', self::$repoRoot, '--no-stack-detect', '--stack-detect-only']);
        self::assertTrue($config['noStackDetect']);
        self::assertTrue($config['stackDetectOnly']);
        self::assertSame([], $config['stacks']);
    }

    public function testUnrelatedFlagsStillWorkAfterStackFlagsAdded(): void
    {
        $config = aiInstallerParseArgs(['install-ai-kit.php', '--target', self::$repoRoot, '--profile', 'minimal', '--dry-run']);
        self::assertSame('minimal', $config['profile']);
        self::assertTrue($config['dryRun']);
    }

    public function testResolveDetectsPhpFromComposerJsonAndUsesDetectedAsDefaultSelection(): void
    {
        $target = $this->makeTempRoot();
        file_put_contents($target . '/composer.json', '{}');

        $resolved = aiStackSelectionResolve($target, []);

        self::assertContains('php', $resolved['selected']);
        self::assertArrayHasKey('php', $resolved['detected']);
    }

    public function testExplicitStacksOverrideDetection(): void
    {
        $target = $this->makeTempRoot();
        file_put_contents($target . '/composer.json', '{}');

        $resolved = aiStackSelectionResolve($target, ['stacks' => ['js-ts']]);

        self::assertSame(['js-ts'], $resolved['selected']);
        // Detection still runs (visible in the summary) even though it does not
        // widen the selection when --stacks is explicit.
        self::assertArrayHasKey('php', $resolved['detected']);
    }

    public function testUnknownExplicitStackIdThrows(): void
    {
        $target = $this->makeTempRoot();

        $this->expectException(\InvalidArgumentException::class);
        aiStackSelectionResolve($target, ['stacks' => ['not-a-real-stack']]);
    }

    public function testNoStackDetectYieldsEmptyDetectionButExplicitStacksStillWork(): void
    {
        $target = $this->makeTempRoot();
        file_put_contents($target . '/composer.json', '{}');

        $resolved = aiStackSelectionResolve($target, ['noStackDetect' => true]);
        self::assertSame([], $resolved['detected']);
        self::assertSame([], $resolved['selected']);

        $resolved = aiStackSelectionResolve($target, ['noStackDetect' => true, 'stacks' => ['php']]);
        self::assertSame(['php'], $resolved['selected']);
    }

    public function testSummaryIsHumanReadableAndListsSelection(): void
    {
        $target = $this->makeTempRoot();
        file_put_contents($target . '/composer.json', '{}');
        $resolved = aiStackSelectionResolve($target, []);

        $summary = aiStackSelectionSummary($resolved);
        self::assertStringContainsString('Detected stacks:', $summary);
        self::assertStringContainsString('php', $summary);
        self::assertStringContainsString('Selected stacks:', $summary);
    }

    public function testStackDetectOnlyCliExitsZeroAndPrintsSummary(): void
    {
        $target = $this->makeTempRoot();
        file_put_contents($target . '/package.json', '{}');

        $cmd = [PHP_BINARY, self::$repoRoot . '/tools/ai/install-ai-kit.php', '--target', $target, '--stack-detect-only'];
        $process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        self::assertSame(0, $exit);
        self::assertStringContainsString('Detected stacks:', (string) $stdout);
        self::assertStringContainsString('js-ts', (string) $stdout);
    }

    private function makeTempRoot(): string
    {
        $dir = sys_get_temp_dir() . '/ai-stack-workflow-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);

        return $dir;
    }
}
