#!/usr/bin/env bash
# prune-shipped-targets.sh
#
# Read .ai-install-manifest.json and operate on the kit-author's local copies
# of files installed from packages/ai-universal-rules/templates/**. The 40
# entries where the manifest's `.value.source != .key` are duplicates of the
# shipped template; deleting the local copies prevents agents from editing
# the wrong side of the source/installed boundary.
#
# Modes (mutually exclusive): --list (default), --dry-run, --apply.
# Restore after --apply: `bash install-ai-kit.sh .` (or the php installer).
#
# See docs/ai/capabilities/evidence-first-execution/examples.md for the
# documented workflow.

set -euo pipefail
set -E

# Early --introspect / --help guard: when invoked with --introspect or --help/-h
# as the FIRST argument, emit this script's machine-readable JSON contract or its
# human-readable contract (static parse via sh-introspect) and exit before running
# any logic. The target script is parsed as text, never executed.
if [[ "${1:-}" == "--introspect" || "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    _ai_self_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    _ai_self_tool="$_ai_self_dir/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_self_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        if [[ "${1:-}" == "--introspect" ]]; then
            exec env AI_OUTPUT=json "${PHP_BIN:-php}" "$_ai_self_tool" "${BASH_SOURCE[0]}"
        fi
        exec "${PHP_BIN:-php}" "$_ai_self_tool" --format=help "${BASH_SOURCE[0]}"
    fi
fi

# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

SCRIPT_VERSION="1"
SCRIPT_NAME="prune-shipped-targets"

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

# ---- Argument parsing ---------------------------------------------------
mode="list"
mode_set=0
force=0
include_candidates=0
manifest_path=".ai-install-manifest.json"

set_mode() {
    if ((mode_set == 1)); then
        printf '[ERROR] only one of --list/--dry-run/--apply may be given\n' >&2
        exit 4
    fi
    mode="$1"
    mode_set=1
}

while [[ $# -gt 0 ]]; do
    case "$1" in
    --help | -h)
        usage
        exit 0
        ;;
    --list)
        set_mode list
        shift
        ;;
    --dry-run)
        set_mode dry-run
        shift
        ;;
    --apply)
        set_mode apply
        shift
        ;;
    --force)
        force=1
        shift
        ;;
    --include-candidates)
        include_candidates=1
        shift
        ;;
    --manifest)
        manifest_path="${2:?--manifest requires value}"
        shift 2
        ;;
    --manifest=*)
        manifest_path="${1#*=}"
        shift
        ;;
    *)
        printf '[ERROR] unknown argument: %s\n' "$1" >&2
        usage >&2
        exit 4
        ;;
    esac
done

# ---- Manifest validation ------------------------------------------------
if [[ ! -f "$manifest_path" ]]; then
    printf '[ERROR] manifest not found: %s\n' "$manifest_path" >&2
    exit 1
fi

require_bins jq

if ! jq -e . "$manifest_path" >/dev/null 2>&1; then
    printf '[ERROR] manifest is not valid JSON: %s\n' "$manifest_path" >&2
    exit 1
fi

# Collect (path \t pack) tuples for entries where source != key.
collect_entries() {
    jq -r '
        .files
        | to_entries[]
        | select(.value.source != .key)
        | "\(.key)\t\(.value.pack // "unknown")"
    ' "$manifest_path"
}

ENTRIES=()
while IFS= read -r entry; do
    ENTRIES+=("$entry")
done < <(collect_entries)

if ((${#ENTRIES[@]} == 0)); then
    printf '[WARN] manifest has no entries where source != key; nothing to do\n' >&2
fi

# ---- --list mode --------------------------------------------------------
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

if [[ "$mode" == "list" ]]; then
    for line in "${ENTRIES[@]+${ENTRIES[@]}}"; do
        path="${line%%$'\t'*}"
        if path_is_candidate "$path"; then
            continue
        fi
        printf '%s\n' "$path"
    done
    exit 0
fi

# Banner for --dry-run and --apply.
printf '%s v%s (mode=%s include-candidates=%d force=%d)\n' \
    "$SCRIPT_NAME" "$SCRIPT_VERSION" "$mode" "$include_candidates" "$force" >&2

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

# ---- --dry-run mode -----------------------------------------------------
if [[ "$mode" == "dry-run" ]]; then
    summary_file="$(mktemp)"
    total_bytes=0
    total_files=0
    for line in "${ENTRIES[@]+${ENTRIES[@]}}"; do
        path="${line%%$'\t'*}"
        pack="${line#*$'\t'}"
        bytes="$(path_size_bytes "$path")"
        printf '%s\t%s\n' "$pack" "$bytes" >>"$summary_file"
        total_bytes=$((total_bytes + bytes))
        total_files=$((total_files + 1))
    done

    printf '\n%-40s %8s %12s\n' "PACK" "FILES" "BYTES" >&2
    printf '%-40s %8s %12s\n' "----" "-----" "-----" >&2
    awk -F'\t' '
        { files[$1]++; bytes[$1] += $2 }
        END {
            for (pack in files) {
                printf "%-40s %8d %12d\n", pack, files[pack], bytes[pack]
            }
        }
    ' "$summary_file" | sort >&2
    rm -f "$summary_file"
    printf '%-40s %8s %12s\n' "----" "-----" "-----" >&2
    printf '%-40s %8d %12d\n' "TOTAL" "$total_files" "$total_bytes" >&2
    printf 'total_bytes=%d\n' "$total_bytes"
    exit 0
fi

# ---- --apply mode -------------------------------------------------------
# 1) clean worktree (unless --force)
if [[ "$force" != "1" ]]; then
    if [[ -n "$(git status --porcelain 2>/dev/null)" ]]; then
        printf '[ERROR] --apply refused: working tree is not clean. Commit/stash or use --force.\n' >&2
        exit 2
    fi
else
    printf '[WARN] --force given: skipping clean-worktree check\n' >&2
fi

# 2) refuse-list defense-in-depth on every collected path
for line in "${ENTRIES[@]+${ENTRIES[@]}}"; do
    path="${line%%$'\t'*}"
    if path_is_refused "$path"; then
        printf '[ERROR] refuse-list violation: manifest entry %s matches a protected prefix; refusing --apply\n' "$path" >&2
        exit 3
    fi
done

# 3) Prepare backup and log directories
ts="$(date +%Y%m%d-%H%M%S)"
repo_root_path="$(git_root)"
backup_root="$repo_root_path/.ai-backups/prune-shipped-$ts"
log_dir="$repo_root_path/.ai-logs"
log_file="$log_dir/prune-$ts.jsonl"

mkdir -p "$backup_root" || {
    printf '[ERROR] cannot mkdir %s\n' "$backup_root" >&2
    exit 4
}
mkdir -p "$log_dir" || {
    printf '[ERROR] cannot mkdir %s\n' "$log_dir" >&2
    exit 4
}

printf '[INFO] backup root: %s\n' "$backup_root" >&2
printf '[INFO] audit log:   %s\n' "$log_file" >&2

snapshot_and_delete() {
    local rel="${1:?rel path required}"
    local pack="${2:-unknown}"
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

# 4) Iterate manifest entries
for line in "${ENTRIES[@]+${ENTRIES[@]}}"; do
    path="${line%%$'\t'*}"
    pack="${line#*$'\t'}"
    snapshot_and_delete "$path" "$pack"
done

# 5) Candidate paths (only when explicitly requested AND in --apply mode)
if ((include_candidates == 1)); then
    for cand in "${CANDIDATE_PATHS[@]}"; do
        if path_is_refused "$cand"; then
            printf '[ERROR] candidate %s matches refuse-list; refusing\n' "$cand" >&2
            exit 3
        fi
        snapshot_and_delete "$cand" "candidate"
    done
fi

printf '[OK] prune complete. Restore with: bash install-ai-kit.sh .\n' >&2
exit 0
