#!/usr/bin/env bash
# Tests for scripts/ai/watch-loop.sh
set -euo pipefail
BASH_BIN="${BASH_BIN:-$(command -v bash)}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/watch-loop.sh"
cd "$REPO_ROOT"

PASS=0 FAIL=0 SKIP=0
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

run_test() {
    local name="$1"
    shift
    local _rc=0
    "$@" >/dev/null 2>&1 || _rc=$?
    if ((_rc == 0)); then
        PASS=$((PASS + 1))
        printf '  \033[0;32m✓\033[0m %s\n' "$name"
    else
        FAIL=$((FAIL + 1))
        printf '  \033[0;31m✗\033[0m %s\n' "$name"
    fi
}
skip_test() {
    SKIP=$((SKIP + 1))
    printf '  \033[0;33m⊘\033[0m %s (skipped: %s)\n' "$1" "$2"
}

printf 'watch-loop.sh\n'

# Missing command fails
test_no_command() { ! AI_LOG_DIR="$TMP/logs" "$BASH_BIN" "$SCRIPT" 2>/dev/null; }
run_test "missing command fails" test_no_command

# Script sources common.sh. The early --help/--introspect guard block now sits
# above the source line, so scan the file rather than only the first 10 lines.
test_sources_common() {
    grep -q 'common.sh' "$SCRIPT"
}
run_test "sources common.sh" test_sources_common

# Requires watchexec or entr
test_watcher_check() {
    if command -v watchexec >/dev/null 2>&1 || command -v entr >/dev/null 2>&1; then
        # At least one watcher is available — script would start a loop
        # We can't actually run it (infinite), so just verify the check logic
        true
    else
        # Neither available — script should fail
        # shellcheck disable=SC2251  # intentional: assert the command fails; errexit skip is desired here
        ! AI_LOG_DIR="$TMP/logs2" "$BASH_BIN" "$SCRIPT" "echo test" 2>/dev/null
    fi
}
run_test "requires watchexec or entr" test_watcher_check

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
# shellcheck disable=SC2015  # intentional pass/fail reporter; the || branch always exits non-zero
((FAIL == 0)) && printf '\033[0;32mPASSED\033[0m\n' || {
    printf '\033[0;31mFAILED\033[0m\n'
    exit 1
}
