# shellcheck shell=bash
# shellcheck disable=SC2154,SC2034  # cross-module globals via dynamic scope
#
# Shared CLI option surface for repomix-context-tree and repomix-scc-router:
# both wrap repomix stats/pack with the same knobs (repomix-context-tree
# forwards most of these verbatim to repomix-scc-router). Extracted to remove
# the ~130 duplicated lines jscpd flagged between their 90-main.sh modules
# (root-arg handling, shared defaults, and the shared option-parsing loop).
#
# Sourced by scripts/ai/repomix-context-tree.sh and scripts/ai/repomix-scc-router.sh
# (thin loaders), BEFORE their own 90-main.sh. Not an entrypoint.
#
# Contract (all state via global variables, matching the existing dynamic-scope
# pattern in both 90-main.sh modules):
#
#   _repomix_common_defaults
#       Sets the shared option variables to their defaults. Call once before
#       the caller's own option loop (after any script-specific defaults that
#       must be set first, if any).
#
#   _repomix_common_root_arg "$@"
#       Consumes an optional leading ROOT arg (any token not starting with
#       --). Sets ROOT_INPUT and _repomix_shift_count (0 or 1); caller does
#       `shift "$_repomix_shift_count"` immediately after.
#
#   _repomix_try_common_opt "$@"
#       Tries to parse $1 (and $2 for two-token flags) as one of the shared
#       flags. On match: sets the corresponding variable(s) and
#       _repomix_shift_count (1 or 2), then returns 0 — caller does
#       `shift "$_repomix_shift_count"` and continues its loop. On no match:
#       returns 1 and leaves _repomix_shift_count untouched, so the caller's
#       own case statement handles script-specific flags, --help/-h, and the
#       unknown-option die().

_repomix_common_defaults() {
    OUTPUT_DIR='.repomix-context'
    DEPTH=2
    TOP=0
    MIN_CODE=25
    MIN_FILES=1
    MIN_SCORE=0
    MIN_COMPLEXITY=0
    CHANGED_SINCE=''
    CHURN_COUNT=50
    STYLE='xml'
    SPLIT_SIZE=''
    COMPRESS=0
    INCLUDE_LOGS=0
    INCLUDE_LOGS_COUNT=20
    INCLUDE_DIFFS=0
    INCLUDE_IGNORED="${INCLUDE_IGNORED:-0}"
    # Full bypass of .repomixignore (and repomix default ignore layers). Off by
    # default; opt in via env key or --no-ignore / --include-repomixignored.
    INCLUDE_REPOMIXIGNORED="${INCLUDE_REPOMIXIGNORED:-0}"
}

_repomix_common_root_arg() {
    ROOT_INPUT='.'
    _repomix_shift_count=0
    if (($# > 0)) && [[ "${1:-}" != --* ]]; then
        ROOT_INPUT="$1"
        shift
        _repomix_shift_count=1
    fi
}

_repomix_try_common_opt() {
    # The `shift`/`shift 2` calls below only consume this function's own copy
    # of "$@" (the caller passed a snapshot via `_repomix_try_common_opt "$@"`)
    # and are otherwise inert — the caller shifts its own args via
    # `_repomix_shift_count` after this function returns. They are kept
    # anyway because tools/ai/sh-introspect.php's static takes-value heuristic
    # (scripts/ai/../../tools/ai/sh-introspect/40-params.php) detects
    # value-taking flags by looking for a literal `shift` in the case-branch
    # body; removing them would make --help/--introspect misreport these
    # flags as boolean.
    _repomix_shift_count=0
    case "$1" in
    --output-dir)
        OUTPUT_DIR="$2"
        shift 2
        _repomix_shift_count=2
        ;;
    --output-dir=*)
        OUTPUT_DIR="${1#*=}"
        shift
        _repomix_shift_count=1
        ;;
    --depth)
        DEPTH="$2"
        shift 2
        _repomix_shift_count=2
        ;;
    --depth=*)
        DEPTH="${1#*=}"
        shift
        _repomix_shift_count=1
        ;;
    --top)
        TOP="$2"
        shift 2
        _repomix_shift_count=2
        ;;
    --top=*)
        TOP="${1#*=}"
        shift
        _repomix_shift_count=1
        ;;
    --min-code)
        MIN_CODE="$2"
        shift 2
        _repomix_shift_count=2
        ;;
    --min-code=*)
        MIN_CODE="${1#*=}"
        shift
        _repomix_shift_count=1
        ;;
    --min-files)
        MIN_FILES="$2"
        shift 2
        _repomix_shift_count=2
        ;;
    --min-files=*)
        MIN_FILES="${1#*=}"
        shift
        _repomix_shift_count=1
        ;;
    --min-score)
        MIN_SCORE="$2"
        shift 2
        _repomix_shift_count=2
        ;;
    --min-score=*)
        MIN_SCORE="${1#*=}"
        shift
        _repomix_shift_count=1
        ;;
    --min-complexity)
        MIN_COMPLEXITY="$2"
        shift 2
        _repomix_shift_count=2
        ;;
    --min-complexity=*)
        MIN_COMPLEXITY="${1#*=}"
        shift
        _repomix_shift_count=1
        ;;
    --changed-since)
        CHANGED_SINCE="$2"
        shift 2
        _repomix_shift_count=2
        ;;
    --changed-since=*)
        CHANGED_SINCE="${1#*=}"
        shift
        _repomix_shift_count=1
        ;;
    --churn-count)
        CHURN_COUNT="$2"
        shift 2
        _repomix_shift_count=2
        ;;
    --churn-count=*)
        CHURN_COUNT="${1#*=}"
        shift
        _repomix_shift_count=1
        ;;
    --style)
        STYLE="$2"
        shift 2
        _repomix_shift_count=2
        ;;
    --style=*)
        STYLE="${1#*=}"
        shift
        _repomix_shift_count=1
        ;;
    --split-size)
        SPLIT_SIZE="$2"
        shift 2
        _repomix_shift_count=2
        ;;
    --split-size=*)
        SPLIT_SIZE="${1#*=}"
        shift
        _repomix_shift_count=1
        ;;
    --compress)
        COMPRESS=1
        shift
        _repomix_shift_count=1
        ;;
    --include-logs)
        INCLUDE_LOGS=1
        shift
        _repomix_shift_count=1
        ;;
    --include-logs-count)
        INCLUDE_LOGS_COUNT="$2"
        shift 2
        _repomix_shift_count=2
        ;;
    --include-logs-count=*)
        INCLUDE_LOGS_COUNT="${1#*=}"
        shift
        _repomix_shift_count=1
        ;;
    --include-diffs)
        INCLUDE_DIFFS=1
        shift
        _repomix_shift_count=1
        ;;
    --include-ignored)
        INCLUDE_IGNORED=1
        shift
        _repomix_shift_count=1
        ;;
    --include-repomixignored | --no-ignore)
        INCLUDE_REPOMIXIGNORED=1
        INCLUDE_IGNORED=1
        shift
        _repomix_shift_count=1
        ;;
    *)
        return 1
        ;;
    esac
    return 0
}
