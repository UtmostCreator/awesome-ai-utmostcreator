#!/usr/bin/env bash
# 65-backend-files.sh — file-list and filename backends.
#
# Purpose: changed-files / staged-files (git name-only listings) and files
#   (fd filename search). Each sets the global `out` to newline-delimited paths,
#   consumed by the shared output assembly in 95-dispatch.sh.
# Allowed dependencies: git, fd (find_fd_bin from common.sh), require_git_root
#   (60-guards.sh), fail() (40-output-json.sh). Reads root and ignore_args.
#
# SC2034/SC2154: root/query/ignore_args are run-state globals; `out` is the
# shared backend output consumed by 95-dispatch.sh.
# shellcheck disable=SC2034,SC2154

backend_changed_files() {
    require_git_root
    out="$(git -C "$root" diff --name-only 2>/dev/null | tr -d '\r' || true)"
}

backend_staged_files() {
    require_git_root
    out="$(git -C "$root" diff --name-only --cached 2>/dev/null | tr -d '\r' || true)"
}

backend_files() {
    local fd_bin fd_ignore_args _ia
    fd_bin="$(find_fd_bin)"
    if [[ -z "$fd_bin" ]]; then
        backend_files_fallback
        return
    fi
    # Translate the rg-style ignore flags to fd-compatible ones. fd shares
    # --no-ignore/--no-ignore-vcs/--no-ignore-parent; it has no separate
    # --no-ignore-global/--no-ignore-dot, so those map up to --no-ignore.
    fd_ignore_args=()
    for _ia in "${ignore_args[@]+"${ignore_args[@]}"}"; do
        case "$_ia" in
        --no-ignore | --no-ignore-vcs | --no-ignore-parent) fd_ignore_args+=("$_ia") ;;
        --no-ignore-global | --no-ignore-dot) fd_ignore_args+=(--no-ignore) ;;
        esac
    done
    out="$("$fd_bin" --hidden "${fd_ignore_args[@]+"${fd_ignore_args[@]}"}" --exclude .git -- "$query" "$root" 2>/dev/null || true)"
}

# backend_files_fallback — degrade `files` mode when fd/fdfind is absent.
#
# Preference: `git ls-files` (tracked files only) inside a git repo, otherwise
# POSIX `find`. The match is a case-insensitive substring on the file path, which
# is NOT identical to fd's smart-case regex/glob — so a parity warning is always
# emitted and the result is intentionally conservative.
backend_files_fallback() {
    local query_lc results=""
    # Empty query means "list everything" (mirrors fd with no pattern).
    query_lc="$(printf '%s' "$query" | tr '[:upper:]' '[:lower:]')"

    if git -C "$root" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
        add_warning "fd/fdfind not installed; files mode degraded to 'git ls-files' (tracked files only; substring match, not fd regex/glob)"
        results="$(git -C "$root" ls-files 2>/dev/null | tr -d '\r' || true)"
    elif command_exists find; then
        add_warning "fd/fdfind not installed; files mode degraded to POSIX 'find' (substring match, not fd regex/glob)"
        results="$(find "$root" -type f -not -path '*/.git/*' 2>/dev/null | sed "s#^${root%/}/##" || true)"
    else
        fail "unavailable" "fd/fdfind not installed and no git/find fallback available; files mode unavailable"
    fi

    if [[ -n "$query_lc" ]]; then
        out="$(printf '%s\n' "$results" | awk -v q="$query_lc" 'tolower($0) ~ q' || true)"
    else
        out="$results"
    fi
}
