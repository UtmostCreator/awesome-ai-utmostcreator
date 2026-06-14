#!/usr/bin/env bash
# GENERATED DELEGATING SHIM — DO NOT EDIT.
# Role/risk alias for the canonical implementation at scripts/ai/ai-search-multi.sh.
# Provides scripts/ai/bin/read/ai-search-multi.sh addressability without relocating
# the implementation (which anchors common.sh, ../../tools, and self-introspect
# to the scripts/ai root). See scripts/ai/MANIFEST.md and
# docs/tickets/arch-todo-restructure-scripts-ai-p3-p5-20260613-220424/plan.md.
set -euo pipefail

# Resolve the scripts/ai root: this shim lives at scripts/ai/bin/read/ai-search-multi.sh,
# so the root is two directories up (read -> bin -> scripts/ai).
_ai_shim_dir="$(CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
_ai_root="$(CDPATH='' cd -- "$_ai_shim_dir/../.." && pwd)"
_ai_impl="$_ai_root/ai-search-multi.sh"

# Introspection/help must report the IMPLEMENTATION's contract, not this shim's.
# sh-introspect statically parses the target file (never executes it), and the
# root impl carries the real flags/modes, so target the root impl explicitly.
if [[ "${1:-}" == "--introspect" || "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    _ai_tool="$_ai_root/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        if [[ "${1:-}" == "--introspect" ]]; then
            exec env AI_OUTPUT=json "${PHP_BIN:-php}" "$_ai_tool" "$_ai_impl"
        fi
        exec "${PHP_BIN:-php}" "$_ai_tool" --format=help "$_ai_impl"
    fi
fi

# Delegate everything else verbatim to the canonical implementation.
exec bash "$_ai_impl" "$@"
