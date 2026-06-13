#!/usr/bin/env bash
# 50-results-context.sh — context-line enrichment and byte-budget trimming.
#
# Purpose: read N lines around each match (context_lines_json), attach them to
#   results[] (add_context_to_results), and trim bulky context payload past the
#   --max-bytes budget (apply_max_bytes_to_results).
# Allowed dependencies: jq, awk. Reads context_before/context_after/max_bytes
#   and sets g_truncated.
#
# SC2034/SC2154: context_before/context_after/max_bytes/g_truncated are
# run-state globals set by sibling modules.
# shellcheck disable=SC2034,SC2154

context_lines_json() {
    local file="$1"
    local start="$2"
    local end="$3"

    if [[ "$start" -gt "$end" || ! -f "$file" ]]; then
        printf '[]'
        return 0
    fi

    awk -v start="$start" -v end="$end" '
        NR >= start && NR <= end {
            printf "%d\t%s\n", NR, $0
        }
    ' "$file" | jq -R -s '
        split("\n")
        | map(select(length > 0))
        | map(
            capture("^(?<line>[0-9]+)\t(?<text>.*)$")
            | .line = (.line | tonumber)
          )
    '
}

add_context_to_results() {
    local root_abs="$1"
    local results_json="$2"
    local result path line file before_start before_end after_start after_end
    local before_json after_json enriched first output

    if [[ "$context_before" -eq 0 && "$context_after" -eq 0 ]]; then
        printf '%s' "$results_json"
        return 0
    fi

    first=1
    output="["

    while IFS= read -r result; do
        [[ -n "$result" ]] || continue

        path="$(printf '%s' "$result" | jq -r '.path // ""')"
        line="$(printf '%s' "$result" | jq -r '.line // 0')"

        if [[ -z "$path" || "$line" -le 0 ]]; then
            before_json="[]"
            after_json="[]"
        else
            file="$root_abs/$path"

            before_start=$((line - context_before))
            before_end=$((line - 1))
            after_start=$((line + 1))
            after_end=$((line + context_after))

            [[ "$before_start" -lt 1 ]] && before_start=1

            before_json="$(context_lines_json "$file" "$before_start" "$before_end")"
            after_json="$(context_lines_json "$file" "$after_start" "$after_end")"
        fi

        enriched="$(
            jq -cn \
                --argjson result "$result" \
                --argjson before "$before_json" \
                --argjson after "$after_json" \
                '$result + { context: { before: $before, after: $after } }'
        )"

        if [[ "$first" -eq 1 ]]; then
            output+="$enriched"
            first=0
        else
            output+=",$enriched"
        fi
    done < <(printf '%s' "$results_json" | jq -c '.[]')

    output+="]"
    printf '%s' "$output"
}

apply_max_bytes_to_results() {
    local results_json="$1"
    local bytes

    if [[ "$max_bytes" -eq 0 ]]; then
        printf '%s' "$results_json"
        return 0
    fi

    bytes="$(printf '%s' "$results_json" | wc -c | tr -d ' ')"

    if [[ "$bytes" -le "$max_bytes" ]]; then
        printf '%s' "$results_json"
        return 0
    fi

    g_truncated=true

    # Minimal safe truncation for Phase 3B: preserve result objects and match
    # identity, but remove bulky context payload.
    printf '%s' "$results_json" | jq '
        map(
            if has("context") then
                .context.before = [] | .context.after = []
            else
                .
            end
        )
    '
}
