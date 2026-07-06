# shellcheck shell=bash
# Tool-availability policy layer for the AI verification gate.
#
# This module is sourced by scripts/ai/ai-verify.sh (the thin root loader);
# it is NOT an entrypoint and must not be executed directly. It is intended to
# be sourced AFTER 40-step-runner.sh (reuses its `has_package_dependency` /
# `has_package_script` jq-based package.json guards) and BEFORE 90-run.sh.
#
# As of this slice, the root loader (scripts/ai/ai-verify.sh) does NOT yet
# source this file — that wiring is a later, separately-approved slice. This
# module is written to be standalone-sourceable (source scripts/ai/common.sh,
# then this file) so it is directly unit-testable today without depending on
# the root loader or any other internal module. It defines no new PATH-based
# `has_node_dependency`/`has_node_script` guards: `has_package_dependency` and
# `has_package_script` already exist in 40-step-runner.sh and are reused as-is
# per the repo's `>=75%` reuse rule.
#
# Purpose: centralize the "is a tool safely runnable here" decision that is
# currently scattered as inline `command -v` / `[[ -x vendor/bin/... ]]` /
# `has_package_dependency` checks throughout 90-run.sh, so a future
# per-language dispatcher (a later slice) can ask one function instead of
# re-deriving the same guard per tool. Nothing here mutates state, installs
# anything, or runs a tool from an unapproved source (no PATH fallback for
# framework-specific tools, no `npx --yes`, no `composer global`).

# True (exit 0) for the small, fixed set of tools that are safe to invoke from
# PATH regardless of project type: they are standalone binaries, not tied to a
# specific package.json/composer.json dependency tree, and every existing
# caller already guards them with a plain `command -v` check (90-run.sh:89-130,
# 324-349; 35-jscpd.sh; ai-verify.sh VERIFY_LINKS). This is a fixed allowlist,
# not a general "is this on PATH" predicate, so it must never be extended to
# cover a framework-local tool such as `eslint` or `phpstan`.
is_standalone_safe_tool() {
    local tool="${1:?tool name required}"
    case "$tool" in
    shellcheck | shfmt | actionlint | gitleaks | trivy | semgrep | osv-scanner | lychee)
        return 0
        ;;
    *)
        return 1
        ;;
    esac
}

# True when a composer-managed binary is present and executable under
# vendor/bin/. Mirrors the existing inline `[[ -x vendor/bin/pint ]]`-style
# guards in 90-run.sh; never falls back to a PATH-installed copy of the same
# tool, since a global install would not reflect this project's pinned
# composer.json/composer.lock version.
has_composer_bin() {
    local bin="${1:?binary name required}"
    [[ -x "vendor/bin/$bin" ]]
}

# Single dispatcher answering "can $tool run safely right now, using only
# what this project already has installed?". Callers should prefer this over
# re-deriving the guard inline once a caller opts into it; existing inline
# guards in 90-run.sh are left as-is in this slice (no behavior change there
# beyond the three targeted fixes) and may be routed through this dispatcher
# in a later slice.
can_run_tool() {
    local tool="${1:?tool name required}"
    case "$tool" in
    pint | phpstan | psalm | phpunit | pest | rector | phpmd | deptrac)
        has_composer_bin "$tool"
        ;;
    eslint)
        has_package_dependency eslint
        ;;
    biome)
        has_package_dependency '@biomejs/biome' ||
            has_package_dependency biome ||
            [[ -f biome.json || -f biome.jsonc ]]
        ;;
    vue-tsc)
        has_package_dependency vue-tsc
        ;;
    nuxt | nuxi)
        has_package_dependency nuxt || has_package_dependency nuxi
        ;;
    knip)
        has_package_dependency knip
        ;;
    *)
        is_standalone_safe_tool "$tool" && command -v "$tool" >/dev/null 2>&1
        ;;
    esac
}
