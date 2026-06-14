#!/usr/bin/env bash
# 40-session.sh — agent session initialization.
#
# Purpose: SESSION_ID/TRACE_ID/TASK_ID creation, session dir/log setup, and
#   session.start logging.
# Allowed dependencies: 00-env.sh (session/log/snapshot dirs), 30-logging.sh
#   (log_json). No snapshot create/apply, rollback, or secret scanning.

[[ "${AI_LIB_SESSION_LOADED:-0}" == "1" ]] && return 0
AI_LIB_SESSION_LOADED=1

agent_session_init() {
    local name="${1:-$(basename "$0" .sh)}"
    local session_base="$AI_SESSION_DIR"
    local log_dir="$AI_LOG_DIR"
    local snapshot_dir="$AI_SNAPSHOT_DIR"
    SESSION_ID="${SESSION_ID:-${name}-$(date +%Y%m%d-%H%M%S)-$$}"
    TRACE_ID="${TRACE_ID:-trc-${SESSION_ID}}"
    TASK_ID="${TASK_ID:-tsk-${SESSION_ID}}"
    SESSION_DIR="${session_base}/${SESSION_ID}"
    # SESSION_LOG is consumed by append_log_entry (30-logging.sh); not unused.
    # shellcheck disable=SC2034
    SESSION_LOG="${SESSION_DIR}/session.jsonl"
    mkdir -p "$SESSION_DIR" "$log_dir" "$snapshot_dir"
    log_json "session.start" '{}' || true
}
