# shellcheck shell=bash
# exec-guard/10-run-timeout.sh — hard-timeout command wrapper.
#
# Sourced via scripts/ai/internal/lib/60-exec-guard.sh (loader). Not an
# entrypoint. Behavior is byte-for-byte identical to the previous monolithic
# 60-exec-guard.sh.

run_with_timeout() {
    local seconds="${1:?seconds required}"
    shift
    # Hard ceiling: SIGTERM at $seconds, then SIGKILL after a grace window so a
    # process that ignores SIGTERM cannot hang the workflow. Override the grace
    # with AI_KILL_AFTER (seconds).
    local kill_after="${AI_KILL_AFTER:-5}"
    local timeout_bin=""
    if command -v gtimeout >/dev/null 2>&1; then
        timeout_bin="gtimeout"
    elif command -v timeout >/dev/null 2>&1; then
        timeout_bin="timeout"
    fi

    if [[ -n "$timeout_bin" ]]; then
        "$timeout_bin" --kill-after="${kill_after}s" "$seconds" "$@"
    else
        if [[ "${AI_ALLOW_NO_TIMEOUT:-1}" == "0" ]]; then
            log_warn "no timeout binary (timeout/gtimeout) and AI_ALLOW_NO_TIMEOUT=0; refusing to run unbounded: $*"
            return 124
        fi
        log_warn "no timeout binary (timeout/gtimeout) found; running WITHOUT a hard time limit (set AI_ALLOW_NO_TIMEOUT=0 to fail closed, or install coreutils): $*"
        "$@"
    fi
}
