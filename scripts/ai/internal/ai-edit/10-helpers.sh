# shellcheck shell=bash
# shellcheck disable=SC2154,SC2034  # cross-module globals set by ai_edit_main/parse_tail via dynamic scope
# ai-edit/10-helpers.sh — JSON/diff/session/status helpers.
#
# Sourced by scripts/ai/ai-edit.sh (thin loader). Not an entrypoint. Behavior is
# byte-for-byte identical to the previous monolithic ai-edit.sh.

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

    # shellcheck disable=SC2086  # $manifest_path is injected as a JSON string literal into the jq program; path is repo-internal
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
        limit_exceeded | blocked | error | verify_failed) printf '%s\n' "$status" >&2 ;;
        esac
    fi

    exit "$exit_code"
}

# shellcheck disable=SC2329  # invoked indirectly via `trap on_error ERR`
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
