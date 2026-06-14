# shellcheck shell=bash
# prune-shipped-targets/90-run.sh — argument parsing, manifest validation, and
# the list / dry-run / apply mode flow.
#
# Sourced by scripts/ai/prune-shipped-targets.sh (thin loader). Not an
# entrypoint. The whole driver runs inside prune_run so top-level `exit`s and
# the global state used by snapshot_and_delete (repo_root_path, backup_root,
# log_file) behave exactly as in the previous monolithic version: they are
# declared here and are visible to snapshot_and_delete via dynamic scope because
# it is only ever called from within prune_run.

prune_run() {
    # ---- Argument parsing -----------------------------------------------
    local mode="list"
    local mode_set=0
    local force=0
    local include_candidates=0
    local manifest_path=".ai-install-manifest.json"

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

    # ---- Manifest validation --------------------------------------------
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

    local -a ENTRIES=()
    local entry
    while IFS= read -r entry; do
        ENTRIES+=("$entry")
    done < <(collect_entries)

    if ((${#ENTRIES[@]} == 0)); then
        printf '[WARN] manifest has no entries where source != key; nothing to do\n' >&2
    fi

    local line path pack

    # ---- --list mode ----------------------------------------------------
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

    # ---- --dry-run mode -------------------------------------------------
    if [[ "$mode" == "dry-run" ]]; then
        local summary_file total_bytes=0 total_files=0 bytes
        summary_file="$(mktemp)"
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

    # ---- --apply mode ---------------------------------------------------
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

    # 3) Prepare backup and log directories. repo_root_path/backup_root/log_file
    # are intentionally NOT local: snapshot_and_delete reads them via dynamic
    # scope (it is only called from inside prune_run).
    local ts
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

    # 4) Iterate manifest entries
    for line in "${ENTRIES[@]+${ENTRIES[@]}}"; do
        path="${line%%$'\t'*}"
        pack="${line#*$'\t'}"
        snapshot_and_delete "$path" "$pack"
    done

    # 5) Candidate paths (only when explicitly requested AND in --apply mode)
    if ((include_candidates == 1)); then
        local cand
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
}
