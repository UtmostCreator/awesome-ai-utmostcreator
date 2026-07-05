<?php

declare(strict_types=1);

require_once __DIR__ . '/stack-registry.php';

/**
 * @param array<string,array<string,mixed>> $registry
 * @param array<string,mixed> $options
 * @return array<string,array{id:string,confidence:int,signals:list<string>,implied_by?:string,warnings?:list<string>}>
 */
function aiStackDetect(string $targetRoot, array $registry, array $options = []): array
{
    $root = aiStackNormalizeRoot($targetRoot);
    $detected = [];

    foreach ($registry as $id => $descriptor) {
        $signals = [];
        $confidence = 0;
        $detection = is_array($descriptor['detection'] ?? null) ? $descriptor['detection'] : [];
        $weights = is_array($detection['confidence'] ?? null) ? $detection['confidence'] : [];

        foreach (aiStackListStrings($detection['files'] ?? []) as $file) {
            if (is_file($root . '/' . $file)) {
                $signals[] = $file;
                $confidence = max($confidence, (int) ($weights[$file] ?? 70));
            }
        }

        foreach (aiStackListStrings($detection['globs'] ?? []) as $glob) {
            if (aiStackGlobAny($root, $glob)) {
                $signals[] = $glob;
                $confidence = max($confidence, (int) ($weights[$glob] ?? 50));
            }
        }

        if ($signals !== []) {
            $detected[(string) $id] = [
                'id' => (string) $id,
                'confidence' => min(100, $confidence),
                'signals' => array_values(array_unique($signals)),
            ];
        }
    }

    if (($options['use_scc'] ?? false) === true && !aiStackFindExecutable('scc')) {
        foreach ($detected as &$entry) {
            $entry['warnings'][] = 'scc unavailable; skipped optional language detection';
        }
        unset($entry);
    }

    aiStackApplyImpliedStacks($detected, $registry);

    ksort($detected);
    return $detected;
}

/**
 * @param array<string,array<string,mixed>> $registry
 * @param list<string> $selected
 * @param list<string> $disabled
 * @return list<string>
 */
function aiStackMergeSelections(array $detected, array $selected, array $disabled): array
{
    $out = array_fill_keys(array_keys($detected), true);
    foreach ($selected as $id) {
        $out[(string) $id] = true;
    }
    foreach ($disabled as $id) {
        unset($out[(string) $id]);
    }

    $ids = array_keys($out);
    sort($ids);
    return $ids;
}

/**
 * @param list<array<string,mixed>> $selectedStacks
 * @return array<string,array{id:string,tool:string,available:bool,output:string,error:string,required:bool}>
 */
function aiStackRunVersionChecks(string $targetRoot, array $selectedStacks): array
{
    $results = [];
    foreach ($selectedStacks as $stack) {
        foreach (($stack['version_checks'] ?? []) as $check) {
            if (!is_array($check)) {
                continue;
            }
            aiStackValidateVersionCheck($check);
            $id = (string) $check['id'];
            $tool = (string) $check['tool'];
            $executable = aiStackFindExecutable($tool);
            if ($executable === null) {
                $results[$id] = ['id' => $id, 'tool' => $tool, 'available' => false, 'output' => '', 'error' => 'tool not found', 'required' => (bool) $check['required']];
                continue;
            }
            $results[$id] = aiStackRunVersionCommand($targetRoot, $id, $tool, $executable, array_values(array_map('strval', $check['args'])), (bool) $check['required']);
        }
    }

    ksort($results);
    return $results;
}

/** @return array{id:string,tool:string,available:bool,output:string,error:string,required:bool} */
function aiStackRunVersionCommand(string $targetRoot, string $id, string $tool, string $executable, array $args, bool $required): array
{
    $cmd = array_merge([$executable], $args);
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = @proc_open($cmd, $descriptors, $pipes, $targetRoot, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        return ['id' => $id, 'tool' => $tool, 'available' => false, 'output' => '', 'error' => 'could not start version check', 'required' => $required];
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);

    return ['id' => $id, 'tool' => $tool, 'available' => $exit === 0, 'output' => trim((string) $output), 'error' => trim((string) $error), 'required' => $required];
}

function aiStackFindExecutable(string $tool): ?string
{
    $path = getenv('PATH') ?: '';
    foreach (explode(PATH_SEPARATOR, $path) as $dir) {
        if ($dir === '') {
            continue;
        }
        $candidate = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $tool;
        if (is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
    }

    return null;
}

/** @param mixed $value @return list<string> */
function aiStackListStrings(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }

    return array_values(array_filter(array_map('strval', $value), static fn (string $v): bool => $v !== ''));
}

function aiStackGlobAny(string $root, string $pattern): bool
{
    if (str_contains($pattern, '**')) {
        return aiStackRecursiveGlobAny($root, $pattern);
    }

    return (glob($root . '/' . $pattern) ?: []) !== [];
}

function aiStackRecursiveGlobAny(string $root, string $pattern): bool
{
    $parts = explode('/**/', $pattern, 2);
    if (count($parts) !== 2) {
        return false;
    }
    [$base, $tail] = $parts;
    $baseDir = rtrim($root . '/' . $base, '/');
    if (!is_dir($baseDir)) {
        return false;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen(str_replace('\\', '/', $baseDir)) + 1);
        if (fnmatch($tail, $relative)) {
            return true;
        }
    }

    return false;
}

/** @param array<string,array{id:string,confidence:int,signals:list<string>,implied_by?:string,warnings?:list<string>}> $detected @param array<string,array<string,mixed>> $registry */
function aiStackApplyImpliedStacks(array &$detected, array $registry): void
{
    foreach ($detected as $id => $entry) {
        foreach (aiStackListStrings($registry[$id]['implies'] ?? []) as $implied) {
            if (!isset($registry[$implied]) || isset($detected[$implied])) {
                continue;
            }
            $detected[$implied] = ['id' => $implied, 'confidence' => 25, 'signals' => ['implied:' . $id], 'implied_by' => $id];
        }
    }
}
