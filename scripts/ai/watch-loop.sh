#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

command_to_run="${1:?command required}"
extensions="${2:-md,json,sh,lua,php,yml,yaml}"
debounce_ms="${WATCH_DEBOUNCE_MS:-500}"

mkdir -p "$AI_LOG_DIR"
watch_log="$AI_LOG_DIR/watch-loop.jsonl"

log_watch_event() {
    local event="$1"
    jq -cn --arg ts "$(date -u +%Y-%m-%dT%H:%M:%SZ)" --arg event "$event" --arg command "$command_to_run" --arg extensions "$extensions" --arg debounce "$debounce_ms" '{ts:$ts, event:$event, command:$command, extensions:$extensions, debounceMs:($debounce|tonumber)}' >>"$watch_log"
}

if command -v watchexec >/dev/null 2>&1; then
    log_watch_event "watch.start.watchexec"
    watchexec --debounce "$debounce_ms" -e "$extensions" -- bash -lc "$command_to_run"
    exit 0
fi

if command -v entr >/dev/null 2>&1; then
    log_watch_event "watch.start.entr"
    rg --files \
        -g '!vendor' \
        -g '!node_modules' \
        -g '!dist' \
        -g '!.git' |
        entr -r bash -lc "$command_to_run"
    exit 0
fi

echo "No file watcher found. Install watchexec (preferred) or entr." >&2
exit 1
