#!/usr/bin/env bash
# Guarded edit wrapper for broad repository modifications.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
# shellcheck source=scripts/ai/common.sh
source "$SCRIPT_DIR/common.sh"

usage() {
    cat <<'EOF'
ai-edit.sh — guarded repository edit entrypoint

Usage:
  ai-edit.sh MODE ARGS... [root] [flags]
  ai-edit.sh ast-grep LANG PATTERN REWRITE [root] [flags]
  ai-edit.sh comby MATCH REWRITE [root] [flags]
  ai-edit.sh sd FROM TO [root] [flags]

JSON output: AI_OUTPUT=json

Status values: dry_run applied verified no_matches error unavailable blocked verify_failed limit_exceeded

Modes:
  structural:
    ast-grep       AST-aware rewrite; uses ast-grep/sg
  text:
    comby          generic structural rewrite
    sd             regex replacement in files found by rg; fully planned before apply

Params:
  scope:
    --glob VALUE+              include glob; repeatable
    --exclude VALUE+           exclude path/glob; repeatable; added to defaults

  bounds:
    --max-files VALUE          cap editable files; default 50
    --max-replacements VALUE   cap total replacements; default 500
    --max-bytes VALUE          skip files above N bytes; default 2000000

  safety:
    --dry-run                  preview only
    --apply                    apply changes
    --verify                   run ai-verify.sh after apply
    --no-verify                do not run ai-verify.sh
    --require-clean-tree       require clean tree before apply
    --allow-dirty-tree         allow dirty tree before apply

  output:
    --format json|text|help

  misc:
    --help | -h                show this help
    --introspect               print machine-readable JSON contract

Env: AI_OUTPUT APPLY VERIFY REQUIRE_CLEAN_TREE MAX_FILES MAX_REPLACEMENTS MAX_BYTES

Tools:
  primary: ast-grep comby git jq rg sd
  base utilities: awk cat sed sort wc
  mode-specific tools: see mode contract via --introspect

Examples:
  AI_OUTPUT=json bash scripts/ai/ai-edit.sh sd OldName NewName . --dry-run
  AI_OUTPUT=json bash scripts/ai/ai-edit.sh sd OldName NewName . --apply --verify
  AI_OUTPUT=json bash scripts/ai/ai-edit.sh ast-grep php 'old($A)' 'new($A)' . --dry-run

Machine contract: bash scripts/ai/ai-edit.sh --introspect
Full contract: AI_OUTPUT=json php tools/ai/sh-introspect.php scripts/ai/ai-edit.sh
EOF
}

show_diff() {
    git --no-pager diff --stat || true
    git --no-pager diff --color=always | sed -n '1,240p' || true
}

dirty_files_json() {
    if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
        printf '[]\n'
        return 0
    fi

    {
        git diff --name-only || true
        git diff --cached --name-only || true
        git ls-files --others --exclude-standard || true
    } | sort -u | sed '/^$/d' | jq -R . | jq -s -c .
}

is_json_output() {
    [[ "${format:-text}" == "json" || "${AI_OUTPUT:-}" == "json" ]]
}

add_warning() {
    warnings_json="$(jq -c --arg v "$1" '. + [$v]' <<<"$warnings_json")"
}

add_error() {
    errors_json="$(jq -c --arg v "$1" '. + [$v]' <<<"$errors_json")"
}

json_array_diff() {
    jq -c -n --argjson before "$1" --argjson after "$2" '$after - $before'
}

save_diff_artifacts() {
    mkdir -p "$SESSION_DIR"
    git --no-pager diff --stat >"$SESSION_DIR/diff.stat" || true
    git --no-pager diff >"$SESSION_DIR/diff.patch" || true
}

write_session_manifest() {
    local status="$1"
    local manifest_path="$SESSION_DIR/edit-session.json"
    local after_json session_changed_json

    mkdir -p "$SESSION_DIR"
    after_json="$(dirty_files_json)"
    session_changed_json="$(json_array_diff "$baseline_dirty_json" "$after_json")"

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
        --arg diff_patch "$SESSION_DIR/diff.patch" \
        --arg diff_stat "$SESSION_DIR/diff.stat" \
        --argjson plannedChanges "$planned_json" \
        --argjson baselineDirtyFiles "$baseline_dirty_json" \
        --argjson changedFiles "$after_json" \
        --argjson sessionChangedFiles "$session_changed_json" \
        --argjson warnings "$warnings_json" \
        --argjson errors "$errors_json" \
        '{
          schema: "ai.edit-session/v1",
          session: $session,
          mode: $mode,
          root: $root,
          status: $status,
          snapshot: (if $snapshot == "" then null else $snapshot end),
          apply: ($apply == "1"),
          verify: ($verify == "1"),
          requireCleanTree: ($require_clean_tree == "1"),
          ts: $ts,
          plannedChanges: $plannedChanges,
          baselineDirtyFiles: $baselineDirtyFiles,
          changedFiles: $changedFiles,
          sessionChangedFiles: $sessionChangedFiles,
          warnings: $warnings,
          errors: $errors,
          artifacts: {
            manifest: "'$manifest_path'",
            diffPatch: $diff_patch,
            diffStat: $diff_stat
          }
        }' >"$manifest_path"

    log_json "edit.manifest" "$(cat "$manifest_path")" || true
}

emit_result_json() {
    local status="$1"
    local after_json session_changed_json

    after_json="$(dirty_files_json)"
    session_changed_json="$(json_array_diff "$baseline_dirty_json" "$after_json")"

    jq -n \
        --arg status "$status" \
        --arg mode "${mode:-unknown}" \
        --arg root "${root:-.}" \
        --arg snapshot "${snapshot:-}" \
        --arg session_dir "${SESSION_DIR:-}" \
        --argjson apply_bool "$([[ "$apply" == "1" ]] && echo true || echo false)" \
        --argjson verify_bool "$([[ "$verify" == "1" ]] && echo true || echo false)" \
        --argjson plannedChanges "$planned_json" \
        --argjson baselineDirtyFiles "$baseline_dirty_json" \
        --argjson changedFiles "$after_json" \
        --argjson sessionChangedFiles "$session_changed_json" \
        --argjson warnings "$warnings_json" \
        --argjson errors "$errors_json" \
        --argjson maxFiles "$max_files" \
        --argjson maxReplacements "$max_replacements" \
        --argjson maxBytes "$max_bytes" \
        '{
          schema: "ai.edit/v1",
          status: $status,
          tool: "ai-edit",
          mode: $mode,
          root: $root,
          apply: $apply_bool,
          verify: $verify_bool,
          plannedChanges: $plannedChanges,
          changedFiles: $changedFiles,
          baselineDirtyFiles: $baselineDirtyFiles,
          sessionChangedFiles: $sessionChangedFiles,
          warnings: $warnings,
          errors: $errors,
          limits: {
            maxFiles: $maxFiles,
            maxReplacements: $maxReplacements,
            maxBytes: $maxBytes
          },
          snapshot: (if $snapshot == "" then null else $snapshot end),
          artifacts: {
            sessionDir: $session_dir,
            manifest: ($session_dir + "/edit-session.json"),
            diffPatch: ($session_dir + "/diff.patch"),
            diffStat: ($session_dir + "/diff.stat")
          },
          meta: {
            targetExecuted: true,
            truncated: false
          }
        }'
}

finish() {
    local status="$1"
    local exit_code="${2:-0}"

    trap - ERR
    write_session_manifest "$status" || true

    if is_json_output; then
        emit_result_json "$status"
    else
        case "$status" in
            dry_run) printf '\nDry-run only. Re-run with --apply or APPLY=1 to modify files.\n' ;;
            no_matches) printf 'No matches.\n' ;;
            applied) printf 'Applied changes. Manifest: %s/edit-session.json\n' "$SESSION_DIR" ;;
            verified) printf 'Applied and verified. Manifest: %s/edit-session.json\n' "$SESSION_DIR" ;;
            limit_exceeded|blocked|error|verify_failed) printf '%s\n' "$status" >&2 ;;
        esac
    fi

    exit "$exit_code"
}

on_error() {
    local exit_code=$?
    trap - ERR
    add_error "unexpected failure"
    finish "error" "$exit_code"
}

fail_status() {
    local status="$1"
    local message="$2"
    local code="${3:-2}"
    add_error "$message"
    finish "$status" "$code"
}

validate_uint() {
    local name="$1" value="$2"
    [[ "$value" =~ ^[0-9]+$ ]] || fail_status "error" "$name must be a non-negative integer: $value" 2
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
    fail_status "unavailable" "required tool not found: ast-grep or sg" 127
}

default_excludes=(
    ".git" ".git/**"
    "vendor" "vendor/**"
    "node_modules" "node_modules/**"
    "dist" "dist/**"
    "build" "build/**"
    "coverage" "coverage/**"
    ".repomix-context" ".repomix-context/**"
    ".cache" ".cache/**"
    "*.min.*" "*.map" "*.lock"
    ".env" ".env.*"
    "*.pem" "*.key" "*.crt"
)

include_globs=()
exclude_globs=()

build_rg_args() {
    rg_args=(--hidden)
    local g
    for g in "${default_excludes[@]}"; do
        rg_args+=(-g "!$g")
    done
    for g in "${exclude_globs[@]}"; do
        rg_args+=(-g "!$g")
    done
    for g in "${include_globs[@]}"; do
        rg_args+=(-g "$g")
    done
}

parse_tail() {
    root="."
    local root_seen=0

    while (($# > 0)); do
        case "$1" in
            --help|-h) usage; exit 0 ;;
            --format=*) format="${1#*=}"; shift ;;
            --format) [[ $# -ge 2 ]] || fail_status "error" "--format requires a value" 2; format="$2"; shift 2 ;;
            --glob) [[ $# -ge 2 ]] || fail_status "error" "--glob requires a value" 2; include_globs+=("$2"); shift 2 ;;
            --exclude) [[ $# -ge 2 ]] || fail_status "error" "--exclude requires a value" 2; exclude_globs+=("$2"); shift 2 ;;
            --max-files) [[ $# -ge 2 ]] || fail_status "error" "--max-files requires a value" 2; max_files="$2"; shift 2 ;;
            --max-files=*) max_files="${1#*=}"; shift ;;
            --max-replacements) [[ $# -ge 2 ]] || fail_status "error" "--max-replacements requires a value" 2; max_replacements="$2"; shift 2 ;;
            --max-replacements=*) max_replacements="${1#*=}"; shift ;;
            --max-bytes) [[ $# -ge 2 ]] || fail_status "error" "--max-bytes requires a value" 2; max_bytes="$2"; shift 2 ;;
            --max-bytes=*) max_bytes="${1#*=}"; shift ;;
            --dry-run) apply=0; shift ;;
            --apply) apply=1; shift ;;
            --verify) verify=1; shift ;;
            --no-verify) verify=0; shift ;;
            --require-clean-tree) require_clean_tree_flag=1; shift ;;
            --allow-dirty-tree) require_clean_tree_flag=0; shift ;;
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
        text|json|help) ;;
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

sd_plan() {
    require_bins rg
    build_rg_args

    local counts_file line path count bytes file_count=0 replacement_count=0 skipped_for_bytes=0
    counts_file="$SESSION_DIR/sd-counts.txt"
    mkdir -p "$SESSION_DIR"

    # Capture rg's real exit code directly. Negating with `! rg ...` would reset
    # $? to 0 inside the branch and lose rg's status (1 = no matches, 2 = error).
    local rc=0
    rg -c "${rg_args[@]}" "$from" "$root" >"$counts_file" || rc=$?
    if ((rc != 0)); then
        ((rc == 1)) && return 1
        fail_status "error" "rg failed while planning replacements" "$rc"
    fi

    while IFS= read -r line; do
        [[ -n "$line" ]] || continue
        path="${line%:*}"
        count="${line##*:}"
        [[ -f "$path" ]] || continue

        bytes="$(wc -c <"$path" | tr -d ' ')"
        if (( bytes > max_bytes )); then
            skipped_for_bytes=1
            add_warning "skipped oversized file: $path"
            continue
        fi

        file_count=$((file_count + 1))
        replacement_count=$((replacement_count + count))

        planned_json="$(
            jq -c \
                --arg path "$path" \
                --argjson replacements "$count" \
                --argjson bytes "$bytes" \
                '. + [{path:$path, replacements:$replacements, bytes:$bytes}]' \
                <<<"$planned_json"
        )"
    done <"$counts_file"

    if ((file_count == 0)); then
        ((skipped_for_bytes == 1)) && return 2
        return 1
    fi

    ((file_count <= max_files)) || {
        add_error "max-files exceeded: $file_count > $max_files"
        return 2
    }

    ((replacement_count <= max_replacements)) || {
        add_error "max-replacements exceeded: $replacement_count > $max_replacements"
        return 2
    }

    return 0
}

sd_apply() {
    require_bins sd
    local path
    while IFS= read -r path; do
        [[ -n "$path" ]] || continue
        sd "$from" "$to" "$path"
    done < <(jq -r '.[].path' <<<"$planned_json")
}

structural_scope_guard() {
    if ((${#include_globs[@]} > 0 || ${#exclude_globs[@]} > 0)); then
        fail_status "blocked" "$mode does not yet support --glob/--exclude safely; scope by root instead" 4
    fi
}

case "${1:-}" in
    --help|-h) usage; exit 0 ;;
    --format=help) usage; exit 0 ;;
    --format)
        [[ "${2:-}" == "help" ]] && { usage; exit 0; }
        ;;
esac

mode="${1:-}"
[[ -n "$mode" ]] || { usage; exit 2; }
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
baseline_dirty_json="$(dirty_files_json)"

trap on_error ERR

case "$mode" in
    ast-grep)
        [[ $# -ge 3 ]] || fail_status "error" "ast-grep requires LANG PATTERN REWRITE [root]" 2
        ast_bin="$(resolve_ast_grep)"
        lang="$1"; pattern="$2"; rewrite="$3"; shift 3
        parse_tail "$@"
        structural_scope_guard

        if [[ "$apply" == "1" ]]; then
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
        match="$1"; rewrite="$2"; shift 2
        parse_tail "$@"
        structural_scope_guard

        if [[ "$apply" == "1" ]]; then
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
        from="$1"; to="$2"; shift 2
        parse_tail "$@"

        if sd_plan; then
            if [[ "$apply" == "1" ]]; then
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
