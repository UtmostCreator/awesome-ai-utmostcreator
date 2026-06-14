#!/usr/bin/env bash
# Project-aware verification gate for AI-driven changes (thin loader).
#
# The implementation lives in numbered, load-ordered modules under
# scripts/ai/internal/ai-verify/; this root file stays the public, registered
# entrypoint and its behavior is byte-for-byte identical to the previous
# monolithic version.

set -euo pipefail

# Early --introspect / --help guard: when invoked with --introspect or --help/-h
# as the FIRST argument, emit this script's machine-readable JSON contract or its
# human-readable contract (static parse via sh-introspect) and exit before running
# any logic. The target script is parsed as text, never executed.
if [[ "${1:-}" == "--introspect" || "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    _ai_self_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    _ai_self_tool="$_ai_self_dir/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_self_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        if [[ "${1:-}" == "--introspect" ]]; then
            exec env AI_OUTPUT=json "${PHP_BIN:-php}" "$_ai_self_tool" "${BASH_SOURCE[0]}"
        fi
        exec "${PHP_BIN:-php}" "$_ai_self_tool" --format=help "${BASH_SOURCE[0]}"
    fi
fi

# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

# Absolute directory of this root script, resolved BEFORE cd "$root" so it stays
# valid afterwards. Helper modules under internal/ai-verify/ are sourced from
# here, and resolve_branch_base uses it to locate the sibling git-branch-origin.sh
# (the monolith resolved that from this file's own location, so this targets the
# same file). Absolute resolution makes the path cwd-independent.
_ai_verify_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

root="${1:-.}"

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
# shellcheck source=scripts/ai/internal/ai-verify/90-run.sh
source "$_ai_verify_dir/internal/ai-verify/90-run.sh"

ai_verify_run
