# shellcheck shell=bash
# shellcheck disable=SC2154,SC2034  # cross-module globals via dynamic scope
# repomix-scc-router/90-main.sh — command/flag parsing and dispatch.
#
# Sourced by scripts/ai/repomix-scc-router.sh (thin loader). Not an entrypoint.
# Runs inside a function so the original top-level flow/exit behavior is
# preserved exactly. Behavior is byte-for-byte identical to the monolith.

repomix_scc_router_main() {
    COMMAND="${1:-}"
    if [[ -z "$COMMAND" ]]; then
        usage
        exit 1
    fi
    if [[ "$COMMAND" == "--help" || "$COMMAND" == "-h" ]]; then
        usage
        exit 0
    fi
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
    STYLE_EXT='xml'
    SPLIT_SIZE=''
    COMPRESS=0
    INCLUDE_LOGS=0
    INCLUDE_LOGS_COUNT=20
    INCLUDE_DIFFS=0
    INCLUDE_IGNORED="${INCLUDE_IGNORED:-0}"
    # INCLUDE_REPOMIXIGNORED additionally bypasses .repomixignore patterns (and
    # repomix's own ignore layers) so an explicitly selected folder can be packed
    # even when listed there. Off by default; opt in via env key or --no-ignore /
    # --include-repomixignored. Implies INCLUDE_IGNORED so git-ignored files are
    # also collected. The .git and output directories stay excluded for safety.
    INCLUDE_REPOMIXIGNORED="${INCLUDE_REPOMIXIGNORED:-0}"

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
            # Full bypass: ignore .repomixignore as well as .gitignore so an
            # explicitly chosen folder is always packable when the key is passed.
            INCLUDE_REPOMIXIGNORED=1
            INCLUDE_IGNORED=1
            shift
            ;;
        --help | -h)
            usage
            exit 0
            ;;
        *)
            die "unknown option '$1'"
            ;;
        esac
    done

    ROOT="$(abs_path "$ROOT_INPUT")"
    [[ -d "$ROOT" ]] || die "root directory '$ROOT' does not exist"

    require_clean_secret_scan "$ROOT"

    if [[ "$OUTPUT_DIR" = /* ]]; then
        OUTPUT_DIR_ABS="$OUTPUT_DIR"
        OUTPUT_DIR_REL="$(basename "$OUTPUT_DIR")"
    else
        OUTPUT_DIR_REL="$OUTPUT_DIR"
        OUTPUT_DIR_ABS="$ROOT/$OUTPUT_DIR"
    fi

    STYLE_EXT="$(ext_for_style "$STYLE")"

    RAW_METRICS="$OUTPUT_DIR_ABS/scc-openmetrics.txt"
    FILE_METRICS_RAW="$OUTPUT_DIR_ABS/file-metrics.raw.tsv"
    FILE_METRICS="$OUTPUT_DIR_ABS/file-metrics.tsv"
    FOLDER_METRICS="$OUTPUT_DIR_ABS/folder-metrics.tsv"
    BUNDLE_PLAN="$OUTPUT_DIR_ABS/bundle-plan.tsv"
    BUNDLE_PLAN_JSON="$OUTPUT_DIR_ABS/bundle-plan.json"

    case "$COMMAND" in
    stats)
        run_stats
        ;;
    plan)
        run_stats
        write_bundle_plan
        ;;
    pack)
        run_pack
        ;;
    all)
        run_stats
        write_bundle_plan
        run_pack
        ;;
    clean)
        run_clean
        ;;
    purge)
        run_purge
        ;;
    *)
        usage
        die "unknown command '$COMMAND'"
        ;;
    esac
}
