#!/usr/bin/env bash
# 30-parse-flags.sh — flag parser.
#
# Purpose: parse all flags into run-state globals. Flags are accepted in any
#   position; non-flag tokens accumulate into positionals[] for later mode-aware
#   interpretation. Unknown flags are a hard error.
# Allowed dependencies: fail(), validate_non_negative_int() (40-output-json.sh),
#   usage()/introspect_help_summary() (10-contract.sh). Caller passes the
#   already-MODE-consumed argument list.
#
# SC2034/SC2154: this module reads and writes run-state globals owned by other
# modules (see ai-search.sh load order); they are not local to this file.
# shellcheck disable=SC2034,SC2154

parse_flags() {
    while [[ $# -gt 0 ]]; do
        case "$1" in
        --fixed)
            pattern_mode="fixed"
            ;;
        --regex)
            pattern_mode="regex"
            ;;
        --pcre2)
            pattern_mode="pcre2"
            ;;
        --absolute)
            absolute=1
            ;;
        --ignore-case | -i)
            case_mode="ignore"
            ;;
        --case-sensitive)
            case_mode="sensitive"
            ;;
        --smart-case)
            case_mode="smart"
            ;;
        --glob)
            shift
            [[ -n "${1:-}" ]] || fail "error" "--glob requires a pattern"
            glob_args+=("$1")
            ;;
        --type)
            shift
            [[ -n "${1:-}" ]] || fail "error" "--type requires a type name"
            type_args+=("$1")
            ;;
        --exclude)
            shift
            [[ -n "${1:-}" ]] || fail "error" "--exclude requires a path"
            exclude_args+=("$1")
            ;;
        --max-depth)
            shift
            validate_non_negative_int "--max-depth" "${1:-}"
            max_depth="$1"
            ;;
        --no-ignore)
            # Disable ALL ignore sources (local + parent + global gitignore,
            # .git/info/exclude, and .ignore/.rgignore files).
            ignore_args+=(--no-ignore)
            ;;
        --no-ignore-vcs)
            # Disable local + parent .gitignore and .git/info/exclude only;
            # the global gitignore is still honored.
            ignore_args+=(--no-ignore-vcs)
            ;;
        --no-ignore-global)
            # Disable only the global gitignore (git core.excludesfile);
            # local/parent .gitignore are still honored.
            ignore_args+=(--no-ignore-global)
            ;;
        --no-ignore-parent)
            # Disable .gitignore/.ignore files in parent directories only.
            ignore_args+=(--no-ignore-parent)
            ;;
        --no-ignore-dot)
            # Disable .ignore and .rgignore files (keep gitignore behavior).
            ignore_args+=(--no-ignore-dot)
            ;;
        --dry-run)
            dry_run=1
            ;;
        --context | -C)
            shift
            validate_non_negative_int "--context" "${1:-}"
            context_before="$1"
            context_after="$1"
            ;;
        --before-context | -B)
            shift
            validate_non_negative_int "--before-context" "${1:-}"
            context_before="$1"
            ;;
        --after-context | -A)
            shift
            validate_non_negative_int "--after-context" "${1:-}"
            context_after="$1"
            ;;
        --max-bytes)
            shift
            validate_non_negative_int "--max-bytes" "${1:-}"
            max_bytes="$1"
            ;;
        --max-results)
            shift
            validate_non_negative_int "--max-results" "${1:-}"
            g_max_results="$1"
            ;;
        --files-with-matches | -l)
            count_mode="files"
            ;;
        --count)
            count_mode="count"
            ;;
        --count-matches)
            count_mode="count-matches"
            ;;
        --staged)
            diff_staged=1
            ;;
        --base)
            shift
            [[ -n "${1:-}" ]] || fail "error" "--base requires a ref"
            diff_base="$1"
            ;;
        --messages)
            history_messages=1
            ;;
        --patch)
            history_patch=1
            ;;
        --lang)
            shift
            [[ -n "${1:-}" ]] || fail "error" "--lang requires a language"
            lang_flag="$1"
            ;;
        --help | -h)
            usage
            introspect_help_summary
            exit 0
            ;;
        --*)
            fail "error" "unknown flag: $1"
            ;;
        *)
            positionals+=("$1")
            ;;
        esac
        shift
    done
    return 0
}
