#!/usr/bin/env bash
# Project-aware verification gate for AI-driven changes (thin loader).
#
# The implementation lives in numbered, load-ordered modules under
# scripts/ai/internal/ai-verify/; this root file stays the public, registered
# entrypoint and its behavior is byte-for-byte identical to the previous
# monolithic version.

set -euo pipefail

# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

# Absolute directory of this root script, resolved BEFORE cd "$root" so it stays
# valid afterwards. Helper modules under internal/ai-verify/ are sourced from
# here, and resolve_branch_base uses it to locate the sibling git-branch-origin.sh
# (the monolith resolved that from this file's own location, so this targets the
# same file). Absolute resolution makes the path cwd-independent.
_ai_verify_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Parse positional args plus an optional `--language <lang>` flag. Both
# argument orders are supported (`--language php .` and `. --language php`)
# since this loop scans every argument instead of assuming a fixed position.
# `--language` selects the new per-language dispatch path
# (53-language-dispatch.sh, docs/tickets/arch-todo-safe-language-verify-scripts-20260706-003959
# §8-P2); when absent, the existing full-pipeline behavior (ai_verify_run) is
# unchanged.
root=""
AI_VERIFY_LANGUAGE=""
_ai_verify_args=("$@")
_ai_verify_idx=0
while ((_ai_verify_idx < ${#_ai_verify_args[@]})); do
    _ai_verify_arg="${_ai_verify_args[$_ai_verify_idx]}"
    case "$_ai_verify_arg" in
    --language)
        _ai_verify_idx=$((_ai_verify_idx + 1))
        AI_VERIFY_LANGUAGE="${_ai_verify_args[$_ai_verify_idx]:-}"
        ;;
    *)
        [[ -z "$root" ]] && root="$_ai_verify_arg"
        ;;
    esac
    _ai_verify_idx=$((_ai_verify_idx + 1))
done
root="${root:-.}"
unset _ai_verify_args _ai_verify_idx _ai_verify_arg

VERIFY_FULL="${VERIFY_FULL:-0}"
VERIFY_TIMEOUT="${VERIFY_TIMEOUT:-180}"
SHELLCHECK_ARGS="${SHELLCHECK_ARGS:--x -e SC1091}"
AI_VERIFY_SCOPE="${AI_VERIFY_SCOPE:-ai}"
VERIFY_SECRETS="${VERIFY_SECRETS:-${SECRETS_SCAN:-1}}"
# Base ref used by the "branch" scope to diff the current branch against its
# divergence point. Override when your trunk is not origin/main.
VERIFY_BASE_REF="${VERIFY_BASE_REF:-}"
# Optional author filter for the "branch" scope. When set (e.g. your git email),
# only files from commits authored by you (plus uncommitted work) are scoped.
VERIFY_AUTHOR="${VERIFY_AUTHOR:-}"
# Link checking is OFF by default. Set VERIFY_LINKS=1 to enable local-only link
# validation. Lychee always runs with --offline from this wrapper; shipped verify
# commands must never dial production URLs embedded in target-project docs.
VERIFY_LINKS="${VERIFY_LINKS:-0}"

# File line-count guardrail. Long files are a refactor signal, so this check
# tiers by size: info at LINECOUNT_INFO, warning at LINECOUNT_WARN, and a hard
# verification failure at LINECOUNT_ERROR (urgent refactor needed). Set
# VERIFY_LINECOUNT=0 to disable entirely. Scope follows AI_VERIFY_SCOPE: it only
# inspects changed/added/untracked files unless AI_VERIFY_SCOPE=all is requested.
VERIFY_LINECOUNT="${VERIFY_LINECOUNT:-1}"
LINECOUNT_INFO="${LINECOUNT_INFO:-350}"
LINECOUNT_WARN="${LINECOUNT_WARN:-550}"
LINECOUNT_ERROR="${LINECOUNT_ERROR:-800}"

# Optional jscpd duplication guardrail. Off by default like VERIFY_LINKS; set
# VERIFY_JSCPD=1 to enable. WARN at JSCPD_WARN_PCT; JSCPD_FAIL_PCT stays empty
# (advisory-only) unless a project explicitly opts into a hard-fail threshold.
VERIFY_JSCPD="${VERIFY_JSCPD:-0}"
JSCPD_MIN_TOKENS="${JSCPD_MIN_TOKENS:-50}"
JSCPD_WARN_PCT="${JSCPD_WARN_PCT:-5}"
JSCPD_FAIL_PCT="${JSCPD_FAIL_PCT:-}"
JSCPD_PATHS="${JSCPD_PATHS:-}"

# Todo-plan checklist status guardrail (docs/tickets/**/plan*.md `- [ ]`/`- [x]`
# items plus difficulty-notice language such as "impossible" or "not enough
# context"). On by default (no external tool required, unlike jscpd/links): set
# VERIFY_PLAN_STATUS=0 to disable. See
# scripts/ai/internal/ai-verify/36-plan-status.sh for the check itself.
VERIFY_PLAN_STATUS="${VERIFY_PLAN_STATUS:-1}"

failures=0

cd "$root"

# Load-ordered modules. Sourced AFTER common.sh so they may use its helpers, and
# AFTER cd "$root" so cwd-relative checks behave exactly as before. Order matters:
# shipped-filter predicates load before the scope helpers that call them.
# shellcheck source=scripts/ai/internal/ai-verify/20-shipped-filters.sh
source "$_ai_verify_dir/internal/ai-verify/20-shipped-filters.sh"
# shellcheck source=scripts/ai/internal/ai-verify/10-scope.sh
source "$_ai_verify_dir/internal/ai-verify/10-scope.sh"
# shellcheck source=scripts/ai/internal/ai-verify/30-linecount.sh
source "$_ai_verify_dir/internal/ai-verify/30-linecount.sh"
# shellcheck source=scripts/ai/internal/ai-verify/40-step-runner.sh
source "$_ai_verify_dir/internal/ai-verify/40-step-runner.sh"
# shellcheck source=scripts/ai/internal/ai-verify/50-tool-policy.sh
source "$_ai_verify_dir/internal/ai-verify/50-tool-policy.sh"
# shellcheck source=scripts/ai/internal/ai-verify/35-jscpd.sh
source "$_ai_verify_dir/internal/ai-verify/35-jscpd.sh"
# shellcheck source=scripts/ai/internal/ai-verify/36-plan-status.sh
source "$_ai_verify_dir/internal/ai-verify/36-plan-status.sh"
# shellcheck source=scripts/ai/internal/ai-verify/51-language-files.sh
source "$_ai_verify_dir/internal/ai-verify/51-language-files.sh"
# shellcheck source=scripts/ai/internal/ai-verify/54-reporting.sh
source "$_ai_verify_dir/internal/ai-verify/54-reporting.sh"
# shellcheck source=scripts/ai/internal/ai-verify/90-run.sh
source "$_ai_verify_dir/internal/ai-verify/90-run.sh"
# shellcheck source=scripts/ai/internal/ai-verify/53-language-dispatch.sh
source "$_ai_verify_dir/internal/ai-verify/53-language-dispatch.sh"

if [[ -n "$AI_VERIFY_LANGUAGE" ]]; then
    ai_verify_language "$AI_VERIFY_LANGUAGE"
else
    ai_verify_run
fi
