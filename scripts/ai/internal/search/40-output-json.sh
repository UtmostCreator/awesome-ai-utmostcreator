#!/usr/bin/env bash
# 40-output-json.sh — JSON envelope output and failure handling.
#
# Purpose: render the canonical envelope (emit_json), the error/blocked path
#   (fail), the legacy string-array helpers (to_json_array, lines_to_matches),
#   canonical_root(), and validate_non_negative_int().
# Allowed dependencies: jq; common.sh log_error. Reads run-state globals
#   (g_query, g_mode, g_results_json, g_summary_json, g_max_results, g_truncated,
#   g_warnings, json_mode).
#
# Load-order constraint: emit_json() MUST be defined before fail(), because
#   fail() calls emit_json() in JSON mode.
#
# SC2154: g_query/g_mode/g_results_json/g_summary_json/g_max_results/g_truncated/
# g_warnings/json_mode are run-state globals set by sibling modules.
# shellcheck disable=SC2154

# to_json_array ITEMS... — render arguments as a JSON string array, dropping
# empty entries. Prints [] when called with no arguments.
to_json_array() {
    if [[ "$#" -eq 0 ]]; then
        printf '[]'
    else
        printf '%s\n' "$@" | jq -R -s 'split("\n") | map(select(length > 0))'
    fi
}

# emit_json STATUS [MATCHES_JSON] [ERRORS_JSON] [WARNINGS_JSON]
# Renders the canonical envelope. Warnings default to g_warnings.
emit_json() {
    local status="$1" matches_json="${2:-[]}" errors="${3:-[]}" warnings="${4:-}"
    local returned truncated="${g_truncated:-false}"

    if [[ -z "$warnings" ]]; then
        warnings="$(to_json_array "${g_warnings[@]}")"
    fi

    returned="$(printf '%s' "$matches_json" | jq 'length')"

    jq -cn \
        --arg schema "1" \
        --arg status "$status" \
        --arg tool "ai-search" \
        --arg query "${g_query:-}" \
        --arg mode "${g_mode:-}" \
        --argjson matches "$matches_json" \
        --argjson results "${g_results_json:-[]}" \
        --argjson errors "$errors" \
        --argjson warnings "$warnings" \
        --argjson max_results "$g_max_results" \
        --argjson returned "$returned" \
        --argjson truncated "$truncated" \
        --argjson summary "${g_summary_json:-null}" \
        '{
            schema: $schema,
            status: $status,
            tool: $tool,
            query: $query,
            mode: $mode,
            matches: $matches,
            results: $results,
            warnings: $warnings,
            errors: $errors,
            limits: { max_results: $max_results },
            meta: { returned: $returned, truncated: $truncated }
        }
        | if $summary != null then .summary = $summary else . end'
}

# fail STATUS MESSAGE [RC] — emit an error/blocked/unavailable envelope in JSON
# mode, or a plain stderr line otherwise, then exit.
fail() {
    local status="$1" msg="$2" rc="${3:-1}"

    if [[ "$json_mode" == "json" ]]; then
        emit_json "$status" "[]" "$(jq -cn --arg m "$msg" '[$m]')"
    else
        log_error "$msg"
    fi

    exit "$rc"
}

# lines_to_matches — turn newline-delimited backend output into a JSON string
# array, dropping empty lines.
lines_to_matches() {
    jq -R -s 'split("\n") | map(select(length > 0))'
}

canonical_root() {
    local root_input="$1"

    if git -C "$root_input" rev-parse --show-toplevel >/dev/null 2>&1; then
        git -C "$root_input" rev-parse --show-toplevel
    else
        (
            cd "$root_input" 2>/dev/null && pwd -P
        )
    fi
}

validate_non_negative_int() {
    local flag="$1"
    local value="$2"

    if [[ ! "$value" =~ ^[0-9]+$ ]]; then
        fail "error" "$flag requires a non-negative integer"
    fi
}
