<?php

declare(strict_types=1);

/**
 * @param array<int,string> $argv
 */
function shIntrospectMain(array $argv): int
{
    $forceJson = false;
    $helpSummary = false;
    $all = false;
    $strictRisk = false;
    $path = null;
    $outputPath = null; // when set, the report is written here instead of STDOUT
    $expectFormat = false; // true after a bare `--format` awaiting its value
    $expectOutput = false; // true after a bare `--output` awaiting its value

    foreach (array_slice($argv, 1) as $arg) {
        // Value for a preceding bare `--format` (space-separated form).
        if ($expectFormat) {
            $expectFormat = false;
            if ($arg === 'json') {
                $forceJson = true;
                continue;
            }
            if ($arg === 'help') {
                $helpSummary = true;
                continue;
            }
            $jsonMode = getenv('AI_OUTPUT') === 'json';
            return shIntrospectFail($jsonMode, '', "unknown --format value: {$arg} (expected json or help)");
        }
        // Value for a preceding bare `--output` (space-separated form).
        if ($expectOutput) {
            $expectOutput = false;
            $outputPath = $arg;
            continue;
        }
        if ($arg === '--help' || $arg === '-h') {
            fwrite(STDOUT, shIntrospectUsage());
            return 0;
        }
        // --all: repo-wide index mode (discover scripts and emit an index).
        if ($arg === '--all') {
            $all = true;
            continue;
        }
        // --strict-risk: exit non-zero (3) when a critical risk is detected.
        if ($arg === '--strict-risk') {
            $strictRisk = true;
            continue;
        }
        // Bare `--format VALUE` (space-separated); the value is the next arg.
        if ($arg === '--format') {
            $expectFormat = true;
            continue;
        }
        // --output PATH / --output=PATH: write the report to a file instead of
        // STDOUT. Works for text, --format=help, JSON, and --all modes. The
        // target script is still never executed; this only redirects output.
        if ($arg === '--output' || $arg === '-o') {
            $expectOutput = true;
            continue;
        }
        if (str_starts_with($arg, '--output=')) {
            $outputPath = substr($arg, strlen('--output='));
            continue;
        }
        // --format=VALUE: json -> JSON envelope, help -> compact help summary.
        // Any other value is an explicit error (status/stderr).
        if (str_starts_with($arg, '--format=')) {
            $value = substr($arg, strlen('--format='));
            if ($value === 'json') {
                $forceJson = true;
                continue;
            }
            if ($value === 'help') {
                $helpSummary = true;
                continue;
            }
            // Honour AI_OUTPUT=json for the error envelope shape when present.
            $jsonMode = getenv('AI_OUTPUT') === 'json';
            return shIntrospectFail($jsonMode, '', "unknown --format value: {$value} (expected json or help)");
        }
        if (str_starts_with($arg, '--')) {
            // Unknown flags are ignored for forward-compat; the only meaningful
            // toggles are --all, --format[=]json and --format[=]help. Path
            // resolution still proceeds.
            continue;
        }
        if ($path === null) {
            $path = $arg;
        }
    }

    $jsonMode = $forceJson || (getenv('AI_OUTPUT') === 'json');

    // A trailing bare `--output`/`-o` with no following value is an error.
    if ($expectOutput) {
        return shIntrospectFail($jsonMode, '', '--output requires a file path');
    }

    // Repo-wide index mode: discover the AI shell scripts/libraries and emit a
    // compact per-file index. `$path`, when given, is treated as the discovery
    // root; otherwise the repo's scripts/ai directory is used.
    if ($all) {
        return shIntrospectAllMain($jsonMode, $path, $strictRisk, $outputPath);
    }

    if ($path === null) {
        return shIntrospectFail($jsonMode, '', 'no input file given; usage: sh-introspect.php FILE.sh');
    }

    // Validate the path before parsing. Each branch yields status=error.
    if (!file_exists($path)) {
        return shIntrospectFail($jsonMode, $path, "file not found: {$path}");
    }
    if (is_dir($path)) {
        return shIntrospectFail($jsonMode, $path, "path is a directory, not a file: {$path}");
    }
    if (!is_readable($path)) {
        return shIntrospectFail($jsonMode, $path, "file is not readable: {$path}");
    }

    $abs = realpath($path);
    if ($abs === false) {
        return shIntrospectFail($jsonMode, $path, "could not resolve path: {$path}");
    }

    $raw = @file_get_contents($abs);
    if ($raw === false) {
        return shIntrospectFail($jsonMode, $abs, "could not read file: {$abs}");
    }

    if (strpos($raw, "\0") !== false) {
        return shIntrospectFail($jsonMode, $abs, "file appears to be binary (contains NUL byte): {$abs}");
    }

    $result = shIntrospectParse($raw, $abs);

    if ($helpSummary) {
        // --format=help is an explicit request for the compact summary and wins
        // over the AI_OUTPUT=json default. Path validation above still used the
        // JSON envelope shape when AI_OUTPUT=json was set.
        $report = shIntrospectRenderHelpSummary($result);
    } elseif ($jsonMode) {
        $report = shIntrospectEncode($result) . "\n";
    } else {
        $report = shIntrospectRenderText($result);
    }

    if (!shIntrospectEmitReport($report, $outputPath, $jsonMode)) {
        return 2;
    }

    // --strict-risk: a critical max_risk fails the run (exit 3) so the contract
    // can gate CI. The full report is still emitted above first.
    if ($strictRisk && (string) ($result['risk_summary']['max_risk'] ?? '') === 'critical') {
        fwrite(STDERR, "STRICT-RISK: critical risk detected in " . $abs . "\n");
        return 3;
    }

    return 0;
}

function shIntrospectUsage(): string
{
    return <<<TXT
sh-introspect.php — static introspector for Bash scripts.

Usage:
    php tools/ai/sh-introspect.php FILE.sh            human-readable summary
    AI_OUTPUT=json php tools/ai/sh-introspect.php FILE.sh   JSON envelope
    php tools/ai/sh-introspect.php --format=json FILE.sh    force JSON
    php tools/ai/sh-introspect.php --format=help FILE.sh    compact help summary
    php tools/ai/sh-introspect.php --all [ROOT]            repo-wide index
    php tools/ai/sh-introspect.php --all --format json     index JSON envelope
    php tools/ai/sh-introspect.php --output PATH FILE.sh   write report to PATH
    php tools/ai/sh-introspect.php --strict-risk FILE.sh   exit 3 on critical risk
    php tools/ai/sh-introspect.php --help | -h             this help

The target script is never executed; FILE.sh is parsed statically as text.

--output PATH (or -o PATH / --output=PATH) writes the report to a file instead
of STDOUT, creating parent directories as needed; a confirmation line goes to
stderr. It works in text, --format=help, JSON, and --all modes.
Reports: functions, modes (with display_group), mode_contracts (per-mode
deps/positionals/examples), params (with applies_to_modes + scope), positionals,
case_labels, json_keys, json_key_candidates, json_paths (JSONPath + confidence),
output_schemas (envelope + per-family + per-mode), env_inputs, sources (with
resolved siblings), dependencies (classified), unknown_option_handlers, commands
(read + mutating, with kind/source), side_effects, risk_summary, risk_findings,
examples, examples_by_mode.

--all emits the index envelope (schema ai.sh-introspect-index/v1) for the AI
shell scripts under scripts/ai (and scripts/ai/lib). --strict-risk makes the run
exit 3 when any critical risk is detected (single file or across the index).

TXT;
}

/**
 * Emit a rendered report either to STDOUT or, when $outputPath is set, to that
 * file. Parent directories are created as needed. Returns false (and reports an
 * error envelope/stderr line) when the file could not be written.
 */
function shIntrospectEmitReport(string $report, ?string $outputPath, bool $jsonMode): bool
{
    if ($outputPath === null || $outputPath === '') {
        fwrite(STDOUT, $report);
        return true;
    }

    $dir = dirname($outputPath);
    if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
        if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
            shIntrospectFail($jsonMode, $outputPath, "could not create output directory: {$dir}");
            return false;
        }
    }

    if (@file_put_contents($outputPath, $report) === false) {
        shIntrospectFail($jsonMode, $outputPath, "could not write output file: {$outputPath}");
        return false;
    }

    // Confirmation goes to stderr so it never pollutes a redirected/captured report.
    fwrite(STDERR, "sh-introspect: wrote report to {$outputPath}\n");
    return true;
}

/**
 * Emit an error envelope (JSON) or stderr line, returning the exit code.
 */
function shIntrospectFail(bool $jsonMode, string $file, string $message): int
{
    if ($jsonMode) {
        $envelope = shIntrospectEmptyEnvelope($file);
        $envelope['status'] = 'error';
        $envelope['errors'] = [$message];
        $envelope['meta']['confidence'] = 0;
        fwrite(STDOUT, shIntrospectEncode($envelope) . "\n");
    } else {
        fwrite(STDERR, "ERROR: {$message}\n");
    }
    return 2;
}
