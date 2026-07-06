# shellcheck shell=bash
# Gradle tool-availability policy for the AI verification gate (Kotlin/Android lane).
#
# Part of docs/tickets/arch-todo-android-kmp-verify-lane-20260706-010421
# (§8-P0-a). This module is intended to be sourced by scripts/ai/ai-verify.sh
# (the thin root loader); it is NOT an entrypoint and must not be executed
# directly. AS OF THIS SLICE (P0) it is NOT YET sourced by ai-verify.sh -- this
# lane must not be wired anywhere yet. Until then it is exercised only by tests
# (see tests/shell/ai-verify-gradle-policy.bats).
#
# FOLD-IN TARGET (plan.md §1b FLAG-D): this MIRRORS the sibling ticket's
# scripts/ai/internal/ai-verify/50-tool-policy.sh conventions (a fixed, documented
# guard with NO PATH fallback for framework-specific tools) rather than editing
# that file, which the concurrent session owns. The intended end state folds a
# `gradle`/`gradlew` case into 50-tool-policy.sh's can_run_tool `*)` arm; a later
# unblocked slice should move these predicates there.
#
# Safety posture (mirrors 50-tool-policy.sh): the ONLY Gradle entrypoint this
# lane ever runs is the project's own committed wrapper (`./gradlew`). It never
# falls back to a PATH-installed `gradle`, because a global Gradle would not
# reflect this project's pinned wrapper/distribution version -- exactly the same
# reasoning as has_composer_bin refusing a PATH copy of a vendored tool.

# True (exit 0) when this project ships an executable Gradle wrapper at its root.
# Everything in this lane is gated on this: with no ./gradlew, no Gradle task can
# be run safely (per the "already available, project-local only" rule).
has_gradle_wrapper() {
    [[ -x ./gradlew ]]
}

# True (exit 0) when the given Gradle task exists in this project, per the
# project's own wrapper. Uses `./gradlew tasks --all` (the documented Gradle way
# to enumerate available tasks) and matches the task name so a verify script
# never invokes a task that has been renamed/removed (the "verify scripts drift"
# risk called out in the Android/KMP proposal). Returns false (never errors) when
# there is no wrapper, so callers can guard-then-skip cleanly.
#
# The match is intentionally on the task's short name (the segment after the last
# ':'), because `./gradlew tasks --all` lists module-qualified tasks in a
# `:module:task` form whose exact rendering varies by Gradle version; matching the
# short name is the stable, version-tolerant check the proposal settled on.
gradle_task_exists() {
    local task="${1:?task required}"
    has_gradle_wrapper || return 1

    local short="${task##*:}"
    ./gradlew tasks --all --console=plain --quiet 2>/dev/null |
        grep -qE "(^|[[:space:]:])${short}([[:space:]]|$)"
}
