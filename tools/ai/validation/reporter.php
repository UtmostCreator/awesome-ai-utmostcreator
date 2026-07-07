<?php

declare(strict_types=1);

// reporter.php: shared oks/warnings/errors report-and-exit-code function used by
// tools/ai/validate-ai-config.php. This is a verbatim wrap of the prior top-level
// report block (same order: oks -> warnings -> errors, same exit-code formula) into
// one function so the call site can do `exit(aiValidationReport($oks, $warnings, $errors));`.
// See docs/tickets/arch-todo-validate-ai-config-extraction-20260706-223128/plan.md, Phase 3.
//
// Deviation from a separate "validation-result.php": there is no distinct result data
// structure in the source — $oks/$warnings/$errors are three plain parallel arrays with
// no shared shape used elsewhere, so a single reporter module is the honest minimal seam.

/**
 * Print collected oks/warnings/errors (in that order) and return the process exit code.
 *
 * @param list<string> $oks
 * @param list<string> $warnings
 * @param list<string> $errors
 */
function aiValidationReport(array $oks, array $warnings, array $errors): int
{
    foreach ($oks as $message) {
        fwrite(STDOUT, "OK: {$message}\n");
    }

    foreach ($warnings as $message) {
        fwrite(STDOUT, "WARN: {$message}\n");
    }

    foreach ($errors as $message) {
        fwrite(STDERR, "ERROR: {$message}\n");
    }

    return $errors === [] ? 0 : 1;
}
