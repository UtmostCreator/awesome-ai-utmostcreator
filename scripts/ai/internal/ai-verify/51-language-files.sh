# shellcheck shell=bash
# Per-language file-discovery helpers for the AI verification gate.
#
# Part of docs/tickets/arch-todo-safe-language-verify-scripts-20260706-003959
# (§8-P1). This module is intended to be sourced by scripts/ai/ai-verify.sh
# (the thin root loader), the same way every other scripts/ai/internal/ai-verify/
# module is; it is NOT an entrypoint and must not be executed directly. AS OF
# THIS SLICE (P1) it is NOT YET sourced by scripts/ai/ai-verify.sh — that wiring
# is deferred to a later slice (P2, per-language dispatcher + `--language` flag).
# Until then this file is only exercised directly, by sourcing it in tests
# (see tests/shell/ai-verify-language-files.bats) or from a future standalone
# per-language wrapper script.
#
# Load-order dependency (read before wiring this into ai-verify.sh):
# `scoped_language_files` calls `scoped_changed_files_by_pathspec`, which is
# defined in scripts/ai/internal/ai-verify/90-run.sh. Bash resolves function
# bodies at CALL time, not at source time, so this file may be sourced BEFORE
# 90-run.sh without error -- the dependency only has to be satisfied by the time
# `scoped_language_files` is actually invoked, not by the time this file is
# read. Today's root loader (scripts/ai/ai-verify.sh) sources modules in this
# order: 20-shipped-filters, 10-scope, 30-linecount, 40-step-runner, 35-jscpd,
# 36-plan-status, 90-run (last). The P2 implementer deciding where to source
# this 51-* file only needs to ensure it happens sometime before
# `scoped_language_files` is first CALLED (typically from a not-yet-built
# 53-language-dispatch.sh); it does not need to load before 90-run.sh sources.
#
# This module also depends on `die` (scripts/ai/internal/lib/05-core.sh, loaded
# transitively via scripts/ai/common.sh) and on `$AI_VERIFY_SCOPE` being set by
# the caller (the root loader sets a default of "ai"; direct callers/tests must
# set it themselves, e.g. AI_VERIFY_SCOPE=changed).

# Print one git pathspec glob per line for the given language. Unknown
# languages are a caller programming error, not a soft-skip condition, so this
# dies loudly (mirrors the "unknown AI_VERIFY_SCOPE" die() calls elsewhere in
# this pipeline, e.g. scripts/ai/internal/ai-verify/10-scope.sh).
language_pathspecs() {
    local lang="${1:?language required}"

    case "$lang" in
    php)
        printf '%s\n' '*.php'
        ;;
    js)
        printf '%s\n' '*.js' '*.jsx' '*.mjs' '*.cjs'
        ;;
    ts)
        printf '%s\n' '*.ts' '*.tsx' '*.mts' '*.cts'
        ;;
    vue)
        printf '%s\n' '*.vue'
        ;;
    html)
        printf '%s\n' '*.html' '*.blade.php' '*.twig'
        ;;
    *)
        die "unknown language: $lang"
        ;;
    esac
}

# Emit existing, scoped, changed files for the given language, honoring
# $AI_VERIFY_SCOPE exactly the way the rest of this pipeline does. This does
# NOT reimplement scope resolution: it calls the existing
# scoped_changed_files_by_pathspec (scripts/ai/internal/ai-verify/90-run.sh) once
# per pathspec returned by language_pathspecs, merges every pathspec's results,
# de-duplicates, and filters down to paths that currently exist as regular
# files (a path can appear in a diff/ls-files listing and then be deleted
# before this check runs, or be a submodule/symlink entry rather than a file).
scoped_language_files() {
    local lang="${1:?language required}"
    local pathspec

    while IFS= read -r pathspec; do
        [[ -n "$pathspec" ]] || continue
        # shellcheck disable=SC2154 # AI_VERIFY_SCOPE is set by the caller/root loader
        scoped_changed_files_by_pathspec "$AI_VERIFY_SCOPE" "$pathspec"
    done < <(language_pathspecs "$lang") |
        sort -u |
        while IFS= read -r f; do
            [[ -n "$f" ]] || continue
            [[ -f "$f" ]] || continue
            printf '%s\n' "$f"
        done
}
