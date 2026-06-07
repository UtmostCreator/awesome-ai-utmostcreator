<?php

declare(strict_types=1);

/**
 * Policy compiler (Phase 7).
 *
 * Reads docs/ai/command-policy.tiers.yaml (a small, flat tiers document) and emits a
 * dependency-free POSIX sh guard with a compiled `case` table. The compiled guard needs no
 * yq, no jq, and no PHP at target runtime — PHP is required only here, at build/install time.
 *
 * Decision precedence in the compiled guard: deny > ask > allow > (default) allow-through.
 * deny exits 1; ask prints an ask decision and exits 0; allow exits 0.
 *
 * Usage:
 *   php tools/ai/compile-command-policy.php [--in=PATH] [--out=PATH] [--check]
 *
 * --check exits non-zero if the on-disk compiled output differs from a fresh compile
 * (so CI can detect drift), and writes nothing.
 */

function aiPolicyCompileMain(array $argv): int
{
    $root = realpath(__DIR__ . '/../..');
    if ($root === false) {
        fwrite(STDERR, "ERROR: cannot resolve repo root\n");
        return 1;
    }

    $in = $root . '/docs/ai/command-policy.tiers.yaml';
    $out = $root . '/.github/hooks/scripts/command-policy.compiled.sh';
    $projectValues = null;
    $check = false;
    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--in=')) {
            $in = substr($arg, 5);
        } elseif (str_starts_with($arg, '--out=')) {
            $out = substr($arg, 6);
        } elseif (str_starts_with($arg, '--project-values=')) {
            $projectValues = substr($arg, 17);
        } elseif ($arg === '--check') {
            $check = true;
        }
    }

    // Local overrides live in the TARGET repo (the one whose hooks we compile), derived from
    // the --out location (.../<targetRoot>/.github/hooks/scripts/...). Falls back to the kit
    // root only when --out is the kit's own path. An explicit --project-values wins.
    if ($projectValues === null) {
        $outDir = dirname($out);
        // .github/hooks/scripts -> up 3 == target repo root
        $targetRoot = dirname($outDir, 3);
        $projectValues = $targetRoot . '/.ai/project.yml';
    }

    if (!is_file($in)) {
        fwrite(STDERR, "ERROR: tiers file not found: {$in}\n");
        return 1;
    }

    $tiers = aiPolicyParseTiersYaml((string) file_get_contents($in));

    // Minimal local overrides: .ai/project.yml may add policy.allow[] entries. These can only
    // widen allow; they can never downgrade a global deny or a tier>=3 (ask) command, and
    // wildcards are rejected. Invalid local allows fail the compile loudly.
    if (is_file($projectValues)) {
        $localAllows = aiPolicyParseLocalAllows((string) file_get_contents($projectValues));
        $violations = aiPolicyValidateLocalAllows($localAllows, $tiers);
        if ($violations !== []) {
            fwrite(STDERR, "ERROR: invalid local policy overrides in .ai/project.yml:\n - " . implode("\n - ", $violations) . "\n");
            return 1;
        }
        $tiers['allow'] = array_values(array_unique(array_merge($tiers['allow'], $localAllows)));
    }

    $compiled = aiPolicyRenderCompiledSh($tiers);

    if ($check) {
        $existing = is_file($out) ? (string) file_get_contents($out) : '';
        if ($existing !== $compiled) {
            fwrite(STDERR, "ERROR: compiled command policy is out of date. Run: php tools/ai/compile-command-policy.php\n");
            return 1;
        }
        fwrite(STDOUT, "OK: compiled command policy up to date\n");
        return 0;
    }

    if (!is_dir(dirname($out))) {
        mkdir(dirname($out), 0755, true);
    }
    file_put_contents($out, $compiled);
    @chmod($out, 0755);
    fwrite(STDOUT, "OK: wrote {$out}\n");

    return 0;
}

/**
 * Minimal parser for the flat tiers YAML used by command-policy.tiers.yaml.
 * Supports the exact shape: tiers: -> tierN: -> {allow,ask,deny}: -> list of "- pattern".
 *
 * @return array{allow:list<string>,ask:list<string>,deny:list<string>}
 */
function aiPolicyParseTiersYaml(string $yaml): array
{
    $allow = [];
    $ask = [];
    $deny = [];
    $bucket = null;

    foreach (preg_split('/\R/', $yaml) ?: [] as $line) {
        if ($line === '' || ltrim($line)[0] === '#') {
            continue;
        }
        $trimmed = trim($line);

        if (preg_match('/^(allow|ask|deny):\s*(\[\])?\s*$/', $trimmed, $m) === 1) {
            $bucket = $m[1];
            continue;
        }
        // A new tier or the tiers root resets the active bucket.
        if (preg_match('/^tier\d+:\s*$/', $trimmed) === 1 || $trimmed === 'tiers:') {
            $bucket = null;
            continue;
        }

        if ($bucket !== null && str_starts_with($trimmed, '- ')) {
            $value = trim(substr($trimmed, 2));
            $value = trim($value, "\"'");
            if ($value === '') {
                continue;
            }
            if ($bucket === 'allow') {
                $allow[] = $value;
            } elseif ($bucket === 'ask') {
                $ask[] = $value;
            } elseif ($bucket === 'deny') {
                $deny[] = $value;
            }
        }
    }

    return [
        'allow' => array_values(array_unique($allow)),
        'ask' => array_values(array_unique($ask)),
        'deny' => array_values(array_unique($deny)),
    ];
}

/**
 * Translate a policy glob pattern ("git status*", "rm *") into a sh `case` glob.
 *
 * Only `*` is a wildcard; every other character (including spaces and shell glob
 * metacharacters) must match literally. POSIX `case` patterns are a single word, so a literal
 * space would be a syntax error — instead we double-quote each literal run and leave `*`
 * unquoted between runs, producing e.g. `"rm "*` and `"git status"*`.
 */
function aiPolicyPatternToCaseGlob(string $pattern): string
{
    $segments = explode('*', $pattern);
    $parts = [];
    foreach ($segments as $index => $literal) {
        if ($literal !== '') {
            // Double-quote the literal run so spaces and glob metachars match verbatim.
            $parts[] = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $literal) . '"';
        }
        if ($index < count($segments) - 1) {
            $parts[] = '*';
        }
    }

    if ($parts === []) {
        return '*';
    }

    return implode('', $parts);
}

/**
 * @param array{allow:list<string>,ask:list<string>,deny:list<string>} $tiers
 */
function aiPolicyRenderCompiledSh(array $tiers): string
{
    $denyCases = aiPolicyRenderCaseBranches($tiers['deny'], 'deny');
    $askCases = aiPolicyRenderCaseBranches($tiers['ask'], 'ask');
    $allowCases = aiPolicyRenderCaseBranches($tiers['allow'], 'allow');

    $header = <<<'SH'
#!/usr/bin/env sh
# GENERATED by tools/ai/compile-command-policy.php from docs/ai/command-policy.tiers.yaml.
# DO NOT EDIT BY HAND. Dependency-free: POSIX sh only (no yq, no jq, no PHP at runtime).
#
# Reads a command string on stdin (already compacted by the caller) and prints a decision:
#   deny:<reason>   and exits 1
#   ask:<reason>    and exits 0
#   allow           and exits 0
# Precedence: deny > ask > allow > default allow-through.
set -u

cmd="$(cat)"
cmd="$(printf '%s' "$cmd" | tr -s '[:space:]' ' ')"
cmd="${cmd# }"
cmd="${cmd% }"

SH;

    $body = "case \"\$cmd\" in\n"
        . $denyCases
        . $askCases
        . $allowCases
        . "    *)\n        printf 'allow\\n'\n        exit 0\n        ;;\n"
        . "esac\n";

    return $header . "\n" . $body;
}

/**
 * @param list<string> $patterns
 */
function aiPolicyRenderCaseBranches(array $patterns, string $decision): string
{
    if ($patterns === []) {
        return '';
    }

    $out = '';
    foreach ($patterns as $pattern) {
        $glob = aiPolicyPatternToCaseGlob($pattern);
        $out .= '    ' . $glob . ")\n";
        if ($decision === 'deny') {
            $reason = 'denied by tiered command policy: ' . $pattern;
            $out .= "        printf 'deny:%s\\n' " . aiPolicyShSingleQuote($reason) . "\n";
            $out .= "        exit 1\n";
        } elseif ($decision === 'ask') {
            $reason = 'confirm required by tiered command policy: ' . $pattern;
            $out .= "        printf 'ask:%s\\n' " . aiPolicyShSingleQuote($reason) . "\n";
            $out .= "        exit 0\n";
        } else {
            $out .= "        printf 'allow\\n'\n";
            $out .= "        exit 0\n";
        }
        $out .= "        ;;\n";
    }

    return $out;
}

function aiPolicyShSingleQuote(string $value): string
{
    return "'" . str_replace("'", "'\\''", $value) . "'";
}

/**
 * Parse a `policy:`/`  allow:` block of simple `- "pattern"` entries from .ai/project.yml.
 * Only entries under the top-level `policy:` -> `allow:` key are considered.
 *
 * @return list<string>
 */
function aiPolicyParseLocalAllows(string $yaml): array
{
    $allows = [];
    $inPolicy = false;
    $inAllow = false;

    foreach (preg_split('/\R/', $yaml) ?: [] as $line) {
        if ($line === '' || ltrim($line)[0] === '#') {
            continue;
        }
        $indent = strlen($line) - strlen(ltrim($line));
        $trimmed = trim($line);

        if ($indent === 0) {
            // Any top-level key other than the policy block ends policy scope.
            $inPolicy = ($trimmed === 'policy:');
            $inAllow = false;
            continue;
        }
        if (!$inPolicy) {
            continue;
        }
        if (preg_match('/^allow:\s*(\[\])?\s*$/', $trimmed) === 1) {
            $inAllow = true;
            continue;
        }
        // A sibling key under policy ends the allow list.
        if ($inAllow && !str_starts_with($trimmed, '- ') && str_ends_with($trimmed, ':')) {
            $inAllow = false;
            continue;
        }
        if ($inAllow && str_starts_with($trimmed, '- ')) {
            $value = trim(trim(substr($trimmed, 2)), "\"'");
            if ($value !== '') {
                $allows[] = $value;
            }
        }
    }

    return array_values(array_unique($allows));
}

/**
 * Enforce the local-override safety contract:
 *  - no wildcards (`*`) in a local allow (prevents broad self-grants),
 *  - a local allow must not match (or be matched by) any global deny pattern,
 *  - a local allow must not equal a tier>=3 (ask) command (no silent privilege downgrade).
 *
 * @param list<string> $localAllows
 * @param array{allow:list<string>,ask:list<string>,deny:list<string>} $tiers
 * @return list<string> human-readable violation messages (empty == valid)
 */
function aiPolicyValidateLocalAllows(array $localAllows, array $tiers): array
{
    $violations = [];
    foreach ($localAllows as $allow) {
        if (str_contains($allow, '*')) {
            $violations[] = "wildcard not permitted in local allow: '{$allow}'";
            continue;
        }
        foreach ($tiers['deny'] as $deny) {
            if (aiPolicyPatternsOverlap($allow, $deny)) {
                $violations[] = "local allow '{$allow}' would downgrade global deny '{$deny}'";
                break;
            }
        }
        foreach ($tiers['ask'] as $ask) {
            if (aiPolicyPatternsOverlap($allow, $ask)) {
                $violations[] = "local allow '{$allow}' would downgrade tier>=3 confirm '{$ask}'";
                break;
            }
        }
    }

    return $violations;
}

/**
 * True if a concrete local allow command would be captured by a (possibly globbed) policy
 * pattern, or vice versa. Used to detect downgrade attempts. `*` in the policy pattern is a
 * trailing/embedded wildcard; matching is done with fnmatch in both directions.
 */
function aiPolicyPatternsOverlap(string $localAllow, string $policyPattern): bool
{
    if ($localAllow === $policyPattern) {
        return true;
    }
    // The policy pattern may contain globs; does it capture the concrete local allow?
    if (fnmatch($policyPattern, $localAllow)) {
        return true;
    }
    // Defense in depth: would the local allow (treated literally) be a prefix of the policy's
    // non-glob stem? e.g. local "git reset" vs deny "git reset --hard *".
    $stem = rtrim((string) strstr($policyPattern, '*', true) ?: $policyPattern);
    if ($stem !== '' && str_starts_with($localAllow, $stem)) {
        return true;
    }

    return false;
}

if (PHP_SAPI === 'cli' && isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit(aiPolicyCompileMain($argv));
}
