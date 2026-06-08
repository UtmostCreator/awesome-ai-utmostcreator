<?php

declare(strict_types=1);

/**
 * validate-mentor-parity.php
 *
 * Enforces the mentor-mode single-source-of-truth invariant so policy numbers
 * cannot drift across files the way help-fading ceilings did in earlier designs.
 *
 *   1. The canonical and packaged config.example.json must normalize identically,
 *      so the two committed copies cannot diverge.
 *   2. No mentor-mode markdown file (except reference.md, which holds research
 *      citations) may restate a tunable policy number from config.example.json.
 *      Rung identifiers (L0-L5) and markdown ordered-list markers are allowed.
 *
 * Usage:
 *   php tools/ai/validate-mentor-parity.php             # scan the repo
 *   php tools/ai/validate-mentor-parity.php --self-test # prove the detector works
 *
 * Exit codes: 0 = ok, 1 = violations found, 2 = usage or IO error.
 */

const MENTOR_CAP_DIRS = [
    'docs/ai/capabilities/mentor-mode',
    'packages/ai-universal-rules/templates/capabilities/mentor-mode',
];

function mentor_repo_root(): string
{
    return dirname(__DIR__, 2);
}

function mentor_fail(string $msg): void
{
    fwrite(STDERR, "ERROR: {$msg}\n");
}

/** Collect every integer value present in the decoded config (by magnitude). */
function mentor_policy_numbers(array $config): array
{
    $nums = [];
    array_walk_recursive($config, static function ($v) use (&$nums): void {
        if (is_int($v)) {
            $nums[abs($v)] = true;
        }
    });

    return array_keys($nums);
}

/** Find policy-number leaks in a markdown body. Returns array of [line, value, text]. */
function mentor_find_leaks(string $body, array $policy): array
{
    $leaks = [];
    $policySet = array_flip($policy);
    $lines = preg_split('/\R/', $body) ?: [];
    foreach ($lines as $i => $line) {
        // Strip a leading markdown ordered-list marker: "1." or "1)".
        $stripped = preg_replace('/^\s*\d+[.)]\s/', '', $line);
        // Remove rung identifiers like L0..L5 (an L followed by digits).
        $stripped = preg_replace('/\bL\d+\b/', '', (string) $stripped);
        if (preg_match_all('/\b\d+\b/', (string) $stripped, $m)) {
            foreach ($m[0] as $tok) {
                if (isset($policySet[(int) $tok])) {
                    $leaks[] = [$i + 1, (int) $tok, trim($line)];
                }
            }
        }
    }

    return $leaks;
}

function mentor_load_config(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($path), true);

    return is_array($data) ? $data : null;
}

function mentor_normalize(array $data): string
{
    $sort = static function (&$v) use (&$sort): void {
        if (is_array($v)) {
            $isList = array_keys($v) === range(0, count($v) - 1);
            if (!$isList && $v !== []) {
                ksort($v);
            }
            foreach ($v as &$child) {
                $sort($child);
            }
        }
    };
    $sort($data);

    return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function mentor_scan(string $root): int
{
    $violations = 0;
    $configs = [];
    $present = 0;

    foreach (MENTOR_CAP_DIRS as $rel) {
        $dir = $root . '/' . $rel;
        if (!is_dir($dir)) {
            // Not all surfaces carry every copy: an installed target has the
            // canonical docs/ai capability but not the package template mirror.
            // Skip absent copies; only the cross-copy drift check below needs both.
            continue;
        }
        $present++;
        $cfg = mentor_load_config("{$dir}/config.example.json");
        if ($cfg === null) {
            mentor_fail("missing or invalid config.example.json in {$rel}");
            $violations++;
            continue;
        }
        $configs[$rel] = $cfg;
        $policy = mentor_policy_numbers($cfg);
        foreach (glob("{$dir}/*.md") ?: [] as $md) {
            if (basename($md) === 'reference.md') {
                continue; // research citations legitimately contain numbers
            }
            foreach (mentor_find_leaks((string) file_get_contents($md), $policy) as [$ln, $val, $text]) {
                $shown = str_replace($root . '/', '', str_replace('\\', '/', $md));
                mentor_fail(sprintf(
                    '%s:%d restates policy number %d (keep numbers in config.example.json): %s',
                    $shown,
                    $ln,
                    $val,
                    $text
                ));
                $violations++;
            }
        }
    }

    if ($present === 0) {
        // No mentor-mode capability is installed on this surface; nothing to check.
        fwrite(STDOUT, "SKIP: no mentor-mode capability directory present\n");

        return 0;
    }

    if (count($configs) > 1) {
        $norms = array_map('mentor_normalize', $configs);
        $first = reset($norms);
        foreach ($norms as $rel => $n) {
            if ($n !== $first) {
                mentor_fail("config.example.json drift between mentor-mode copies (see {$rel})");
                $violations++;
            }
        }
    }

    return $violations;
}

function mentor_self_test(): int
{
    $policy = [0, 1, 2, 3, 4, 5];
    $clean = "The learn ceiling is configured in config.example.json.\n- drop to L2 when unsure\n1. first step";
    $dirty = 'The learn ceiling is 4 in this doc.';

    if (mentor_find_leaks($clean, $policy) !== []) {
        mentor_fail('self-test: false positive on clean text');

        return 1;
    }
    if (mentor_find_leaks($dirty, $policy) === []) {
        mentor_fail('self-test: failed to detect planted duplicate number');

        return 1;
    }
    fwrite(STDOUT, "self-test OK: detector flags planted duplicates and passes clean text\n");

    return 0;
}

$arg = $argv[1] ?? '';
if ($arg === '--self-test') {
    exit(mentor_self_test());
}
if ($arg !== '' && $arg !== '--scan') {
    fwrite(STDERR, "usage: validate-mentor-parity.php [--scan|--self-test]\n");
    exit(2);
}

$violations = mentor_scan(mentor_repo_root());
if ($violations > 0) {
    fwrite(STDERR, "FAIL: {$violations} mentor-mode parity violation(s)\n");
    exit(1);
}
fwrite(STDOUT, "OK: mentor-mode policy numbers live only in config.example.json; copies are in sync\n");
exit(0);
