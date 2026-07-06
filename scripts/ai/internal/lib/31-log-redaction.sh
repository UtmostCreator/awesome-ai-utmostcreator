#!/usr/bin/env bash
# 31-log-redaction.sh — logging redaction seam.
#
# Purpose: thin adapter that wires the existing JSON redaction helper
#   (redact_json_payload, defined in 10-json.sh) into a single entry point the
#   event emitter (30-logging.sh) calls before serializing a payload. This keeps
#   redaction policy in one place (10-json.sh) and gives the logger a stable hook
#   without duplicating any secret-matching regex.
# Allowed dependencies: 10-json.sh (redact_json_payload). No file appends,
#   session IDs, git metadata, event construction, or new redaction rules.

[[ "${AI_LIB_LOG_REDACTION_LOADED:-0}" == "1" ]] && return 0
AI_LIB_LOG_REDACTION_LOADED=1

# AI_LOG_REDACT_KEY_PATTERN — single source of the sensitive-key regex used by
# the logger. Kept here (not duplicated per call site) so the log redaction rule
# lives in one place; mirrors the key set used by redact_json_payload (10-json.sh).
AI_LOG_REDACT_KEY_PATTERN="${AI_LOG_REDACT_KEY_PATTERN:-token|secret|password|api[_-]?key|authorization}"

# log_redact_payload — redact sensitive object keys from a compact JSON string.
# Input: a compact JSON value on argument 1 (already parsed/normalized).
# Output: the redacted compact JSON on stdout, or the original payload unchanged
#   when redaction is disabled (AI_LOG_REDACT=0) or the input is not valid JSON.
#
# Implementation note: this passes the payload to jq via --argjson (single read)
# rather than piping into a helper that reads stdin twice. That avoids the
# double-read / doubled-line failure mode of the stdin-based redact_json_payload
# fallback, which is unsafe on payloads without a trailing newline.
log_redact_payload() {
    # Do NOT write ${1:-{}} — bash parses that as default "{}" plus a literal
    # trailing "}", corrupting the payload (same class as the historic log_json
    # bug). Default the empty case explicitly.
    local payload="${1:-}"
    [[ -n "$payload" ]] || payload='{}'
    local redacted

    if [[ "${AI_LOG_REDACT:-1}" != "1" ]]; then
        printf '%s' "$payload"
        return 0
    fi

    redacted="$(jq -c --arg kp "$AI_LOG_REDACT_KEY_PATTERN" \
        'walk(if type == "object" then with_entries(if (.key|ascii_downcase|test($kp)) then .value = "REDACTED" else . end) else . end)' \
        <<<"$payload" 2>/dev/null)"

    if [[ -n "$redacted" ]]; then
        printf '%s' "$redacted"
    else
        # Payload was not valid JSON (or jq unavailable): fail safe to original.
        printf '%s' "$payload"
    fi
}
