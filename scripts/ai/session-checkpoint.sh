#!/usr/bin/env bash
# Create a repository-local checkpoint using the shared snapshot system.

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

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/ai/common.sh
source "$SCRIPT_DIR/common.sh"

usage() {
    cat <<'EOF'
Usage:
  session-checkpoint.sh [label]

Creates a manifest-based snapshot in .ai-logs/snapshots/.

Examples:
  scripts/ai/session-checkpoint.sh
  scripts/ai/session-checkpoint.sh before-refactor
EOF
}

case "${1:-}" in
--help | -h)
    usage
    exit 0
    ;;
esac

label="${1:-checkpoint}"

agent_session_init "session-checkpoint"
require_bins jq git

snapshot="$(snapshot_create "$label")"

printf 'checkpoint created: %s\n' "$snapshot"

log_json "checkpoint.create" "$(jq -cn --arg snapshot "$snapshot" --arg label "$label" '{snapshot:$snapshot, label:$label}')"
