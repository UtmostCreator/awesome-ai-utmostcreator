<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

class AdvisorScannerTest extends TestCase
{
    private static string $repoRoot;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root.');
        }
        self::$repoRoot = $root;
    }

    /** @return array{stdout:string,stderr:string,exit:int} */
    private function runTool(string $command): array
    {
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes, self::$repoRoot, [
            'HOME' => sys_get_temp_dir(),
            'XDG_CONFIG_HOME' => sys_get_temp_dir(),
            'GIT_CONFIG_GLOBAL' => '/dev/null',
            'PATH' => (string) getenv('PATH'),
        ]);
        $this->assertIsResource($process);
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
    }

    public function testAdvisorScanWritesSignalsArtifacts(): void
    {
        $php = escapeshellarg((string) PHP_BINARY);
        $result = $this->runTool($php . ' tools/ai/ai.php advisor --scan');
        $this->assertSame(0, $result['exit'], $result['stderr']);

        $jsonPath = self::$repoRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'generated' . DIRECTORY_SEPARATOR . 'project-signals.json';
        $this->assertFileExists($jsonPath);
        $decoded = json_decode((string) file_get_contents($jsonPath), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('tracked_files_count', $decoded);
        $this->assertArrayHasKey('toolchain', $decoded);
    }
}
