<?php

declare(strict_types=1);

require_once __DIR__ . '/registry.php';
require_once __DIR__ . '/SecretScanGate.php';

function aiAdvisorDefaultIncludePrefixes(): array
{
    return [
        'AGENTS.md',
        'CLAUDE.md',
        'llms.txt',
        'tools/ai/',
        'scripts/ai/',
        'scripts/ai/',
        'tests/',
        '.github/workflows/',
        'packages/ai-universal-rules/manifest.',
        'packages/ai-universal-rules/catalog.json',
        'docs/ai/installer-architecture.md',
        'docs/ai/toolchain-requirements.md',
        'docs/ai/scripts-reference.md',
    ];
}

function aiAdvisorDefaultExcludeRegex(): array
{
    return [
        '#^vendor/#', '#^node_modules/#', '#^\.git/#', '#^dist/#', '#^build/#', '#^coverage/#',
        '#^docs/ai/generated/logs/#', '#\.log$#', '#\.(png|jpg|jpeg|gif|webp|pdf|zip|tar|gz)$#i',
        '#^\.env(\..+)?$#', '#\.pem$#i', '#\.key$#i', '#id_rsa$#', '#id_ed25519$#',
    ];
}

function aiAdvisorPackContext(string $root): array
{
    aiAdvisorRequireCleanSecretScan($root);

    $tracked = [];
    exec('git -C ' . escapeshellarg($root) . ' ls-files', $tracked);
    $includePrefixes = aiAdvisorDefaultIncludePrefixes();
    $excludeRegex = aiAdvisorDefaultExcludeRegex();

    $selected = [];
    foreach ($tracked as $rel) {
        $rel = (string) $rel;
        $include = false;
        foreach ($includePrefixes as $prefix) {
            if (str_ends_with($prefix, '.md') || str_ends_with($prefix, '.json') || str_ends_with($prefix, '.txt') || str_ends_with($prefix, '.')) {
                if (str_starts_with($rel, $prefix) || $rel === $prefix) {
                    $include = true;
                    break;
                }
            } elseif (str_starts_with($rel, $prefix)) {
                $include = true;
                break;
            }
        }
        if (!$include) {
            continue;
        }
        $excluded = false;
        foreach ($excludeRegex as $pattern) {
            if (preg_match($pattern, $rel) === 1) {
                $excluded = true;
                break;
            }
        }
        if ($excluded) {
            continue;
        }
        $selected[] = $rel;
    }

    sort($selected);
    $content = "# Advisor Context\n\n";
    foreach ($selected as $rel) {
        $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($abs)) {
            continue;
        }
        $text = (string) file_get_contents($abs);
        $content .= "## FILE: {$rel}\n\n```text\n" . $text . "\n```\n\n";
    }

    $dir = aiAdvisorGeneratedDir($root);
    aiAdvisorWriteMarkdown($dir . DIRECTORY_SEPARATOR . 'advisor-context.md', $content);
    $index = "# Advisor Context Index\n\n";
    foreach ($selected as $rel) {
        $index .= "- `{$rel}`\n";
    }
    aiAdvisorWriteMarkdown($dir . DIRECTORY_SEPARATOR . 'advisor-context.index.md', $index);

    return ['files' => $selected, 'context_path' => 'docs/ai/generated/advisor-context.md', 'index_path' => 'docs/ai/generated/advisor-context.index.md'];
}
