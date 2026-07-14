# shellcheck shell=bash
# Android/KMP repo-reality guards for the AI verification gate (Kotlin lane).
#
# Part of docs/tickets/arch-todo-android-kmp-verify-lane-20260706-010421
# (§8-P0-b). This module is intended to be sourced by scripts/ai/ai-verify.sh
# (the thin root loader); it is NOT an entrypoint and must not be executed
# directly. AS OF THIS SLICE (P0) it is NOT YET sourced by ai-verify.sh -- this
# lane must not be wired anywhere yet. Until then it is exercised only by tests
# (see tests/shell/ai-verify-android-guards.bats).
#
# These are the genuinely NET-NEW checks of the Android/KMP proposal (0% overlap
# with any existing check_* -- see plan.md §1c). They target the AdvancedGym
# reality: `:app` + `:shared` are the only active Gradle modules; `composeApp` is
# inactive and its files must not drift; and dependency versions must live in the
# Gradle version catalog, not inline in build scripts.
#
# Contract (mirrors 36-plan-status.sh: check_*): each guard prints an `OK:` or
# `FAIL:` line and increments the GLOBAL $failures on violation, so a caller
# under `set -e` keeps running every guard and tallies failures. $failures must
# be defined by the caller (the root loader does; tests initialise it).
# shellcheck disable=SC2154 # $failures is a global owned by the caller/root loader.
#
# Reuse note (plan.md §1b FLAG-B): check_inactive_compose_app_drift REUSES
# scoped_changed_files_by_pathspec (90-run.sh) when it is defined, instead of
# hand-rolling three git calls. When that engine function is absent (this module
# sourced in isolation, e.g. a direct unit test that does not load 90-run.sh) it
# falls back to a self-contained git form so the guard stays independently
# testable. Both paths honour the same scope semantics for the "changed" scope.

# Default settings file inspected by the module-activation guards. Overridable
# for tests via GRADLE_SETTINGS_FILE.
GRADLE_SETTINGS_FILE="${GRADLE_SETTINGS_FILE:-settings.gradle.kts}"

# Hard-fail unless the active Gradle module set is exactly the expected reality:
# `:app` and `:shared` included, and `composeApp` NOT active. A missing settings
# file is itself a failure (the guard cannot prove module reality without it).
check_active_modules() {
    echo "==> active Gradle modules"

    if [[ ! -f "$GRADLE_SETTINGS_FILE" ]]; then
        echo "FAIL: $GRADLE_SETTINGS_FILE not found; cannot verify active modules" >&2
        failures=$((failures + 1))
        return 0
    fi

    local includes
    includes="$(grep -E '^[[:space:]]*include\(' "$GRADLE_SETTINGS_FILE" || true)"

    if ! grep -q '":app"' <<<"$includes"; then
        echo "FAIL: :app is not included in $GRADLE_SETTINGS_FILE" >&2
        failures=$((failures + 1))
    fi

    if ! grep -q '":shared"' <<<"$includes"; then
        echo "FAIL: :shared is not included in $GRADLE_SETTINGS_FILE" >&2
        failures=$((failures + 1))
    fi

    if grep -q 'composeApp' <<<"$includes"; then
        echo "FAIL: composeApp is active in $GRADLE_SETTINGS_FILE but infra reality says inactive" >&2
        failures=$((failures + 1))
    fi
}

# Print scoped changed files under composeApp/, reusing the pipeline's own
# scope helper when present (FLAG-B), else a self-contained git fallback.
_compose_app_changed_files() {
    if declare -F scoped_changed_files_by_pathspec >/dev/null 2>&1; then
        # shellcheck disable=SC2154 # AI_VERIFY_SCOPE set by caller/root loader
        scoped_changed_files_by_pathspec "${AI_VERIFY_SCOPE:-changed}" 'composeApp/**' 'composeApp'
        return 0
    fi
    {
        git diff --name-only --diff-filter=ACMRT -- composeApp
        git diff --cached --name-only --diff-filter=ACMRT -- composeApp
        git ls-files --others --exclude-standard -- composeApp
    } | sort -u
}

# Hard-fail when the inactive composeApp module has changed files, unless the
# explicit ALLOW_INACTIVE_MODULE_CHANGES=1 escape hatch is set (for deliberate
# quarantine/reactivation work only). This prevents composeApp edits being
# treated as active-app evidence.
check_inactive_compose_app_drift() {
    echo "==> inactive composeApp drift"

    local changed
    changed="$(_compose_app_changed_files)"

    if [[ -n "$changed" && "${ALLOW_INACTIVE_MODULE_CHANGES:-0}" != "1" ]]; then
        echo "FAIL: composeApp is inactive but has changed files:" >&2
        while IFS= read -r f; do
            [[ -n "$f" ]] || continue
            echo "    $f" >&2
        done <<<"$changed"
        echo "Set ALLOW_INACTIVE_MODULE_CHANGES=1 only for explicit quarantine/reactivation work." >&2
        failures=$((failures + 1))
    else
        echo "OK: no inactive composeApp drift"
    fi
}

# Hard-fail when a Gradle build script declares an inline, hard-coded dependency
# VERSION (group:name:version) instead of routing through the Gradle version
# catalog. Scans *.gradle.kts / *.gradle outside build/ and .gradle/. This is the
# version-catalog discipline guard from the proposal.
check_version_catalog_required() {
    echo "==> version catalog discipline"

    local hits
    hits="$(
        find . -type f \( -name '*.gradle.kts' -o -name '*.gradle' \) \
            ! -path './build/*' ! -path '*/build/*' ! -path './.gradle/*' -print0 2>/dev/null |
            xargs -0 grep -nE 'implementation\("[A-Za-z0-9_.-]+:[A-Za-z0-9_.-]+:[0-9]' 2>/dev/null || true
    )"

    if [[ -n "$hits" ]]; then
        echo "FAIL: direct dependency versions found outside the version catalog:" >&2
        while IFS= read -r line; do
            [[ -n "$line" ]] || continue
            echo "    $line" >&2
        done <<<"$hits"
        failures=$((failures + 1))
    else
        echo "OK: no direct dependency versions outside the version catalog"
    fi
}
