<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/../../tools/ai/install/stack-project-doc.php';
require_once __DIR__ . '/../../tools/ai/commands/stack_selection.php';

/**
 * P4.1/P4.2/P4.2a of
 * docs/tickets/arch-todo-stack-permission-placeholder-skill-trio-20260705T151632Z/plan.md:
 * the `scan-stack` skill's `stack.md` renderer, output path, and the
 * naming-collision guard against the pre-existing, unrelated
 * `docs/ai/project-stack.md` legacy compatibility shim.
 */
final class StackProjectDocTest extends TestCase
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

    public function testOutputPathIsExactlyDocsAiProjectStackMd(): void
    {
        self::assertSame('docs/ai/project/stack.md', aiStackProjectDocRelativePath());
    }

    public function testOutputPathIsDistinctFromLegacyProjectStackShim(): void
    {
        self::assertNotSame(
            aiStackProjectDocRelativePath(),
            aiStackLegacyProjectStackRelativePath(),
            'scan-stack output path must never collide with the pre-existing legacy shim path'
        );
        self::assertSame('docs/ai/project-stack.md', aiStackLegacyProjectStackRelativePath());
    }

    public function testWriteProjectDocNeverTouchesPreExistingLegacyShim(): void
    {
        $target = $this->makeTempRoot();
        mkdir($target . '/docs/ai', 0777, true);
        $legacyPath = $target . '/' . aiStackLegacyProjectStackRelativePath();
        $legacyContent = "# Legacy shim\n\nUnrelated pre-existing content.\n";
        file_put_contents($legacyPath, $legacyContent);

        file_put_contents($target . '/composer.json', '{}');
        $resolved = aiStackSelectionResolve($target, []);
        $written = aiStackWriteProjectDoc($target, $resolved, 'Example');

        self::assertSame(aiStackProjectDocPath($target), $written);
        self::assertFileExists($written);
        self::assertSame($legacyContent, (string) file_get_contents($legacyPath), 'legacy docs/ai/project-stack.md must remain byte-identical');
    }

    public function testWrittenDocContainsDetectedStacksConfidenceAndSelection(): void
    {
        $target = $this->makeTempRoot();
        file_put_contents($target . '/composer.json', '{}');
        $resolved = aiStackSelectionResolve($target, []);

        $written = aiStackWriteProjectDoc($target, $resolved);
        $content = (string) file_get_contents($written);

        self::assertStringContainsString('## Detected Stacks', $content);
        self::assertStringContainsString('`php`', $content);
        self::assertStringContainsString('## Selected Stacks', $content);
        self::assertStringContainsString('## Tool Versions', $content);
    }

    public function testRenderReportsNoneDetectedWhenNothingMatches(): void
    {
        $target = $this->makeTempRoot();
        $resolved = aiStackSelectionResolve($target, ['noStackDetect' => true]);

        $markdown = aiStackRenderProjectDocMarkdown($resolved);

        self::assertStringContainsString('None detected.', $markdown);
        self::assertStringContainsString('None selected.', $markdown);
    }

    public function testStackDetectCliWritesCommittedDocAndLeavesLegacyShimAlone(): void
    {
        $target = $this->makeTempRoot();
        mkdir($target . '/docs/ai', 0777, true);
        $legacyContent = "# Legacy shim\n";
        file_put_contents($target . '/docs/ai/project-stack.md', $legacyContent);
        file_put_contents($target . '/package.json', '{}');

        $result = $this->runStackDetectCli($target, []);

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('Wrote docs/ai/project/stack.md', $result['stdout']);
        self::assertFileExists($target . '/docs/ai/project/stack.md');
        self::assertSame($legacyContent, (string) file_get_contents($target . '/docs/ai/project-stack.md'));
    }

    public function testStackDetectCliNoWriteSkipsFileButStillPrintsSummary(): void
    {
        $target = $this->makeTempRoot();
        file_put_contents($target . '/composer.json', '{}');

        $result = $this->runStackDetectCli($target, ['--no-write']);

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('Detected stacks:', $result['stdout']);
        self::assertStringNotContainsString('Wrote docs/ai/project/stack.md', $result['stdout']);
        self::assertDirectoryDoesNotExist($target . '/docs/ai/project');
    }

    public function testStackDetectCliStacksFlagOverridesSelectionButStillDetects(): void
    {
        $target = $this->makeTempRoot();
        file_put_contents($target . '/composer.json', '{}');

        $result = $this->runStackDetectCli($target, ['--stacks', 'js-ts']);

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('Detected stacks:', $result['stdout']);
        self::assertStringContainsString('php', $result['stdout'], 'detection still runs and reports php even though it is not selected');
        self::assertStringContainsString('Selected stacks: js-ts', $result['stdout']);
        self::assertFileExists($target . '/docs/ai/project/stack.md');
        self::assertStringContainsString('`js-ts`', (string) file_get_contents($target . '/docs/ai/project/stack.md'));
    }

    /** @param list<string> $extraArgs @return array{exit:int,stdout:string,stderr:string} */
    private function runStackDetectCli(string $target, array $extraArgs): array
    {
        $env = getenv();
        $env['AI_CLI_REPO_ROOT'] = $target;
        $cmd = array_merge([PHP_BINARY, self::$repoRoot . '/tools/ai/ai.php', 'stack-detect'], $extraArgs);
        $process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $target, $env);
        self::assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return ['exit' => $exit, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    private function makeTempRoot(): string
    {
        $dir = sys_get_temp_dir() . '/ai-stack-project-doc-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);

        return $dir;
    }
}
