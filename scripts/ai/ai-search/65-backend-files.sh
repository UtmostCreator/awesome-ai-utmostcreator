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
    [[ -n "$fd_bin" ]] || fail "unavailable" "fd/fdfind not installed; files mode unavailable"
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
