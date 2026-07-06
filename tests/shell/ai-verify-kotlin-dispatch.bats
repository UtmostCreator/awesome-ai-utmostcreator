#!/usr/bin/env bats
# Tests for the Kotlin/Android/KMP verification dispatcher
# (scripts/ai/internal/ai-verify/63-kotlin-dispatch.sh).
#
# As of docs/tickets/arch-todo-android-kmp-verify-lane-20260706-010421 §8-P0-b,
# this module is NOT YET sourced by scripts/ai/ai-verify.sh, so these tests
# source it directly (after common.sh + 40-step-runner.sh for run_step, and
# 61-gradle-policy.sh + 62-android-guards.sh which the dispatcher calls). They
# run with AI_KOTLIN_TEST_MODE=1 so no real Gradle task ever executes: the
# dispatcher announces each selected task via a `PLAN:` line, letting tests
# assert exactly which tasks are selected (default vs VERIFY_FULL vs VERIFY_IOS),
# mirroring the AI_VERIFY_TEST_MODE stub pattern used elsewhere in this pipeline.

REPO_ROOT="$(cd "$(dirname "$BATS_TEST_FILENAME")/../.." && pwd)"
COMMON="$REPO_ROOT/scripts/ai/common.sh"
STEP_RUNNER="$REPO_ROOT/scripts/ai/internal/ai-verify/40-step-runner.sh"
GRADLE_POLICY="$REPO_ROOT/scripts/ai/internal/ai-verify/61-gradle-policy.sh"
GUARDS="$REPO_ROOT/scripts/ai/internal/ai-verify/62-android-guards.sh"
DISPATCH="$REPO_ROOT/scripts/ai/internal/ai-verify/63-kotlin-dispatch.sh"

setup() {
    TMP_DIR="$(mktemp -d)"
}

teardown() {
    rm -rf "$TMP_DIR" 2>/dev/null || true
}

# Runs ai_verify_kotlin in stub mode with cwd = $TMP_DIR and $failures seeded to
# 0. Extra env (VERIFY_FULL / VERIFY_IOS) is passed via the caller environment.
dispatch_eval() {
    run bash -c '
        set -uo pipefail
        cd "$1"
        source "$2"
        source "$3"
        source "$4"
        source "$5"
        source "$6"
        failures=0
        AI_KOTLIN_TEST_MODE=1 ai_verify_kotlin
        echo "failures=$failures"
    ' _ "$TMP_DIR" "$COMMON" "$STEP_RUNNER" "$GRADLE_POLICY" "$GUARDS" "$DISPATCH"
}

@test "default lane selects exactly the five P0 tasks" {
    dispatch_eval
    [[ "$output" == *"PLAN: :app:assembleDebug"* ]]
    [[ "$output" == *"PLAN: :shared:check"* ]]
    [[ "$output" == *"PLAN: :app:lintDebug"* ]]
    [[ "$output" == *"PLAN: :shared:compileKotlinMetadata"* ]]
    [[ "$output" == *"PLAN: :shared:compileDebugKotlinAndroid"* ]]
}

@test "default lane does NOT select full or iOS tasks" {
    dispatch_eval
    [[ "$output" != *"PLAN: :app:connectedDebugAndroidTest"* ]]
    [[ "$output" != *"PLAN: :shared:allTests"* ]]
    [[ "$output" != *"PLAN: :shared:compileKotlinIosX64"* ]]
    [[ "$output" == *"Skipping full Android/KMP test matrix"* ]]
    [[ "$output" == *"Skipping iOS compile lane"* ]]
}

@test "VERIFY_FULL=1 adds the full test-matrix tasks" {
    VERIFY_FULL=1 dispatch_eval
    [[ "$output" == *"PLAN: :app:connectedDebugAndroidTest"* ]]
    [[ "$output" == *"PLAN: :shared:allTests"* ]]
    [[ "$output" != *"Skipping full Android/KMP test matrix"* ]]
}

@test "VERIFY_IOS=1 adds the iOS compile tasks" {
    VERIFY_IOS=1 dispatch_eval
    [[ "$output" == *"PLAN: :shared:compileKotlinIosX64"* ]]
    [[ "$output" == *"PLAN: :shared:compileKotlinIosSimulatorArm64"* ]]
    [[ "$output" != *"Skipping iOS compile lane"* ]]
}

@test "stub mode does not run the repo-reality guards or invoke gradle" {
    # In stub mode the guards are skipped (they inspect real files/git); no
    # FAIL lines should appear and failures stays 0 even in an empty dir.
    dispatch_eval
    [[ "$output" != *"FAIL:"* ]]
    [[ "$output" == *"failures=0"* ]]
}
