#!/usr/bin/env bash
# 30-logging.sh — structured event logging.
#
# Purpose: JSONL event writing, optional session-log append, durable PHP log
#   integration, and log rotation.
# Allowed dependencies: 00-env.sh (log/event dirs), 20-paths.sh (git_root).
#   Logging may read git metadata; it must not mutate repo files except logs.
#   No session-ID generation, approval policy, or command execution.

[[ "${AI_LIB_LOGGING_LOADED:-0}" == "1" ]] && return 0
AI_LIB_LOGGING_LOADED=1

append_log_entry() {
    local entry="${1:?entry required}"
    local repo_root
    local log_dir="$AI_LOG_DIR"
    local event_log="$AI_EVENT_LOG"

    mkdir -p "$log_dir"
    printf '%s\n' "$entry" >>"$event_log"

    if [[ -n "${SESSION_LOG:-}" ]]; then
        printf '%s\n' "$entry" >>"$SESSION_LOG"
    fi

    if [[ "${AI_SESSION_DURABLE_LOG:-1}" == "1" && -n "${SESSION_ID:-}" ]]; then
        repo_root="$(git_root 2>/dev/null || pwd)"
        if command -v php >/dev/null 2>&1 && [[ -f "$repo_root/tools/ai/agent-log.php" ]]; then
            php "$repo_root/tools/ai/agent-log.php" --root "$repo_root" --session-id "$SESSION_ID" --event-json "$entry" >/dev/null 2>&1 || true
        fi
    fi
}

log_json() {
    local event="${1:-event}"
    local payload="${2:-{}}"
    local caller="${3:-$(basename "${BASH_SOURCE[1]:-unknown}" .sh)}"
    local payload_json
    local entry

    if ! payload_json="$(jq -c . <<<"$payload" 2>/dev/null)"; then
        payload_json="$(jq -cn --arg raw "$payload" '{raw:$raw}')"
    fi

    entry="$(jq -cn \
        --arg event_version "2.0" \
        --arg event_type "$event" \
        --arg trace_id "${TRACE_ID:-unknown}" \
        --arg session_id "${SESSION_ID:-unknown}" \
        --arg task_id "${TASK_ID:-unknown}" \
        --arg timestamp "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
        --arg actor_id "${ACTOR_ID:-$caller}" \
        --arg delegated_by "${DELEGATED_BY:-}" \
        --arg tool_name "$caller" \
        --arg repo_root "$(git_root 2>/dev/null || pwd)" \
        --arg git_branch "$(git rev-parse --abbrev-ref HEAD 2>/dev/null || printf 'unknown')" \
        --arg git_commit "$(git rev-parse HEAD 2>/dev/null || printf 'unknown')" \
        --argjson data "$payload_json" \
        '{
                    event_version: $event_version,
                    event_type: $event_type,
                    trace_id: $trace_id,
                    session_id: $session_id,
                    task_id: $task_id,
                    timestamp: $timestamp,
                    actor: {
                        type: "agent",
                        id: $actor_id,
                        delegated_by: (if $delegated_by == "" then null else $delegated_by end)
                    },
                    tool: {
                        name: $tool_name,
                        category: null,
                        args_hash: null,
                        mutates_state: false
                    },
                    authorization: {
                        policy_version: null,
                        decision: "unknown",
                        approval_required: null,
                        approved_by: null,
                        reason: null
                    },
                    execution: {
                        status: "unknown",
                        latency_ms: null,
                        retry_count: 0,
                        exit_code: null,
                        output_truncated: null
                    },
                    cost: {
                        model: null,
                        input_tokens: null,
                        output_tokens: null,
                        estimated_cost_usd: null
                    },
                    failure: {
                        category: null,
                        message: null,
                        resolution: null
                    },
                    repository: {
                        root: $repo_root,
                        git_branch: (if $git_branch == "" or $git_branch == "unknown" then null else $git_branch end),
                        git_commit: (if $git_commit == "" or $git_commit == "unknown" then null else $git_commit end)
                    },
                    output: {
                        preview: null
                    },
                    details: (if ($data | type) == "object" then $data else {raw: $data} end)
                }')"

    append_log_entry "$entry"
}

rotate_log_if_needed_locked() {
    local file="${1:?file required}"
    local max="${AI_LOG_MAX_BYTES:-1048576}"
    [[ -f "$file" ]] || return 0
    local size
    size="$(wc -c <"$file" | tr -d ' ')"
    if ((size > max)); then
        mv "$file" "$file.$(date +%s).bak"
    fi
}

append_jsonl_safe() {
    local file="${1:?file required}" line="${2:?line required}"
    mkdir -p "$(dirname "$file")"
    printf '%s\n' "$line" >>"$file"
}
