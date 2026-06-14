# shellcheck shell=bash
# shellcheck disable=SC2154,SC2034  # cross-module globals via dynamic scope
# ai-diff-context/40-commands.sh — per-subcommand implementations (cmd_*).
#
# Sourced by scripts/ai/ai-diff-context.sh (thin loader). Not an entrypoint.
# All cmd_* functions are read-only context builders. Behavior is byte-for-byte
# identical to the previous monolithic version.

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
