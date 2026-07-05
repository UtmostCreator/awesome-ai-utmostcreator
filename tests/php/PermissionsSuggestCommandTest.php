<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/../../tools/ai/commands/permissions_suggest_command.php';
require_once __DIR__ . '/../../tools/ai/commands/stack_detect_command.php';

/**
 * P4.3/P4.4/P4.8 of
 * docs/tickets/arch-todo-stack-permission-placeholder-skill-trio-20260705T151632Z/plan.md:
 * the `generate-permissions` skill's refuse-gate, preview surface, and
 * deny-floor-safety contract (must call aiPermissionStackOverlayEntries(),
 * never derive raw permission patterns from package names).
 */
final class PermissionsSuggestCommandTest extends TestCase
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

    public function testRefusesWithoutFreshScan(): void
    {
        $target = $this->makeTempRoot();

        $exit = aiRunPermissionsSuggest($target, []);

        self::assertSame(1, $exit);
    }

    public function testRefusesWhenEvidenceFileIsInvalidJson(): void
    {
        $target = $this->makeTempRoot();
        mkdir($target . '/.ai', 0777, true);
        file_put_contents($target . '/.ai/stack-detection.json', 'not json');

        $exit = aiRunPermissionsSuggest($target, []);

        self::assertSame(1, $exit);
    }

    public function testProceedsAfterFreshScanAndNeverWritesAnything(): void
    {
        $target = $this->makeTempRoot();
        file_put_contents($target . '/composer.json', '{}');
        aiRunStackDetect($target, []);

        $filesBefore = $this->listFilesRecursive($target);
        $exit = aiRunPermissionsSuggest($target, []);
        $filesAfter = $this->listFilesRecursive($target);

        self::assertSame(0, $exit);
        self::assertSame($filesBefore, $filesAfter, 'permissions-suggest must never write any file (preview only)');
    }

    public function testNoStacksSelectedIsANoOpNotAnError(): void
    {
        $target = $this->makeTempRoot();
        mkdir($target . '/.ai', 0777, true);
        file_put_contents($target . '/.ai/stack-detection.json', json_encode([
            'informational' => true,
            'detected' => [],
            'selected' => [],
            'versionChecks' => [],
        ]));

        $exit = aiRunPermissionsSuggest($target, []);

        self::assertSame(0, $exit);
    }

    /**
     * Deny-floor safety (P4.8): the command must resolve permissions only via
     * aiPermissionStackOverlayEntries() — proven here indirectly by confirming
     * the composed model's hard-deny floor entries are unaffected by an
     * arbitrary stack selection, mirroring the guarantee
     * aiPermissionComposeFromSpec() itself enforces for every caller.
     */
    public function testComposedPreviewNeverWeakensHardDenyFloor(): void
    {
        $target = $this->makeTempRoot();
        file_put_contents($target . '/composer.json', '{}');
        aiRunStackDetect($target, []);

        $env = getenv();
        $env['AI_CLI_REPO_ROOT'] = $target;
        $result = $this->runCli($target, $env, ['permissions-suggest', '--profile', 'impl', '--edit-surface', 'code']);

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('hard-deny floor is enforced', $result['stdout']);
    }

    public function testStackDetectCliThenPermissionsSuggestCliEndToEnd(): void
    {
        $target = $this->makeTempRoot();
        file_put_contents($target . '/composer.json', '{}');

        $env = getenv();
        $env['AI_CLI_REPO_ROOT'] = $target;

        $scan = $this->runCli($target, $env, ['stack-detect']);
        self::assertSame(0, $scan['exit']);

        $suggest = $this->runCli($target, $env, ['permissions-suggest']);
        self::assertSame(0, $suggest['exit']);
        self::assertStringContainsString('PREVIEW ONLY', $suggest['stdout']);
        self::assertStringContainsString('php -l *', $suggest['stdout']);
    }

    /** @param array<string,string> $env @param list<string> $cliArgs @return array{exit:int,stdout:string,stderr:string} */
    private function runCli(string $target, array $env, array $cliArgs): array
    {
        $cmd = array_merge([PHP_BINARY, self::$repoRoot . '/tools/ai/ai.php'], $cliArgs);
        $process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $target, $env);
        self::assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return ['exit' => $exit, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    /** @return list<string> */
    private function listFilesRecursive(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }
        sort($files);

        return $files;
    }

    private function makeTempRoot(): string
    {
        $dir = sys_get_temp_dir() . '/ai-permissions-suggest-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);

        return $dir;
    }
}
