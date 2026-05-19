#!/usr/bin/env bash
# Tests for scripts/ai/ai-task.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/ai-task.sh"
cd "$REPO_ROOT"
BASH_BIN="${BASH_BIN:-$(command -v bash)}"

PASS=0 FAIL=0 SKIP=0
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

run_test() {
    local name="$1"; shift; local _rc=0
    "$@" >/dev/null 2>&1 || _rc=$?
    if ((_rc == 0)); then PASS=$((PASS+1)); printf '  \033[0;32m✓\033[0m %s\n' "$name"
    else FAIL=$((FAIL+1)); printf '  \033[0;31m✗\033[0m %s\n' "$name"; fi
}

printf 'ai-task.sh\n'

# --help
test_help() { "$BASH_BIN" "$SCRIPT" --help 2>&1 | grep -q 'Usage'; }
run_test "help flag works" test_help

# list mode produces JSON inventory
test_list() {
    local out
    out="$(AI_LOG_DIR="$TMP/logs" AI_EVENT_LOG="$TMP/logs/ev.jsonl" "$BASH_BIN" "$SCRIPT" list 2>/dev/null)"
    echo "$out" | jq -e '.package_manager' >/dev/null
    echo "$out" | jq -e '.composer_scripts' >/dev/null
    echo "$out" | jq -e '.just_tasks' >/dev/null
}
run_test "list mode returns inventory JSON" test_list

# json mode (alias for list)
test_json() {
    local out
    out="$(AI_LOG_DIR="$TMP/logs2" AI_EVENT_LOG="$TMP/logs2/ev.jsonl" "$BASH_BIN" "$SCRIPT" json 2>/dev/null)"
    echo "$out" | jq -e '.package_manager' >/dev/null
}
run_test "json mode returns inventory JSON" test_json

# verify recommends a command
test_verify() {
    local out
    out="$(AI_LOG_DIR="$TMP/logs3" AI_EVENT_LOG="$TMP/logs3/ev.jsonl" "$BASH_BIN" "$SCRIPT" verify 2>/dev/null)"
    [[ -n "$out" ]]
}
run_test "verify mode recommends a command" test_verify

# test recommends a command
test_test() {
    local out
    out="$(AI_LOG_DIR="$TMP/logs4" AI_EVENT_LOG="$TMP/logs4/ev.jsonl" "$BASH_BIN" "$SCRIPT" test 2>/dev/null)"
    [[ -n "$out" ]]
}
run_test "test mode recommends a command" test_test

# lint recommends a command
test_lint() {
    local out
    out="$(AI_LOG_DIR="$TMP/logs5" AI_EVENT_LOG="$TMP/logs5/ev.jsonl" "$BASH_BIN" "$SCRIPT" lint 2>/dev/null)"
    [[ -n "$out" ]]
}
run_test "lint mode recommends a command" test_lint

# typecheck recommends a command
test_typecheck() {
    local out
    out="$(AI_LOG_DIR="$TMP/logs6" AI_EVENT_LOG="$TMP/logs6/ev.jsonl" "$BASH_BIN" "$SCRIPT" typecheck 2>/dev/null)"
    [[ -n "$out" ]]
}
run_test "typecheck mode recommends a command" test_typecheck

# Unknown mode fails
test_unknown() {
    ! AI_LOG_DIR="$TMP/logs7" AI_EVENT_LOG="$TMP/logs7/ev.jsonl" "$BASH_BIN" "$SCRIPT" nonexistent 2>/dev/null
}
run_test "unknown mode fails" test_unknown

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
((FAIL == 0)) && printf '\033[0;32mPASSED\033[0m\n' || { printf '\033[0;31mFAILED\033[0m\n'; exit 1; }
