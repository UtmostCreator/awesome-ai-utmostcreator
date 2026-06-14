#!/usr/bin/env bash
# 90-snapshot.sh — snapshot creation and apply (rollback mechanism).
#
# Purpose: snapshot creation, manifest reading, apply mechanics, tracked-file
#   restore, and untracked-archive restore. Private helpers use the
#   _ai_snapshot_ prefix.
# Allowed dependencies: 00-env.sh (snapshot/protected dirs), 05-core.sh (die,
#   log_warn), 20-paths.sh (git_root, require_bins), 30-logging.sh (log_json).
#
# Critical boundary: this module is the MECHANISM only. User confirmation,
# interactive prompts, approval-env checks, and CLI parsing live in
# ai-rollback.sh, NOT here.

[[ "${AI_LIB_SNAPSHOT_LOADED:-0}" == "1" ]] && return 0
AI_LIB_SNAPSHOT_LOADED=1

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

    mkdir -p "$AI_SNAPSHOT_DIR"

    snap_base="${AI_SNAPSHOT_DIR}/${session}-${label}-${timestamp}"
    patch_file="${snap_base}.patch"
    manifest_file="${snap_base}.manifest.json"
    manifest_tmp="${manifest_file}.tmp"
    untracked_list="${snap_base}.untracked.txt"
    untracked_archive="${snap_base}.untracked.tar.gz"

    # set -e (asserted by the common.sh facade) aborts on pushd failure.
    # shellcheck disable=SC2164
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

    # shellcheck disable=SC2164
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

_ai_snapshot_manifest_value() {
    local manifest="${1:?manifest required}"
    local key="${2:?key required}"
    jq -r --arg key "$key" '.[$key] // empty' "$manifest"
}

_ai_snapshot_path_from_manifest() {
    local manifest="${1:?manifest required}"
    local key="${2:?key required}"
    local value
    local dir

    value="$(_ai_snapshot_manifest_value "$manifest" "$key")"
    [[ -n "$value" && "$value" != "null" ]] || return 1

    if [[ "$value" = /* || "$value" =~ ^[A-Za-z]: ]]; then
        printf '%s\n' "$value"
        return 0
    fi

    dir="$(dirname "$manifest")"
    printf '%s/%s\n' "$dir" "$value"
}

_ai_snapshot_protected_untracked_path() {
    local path="${1:?path required}"

    case "$path" in
    .git | .git/*)
        return 0
        ;;
    "$AI_LOG_DIR" | "$AI_LOG_DIR"/*)
        return 0
        ;;
    "$AI_CONTEXT_DIR" | "$AI_CONTEXT_DIR"/*)
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

_ai_snapshot_untracked_existed() {
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

    root="$(_ai_snapshot_manifest_value "$manifest" root)"
    base_ref="$(_ai_snapshot_manifest_value "$manifest" base_ref)"
    patch_file="$(_ai_snapshot_path_from_manifest "$manifest" patch_file || true)"
    untracked_list="$(_ai_snapshot_path_from_manifest "$manifest" untracked_list || true)"
    untracked_archive="$(_ai_snapshot_path_from_manifest "$manifest" untracked_archive || true)"

    [[ -n "$root" && -d "$root" ]] || root="$(git_root)"
    [[ -n "$base_ref" ]] || die "snapshot manifest missing base_ref"

    (
        # set -e (asserted by the common.sh facade) aborts on cd failure.
        # shellcheck disable=SC2164
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

                # Never delete protected untracked paths (.git, log/context dirs).
                if _ai_snapshot_protected_untracked_path "$path"; then
                    continue
                fi

                if ! _ai_snapshot_untracked_existed "$path" "$untracked_list"; then
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
