#!/opt/homebrew/bin/bash
# Tests for scripts/ai/post-tool-use.sh
set -euo pipefail
BASH_BIN="${BASH_BIN:-/opt/homebrew/bin/bash}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/post-tool-use.sh"
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

printf 'post-tool-use.sh\n'

# Success event produces tool.result
test_success_event() {
    local input out
    input='{"toolName":"bash","toolArgs":{"command":"ls"},"toolResult":{"resultType":"success","isError":false,"output":"file1\nfile2"}}'
    out="$(echo "$input" | AI_LOG_DIR="$TMP/logs" AI_EVENT_LOG="$TMP/logs/events.jsonl" "$BASH_BIN" "$SCRIPT" 2>/dev/null)"
    [[ -f "$TMP/logs/events.jsonl" ]]
    local event_type
    event_type="$(tail -1 "$TMP/logs/events.jsonl" | jq -r '.event_type')"
    [[ "$event_type" == "tool.result" ]]
}
run_test "success event logged as tool.result" test_success_event

# Error event produces tool.failure
test_error_event() {
    local input out
    input='{"toolName":"bash","toolArgs":{"command":"false"},"toolResult":{"resultType":"error","isError":true,"error":"command failed","exitCode":1}}'
    out="$(echo "$input" | AI_LOG_DIR="$TMP/logs2" AI_EVENT_LOG="$TMP/logs2/events.jsonl" "$BASH_BIN" "$SCRIPT" 2>/dev/null)"
    [[ -f "$TMP/logs2/events.jsonl" ]]
    local event_type
    event_type="$(tail -1 "$TMP/logs2/events.jsonl" | jq -r '.event_type')"
    [[ "$event_type" == "tool.failure" ]]
}
run_test "error event logged as tool.failure" test_error_event

# Event has required schema fields
test_schema_fields() {
    local input
    input='{"toolName":"bash","toolArgs":{"command":"echo hi"},"toolResult":{"resultType":"success","isError":false}}'
    echo "$input" | AI_LOG_DIR="$TMP/logs3" AI_EVENT_LOG="$TMP/logs3/events.jsonl" "$BASH_BIN" "$SCRIPT" 2>/dev/null
    local entry
    entry="$(tail -1 "$TMP/logs3/events.jsonl")"
    echo "$entry" | jq -e '.event_version' >/dev/null
    echo "$entry" | jq -e '.trace_id' >/dev/null
    echo "$entry" | jq -e '.session_id' >/dev/null
    echo "$entry" | jq -e '.tool.name' >/dev/null
    echo "$entry" | jq -e '.execution.status' >/dev/null
    echo "$entry" | jq -e '.authorization.decision' >/dev/null
}
run_test "event has all schema fields" test_schema_fields

# Failure classification: approval_missing
test_classify_approval() {
    local input
    input='{"toolName":"bash","toolArgs":{"command":"rm -rf"},"toolResult":{"resultType":"error","isError":true,"error":"approval required"}}'
    echo "$input" | AI_LOG_DIR="$TMP/logs4" AI_EVENT_LOG="$TMP/logs4/events.jsonl" "$BASH_BIN" "$SCRIPT" 2>/dev/null
    local entry cat
    entry="$(tail -1 "$TMP/logs4/events.jsonl")"
    cat="$(echo "$entry" | jq -r '.failure.category')"
    [[ "$cat" == "approval_missing" ]]
}
run_test "classifies approval_missing failure" test_classify_approval

# Failure classification: tool_unavailable
test_classify_unavailable() {
    local input
    input='{"toolName":"bash","toolArgs":{"command":"rg x"},"toolResult":{"resultType":"error","isError":true,"error":"required tools not found: rg"}}'
    echo "$input" | AI_LOG_DIR="$TMP/logs5" AI_EVENT_LOG="$TMP/logs5/events.jsonl" "$BASH_BIN" "$SCRIPT" 2>/dev/null
    local entry cat
    entry="$(tail -1 "$TMP/logs5/events.jsonl")"
    cat="$(echo "$entry" | jq -r '.failure.category')"
    [[ "$cat" == "tool_unavailable" ]]
}
run_test "classifies tool_unavailable failure" test_classify_unavailable

# Mutation detection: rm
test_detect_mutation_rm() {
    local input
    input='{"toolName":"bash","toolArgs":{"command":"rm file.txt"},"toolResult":{"resultType":"success","isError":false}}'
    echo "$input" | AI_LOG_DIR="$TMP/logs6" AI_EVENT_LOG="$TMP/logs6/events.jsonl" "$BASH_BIN" "$SCRIPT" 2>/dev/null
    local entry mutates
    entry="$(tail -1 "$TMP/logs6/events.jsonl")"
    mutates="$(echo "$entry" | jq -r '.tool.mutates_state')"
    [[ "$mutates" == "true" ]]
}
run_test "detects mutation for rm command" test_detect_mutation_rm

# Non-mutation: rg
test_detect_no_mutation_rg() {
    local input
    input='{"toolName":"bash","toolArgs":{"command":"rg pattern ."},"toolResult":{"resultType":"success","isError":false}}'
    echo "$input" | AI_LOG_DIR="$TMP/logs7" AI_EVENT_LOG="$TMP/logs7/events.jsonl" "$BASH_BIN" "$SCRIPT" 2>/dev/null
    local entry mutates
    entry="$(tail -1 "$TMP/logs7/events.jsonl")"
    mutates="$(echo "$entry" | jq -r '.tool.mutates_state')"
    [[ "$mutates" == "false" ]]
}
run_test "no mutation detected for rg command" test_detect_no_mutation_rg

# Args hash is consistent
test_args_hash() {
    local input
    input='{"toolName":"bash","toolArgs":{"command":"echo hi"},"toolResult":{"resultType":"success","isError":false}}'
    echo "$input" | AI_LOG_DIR="$TMP/logs8" AI_EVENT_LOG="$TMP/logs8/events.jsonl" "$BASH_BIN" "$SCRIPT" 2>/dev/null
    local entry hash
    entry="$(tail -1 "$TMP/logs8/events.jsonl")"
    hash="$(echo "$entry" | jq -r '.tool.args_hash')"
    [[ "$hash" == sha256:* ]]
}
run_test "args_hash starts with sha256:" test_args_hash

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
((FAIL == 0)) && printf '\033[0;32mPASSED\033[0m\n' || { printf '\033[0;31mFAILED\033[0m\n'; exit 1; }
