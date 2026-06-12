#!/usr/bin/env bash
# Pack changed or targeted files into AI context bundles.
#
# Name kept for compatibility.
# Behaviour: packs full changed-file context; optionally includes diffs.

set -euo pipefail
# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

TOKEN_BUDGET="${TOKEN_BUDGET:-80000}"
OUTPUT_DIR="${OUTPUT_DIR:-${AI_CONTEXT_DIR}/diff}"
INCLUDE_TESTS="${INCLUDE_TESTS:-1}"
SECRETS_SCAN="${SECRETS_SCAN:-1}"
INCLUDE_DIFFS="${INCLUDE_DIFFS:-0}"
DRY_RUN="${DRY_RUN:-0}"
STRICT_TOKENS="${STRICT_TOKENS:-0}"
SPLIT_OUTPUT="${SPLIT_OUTPUT:-}"
COMMON_OPTION_CONSUMED=0

usage() {
    cat <<'EOF'
Usage:
  ai-diff-context.sh since <ref> [options]
  ai-diff-context.sh unstaged [options]
  ai-diff-context.sh pr <number> [options]
  ai-diff-context.sh recent [--count N] [options]
  ai-diff-context.sh touched <pattern> [options]

Options:
  --include-diffs         Include git diff / PR diff as context artifact
  --no-tests              Do not include related tests
  --no-secrets-scan       Disable gitleaks scan
  --dry-run               Show selected files and estimated tokens only
  --strict                Fail when output exceeds token budget
  --token-budget N        Override TOKEN_BUDGET
  --split SIZE            Pass --split-output SIZE to repomix when available
  --help                  Show help

Environment:
  TOKEN_BUDGET=80000
  INCLUDE_TESTS=1
  SECRETS_SCAN=1
  INCLUDE_DIFFS=0
  DRY_RUN=0
  STRICT_TOKENS=0
  SPLIT_OUTPUT=
  TOKEN_ESTIMATOR_CMD=custom-token-counter
EOF
}

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

cmd_since() {
    local positional=()
    local shift_by=0
    local arg

    while (($# > 0)); do
        arg="$1"
        shift_by=0
        if parse_common_option "$arg" "${2:-}"; then
            shift_by="$COMMON_OPTION_CONSUMED"
            shift "$shift_by"
        else
            positional+=("$arg")
            shift
        fi
    done

    local ref="${positional[0]:-}"
    [[ -n "$ref" ]] || die "git ref required"

    section "Changed files since $ref"

    local files=()
    local diff_artifact=""
    mapfile -t files < <((git diff --name-only "$ref"...HEAD 2>/dev/null || git diff --name-only "$ref") | filter_existing)

    append_tests files

    diff_artifact="$(write_diff_artifact "since-${ref//\//-}" since "$ref" || true)"
    [[ -n "$diff_artifact" ]] && files+=("$diff_artifact")

    mapfile -t files < <(deduplicate_files "${files[@]+${files[@]}}" | filter_existing)
    pack_files_list "since-${ref//\//-}" "${files[@]}"
}

cmd_unstaged() {
    local shift_by=0

    while (($# > 0)); do
        shift_by=0
        if parse_common_option "$1" "${2:-}"; then
            shift_by="$COMMON_OPTION_CONSUMED"
            shift "$shift_by"
        else
            die "unknown option: $1"
        fi
    done

    section "Unstaged, staged, and untracked changed files"

    local files=()
    local diff_artifact=""
    mapfile -t files < <({
        git diff --name-only
        git diff --cached --name-only
        git ls-files --others --exclude-standard
    } | sort -u | filter_existing)

    append_tests files

    diff_artifact="$(write_diff_artifact "unstaged" unstaged || true)"
    [[ -n "$diff_artifact" ]] && files+=("$diff_artifact")

    mapfile -t files < <(deduplicate_files "${files[@]+${files[@]}}" | filter_existing)
    pack_files_list "unstaged" "${files[@]}"
}

cmd_pr() {
    local positional=()
    local shift_by=0
    local arg

    while (($# > 0)); do
        arg="$1"
        shift_by=0
        if parse_common_option "$arg" "${2:-}"; then
            shift_by="$COMMON_OPTION_CONSUMED"
            shift "$shift_by"
        else
            positional+=("$arg")
            shift
        fi
    done

    local pr="${positional[0]:-}"
    [[ -n "$pr" ]] || die "PR number required"

    require_bins gh
    section "Files in PR #$pr"

    local files=()
    local diff_artifact=""
    mapfile -t files < <(gh pr view "$pr" --json files --jq '.files[].path' | filter_existing)

    append_tests files

    diff_artifact="$(write_diff_artifact "pr-${pr}" pr "$pr" || true)"
    [[ -n "$diff_artifact" ]] && files+=("$diff_artifact")

    mapfile -t files < <(deduplicate_files "${files[@]+${files[@]}}" | filter_existing)
    pack_files_list "pr-${pr}" "${files[@]}"
}

cmd_recent() {
    local count=10
    local shift_by=0

    while (($# > 0)); do
        case "$1" in
        --count | -n)
            count="${2:?count required}"
            shift 2
            ;;
        --count=*)
            count="${1#*=}"
            shift
            ;;
        *)
            shift_by=0
            if parse_common_option "$1" "${2:-}"; then
                shift_by="$COMMON_OPTION_CONSUMED"
                shift "$shift_by"
            else
                die "unknown option: $1"
            fi
            ;;
        esac
    done

    section "Files changed in last $count commits"

    local files=()
    local diff_artifact=""
    mapfile -t files < <(git log --name-only --pretty=format: -"$count" | sort -u | grep -v '^$' | filter_existing)

    append_tests files

    diff_artifact="$(write_diff_artifact "recent-${count}" recent "$count" || true)"
    [[ -n "$diff_artifact" ]] && files+=("$diff_artifact")

    mapfile -t files < <(deduplicate_files "${files[@]+${files[@]}}" | filter_existing)
    pack_files_list "recent-${count}" "${files[@]}"
}

cmd_touched() {
    local positional=()
    local shift_by=0
    local arg

    while (($# > 0)); do
        arg="$1"
        shift_by=0
        if parse_common_option "$arg" "${2:-}"; then
            shift_by="$COMMON_OPTION_CONSUMED"
            shift "$shift_by"
        else
            positional+=("$arg")
            shift
        fi
    done

    local pattern="${positional[0]:-}"
    [[ -n "$pattern" ]] || die "pattern required"

    require_bins fd rg
    section "Files matching: $pattern"

    local root
    local files=()
    local diff_artifact=""

    root="$(git_root)"
    mapfile -t files < <({
        fd --hidden -E vendor -E node_modules -E dist -E .git "$pattern" "$root"
        rg -l --hidden -g '!vendor' -g '!node_modules' -g '!dist' -g '!.git' "$pattern" "$root" 2>/dev/null || true
    } | sort -u | filter_existing)

    append_tests files

    diff_artifact="$(write_diff_artifact "touched-${pattern//[^a-zA-Z0-9]/-}" touched "${files[@]}" || true)"
    [[ -n "$diff_artifact" ]] && files+=("$diff_artifact")

    mapfile -t files < <(deduplicate_files "${files[@]+${files[@]}}" | filter_existing)
    pack_files_list "touched-${pattern//[^a-zA-Z0-9]/-}" "${files[@]}"
}

agent_session_init "ai-diff-context"
require_bins jq

cmd="${1:-}"
[[ -n "$cmd" ]] || {
    usage
    exit 1
}
shift || true

case "$cmd" in
since) cmd_since "$@" ;;
unstaged) cmd_unstaged "$@" ;;
pr) cmd_pr "$@" ;;
recent) cmd_recent "$@" ;;
touched) cmd_touched "$@" ;;
--help | -h) usage ;;
*)
    usage
    die "unknown command: $cmd"
    ;;
esac
