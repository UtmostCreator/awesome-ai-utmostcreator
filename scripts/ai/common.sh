#!/usr/bin/env bash
# Shared library for repository AI tooling scripts.

set -euo pipefail

COPILOT_LOG_DIR="${COPILOT_LOG_DIR:-${AI_LOG_DIR:-.ai-logs}}"
COPILOT_CONTEXT_DIR="${COPILOT_CONTEXT_DIR:-.repomix-context}"
COPILOT_SESSION_DIR="${COPILOT_SESSION_DIR:-${COPILOT_LOG_DIR}/sessions}"
COPILOT_SNAPSHOT_DIR="${COPILOT_SNAPSHOT_DIR:-${COPILOT_LOG_DIR}/snapshots}"
COPILOT_EVENT_LOG="${COPILOT_EVENT_LOG:-${COPILOT_LOG_DIR}/tool-usage.jsonl}"
AI_SESSION_GENERATED_DIR="${AI_SESSION_GENERATED_DIR:-docs/ai/generated/sessions}"

if [[ -z "${NO_COLOR:-}" ]] && [[ -t 2 ]]; then
    _C_RESET=$'\033[0m'
    _C_RED=$'\033[0;31m'
    _C_YELLOW=$'\033[0;33m'
    _C_GREEN=$'\033[0;32m'
    _C_CYAN=$'\033[0;36m'
    _C_BOLD=$'\033[1m'
else
    _C_RESET=''
    _C_RED=''
    _C_YELLOW=''
    _C_GREEN=''
    _C_CYAN=''
    _C_BOLD=''
fi

agent_session_init() {
    local name="${1:-$(basename "$0" .sh)}"
    SESSION_ID="${SESSION_ID:-${name}-$(date +%Y%m%d-%H%M%S)-$$}"
    TRACE_ID="${TRACE_ID:-trc-${SESSION_ID}}"
    TASK_ID="${TASK_ID:-tsk-${SESSION_ID}}"
    SESSION_DIR="${COPILOT_SESSION_DIR}/${SESSION_ID}"
    SESSION_LOG="${SESSION_DIR}/session.jsonl"
    mkdir -p "$SESSION_DIR" "$COPILOT_LOG_DIR" "$COPILOT_SNAPSHOT_DIR"
    log_json "session.start" '{}' || true
}

append_log_entry() {
    local entry="${1:?entry required}"
    local repo_root

    mkdir -p "$COPILOT_LOG_DIR"
    printf '%s\n' "$entry" >>"$COPILOT_EVENT_LOG"

    if [[ -n "${SESSION_LOG:-}" ]]; then
        printf '%s\n' "$entry" >>"$SESSION_LOG"
    fi

    if [[ "${AI_SESSION_DURABLE_LOG:-1}" == "1" && -n "${SESSION_ID:-}" ]]; then
        repo_root="$(git_root 2>/dev/null || pwd)"
        if command -v php >/dev/null 2>&1 && [[ -f "$repo_root/tools/ai/agent-log.php" ]]; then
            php "$repo_root/tools/ai/agent-log.php" --root "$repo_root" --session-id "$SESSION_ID" --event-json "$entry" >/dev/null 2>&1 || true
        fi
    fi
}

log_json() {
    local event="${1:-event}"
    local payload="${2:-{}}"
    local payload_json
    local entry

    if ! payload_json="$(jq -c . <<<"$payload" 2>/dev/null)"; then
        payload_json="$(jq -cn --arg raw "$payload" '{raw:$raw}')"
    fi

        entry="$(jq -cn \
                --arg event_version "1.1" \
                --arg event_type "$event" \
                --arg trace_id "${TRACE_ID:-unknown}" \
                --arg session_id "${SESSION_ID:-unknown}" \
                --arg task_id "${TASK_ID:-unknown}" \
                --arg timestamp "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
                --arg actor_id "${ACTOR_ID:-$(basename "${BASH_SOURCE[1]:-unknown}" .sh)}" \
                --arg delegated_by "${DELEGATED_BY:-}" \
                --arg tool_name "$(basename "${BASH_SOURCE[1]:-unknown}")" \
                --arg repo_root "$(git_root 2>/dev/null || pwd)" \
                --arg git_branch "$(git rev-parse --abbrev-ref HEAD 2>/dev/null || printf 'unknown')" \
                --arg git_commit "$(git rev-parse HEAD 2>/dev/null || printf 'unknown')" \
                --argjson data "$payload_json" \
                '{
                    event_version: $event_version,
                    event_type: $event_type,
                    trace_id: $trace_id,
                    session_id: $session_id,
                    task_id: $task_id,
                    timestamp: $timestamp,
                    actor: {
                        type: "agent",
                        id: $actor_id,
                        delegated_by: (if $delegated_by == "" then null else $delegated_by end)
                    },
                    tool: {
                        name: $tool_name,
                        category: null,
                        args_hash: null,
                        mutates_state: false
                    },
                    authorization: {
                        policy_version: null,
                        decision: "unknown",
                        approval_required: null,
                        approved_by: null,
                        reason: null
                    },
                    execution: {
                        status: "unknown",
                        latency_ms: null,
                        retry_count: 0,
                        exit_code: null,
                        output_truncated: null
                    },
                    cost: {
                        model: null,
                        input_tokens: null,
                        output_tokens: null,
                        estimated_cost_usd: null
                    },
                    failure: {
                        category: null,
                        message: null,
                        resolution: null
                    },
                    repository: {
                        root: $repo_root,
                        git_branch: (if $git_branch == "" or $git_branch == "unknown" then null else $git_branch end),
                        git_commit: (if $git_commit == "" or $git_commit == "unknown" then null else $git_commit end)
                    },
                    output: {
                        preview: null
                    },
                    details: (if ($data | type) == "object" then $data else {raw: $data} end)
                }')"

        append_log_entry "$entry"
}

log_info() { printf '%b[INFO]%b  %s\n' "$_C_CYAN" "$_C_RESET" "$*" >&2; }
log_ok() { printf '%b[OK]%b    %s\n' "$_C_GREEN" "$_C_RESET" "$*" >&2; }
log_warn() { printf '%b[WARN]%b  %s\n' "$_C_YELLOW" "$_C_RESET" "$*" >&2; }
log_error() { printf '%b[ERROR]%b %s\n' "$_C_RED" "$_C_RESET" "$*" >&2; }

die() {
    log_error "$*"
    log_json "error" "$(jq -cn --arg msg "$*" '{msg:$msg}')" || true
    exit 1
}

section() {
    printf '\n%b==> %s%b\n' "$_C_BOLD" "$*" "$_C_RESET" >&2
}

require_bins() {
    local missing=()
    local bin
    for bin in "$@"; do
        command -v "$bin" >/dev/null 2>&1 || missing+=("$bin")
    done
    if ((${#missing[@]} > 0)); then
        die "required tools not found: ${missing[*]}"
    fi
}

require_clean_tree() {
    git rev-parse --is-inside-work-tree >/dev/null 2>&1 || die "not inside a git repository"
    if ! git diff --quiet || ! git diff --cached --quiet; then
        die "working tree is not clean; commit or stash changes first"
    fi
}

git_root() {
    git rev-parse --show-toplevel 2>/dev/null || pwd
}

run_with_timeout() {
    local seconds="${1:?seconds required}"
    shift
    local timeout_bin=""
    if command -v gtimeout >/dev/null 2>&1; then
        timeout_bin="gtimeout"
    elif command -v timeout >/dev/null 2>&1; then
        timeout_bin="timeout"
    fi

    if [[ -n "$timeout_bin" ]]; then
        "$timeout_bin" "$seconds" "$@"
    else
        "$@"
    fi
}

require_clean_secret_scan() {
    local target="${1:-.}"

    if [[ "${SECRETS_SCAN:-1}" != "1" ]]; then
        log_warn "SECRETS_SCAN disabled"
        return 0
    fi

    if ! secrets_scan "$target"; then
        die "secrets detected; refusing to continue"
    fi
}

estimate_file_tokens_fallback() {
    local file="${1:?file required}"
    local bytes
    bytes="$(wc -c <"$file" | tr -d ' ')"
    echo $(((bytes + 3) / 4))
}

estimate_tokens() {
    local file="${1:?file required}"

    if [[ -n "${TOKEN_ESTIMATOR_CMD:-}" ]]; then
        local estimated=""

        estimated="$($TOKEN_ESTIMATOR_CMD "$file" 2>/dev/null || true)"

        if [[ "$estimated" =~ ^[0-9]+$ ]]; then
            printf '%s\n' "$estimated"
            return 0
        fi

        log_warn "TOKEN_ESTIMATOR_CMD failed or returned non-integer output; falling back to bytes/4"
    fi

    estimate_file_tokens_fallback "$file"
}

within_token_budget() {
    local file="${1:?file required}"
    local max="${2:-128000}"
    local tokens
    tokens="$(estimate_tokens "$file")"
    ((tokens <= max))
}

secrets_scan() {
    local target="${1:-.}"
    if command -v gitleaks >/dev/null 2>&1; then
        gitleaks detect --source "$target" --redact --no-banner --exit-code 1 >/dev/null 2>&1
    else
        log_warn "gitleaks not installed; skipping secrets scan"
        return 0
    fi
}

snapshot_create() {
    local label="${1:-snap}"
    local session="${SESSION_ID:-manual}"
    local timestamp
    local snap_base
    local patch_file
    local manifest_file
    local manifest_tmp
    local untracked_list
    local untracked_archive
    local base_ref
    local root
    local has_untracked_archive_json=false

    git rev-parse --is-inside-work-tree >/dev/null 2>&1 || die "not inside a git repository"

    root="$(git_root)"
    timestamp="$(date +%H%M%S)"
    base_ref="$(git -C "$root" rev-parse HEAD)"

    mkdir -p "$COPILOT_SNAPSHOT_DIR"

    snap_base="${COPILOT_SNAPSHOT_DIR}/${session}-${label}-${timestamp}"
    patch_file="${snap_base}.patch"
    manifest_file="${snap_base}.manifest.json"
    manifest_tmp="${manifest_file}.tmp"
    untracked_list="${snap_base}.untracked.txt"
    untracked_archive="${snap_base}.untracked.tar.gz"

    pushd "$root" >/dev/null

    git diff --binary HEAD >"$patch_file"
    git ls-files --others --exclude-standard >"$untracked_list"

    if [[ -s "$untracked_list" ]]; then
        if command -v tar >/dev/null 2>&1; then
            if tar -czf "$untracked_archive" -T "$untracked_list" 2>/dev/null; then
                has_untracked_archive_json=true
            else
                rm -f "$untracked_archive"
                log_warn "failed to archive untracked files for snapshot"
            fi
        else
            log_warn "tar not installed; untracked file contents will not be archived"
        fi
    fi

    popd >/dev/null

    jq -n \
        --arg version "2" \
        --arg session "$session" \
        --arg label "$label" \
        --arg base_ref "$base_ref" \
        --arg root "$root" \
        --arg patch_file "$patch_file" \
        --arg manifest_file "$manifest_file" \
        --arg untracked_list "$untracked_list" \
        --arg untracked_archive "$untracked_archive" \
        --arg ts "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
        --argjson has_untracked_archive "$has_untracked_archive_json" \
        '{
          version: ($version | tonumber),
          session: $session,
          label: $label,
          base_ref: $base_ref,
          root: $root,
          patch_file: $patch_file,
          manifest_file: $manifest_file,
          untracked_list: $untracked_list,
          untracked_archive: (if $has_untracked_archive then $untracked_archive else null end),
          has_untracked_archive: $has_untracked_archive,
          ts: $ts
        }' >"$manifest_tmp"

    mv "$manifest_tmp" "$manifest_file"

    log_json "snapshot.create" "$(cat "$manifest_file")" || true
    printf '%s\n' "$manifest_file"
}

_snapshot_manifest_value() {
    local manifest="${1:?manifest required}"
    local key="${2:?key required}"
    jq -r --arg key "$key" '.[$key] // empty' "$manifest"
}

_snapshot_path_from_manifest() {
    local manifest="${1:?manifest required}"
    local key="${2:?key required}"
    local value
    local dir

    value="$(_snapshot_manifest_value "$manifest" "$key")"
    [[ -n "$value" && "$value" != "null" ]] || return 1

    if [[ "$value" = /* || "$value" =~ ^[A-Za-z]: ]]; then
        printf '%s\n' "$value"
        return 0
    fi

    dir="$(dirname "$manifest")"
    printf '%s/%s\n' "$dir" "$value"
}

_snapshot_protected_untracked_path() {
    local path="${1:?path required}"

    case "$path" in
    .git | .git/*)
        return 0
        ;;
    "$COPILOT_LOG_DIR" | "$COPILOT_LOG_DIR"/*)
        return 0
        ;;
    "$COPILOT_CONTEXT_DIR" | "$COPILOT_CONTEXT_DIR"/*)
        return 0
        ;;
    .ai-logs | .ai-logs/*)
        return 0
        ;;
    .repomix-context | .repomix-context/*)
        return 0
        ;;
    esac

    return 1
}

_snapshot_untracked_existed() {
    local file="${1:?file required}"
    local list="${2:?list required}"

    [[ -f "$list" ]] || return 1
    grep -Fxq "$file" "$list"
}

snapshot_apply_manifest() {
    local manifest="${1:?manifest file required}"
    local root
    local base_ref
    local patch_file
    local untracked_list
    local untracked_archive
    local current_untracked=()
    local path

    [[ -f "$manifest" ]] || die "snapshot manifest not found: $manifest"

    require_bins jq git

    root="$(_snapshot_manifest_value "$manifest" root)"
    base_ref="$(_snapshot_manifest_value "$manifest" base_ref)"
    patch_file="$(_snapshot_path_from_manifest "$manifest" patch_file || true)"
    untracked_list="$(_snapshot_path_from_manifest "$manifest" untracked_list || true)"
    untracked_archive="$(_snapshot_path_from_manifest "$manifest" untracked_archive || true)"

    [[ -n "$root" && -d "$root" ]] || root="$(git_root)"
    [[ -n "$base_ref" ]] || die "snapshot manifest missing base_ref"

    (
        cd "$root"

        log_warn "Applying rollback snapshot. This will reset tracked files to snapshot state."

        mapfile -t current_untracked < <(git ls-files --others --exclude-standard)

        git reset --hard "$base_ref" >/dev/null

        if [[ -n "$patch_file" && -f "$patch_file" && -s "$patch_file" ]]; then
            git apply --whitespace=fix "$patch_file"
        fi

        if [[ "${ROLLBACK_REMOVE_CREATED_UNTRACKED:-1}" == "1" ]]; then
            for path in "${current_untracked[@]+${current_untracked[@]}}"; do
                [[ -n "$path" ]] || continue

                if _snapshot_protected_untracked_path "$path"; then
                    continue
                fi

                if ! _snapshot_untracked_existed "$path" "$untracked_list"; then
                    rm -f -- "$path"
                fi
            done
        else
            log_warn "ROLLBACK_REMOVE_CREATED_UNTRACKED=0; created untracked files were not removed"
        fi

        if [[ -n "$untracked_archive" && -f "$untracked_archive" ]]; then
            tar -xzf "$untracked_archive"
        fi
    )

    log_json "snapshot.apply" "$(cat "$manifest")" || true
}

snapshot_apply() {
    local snap_file="${1:?snapshot file required}"

    [[ -f "$snap_file" ]] || die "snapshot not found: $snap_file"

    case "$snap_file" in
    *.manifest.json)
        snapshot_apply_manifest "$snap_file"
        ;;
    *.ref)
        local ref
        ref="$(<"$snap_file")"
        git reset --hard "$ref"
        log_json "snapshot.apply.legacy_ref" "$(jq -cn --arg file "$snap_file" --arg ref "$ref" '{file:$file, ref:$ref}')" || true
        ;;
    *.patch)
        git apply --whitespace=fix "$snap_file"
        log_json "snapshot.apply.legacy_patch" "$(jq -cn --arg file "$snap_file" '{file:$file}')" || true
        ;;
    *)
        die "unsupported snapshot type: $snap_file"
        ;;
    esac
}
