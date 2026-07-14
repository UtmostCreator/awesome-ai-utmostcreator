#!/usr/bin/env bash
# Thin convenience wrapper for `ai-verify.sh --language ts`. Never runs tool
# logic itself; everything (including --introspect/--help) delegates to the
# canonical implementation, which carries the real contract. Pattern mirrors
# the existing delegating shim at scripts/ai/bin/verify/ai-verify.sh. See
# docs/tickets/arch-todo-safe-language-verify-scripts-20260706-003959/plan.md.
set -euo pipefail

_ai_wrap_dir="$(CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
_ai_impl="$_ai_wrap_dir/ai-verify.sh"

if [[ "${1:-}" == "--introspect" || "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    _ai_tool="$_ai_wrap_dir/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        if [[ "${1:-}" == "--introspect" ]]; then
            exec env AI_OUTPUT=json "${PHP_BIN:-php}" "$_ai_tool" "$_ai_impl"
        fi
        exec "${PHP_BIN:-php}" "$_ai_tool" --format=help "$_ai_impl"
    fi
fi

exec bash "$_ai_impl" --language ts "$@"
