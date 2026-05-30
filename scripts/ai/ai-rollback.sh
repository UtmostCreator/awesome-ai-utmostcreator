#!/usr/bin/env bash
# Review and apply repository-local rollback snapshots created by AI tooling sessions.

set -euo pipefail
# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

SNAPSHOT_DIR="${COPILOT_SNAPSHOT_DIR:-.ai-logs/snapshots}"

usage() {
    cat <<'EOF'
Usage:
  ai-rollback.sh list
  ai-rollback.sh show SESSION_OR_SNAPSHOT
  ai-rollback.sh apply SESSION_OR_SNAPSHOT
  ai-rollback.sh prune [--days N]

Environment:
  ROLLBACK_REMOVE_CREATED_UNTRACKED=1
EOF
}

confirm_mutation() {
    local message="$1"

    log_warn "$message"

    if [[ -t 0 ]] && [[ "${CI:-}" != "true" ]]; then
        printf '%b[WARN]%b Continue? [y/N] ' "$_C_YELLOW" "$_C_RESET" >&2
        read -r confirm
        [[ "$confirm" =~ ^[Yy]$ ]] || {
            log_info "Aborted."
            exit 0
        }
    fi
}

resolve_snapshot() {
    local input="$1"
    local match=""

    [[ -n "$input" ]] || die "session or snapshot required"

    if [[ -f "$input" ]]; then
        printf '%s\n' "$input"
        return 0
    fi

    if [[ ! -d "$SNAPSHOT_DIR" ]]; then
        die "snapshot directory not found: $SNAPSHOT_DIR"
    fi

    match="$(
        find "$SNAPSHOT_DIR" -maxdepth 1 \
            \( -name "${input}*.manifest.json" -o -name "${input}*.patch" -o -name "${input}*.ref" \) |
            sort -r |
            head -1
    )"

    [[ -n "$match" ]] || die "no snapshot found matching: $input"
    printf '%s\n' "$match"
}

snapshot_size() {
    local snap="$1"
    du -sh "$snap" 2>/dev/null | cut -f1
}

snapshot_date() {
    local snap="$1"
    stat -c '%y' "$snap" 2>/dev/null | cut -c1-16 ||
        stat -f '%Sm' -t '%Y-%m-%d %H:%M' "$snap" 2>/dev/null ||
        printf '-'
}

cmd_list() {
    if [[ ! -d "$SNAPSHOT_DIR" ]]; then
        log_warn "No snapshot directory found at $SNAPSHOT_DIR"
        exit 0
    fi

    local count=0
    local snap
    local base
    local type

    printf '%-60s  %-12s  %-10s  %s\n' "SNAPSHOT" "TYPE" "SIZE" "DATE"
    printf '%s\n' "$(printf '=%.0s' {1..100})"

    while IFS= read -r snap; do
        [[ -n "$snap" ]] || continue

        base="$(basename "$snap")"

        case "$snap" in
        *.manifest.json) type="manifest" ;;
        *.patch) type="legacy-patch" ;;
        *.ref) type="legacy-ref" ;;
        *) type="unknown" ;;
        esac

        printf '%-60s  %-12s  %-10s  %s\n' \
            "$base" \
            "$type" \
            "$(snapshot_size "$snap")" \
            "$(snapshot_date "$snap")"

        count=$((count + 1))
    done < <(
        find "$SNAPSHOT_DIR" -maxdepth 1 \
            \( -name '*.manifest.json' -o -name '*.patch' -o -name '*.ref' \) |
            sort -r
    )

    printf '\n%d snapshot artifact(s) found\n' "$count"
}

cmd_show_manifest() {
    local manifest="$1"
    local patch_file
    local untracked_list
    local untracked_archive

    jq '{
      version,
      session,
      label,
      base_ref,
      root,
      patch_file,
      untracked_list,
      untracked_archive,
      has_untracked_archive,
      ts
    }' "$manifest"

    patch_file="$(jq -r '.patch_file // empty' "$manifest")"
    untracked_list="$(jq -r '.untracked_list // empty' "$manifest")"
    untracked_archive="$(jq -r '.untracked_archive // empty' "$manifest")"

    if [[ -n "$patch_file" && -f "$patch_file" && -s "$patch_file" ]]; then
        echo
        echo "## Patch stat"
        git apply --stat "$patch_file" 2>/dev/null || sed -n '1,80p' "$patch_file"
    fi

    if [[ -n "$untracked_list" && -f "$untracked_list" && -s "$untracked_list" ]]; then
        echo
        echo "## Untracked files captured"
        sed -n '1,120p' "$untracked_list"
    fi

    if [[ -n "$untracked_archive" && "$untracked_archive" != "null" ]]; then
        echo
        echo "## Untracked archive"
        printf '%s\n' "$untracked_archive"
    fi
}

cmd_show() {
    local input="${1:?session or snapshot required}"
    local snap

    snap="$(resolve_snapshot "$input")"
    log_info "Snapshot: $snap"

    case "$snap" in
    *.manifest.json)
        cmd_show_manifest "$snap"
        ;;
    *.ref)
        local ref
        ref="$(<"$snap")"
        log_info "Type: legacy ref"
        git show --stat "$ref"
        ;;
    *.patch)
        log_info "Type: legacy patch"
        git apply --stat "$snap" 2>/dev/null || sed -n '1,120p' "$snap"
        ;;
    *)
        die "unsupported snapshot type: $snap"
        ;;
    esac
}

cmd_apply() {
    local input="${1:?session or snapshot required}"
    local snap

    snap="$(resolve_snapshot "$input")"

    confirm_mutation "Rollback modifies the working tree and may remove created untracked files."

    snapshot_apply "$snap"

    log_ok "Rollback applied"
    git --no-pager diff --stat || true
    log_json "rollback.apply" "$(jq -cn --arg snapshot "$snap" '{snapshot:$snapshot}')"
}

cmd_prune() {
    local days=14
    local count=0
    local snap

    while [[ $# -gt 0 ]]; do
        case "$1" in
        --days)
            days="${2:?days required}"
            shift 2
            ;;
        --days=*)
            days="${1#*=}"
            shift
            ;;
        *) die "unknown option: $1" ;;
        esac
    done

    confirm_mutation "Pruning rollback snapshots older than $days days deletes recovery artifacts."

    if [[ ! -d "$SNAPSHOT_DIR" ]]; then
        log_warn "No snapshot directory found at $SNAPSHOT_DIR"
        exit 0
    fi

    while IFS= read -r snap; do
        [[ -n "$snap" ]] || continue
        rm -f "$snap"
        count=$((count + 1))
    done < <(
        find "$SNAPSHOT_DIR" -maxdepth 1 \
            \( -name '*.manifest.json' -o -name '*.patch' -o -name '*.ref' -o -name '*.untracked.txt' -o -name '*.untracked.tar.gz' \) \
            -mtime +"$days" 2>/dev/null
    )

    log_ok "Pruned $count snapshot artifact(s)"
    log_json "rollback.prune" "$(jq -cn --argjson days "$days" --argjson count "$count" '{days:$days, count:$count}')"
}

agent_session_init "ai-rollback"
require_bins jq git

cmd="${1:-}"
[[ -n "$cmd" ]] || {
    usage
    exit 1
}
shift || true

case "$cmd" in
list) cmd_list ;;
show) cmd_show "${1:-}" ;;
apply) cmd_apply "${1:-}" ;;
prune) cmd_prune "$@" ;;
--help | -h) usage ;;
*)
    usage
    die "unknown command: $cmd"
    ;;
esac
