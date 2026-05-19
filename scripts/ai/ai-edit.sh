#!/usr/bin/env bash
# Guarded edit wrapper for broad repository modifications.

set -euo pipefail
# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

usage() {
    cat <<'EOF'
Usage:
  ai-edit.sh ast-grep LANG PATTERN REWRITE [root]
  ai-edit.sh comby MATCH REWRITE [root]
  ai-edit.sh sd FROM TO [root]

Environment:
  APPLY=1                 Apply changes. Default: dry-run.
  VERIFY=1                Run ai-verify.sh after apply.
  REQUIRE_CLEAN_TREE=1    Require clean tree before apply. Default: 1.
EOF
}

show_diff() {
    git --no-pager diff --stat || true
    git --no-pager diff --color=always | sed -n '1,240p' || true
}

changed_files_json() {
    {
        git diff --name-only || true
        git diff --cached --name-only || true
        git ls-files --others --exclude-standard || true
    } | sort -u | sed '/^$/d' | jq -R . | jq -s .
}

write_session_manifest() {
    local status="$1"
    local manifest_path="$SESSION_DIR/edit-session.json"

    mkdir -p "$SESSION_DIR"

    jq -n \
        --arg session "${SESSION_ID:-unknown}" \
        --arg mode "${mode:-unknown}" \
        --arg root "${root:-.}" \
        --arg status "$status" \
        --arg snapshot "${snapshot:-}" \
        --arg apply "${apply:-0}" \
        --arg verify "${verify:-0}" \
        --arg require_clean_tree "${require_clean_tree_flag:-1}" \
        --arg ts "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
        --argjson changedFiles "$(changed_files_json)" \
        '{
          session: $session,
          mode: $mode,
          root: $root,
          status: $status,
          snapshot: (if $snapshot == "" then null else $snapshot end),
          apply: ($apply == "1"),
          verify: ($verify == "1"),
          requireCleanTree: ($require_clean_tree == "1"),
          ts: $ts,
          changedFiles: $changedFiles
        }' >"$manifest_path"

    log_json "edit.manifest" "$(cat "$manifest_path")" || true
}

resolve_ast_grep() {
    if command -v ast-grep >/dev/null 2>&1; then
        printf 'ast-grep\n'
        return 0
    fi

    if command -v sg >/dev/null 2>&1; then
        printf 'sg\n'
        return 0
    fi

    die "required tool not found: ast-grep or sg"
}

on_error() {
    local exit_code=$?
    write_session_manifest "failed" || true
    exit "$exit_code"
}

mode="${1:-}"
[[ -n "$mode" ]] || {
    usage
    exit 2
}
shift || true

agent_session_init "ai-edit"
require_bins jq git

apply="${APPLY:-0}"
verify="${VERIFY:-0}"
require_clean_tree_flag="${REQUIRE_CLEAN_TREE:-1}"
root='.'
snapshot=''

trap on_error ERR

if [[ "$apply" == "1" ]]; then
    if [[ "$require_clean_tree_flag" == "1" ]]; then
        require_clean_tree
    else
        log_warn "REQUIRE_CLEAN_TREE=0; applying on a dirty tree is allowed for this run"
    fi

    snapshot="$(snapshot_create pre-edit)"
    log_info "Snapshot: $snapshot"
fi

case "$mode" in
ast-grep)
    ast_bin="$(resolve_ast_grep)"
    lang="${1:?lang required}"
    pattern="${2:?pattern required}"
    rewrite="${3:?rewrite required}"
    root="${4:-.}"

    if [[ "$apply" == "1" ]]; then
        "$ast_bin" run --lang "$lang" --pattern "$pattern" --rewrite "$rewrite" "$root" --update-all
    else
        "$ast_bin" run --lang "$lang" --pattern "$pattern" --rewrite "$rewrite" "$root"
        write_session_manifest "dry-run"
        printf '\nDry-run only. Re-run with APPLY=1 to modify files.\n'
        exit 0
    fi
    ;;

comby)
    require_bins comby
    match="${1:?match required}"
    rewrite="${2:?rewrite required}"
    root="${3:-.}"

    if [[ "$apply" == "1" ]]; then
        comby "$match" "$rewrite" -matcher .generic -in-place "$root"
    else
        comby "$match" "$rewrite" -matcher .generic "$root"
        write_session_manifest "dry-run"
        printf '\nDry-run only. Re-run with APPLY=1 to modify files.\n'
        exit 0
    fi
    ;;

sd)
    require_bins rg sd
    from="${1:?from required}"
    to="${2:?to required}"
    root="${3:-.}"

    if [[ "$apply" == "1" ]]; then
        mapfile -t files < <(
            rg -l --hidden \
                -g '!vendor' \
                -g '!node_modules' \
                -g '!dist' \
                -g '!.git' \
                -g '!.repomix-context' \
                "$from" "$root"
        )

        ((${#files[@]} > 0)) || die "no files matched replacement pattern"

        for target_file in "${files[@]}"; do
            sd "$from" "$to" "$target_file"
        done
    else
        rg -n --hidden \
            -g '!vendor' \
            -g '!node_modules' \
            -g '!dist' \
            -g '!.git' \
            -g '!.repomix-context' \
            "$from" "$root"

        write_session_manifest "dry-run"
        printf '\nDry-run only. Re-run with APPLY=1 to modify files.\n'
        exit 0
    fi
    ;;

*)
    usage
    die "unknown mode: $mode"
    ;;
esac

show_diff

if [[ "$verify" == "1" ]]; then
    if ! "$(dirname "${BASH_SOURCE[0]}")/ai-verify.sh" .; then
        write_session_manifest "verify-failed"
        exit 1
    fi

    write_session_manifest "verified"
else
    write_session_manifest "applied"
fi

log_json "edit.apply" "$(jq -cn --arg mode "$mode" --arg snapshot "$snapshot" '{mode:$mode, snapshot:$snapshot}')"