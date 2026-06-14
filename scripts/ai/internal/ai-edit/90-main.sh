# shellcheck shell=bash
# shellcheck disable=SC2154,SC2034  # cross-module globals set by ai_edit_main/parse_tail via dynamic scope
# ai-edit/90-main.sh — argument dispatch and run flow (ai_edit_main).
#
# Sourced by scripts/ai/ai-edit.sh (thin loader). Not an entrypoint. The driver
# runs inside ai_edit_main so the original top-level flow (mode dispatch, session
# init, finish) is preserved exactly. Globals it sets (mode, apply, root,
# SESSION_DIR, *_json, etc.) are intentionally NOT declared local so the helper
# functions in the sibling modules see them via dynamic scope, exactly as when
# this code ran at the top level of the monolithic script. Behavior is
# byte-for-byte identical to the previous monolithic ai-edit.sh.

ai_edit_main() {
    case "${1:-}" in
    --help | -h)
        usage
        exit 0
        ;;
    --format=help)
        usage
        exit 0
        ;;
    --format)
        [[ "${2:-}" == "help" ]] && {
            usage
            exit 0
        }
        ;;
    esac

    mode="${1:-}"
    [[ -n "$mode" ]] || {
        usage
        exit 2
    }
    shift || true

    agent_session_init "ai-edit"
    SESSION_ID="${SESSION_ID:-$(date -u +%Y%m%dT%H%M%SZ)-$$}"
    SESSION_DIR="${SESSION_DIR:-${AI_LOG_DIR:-$REPO_ROOT/.ai-sessions}/$SESSION_ID}"
    mkdir -p "$SESSION_DIR"

    require_bins jq git

    apply="${APPLY:-0}"
    verify="${VERIFY:-0}"
    require_clean_tree_flag="${REQUIRE_CLEAN_TREE:-1}"
    format="${FORMAT:-text}"
    max_files="${MAX_FILES:-50}"
    max_replacements="${MAX_REPLACEMENTS:-500}"
    max_bytes="${MAX_BYTES:-2000000}"
    snapshot=""
    planned_json='[]'
    warnings_json='[]'
    errors_json='[]'
    patch_path=""
    patch_file=""
    patch_changed_files_json='[]'
    baseline_dirty_json="$(dirty_files_json)"

    trap on_error ERR

    case "$mode" in
    ast-grep)
        [[ $# -ge 3 ]] || fail_status "error" "ast-grep requires LANG PATTERN REWRITE [root]" 2
        ast_bin="$(resolve_ast_grep)"
        lang="$1"
        pattern="$2"
        rewrite="$3"
        shift 3
        parse_tail "$@"
        structural_scope_guard

        if [[ "$apply" == "1" ]]; then
            # shellcheck disable=SC2015  # intentional: warn-and-continue only when clean tree is NOT required
            [[ "$require_clean_tree_flag" == "1" ]] && require_clean_tree || log_warn "dirty tree allowed"
            snapshot="$(snapshot_create pre-edit)"
            "$ast_bin" run --lang "$lang" --pattern "$pattern" --rewrite "$rewrite" "$root" --update-all
        else
            if is_json_output; then
                "$ast_bin" run --lang "$lang" --pattern "$pattern" --rewrite "$rewrite" "$root" >"$SESSION_DIR/dry-run.txt" || true
            else
                "$ast_bin" run --lang "$lang" --pattern "$pattern" --rewrite "$rewrite" "$root" || true
            fi
            finish "dry_run" 0
        fi
        ;;

    comby)
        [[ $# -ge 2 ]] || fail_status "error" "comby requires MATCH REWRITE [root]" 2
        require_bins comby
        match="$1"
        rewrite="$2"
        shift 2
        parse_tail "$@"
        structural_scope_guard

        if [[ "$apply" == "1" ]]; then
            # shellcheck disable=SC2015  # intentional: warn-and-continue only when clean tree is NOT required
            [[ "$require_clean_tree_flag" == "1" ]] && require_clean_tree || log_warn "dirty tree allowed"
            snapshot="$(snapshot_create pre-edit)"
            comby "$match" "$rewrite" -matcher .generic -in-place "$root"
        else
            if is_json_output; then
                comby "$match" "$rewrite" -matcher .generic "$root" >"$SESSION_DIR/dry-run.txt" || true
            else
                comby "$match" "$rewrite" -matcher .generic "$root" || true
            fi
            finish "dry_run" 0
        fi
        ;;

    sd)
        [[ $# -ge 2 ]] || fail_status "error" "sd requires FROM TO [root]" 2
        from="$1"
        to="$2"
        shift 2
        parse_tail "$@"

        if sd_plan; then
            if [[ "$apply" == "1" ]]; then
                # shellcheck disable=SC2015  # intentional: warn-and-continue only when clean tree is NOT required
                [[ "$require_clean_tree_flag" == "1" ]] && require_clean_tree || log_warn "dirty tree allowed"
                snapshot="$(snapshot_create pre-edit)"
                sd_apply
            else
                finish "dry_run" 0
            fi
        else
            case "$?" in
            1) finish "no_matches" 0 ;;
            2) finish "limit_exceeded" 3 ;;
            *) finish "error" 1 ;;
            esac
        fi
        ;;

    patch)
        [[ $# -ge 1 ]] || fail_status "error" "patch requires PATCH_FILE|- [root] [flags]" 2
        patch_path="$1"
        shift 1
        parse_tail "$@"

        # patch validation/preview/apply is path-driven; --glob/--exclude do not
        # apply to an explicit diff, so reject them rather than silently ignore.
        structural_scope_guard

        patch_plan

        if [[ "$apply" == "1" ]]; then
            # shellcheck disable=SC2015  # intentional: warn-and-continue only when clean tree is NOT required
            [[ "$require_clean_tree_flag" == "1" ]] && require_clean_tree || log_warn "dirty tree allowed"
            snapshot="$(snapshot_create pre-edit)"
            patch_apply
        else
            finish "dry_run" 0
        fi
        ;;

    *)
        usage
        fail_status "error" "unknown mode: $mode" 2
        ;;
    esac

    save_diff_artifacts

    if ! is_json_output; then
        show_diff
    fi

    if [[ "$verify" == "1" ]]; then
        if ! "$SCRIPT_DIR/ai-verify.sh" . >"$SESSION_DIR/verify.log" 2>&1; then
            add_error "verification failed; see $SESSION_DIR/verify.log"
            finish "verify_failed" 1
        fi
        finish "verified" 0
    fi

    finish "applied" 0
}
