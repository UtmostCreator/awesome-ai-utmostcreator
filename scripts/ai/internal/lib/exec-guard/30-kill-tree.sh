# shellcheck shell=bash
# exec-guard/30-kill-tree.sh — process-group termination.
#
# Sourced via scripts/ai/internal/lib/60-exec-guard.sh (loader). Not an
# entrypoint. Behavior is byte-for-byte identical to the previous monolithic
# 60-exec-guard.sh.

# Terminate a process group: SIGTERM, grace, then SIGKILL. When the child led
# its own session (setsid), signal the whole group via -PID.
_ai_guard_kill_tree() {
    local pid="${1:?pid required}"
    local use_setsid="${2:-0}"
    local grace="${3:-5}"
    local target="$pid"
    ((use_setsid)) && target="-$pid"

    kill -TERM "$target" 2>/dev/null || kill -TERM "$pid" 2>/dev/null || true
    local waited=0
    while kill -0 "$pid" 2>/dev/null && ((waited < grace)); do
        sleep 1
        waited=$((waited + 1))
    done
    if kill -0 "$pid" 2>/dev/null; then
        kill -KILL "$target" 2>/dev/null || kill -KILL "$pid" 2>/dev/null || true
    fi
}
