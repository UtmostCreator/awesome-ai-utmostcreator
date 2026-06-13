#!/usr/bin/env bash
# 55-scope-args.sh — case/pattern args, ignore-file resolution, scope filters.
#
# Purpose: translate case_mode/pattern_mode into rg flags (build_case_pattern_args),
#   resolve and apply the global gitignore (resolve_global_gitignore /
#   apply_global_gitignore), and assemble glob/type/exclude/max-depth scope
#   filters (build_rg_scope_args).
# Allowed dependencies: git for the global gitignore lookup. Reads/sets the
#   case_args, rg_fixed_args, ignore_args, rg_scope_args globals.
#
# SC2034/SC2154: case/pattern/scope/ignore globals are owned across modules
# (see ai-search.sh load order), not local to this file.
# shellcheck disable=SC2034,SC2154

# Directories excluded by default; callers can extend via --exclude.
DEFAULT_EXCLUDES=(vendor node_modules dist build coverage)

# build_case_pattern_args — map case_mode/pattern_mode to rg flag arrays.
build_case_pattern_args() {
    # Phase 3C case control. Default is smart-case (case-insensitive unless the
    # query contains uppercase), matching rg's native --smart-case.
    case_args=()
    case "$case_mode" in
    ignore) case_args=(--ignore-case) ;;
    sensitive) case_args=(--case-sensitive) ;;
    smart | *) case_args=(--smart-case) ;;
    esac

    # Phase 3C pattern control: literal (--fixed), regex (default/--regex), or PCRE2.
    rg_fixed_args=()
    case "$pattern_mode" in
    fixed) rg_fixed_args=(--fixed-strings) ;;
    pcre2) rg_fixed_args=(--pcre2) ;;
    *) : ;;
    esac
    return 0
}

# Global gitignore robustness. rg only auto-reads the global gitignore from
# git's GLOBAL/system config (or $XDG_CONFIG_HOME/git/ignore), not a repo-local
# core.excludesfile. To honor the global gitignore deterministically, resolve it
# and pass it via --ignore-file. Skipped when the user disabled global or all
# ignore sources.
resolve_global_gitignore() {
    local f
    f="$(git config --get core.excludesfile 2>/dev/null || true)"
    if [[ -z "$f" ]]; then
        f="${XDG_CONFIG_HOME:-$HOME/.config}/git/ignore"
    fi
    # Expand a leading literal ~ to $HOME. git stores core.excludesfile verbatim,
    # so a configured "~/path" arrives as a literal tilde that we must expand
    # ourselves. SC2088 warns about tilde-in-quotes, but matching the literal
    # prefix is exactly the intent here.
    # shellcheck disable=SC2088
    case "$f" in
    "~/"*) f="$HOME/${f#"~/"}" ;;
    esac
    if [[ -f "$f" ]]; then
        printf '%s' "$f"
    fi
    # Always succeed: an absent global gitignore is normal, not an error. The
    # trailing `[[ ]] && cmd` footgun under `set -e` would otherwise abort.
    return 0
}

# apply_global_gitignore — append --ignore-file for the global gitignore unless
# the caller disabled global/all ignore sources.
apply_global_gitignore() {
    local _ia ignore_disables_global=0
    for _ia in "${ignore_args[@]+"${ignore_args[@]}"}"; do
        [[ "$_ia" == "--no-ignore" || "$_ia" == "--no-ignore-global" ]] && ignore_disables_global=1
    done
    if [[ "$ignore_disables_global" -eq 0 ]]; then
        global_gitignore="$(resolve_global_gitignore)"
        if [[ -n "$global_gitignore" ]]; then
            ignore_args+=(--ignore-file "$global_gitignore")
        fi
    fi
    return 0
}

# build_rg_scope_args — assemble glob/type/exclude/max-depth filters for content
# searches. Emitted into a global array so multiple backends can reuse it.
build_rg_scope_args() {
    rg_scope_args=()
    local g t e d
    for g in "${glob_args[@]+"${glob_args[@]}"}"; do
        rg_scope_args+=(--glob "$g")
    done
    for t in "${type_args[@]+"${type_args[@]}"}"; do
        rg_scope_args+=(--type "$t")
    done
    for e in "${exclude_args[@]+"${exclude_args[@]}"}"; do
        rg_scope_args+=(--glob "!$e" --glob "!$e/**")
    done
    for d in "${DEFAULT_EXCLUDES[@]}"; do
        rg_scope_args+=(--glob "!$d" --glob "!$d/**")
    done
    if [[ -n "$max_depth" ]]; then
        rg_scope_args+=(--max-depth "$max_depth")
    fi
    return 0
}
