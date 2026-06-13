<?php

declare(strict_types=1);

/**
 * P4: extract concrete command invocations with a coarse risk classification.
 *
 * Each finding records the 1-based line, the command name, a short argv hint
 * (the rest of the line, trimmed), a risk class, and an effect tag. Detection
 * is deliberately conservative and command-position aware to avoid matching
 * literals inside strings. Covers destructive filesystem mutations, git
 * mutations, installer/network mutations, and dynamic execution.
 *
 * @param array<int,string> $codeLines
 * @return array<int,array<string,mixed>>
 */
function shIntrospectExtractCommands(array $codeLines): array
{
    // De-duplicate by name+effect+risk, keeping the FIRST occurrence (and its
    // line/argv_hint). This keeps the list compact and stable: read commands
    // that recur on many lines collapse to one entry, and each distinct
    // mutating command class is reported once with its first site.
    $seen = [];
    $out = [];
    foreach ($codeLines as $idx => $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        // Classify the call SITE so dependency probes and tool-name loops are
        // not reported as real command executions (e.g. `for tool in ... rg ...`
        // or `command_exists ast-grep`). `kind` is `invocation` for a genuine
        // execution and `dependency-check` for a probe/loop; `source` records
        // the detected context.
        $context = shIntrospectCommandContext($line);

        $found = shIntrospectClassifyCommandLine($line);
        foreach ($found as $f) {
            $dedupKey = $f['name'] . "\0" . $f['effect'] . "\0" . $f['risk'] . "\0" . $context['kind'];
            if (isset($seen[$dedupKey])) {
                continue;
            }
            $seen[$dedupKey] = true;
            $out[] = [
                'name' => $f['name'],
                'line' => $idx + 1,
                'argv_hint' => shIntrospectArgvHint($trimmed),
                'risk' => $f['risk'],
                'effect' => $f['effect'],
                'source' => $context['source'],
                'kind' => $context['kind'],
            ];
        }
    }

    return $out;
}

/**
 * Classify the SITE of a command line so dependency probes and tool-name loops
 * are distinguished from real executions:
 *
 *   - `for tool in jq git rg ast-grep; do`  -> tool-loop / dependency-check
 *   - `command_exists ast-grep || ...`      -> command-check / dependency-check
 *   - `command -v rg >/dev/null`            -> command-check / dependency-check
 *   - anything else                          -> command-position / invocation
 *
 * The tool name appearing inside these contexts is a check, not a run, so the
 * caller can tag the finding as `kind: dependency-check`.
 *
 * @return array{source:string,kind:string}
 */
function shIntrospectCommandContext(string $line): array
{
    // `for VAR in ...; do` — a tool-name iteration list, not an execution.
    if (preg_match('/\bfor\s+\w+\s+in\b.*\bdo\b/', $line)) {
        return ['source' => 'tool-loop', 'kind' => 'dependency-check'];
    }
    // Availability probes: command_exists/require_tool/need_tool/have X, or
    // `command -v X` / `type X` / `hash X`.
    if (preg_match('/\b(?:command_exists|require_tool|need_tool|have)\b/', $line)
        || preg_match('/\bcommand\s+-v\b/', $line)
        || preg_match('/\b(?:type|hash)\s+-?\w/', $line)
    ) {
        return ['source' => 'command-check', 'kind' => 'dependency-check'];
    }
    return ['source' => 'command-position', 'kind' => 'invocation'];
}

/**
 * Classify a single line into zero or more dangerous command findings.
 *
 * @return array<int,array{name:string,risk:string,effect:string}>
 */
function shIntrospectClassifyCommandLine(string $line): array
{
    $found = [];

    // Dynamic execution (critical).
    if (preg_match('/(?:^|[;&|]|\b(?:then|else|do)\s)\s*eval\s+[^(]/', $line)) {
        $found[] = ['name' => 'eval', 'risk' => 'critical', 'effect' => 'dynamic-execution'];
    }
    if (preg_match('/(?:^\s*|[;&|(]\s*|\bxargs\s+)(?:bash|sh)\s+-c\b/', $line)) {
        $found[] = ['name' => 'sh -c', 'risk' => 'critical', 'effect' => 'dynamic-execution'];
    }

    // Dynamic source: `source X` / `. X` where X is a variable / command
    // substitution. Sourcing `$0`/${BASH_SOURCE} re-enters the running script
    // and is treated as critical (self dynamic-execution); other dynamic source
    // targets are high (they execute arbitrary external file contents).
    if (preg_match('/(?:^\s*|[;&|]\s*|\b(?:then|else|do)\s+)(?:source|\.)\s+(["\']?)([^"\';\s]+)\1/', $line, $sm)) {
        $target = $sm[2];
        $isDynamic = (bool) preg_match('/[\$`]/', $target);
        if ($isDynamic) {
            $isSelf = (bool) preg_match('/\$\{?0\}?|\$\{?BASH_SOURCE/', $target);
            if ($isSelf) {
                $found[] = ['name' => 'source $0', 'risk' => 'critical', 'effect' => 'dynamic-execution'];
            } else {
                $found[] = ['name' => 'source', 'risk' => 'high', 'effect' => 'dynamic-source'];
            }
        }
    }

    // Installer / network mutation (high).
    if (preg_match('/\b(?:curl|wget)\b[^|]*\|\s*(?:sudo\s+)?(?:bash|sh)\b/', $line)) {
        $found[] = ['name' => 'curl|sh', 'risk' => 'critical', 'effect' => 'network-exec'];
    }
    if (preg_match('/\b(?:npm|pnpm|yarn)\s+(?:install|add|i)\b/', $line)) {
        $found[] = ['name' => 'npm-install', 'risk' => 'high', 'effect' => 'installer'];
    }
    if (preg_match('/\bcomposer\s+(?:install|require|update)\b/', $line)) {
        $found[] = ['name' => 'composer-install', 'risk' => 'high', 'effect' => 'installer'];
    }
    if (preg_match('/\bpip3?\s+install\b/', $line)) {
        $found[] = ['name' => 'pip-install', 'risk' => 'high', 'effect' => 'installer'];
    }
    if (preg_match('/\b(?:sudo\s+)?(?:apt-get|apt|dnf|yum|apk|pacman|zypper)\s+(?:install|add)\b/', $line, $pm)) {
        $tool = preg_replace('/^sudo\s+/', '', $pm[0]) ?? $pm[0];
        $tool = strtok($tool, ' ') ?: $tool;
        $found[] = ['name' => $tool . '-install', 'risk' => 'high', 'effect' => 'installer'];
    }
    if (preg_match('/\bbrew\s+(?:install|upgrade)\b/', $line)) {
        $found[] = ['name' => 'brew-install', 'risk' => 'high', 'effect' => 'installer'];
    }

    // Destructive filesystem mutation (high; rm -rf with expansion is critical).
    if (preg_match('/\brm\s+(?:-[A-Za-z]*\s+)*-?[A-Za-z]*[rf]/', $line)) {
        $risk = preg_match('/\brm\s+-[A-Za-z]*r[A-Za-z]*f|\brm\s+-[A-Za-z]*f[A-Za-z]*r|\brm\s+-rf\b|\brm\s+-fr\b/', $line)
            && preg_match('/\$|`|\*/', $line) ? 'critical' : 'high';
        $found[] = ['name' => 'rm', 'risk' => $risk, 'effect' => 'filesystem-delete'];
    } elseif (preg_match('/\brm\s+/', $line)) {
        $found[] = ['name' => 'rm', 'risk' => 'high', 'effect' => 'filesystem-delete'];
    }
    if (preg_match('/\b(mv|cp)\s+\S/', $line, $mc)) {
        $found[] = ['name' => $mc[1], 'risk' => 'medium', 'effect' => 'filesystem-write'];
    }
    if (preg_match('/\bsed\s+-i\b/', $line)) {
        $found[] = ['name' => 'sed -i', 'risk' => 'high', 'effect' => 'filesystem-write'];
    }
    if (preg_match('/\bfind\b.*-delete\b/', $line)) {
        $found[] = ['name' => 'find -delete', 'risk' => 'high', 'effect' => 'filesystem-delete'];
    }
    if (preg_match('/(?:^|[\s;&|(])truncate\s+-/', $line)) {
        $found[] = ['name' => 'truncate', 'risk' => 'high', 'effect' => 'filesystem-write'];
    }
    if (preg_match('/\bchmod\s+(?:-[A-Za-z]*\s+)*-R\b|\bchmod\s+-R\b/', $line)) {
        $found[] = ['name' => 'chmod -R', 'risk' => 'high', 'effect' => 'filesystem-perms'];
    }
    if (preg_match('/\bchown\s+(?:-[A-Za-z]*\s+)*-R\b|\bchown\s+-R\b/', $line)) {
        $found[] = ['name' => 'chown -R', 'risk' => 'high', 'effect' => 'filesystem-perms'];
    }
    if (preg_match('/\brsync\b.*--delete\b/', $line)) {
        $found[] = ['name' => 'rsync --delete', 'risk' => 'high', 'effect' => 'filesystem-delete'];
    }

    // Git mutation (high). Destructive/irreversible variants are critical:
    // `git reset --hard`, `git clean -f[d]`, `git push --force/-f`.
    if (preg_match('/\bgit\b[^|]*\b(reset|clean|checkout|restore|add|commit|push|rebase|merge|tag)\b/', $line, $gm)) {
        $isCriticalGit =
            (bool) preg_match('/\bgit\b[^|]*\breset\b[^|]*--hard\b/', $line)
            || (bool) preg_match('/\bgit\b[^|]*\bclean\b[^|]*-[A-Za-z]*f/', $line)
            || (bool) preg_match('/\bgit\b[^|]*\bpush\b[^|]*(?:--force\b|-f\b|--force-with-lease\b)/', $line);
        $found[] = [
            'name' => 'git ' . $gm[1],
            'risk' => $isCriticalGit ? 'critical' : 'high',
            'effect' => 'git-mutation',
        ];
    }

    // --- Read-only commands (low risk). Only when no mutating finding already
    //     matched this line, so a mutation never gets shadowed by a read tag. ---
    if ($found === []) {
        // git read subcommands.
        if (preg_match('/\bgit\b(?:\s+-C\s+\S+)?\s+(grep|log|diff|show|rev-parse|ls-files|blame|config|status|cat-file)\b/', $line, $grm)) {
            $found[] = ['name' => 'git ' . $grm[1], 'risk' => 'low', 'effect' => 'git-read'];
        }
        // filesystem read tools.
        if (preg_match('/(?:^|[\s;|&(`$])(rg|fd|fdfind|ast-grep|sg|cat|head|tail|sort)\b/', $line, $frm)) {
            $found[] = ['name' => $frm[1], 'risk' => 'low', 'effect' => 'filesystem-read'];
        }
    }

    return $found;
}

/**
 * Short, single-line argv hint: collapse whitespace and cap length so findings
 * stay compact in the envelope.
 */
function shIntrospectArgvHint(string $trimmed): string
{
    $hint = preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed;
    if (strlen($hint) > 120) {
        $hint = substr($hint, 0, 117) . '...';
    }
    return $hint;
}

/**
 * P4: structured risk findings explaining max_risk and each unresolved source.
 *
 * Combines: a finding per unresolved dynamic source, and a finding per critical
 * command (eval / sh -c / curl|sh / rm -rf expansion). Deterministic ordering
 * by (line, code).
 *
 * @param array{max_risk:string,has_mutation:bool,has_dynamic_execution:bool,has_unresolved_source:bool} $riskSummary
 * @param array<int,array<string,mixed>> $sources
 * @param array<int,array<string,mixed>> $commands
 * @return array<int,array<string,mixed>>
 */

/**
 * A source is "unresolved" when its target is dynamic (variable / command
 * substitution) AND it was not statically resolved to an existing sibling file.
 * A resolved sibling source (e.g. the common
 * `$(dirname "${BASH_SOURCE[0]}")/common.sh` pattern that exists on disk) is
 * treated as resolved.
 *
 * @param array<string,mixed> $src
 */
function shIntrospectSourceIsUnresolved(array $src): bool
{
    $target = (string) ($src['target'] ?? '');
    if ($target === '') {
        return false;
    }
    $isDynamic = str_contains($target, '$') || str_contains($target, '`') || str_contains($target, '(');
    if (!$isDynamic) {
        return false;
    }
    // Resolved to an existing sibling => no longer unresolved.
    if (!empty($src['resolved']) && !empty($src['exists'])) {
        return false;
    }
    return true;
}

function shIntrospectBuildRiskFindings(array $riskSummary, array $sources, array $commands): array
{
    $findings = [];

    // Unresolved-source findings.
    foreach ($sources as $src) {
        if (shIntrospectSourceIsUnresolved($src)) {
            $findings[] = [
                'code' => 'unresolved-source',
                'risk' => 'unknown',
                'line' => (int) ($src['line'] ?? 0),
                'detail' => 'dynamic source target could not be statically resolved: ' . (string) ($src['target'] ?? ''),
            ];
        }
    }

    // Critical / high command findings.
    foreach ($commands as $cmd) {
        $risk = (string) ($cmd['risk'] ?? '');
        if ($risk === 'critical' || $risk === 'high') {
            $findings[] = [
                'code' => (string) ($cmd['effect'] ?? 'command'),
                'risk' => $risk,
                'line' => (int) ($cmd['line'] ?? 0),
                'detail' => (string) ($cmd['name'] ?? '') . ': ' . (string) ($cmd['argv_hint'] ?? ''),
            ];
        }
    }

    usort($findings, static function (array $a, array $b): int {
        return [$a['line'], $a['code']] <=> [$b['line'], $b['code']];
    });

    return $findings;
}

/**
 * Best-effort side-effect classification.
 *
 * Read-only effects: filesystem-read (rg/fd/cat/preview), git-read (git
 * grep/log/diff/show/rev-parse). Mutating effects: filesystem-write (`>`/`>>`,
 * tee, rm/mv/cp, sed -i, find -delete) and git-mutation (reset/checkout/clean/
 * restore/add/commit/push).
 *
 * @param array<int,string> $codeLines
 * @return array<int,array<string,mixed>> items { type, confidence }
 */
function shIntrospectExtractSideEffects(array $codeLines): array
{
    $found = [];
    $add = static function (string $type, int $confidence) use (&$found): void {
        if (!isset($found[$type]) || $confidence > $found[$type]) {
            $found[$type] = $confidence;
        }
    };

    foreach ($codeLines as $line) {
        // --- mutating: git ---
        if (preg_match('/\bgit\b.*\b(reset|checkout|clean|restore|add|commit|push|rebase|merge|tag)\b/', $line)) {
            $add('git-mutation', 85);
        }
        // --- mutating: filesystem ---
        // Skip comment lines so prose like "must not truncate them" does not
        // register as a filesystem mutation.
        $codeOnly = preg_replace('/(^|\s)#.*$/', '', $line) ?? $line;
        if (preg_match('/\b(rm|mv|cp)\s+/', $codeOnly)
            || preg_match('/\bsed\s+-i\b/', $codeOnly)
            || preg_match('/\bfind\b.*-delete\b/', $codeOnly)
            || preg_match('/(?:^|[\s;&|(])truncate\s+-/', $codeOnly)
            || preg_match('/\bchmod\s+(?:-[A-Za-z]*\s+)*-R\b|\bchmod\s+-R\b/', $codeOnly)
            || preg_match('/\bchown\s+(?:-[A-Za-z]*\s+)*-R\b|\bchown\s+-R\b/', $codeOnly)
            || preg_match('/\brsync\b.*--delete\b/', $codeOnly)
            || preg_match('/\btee\b/', $codeOnly)) {
            $add('filesystem-write', 80);
        }
        // Write redirection to a real file target. This is intentionally
        // conservative: a redirection operator (optional fd, `>`/`>>`) directly
        // followed (no space) by a filename token that looks like a path, quoted
        // string, or variable. This excludes jq/test comparisons (`> 0`, `>=`),
        // `/dev/null` sinks, `>&` fd dups, and `> $(...)`-style noise.
        if (preg_match('/(?:^|[\s;|&])\d*>>?(["\'\/~.$][^\s&|;()<]*)/', $line, $rm)) {
            $target = $rm[1];
            if ($target !== ''
                && !str_starts_with($target, '/dev/')
                && !str_starts_with($target, '$(')
                && !str_contains($target, '/dev/null')) {
                $add('filesystem-write', 70);
            }
        }

        // --- read-only: git ---
        if (preg_match('/\bgit\b.*\b(grep|log|diff|show|rev-parse|ls-files|blame|config)\b/', $line)) {
            $add('git-read', 75);
        }
        // --- read-only: filesystem ---
        if (preg_match('/\b(rg|fd|fdfind|cat|head|tail|sort)\b/', $line)
            || preg_match('/preview-file/', $line)) {
            $add('filesystem-read', 70);
        }
    }

    $out = [];
    foreach ($found as $type => $confidence) {
        $out[] = ['type' => $type, 'confidence' => $confidence];
    }
    // Deterministic ordering by type.
    usort($out, static fn(array $a, array $b): int => strcmp((string) $a['type'], (string) $b['type']));
    return $out;
}

/**
 * Compute a coarse risk_summary from side effects, sources, and dynamic
 * execution markers. Never returns null fields.
 *
 * @param array<int,string> $codeLines
 * @param array<int,array<string,mixed>> $sideEffects
 * @param array<int,array<string,mixed>> $sources
 * @param array<int,array<string,mixed>> $commands
 * @return array{max_risk:string,has_mutation:bool,has_dynamic_execution:bool,has_unresolved_source:bool}
 */
function shIntrospectRiskSummary(array $codeLines, array $sideEffects, array $sources, array $commands = []): array
{
    $hasMutation = false;
    foreach ($sideEffects as $se) {
        $type = (string) $se['type'];
        if ($type === 'git-mutation' || $type === 'filesystem-write') {
            $hasMutation = true;
            break;
        }
    }

    $maxCommandRisk = 'unknown';
    foreach ($commands as $cmd) {
        $risk = (string) ($cmd['risk'] ?? 'unknown');
        $effect = (string) ($cmd['effect'] ?? '');
        if ($risk !== 'unknown' && shIntrospectRiskRank($risk) > shIntrospectRiskRank($maxCommandRisk)) {
            $maxCommandRisk = $risk;
        }
        if (in_array($effect, [
            'dynamic-source',
            'filesystem-delete',
            'filesystem-perms',
            'filesystem-write',
            'git-mutation',
            'installer',
            'network-exec',
        ], true)) {
            $hasMutation = true;
        }
    }

    // Dynamic execution: eval, bash -c, sh -c, and `source $0`/`. $0` (dynamic
    // self re-execution). Detect only command-position forms so literal
    // references inside jq/printf strings (e.g. `contains("eval(")` or
    // `rule=eval`) do not produce false positives.
    $hasDynamic = false;
    foreach ($codeLines as $line) {
        // `eval` as a command word: at statement start or after a command
        // separator, followed by whitespace and an argument (not `eval(`).
        if (preg_match('/(?:^|[;&|]|\b(?:then|else|do)\s)\s*eval\s+[^(]/', $line)
            || preg_match('/(?:^\s*|[;&|(]\s*|\bxargs\s+)(?:bash|sh)\s+-c\b/', $line)
            || preg_match('/(?:^\s*|[;&|]\s*|\b(?:then|else|do)\s+)(?:source|\.)\s+["\']?(?:\$\{?0\}?|\$\{?BASH_SOURCE)/', $line)) {
            $hasDynamic = true;
            break;
        }
    }
    foreach ($commands as $cmd) {
        if (in_array((string) ($cmd['effect'] ?? ''), ['dynamic-execution', 'network-exec'], true)) {
            $hasDynamic = true;
            break;
        }
    }

    // Unresolved source: a `source`/`.` whose target is a variable or command
    // substitution rather than a static literal path.
    $hasUnresolvedSource = false;
    foreach ($sources as $src) {
        if (shIntrospectSourceIsUnresolved($src)) {
            $hasUnresolvedSource = true;
            break;
        }
    }

    // Derive max_risk.
    if (shIntrospectRiskRank($maxCommandRisk) >= shIntrospectRiskRank('medium')) {
        $maxRisk = $maxCommandRisk;
    } elseif ($hasDynamic || $hasMutation) {
        $maxRisk = 'high';
    } elseif ($hasUnresolvedSource) {
        $maxRisk = 'unknown';
    } elseif ($maxCommandRisk === 'low') {
        $maxRisk = 'low';
    } elseif ($sideEffects !== []) {
        $maxRisk = 'low';
    } else {
        $maxRisk = 'unknown';
    }

    return [
        'max_risk' => $maxRisk,
        'has_mutation' => $hasMutation,
        'has_dynamic_execution' => $hasDynamic,
        'has_unresolved_source' => $hasUnresolvedSource,
    ];
}

function shIntrospectRiskRank(string $risk): int
{
    return match ($risk) {
        'critical' => 4,
        'high' => 3,
        'medium' => 2,
        'low' => 1,
        default => 0,
    };
}
