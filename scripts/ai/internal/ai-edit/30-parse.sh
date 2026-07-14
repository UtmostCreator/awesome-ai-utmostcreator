# shellcheck shell=bash
# shellcheck disable=SC2154,SC2034  # cross-module globals set by ai_edit_main/parse_tail via dynamic scope
# ai-edit/30-parse.sh — common tail/flag parser (parse_tail).
#
# Sourced by scripts/ai/ai-edit.sh (thin loader). Not an entrypoint. Behavior is
# byte-for-byte identical to the previous monolithic ai-edit.sh.

parse_tail() {
    root="."
    local root_seen=0

    while (($# > 0)); do
        case "$1" in
        --help | -h)
            usage
            exit 0
            ;;
        --format=*)
            format="${1#*=}"
            shift
            ;;
        --format)
            [[ $# -ge 2 ]] || fail_status "error" "--format requires a value" 2
            format="$2"
            shift 2
            ;;
        --glob)
            [[ $# -ge 2 ]] || fail_status "error" "--glob requires a value" 2
            include_globs+=("$2")
            shift 2
            ;;
        --exclude)
            [[ $# -ge 2 ]] || fail_status "error" "--exclude requires a value" 2
            exclude_globs+=("$2")
            shift 2
            ;;
        --max-files)
            [[ $# -ge 2 ]] || fail_status "error" "--max-files requires a value" 2
            max_files="$2"
            shift 2
            ;;
        --max-files=*)
            max_files="${1#*=}"
            shift
            ;;
        --max-replacements)
            [[ $# -ge 2 ]] || fail_status "error" "--max-replacements requires a value" 2
            max_replacements="$2"
            shift 2
            ;;
        --max-replacements=*)
            max_replacements="${1#*=}"
            shift
            ;;
        --max-bytes)
            [[ $# -ge 2 ]] || fail_status "error" "--max-bytes requires a value" 2
            max_bytes="$2"
            shift 2
            ;;
        --max-bytes=*)
            max_bytes="${1#*=}"
            shift
            ;;
        --dry-run)
            apply=0
            shift
            ;;
        --apply)
            apply=1
            shift
            ;;
        --verify)
            verify=1
            shift
            ;;
        --no-verify)
            verify=0
            shift
            ;;
        --require-clean-tree)
            require_clean_tree_flag=1
            shift
            ;;
        --allow-dirty-tree)
            require_clean_tree_flag=0
            shift
            ;;
        --*) fail_status "error" "unknown flag: $1" 2 ;;
        *)
            ((root_seen == 0)) || fail_status "error" "unexpected extra positional: $1" 2
            root="$1"
            root_seen=1
            shift
            ;;
        esac
    done

    case "$format" in
    text | json | help) ;;
    *) fail_status "error" "unknown --format value: $format" 2 ;;
    esac

    validate_uint "--max-files" "$max_files"
    validate_uint "--max-replacements" "$max_replacements"
    validate_uint "--max-bytes" "$max_bytes"

    # Note: a trailing `[[ ... ]] && { ... }` would return non-zero when the
    # test is false, and since parse_tail is called as a bare statement that
    # would trip the ERR trap under `set -e`. Use an explicit if instead.
    if [[ "$format" == "help" ]]; then
        usage
        exit 0
    fi
    return 0
}
