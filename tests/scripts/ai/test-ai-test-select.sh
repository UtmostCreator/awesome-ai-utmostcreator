#!/opt/homebrew/bin/bash
# Tests for scripts/ai/ai-test-select.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/ai-test-select.sh"
cd "$REPO_ROOT"

PASS=0 FAIL=0 SKIP=0
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

run_test() {
    local name="$1"; shift; local _rc=0
    "$@" >/dev/null 2>&1 || _rc=$?
    if ((_rc == 0)); then PASS=$((PASS+1)); printf '  \033[0;32m✓\033[0m %s\n' "$name"
    else FAIL=$((FAIL+1)); printf '  \033[0;31m✗\033[0m %s\n' "$name"; fi
}

BASH_BIN="/opt/homebrew/bin/bash"

printf 'ai-test-select.sh\n'

# --help
test_help() { "$BASH_BIN" "$SCRIPT" --help 2>&1 | grep -q 'Usage'; }
run_test "help flag works" test_help

# Missing mode fails
test_no_mode() { ! "$BASH_BIN" "$SCRIPT" 2>/dev/null; }
run_test "missing mode exits with error" test_no_mode

# changed mode produces JSON
test_changed() {
    local out
    out="$(AI_LOG_DIR="$TMP/logs" AI_EVENT_LOG="$TMP/logs/ev.jsonl" "$BASH_BIN" "$SCRIPT" changed 2>/dev/null)"
    echo "$out" | jq -e '.input_files' >/dev/null
    echo "$out" | jq -e '.candidate_tests' >/dev/null
    echo "$out" | jq -e '.recommended_commands' >/dev/null
}
run_test "changed mode returns JSON with required keys" test_changed

# file mode with known file
test_file_mode() {
    local out
    out="$(AI_LOG_DIR="$TMP/logs2" AI_EVENT_LOG="$TMP/logs2/ev.jsonl" "$BASH_BIN" "$SCRIPT" file scripts/ai/common.sh 2>/dev/null)"
    echo "$out" | jq -e '.input_files' >/dev/null
}
run_test "file mode returns JSON" test_file_mode

# json mode (alias for changed)
test_json_mode() {
    local out
    out="$(AI_LOG_DIR="$TMP/logs3" AI_EVENT_LOG="$TMP/logs3/ev.jsonl" "$BASH_BIN" "$SCRIPT" json 2>/dev/null)"
    echo "$out" | jq -e '.candidate_tests' >/dev/null
}
run_test "json mode returns JSON" test_json_mode

# symbol mode
test_symbol() {
    local out
    out="$(AI_LOG_DIR="$TMP/logs4" AI_EVENT_LOG="$TMP/logs4/ev.jsonl" "$BASH_BIN" "$SCRIPT" symbol "log_info" 2>/dev/null)"
    echo "$out" | jq -e '.candidate_tests' >/dev/null
}
run_test "symbol mode searches for symbol usage" test_symbol

# Unknown mode fails
test_unknown_mode() {
    ! AI_LOG_DIR="$TMP/logs5" AI_EVENT_LOG="$TMP/logs5/ev.jsonl" "$BASH_BIN" "$SCRIPT" nonexistent 2>/dev/null
}
run_test "unknown mode fails" test_unknown_mode

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
((FAIL == 0)) && printf '\033[0;32mPASSED\033[0m\n' || { printf '\033[0;31mFAILED\033[0m\n'; exit 1; }
