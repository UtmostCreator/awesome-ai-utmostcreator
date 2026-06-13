#!/usr/bin/env bash
# 70-backend-text.sh — content-search backends.
#
# Purpose: text (rg --json), docs/tests/config/deps (surface-scoped rg),
#   tracked (git grep), and changed-text/staged-text (rg over the changed or
#   staged file set via search_git_scoped_files). Each sets the global `out`,
#   consumed by the shared output assembly in 95-dispatch.sh.
# Allowed dependencies: rg, git grep; require_git_root (60-guards.sh),
#   surface_globs (25-modes.sh), fail() (40-output-json.sh). Reads case_args,
#   rg_fixed_args, ignore_args, rg_scope_args, query, root, mode.
#
# SC2034/SC2154: case/pattern/scope/ignore/query/root/mode are run-state
# globals; `out` is the shared backend output consumed by 95-dispatch.sh.
# shellcheck disable=SC2034,SC2154

# search_git_scoped_files SCOPE — content search restricted to the changed or
# staged file set, run from the repository root so reported paths are
# repo-relative. Sets the global `out`.
search_git_scoped_files() {
    local scope="$1" repo_root rc=0
    local files=()
    local cleaned=()
    local f

    repo_root="$(git -C "$root" rev-parse --show-toplevel 2>/dev/null)" ||
        fail "error" "not a git repository: $root"

    case "$scope" in
    changed)
        mapfile -d '' files < <(git -C "$repo_root" diff --name-only -z --)
        ;;
    staged)
        mapfile -d '' files < <(git -C "$repo_root" diff --name-only --cached -z --)
        ;;
    *)
        fail "error" "unknown scoped search: $scope"
        ;;
    esac

    for f in "${files[@]}"; do
        [[ -n "$f" ]] && cleaned+=("$f")
    done

    if [[ ${#cleaned[@]} -eq 0 ]]; then
        out=""
        return 0
    fi

    # -H forces path prefixes even when a single file is searched, so matches
    # stay in the canonical "path:line:text" shape.
    out="$(cd "$repo_root" && rg "${case_args[@]}" "${rg_fixed_args[@]}" -H -n -- "$query" "${cleaned[@]}" 2>/dev/null)" || rc=$?

    if [[ "$rc" -eq 2 ]]; then
        fail "error" "search backend error in $scope files: $query"
    fi

    return 0
}

backend_changed_text() {
    require_git_root
    search_git_scoped_files changed
}

backend_staged_text() {
    require_git_root
    search_git_scoped_files staged
}

backend_tracked() {
    require_git_root
    # git grep does not support rg's --smart-case/--pcre2 spellings, so map the
    # case/pattern modes to git-grep-compatible flags here.
    local git_grep_args=() rc=0
    case "$case_mode" in
    ignore) git_grep_args+=(-i) ;;
    sensitive) : ;;
    smart | *) [[ "$query" =~ [[:upper:]] ]] || git_grep_args+=(-i) ;;
    esac
    case "$pattern_mode" in
    fixed) git_grep_args+=(--fixed-strings) ;;
    pcre2) git_grep_args+=(-P) ;;
    *) : ;;
    esac
    out="$(git -C "$root" grep "${git_grep_args[@]}" -n -- "$query" 2>/dev/null)" || rc=$?
    # git grep: 0 = match, 1 = no match, >=2 = error.
    [[ "$rc" -ge 2 ]] && fail "error" "git grep error for query: $query"
    return 0
}

backend_text() {
    local rc=0
    out="$(rg --json "${case_args[@]}" "${rg_fixed_args[@]}" "${ignore_args[@]+"${ignore_args[@]}"}" "${rg_scope_args[@]}" -- "$query" "$root" 2>/dev/null)" || rc=$?
    # rg: 0 = match, 1 = no match, 2 = error.
    [[ "$rc" -eq 2 ]] && fail "error" "search backend error (invalid regex or unreadable path): $query"
    return 0
}

backend_surface() {
    # Surface-scoped text search: same engine as `text` but restricted to the
    # mode's file family via include globs (default excludes still apply).
    local surface_glob_args=() _sg rc=0
    while IFS= read -r _sg; do
        [[ -n "$_sg" ]] && surface_glob_args+=(--glob "$_sg")
    done < <(surface_globs "$mode")
    out="$(rg --json "${case_args[@]}" "${rg_fixed_args[@]}" "${ignore_args[@]+"${ignore_args[@]}"}" "${rg_scope_args[@]}" "${surface_glob_args[@]}" -- "$query" "$root" 2>/dev/null)" || rc=$?
    [[ "$rc" -eq 2 ]] && fail "error" "search backend error (invalid regex or unreadable path): $query"
    return 0
}
