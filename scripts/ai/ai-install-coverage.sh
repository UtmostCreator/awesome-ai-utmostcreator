#!/usr/bin/env bash
set -euo pipefail

# Early --introspect guard: emit this script's machine-readable JSON contract
# (static parse via sh-introspect) and exit before running any logic. The
# target script is parsed as text, never executed.
if [[ "${1:-}" == "--introspect" ]]; then
    _ai_introspect_here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    _ai_introspect_tool="$_ai_introspect_here/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_introspect_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        exec env AI_OUTPUT=json "${PHP_BIN:-php}" "$_ai_introspect_tool" "${BASH_SOURCE[0]}"
    fi
fi

# Early --help/-h guard: emit this script's human-readable contract (the static
# introspector's compact --format=help view) and exit before running validation.
if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    _ai_help_here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    _ai_help_tool="$_ai_help_here/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_help_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        exec "${PHP_BIN:-php}" "$_ai_help_tool" --format=help "${BASH_SOURCE[0]}"
    fi
fi

php tools/ai/validate-install-surface.php --strict
