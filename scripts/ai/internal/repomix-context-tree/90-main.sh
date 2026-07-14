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

    _repomix_common_root_arg "$@"
    shift "$_repomix_shift_count" || true

    _repomix_common_defaults
    CONTEXT_WINDOW=1000000
    RESERVED_OUTPUT=25000
    INSTRUCTION_OVERHEAD=30000
    SAFETY_FACTOR=0.8

    while (($# > 0)); do
        if _repomix_try_common_opt "$@"; then
            shift "$_repomix_shift_count"
            continue
        fi
        case "$1" in
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
