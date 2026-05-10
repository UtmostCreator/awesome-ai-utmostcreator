<?php

declare(strict_types=1);

require_once __DIR__ . '/registry.php';

function aiAdvisorScan(string $root): array
{
    $tracked = [];
    exec('git -C ' . escapeshellarg($root) . ' ls-files', $tracked);

    $top = [];
    foreach ($tracked as $file) {
        $parts = explode('/', (string) $file);
        $top[$parts[0] !== '' ? $parts[0] : '_root'] = true;
    }

    $aiSurface = [
        'AGENTS.md',
        'CLAUDE.md',
        'llms.txt',
        '.github/copilot-instructions.md',
    ];
    $aiSurfacePresent = [];
    foreach ($aiSurface as $path) {
        $aiSurfacePresent[$path] = file_exists($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
    }

    $counts = [
        'tests_php' => 0,
        'tests_shell' => 0,
        'scripts_copilot' => 0,
        'scripts_ai' => 0,
        'tools_ai_php' => 0,
    ];
    foreach ($tracked as $file) {
        $f = (string) $file;
        if (str_starts_with($f, 'tests/php/') && str_ends_with($f, '.php')) {
            $counts['tests_php']++;
        }
        if (str_starts_with($f, 'tests/shell/') && str_ends_with($f, '.bats')) {
            $counts['tests_shell']++;
        }
        if (str_starts_with($f, 'scripts/ai/') && str_ends_with($f, '.sh')) {
            $counts['scripts_copilot']++;
        }
        if (str_starts_with($f, 'scripts/ai/') && str_ends_with($f, '.sh')) {
            $counts['scripts_ai']++;
        }
        if (str_starts_with($f, 'tools/ai/') && str_ends_with($f, '.php')) {
            $counts['tools_ai_php']++;
        }
    }

    $toolchain = [
        'git' => aiAdvisorCommandExists('git'),
        'php' => aiAdvisorCommandExists('php'),
        'jq' => aiAdvisorCommandExists('jq'),
        'rg' => aiAdvisorCommandExists('rg'),
        'repomix' => aiAdvisorCommandExists('repomix'),
        'scc' => aiAdvisorCommandExists('scc'),
    ];

    $signals = [
        'schema_version' => 1,
        'project' => basename($root),
        'tracked_files_count' => count($tracked),
        'top_level_paths' => array_keys($top),
        'counts' => $counts,
        'ai_surface' => $aiSurfacePresent,
        'toolchain' => $toolchain,
    ];

    $dir = aiAdvisorGeneratedDir($root);
    aiAdvisorWriteJson($dir . DIRECTORY_SEPARATOR . 'project-signals.json', $signals);

    $md = "# Project Signals\n\n";
    $md .= "- Project: `" . $signals['project'] . "`\n";
    $md .= "- Tracked files: `" . $signals['tracked_files_count'] . "`\n";
    $md .= "- Top-level paths: `" . implode(', ', $signals['top_level_paths']) . "`\n";
    $md .= "\n## Counts\n\n";
    foreach ($counts as $k => $v) {
        $md .= "- `{$k}`: `{$v}`\n";
    }
    $md .= "\n## Toolchain\n\n";
    foreach ($toolchain as $k => $v) {
        $md .= "- `{$k}`: `" . ($v ? 'present' : 'missing') . "`\n";
    }
    aiAdvisorWriteMarkdown($dir . DIRECTORY_SEPARATOR . 'project-signals.md', $md);

    return $signals;
}
