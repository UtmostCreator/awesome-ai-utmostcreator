# shellcheck shell=bash
# exec-guard/20-cpu-sampling.sh — per-process / process-group CPU sampling.
#
# Sourced via scripts/ai/internal/lib/60-exec-guard.sh (loader). Not an
# entrypoint. Private helpers use the _ai_guard_ prefix. Behavior is
# byte-for-byte identical to the previous monolithic 60-exec-guard.sh.

# utime+stime jiffies for a single pid, parsed from /proc/<pid>/stat. The comm
# field (field 2) is wrapped in parentheses and may contain spaces, so we strip
# everything through the final ") " before splitting. After that strip, the
# state field is index 0, making utime index 11 and stime index 12.
_ai_guard_pid_jiffies() {
    local pid="$1"
    [[ -r "/proc/$pid/stat" ]] || return 1
    local raw rest
    raw="$(cat "/proc/$pid/stat" 2>/dev/null)" || return 1
    rest="${raw##*) }"
    local -a a
    # read returns nonzero on the no-trailing-newline herestring; tolerate it
    # so callers under `set -e` are not aborted.
    read -r -a a <<<"$rest" || true
    printf '%s\n' "$(( ${a[11]:-0} + ${a[12]:-0} ))"
}

# Sum used CPU jiffies (utime+stime) for an entire process group, from /proc.
# When the guarded command is started with setsid it leads its own group, so the
# group id equals the child pid and this captures every descendant (including
# transient forked grandchildren that a direct-children walk would miss).
# Prints the total or returns nonzero when /proc is unavailable.
_ai_guard_proc_jiffies() {
    local pgid="${1:?pgid required}"
    [[ -d /proc ]] || return 1
    local total=0 d pid raw rest matched=0
    local -a a

    # Seed with the group leader's own jiffies so a racy /proc scan (processes
    # appearing/vanishing mid-walk) can never make this return empty while the
    # leader is still alive. The leader (pid == pgid) is then skipped in the loop
    # to avoid double counting.
    if raw="$(cat "/proc/$pgid/stat" 2>/dev/null)"; then
        rest="${raw##*) }"
        read -r -a a <<<"$rest" || true
        total=$((${a[11]:-0} + ${a[12]:-0}))
        matched=1
    fi

    for d in /proc/[0-9]*; do
        pid="${d#/proc/}"
        [[ "$pid" == "$pgid" ]] && continue
        raw="$(cat "/proc/$pid/stat" 2>/dev/null)" || continue
        rest="${raw##*) }"
        read -r -a a <<<"$rest" || true
        # After stripping comm, indices: 0=state ... 2=pgrp ... 11=utime 12=stime
        [[ "${a[2]:-}" == "$pgid" ]] || continue
        matched=1
        total=$((total + ${a[11]:-0} + ${a[12]:-0}))
    done

    [[ "$matched" == "1" ]] || return 1
    printf '%s\n' "$total"
}

# Print a process's instantaneous CPU percent over a short sampling window.
# Prefers a /proc jiffies delta (accurate, instantaneous, includes children);
# falls back to `ps -o %cpu` (lifetime average) when /proc is absent (macOS/BSD).
# Returns nonzero if the platform cannot be sampled at all.
# Args: <pid> [sample_seconds] [group_mode]
# group_mode=1 sums the whole process group (pid == pgid, i.e. started under
# setsid) so transient forked grandchildren are counted; otherwise just the pid.
# Public wrapper: runs the implementation with errexit disabled and restores the
# caller's errexit afterwards, so the flag never leaks into callers under set -e.
_ai_guard_cpu_percent() {
    local _errexit_was_set=0 _rc=0 _out=""
    case "$-" in *e*) _errexit_was_set=1 ;; esac
    set +e
    _out="$(_ai_guard_cpu_percent_impl "$@")"
    _rc=$?
    ((_errexit_was_set)) && set -e
    [[ -n "$_out" ]] && printf '%s\n' "$_out"
    return "$_rc"
}

_ai_guard_cpu_percent_impl() {
    local pid="${1:?pid required}"
    local sample="${2:-1}"
    local group_mode="${3:-0}"

    if [[ -r "/proc/$pid/stat" ]]; then
        local j0 j1 hz
        if ((group_mode)); then
            j0="$(_ai_guard_proc_jiffies "$pid")" || return 1
        else
            j0="$(_ai_guard_pid_jiffies "$pid")" || return 1
        fi
        sleep "$sample"
        kill -0 "$pid" 2>/dev/null || { printf '0\n'; return 0; }
        if ((group_mode)); then
            j1="$(_ai_guard_proc_jiffies "$pid")" || return 1
        else
            j1="$(_ai_guard_pid_jiffies "$pid")" || return 1
        fi
        hz="$(getconf CLK_TCK 2>/dev/null || echo 100)"
        # percent = (delta jiffies / hz) / sample_seconds * 100
        awk -v d="$((j1 - j0))" -v hz="$hz" -v s="$sample" \
            'BEGIN{ if (s<=0) s=1; printf "%.1f\n", (d/hz)/s*100 }'
        return 0
    fi

    if command -v ps >/dev/null 2>&1; then
        local v
        v="$(ps -o %cpu= -p "$pid" 2>/dev/null | tr -d ' ')"
        [[ -n "$v" ]] || return 1
        printf '%s\n' "$v"
        return 0
    fi
    return 1
}
