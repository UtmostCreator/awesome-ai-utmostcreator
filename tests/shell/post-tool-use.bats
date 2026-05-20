#!/usr/bin/env bats
# Tests for scripts/ai/post-tool-use.sh
#
# Input: full tool event JSON on stdin with toolName, toolArgs, toolResult, durationMs.
# Output: appends a JSONL evidence event to $COPILOT_LOG_DIR/tool-usage.jsonl
#
# Exact log path: $COPILOT_LOG_DIR/tool-usage.jsonl (COPILOT_LOG_DIR default = .ai-logs)
# JSONL fields: event_version, event_type, trace_id, session_id, task_id,
#   actor, tool, authorization, execution, failure, repository, output, details
# Exact normalized failure categories used here:
#   tool_timeout | approval_missing | tool_unavailable | authorization_denied
#   unsafe_mutation_blocked | invalid_tool_input | external_dependency_failure
#   test_failure | lint_failure | schema_validation_failure | unknown
#
# Requires: jq (used internally by post-tool-use.sh).

REPO_ROOT="$(cd "$(dirname "$BATS_TEST_FILENAME")/../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/post-tool-use.sh"

_has_jq() {
    command -v jq >/dev/null 2>&1
}

setup() {
    if ! _has_jq; then
        skip "jq not in PATH — required by post-tool-use.sh"
    fi

    export COPILOT_LOG_DIR
    COPILOT_LOG_DIR="$(mktemp -d)"

    export HOME
    HOME="$(mktemp -d)"
    export XDG_CONFIG_HOME
    XDG_CONFIG_HOME="$(mktemp -d)"
    export GIT_CONFIG_GLOBAL=/dev/null

    cd "$REPO_ROOT"
}

teardown() {
    rm -rf "$COPILOT_LOG_DIR" "$HOME" "$XDG_CONFIG_HOME" 2>/dev/null || true
}

# ---- helpers ----

_success_event() {
    cat <<'EOF'
{
    "toolName": "bash",
    "toolArgs": {"command": "ls ."},
    "toolResult": {"resultType": "success", "output": "file.txt"},
    "durationMs": 42
}
EOF
}

_error_event() {
    local error_msg="${1:-command not found}"
    printf '{"toolName":"bash","toolArgs":{"command":"bad-cmd"},"toolResult":{"resultType":"error","isError":true,"error":"%s"},"durationMs":10}' "$error_msg"
}

_run_hook() {
    echo "$1" | bash "$SCRIPT"
}

_log_file() {
    echo "$COPILOT_LOG_DIR/tool-usage.jsonl"
}

# ---- success path ----

@test "success event creates tool-usage.jsonl" {
    _run_hook "$(_success_event)"
    [ -f "$(_log_file)" ]
}

@test "success event writes valid JSONL" {
    _run_hook "$(_success_event)"
    jq . "$(_log_file)" >/dev/null
}

@test "success event JSONL contains ts field" {
    _run_hook "$(_success_event)"
    jq -e '.timestamp' "$(_log_file)" >/dev/null
}

@test "success event JSONL contains tool field" {
    _run_hook "$(_success_event)"
    jq -e '.tool.name == "bash"' "$(_log_file)" >/dev/null
}

@test "success event JSONL contains event identity fields" {
    _run_hook "$(_success_event)"
    jq -e '.event_version and .event_type and .trace_id and .session_id and .task_id' "$(_log_file)" >/dev/null
}

@test "success event JSONL contains normalized authorization and execution fields" {
    _run_hook "$(_success_event)"
    jq -e '.authorization.decision == "allowed" and .execution.status == "success" and .execution.latency_ms == 42' "$(_log_file)" >/dev/null
}

# ---- error path — failure categories ----

@test "error with 'not found' maps to tool_unavailable category" {
    _run_hook "$(_error_event "binary not found")"
    category=$(jq -r '.failure.category' "$(_log_file)")
    [ "$category" = "tool_unavailable" ]
}

@test "error with 'missing' maps to tool_unavailable category" {
    _run_hook "$(_error_event "tool missing from PATH")"
    category=$(jq -r '.failure.category' "$(_log_file)")
    [ "$category" = "tool_unavailable" ]
}

@test "error with 'denied' maps to authorization_denied category" {
    _run_hook "$(_error_event "command denied by policy")"
    category=$(jq -r '.failure.category' "$(_log_file)")
    [ "$category" = "authorization_denied" ]
}

@test "error with 'permission' maps to authorization_denied category" {
    _run_hook "$(_error_event "permission denied")"
    category=$(jq -r '.failure.category' "$(_log_file)")
    [ "$category" = "authorization_denied" ]
}

@test "error with 'unknown option' maps to invalid_tool_input category" {
    _run_hook "$(_error_event "unknown option --foo")"
    category=$(jq -r '.failure.category' "$(_log_file)")
    [ "$category" = "invalid_tool_input" ]
}

@test "error with 'network' maps to external_dependency_failure category" {
    _run_hook "$(_error_event "network connection refused")"
    category=$(jq -r '.failure.category' "$(_log_file)")
    [ "$category" = "external_dependency_failure" ]
}

@test "error with 'timeout' in resultType maps to tool_timeout category" {
    local timeout_event='{"toolName":"bash","toolArgs":{"command":"sleep 999"},"toolResult":{"resultType":"timeout","isError":true,"error":"timed out"},"durationMs":30000}'
    _run_hook "$timeout_event"
    category=$(jq -r '.failure.category' "$(_log_file)")
    status=$(jq -r '.execution.status' "$(_log_file)")
    [ "$category" = "tool_timeout" ]
    [ "$status" = "timeout" ]
}

# ---- idempotency ----

@test "running twice appends two valid JSONL lines" {
    _run_hook "$(_success_event)"
    _run_hook "$(_success_event)"
    lines=$(wc -l < "$(_log_file)")
    [ "$lines" -eq 2 ]
    # Both lines must be valid JSON
    jq -c '.' "$(_log_file)" | while IFS= read -r line; do
        echo "$line" | jq . >/dev/null
    done
}

@test "second run does not corrupt first log line" {
    _run_hook "$(_success_event)"
    first=$(head -1 "$(_log_file)")
    _run_hook "$(_success_event)"
    still_first=$(head -1 "$(_log_file)")
    [ "$first" = "$still_first" ]
}
