<?php

declare(strict_types=1);

/**
 * Format artifact write result for human-readable output.
 * Handles the case where markdown was not written (null).
 */
function aiCliArtifactSummary(array $written): string
{
    if ($written['markdown'] !== null) {
        return "wrote {$written['json']} and {$written['markdown']}";
    }

    return "wrote {$written['json']}";
}

function aiCliRepoRoot(): string
{
    $root = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
    if ($root === false) {
        throw new RuntimeException('Could not resolve repository root.');
    }

    return $root;
}

function aiCliGeneratedDir(string $root): string
{
    $dir = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'generated';
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create docs/ai/generated directory.');
    }

    return $dir;
}

function aiCliIsoNow(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
}

function aiCliNullDevice(): string
{
    return PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
}

function aiCliGitValue(string $root, string $command): string
{
    $output = [];
    $exit = 0;
    // On Windows, cmd.exe cannot use a UNC working directory and prints
    // "UNC paths are not supported" before each spawned command. This call
    // already targets the repo via `git -C`, so run it from a non-UNC cwd.
    $cwdPrev = null;
    if (PHP_OS_FAMILY === 'Windows') {
        $cwd = getcwd();
        if ($cwd !== false && str_starts_with($cwd, '\\\\')) {
            $tmp = sys_get_temp_dir();
            if ($tmp !== '' && is_dir($tmp) && @chdir($tmp)) {
                $cwdPrev = $cwd;
            }
        }
    }
    exec('git -C ' . escapeshellarg($root) . ' ' . $command . ' 2>' . escapeshellarg(aiCliNullDevice()), $output, $exit);
    if ($cwdPrev !== null) {
        @chdir($cwdPrev);
    }
    if ($exit !== 0 || $output === []) {
        return 'unknown';
    }

    return trim((string) $output[0]);
}

function aiCliCurrentCommit(string $root): string
{
    return aiCliGitValue($root, 'rev-parse --short HEAD');
}

function aiCliCurrentBranch(string $root): string
{
    return aiCliGitValue($root, 'rev-parse --abbrev-ref HEAD');
}

function aiCliEstimateTokens(string $content): int
{
    return (int) ceil(strlen($content) / 4);
}

function aiCliToRelative(string $root, string $absolutePath): string
{
    $normalizedRoot = str_replace('\\', '/', $root);
    $normalizedPath = str_replace('\\', '/', $absolutePath);
    if (str_starts_with($normalizedPath, $normalizedRoot . '/')) {
        return substr($normalizedPath, strlen($normalizedRoot) + 1);
    }

    return $normalizedPath;
}

function aiCliLoadArtifactsRegistry(string $generatedDir): array
{
    $path = $generatedDir . DIRECTORY_SEPARATOR . 'artifacts.json';
    if (!is_file($path)) {
        return [
            'schema_version' => 1,
            'updated_at' => aiCliIsoNow(),
            'current_commit' => 'unknown',
            'artifacts' => [],
        ];
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return [
            'schema_version' => 1,
            'updated_at' => aiCliIsoNow(),
            'current_commit' => 'unknown',
            'artifacts' => [],
        ];
    }

    return $decoded;
}

function aiCliWriteArtifactsRegistry(string $generatedDir, array $registry): void
{
    $path = $generatedDir . DIRECTORY_SEPARATOR . 'artifacts.json';
    $encoded = json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        throw new RuntimeException('Failed to encode artifacts registry JSON.');
    }
    file_put_contents($path, $encoded . PHP_EOL);
}

function aiCliWriteArtifact(
    string $root,
    string $artifactBase,
    string $command,
    array $data,
    string $status = 'ok',
    ?int $score = null,
    ?string $recommendedNextAction = null,
    array $inputHashes = [],
    bool $writeMd = false
): array {
    $verbose = getenv('AI_ARTIFACTS_VERBOSE') === '1';
    $writeMd = $writeMd || $verbose;

    $generatedDir = aiCliGeneratedDir($root);
    $jsonPath = $generatedDir . DIRECTORY_SEPARATOR . $artifactBase . '.json';
    $mdPath = $generatedDir . DIRECTORY_SEPARATOR . $artifactBase . '.md';

    $payload = [
        'schema_version' => 1,
        'artifact' => $artifactBase . '.json',
        'generated_at' => aiCliIsoNow(),
        'command' => $command,
        'based_on_commit' => aiCliCurrentCommit($root),
        'based_on_branch' => aiCliCurrentBranch($root),
        'input_hashes' => (object) $inputHashes,
        'status' => $status,
        'score' => $score,
        'stale' => false,
        'recommended_next_action' => $recommendedNextAction,
        'data' => $data,
    ];

    $encodedJson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encodedJson === false) {
        throw new RuntimeException('Failed to encode artifact JSON.');
    }
    file_put_contents($jsonPath, $encodedJson . PHP_EOL);

    if ($writeMd) {
        $markdown = "# " . ucfirst(str_replace('-', ' ', $artifactBase)) . PHP_EOL . PHP_EOL;
        $markdown .= '- Status: `' . $status . '`' . PHP_EOL;
        $markdown .= '- Generated at: `' . $payload['generated_at'] . '`' . PHP_EOL;
        $markdown .= '- Commit: `' . $payload['based_on_commit'] . '`' . PHP_EOL;
        $markdown .= '- Branch: `' . $payload['based_on_branch'] . '`' . PHP_EOL;
        if ($recommendedNextAction !== null) {
            $markdown .= '- Recommended next action: `' . $recommendedNextAction . '`' . PHP_EOL;
        }
        $markdown .= PHP_EOL . '```json' . PHP_EOL . $encodedJson . PHP_EOL . '```' . PHP_EOL;
        file_put_contents($mdPath, $markdown);
    }

    $registry = aiCliLoadArtifactsRegistry($generatedDir);
    $registry['updated_at'] = aiCliIsoNow();
    $registry['current_commit'] = aiCliCurrentCommit($root);
    if (!isset($registry['artifacts']) || !is_array($registry['artifacts'])) {
        $registry['artifacts'] = [];
    }
    $registryEntry = [
        'generated_at' => $payload['generated_at'],
        'based_on_commit' => $payload['based_on_commit'],
        'command' => $command,
        'estimated_tokens' => aiCliEstimateTokens($encodedJson),
        'stale' => false,
        'json_path' => aiCliToRelative($root, $jsonPath),
    ];
    if ($writeMd) {
        $registryEntry['md_path'] = aiCliToRelative($root, $mdPath);
    }
    $registry['artifacts'][$artifactBase . '.json'] = $registryEntry;
    aiCliWriteArtifactsRegistry($generatedDir, $registry);

    return [
        'json' => aiCliToRelative($root, $jsonPath),
        'markdown' => $writeMd ? aiCliToRelative($root, $mdPath) : null,
    ];
}
