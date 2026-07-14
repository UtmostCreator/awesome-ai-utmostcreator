# shellcheck shell=bash
# Kotlin / Android / KMP verification dispatcher for the AI verification gate.
#
# Part of docs/tickets/arch-todo-android-kmp-verify-lane-20260706-010421
# (§8-P0-b). This module is intended to be sourced by scripts/ai/ai-verify.sh
# (the thin root loader); it is NOT an entrypoint and must not be executed
# directly. AS OF THIS SLICE (P0) it is NOT YET sourced by ai-verify.sh -- this
# lane must not be wired anywhere yet, and there is intentionally NO
# `--language kotlin` flag, NO ai-verify-kotlin.sh wrapper, and NO registry
# entry. Until then it is exercised only by tests
# (see tests/shell/ai-verify-kotlin-dispatch.bats).
#
# FOLD-IN TARGET (plan.md §1b FLAG-C): ai_verify_kotlin mirrors the sibling
# ticket's intended 53-language-dispatch.sh: ai_verify_language contract, so a
# later unblocked slice can register `kotlin` as a sixth language lane.
#
# Reuse (plan.md §1b FLAG-A): this dispatcher does NOT define its own step
# runner. It calls run_step (40-step-runner.sh) -- inheriting the watchdog,
# VERIFY_TIMEOUT, and the global $failures tally -- and gates every Gradle task
# on gradle_task_exists / has_gradle_wrapper (61-gradle-policy.sh). It never runs
# a task the project does not define, and never runs any tool but ./gradlew.
# shellcheck disable=SC2154 # $failures is a global owned by the caller/root loader.
#
# STUB MODE: when AI_KOTLIN_TEST_MODE=1, gradle tasks are not executed; each
# would-be task is announced via a `PLAN:` line instead. This lets the unit
# tests assert exactly which tasks the dispatcher selects (default vs
# VERIFY_FULL vs VERIFY_IOS) without a real Gradle project, mirroring the
# AI_VERIFY_TEST_MODE stub pattern used elsewhere in this pipeline.

# The P0 default hard gate for the Android/KMP lane. These prove the active
# Android host builds, the shared KMP module's checks pass, Android lint is
# clean, and the shared common/Android source sets still compile.
AI_KOTLIN_DEFAULT_TASKS=(
    ':app:assembleDebug'
    ':shared:check'
    ':app:lintDebug'
    ':shared:compileKotlinMetadata'
    ':shared:compileDebugKotlinAndroid'
)

# Full-mode additions (VERIFY_FULL=1): device/instrumented + full KMP test
# matrix. Guarded by existence so a repo without a connected device task or an
# allTests aggregate task skips them cleanly.
AI_KOTLIN_FULL_TASKS=(
    ':app:connectedDebugAndroidTest'
    ':shared:allTests'
)

# iOS compile lanes (VERIFY_IOS=1 only; never a default Linux/Windows gate).
AI_KOTLIN_IOS_TASKS=(
    ':shared:compileKotlinIosX64'
    ':shared:compileKotlinIosSimulatorArm64'
)

# Run a single Gradle task iff it exists, via run_step; skip cleanly otherwise.
# In stub mode, announce the selection instead of executing.
_kotlin_run_task() {
    local task="${1:?task required}"

    if [[ "${AI_KOTLIN_TEST_MODE:-0}" == "1" ]]; then
        echo "PLAN: $task"
        return 0
    fi

    if gradle_task_exists "$task"; then
        run_step "./gradlew $task" ./gradlew "$task" --console=plain
    else
        log_warn "Skipping missing Gradle task: $task"
    fi
}

# Kotlin/Android/KMP verification lane. Runs the repo-reality guards, then the
# default P0 gate, then optional full/iOS lanes. Honors AI_VERIFY_SCOPE (via the
# guards' scope helper), VERIFY_FULL, VERIFY_IOS, and VERIFY_TIMEOUT (via
# run_step). Mutates the global $failures exactly like every other lane.
ai_verify_kotlin() {
    echo "==> kotlin/android verification lane"

    # Repo-reality guards run first: they are cheap and catch module/version
    # drift before the expensive Gradle tasks. They only run outside stub mode
    # (they inspect real files / git); stub mode is only about task selection.
    if [[ "${AI_KOTLIN_TEST_MODE:-0}" != "1" ]]; then
        check_active_modules
        check_inactive_compose_app_drift
        check_version_catalog_required

        if ! has_gradle_wrapper; then
            log_warn "No ./gradlew wrapper found; skipping all Gradle tasks in the kotlin lane."
            return 0
        fi
    fi

    local task
    for task in "${AI_KOTLIN_DEFAULT_TASKS[@]}"; do
        _kotlin_run_task "$task"
    done

    if [[ "${VERIFY_FULL:-0}" == "1" ]]; then
        for task in "${AI_KOTLIN_FULL_TASKS[@]}"; do
            _kotlin_run_task "$task"
        done
    else
        log_warn "Skipping full Android/KMP test matrix. Use VERIFY_FULL=1."
    fi

    if [[ "${VERIFY_IOS:-0}" == "1" ]]; then
        for task in "${AI_KOTLIN_IOS_TASKS[@]}"; do
            _kotlin_run_task "$task"
        done
    else
        log_warn "Skipping iOS compile lane. Use VERIFY_IOS=1 on macOS."
    fi
}
