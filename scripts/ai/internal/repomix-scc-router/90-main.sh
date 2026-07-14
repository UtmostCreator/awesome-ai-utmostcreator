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

    _repomix_common_root_arg "$@"
    shift "$_repomix_shift_count" || true

    _repomix_common_defaults
    STYLE_EXT='xml'

    while (($# > 0)); do
        if _repomix_try_common_opt "$@"; then
            shift "$_repomix_shift_count"
            continue
        fi
        case "$1" in
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
