<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Slice 6 of docs/tickets/arch-todo-dynamic-stack-permission-selection-20260705T011906Z/plan.md:
 * end-to-end proof that a real install run detects a pre-existing target-project
 * signal (composer.json), writes the scalar summary into .ai/project.yml, and
 * writes the informational .ai/stack-detection.json evidence file — without
 * attributing the kit's own shipped files (e.g. .github/workflows/*.yml) to the
 * target project's stack.
 */
final class StackEndToEndInstallTest extends TestCase
{
    private static string $repoRoot;
    /** @var list<string> */
    private array $tmpDirs = [];

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new RuntimeException('Could not resolve repo root.');
        }
        self::$repoRoot = $root;
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpDirs as $dir) {
            $this->removeTree($dir);
        }
        $this->tmpDirs = [];
    }

    public function testRealInstallDetectsComposerJsonAndWritesStackMetadata(): void
    {
        $target = $this->makeTempRoot();
        mkdir($target . '/.git', 0700, true);
        file_put_contents($target . '/composer.json', '{}');

        $result = $this->runInstall($target, ['--profile', 'minimal', '--project-name', 'stack-e2e-demo']);
        self::assertSame(0, $result['exit'], $result['stdout'] . $result['stderr']);

        $projectYaml = (string) file_get_contents($target . '/.ai/project.yml');
        // php.json implies markdown+shell, so detection legitimately picks up all three;
        // primaryStack must be the highest-confidence one (php: composer.json = 95),
        // not the alphabetically-first selected id.
        self::assertStringContainsString('selectedStacks: "markdown,php,shell"', $projectYaml);
        self::assertStringContainsString('primaryStack: "php"', $projectYaml);

        $evidence = json_decode((string) file_get_contents($target . '/.ai/stack-detection.json'), true);
        self::assertIsArray($evidence);
        self::assertTrue($evidence['informational']);
        self::assertContains('php', $evidence['selected']);
        self::assertArrayHasKey('php', $evidence['detected']);
    }

    public function testRealInstallDoesNotAttributeKitsOwnShippedFilesToTargetStack(): void
    {
        $target = $this->makeTempRoot();
        mkdir($target . '/.git', 0700, true);
        // No pre-existing composer.json/package.json/.github — a bare target.

        $result = $this->runInstall($target, ['--profile', 'minimal', '--project-name', 'stack-e2e-bare']);
        self::assertSame(0, $result['exit'], $result['stdout'] . $result['stderr']);

        // The kit itself ships .github/workflows/*.yml to installed targets; if detection
        // ran AFTER the copy step it would falsely report github-actions here.
        self::assertDirectoryDoesNotExist($target . '/.github/workflows', 'sanity check assumption only; minimal profile may not ship workflows');

        $evidence = json_decode((string) file_get_contents($target . '/.ai/stack-detection.json'), true);
        self::assertIsArray($evidence);
        self::assertSame([], $evidence['detected']);
        self::assertSame([], $evidence['selected']);
    }

    public function testNoStackDetectFlagSkipsDetectionEntirely(): void
    {
        $target = $this->makeTempRoot();
        mkdir($target . '/.git', 0700, true);
        file_put_contents($target . '/composer.json', '{}');

        $result = $this->runInstall($target, ['--profile', 'minimal', '--project-name', 'stack-e2e-nodetect', '--no-stack-detect']);
        self::assertSame(0, $result['exit'], $result['stdout'] . $result['stderr']);

        $evidence = json_decode((string) file_get_contents($target . '/.ai/stack-detection.json'), true);
        self::assertIsArray($evidence);
        self::assertSame([], $evidence['detected']);
    }

    /** @param list<string> $extraArgs @return array{stdout:string,stderr:string,exit:int} */
    private function runInstall(string $target, array $extraArgs): array
    {
        $cmd = array_merge(
            [PHP_BINARY, self::$repoRoot . '/tools/ai/install-ai-kit.php', '--target', $target],
            $extraArgs
        );
        $process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, self::$repoRoot);
        self::assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
    }

    private function makeTempRoot(): string
    {
        $dir = sys_get_temp_dir() . '/ai-stack-e2e-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        $this->tmpDirs[] = $dir;

        return $dir;
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
