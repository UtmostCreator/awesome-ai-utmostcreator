#!/usr/bin/env bash
# Tests for scripts/ai/gh-pr-context.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/gh-pr-context.sh"
cd "$REPO_ROOT"
BASH_BIN="${BASH_BIN:-$(command -v bash)}"

PASS=0 FAIL=0 SKIP=0
run_test() {
    local name="$1"; shift; local _rc=0
    "$@" >/dev/null 2>&1 || _rc=$?
    if ((_rc == 0)); then PASS=$((PASS+1)); printf '  \033[0;32m✓\033[0m %s\n' "$name"
    else FAIL=$((FAIL+1)); printf '  \033[0;31m✗\033[0m %s\n' "$name"; fi
}
skip_test() { SKIP=$((SKIP+1)); printf '  \033[0;33m⊘\033[0m %s (skipped: %s)\n' "$1" "$2"; }

printf 'gh-pr-context.sh\n'

# Requires gh CLI
if ! command -v gh >/dev/null 2>&1; then
    skip_test "missing PR number fails" "gh CLI not installed"
    skip_test "unknown option fails" "gh CLI not installed"
else
    # Missing PR number fails
    test_no_pr() { ! "$BASH_BIN" "$SCRIPT" 2>/dev/null; }
    run_test "missing PR number fails" test_no_pr

    # Unknown option fails
    test_unknown() { ! "$BASH_BIN" "$SCRIPT" 1 --bogus 2>/dev/null; }
    run_test "unknown option fails" test_unknown

    # --help is in the option loop (after PR number)
    test_help() { "$BASH_BIN" "$SCRIPT" 1 --help 2>&1 | grep -q 'Usage'; }
    run_test "help flag works (after PR number)" test_help
fi

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
((FAIL == 0)) && printf '\033[0;32mPASSED\033[0m\n' || { printf '\033[0;31mFAILED\033[0m\n'; exit 1; }
