#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"

# `--introspect` reports THIS wrapper's own machine-readable contract (static
# parse), matching the universal --introspect convention; the wrapper is parsed
# as text, never executed.
if [[ "${1:-}" == "--introspect" ]]; then
    _ai_introspect_tool="$ROOT/tools/ai/sh-introspect.php"
    if [[ -f "$_ai_introspect_tool" ]] && command -v "$PHP_BIN" >/dev/null 2>&1; then
        exec env AI_OUTPUT=json "$PHP_BIN" "$_ai_introspect_tool" "${BASH_SOURCE[0]}"
    fi
fi

# `--help`/`-h` reports THIS wrapper's human-readable contract (the static
# introspector's compact --format=help view) and exits before delegating.
if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    _ai_help_tool="$ROOT/tools/ai/sh-introspect.php"
    if [[ -f "$_ai_help_tool" ]] && command -v "$PHP_BIN" >/dev/null 2>&1; then
        exec "$PHP_BIN" "$_ai_help_tool" --format=help "${BASH_SOURCE[0]}"
    fi
fi

exec "$PHP_BIN" "$ROOT/tools/ai/repo-tool-inventory.php" "$@"
