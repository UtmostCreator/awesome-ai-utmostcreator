#!/usr/bin/env bash
set -euo pipefail

# Pre-tool-use policy gate (thin loader).
#
# Reads a JSON tool request from stdin and emits a permission decision when
# policy applies. The implementation lives in numbered, load-ordered modules
# under scripts/ai/internal/pre-tool-use/; this root file stays the public,
# registered entrypoint and its behavior is byte-for-byte identical to the
# previous monolithic version.

# Early --introspect guard: emit this script's machine-readable JSON contract
# (static parse via sh-introspect) and exit before running any logic (including
# stdin reads). The target script is parsed as text, never executed.
if [[ "${1:-}" == "--introspect" ]]; then
    _ai_introspect_here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    _ai_introspect_tool="$_ai_introspect_here/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_introspect_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        exec env AI_OUTPUT=json "${PHP_BIN:-php}" "$_ai_introspect_tool" "${BASH_SOURCE[0]}"
    fi
fi

POLICY_FILE="${AI_POLICY_FILE:-${COPILOT_POLICY_FILE:-policies/ai/policy.yaml}}"
MAINTENANCE_STATE_FILE="${AI_MAINTENANCE_STATE_FILE:-${COPILOT_MAINTENANCE_STATE_FILE:-.ai-logs/maintenance-mode.json}}"
# maintenance mode allows repository-delivered scripts only

_ai_pre_tool_use_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/internal/pre-tool-use"
# shellcheck source=scripts/ai/internal/pre-tool-use/10-helpers.sh
source "$_ai_pre_tool_use_dir/10-helpers.sh"
# shellcheck source=scripts/ai/internal/pre-tool-use/20-decide.sh
source "$_ai_pre_tool_use_dir/20-decide.sh"

case "${1:-}" in
--help | -h)
    usage
    exit 0
    ;;
esac

pre_tool_use_decide
