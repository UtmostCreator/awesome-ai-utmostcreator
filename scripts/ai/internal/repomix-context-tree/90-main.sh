# shellcheck shell=bash
# shellcheck disable=SC2154,SC2034  # cross-module globals via dynamic scope
# repomix-context-tree/90-main.sh — command/flag parsing, config, dispatch.
#
# Sourced by scripts/ai/repomix-context-tree.sh (thin loader). Not an entrypoint.
# Runs inside a function so the original top-level flow/exit behavior is
# preserved exactly. Behavior is byte-for-byte identical to the monolith.

repomix_context_tree_main() {
    COMMAND="${1:-}"
    [[ -n "$COMMAND" ]] || {
        usage
        exit 1
    }
    [[ "$COMMAND" != "--help" && "$COMMAND" != "-h" ]] || {
        usage
        exit 0
    }
    shift || true

    ROOT_INPUT='.'
    if (($# > 0)) && [[ "${1:-}" != --* ]]; then
        ROOT_INPUT="$1"
        shift || true
    fi

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
    CONTEXT_WINDOW=1000000
    RESERVED_OUTPUT=25000
    INSTRUCTION_OVERHEAD=30000
    SAFETY_FACTOR=0.8

    while (($# > 0)); do
        case "$1" in
        --output-dir)
            OUTPUT_DIR="$2"
            shift 2
            ;;
        --output-dir=*)
            OUTPUT_DIR="${1#*=}"
            shift
            ;;
        --depth)
            DEPTH="$2"
            shift 2
            ;;
        --depth=*)
            DEPTH="${1#*=}"
            shift
            ;;
        --top)
            TOP="$2"
            shift 2
            ;;
        --top=*)
            TOP="${1#*=}"
            shift
            ;;
        --min-code)
            MIN_CODE="$2"
            shift 2
            ;;
        --min-code=*)
            MIN_CODE="${1#*=}"
            shift
            ;;
        --min-files)
            MIN_FILES="$2"
            shift 2
            ;;
        --min-files=*)
            MIN_FILES="${1#*=}"
            shift
            ;;
        --min-score)
            MIN_SCORE="$2"
            shift 2
            ;;
        --min-score=*)
            MIN_SCORE="${1#*=}"
            shift
            ;;
        --min-complexity)
            MIN_COMPLEXITY="$2"
            shift 2
            ;;
        --min-complexity=*)
            MIN_COMPLEXITY="${1#*=}"
            shift
            ;;
        --changed-since)
            CHANGED_SINCE="$2"
            shift 2
            ;;
        --changed-since=*)
            CHANGED_SINCE="${1#*=}"
            shift
            ;;
        --churn-count)
            CHURN_COUNT="$2"
            shift 2
            ;;
        --churn-count=*)
            CHURN_COUNT="${1#*=}"
            shift
            ;;
        --style)
            STYLE="$2"
            shift 2
            ;;
        --style=*)
            STYLE="${1#*=}"
            shift
            ;;
        --split-size)
            SPLIT_SIZE="$2"
            shift 2
            ;;
        --split-size=*)
            SPLIT_SIZE="${1#*=}"
            shift
            ;;
        --compress)
            COMPRESS=1
            shift
            ;;
        --include-logs)
            INCLUDE_LOGS=1
            shift
            ;;
        --include-logs-count)
            INCLUDE_LOGS_COUNT="$2"
            shift 2
            ;;
        --include-logs-count=*)
            INCLUDE_LOGS_COUNT="${1#*=}"
            shift
            ;;
        --include-diffs)
            INCLUDE_DIFFS=1
            shift
            ;;
        --include-ignored)
            INCLUDE_IGNORED=1
            shift
            ;;
        --include-repomixignored | --no-ignore)
            INCLUDE_REPOMIXIGNORED=1
            INCLUDE_IGNORED=1
            shift
            ;;
        --context-window)
            CONTEXT_WINDOW="$2"
            shift 2
            ;;
        --context-window=*)
            CONTEXT_WINDOW="${1#*=}"
            shift
            ;;
        --reserved-output)
            RESERVED_OUTPUT="$2"
            shift 2
            ;;
        --reserved-output=*)
            RESERVED_OUTPUT="${1#*=}"
            shift
            ;;
        --instruction-overhead)
            INSTRUCTION_OVERHEAD="$2"
            shift 2
            ;;
        --instruction-overhead=*)
            INSTRUCTION_OVERHEAD="${1#*=}"
            shift
            ;;
        --safety-factor)
            SAFETY_FACTOR="$2"
            shift 2
            ;;
        --safety-factor=*)
            SAFETY_FACTOR="${1#*=}"
            shift
            ;;
        --help | -h)
            usage
            exit 0
            ;;
        *) die "unknown option '$1'" ;;
        esac
    done

    ROOT="$(abs_path "$ROOT_INPUT")"
    [[ -d "$ROOT" ]] || die "root directory '$ROOT' does not exist"

    require_clean_secret_scan "$ROOT"

    add_winget_paths

    if [[ "$OUTPUT_DIR" = /* ]]; then
        OUTPUT_DIR_ABS="$OUTPUT_DIR"
    else
        OUTPUT_DIR_ABS="$ROOT/$OUTPUT_DIR"
    fi

    TREE_DIR="$OUTPUT_DIR_ABS/tree-context"
    BUNDLES_DIR="$TREE_DIR/bundles"
    INDEXES_DIR="$TREE_DIR/indexes"
    TREE_PLAN_TSV="$TREE_DIR/tree-plan.tsv"
    TREE_PLAN_JSON="$TREE_DIR/tree-plan.json"
    TREE_MANIFEST_JSON="$TREE_DIR/tree-manifest.json"
    INDEX_MD="$TREE_DIR/index.md"
    INDEX_JSON="$TREE_DIR/index.json"
    ROUTER_FOLDER_METRICS="$TREE_DIR/folder-metrics.tsv"
    ROUTER_FILE_METRICS="$TREE_DIR/file-metrics.tsv"
    STYLE_EXT="$(ext_for_style "$STYLE")"
    # COMMON_DIR is the scripts/ai root resolved by the thin loader from the root
    # script's own location. The original computed SCRIPT_DIR from
    # ${BASH_SOURCE[0]} at top level (= scripts/ai); now that this code lives in a
    # sourced module, reuse COMMON_DIR so the sibling router still resolves to
    # scripts/ai/repomix-scc-router.sh rather than this module's directory.
    SCRIPT_DIR="$COMMON_DIR"
    ROUTER_SCRIPT="$SCRIPT_DIR/repomix-scc-router.sh"

    router_args=("$ROUTER_SCRIPT" stats . --output-dir "$TREE_DIR" --depth "$DEPTH" --top "$TOP" --min-code "$MIN_CODE" --min-files "$MIN_FILES" --min-score "$MIN_SCORE" --min-complexity "$MIN_COMPLEXITY" --churn-count "$CHURN_COUNT" --style "$STYLE" --include-logs-count "$INCLUDE_LOGS_COUNT")
    [[ -n "$CHANGED_SINCE" ]] && router_args+=(--changed-since "$CHANGED_SINCE")
    [[ -n "$SPLIT_SIZE" ]] && router_args+=(--split-size "$SPLIT_SIZE")
    [[ "$COMPRESS" == "1" ]] && router_args+=(--compress)
    [[ "$INCLUDE_LOGS" == "1" ]] && router_args+=(--include-logs)
    [[ "$INCLUDE_DIFFS" == "1" ]] && router_args+=(--include-diffs)
    [[ "$INCLUDE_IGNORED" == "1" ]] && router_args+=(--include-ignored)
    [[ "$INCLUDE_REPOMIXIGNORED" == "1" ]] && router_args+=(--include-repomixignored)

    case "$COMMAND" in
    analyze | plan) run_analyze ;;
    pack) run_pack ;;
    all) run_all ;;
    clean) run_clean ;;
    purge) run_purge ;;
    *)
        usage
        die "unknown command '$COMMAND'"
        ;;
    esac
}
