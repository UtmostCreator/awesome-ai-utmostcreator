#!/usr/bin/env bash
set -uo pipefail

# Pre-tool-use policy gate (thin loader).
#
# Reads a JSON tool request from stdin and emits a permission decision when
# policy applies. The implementation lives in numbered, load-ordered modules
# under scripts/ai/internal/pre-tool-use/; this root file stays the public,
# registered entrypoint and its behavior is byte-for-byte identical to the
# previous monolithic version.
#
# F-1 fail-safe (see docs/ai/validation.md "Permanent Maintenance Loop"): the
# actual decision logic (pre_tool_use_decide, which still runs under its own
# `set -e` — see below) is invoked through command substitution and its exit
# code is captured explicitly, rather than relying on an ERR trap. An ERR trap
# does not work here: `pre_tool_use_decide` is invoked inside the subshell that
# `$(...)` creates, so a trap firing there and calling `exit` only terminates
# that subshell — its stdout becomes the substitution's captured value and the
# main script keeps running with garbage input, which is worse than the
# original bug. Capturing output and exit status explicitly avoids that trap.
# On an unexpected internal error (for example jq failing on a well-formed but
# resource-heavy payload) the hook must never silently die with no decision —
# that is fail-closed (deny) from the caller's perspective with no diagnosis —
# so any output that is not valid decision JSON is treated as an internal
# error and routed to a dependency-free, best-effort fallback classifier:
# allow only when read-only is confirmed, deny (with a remediation message)
# otherwise.

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

# Last-resort fallback for the one gap the F-1 subshell fix does not cover on
# its own: if a module below fails to `source` (missing/unreadable file,
# syntax error), `pre_tool_use_error_fallback` itself would not be defined
# yet, and the script would otherwise die with "command not found" (exit 127)
# and no decision JSON — the exact original F-1 symptom, via a different
# trigger. This function is deliberately self-contained: no dependency on
# anything sourced below.
_ai_pre_tool_use_emergency_fallback() {
    printf '{"permissionDecision":"deny","permissionDecisionReason":"internal hook error: failed to load scripts/ai/internal/pre-tool-use/%s; denying by default. Check the file exists and is readable."}\n' "$1"
    exit 1
}

_ai_pre_tool_use_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/internal/pre-tool-use"
# shellcheck source=scripts/ai/internal/pre-tool-use/10-helpers.sh
source "$_ai_pre_tool_use_dir/10-helpers.sh" || _ai_pre_tool_use_emergency_fallback "10-helpers.sh"
# shellcheck source=scripts/ai/internal/pre-tool-use/20-decide.sh
source "$_ai_pre_tool_use_dir/20-decide.sh" || _ai_pre_tool_use_emergency_fallback "20-decide.sh"

case "${1:-}" in
--help | -h)
    usage
    exit 0
    ;;
esac

_ai_pre_tool_use_raw_input="$(cat)"

# Run the actual decision logic in an explicit subshell with its own `-e`, so
# an internal crash there stops immediately (instead of continuing with
# corrupted state) but only terminates that subshell. Capture its stdout and
# exit code here in the un-subshelled main process, where `exit` reliably
# terminates the real hook process.
_ai_pre_tool_use_decision_output="$(set -eo pipefail; pre_tool_use_decide "$_ai_pre_tool_use_raw_input")"
_ai_pre_tool_use_decision_exit=$?

if printf '%s' "$_ai_pre_tool_use_decision_output" | grep -q '"permissionDecision"'; then
    # Normal deny/ask/allow decision.
    printf '%s\n' "$_ai_pre_tool_use_decision_output"
    exit "$_ai_pre_tool_use_decision_exit"
fi

if [[ -z "$_ai_pre_tool_use_decision_output" && "$_ai_pre_tool_use_decision_exit" -eq 0 ]]; then
    # Legitimate silent pass-through (non-terminal, non-edit tool): no policy
    # opinion, no output, exit 0 — unchanged from the previous behavior.
    exit 0
fi

# pre_tool_use_decide crashed internally before emitting any decision JSON.
pre_tool_use_error_fallback "$_ai_pre_tool_use_decision_exit" "$_ai_pre_tool_use_raw_input"
