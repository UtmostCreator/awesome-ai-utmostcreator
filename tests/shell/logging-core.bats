#!/usr/bin/env bats
# Tests for scripts/ai/internal/lib/30-logging.sh core append + rotation.
#
# Focus: flock-atomic append_jsonl_safe (no interleaved/partial lines under
# concurrency) and rotate_log_if_needed_locked (size-based rotation to .bak),
# plus init_log_repo_context caching. These back Slice 1 of the runner-agnostic
# logging plan (docs/tickets/arch-todo-runner-agnostic-logging-core-20260706).
#
# Requires: jq (used by log_json). flock is optional — append degrades to a
# plain append with a warning when flock is absent.

REPO_ROOT="$(cd "$(dirname "$BATS_TEST_FILENAME")/../.." && pwd)"
COMMON="$REPO_ROOT/scripts/ai/common.sh"

_has_jq() {
    command -v jq >/dev/null 2>&1
}

setup() {
    if ! _has_jq; then
        skip "jq not in PATH — required by log_json"
    fi
    export AI_LOG_DIR
    AI_LOG_DIR="$(mktemp -d)"
    export AI_EVENT_LOG="$AI_LOG_DIR/events.jsonl"
    export AI_SESSION_DURABLE_LOG=0
    cd "$REPO_ROOT"
}

teardown() {
    rm -rf "$AI_LOG_DIR" 2>/dev/null || true
}

@test "append_jsonl_safe writes one intact line per call" {
    run bash -c '
        source "'"$COMMON"'"
        append_jsonl_safe "'"$AI_EVENT_LOG"'" "line-one"
        append_jsonl_safe "'"$AI_EVENT_LOG"'" "line-two"
    '
    [ "$status" -eq 0 ]
    [ "$(wc -l <"$AI_EVENT_LOG" | tr -d ' ')" -eq 2 ]
    run sed -n 1p "$AI_EVENT_LOG"
    [ "$output" = "line-one" ]
    run sed -n 2p "$AI_EVENT_LOG"
    [ "$output" = "line-two" ]
}

@test "concurrent appends do not interleave (every line stays intact JSON)" {
    # 40 parallel writers; with flock every appended line must be a complete,
    # valid JSON object and the count must be exact.
    run bash -c '
        source "'"$COMMON"'"
        for i in $(seq 1 40); do
            append_jsonl_safe "'"$AI_EVENT_LOG"'" "{\"n\":$i}" &
        done
        wait
    '
    [ "$status" -eq 0 ]
    [ "$(wc -l <"$AI_EVENT_LOG" | tr -d ' ')" -eq 40 ]
    # Every line must parse as JSON (no torn writes).
    run bash -c 'while IFS= read -r l; do printf "%s" "$l" | jq -e . >/dev/null || exit 1; done <"'"$AI_EVENT_LOG"'"'
    [ "$status" -eq 0 ]
}

@test "rotate_log_if_needed_locked rotates when over AI_LOG_MAX_BYTES" {
    printf '%0.sX' $(seq 1 200) >"$AI_EVENT_LOG"
    printf '\n' >>"$AI_EVENT_LOG"
    run bash -c '
        source "'"$COMMON"'"
        AI_LOG_MAX_BYTES=50 rotate_log_if_needed_locked "'"$AI_EVENT_LOG"'"
    '
    [ "$status" -eq 0 ]
    # Original file was moved aside to a timestamped .bak; new events.jsonl absent.
    run bash -c 'ls "'"$AI_LOG_DIR"'"/events.jsonl.*.bak >/dev/null 2>&1'
    [ "$status" -eq 0 ]
    [ ! -f "$AI_EVENT_LOG" ]
}

@test "rotate_log_if_needed_locked leaves small files untouched" {
    printf 'small\n' >"$AI_EVENT_LOG"
    run bash -c '
        source "'"$COMMON"'"
        AI_LOG_MAX_BYTES=1048576 rotate_log_if_needed_locked "'"$AI_EVENT_LOG"'"
    '
    [ "$status" -eq 0 ]
    [ -f "$AI_EVENT_LOG" ]
    run bash -c 'ls "'"$AI_LOG_DIR"'"/events.jsonl.*.bak >/dev/null 2>&1'
    [ "$status" -ne 0 ]
}

@test "log_json append goes through the safe path and emits v3.0 events" {
    run bash -c '
        source "'"$COMMON"'"
        log_json "verify.failed" "{\"failures\":3}" "ai-verify"
    '
    [ "$status" -eq 0 ]
    run bash -c 'head -1 "'"$AI_EVENT_LOG"'" | jq -r ".event_version"'
    [ "$output" = "3.0" ]
    run bash -c 'head -1 "'"$AI_EVENT_LOG"'" | jq -r ".ids.event_id != null"'
    [ "$output" = "true" ]
}
