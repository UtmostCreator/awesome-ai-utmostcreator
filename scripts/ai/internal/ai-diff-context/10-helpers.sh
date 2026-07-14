# shellcheck shell=bash
# shellcheck disable=SC2154,SC2034  # cross-module globals via dynamic scope
# shellcheck disable=SC2164  # cd calls are inside ( ) subshells under set -e (output captured); a failed cd aborts only the subshell — behavior preserved verbatim from the monolith
# ai-diff-context/10-helpers.sh — option parsing, file collection, packing helpers.
#
# Sourced by scripts/ai/ai-diff-context.sh (thin loader). Not an entrypoint.
# Behavior is byte-for-byte identical to the previous monolithic version.

parse_common_option() {
    COMMON_OPTION_CONSUMED=0

    case "${1:-}" in
    --include-diffs)
        INCLUDE_DIFFS=1
        COMMON_OPTION_CONSUMED=1
        return 0
        ;;
    --no-tests)
        INCLUDE_TESTS=0
        COMMON_OPTION_CONSUMED=1
        return 0
        ;;
    --no-secrets-scan)
        SECRETS_SCAN=0
        COMMON_OPTION_CONSUMED=1
        return 0
        ;;
    --dry-run)
        DRY_RUN=1
        COMMON_OPTION_CONSUMED=1
        return 0
        ;;
    --strict)
        STRICT_TOKENS=1
        COMMON_OPTION_CONSUMED=1
        return 0
        ;;
    --token-budget)
        TOKEN_BUDGET="${2:?token budget required}"
        COMMON_OPTION_CONSUMED=2
        return 0
        ;;
    --token-budget=*)
        TOKEN_BUDGET="${1#*=}"
        COMMON_OPTION_CONSUMED=1
        return 0
        ;;
    --split)
        SPLIT_OUTPUT="${2:?split size required}"
        COMMON_OPTION_CONSUMED=2
        return 0
        ;;
    --split=*)
        SPLIT_OUTPUT="${1#*=}"
        COMMON_OPTION_CONSUMED=1
        return 0
        ;;
    --help | -h)
        usage
        exit 0
        ;;
    esac

    return 1
}

repo_relative_file() {
    local input="$1"
    local root
    root="$(git_root)"

    input="${input#./}"

    if [[ "$input" == "$root/"* ]]; then
        input="${input#"$root/"}"
    fi

    printf '%s\n' "$input"
}

filter_existing() {
    local root
    root="$(git_root)"

    while IFS= read -r f; do
        [[ -n "$f" ]] || continue
        f="$(repo_relative_file "$f")"

        if [[ -f "$root/$f" ]]; then
            printf '%s\n' "$f"
        fi
    done
}

deduplicate_files() {
    printf '%s\n' "$@" | sed '/^$/d' | sort -u
}

regex_escape_lines() {
    sed -E 's/[][(){}.^$+*?|\\]/\\&/g'
}

build_stem_regex() {
    local stems=("$@")

    printf '%s\n' "${stems[@]}" |
        sed '/^$/d' |
        sort -u |
        regex_escape_lines |
        paste -sd'|' -
}

collect_file_stems() {
    local files=("$@")
    local f base stem

    for f in "${files[@]}"; do
        base="$(basename "$f")"
        stem="${base%.*}"

        [[ -n "$stem" ]] || continue
        [[ "$stem" != "$base" || "$base" != "." ]] || continue

        printf '%s\n' "$stem"
    done | sort -u
}

collect_related_tests() {
    local files=("$@")
    local root
    local stems=()
    local stem_regex

    root="$(git_root)"

    if [[ "$INCLUDE_TESTS" != "1" ]]; then
        return 0
    fi

    if ((${#files[@]} == 0)); then
        return 0
    fi

    if ! command -v fd >/dev/null 2>&1; then
        log_warn "fd not installed; skipping related test discovery"
        return 0
    fi

    mapfile -t stems < <(collect_file_stems "${files[@]}")
    ((${#stems[@]} > 0)) || return 0

    stem_regex="$(build_stem_regex "${stems[@]}")"
    [[ -n "$stem_regex" ]] || return 0

    {
        # Common direct naming conventions.
        fd --hidden -E vendor -E node_modules -E dist -E .git \
            "(${stem_regex})(Test)?\.php$" "$root" 2>/dev/null || true

        fd --hidden -E vendor -E node_modules -E dist -E .git \
            "(${stem_regex})\.(test|spec)\.(js|ts|jsx|tsx|mjs|cjs)$" "$root" 2>/dev/null || true

        fd --hidden -E vendor -E node_modules -E dist -E .git \
            "(${stem_regex})Test\.(kt|kts)$" "$root" 2>/dev/null || true

        # Conventional test folders where names may not match exactly.
        if command -v rg >/dev/null 2>&1; then
            rg -l --hidden \
                -g '!vendor' \
                -g '!node_modules' \
                -g '!dist' \
                -g '!.git' \
                -g 'tests/**' \
                -g 'test/**' \
                -g 'spec/**' \
                -g '__tests__/**' \
                -g '*.{php,js,ts,jsx,tsx,kt,kts}' \
                "(${stem_regex})" "$root" 2>/dev/null || true
        fi
    } | filter_existing | sort -u
}

estimate_files_tokens() {
    local root
    local total_bytes=0
    local f
    local bytes

    root="$(git_root)"

    for f in "$@"; do
        [[ -f "$root/$f" ]] || continue
        bytes="$(wc -c <"$root/$f" | tr -d ' ')"
        total_bytes=$((total_bytes + bytes))
    done

    echo $(((total_bytes + 3) / 4))
}

write_diff_artifact() {
    local label="$1"
    shift
    local mode="$1"
    shift || true

    [[ "$INCLUDE_DIFFS" == "1" ]] || return 0

    local root
    local diff_file
    root="$(git_root)"
    diff_file="${SESSION_DIR}/${label}.diff"

    mkdir -p "$SESSION_DIR"

    case "$mode" in
    since)
        local ref="${1:?ref required}"
        (
            cd "$root"
            git diff "$ref"...HEAD 2>/dev/null || git diff "$ref"
        ) >"$diff_file" || true
        ;;
    unstaged)
        (
            cd "$root"
            printf '# git diff\n\n'
            git diff || true
            printf '\n# git diff --cached\n\n'
            git diff --cached || true
            printf '\n# untracked files\n\n'
            git ls-files --others --exclude-standard | sed 's/^/UNTRACKED: /' || true
        ) >"$diff_file"
        ;;
    pr)
        local pr="${1:?PR number required}"
        require_bins gh
        gh pr diff "$pr" >"$diff_file" 2>/dev/null || true
        ;;
    recent)
        local count="${1:?count required}"
        (
            cd "$root"
            git diff "HEAD~${count}"..HEAD 2>/dev/null || git diff HEAD
        ) >"$diff_file" || true
        ;;
    touched)
        (
            cd "$root"
            git diff -- "$@" 2>/dev/null || true
        ) >"$diff_file" || true
        ;;
    *)
        die "unknown diff artifact mode: $mode"
        ;;
    esac

    if [[ -s "$diff_file" ]]; then
        repo_relative_file "$diff_file"
    else
        rm -f "$diff_file"
    fi
}

pack_files_list() {
    local label="$1"
    shift
    local files=("$@")
    local root
    local out_file
    local manifest
    local list_file
    local tokens
    local estimated_input_tokens
    local repomix_args=()

    root="$(git_root)"

    mapfile -t files < <(deduplicate_files "${files[@]}" | filter_existing)
    ((${#files[@]} > 0)) || die "no files to pack"

    mkdir -p "$OUTPUT_DIR"
    out_file="${OUTPUT_DIR}/${label}-$(date +%Y%m%d-%H%M%S).xml"
    manifest="${out_file%.xml}.manifest.json"

    estimated_input_tokens="$(estimate_files_tokens "${files[@]}")"

    if [[ "$DRY_RUN" == "1" ]]; then
        jq -n \
            --arg label "$label" \
            --arg output "$out_file" \
            --argjson files "$(printf '%s\n' "${files[@]}" | jq -R . | jq -s .)" \
            --argjson estimated_tokens "$estimated_input_tokens" \
            --argjson token_budget "$TOKEN_BUDGET" \
            '{
              dry_run: true,
              label: $label,
              output: $output,
              file_count: ($files | length),
              estimated_input_tokens: $estimated_tokens,
              token_budget: $token_budget,
              files: $files
            }'
        return 0
    fi

    list_file="$(mktemp)"
    printf '%s\n' "${files[@]}" >"$list_file"

    log_info "Packing ${#files[@]} files into context"
    log_info "Estimated input tokens before packing: ~${estimated_input_tokens}"

    if [[ "$SECRETS_SCAN" == "1" ]]; then
        section "Secrets scan"
        require_clean_secret_scan "$root"
        log_ok "No secrets found"
    else
        log_warn "Secrets scan disabled"
    fi

    if command -v repomix >/dev/null 2>&1; then
        repomix_args=(--stdin --output "$out_file" --style xml --compress)

        if [[ -n "$SPLIT_OUTPUT" ]]; then
            repomix_args+=(--split-output "$SPLIT_OUTPUT")
        fi

        (
            cd "$root"
            repomix "${repomix_args[@]}" <"$list_file"
        )
    elif command -v files-to-prompt >/dev/null 2>&1; then
        mapfile -t file_args <"$list_file"
        (
            cd "$root"
            files-to-prompt "${file_args[@]}"
        ) >"$out_file"
    else
        rm -f "$list_file"
        die "no context packer available; install repomix or files-to-prompt"
    fi

    rm -f "$list_file"

    tokens="$(estimate_tokens "$out_file")"

    if ! within_token_budget "$out_file" "$TOKEN_BUDGET"; then
        if [[ "$STRICT_TOKENS" == "1" ]]; then
            die "context is ~${tokens} tokens, exceeding strict budget ${TOKEN_BUDGET}"
        fi

        log_warn "Context is ~${tokens} tokens, exceeding budget ${TOKEN_BUDGET}"
    else
        log_ok "Context packed: ~${tokens} tokens"
    fi

    jq -n \
        --arg label "$label" \
        --arg out "$out_file" \
        --arg ts "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
        --argjson files "$(printf '%s\n' "${files[@]}" | jq -R . | jq -s .)" \
        --argjson tokens "$tokens" \
        --argjson estimated_input_tokens "$estimated_input_tokens" \
        --argjson token_budget "$TOKEN_BUDGET" \
        --argjson include_tests "$INCLUDE_TESTS" \
        --argjson include_diffs "$INCLUDE_DIFFS" \
        --argjson strict_tokens "$STRICT_TOKENS" \
        --arg split_output "$SPLIT_OUTPUT" \
        '{
          label: $label,
          output: $out,
          ts: $ts,
          file_count: ($files | length),
          estimated_tokens: $tokens,
          estimated_input_tokens: $estimated_input_tokens,
          token_budget: $token_budget,
          include_tests: ($include_tests == 1),
          include_diffs: ($include_diffs == 1),
          strict_tokens: ($strict_tokens == 1),
          split_output: (if $split_output == "" then null else $split_output end),
          files: $files
        }' >"$manifest"

    log_json "context.pack" "$(cat "$manifest")"
    printf '%s\n' "$out_file"
}

append_tests() {
    local -n files_ref=$1
    local tests=()

    if [[ "$INCLUDE_TESTS" == "1" ]]; then
        mapfile -t tests < <(collect_related_tests "${files_ref[@]+${files_ref[@]}}")
        files_ref+=("${tests[@]+${tests[@]}}")
    fi
}
