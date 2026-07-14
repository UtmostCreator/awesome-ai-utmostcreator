#!/usr/bin/env bash
# 35-parse-positionals.sh — positional interpretation + dry-run.
#
# Purpose: interpret positionals[] into query/root per mode family and handle
#   --dry-run. The deprecated-alias normaliser (normalize_legacy_alias) lives in
#   10-contract.sh so the single-file introspection target carries the full
#   contract; ai_search_main calls it before interpret_positionals.
# Allowed dependencies: fail()/emit_json() (output + state), is_*_mode()
#   (25-modes.sh). Reads/sets run-state globals.
#
# SC2034/SC2154: run-state globals are owned across modules (see ai-search.sh
# load order), not local to this file.
# shellcheck disable=SC2034,SC2154

# interpret_positionals — resolve query and root from positionals[] according
# to the mode family.
interpret_positionals() {
    query=""
    root="."

    if is_file_list_mode "$mode" && [[ "$legacy_alias" -eq 0 ]]; then
        # Canonical file-list modes take an optional root and never a query.
        if [[ ${#positionals[@]} -gt 1 ]]; then
            fail "error" "mode '$mode' does not accept a query; usage: ai-search.sh $mode [root] [flags]"
        fi

        if [[ ${#positionals[@]} -eq 1 ]]; then
            root="${positionals[0]}"
        fi

    elif is_file_list_mode "$mode" && [[ "$legacy_alias" -eq 1 ]]; then
        # Legacy `changed`/`staged`: tolerate an ignored leading query so existing
        # callers like `changed dummy .` keep working during migration.
        case "${#positionals[@]}" in
        0)
            root="."
            ;;
        1)
            if [[ -d "${positionals[0]}" ]]; then
                root="${positionals[0]}"
            else
                root="."
            fi
            ;;
        2)
            root="${positionals[1]}"
            ;;
        *)
            fail "error" "too many positional arguments for legacy mode '$original_mode'"
            ;;
        esac

    elif is_content_mode "$mode"; then
        if [[ ${#positionals[@]} -lt 1 ]]; then
            fail "error" "query required for mode: $mode"
        fi

        if [[ ${#positionals[@]} -gt 2 ]]; then
            fail "error" "too many positional arguments"
        fi

        query="${positionals[0]}"

        if [[ ${#positionals[@]} -eq 2 ]]; then
            root="${positionals[1]}"
        fi

    elif is_no_query_mode "$mode"; then
        # todo / unsafe-patterns: optional root, never a query.
        if [[ ${#positionals[@]} -gt 1 ]]; then
            fail "error" "mode '$mode' does not accept a query; usage: ai-search.sh $mode [root] [flags]"
        fi

        if [[ ${#positionals[@]} -eq 1 ]]; then
            root="${positionals[0]}"
        fi

    else
        fail "error" "unknown mode: $mode"
    fi

    g_query="$query"
}

# handle_dry_run — when --dry-run was given, emit a dry_run envelope and exit.
handle_dry_run() {
    if [[ "$dry_run" -eq 1 ]]; then
        if [[ "$json_mode" == "json" ]]; then
            emit_json "dry_run"
        else
            echo "dry-run"
        fi
        exit 0
    fi
}
