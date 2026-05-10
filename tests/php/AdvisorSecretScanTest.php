<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

class AdvisorSecretScanTest extends TestCase
{
    public function testSecretScanProducesFindingsArtifact(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        $this->assertNotFalse($root);
        $php = escapeshellarg((string) PHP_BINARY);

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($php . ' tools/ai/ai.php advisor --secret-scan', $descriptors, $pipes, (string) $root);
        $this->assertIsResource($process);
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        $this->assertContains($exit, [0, 1]);

        $artifact = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'generated' . DIRECTORY_SEPARATOR . 'advisor-secret-findings.json';
        $this->assertFileExists($artifact);
        $decoded = json_decode((string) file_get_contents($artifact), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('blocked', $decoded);
        $this->assertArrayHasKey('findings', $decoded);
    }
}
