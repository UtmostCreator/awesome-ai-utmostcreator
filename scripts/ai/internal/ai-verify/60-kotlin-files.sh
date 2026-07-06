# shellcheck shell=bash
# Kotlin / Android / KMP file-discovery helpers for the AI verification gate.
#
# Part of docs/tickets/arch-todo-android-kmp-verify-lane-20260706-010421
# (§8-P0-a). This module is intended to be sourced by scripts/ai/ai-verify.sh
# (the thin root loader), the same way every other scripts/ai/internal/ai-verify/
# module is; it is NOT an entrypoint and must not be executed directly. AS OF
# THIS SLICE (P0) it is NOT YET sourced by scripts/ai/ai-verify.sh -- that wiring
# is deferred to a later, separately-approved slice (constraint: this lane must
# not be wired anywhere yet). Until then this file is only exercised directly, by
# sourcing it in tests (see tests/shell/ai-verify-kotlin-files.bats).
#
# FOLD-IN TARGET (plan.md §1b FLAG-C): this file deliberately MIRRORS the
# sibling ticket's scripts/ai/internal/ai-verify/51-language-files.sh contract
# (language_pathspecs / scoped_language_files) rather than editing it, because
# the concurrent session owns 51-language-files.sh and this lane must not touch
# PHP/JS-lane files. The intended end state is a single `kotlin)` case inside
# 51-language-files.sh's language_pathspecs; a later unblocked slice should move
# kotlin_language_pathspecs' body there and drop this file's discovery half.
#
# Load-order dependency (read before wiring this into ai-verify.sh):
# `scoped_kotlin_files` calls `scoped_changed_files_by_pathspec`, which is
# defined in scripts/ai/internal/ai-verify/90-run.sh. Bash resolves function
# bodies at CALL time, not at source time, so this file may be sourced BEFORE
# 90-run.sh without error -- the dependency only has to be satisfied by the time
# `scoped_kotlin_files` is actually invoked. This mirrors the identical note in
# 51-language-files.sh.
#
# This module also depends on `die` (scripts/ai/internal/lib/05-core.sh, loaded
# transitively via scripts/ai/common.sh) and on `$AI_VERIFY_SCOPE` being set by
# the caller (the root loader sets a default; direct callers/tests must set it
# themselves, e.g. AI_VERIFY_SCOPE=changed).

# Print one git pathspec glob per line for the Kotlin/Android/KMP surface. This
# covers Kotlin sources (`*.kt`), Kotlin/Gradle scripts (`*.kts`, including
# `*.gradle.kts`), Groovy Gradle scripts (`*.gradle`), and the Gradle version
# catalog (`gradle/libs.versions.toml`) -- the files whose changes should drive a
# Kotlin/Android verification run. Unknown languages are a caller programming
# error (mirrors 51-language-files.sh: language_pathspecs), so this dies loudly.
kotlin_language_pathspecs() {
    local lang="${1:?language required}"

    case "$lang" in
    kotlin)
        printf '%s\n' \
            '*.kt' \
            '*.kts' \
            '*.gradle.kts' \
            '*.gradle' \
            'gradle/libs.versions.toml'
        ;;
    *)
        die "unknown language: $lang"
        ;;
    esac
}

# Emit existing, scoped, changed Kotlin/Gradle files, honoring $AI_VERIFY_SCOPE
# exactly the way the rest of this pipeline does. This does NOT reimplement scope
# resolution: it calls the existing scoped_changed_files_by_pathspec
# (scripts/ai/internal/ai-verify/90-run.sh) once per pathspec returned by
# kotlin_language_pathspecs, merges every pathspec's results, de-duplicates, and
# filters down to paths that currently exist as regular files. Mirrors
# 51-language-files.sh: scoped_language_files.
scoped_kotlin_files() {
    local lang="${1:-kotlin}"
    local pathspec

    while IFS= read -r pathspec; do
        [[ -n "$pathspec" ]] || continue
        # shellcheck disable=SC2154 # AI_VERIFY_SCOPE is set by the caller/root loader
        scoped_changed_files_by_pathspec "$AI_VERIFY_SCOPE" "$pathspec"
    done < <(kotlin_language_pathspecs "$lang") |
        sort -u |
        while IFS= read -r f; do
            [[ -n "$f" ]] || continue
            [[ -f "$f" ]] || continue
            printf '%s\n' "$f"
        done
}
