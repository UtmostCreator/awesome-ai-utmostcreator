<?php

declare(strict_types=1);

/**
 * Parses the canonical OpenCode agent frontmatter shared by every runtime-specific agent
 * renderer (Copilot, Claude, ...). Extracted so the parse logic is not duplicated per renderer;
 * runtime-specific rendering (frontmatter shape, tool mapping, body sections) stays in each
 * renderer file.
 *
 * @return array{rawFm: string, body: string, frontMatter: array<string,string>, allowedBash: string[]}
 */
function aiInstallerParseCanonicalAgentFrontmatter(string $srcContent): array
{
    $frontMatter = [];
    $body = $srcContent;
    $rawFm = '';
    $allowedBash = [];

    if (preg_match('/^---\R(.*?)\R---\R?/s', $srcContent, $fm)) {
        $rawFm = $fm[1];
        $body = substr($srcContent, strlen($fm[0]));

        // Parse simple key: value lines (not nested YAML — we only need top-level scalars)
        foreach (explode("\n", $rawFm) as $line) {
            if (preg_match('/^(\w[\w-]*):\s+(.+)$/', trim($line), $m)) {
                $frontMatter[$m[1]] = trim($m[2]);
            }
        }

        // Collect allowed bash commands for the policy section
        if (preg_match('/bash:\s*\R((?:\s+.+\R)*)/s', $rawFm, $bashMatch)) {
            foreach (explode("\n", $bashMatch[1]) as $bashLine) {
                if (preg_match("/^\\s+'([^']+)':\\s*allow/", $bashLine, $bm)) {
                    $cmd = $bm[1];
                    if ($cmd !== '*') {
                        $allowedBash[] = $cmd;
                    }
                }
            }
        }
    }

    return ['rawFm' => $rawFm, 'body' => $body, 'frontMatter' => $frontMatter, 'allowedBash' => $allowedBash];
}
