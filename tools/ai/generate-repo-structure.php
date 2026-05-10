<?php

declare(strict_types=1);

$checkOnly = in_array('--check', $argv, true);
$withScc = in_array('--with-scc', $argv, true);
$rootInput = '.';
$outputDirInput = 'docs/ai/generated';
$metadataPathInput = 'docs/ai/repo-directory-map.json';

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--root=')) {
        $rootInput = substr($argument, strlen('--root='));
    }

    if (str_starts_with($argument, '--output-dir=')) {
        $outputDirInput = substr($argument, strlen('--output-dir='));
    }

    if (str_starts_with($argument, '--metadata=')) {
        $metadataPathInput = substr($argument, strlen('--metadata='));
    }
}

$root = realpath($rootInput);

if ($root === false || !is_dir($root)) {
    fwrite(STDERR, "ERROR: root directory not found: {$rootInput}\n");
    exit(1);
}

$gitCheck = runCommand(['git', 'rev-parse', '--is-inside-work-tree'], $root);

if ($gitCheck['exit'] !== 0 || trim($gitCheck['stdout']) !== 'true') {
    fwrite(STDERR, "ERROR: root is not a git repository: {$root}\n");
    exit(1);
}

$filesResult = runCommand(['git', 'ls-files'], $root);

if ($filesResult['exit'] !== 0) {
    fwrite(STDERR, "ERROR: unable to read tracked files with git ls-files\n");
    fwrite(STDERR, trim($filesResult['stderr']) . "\n");
    exit(1);
}

$trackedFiles = array_values(
    array_filter(
        array_map(static fn(string $line): string => trim(str_replace('\\', '/', $line)), preg_split('/\r?\n/', $filesResult['stdout']) ?: []),
        static fn(string $line): bool => $line !== ''
    )
);

$trackedFiles = array_values(array_filter(
    $trackedFiles,
    static fn (string $path): bool => !repoStructureShouldExcludeTrackedPath($path)
        && file_exists($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path))
));

sort($trackedFiles, SORT_STRING);

if ($trackedFiles === []) {
    fwrite(STDERR, "ERROR: no tracked files found\n");
    exit(1);
}

$folderMap = [];

foreach ($trackedFiles as $file) {
    $parts = explode('/', $file);
    $folder = count($parts) > 1 ? $parts[0] : '.';

    if (!array_key_exists($folder, $folderMap)) {
        $folderMap[$folder] = [];
    }

    $folderMap[$folder][] = $file;
}

ksort($folderMap, SORT_STRING);

$topLevelPaths = array_keys($folderMap);

$metadataPath = isAbsolutePath($metadataPathInput)
    ? $metadataPathInput
    : $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $metadataPathInput);

$metadata = loadAndValidateMetadata($metadataPath, $root, $trackedFiles, $topLevelPaths);
$metadataByPath = $metadata['directories'];
$metadataExemptions = $metadata['exemptions'];

$sccByFile = [];

if ($withScc) {
    $sccCheck = runCommand(['scc', '--by-file', '--format', 'json', '.'], $root);

    if ($sccCheck['exit'] !== 0) {
        $stderr = trim($sccCheck['stderr']);
        $hint = "Install scc: winget install BenBoyter.scc | brew install scc | use release binary/Go install on Linux";
        fwrite(STDERR, "ERROR: unable to run scc --by-file --format json .\n");
        fwrite(STDERR, ($stderr === '' ? 'scc command failed' : $stderr) . "\n");
        fwrite(STDERR, "HINT: {$hint}\n");
        exit(1);
    }

    $decoded = json_decode($sccCheck['stdout'], true);

    if (!is_array($decoded)) {
        fwrite(STDERR, "ERROR: invalid JSON returned by scc\n");
        exit(1);
    }

    foreach ($decoded as $languageGroup) {
        if (!is_array($languageGroup) || !isset($languageGroup['Files']) || !is_array($languageGroup['Files'])) {
            continue;
        }

        foreach ($languageGroup['Files'] as $fileEntry) {
            if (!is_array($fileEntry) || !isset($fileEntry['Location']) || !is_string($fileEntry['Location'])) {
                continue;
            }

            $location = str_replace('\\', '/', $fileEntry['Location']);
            $location = preg_replace('/^\.\//', '', $location) ?? $location;

            $sccByFile[$location] = [
                'lines' => toInt($fileEntry['Lines'] ?? 0),
                'code' => toInt($fileEntry['Code'] ?? 0),
                'comments' => toInt($fileEntry['Comment'] ?? 0),
                'blanks' => toInt($fileEntry['Blank'] ?? 0),
                'complexity' => toInt($fileEntry['Complexity'] ?? 0),
                'bytes' => toInt($fileEntry['Bytes'] ?? 0),
            ];
        }
    }
}

$folders = [];

foreach ($folderMap as $folder => $files) {
    sort($files, SORT_STRING);

    $metrics = [
        'lines' => 0,
        'code' => 0,
        'comments' => 0,
        'blanks' => 0,
        'complexity' => 0,
        'bytes' => 0,
    ];

    if ($withScc) {
        foreach ($files as $file) {
            if (!array_key_exists($file, $sccByFile)) {
                continue;
            }

            $metrics['lines'] += $sccByFile[$file]['lines'];
            $metrics['code'] += $sccByFile[$file]['code'];
            $metrics['comments'] += $sccByFile[$file]['comments'];
            $metrics['blanks'] += $sccByFile[$file]['blanks'];
            $metrics['complexity'] += $sccByFile[$file]['complexity'];
            $metrics['bytes'] += $sccByFile[$file]['bytes'];
        }
    }

    $folderMetadata = $metadataByPath[$folder] ?? [
        'purpose' => 'unknown',
        'designed_for' => 'unknown',
        'install_guide' => 'unknown',
        'install_script' => 'unknown',
        'ai_entrypoint' => 'unknown',
        'notes' => 'unknown',
    ];

    $folders[] = [
        'path' => $folder,
        'file_count' => count($files),
        'files' => $files,
        'files_csv' => implode(',', $files),
        'purpose' => $folderMetadata['purpose'],
        'designed_for' => $folderMetadata['designed_for'],
        'install_guide' => $folderMetadata['install_guide'],
        'install_script' => $folderMetadata['install_script'],
        'ai_entrypoint' => $folderMetadata['ai_entrypoint'],
        'notes' => $folderMetadata['notes'],
        'metrics' => $withScc ? $metrics : null,
    ];
}

usort($folders, static fn(array $a, array $b): int => strcmp((string) $a['path'], (string) $b['path']));

$missingMetadata = [];
foreach ($topLevelPaths as $path) {
    if (array_key_exists($path, $metadataByPath)) {
        continue;
    }

    if (array_key_exists($path, $metadataExemptions)) {
        continue;
    }

    $missingMetadata[] = $path;
}

sort($missingMetadata, SORT_STRING);

if ($missingMetadata !== []) {
    fwrite(STDERR, 'ERROR: missing metadata for top-level paths: ' . implode(', ', $missingMetadata) . "\n");
    exit(1);
}

$payload = [
    'generated_by' => 'tools/ai/generate-repo-structure.php',
    'source' => 'git ls-files',
    'with_scc' => $withScc,
    'folder_count' => count($folders),
    'tracked_file_count' => count($trackedFiles),
    'metadata' => [
        'schema_version' => $metadata['schema_version'],
        'coverage_required' => true,
        'covered_count' => count($metadataByPath),
        'exemption_count' => count($metadataExemptions),
        'missing_count' => count($missingMetadata),
    ],
    'folders' => $folders,
];

$jsonOutput = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
$csvOutput = renderCsv($folders, $withScc);
$mdOutput = renderMarkdown($payload);
$logOutput = renderDeterministicLog($payload);

$outputDir = isAbsolutePath($outputDirInput)
    ? $outputDirInput
    : $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $outputDirInput);

$outputDir = rtrim($outputDir, DIRECTORY_SEPARATOR);

$jsonPath = $outputDir . DIRECTORY_SEPARATOR . 'repo-structure.json';
$csvPath = $outputDir . DIRECTORY_SEPARATOR . 'repo-structure.csv';
$mdPath = $outputDir . DIRECTORY_SEPARATOR . 'repo-structure.md';
$logPath = $outputDir . DIRECTORY_SEPARATOR . 'repo-structure.log';

$messages = [];
$ok = true;
$ok = compareOrWrite($jsonPath, $jsonOutput, $checkOnly, $messages) && $ok;
$ok = compareOrWrite($csvPath, $csvOutput, $checkOnly, $messages) && $ok;
$ok = compareOrWrite($mdPath, $mdOutput, $checkOnly, $messages) && $ok;
$ok = compareOrWrite($logPath, $logOutput, $checkOnly, $messages) && $ok;

foreach ($messages as $message) {
    $stream = str_starts_with($message, 'ERROR:') ? STDERR : STDOUT;
    fwrite($stream, $message . "\n");
}

exit($ok ? 0 : 1);

function compareOrWrite(string $path, string $content, bool $checkOnly, array &$messages): bool
{
    $normalizedContent = str_replace("\r\n", "\n", $content);
    $exists = is_file($path);
    $current = $exists ? str_replace("\r\n", "\n", (string)file_get_contents($path)) : null;

    if ($checkOnly) {
        if (!$exists) {
            $messages[] = "ERROR: missing generated file {$path}";
            return false;
        }

        if ($current !== $normalizedContent) {
            $messages[] = "ERROR: generated output drift detected in {$path}";
            return false;
        }

        $messages[] = "OK: {$path} is up to date";
        return true;
    }

    $directory = dirname($path);

    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        $messages[] = "ERROR: unable to create directory {$directory}";
        return false;
    }

    file_put_contents($path, $normalizedContent);
    $messages[] = "OK: wrote {$path}";
    return true;
}

function renderCsv(array $folders, bool $withScc): string
{
    $stream = fopen('php://temp', 'r+');

    if ($stream === false) {
        throw new RuntimeException('unable to open temporary stream for csv rendering');
    }

    $headers = ['folder', 'file_count', 'purpose', 'designed_for', 'install_guide', 'install_script', 'ai_entrypoint', 'notes'];

    if ($withScc) {
        $headers = array_merge($headers, ['lines', 'code', 'comments', 'blanks', 'complexity', 'bytes']);
    }

    $headers[] = 'files';
    fputcsv($stream, $headers, ',', '"', '\\');

    foreach ($folders as $folder) {
        $row = [
            $folder['path'],
            $folder['file_count'],
            $folder['purpose'],
            $folder['designed_for'],
            $folder['install_guide'],
            $folder['install_script'],
            $folder['ai_entrypoint'],
            $folder['notes'],
        ];

        if ($withScc) {
            $row[] = $folder['metrics']['lines'];
            $row[] = $folder['metrics']['code'];
            $row[] = $folder['metrics']['comments'];
            $row[] = $folder['metrics']['blanks'];
            $row[] = $folder['metrics']['complexity'];
            $row[] = $folder['metrics']['bytes'];
        }

        $row[] = $folder['files_csv'];
        fputcsv($stream, $row, ',', '"', '\\');
    }

    rewind($stream);
    $output = (string)stream_get_contents($stream);
    fclose($stream);
    return $output;
}

function renderMarkdown(array $payload): string
{
    $lines = [];
    $lines[] = '# Repo Structure';
    $lines[] = '';
    $lines[] = '_Generated by `php tools/ai/generate-repo-structure.php`. Do not edit by hand._';
    $lines[] = '';
    $lines[] = '- Source: `git ls-files` (tracked files only)';
    $lines[] = '- Folder count: `' . $payload['folder_count'] . '`';
    $lines[] = '- Tracked file count: `' . $payload['tracked_file_count'] . '`';
    $lines[] = '- SCC metrics: `' . ($payload['with_scc'] ? 'enabled' : 'disabled') . '`';
    $lines[] = '- Metadata schema version: `' . $payload['metadata']['schema_version'] . '`';
    $lines[] = '';
    $lines[] = '## Folder Index';
    $lines[] = '';

    foreach ($payload['folders'] as $folder) {
        $summary = '- `' . $folder['path'] . '` (' . $folder['file_count'] . ' files)';

        if (is_array($folder['metrics'])) {
            $summary .= ', code=' . $folder['metrics']['code'] . ', complexity=' . $folder['metrics']['complexity'];
        }

        $summary .= ' - ' . $folder['purpose'];
        $lines[] = $summary;
    }

    $lines[] = '';
    $lines[] = '## Directory Metadata';
    $lines[] = '';

    foreach ($payload['folders'] as $folder) {
        $lines[] = '### `' . $folder['path'] . '`';
        $lines[] = '';
        $lines[] = '- Purpose: ' . $folder['purpose'];
        $lines[] = '- Designed for: ' . $folder['designed_for'];
        $lines[] = '- Install guide: `' . $folder['install_guide'] . '`';
        $lines[] = '- Install script: `' . $folder['install_script'] . '`';
        $lines[] = '- AI entrypoint: `' . $folder['ai_entrypoint'] . '`';
        $lines[] = '- Notes: ' . $folder['notes'];
        $lines[] = '';
    }

    $lines[] = '## Folder To Files (comma-separated)';
    $lines[] = '';

    foreach ($payload['folders'] as $folder) {
        $lines[] = '### `' . $folder['path'] . '`';
        $lines[] = '';
        $lines[] = '`' . $folder['files_csv'] . '`';
        $lines[] = '';
    }

    return implode("\n", $lines) . "\n";
}

function renderDeterministicLog(array $payload): string
{
    $lines = [];
    $lines[] = 'generator=tools/ai/generate-repo-structure.php';
    $lines[] = 'source=git ls-files';
    $lines[] = 'scc_enabled=' . ($payload['with_scc'] ? 'true' : 'false');
    $lines[] = 'folder_count=' . $payload['folder_count'];
    $lines[] = 'tracked_file_count=' . $payload['tracked_file_count'];
    $lines[] = 'metadata_schema_version=' . $payload['metadata']['schema_version'];
    $lines[] = 'metadata_covered=' . $payload['metadata']['covered_count'];
    $lines[] = 'metadata_exemptions=' . $payload['metadata']['exemption_count'];
    $lines[] = 'metadata_missing=' . $payload['metadata']['missing_count'];

    return implode("\n", $lines) . "\n";
}

function loadAndValidateMetadata(string $metadataPath, string $root, array $trackedFiles, array $topLevelPaths): array
{
    if (!is_file($metadataPath)) {
        fwrite(STDERR, "ERROR: metadata file not found: {$metadataPath}\n");
        exit(1);
    }

    $raw = (string)file_get_contents($metadataPath);
    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        fwrite(STDERR, "ERROR: metadata file is not valid JSON: {$metadataPath}\n");
        exit(1);
    }

    if (!array_key_exists('schema_version', $decoded) || !is_int($decoded['schema_version'])) {
        fwrite(STDERR, "ERROR: metadata schema_version must exist and be an integer\n");
        exit(1);
    }

    if ($decoded['schema_version'] !== 1) {
        fwrite(STDERR, 'ERROR: unsupported metadata schema_version: ' . $decoded['schema_version'] . "\n");
        exit(1);
    }

    if (!isset($decoded['directories']) || !is_array($decoded['directories'])) {
        fwrite(STDERR, "ERROR: metadata directories must be an array\n");
        exit(1);
    }

    if (!isset($decoded['metadata_exemptions']) || !is_array($decoded['metadata_exemptions'])) {
        fwrite(STDERR, "ERROR: metadata_exemptions must be an array\n");
        exit(1);
    }

    $trackedSet = array_fill_keys($trackedFiles, true);
    $topLevelSet = array_fill_keys($topLevelPaths, true);
    $allowedExtraExemptions = ['.git' => true, '.repomix-context' => true];

    $exemptions = [];
    foreach ($decoded['metadata_exemptions'] as $idx => $entry) {
        if (!is_array($entry)) {
            fwrite(STDERR, "ERROR: metadata_exemptions entry #{$idx} must be an object\n");
            exit(1);
        }

        $path = trim((string)($entry['path'] ?? ''));
        $reason = trim((string)($entry['reason'] ?? ''));

        if ($path === '' || $reason === '') {
            fwrite(STDERR, "ERROR: metadata_exemptions entries require non-empty path and reason\n");
            exit(1);
        }

        if (!array_key_exists($path, $topLevelSet) && !array_key_exists($path, $allowedExtraExemptions)) {
            fwrite(STDERR, "ERROR: metadata exemption path is not a tracked top-level path or allowed generated path: {$path}\n");
            exit(1);
        }

        $exemptions[$path] = ['path' => $path, 'reason' => $reason];
    }

    ksort($exemptions, SORT_STRING);

    $requiredFields = ['path', 'purpose', 'designed_for', 'install_guide', 'install_script', 'ai_entrypoint', 'notes'];
    $directories = [];

    foreach ($decoded['directories'] as $idx => $entry) {
        if (!is_array($entry)) {
            fwrite(STDERR, "ERROR: directories entry #{$idx} must be an object\n");
            exit(1);
        }

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $entry)) {
                fwrite(STDERR, "ERROR: metadata directory entry #{$idx} missing required field '{$field}'\n");
                exit(1);
            }

            if (!is_string($entry[$field]) || trim($entry[$field]) === '') {
                fwrite(STDERR, "ERROR: metadata field '{$field}' in entry #{$idx} must be a non-empty string\n");
                exit(1);
            }
        }

        $path = trim($entry['path']);

        if (array_key_exists($path, $directories)) {
            fwrite(STDERR, "ERROR: duplicate metadata path: {$path}\n");
            exit(1);
        }

        if (!array_key_exists($path, $topLevelSet)) {
            fwrite(STDERR, "ERROR: metadata path does not match a tracked top-level path: {$path}\n");
            exit(1);
        }

        foreach (['install_guide', 'install_script', 'ai_entrypoint'] as $refField) {
            $refValue = trim($entry[$refField]);

            if ($refValue === 'none') {
                continue;
            }

            $refFile = str_replace('\\', '/', $refValue);
            $absRef = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $refFile);

            if (!is_file($absRef)) {
                fwrite(STDERR, "ERROR: metadata reference '{$refField}' points to missing file: {$refValue}\n");
                exit(1);
            }
        }

        $directories[$path] = [
            'path' => $path,
            'purpose' => trim($entry['purpose']),
            'designed_for' => trim($entry['designed_for']),
            'install_guide' => trim($entry['install_guide']),
            'install_script' => trim($entry['install_script']),
            'ai_entrypoint' => trim($entry['ai_entrypoint']),
            'notes' => trim($entry['notes']),
        ];
    }

    if (array_key_exists('.', $topLevelSet) && !array_key_exists('.', $directories) && !array_key_exists('.', $exemptions)) {
        fwrite(STDERR, "ERROR: tracked root-level files detected; metadata entry for '.' is required\n");
        exit(1);
    }

    ksort($directories, SORT_STRING);

    return [
        'schema_version' => $decoded['schema_version'],
        'directories' => $directories,
        'exemptions' => $exemptions,
    ];
}

/**
 * @param array<int, string> $command
 * @return array{stdout: string, stderr: string, exit: int}
 */
function runCommand(array $command, string $cwd): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes, $cwd);

    if (!is_resource($process)) {
        return ['stdout' => '', 'stderr' => 'failed to start command', 'exit' => 1];
    }

    fclose($pipes[0]);
    $stdout = (string)stream_get_contents($pipes[1]);
    $stderr = (string)stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);

    return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
}

function toInt(mixed $value): int
{
    return is_numeric($value) ? (int)$value : 0;
}

function isAbsolutePath(string $path): bool
{
    if ($path === '') {
        return false;
    }

    if ($path[0] === '/' || $path[0] === '\\') {
        return true;
    }

    return preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
}

function repoStructureShouldExcludeTrackedPath(string $path): bool
{
    $path = str_replace('\\', '/', $path);

    $excludedExactPaths = [
        'docs/ai/generated/repo-structure.json' => true,
        'docs/ai/generated/repo-structure.csv' => true,
        'docs/ai/generated/repo-structure.md' => true,
        'docs/ai/generated/repo-structure.log' => true,
    ];

    if (isset($excludedExactPaths[$path])) {
        return true;
    }

    $excludedPrefixes = [
        'docs/ai/generated/',
        'packages/ai-universal-rules/examples/',
    ];

    foreach ($excludedPrefixes as $prefix) {
        if (str_starts_with($path, $prefix)) {
            return true;
        }
    }

    return false;
}
