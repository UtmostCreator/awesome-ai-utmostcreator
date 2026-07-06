#!/usr/bin/env bash
# 30-logging.sh — structured event logging.
#
# Purpose: JSONL event writing (v3.0 schema), flock-atomic + locked-rotation
#   append, optional session-log append, durable PHP log integration, event/id
#   and sequence generation, and cached per-process git metadata.
# Allowed dependencies: 00-env.sh (log/event dirs, color vars), 20-paths.sh
#   (git_root), 31-log-redaction.sh (log_redact_payload, resolved lazily at call
#   time). Logging may read git metadata; it must not mutate repo files except
#   logs. No session-ID generation, approval policy, or command execution.

[[ "${AI_LIB_LOGGING_LOADED:-0}" == "1" ]] && return 0
AI_LIB_LOGGING_LOADED=1

# Process-scoped monotonic event counter. Seeded once per process; each event
# increments it so a session timeline can be ordered even when timestamps
# collide at second resolution.
AI_LOG_SEQUENCE="${AI_LOG_SEQUENCE:-0}"

# log_new_id — generate a short unique id with a typed prefix (e.g. evt, spn).
# Uses uuidgen when available, otherwise the same time+pid+RANDOM composition
# the session layer already uses for SESSION_ID (40-session.sh); no new dep.
log_new_id() {
    local prefix="${1:?prefix required}"
    if command -v uuidgen >/dev/null 2>&1; then
        printf '%s_%s' "$prefix" "$(uuidgen | tr '[:upper:]' '[:lower:]')"
    else
        printf '%s_%s_%s_%s' "$prefix" "$(date -u +%s)" "$$" "${RANDOM}"
    fi
}

# init_log_repo_context — resolve git metadata once per process and cache it in
# process-scoped vars, so log_json does not shell out to git on every event.
# All events in one run then share a consistent repo snapshot.
init_log_repo_context() {
    AI_LOG_REPO_ROOT="${AI_LOG_REPO_ROOT:-$(git_root 2>/dev/null || pwd)}"
    AI_LOG_GIT_BRANCH="${AI_LOG_GIT_BRANCH:-$(git rev-parse --abbrev-ref HEAD 2>/dev/null || printf 'unknown')}"
    AI_LOG_GIT_COMMIT="${AI_LOG_GIT_COMMIT:-$(git rev-parse HEAD 2>/dev/null || printf 'unknown')}"
}

# event_execution_status — derive execution.status from an event_type.
# Maps the event-type suffix to a downstream-parseable status so log
# consumers can filter success/failure without inspecting details.raw.
# Values are constrained to the evidence-event schema enum
# (schemas/ai/evidence-event.schema.json): success | error | timeout | unknown.
event_execution_status() {
    local event="${1:-}"
    case "$event" in
    *.timeout) echo timeout ;;
    *.passed | *.done | *.ok | *.success | *.created | *.apply | *.applied) echo success ;;
    *.failed | *.killed | *.error | error | *.aborted) echo error ;;
    *) echo unknown ;;
    esac
}

# event_category — derive tool.category from an event_type domain (prefix).
# This is the event taxonomy (verify/doc-check/guard/...), distinct from
# classify_command()'s shell-command taxonomy. Returns the domain or "unknown".
event_category() {
    local event="${1:-}"
    local domain="${event%%.*}"
    case "$domain" in
    verify | doc-check | guard | session | context | task | structured | test-select | \
        edit | rollback | snapshot | checkpoint | error | gh-pr-context) echo "$domain" ;;
    *) echo unknown ;;
    esac
}

append_log_entry() {
    local entry="${1:?entry required}"
    local repo_root
    local log_dir="$AI_LOG_DIR"
    local event_log="$AI_EVENT_LOG"

    mkdir -p "$log_dir"

    rotate_log_if_needed_locked "$event_log"
    append_jsonl_safe "$event_log" "$entry"

    if [[ -n "${SESSION_LOG:-}" ]]; then
        append_jsonl_safe "$SESSION_LOG" "$entry"
    fi

    if [[ "${AI_SESSION_DURABLE_LOG:-1}" == "1" && -n "${SESSION_ID:-}" ]]; then
        repo_root="${AI_LOG_REPO_ROOT:-$(git_root 2>/dev/null || pwd)}"
        if command -v php >/dev/null 2>&1 && [[ -f "$repo_root/tools/ai/agent-log.php" ]]; then
            php "$repo_root/tools/ai/agent-log.php" --root "$repo_root" --session-id "$SESSION_ID" --event-json "$entry" >/dev/null 2>&1 || true
        fi
    fi
}

log_json() {
    local event="${1:-event}"
    # Note: do NOT write ${2:-{}} — bash parses that as default "{}" plus a
    # literal trailing "}", corrupting every valid-JSON payload (historic
    # malformed details.raw "}}}" bug). Default the empty case explicitly.
    local payload="${2:-}"
    [[ -n "$payload" ]] || payload='{}'
    local caller="${3:-$(basename "${BASH_SOURCE[1]:-unknown}" .sh)}"
    local payload_json
    local entry

    if ! payload_json="$(jq -c . <<<"$payload" 2>/dev/null)"; then
        payload_json="$(jq -cn --arg raw "$payload" '{raw:$raw}')"
    fi

    # Redaction seam (31-log-redaction.sh -> 10-json redact_json_payload).
    # Applied before any field extraction so overrides and details are clean.
    if declare -F log_redact_payload >/dev/null 2>&1; then
        payload_json="$(log_redact_payload "$payload_json")"
    fi

    # Explicit-status override: a payload may set "_status" to declare the real
    # execution result (preferred); otherwise fall back to suffix inference.
    # "_status"/"_severity" are control keys and are stripped from details.
    local exec_status event_cat status_override severity
    exec_status="$(event_execution_status "$event")"
    event_cat="$(event_category "$event")"
    status_override="$(jq -r 'if type=="object" then (._status // empty) else empty end' <<<"$payload_json" 2>/dev/null)"
    case "$status_override" in
    success | error | timeout | blocked | unknown) exec_status="$status_override" ;;
    esac
    severity="$(jq -r 'if type=="object" then (._severity // empty) else empty end' <<<"$payload_json" 2>/dev/null)"
    case "$severity" in
    debug | info | warn | error) : ;;
    *) severity="$(if [[ "$exec_status" == "error" || "$exec_status" == "timeout" ]]; then printf 'error'; else printf 'info'; fi)" ;;
    esac

    # Increment the process-scoped sequence and seed cached repo metadata.
    AI_LOG_SEQUENCE=$((AI_LOG_SEQUENCE + 1))
    init_log_repo_context

    entry="$(jq -cn \
        --arg event_version "3.0" \
        --arg event_type "$event" \
        --arg event_id "$(log_new_id evt)" \
        --argjson sequence "$AI_LOG_SEQUENCE" \
        --arg severity "$severity" \
        --arg trace_id "${TRACE_ID:-unknown}" \
        --arg session_id "${SESSION_ID:-unknown}" \
        --arg task_id "${TASK_ID:-unknown}" \
        --arg timestamp "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
        --arg actor_id "${ACTOR_ID:-$caller}" \
        --arg delegated_by "${DELEGATED_BY:-}" \
        --arg tool_name "$caller" \
        --arg exec_status "$exec_status" \
        --arg event_cat "$event_cat" \
        --arg repo_root "${AI_LOG_REPO_ROOT:-$(pwd)}" \
        --arg git_branch "${AI_LOG_GIT_BRANCH:-unknown}" \
        --arg git_commit "${AI_LOG_GIT_COMMIT:-unknown}" \
        --argjson data "$payload_json" \
        '{
                    event_version: $event_version,
                    event_type: $event_type,
                    ids: {
                        event_id: $event_id,
                        span_id: null,
                        parent_span_id: null,
                        sequence: $sequence
                    },
                    severity: $severity,
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
                        category: (if $event_cat == "unknown" then null else $event_cat end),
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
                        status: $exec_status,
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
                    details: (if ($data | type) == "object" then ($data | del(._status, ._severity)) else {raw: $data} end)
                }')"

    append_log_entry "$entry"
}

# rotate_log_if_needed_locked — rotate a log file that exceeds AI_LOG_MAX_BYTES.
# Rotation is serialized under the same per-file lock as appends (via flock on
# <file>.lock) so a concurrent writer cannot append to a file mid-rename. When
# flock is unavailable the check still runs, just without cross-process locking.
rotate_log_if_needed_locked() {
    local file="${1:?file required}"
    local max="${AI_LOG_MAX_BYTES:-1048576}"
    [[ -f "$file" ]] || return 0

    mkdir -p "$(dirname "$file")"

    if command -v flock >/dev/null 2>&1; then
        (
            flock -x 9 || exit 0
            local size
            size="$(wc -c <"$file" 2>/dev/null | tr -d ' ')"
            if [[ -n "$size" ]] && ((size > max)); then
                mv "$file" "$file.$(date -u +%s).bak"
            fi
        ) 9>"${file}.lock"
    else
        local size
        size="$(wc -c <"$file" 2>/dev/null | tr -d ' ')"
        if [[ -n "$size" ]] && ((size > max)); then
            mv "$file" "$file.$(date -u +%s).bak"
        fi
    fi
}

# append_jsonl_safe — atomically append a single line to a JSONL file.
# Uses an exclusive flock on <file>.lock so parallel agents/tools cannot
# interleave partial writes. Degrades gracefully to a plain append (with a
# one-time warning) on platforms without flock (e.g. some macOS/sandbox setups),
# mirroring the exec-guard graceful-degradation pattern.
append_jsonl_safe() {
    local file="${1:?file required}" line="${2:?line required}"
    mkdir -p "$(dirname "$file")"

    if command -v flock >/dev/null 2>&1; then
        (
            flock -x 9 || {
                printf '%s\n' "$line" >>"$file"
                exit 0
            }
            printf '%s\n' "$line" >>"$file"
        ) 9>"${file}.lock"
    else
        if [[ "${AI_LOG_FLOCK_WARNED:-0}" != "1" ]]; then
            AI_LOG_FLOCK_WARNED=1
            printf '%b[WARN]%b  flock not found; log appends are not concurrency-safe\n' \
                "${_C_YELLOW:-}" "${_C_RESET:-}" >&2
        fi
        printf '%s\n' "$line" >>"$file"
    fi
}
