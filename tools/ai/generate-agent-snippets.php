<?php

declare(strict_types=1);

/**
 * Keep the shared CLI-tool permission block in agent files in sync with a
 * single source-of-truth snippet, without introducing install-time templating.
 *
 * Source of truth:
 *   packages/ai-universal-rules/templates/snippets/agent-tools-readonly.snippet.md
 *   packages/ai-universal-rules/templates/snippets/agent-tools-execute.snippet.md
 *
 * Each managed agent file contains a delimited block:
 *   # --- shipped CLI tool access (shared snippet: <name>) ---
 *   ...lines...
 *   # --- repomix freshness check ---
 *   'bash scripts/ai/repomix-freshness.sh *': allow
 *
 * The block is byte-identical to the chosen snippet. This script can:
 *   --check  : exit non-zero if any managed file's block differs from its snippet
 *   --write  : rewrite each managed file's block from its snippet
 *
 * Shipped files never contain unexpanded markers; the delimiters are valid
 * YAML comments inside the permission.bash map.
 */

$root = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
if ($root === false) {
    fwrite(STDERR, "ERROR: could not resolve repository root\n");
    exit(1);
}

$check = in_array('--check', $argv, true);
$write = in_array('--write', $argv, true);
if (!$check && !$write) {
    $check = true;
}

$snippetDirs = [
    $root . '/packages/ai-universal-rules/templates/snippets',
    $root . '/docs/ai/snippets',
];
$readonly = false;
$execute = false;
foreach ($snippetDirs as $snippetDir) {
    $readonlyCandidate = @file_get_contents($snippetDir . '/agent-tools-readonly.snippet.md');
    $executeCandidate = @file_get_contents($snippetDir . '/agent-tools-execute.snippet.md');
    if ($readonlyCandidate !== false && $executeCandidate !== false) {
        $readonly = $readonlyCandidate;
        $execute = $executeCandidate;
        break;
    }
}
if ($readonly === false || $execute === false) {
    fwrite(STDERR, "ERROR: missing agent tool snippet source\n");
    exit(1);
}
$readonly = rtrim($readonly, "\r\n") . "\n";
$execute = rtrim($execute, "\r\n") . "\n";

// Agent -> snippet kind. Read-only agents get the readonly block; agents that
// already carry execute/verify shell access get the execute block.
// repository-researcher / repository-reviewer are ask-default and intentionally
// excluded from the shared block.
$kind = [
    'architect' => 'readonly',
    'researcher' => 'readonly',
    'release-auditor' => 'readonly',
    'workflow-auditor' => 'readonly',
    'reviewer' => 'execute',
    'config-maintainer' => 'execute',
    'implementer' => 'execute',
    'refactorer' => 'execute',
    'bootstrapper' => 'execute',
    // post-install historically carries the read-only CLI tool block (shellcheck,
    // no semgrep/packers); keep that exact posture to avoid policy drift.
    'post-install' => 'readonly',
];

$dirs = [
    $root . '/packages/ai-universal-rules/templates/core/agents',
    $root . '/.opencode/agents',
];

$startRe = '/^\s*#\s*---\s*shipped CLI tool access(?: \(shared snippet: [a-z-]+\))?\s*---\s*$/m';

$drift = [];
$rewritten = [];
$missingBlock = [];

foreach ($dirs as $dir) {
    foreach (glob($dir . '/*.md') ?: [] as $file) {
        $name = basename($file, '.md');
        if (!isset($kind[$name])) {
            continue; // ask-default or unmanaged agent
        }
        $snippet = $kind[$name] === 'execute' ? $execute : $readonly;

        $raw = file_get_contents($file);
        if ($raw === false) {
            continue;
        }

        // Locate the existing block: from the start marker, consume the
        // contiguous run of block lines (indented permission entries and
        // '# ---' comment lines). Stop at the first line that is not part of
        // the block (e.g. the closing '---' or an unindented/blank line).
        if (preg_match($startRe, $raw, $m, PREG_OFFSET_CAPTURE) !== 1) {
            $missingBlock[] = relPath($root, $file);
            continue;
        }
        $blockStart = $m[0][1];

        // Walk lines from the marker forward.
        $cursor = $blockStart;
        $len = strlen($raw);
        $blockEnd = $blockStart;
        $firstLine = true;
        while ($cursor < $len) {
            $nl = strpos($raw, "\n", $cursor);
            $lineEnd = $nl === false ? $len : $nl;
            $line = substr($raw, $cursor, $lineEnd - $cursor);
            $isMarker = (bool) preg_match('/^\s*#\s*---/', $line);
            $isEntry = (bool) preg_match('/^\s+[\'"][^\'"]+[\'"]\s*:\s*(?:allow|ask|deny)\s*$/', $line);
            if ($firstLine) {
                // first line is the start marker itself
                $firstLine = false;
            } elseif (!$isMarker && !$isEntry) {
                break; // left the block
            }
            $blockEnd = $lineEnd;
            if ($nl === false) {
                break;
            }
            $cursor = $nl + 1;
        }
        // include the trailing newline of the last block line if present
        if (($raw[$blockEnd] ?? '') === "\n") {
            $blockEnd++;
        }

        $currentBlock = substr($raw, $blockStart, $blockEnd - $blockStart);
        $desiredBlock = $snippet; // already newline-terminated

        // Normalize: compare without leading indentation differences are not
        // expected (snippet carries 4-space indent). Compare exactly.
        if (rtrim($currentBlock, "\n") === rtrim($desiredBlock, "\n")) {
            continue; // in sync
        }

        if ($write) {
            $newRaw = substr($raw, 0, $blockStart) . $desiredBlock . substr($raw, $blockEnd);
            if (file_put_contents($file, $newRaw) === false) {
                fwrite(STDERR, 'ERROR: failed to write ' . relPath($root, $file) . "\n");
                exit(1);
            }
            $rewritten[] = relPath($root, $file);
        } else {
            $drift[] = relPath($root, $file);
        }
    }
}

if ($missingBlock !== []) {
    fwrite(STDERR, "ERROR: managed agents missing the shared tool block:\n");
    foreach ($missingBlock as $p) {
        fwrite(STDERR, "  - {$p}\n");
    }
    exit(1);
}

if ($write) {
    if ($rewritten === []) {
        echo "OK: agent tool snippets already in sync\n";
    } else {
        echo 'OK: rewrote shared tool block in ' . count($rewritten) . " agent file(s)\n";
        foreach ($rewritten as $p) {
            echo "  - {$p}\n";
        }
    }
    exit(0);
}

if ($drift !== []) {
    fwrite(STDERR, "ERROR: agent tool block drift detected. Run: php tools/ai/generate-agent-snippets.php --write\n");
    foreach ($drift as $p) {
        fwrite(STDERR, "  - {$p}\n");
    }
    exit(1);
}

echo "OK: agent tool snippets in sync\n";
exit(0);

function relPath(string $root, string $path): string
{
    return ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
}
