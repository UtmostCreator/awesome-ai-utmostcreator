<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

class AdvisorSchemaTest extends TestCase
{
    public function testAdvisorSchemaFilesExist(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        $this->assertNotFalse($root);
        $this->assertFileExists($root . DIRECTORY_SEPARATOR . '.schemas' . DIRECTORY_SEPARATOR . 'project-signals.schema.json');
        $this->assertFileExists($root . DIRECTORY_SEPARATOR . '.schemas' . DIRECTORY_SEPARATOR . 'project-scorecard.schema.json');
        $this->assertFileExists($root . DIRECTORY_SEPARATOR . '.schemas' . DIRECTORY_SEPARATOR . 'advisor-recommendation.schema.json');
    }

    public function testAdvisorCheckFailsForMalformedSignals(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        $this->assertNotFalse($root);
        $generated = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'generated';
        $signals = $generated . DIRECTORY_SEPARATOR . 'project-signals.json';
        if (!is_dir($generated)) {
            mkdir($generated, 0777, true);
        }
        $original = is_file($signals) ? (string) file_get_contents($signals) : null;
        file_put_contents($signals, json_encode(['schema_version' => 1], JSON_PRETTY_PRINT));

        $php = escapeshellarg((string) PHP_BINARY);
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($php . ' tools/ai/ai.php advisor --validate', $descriptors, $pipes, (string) $root);
        $this->assertIsResource($process);
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        if ($original === null) {
            @unlink($signals);
        } else {
            file_put_contents($signals, $original);
        }

        $this->assertSame(1, $exit);
    }
}
