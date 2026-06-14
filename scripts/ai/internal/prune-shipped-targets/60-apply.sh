# shellcheck shell=bash
# prune-shipped-targets/60-apply.sh — the ONLY module that deletes.
#
# Sourced by scripts/ai/prune-shipped-targets.sh (thin loader). Not an
# entrypoint. Isolating the destructive snapshot+delete here keeps the
# mutation surface auditable (every other module is read-only). Behavior is
# byte-for-byte identical to the previous monolithic version.

snapshot_and_delete() {
    local rel="${1:?rel path required}"
    local pack="${2:-unknown}"
    # repo_root_path / backup_root / log_file are set by prune_run (90-run.sh)
    # and reach here via dynamic scope; shellcheck cannot see that cross-module.
    # shellcheck disable=SC2154
    local abs="$repo_root_path/$rel"

    if [[ ! -e "$abs" ]]; then
        printf '[WARN] not present, skipping: %s\n' "$rel" >&2
        return 0
    fi

    # Defense-in-depth: assert path inside repo and not refused.
    assert_inside_repo "$abs"
    if path_is_refused "$rel"; then
        printf '[ERROR] refuse-list violation at delete time: %s\n' "$rel" >&2
        exit 3
    fi

    # shellcheck disable=SC2154
    local snap_dest="$backup_root/$rel"
    mkdir -p "$(dirname "$snap_dest")"

    local sha=""
    if [[ -f "$abs" ]]; then
        cp -p "$abs" "$snap_dest"
        if command_exists sha256sum; then
            sha="$(sha256sum "$abs" | awk '{print $1}')"
        elif command_exists shasum; then
            sha="$(shasum -a 256 "$abs" | awk '{print $1}')"
        fi
    elif [[ -d "$abs" ]]; then
        # Recursive copy preserving structure; cp -a not available everywhere
        cp -R "$abs/." "$snap_dest/"
        sha="dir"
    fi

    # JSONL log entry written BEFORE deletion so it survives any rm failure.
    # shellcheck disable=SC2154
    jq -cn \
        --arg ts "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
        --arg path "$rel" \
        --arg sha256_before "$sha" \
        --arg snapshot_path "$snap_dest" \
        --arg pack "$pack" \
        '{ts:$ts, path:$path, sha256_before:$sha256_before, snapshot_path:$snapshot_path, pack:$pack}' \
        >>"$log_file"

    rm -rf -- "$abs"
    printf '[OK] deleted: %s (snapshot=%s)\n' "$rel" "$snap_dest" >&2
}
