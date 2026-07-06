#!/usr/bin/env bats
# Tests for the Gradle tool-availability policy
# (scripts/ai/internal/ai-verify/61-gradle-policy.sh).
#
# As of docs/tickets/arch-todo-android-kmp-verify-lane-20260706-010421 §8-P0-a,
# this module is NOT YET sourced by scripts/ai/ai-verify.sh, so these tests
# source it directly (after common.sh, mirroring every sibling module's real
# load order) inside a throwaway tmp dir with a stubbed ./gradlew, so the real
# Gradle is never invoked and no network/daemon is touched. Mirrors
# tests/shell/ai-verify-tool-policy.bats.

REPO_ROOT="$(cd "$(dirname "$BATS_TEST_FILENAME")/../.." && pwd)"
COMMON="$REPO_ROOT/scripts/ai/common.sh"
GRADLE_POLICY="$REPO_ROOT/scripts/ai/internal/ai-verify/61-gradle-policy.sh"

setup() {
    TMP_DIR="$(mktemp -d)"
}

teardown() {
    rm -rf "$TMP_DIR" 2>/dev/null || true
}

# Sources common.sh + 61-gradle-policy.sh with $1 as cwd, then runs the
# remaining args as a command/function call, in a subshell so the `set -euo
# pipefail` picked up from common.sh never affects the bats process.
gradle_policy_eval() {
    local dir="$1"
    shift
    (
        cd "$dir" || exit 1
        source "$COMMON"
        source "$GRADLE_POLICY"
        "$@"
    )
}

# Write an executable ./gradlew stub that, when called with `tasks`, prints the
# provided task listing (one module-qualified task per line), and exits 0.
write_gradlew_stub() {
    local listing="$1"
    cat >"$TMP_DIR/gradlew" <<EOF
#!/usr/bin/env bash
if [[ "\$1" == "tasks" ]]; then
    cat <<'TASKS'
$listing
TASKS
    exit 0
fi
exit 0
EOF
    chmod +x "$TMP_DIR/gradlew"
}

# --- has_gradle_wrapper ------------------------------------------------------

@test "has_gradle_wrapper is true when ./gradlew is executable" {
    write_gradlew_stub ""
    run gradle_policy_eval "$TMP_DIR" has_gradle_wrapper
    [ "$status" -eq 0 ]
}

@test "has_gradle_wrapper is false when ./gradlew is absent" {
    run gradle_policy_eval "$TMP_DIR" has_gradle_wrapper
    [ "$status" -ne 0 ]
}

@test "has_gradle_wrapper is false when ./gradlew exists but is not executable" {
    printf '#!/usr/bin/env bash\n' >"$TMP_DIR/gradlew"
    chmod -x "$TMP_DIR/gradlew"
    run gradle_policy_eval "$TMP_DIR" has_gradle_wrapper
    [ "$status" -ne 0 ]
}

# --- gradle_task_exists ------------------------------------------------------

@test "gradle_task_exists is true for a module-qualified task present in the listing" {
    write_gradlew_stub ":app:assembleDebug - Assembles the Debug build.
:shared:check - Runs all checks.
:app:lintDebug - Runs lint on the Debug variant."
    run gradle_policy_eval "$TMP_DIR" gradle_task_exists ':app:assembleDebug'
    [ "$status" -eq 0 ]
    run gradle_policy_eval "$TMP_DIR" gradle_task_exists ':shared:check'
    [ "$status" -eq 0 ]
}

@test "gradle_task_exists is false for a task missing from the listing" {
    write_gradlew_stub ":app:assembleDebug - Assembles the Debug build."
    run gradle_policy_eval "$TMP_DIR" gradle_task_exists ':shared:allTests'
    [ "$status" -ne 0 ]
}

@test "gradle_task_exists is false (never errors) when there is no gradle wrapper" {
    run gradle_policy_eval "$TMP_DIR" gradle_task_exists ':app:assembleDebug'
    [ "$status" -ne 0 ]
}

@test "gradle_task_exists never falls back to a PATH gradle" {
    # No ./gradlew wrapper, but a PATH `gradle` that would falsely succeed if
    # the policy ever shelled out to it. It must be ignored.
    STUB_BIN="$(mktemp -d)"
    cat >"$STUB_BIN/gradle" <<'EOF'
#!/usr/bin/env bash
echo ":app:assembleDebug - PATH GRADLE SHOULD NOT BE USED"
exit 0
EOF
    chmod +x "$STUB_BIN/gradle"
    run env PATH="$STUB_BIN:$PATH" bash -c '
        set -euo pipefail
        cd "$1" || exit 1
        source "$2"
        source "$3"
        gradle_task_exists ":app:assembleDebug"
    ' _ "$TMP_DIR" "$COMMON" "$GRADLE_POLICY"
    rm -rf "$STUB_BIN"
    [ "$status" -ne 0 ]
}
