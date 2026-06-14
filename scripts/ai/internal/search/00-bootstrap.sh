#!/usr/bin/env bash
# 00-bootstrap.sh — early guards and shared-library bootstrap.
#
# Purpose: handle the `--help`/`-h` and `--introspect` fast paths BEFORE any
#   search runtime is loaded, then source common.sh. These guards mirror the
#   pre-split ai-search.sh head: they never execute search logic, never require
#   a git repo or rg/fd/ast-grep, and exec/exit before normal dispatch.
# Allowed dependencies: common.sh (sourced here). Must be the FIRST module the
#   entrypoint sources.
#
# Note: the `--help` guard runs BEFORE sourcing common.sh on purpose, so the
#   human-readable help renders even when common.sh's own guards would differ.
#
# Introspection target: sh-introspect.php inlines statically-resolvable sourced
#   modules (see tools/ai/sh-introspect/22-source-inline.php), so introspecting
#   the thin entrypoint aggregates the full contract from scripts/ai/internal/search/.

# Early --help|-h delegate: render the JSON-driven help BEFORE sourcing any
# runtime dependencies or dispatching search logic. This mirrors the
# --introspect guard below but emits the human-readable --format=help view.
# It never executes search, never sources common.sh, and does not require a git
# repo or rg/fd/ast-grep. If PHP or the introspector is unavailable it prints a
# minimal fallback rather than crashing.
if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    _here="$(CDPATH= cd -- "$(dirname -- "${AI_SEARCH_ENTRYPOINT:-${BASH_SOURCE[0]}}")" && pwd)"
    _introspector="$_here/../../tools/ai/sh-introspect.php"
    _php_bin="${PHP_BIN:-php}"
    if command -v "$_php_bin" >/dev/null 2>&1 && [[ -f "$_introspector" ]]; then
        exec "$_php_bin" "$_introspector" --format=help "$_here/ai-search.sh"
    fi
    # Minimal fallback (no PHP / no introspector available).
    echo "ai-search.sh — unified repository search entrypoint"
    echo "Usage: ai-search.sh MODE [QUERY] [root] [flags]"
    echo "Run with --introspect for the machine-readable JSON contract."
    exit 0
fi

# Self-introspection: machine-readable contract for this script.
# Never executes search logic; delegates to the static introspector and
# replaces this process so no normal mode dispatch runs. The introspector
# inlines the sourced modules, so targeting the entrypoint yields the full
# aggregated contract.
#
# This runs BEFORE sourcing common.sh on purpose: common.sh carries its own
# universal --introspect guard that would otherwise introspect THIS bootstrap
# module (the sourcer) and report an empty contract.
if [[ "${1:-}" == "--introspect" ]]; then
    _here="$(cd "$(dirname "${AI_SEARCH_ENTRYPOINT:-${BASH_SOURCE[0]}}")" && pwd)"
    exec env AI_OUTPUT=json "${PHP_BIN:-php}" \
        "$_here/../../tools/ai/sh-introspect.php" "$_here/ai-search.sh"
fi

# shellcheck disable=SC1091
source "$(dirname "${AI_SEARCH_ENTRYPOINT:-${BASH_SOURCE[0]}}")/common.sh"
