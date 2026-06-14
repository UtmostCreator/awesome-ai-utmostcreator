# shellcheck shell=bash
# prune-shipped-targets/10-rules.sh — usage text, refuse-list, and read-only
# predicates / sizing helpers.
#
# Sourced by scripts/ai/prune-shipped-targets.sh (thin loader). Not an
# entrypoint. NOTHING here deletes; deletion lives only in 60-apply.sh.
# Behavior is byte-for-byte identical to the previous monolithic version.

usage() {
    cat <<'EOF'
Usage:
  bash scripts/ai/prune-shipped-targets.sh [--list | --dry-run | --apply] [--force]
                                            [--include-candidates]
                                            [--manifest PATH]

Modes (mutually exclusive):
  --list         (default) print one path per line that WOULD be removed.
                 Read-only. Exit 0.
  --dry-run      group by pack, print file+byte counts, no deletion.
                 Read-only. Exit 0.
  --apply        actually delete. Requires clean worktree (unless --force).
                 Snapshots each path to .ai-backups/prune-shipped-<ts>/
                 BEFORE deletion. Writes JSONL log to
                 .ai-logs/prune-<ts>.jsonl.

Options:
  --include-candidates   only meaningful with --apply. Also delete the two
                         candidate paths that are referenced in
                         tools/ai/install/packs.php but absent from the
                         manifest (AGENTS.md and opencode.jsonc).
                         Off by default.
  --force        bypass the clean-worktree refusal (logs WARN).
  --manifest PATH  override .ai-install-manifest.json path.
  --help, -h     show this help and exit 0.

Exit codes:
  0  success (--list/--dry-run always 0 when manifest valid)
  1  manifest missing or invalid JSON
  2  --apply refused due to dirty worktree
  3  --apply refused due to refuse-list violation (defense in depth)
  4  internal error (jq failed, mkdir failed, etc.)

Restore after --apply:
  bash install-ai-kit.sh .
EOF
}

# ---- Hardcoded refuse-list (defense-in-depth) ---------------------------
# Prefix patterns checked against repo-relative paths BEFORE any rm. The
# manifest is never trusted blindly; even if a future generator placed one
# of these prefixes in the manifest, --apply will refuse with exit 3.
REFUSE_PREFIXES=(
    "packages/"
    "tools/"
    "scripts/ai/"
    "tests/"
    "schemas/ai/"
    ".git/"
    ".ai-logs/"
    ".ai-backups/"
    "docs/ai/generated/"
    "vendor/"
    "node_modules/"
)

# The two candidate paths intentionally outside the manifest. Only deleted
# when --apply --include-candidates is set.
CANDIDATE_PATHS=(
    "AGENTS.md"
    "opencode.jsonc"
)

path_is_refused() {
    local rel="${1:?rel path required}"
    local prefix
    # The script itself must never be deleted.
    if [[ "$rel" == "scripts/ai/prune-shipped-targets.sh" ]]; then
        return 0
    fi
    for prefix in "${REFUSE_PREFIXES[@]}"; do
        if [[ "$rel" == "$prefix"* ]]; then
            return 0
        fi
    done
    return 1
}

# Defense-in-depth: candidate paths (AGENTS.md, opencode.jsonc) are an
# --apply --include-candidates concept only. They must never appear in --list,
# even if a generator places them in the manifest with source != key.
path_is_candidate() {
    local rel="${1:?rel path required}"
    local cand
    for cand in "${CANDIDATE_PATHS[@]}"; do
        [[ "$rel" == "$cand" ]] && return 0
    done
    return 1
}

# Helper: size in bytes for a file OR directory (recursive).
path_size_bytes() {
    local p="${1:?path required}"
    if [[ ! -e "$p" ]]; then
        printf '0\n'
        return 0
    fi
    if [[ -d "$p" ]]; then
        # Sum sizes of all regular files under the dir. du -sb is GNU-only;
        # use a find+stat loop for portability with Git Bash on Windows.
        local total=0 fsize
        while IFS= read -r -d '' f; do
            fsize="$(wc -c <"$f" 2>/dev/null | tr -d ' \r\n')"
            [[ -z "$fsize" ]] && fsize=0
            total=$((total + fsize))
        done < <(find "$p" -type f -print0 2>/dev/null)
        printf '%d\n' "$total"
    else
        local fsize
        fsize="$(wc -c <"$p" 2>/dev/null | tr -d ' \r\n')"
        [[ -z "$fsize" ]] && fsize=0
        printf '%d\n' "$fsize"
    fi
}
