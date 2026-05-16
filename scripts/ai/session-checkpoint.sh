#!/usr/bin/env bash
# Create a repository-local checkpoint using the shared snapshot system.

set -euo pipefail

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
--help|-h)
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
