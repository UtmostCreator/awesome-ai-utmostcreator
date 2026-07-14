#!/usr/bin/env bash
# 60-guards.sh — tool and git-root guards.
#
# Purpose: classify which modes need rg/git (mode_needs_rg / mode_needs_git),
#   enforce those tools as hard errors (check_tool_guards), and verify a git
#   root for git-backed modes (require_git_root).
# Allowed dependencies: command_exists() (common.sh), fail() (40-output-json.sh).
#   Reads mode and root.
#
# A missing core backend must be a hard `error`, not a silent `no_matches` (the
# backend commands suppress stderr, so a missing tool would otherwise collapse
# to rc!=2 and look like an empty result set).
#
# SC2154: mode/root are run-state globals set by sibling modules.
# shellcheck disable=SC2154

mode_needs_rg() {
    case "$1" in
    text | docs | tests | config | deps | changed-text | staged-text | \
        todo | unsafe-patterns) return 0 ;;
    *) return 1 ;;
    esac
}

mode_needs_git() {
    case "$1" in
    changed-files | staged-files | changed-text | staged-text | tracked | \
        diff | history) return 0 ;;
    *) return 1 ;;
    esac
}

# mode_has_rg_fallback — modes that can degrade to git grep when rg is absent.
# Only plain `text` qualifies: git grep yields the same path:line:text shape the
# dispatch already parses. Surface modes (docs/tests/config/deps) rely on rg glob
# scoping with no safe git-grep equivalent, so they keep the hard guard.
mode_has_rg_fallback() {
    [[ "$1" == "text" ]]
}

# check_tool_guards — fail early when a required core tool is missing.
check_tool_guards() {
    if mode_needs_rg "$mode" && ! command_exists rg; then
        # Allow `text` to degrade to git grep when rg is missing but git is
        # present (the backend emits a parity warning). Everything else, and the
        # case where git is also missing, stays a hard error.
        if mode_has_rg_fallback "$mode" && command_exists git; then
            :
        else
            fail "error" "required tool 'rg' (ripgrep) not found on PATH; mode '$mode' unavailable"
        fi
    fi

    if mode_needs_git "$mode" && ! command_exists git; then
        fail "error" "required tool 'git' not found on PATH; mode '$mode' unavailable"
    fi
    return 0
}

require_git_root() {
    # git -C requires a directory; a file path yields a misleading
    # "Not a directory" / "not a git repository" message. Catch it first so the
    # caller sees the real cause and the fix (scope a single file with --glob).
    if [[ -e "$root" && ! -d "$root" ]]; then
        fail "error" "root must be a directory (got file): $root; scope a single file with --glob"
    fi
    git -C "$root" rev-parse --is-inside-work-tree >/dev/null 2>&1 ||
        fail "error" "not a git repository: $root"
}
