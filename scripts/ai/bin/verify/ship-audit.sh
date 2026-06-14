#!/usr/bin/env bash
# GENERATED DELEGATING SHIM — DO NOT EDIT.
# Role/risk alias for the canonical implementation at scripts/ai/ship-audit.sh.
set -euo pipefail

_ai_shim_dir="$(CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
_ai_root="$(CDPATH='' cd -- "$_ai_shim_dir/../.." && pwd)"
_ai_impl="$_ai_root/ship-audit.sh"

if [[ "${1:-}" == "--introspect" || "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    _ai_tool="$_ai_root/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        if [[ "${1:-}" == "--introspect" ]]; then
            exec env AI_OUTPUT=json "${PHP_BIN:-php}" "$_ai_tool" "$_ai_impl"
        fi
        exec "${PHP_BIN:-php}" "$_ai_tool" --format=help "$_ai_impl"
    fi
fi

exec bash "$_ai_impl" "$@"
