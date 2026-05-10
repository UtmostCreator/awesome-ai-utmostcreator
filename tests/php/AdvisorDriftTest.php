<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

class AdvisorDriftTest extends TestCase
{
    public function testAdvisorBaselineAndDiffProduceArtifacts(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        $this->assertNotFalse($root);
        $php = escapeshellarg((string) PHP_BINARY);

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($php . ' tools/ai/ai.php advisor --scan', $descriptors, $pipes, (string) $root);
        $this->assertIsResource($process);
        fclose($pipes[0]); stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); proc_close($process);

        $process2 = proc_open($php . ' tools/ai/ai.php advisor --score', $descriptors, $pipes2, (string) $root);
        $this->assertIsResource($process2);
        fclose($pipes2[0]); stream_get_contents($pipes2[1]); stream_get_contents($pipes2[2]); fclose($pipes2[1]); fclose($pipes2[2]); proc_close($process2);

        $process3 = proc_open($php . ' tools/ai/ai.php advisor --baseline', $descriptors, $pipes3, (string) $root);
        $this->assertIsResource($process3);
        fclose($pipes3[0]); stream_get_contents($pipes3[1]); stream_get_contents($pipes3[2]); fclose($pipes3[1]); fclose($pipes3[2]);
        $exit3 = proc_close($process3);
        $this->assertSame(0, $exit3);

        $process4 = proc_open($php . ' tools/ai/ai.php advisor --diff', $descriptors, $pipes4, (string) $root);
        $this->assertIsResource($process4);
        fclose($pipes4[0]); stream_get_contents($pipes4[1]); stream_get_contents($pipes4[2]); fclose($pipes4[1]); fclose($pipes4[2]);
        $exit4 = proc_close($process4);
        $this->assertSame(0, $exit4);

        $drift = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'generated' . DIRECTORY_SEPARATOR . 'advisor-drift.md';
        $this->assertFileExists($drift);
    }
}
