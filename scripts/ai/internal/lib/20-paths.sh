#!/usr/bin/env bash
# 20-paths.sh — path, repo, and tool-discovery helpers.
#
# Purpose: pure path/repo validation, repo-relative conversion, protected-path
#   classification, and timeout/fd binary discovery.
# Allowed dependencies: 05-core.sh (command_exists, die). No rm, git reset, tar,
#   or snapshot apply logic.

[[ "${AI_LIB_PATHS_LOADED:-0}" == "1" ]] && return 0
AI_LIB_PATHS_LOADED=1

find_timeout_bin() {
    if command_exists gtimeout; then
        printf 'gtimeout\n'
    elif command_exists timeout; then
        printf 'timeout\n'
    else
        printf '\n'
    fi
}

find_fd_bin() {
    if command_exists fd; then
        printf 'fd\n'
    elif command_exists fdfind; then
        printf 'fdfind\n'
    else
        printf '\n'
    fi
}

git_root() {
    git rev-parse --show-toplevel 2>/dev/null || pwd
}

repo_root() {
    git_root
}

realpath_safe() {
    local p="${1:?path required}"
    if command_exists realpath; then
        realpath "$p"
    else
        printf '%s/%s\n' "$(cd "$(dirname "$p")" && pwd)" "$(basename "$p")"
    fi
}

assert_inside_repo() {
    local p
    local root

    p="$(realpath_safe "${1:?path required}")"
    root="$(repo_root)"
    [[ "$p" == "$root" || "$p" == "$root"/* ]] || die "path outside repo: $p"
}

repo_relative_path() {
    local p
    local root

    p="$(realpath_safe "${1:?path required}")"
    root="$(repo_root)"
    if [[ "$p" == "$root" ]]; then
        echo "."
    else
        echo "${p#"$root"/}"
    fi
}

assert_relative_safe_path() {
    local p="${1:-}"
    [[ -n "$p" ]] || die "empty path"
    [[ "$p" != /* ]] || die "absolute path not allowed"
    [[ "$p" != *".."* ]] || die "path traversal not allowed"
    [[ "$p" != ".git"* ]] || die ".git path not allowed"
}

path_matches_protected_pattern() {
    local p="${1,,}"
    case "$p" in
    .env | .env.* | *.key | *.pem | *.crt | *.p12 | *.pfx | *secret* | agents.md | .github/* | docs/ai/generated/*) return 0 ;;
    *) return 1 ;;
    esac
}

require_clean_tree() {
    git rev-parse --is-inside-work-tree >/dev/null 2>&1 || die "not inside a git repository"
    if ! git diff --quiet || ! git diff --cached --quiet; then
        die "working tree is not clean; commit or stash changes first"
    fi
}
