<?php

declare(strict_types=1);

require_once __DIR__ . '/../command-exists.php';

function aiAdvisorGeneratedDir(string $root): string
{
    $override = getenv('AI_ADVISOR_GENERATED_DIR');
    if (is_string($override) && $override !== '') {
        $dir = str_starts_with($override, DIRECTORY_SEPARATOR)
            ? $override
            : $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $override);
    } else {
        $dir = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'generated';
    }
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create advisor generated directory.');
    }
    return $dir;
}

function aiAdvisorCommandExists(string $command): bool
{
    return aiCommandExists($command);
}

function aiAdvisorReadJson(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException('Missing JSON file: ' . $path);
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid JSON file: ' . $path);
    }
    return $decoded;
}

function aiAdvisorWriteJson(string $path, array $data): void
{
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        throw new RuntimeException('Failed to encode JSON for: ' . $path);
    }
    file_put_contents($path, $encoded . PHP_EOL);
}

function aiAdvisorWriteMarkdown(string $path, string $content): void
{
    file_put_contents($path, $content);
}

function aiAdvisorTokenEstimate(string $content): int
{
    return (int) ceil(strlen($content) / 4);
}
