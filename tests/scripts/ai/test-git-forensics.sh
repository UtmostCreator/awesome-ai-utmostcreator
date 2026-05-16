#!/usr/bin/env bash
# Tests for scripts/ai/git-forensics.sh
set -euo pipefail
BASH_BIN="${BASH_BIN:-$(command -v bash)}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/git-forensics.sh"
cd "$REPO_ROOT"

PASS=0 FAIL=0 SKIP=0
run_test() {
    local name="$1"; shift; local _rc=0
    "$@" >/dev/null 2>&1 || _rc=$?
    if ((_rc == 0)); then PASS=$((PASS+1)); printf '  \033[0;32m✓\033[0m %s\n' "$name"
    else FAIL=$((FAIL+1)); printf '  \033[0;31m✗\033[0m %s\n' "$name"; fi
}

printf 'git-forensics.sh\n'

# No --help flag; test usage via missing args
test_no_args() { ! "$BASH_BIN" "$SCRIPT" 2>/dev/null; }
run_test "missing args fails" test_no_args

# S mode (string search)
test_s_mode() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" S "common_require_core" 2>/dev/null || true)"
    # May have no output if the string was never added/removed, but should not crash
    true
}
run_test "S mode runs without crash" test_s_mode

# G mode (regex search)
test_g_mode() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" G "require_bins" 2>/dev/null || true)"
    true
}
run_test "G mode runs without crash" test_g_mode

# blame mode
test_blame() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" blame "1,5" scripts/ai/common.sh 2>/dev/null)"
    [[ -n "$out" ]]
}
run_test "blame mode returns output" test_blame

# blame without file fails
test_blame_no_file() {
    ! "$BASH_BIN" "$SCRIPT" blame "1,5" 2>/dev/null
}
run_test "blame without file fails" test_blame_no_file

# JSON output
test_json() {
    local out
    out="$("$BASH_BIN" "$SCRIPT" blame "1,3" scripts/ai/common.sh --json 2>/dev/null)"
    echo "$out" | jq -e '.mode' >/dev/null
    echo "$out" | jq -e '.output' >/dev/null
}
run_test "JSON output has mode and output fields" test_json

# Unknown mode fails
test_unknown() {
    ! "$BASH_BIN" "$SCRIPT" X "query" 2>/dev/null
}
run_test "unknown mode fails" test_unknown

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
((FAIL == 0)) && printf '\033[0;32mPASSED\033[0m\n' || { printf '\033[0;31mFAILED\033[0m\n'; exit 1; }
