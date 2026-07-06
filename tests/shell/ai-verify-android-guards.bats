#!/usr/bin/env bats
# Tests for the Android/KMP repo-reality guards
# (scripts/ai/internal/ai-verify/62-android-guards.sh).
#
# As of docs/tickets/arch-todo-android-kmp-verify-lane-20260706-010421 §8-P0-b,
# this module is NOT YET sourced by scripts/ai/ai-verify.sh, so these tests
# source it directly (after common.sh) inside a throwaway nested git repo. The
# composeApp-drift guard reuses scoped_changed_files_by_pathspec when present;
# these tests exercise the self-contained git fallback path (they do NOT source
# 90-run.sh), which is the module's independently-testable form.
#
# Each guard mutates a caller-owned global $failures; the eval harness seeds it
# to 0 and echoes its final value so tests can assert pass/fail.

REPO_ROOT="$(cd "$(dirname "$BATS_TEST_FILENAME")/../.." && pwd)"
COMMON="$REPO_ROOT/scripts/ai/common.sh"
GUARDS="$REPO_ROOT/scripts/ai/internal/ai-verify/62-android-guards.sh"

setup() {
    TMP_REPO="$(mktemp -d)"
    git -C "$TMP_REPO" init --quiet
    git -C "$TMP_REPO" config user.email test@example.com
    git -C "$TMP_REPO" config user.name Tester
}

teardown() {
    rm -rf "$TMP_REPO" 2>/dev/null || true
}

# Runs one guard function with cwd = $TMP_REPO, $failures seeded to 0, and prints
# a trailing "failures=<n>" line so tests can assert the tally. Extra env (e.g.
# ALLOW_INACTIVE_MODULE_CHANGES=1) is passed through the caller's environment.
guard_eval() {
    run bash -c '
        set -uo pipefail
        cd "$1"
        source "$2"
        source "$3"
        failures=0
        "$4"
        echo "failures=$failures"
    ' _ "$TMP_REPO" "$COMMON" "$GUARDS" "$1"
}

# --- check_active_modules ----------------------------------------------------

@test "check_active_modules passes for :app + :shared only" {
    cat >"$TMP_REPO/settings.gradle.kts" <<'EOF'
include(":app")
include(":shared")
EOF
    guard_eval check_active_modules
    [[ "$output" == *"failures=0"* ]]
}

@test "check_active_modules fails when :shared is missing" {
    cat >"$TMP_REPO/settings.gradle.kts" <<'EOF'
include(":app")
EOF
    guard_eval check_active_modules
    [[ "$output" == *":shared is not included"* ]]
    [[ "$output" == *"failures=1"* ]]
}

@test "check_active_modules fails when composeApp is active" {
    cat >"$TMP_REPO/settings.gradle.kts" <<'EOF'
include(":app")
include(":shared")
include(":composeApp")
EOF
    guard_eval check_active_modules
    [[ "$output" == *"composeApp is active"* ]]
    [[ "$output" == *"failures=1"* ]]
}

@test "check_active_modules fails when the settings file is missing" {
    guard_eval check_active_modules
    [[ "$output" == *"settings.gradle.kts not found"* ]]
    [[ "$output" == *"failures=1"* ]]
}

# --- check_inactive_compose_app_drift ---------------------------------------

@test "check_inactive_compose_app_drift passes with no composeApp changes" {
    guard_eval check_inactive_compose_app_drift
    [[ "$output" == *"OK: no inactive composeApp drift"* ]]
    [[ "$output" == *"failures=0"* ]]
}

@test "check_inactive_compose_app_drift fails on an untracked composeApp change" {
    mkdir -p "$TMP_REPO/composeApp/src"
    printf 'fun x() {}\n' >"$TMP_REPO/composeApp/src/Leak.kt"
    guard_eval check_inactive_compose_app_drift
    [[ "$output" == *"composeApp is inactive but has changed files"* ]]
    [[ "$output" == *"failures=1"* ]]
}

@test "check_inactive_compose_app_drift honors ALLOW_INACTIVE_MODULE_CHANGES=1 escape" {
    mkdir -p "$TMP_REPO/composeApp/src"
    printf 'fun x() {}\n' >"$TMP_REPO/composeApp/src/Leak.kt"
    ALLOW_INACTIVE_MODULE_CHANGES=1 guard_eval check_inactive_compose_app_drift
    [[ "$output" == *"OK: no inactive composeApp drift"* ]]
    [[ "$output" == *"failures=0"* ]]
}

# --- check_version_catalog_required -----------------------------------------

@test "check_version_catalog_required passes when deps use the catalog" {
    cat >"$TMP_REPO/build.gradle.kts" <<'EOF'
dependencies {
    implementation(libs.androidx.core)
    implementation(project(":shared"))
}
EOF
    guard_eval check_version_catalog_required
    [[ "$output" == *"OK: no direct dependency versions"* ]]
    [[ "$output" == *"failures=0"* ]]
}

@test "check_version_catalog_required fails on an inline versioned dependency" {
    cat >"$TMP_REPO/build.gradle.kts" <<'EOF'
dependencies {
    implementation("androidx.core:core-ktx:1.13.1")
}
EOF
    guard_eval check_version_catalog_required
    [[ "$output" == *"direct dependency versions found"* ]]
    [[ "$output" == *"failures=1"* ]]
}

@test "check_version_catalog_required ignores build/ output directories" {
    mkdir -p "$TMP_REPO/build/generated"
    cat >"$TMP_REPO/build/generated/gen.gradle.kts" <<'EOF'
    implementation("androidx.core:core-ktx:1.13.1")
EOF
    guard_eval check_version_catalog_required
    [[ "$output" == *"OK: no direct dependency versions"* ]]
    [[ "$output" == *"failures=0"* ]]
}
