<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

class AdvisorSchemaTest extends TestCase
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

    public function testAdvisorSchemaFilesExist(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        $this->assertNotFalse($root);
        $this->assertFileExists($root . DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'project-signals.schema.json');
        $this->assertFileExists($root . DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'project-scorecard.schema.json');
        $this->assertFileExists($root . DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'advisor-recommendation.schema.json');
    }

    public function testAdvisorCheckFailsForMalformedSignals(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        $this->assertNotFalse($root);
        $generated = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'advisor-schema-' . uniqid('', true);
        $signals = $generated . DIRECTORY_SEPARATOR . 'project-signals.json';
        mkdir($generated, 0777, true);
        file_put_contents($signals, json_encode(['schema_version' => 1], JSON_PRETTY_PRINT));

        $php = escapeshellarg((string) PHP_BINARY);
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $env = $_ENV;
        $env['AI_ADVISOR_GENERATED_DIR'] = $generated;
        $process = proc_open($php . ' tools/ai/ai.php advisor --validate', $descriptors, $pipes, (string) $root, $env);
        $this->assertIsResource($process);
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        $this->removeTree($generated);

        $this->assertSame(1, $exit);
    }
}
