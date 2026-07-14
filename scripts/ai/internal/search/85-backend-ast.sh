#!/usr/bin/env bash
# 85-backend-ast.sh — structural (ast-grep) backend.
#
# Purpose: run_ast_mode for struct/symbols/class — emits structured results[]
#   with name/kind/path/start/end/language, and (for symbols/class) an extra
#   symbols[] array. Emits + exits directly.
# Allowed dependencies: ast-grep, jq; command_exists()/canonical_root()/
#   emit_json()/to_json_array() (common.sh + 40-output-json.sh). Reads lang_flag,
#   AI_LANG, query, mode, root, g_max_results.
#
# SC2034/SC2154: query/mode/root/lang/g_* are run-state globals; `rc` mirrors
# the pre-split backend pattern (captured, not re-checked).
# shellcheck disable=SC2034,SC2154

# run_ast_mode — Phase 5 structural search via ast-grep, emitting structured
# results[] with name/kind/path/start/end/language.
run_ast_mode() {
    # Fail closed: ast-grep has no safe grep/text equivalent (AST semantics differ),
    # so do NOT silently degrade. Point the caller to the text fallback instead.
    command_exists ast-grep ||
        fail "unavailable" "ast-grep not installed; '$mode' mode unavailable (no safe text fallback). Use: ai-search.sh text \"$query\" . --fixed"

    local lang="${lang_flag:-${AI_LANG:-php}}"
    local pattern kind=""

    case "$mode" in
    struct)
        pattern="$query"
        ;;
    class)
        kind="class"
        pattern="class $query"
        ;;
    symbols)
        # Resolve a bare name to its definition. Default to class def; callers
        # use the dedicated shortcuts for other kinds.
        kind="class"
        pattern="class $query"
        ;;
    esac

    local out rc=0 root_abs
    out="$(ast-grep run --lang "$lang" --pattern "$pattern" --json "$root" 2>/dev/null)" || rc=$?
    root_abs="$(canonical_root "$root")"

    g_results_json="$(printf '%s' "$out" | jq -c \
        --argjson n "$g_max_results" \
        --arg mode "$g_mode" \
        --arg lang "$lang" \
        --arg kind "$kind" \
        --arg query "$query" \
        --arg root "$root_abs" '
        def relpath($p): ($p|if type=="string" then . else "" end) as $s
          | if ($root != "" and ($s | startswith($root + "/"))) then $s[($root|length+1):] else $s end;
        (if type == "array" then . else [] end)
        | .[:$n]
        | map({
            path: relpath(.file),
            text: .text,
            start: ((.range.start.line // 0) + 1),
            end: ((.range.end.line // 0) + 1),
            language: $lang,
            mode: $mode,
            source_tool: "ast-grep"
          }
          + (if $kind != "" then {
                kind: $kind,
                name: ((.metaVariables.single.NAME.text) // $query)
             } else {} end))
    ')"

    local matches_json status
    matches_json="$(printf '%s' "$g_results_json" |
        jq '[.[] | (.path + ":" + (.start|tostring) + ":" + (.name // .text))]')"
    status="ok"
    [[ "$(printf '%s' "$g_results_json" | jq 'length')" -eq 0 ]] && status="no_matches"

    if [[ "$mode" == "symbols" || "$mode" == "class" ]]; then
        # Symbol modes publish symbols[] in addition to results[].
        if [[ "$json_mode" == "json" ]]; then
            local symbols_json
            symbols_json="$g_results_json"
            jq -cn \
                --arg schema "1" --arg status "$status" --arg tool "ai-search" \
                --arg query "$g_query" --arg mode "$g_mode" \
                --argjson results "$g_results_json" \
                --argjson symbols "$symbols_json" \
                --argjson matches "$matches_json" \
                --argjson warnings "$(to_json_array "${g_warnings[@]+"${g_warnings[@]}"}")" \
                --argjson max_results "$g_max_results" '
                {
                    schema: $schema, status: $status, tool: $tool,
                    query: $query, mode: $mode,
                    matches: $matches, results: $results, symbols: $symbols,
                    warnings: $warnings, errors: [],
                    limits: { max_results: $max_results },
                    meta: { returned: ($results|length), truncated: false }
                }'
            exit 0
        fi
    fi

    if [[ "$json_mode" == "json" ]]; then
        emit_json "$status" "$matches_json"
    else
        printf '%s' "$g_results_json" | jq -r '.[] | "\(.path):\(.start):\(.text)"'
    fi
    exit 0
}
