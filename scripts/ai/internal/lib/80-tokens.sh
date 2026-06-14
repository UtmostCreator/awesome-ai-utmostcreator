#!/usr/bin/env bash
# 80-tokens.sh — token estimation and preview helpers.
#
# Purpose: bytes/4 fallback, TOKEN_ESTIMATOR_CMD support, file token-budget
#   check, and preview truncation.
# Allowed dependencies: 05-core.sh (command_exists, log_warn). No file discovery,
#   context packing, repomix, or search logic.

[[ "${AI_LIB_TOKENS_LOADED:-0}" == "1" ]] && return 0
AI_LIB_TOKENS_LOADED=1

estimate_tokens_string() {
    local s="${1-}"
    local n=${#s}
    echo $(((n + 3) / 4))
}

truncate_file_preview() {
    local file="${1:?file required}" max="${2:-4096}"
    if command_exists head; then
        head -c "$max" "$file"
    else
        cat "$file"
    fi
}

estimate_file_tokens_fallback() {
    local file="${1:?file required}"
    local bytes
    bytes="$(wc -c <"$file" | tr -d ' ')"
    echo $(((bytes + 3) / 4))
}

estimate_tokens() {
    local file="${1:?file required}"

    if [[ -n "${TOKEN_ESTIMATOR_CMD:-}" ]]; then
        local estimated=""

        estimated="$($TOKEN_ESTIMATOR_CMD "$file" 2>/dev/null || true)"

        if [[ "$estimated" =~ ^[0-9]+$ ]]; then
            printf '%s\n' "$estimated"
            return 0
        fi

        log_warn "TOKEN_ESTIMATOR_CMD failed or returned non-integer output; falling back to bytes/4"
    fi

    estimate_file_tokens_fallback "$file"
}

within_token_budget() {
    local file="${1:?file required}"
    local max="${2:-128000}"
    local tokens
    tokens="$(estimate_tokens "$file")"
    ((tokens <= max))
}
