<?php

declare(strict_types=1);

/**
 * Smart pager support for sh-introspect human-readable output.
 *
 * The pager only ever changes the *sink* of an already-rendered report; it
 * never alters the report bytes. Non-interactive, JSON, file-output, and CI
 * invocations bypass the pager entirely and write the literal report to STDOUT
 * (or a file), so captured/piped/redirected output stays byte-identical.
 *
 * Pager preference (`--pager`/`--no-pager`):
 *   - 'auto'  (default): page only when the gate below is satisfied.
 *   - 'always': page when STDOUT is a TTY and the content is pageable text
 *               (still never pages JSON, file-output, or non-TTY sinks).
 *   - 'never':  never page.
 */

/**
 * Decide whether a human-readable report should be routed through a pager.
 *
 * Returns true only when ALL of the following hold:
 *   - $mode is 'always' or 'auto' (never when 'never');
 *   - the report is pageable text ($jsonMode is false);
 *   - it is not being written to a file ($outputPath is empty);
 *   - STDOUT is an interactive TTY;
 *   - the environment is not CI (auto mode only; 'always' overrides the
 *     default-off heuristic but still requires a TTY and non-JSON/non-file).
 */
function shIntrospectShouldPage(string $mode, bool $jsonMode, ?string $outputPath): bool
{
    if ($mode === 'never') {
        return false;
    }
    // JSON and file-output paths must remain byte-identical and unpaged.
    if ($jsonMode) {
        return false;
    }
    if ($outputPath !== null && $outputPath !== '') {
        return false;
    }
    // A pager only makes sense for an interactive terminal sink.
    if (!shIntrospectStdoutIsTty()) {
        return false;
    }
    if ($mode === 'always') {
        return true;
    }
    // auto: suppress the pager under CI so logs stay plain and complete.
    if (shIntrospectIsCi()) {
        return false;
    }
    return true;
}

/**
 * True when STDOUT is an interactive terminal.
 */
function shIntrospectStdoutIsTty(): bool
{
    if (!defined('STDOUT')) {
        return false;
    }
    if (function_exists('stream_isatty')) {
        return @stream_isatty(STDOUT);
    }
    if (function_exists('posix_isatty')) {
        return @posix_isatty(STDOUT);
    }
    return false;
}

/**
 * True when running under a recognised CI environment. Mirrors the idiom used
 * by tools/ai/commands/helpers.php (CI / GITHUB_ACTIONS == "true").
 */
function shIntrospectIsCi(): bool
{
    $ci = getenv('CI');
    if (is_string($ci) && strtolower($ci) === 'true') {
        return true;
    }
    $gh = getenv('GITHUB_ACTIONS');
    if (is_string($gh) && strtolower($gh) === 'true') {
        return true;
    }
    return false;
}

/**
 * Resolve the pager command. Honours $PAGER / $AI_PAGER when set; otherwise
 * uses `less -R -F -X`. Returns null when no usable pager binary is available,
 * in which case the caller must fall back to direct STDOUT.
 */
function shIntrospectResolvePager(): ?string
{
    foreach (['AI_PAGER', 'PAGER'] as $envName) {
        $val = getenv($envName);
        if (is_string($val) && trim($val) !== '') {
            // Only honour an env pager whose binary actually resolves; otherwise
            // fall through so a broken $PAGER/$AI_PAGER never swallows output.
            $bin = shIntrospectFirstWord($val);
            if ($bin !== '' && shIntrospectCommandExists($bin)) {
                return $val;
            }
            return null;
        }
    }
    if (shIntrospectCommandExists('less')) {
        return 'less -R -F -X';
    }
    return null;
}

/**
 * First whitespace-delimited token of a command string (the binary name).
 */
function shIntrospectFirstWord(string $command): string
{
    $trimmed = ltrim($command);
    $pos = strcspn($trimmed, " \t");
    return substr($trimmed, 0, $pos);
}

/**
 * True when a command is resolvable on PATH (best-effort, no execution of the
 * target). Uses `command -v` via a quick shell probe.
 */
function shIntrospectCommandExists(string $bin): bool
{
    $escaped = escapeshellarg($bin);
    $out = @shell_exec("command -v {$escaped} 2>/dev/null");
    return is_string($out) && trim($out) !== '';
}

/**
 * Page $report through the resolved pager. Returns true when the report was
 * fully handed to the pager; returns false when no pager could be launched so
 * the caller can fall back to a direct STDOUT write (never failing the run for
 * a missing/broken pager).
 */
function shIntrospectPageReport(string $report): bool
{
    $pager = shIntrospectResolvePager();
    if ($pager === null) {
        return false;
    }

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => STDOUT,
        2 => STDERR,
    ];
    $pipes = [];
    $proc = @proc_open($pager, $descriptors, $pipes);
    if (!is_resource($proc)) {
        return false;
    }

    if (isset($pipes[0]) && is_resource($pipes[0])) {
        @fwrite($pipes[0], $report);
        @fclose($pipes[0]);
    }
    @proc_close($proc);
    return true;
}
