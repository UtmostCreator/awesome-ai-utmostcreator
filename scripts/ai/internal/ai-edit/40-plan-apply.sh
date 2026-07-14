# shellcheck shell=bash
# shellcheck disable=SC2154,SC2034  # cross-module globals set by ai_edit_main/parse_tail via dynamic scope
# ai-edit/40-plan-apply.sh — per-mode planning and apply helpers.
#
# Sourced by scripts/ai/ai-edit.sh (thin loader). Not an entrypoint. Mutations
# (sd_apply, patch_apply, and the ast-grep/comby in-place runs in the dispatch)
# only execute when apply=1 with a snapshot taken first. Behavior is
# byte-for-byte identical to the previous monolithic ai-edit.sh.

sd_plan() {
    require_bins rg
    build_rg_args

    local counts_file line path count bytes file_count=0 replacement_count=0 skipped_for_bytes=0
    counts_file="$SESSION_DIR/sd-counts.txt"
    mkdir -p "$SESSION_DIR"

    # Capture rg's real exit code directly. Negating with `! rg ...` would reset
    # $? to 0 inside the branch and lose rg's status (1 = no matches, 2 = error).
    #
    # Use --count-matches (total matches), not -c (matching LINES). sd replaces
    # every occurrence, so a line with three matches is three replacements; -c
    # would report one and let --max-replacements be silently undercounted. The
    # per-line output format stays `path:count`, so the parsing loop is unchanged.
    local rc=0
    rg --count-matches "${rg_args[@]}" "$from" "$root" >"$counts_file" || rc=$?
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
        if ((bytes > max_bytes)); then
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

# --- patch mode: apply an agent-supplied unified diff transactionally ---------

# Deny list for patch targets. A unified diff can name ANY path, so patch mode
# needs its own guard independent of the rg-based default_excludes (which only
# affect sd discovery). Secrets, key material, and common binary blobs must
# never be written via an opaque agent-supplied diff.
patch_denied_globs=(
    ".env" ".env.*"
    "*.pem" "*.key" "*.crt" "*.p12" "*.pfx"
    "*.sqlite" "*.db"
    "*.png" "*.jpg" "*.jpeg" "*.gif" "*.webp" "*.ico" "*.pdf"
    "*.zip" "*.tar" "*.gz" "*.tgz" "*.bz2" "*.xz" "*.7z"
)

# Materialize the incoming patch into the session dir so we have a stable,
# inspectable artifact regardless of stdin vs file input.
patch_materialize() {
    local input="$1"

    mkdir -p "$SESSION_DIR"
    patch_file="$SESSION_DIR/agent.patch"

    if [[ "$input" == "-" ]]; then
        cat >"$patch_file"
    else
        [[ -f "$input" ]] || fail_status "error" "patch file not found: $input" 2
        cp -- "$input" "$patch_file"
    fi

    [[ -s "$patch_file" ]] || fail_status "error" "patch is empty" 2
}

# Emit the destination paths the patch would write. git apply --numstat prints
# `added<TAB>deleted<TAB>path`; renames render as `old => new` (or brace form)
# and we keep the destination. /dev/null deletions are dropped.
patch_changed_paths() {
    git apply --numstat "$patch_file" 2>/dev/null |
        awk -F'\t' '
            NF >= 3 {
                path = $3
                # rename forms: "old => new" and "pre{old => new}post"
                if (index(path, "=>") > 0) {
                    sub(/^.*=> /, "", path)
                    gsub(/[{}]/, "", path)
                }
                if (path != "/dev/null" && path != "")
                    print path
            }
        ' |
        sort -u
}

# Block destinations that are unsafe to write from an opaque diff.
patch_guard_paths() {
    local paths bad="" p g matched
    paths="$(patch_changed_paths)"

    while IFS= read -r p; do
        [[ -n "$p" ]] || continue
        case "$p" in
        /* | ../* | */../* | .git | .git/*)
            bad+="$p"$'\n'
            continue
            ;;
        esac
        matched=0
        for g in "${patch_denied_globs[@]}"; do
            # shellcheck disable=SC2053  # intentional glob match, not literal compare
            if [[ "$(basename -- "$p")" == $g || "$p" == $g ]]; then
                matched=1
                break
            fi
        done
        ((matched == 1)) && bad+="$p"$'\n'
    done <<<"$paths"

    if [[ -n "$bad" ]]; then
        add_error "patch contains unsafe or protected paths"
        while IFS= read -r p; do
            [[ -n "$p" ]] && add_error "unsafe patch path: $p"
        done <<<"$bad"
        finish "blocked" 4
    fi
}

patch_plan() {
    require_bins git jq
    patch_materialize "$patch_path"
    patch_guard_paths

    if ! git apply --check "$patch_file" >/dev/null 2>"$SESSION_DIR/patch-check.log"; then
        add_error "patch does not apply cleanly; see $SESSION_DIR/patch-check.log"
        finish "blocked" 4
    fi

    patch_changed_files_json="$(patch_changed_paths | jq -R . | jq -s -c .)"

    local file_count
    file_count="$(jq 'length' <<<"$patch_changed_files_json")"

    ((file_count > 0)) || finish "no_matches" 0

    ((file_count <= max_files)) || {
        add_error "max-files exceeded: $file_count > $max_files"
        finish "limit_exceeded" 3
    }

    planned_json="$(
        jq -c \
            --argjson files "$patch_changed_files_json" \
            '$files | map({path: ., replacements: null, bytes: null, operation: "patch"})' \
            <<<'[]'
    )"
}

patch_apply() {
    require_bins git
    # --whitespace=warn (not fix): never silently rewrite agent-supplied content.
    git apply --whitespace=warn "$patch_file"
}
