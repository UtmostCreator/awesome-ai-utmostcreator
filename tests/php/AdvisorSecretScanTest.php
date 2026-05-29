<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

class AdvisorSecretScanTest extends TestCase
{
    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($child)) {
                $this->removeTree($child);
            } else {
                @unlink($child);
            }
        }

        @rmdir($path);
    }

    public function testSecretScanProducesFindingsArtifact(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        $this->assertNotFalse($root);
        $php = escapeshellarg((string) PHP_BINARY);
        $generated = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'advisor-secret-' . uniqid('', true);
        mkdir($generated, 0777, true);

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $env = $_ENV;
        $env['AI_ADVISOR_GENERATED_DIR'] = $generated;
        $process = proc_open($php . ' tools/ai/ai.php advisor --secret-scan', $descriptors, $pipes, (string) $root, $env);
        $this->assertIsResource($process);
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        $this->assertContains($exit, [0, 1]);

        $artifact = $generated . DIRECTORY_SEPARATOR . 'advisor-secret-findings.json';
        $this->assertFileExists($artifact);
        $decoded = json_decode((string) file_get_contents($artifact), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('blocked', $decoded);
        $this->assertArrayHasKey('findings', $decoded);

        $this->removeTree($generated);
    }
}
