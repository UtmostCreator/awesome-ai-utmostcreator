#!/usr/bin/env bash
# 10-json.sh — JSON, redaction, and envelope helpers.
#
# Purpose: payload normalization, redaction, and JSON envelope emission.
# Allowed dependencies: 05-core.sh (command_exists). No file appends, session
#   IDs, git metadata, or snapshot logic.

[[ "${AI_LIB_JSON_LOADED:-0}" == "1" ]] && return 0
AI_LIB_JSON_LOADED=1

json_available() {
    command_exists jq
}

redact_sensitive_text() {
    local in
    in="$(cat)"
    in="$(printf '%s' "$in" | sed -E 's/([Tt]oken|[Pp]assword|[Aa]pi[_-]?[Kk]ey|[Ss]ecret)[[:space:]]*=[[:space:]]*[^[:space:]]+/\1=REDACTED/g')"
    in="$(printf '%s' "$in" | sed -E 's/(Authorization:[[:space:]]*Bearer)[[:space:]]+[^[:space:]]+/\1 REDACTED/g')"
    in="$(printf '%s' "$in" | sed -E 's/[A-Za-z0-9_\/+=-]{48,}/REDACTED_LONG_SECRET/g')"
    printf '%s' "$in"
}

redact_json_payload() {
    jq -c 'walk(if type == "object" then with_entries(if (.key|ascii_downcase|test("token|secret|password|api[_-]?key|authorization")) then .value = "REDACTED" else . end) else . end)' 2>/dev/null || jq -cn --arg raw "$(cat)" '{raw:$raw}'
}

json_compact_or_raw() {
    local payload="${1:-}"
    jq -c . <<<"$payload" 2>/dev/null || jq -cn --arg raw "$payload" '{raw:$raw}'
}

emit_envelope() {
    local status="${1:-ok}" tool="${2:-unknown}" content="${3:-{}}" warnings="${4:-[]}" errors="${5:-[]}" elapsed="${6:-0}" truncated="${7:-false}"
    local parsed_content parsed_warnings parsed_errors parsed_truncated
    parsed_content="$(json_compact_or_raw "$content")"
    parsed_warnings="$(jq -c . <<<"$warnings" 2>/dev/null || printf '[]')"
    parsed_errors="$(jq -c . <<<"$errors" 2>/dev/null || printf '[]')"
    parsed_truncated="$(jq -c . <<<"$truncated" 2>/dev/null || printf 'false')"
    jq -cn \
        --arg schema "1" \
        --arg status "$status" \
        --arg tool "$tool" \
        --arg content_raw "$parsed_content" \
        --arg warnings_raw "$parsed_warnings" \
        --arg errors_raw "$parsed_errors" \
        --arg elapsed_raw "$elapsed" \
        --arg truncated_raw "$parsed_truncated" \
        '{
        schema: ($schema|tonumber),
        status: $status,
        tool: $tool,
        content: (try ($content_raw|fromjson) catch {raw:$content_raw}),
        warnings: (try ($warnings_raw|fromjson) catch []),
        errors: (try ($errors_raw|fromjson) catch []),
        meta: {
          elapsed_ms: (try ($elapsed_raw|tonumber) catch 0),
          truncated: (try ($truncated_raw|fromjson) catch false)
        }
      }'
}

emit_blocked_envelope() {
    local reason="${1:-blocked}"
    local errors_json
    errors_json="$(jq -cn --arg reason "$reason" '[$reason]')"
    emit_envelope "unsafe_blocked" "unknown" '{}' '[]' "$errors_json" 0 false
}
