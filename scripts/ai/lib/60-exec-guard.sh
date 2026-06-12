#!/usr/bin/env bash
# 60-exec-guard.sh — timeout and hang/freeze execution guards.
#
# Purpose: hard-timeout wrapper, idle/hung-process watchdog, process-group kill,
#   and CPU sampling helpers. Private helpers use the _ai_guard_ prefix.
# Allowed dependencies: 05-core.sh (log_warn), 30-logging.sh (log_json),
#   20-paths.sh (timeout discovery is inline here). No policy, approval prompts,
#   snapshots, or secret scanning.

[[ "${AI_LIB_EXEC_GUARD_LOADED:-0}" == "1" ]] && return 0
AI_LIB_EXEC_GUARD_LOADED=1

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

# run_guarded — run a command under a hang/freeze watchdog.
#
# Terminates the command's process group when ANY of these trip:
#   1. wall-clock     > AI_GUARD_TIMEOUT seconds              (hard ceiling)
#   2. hung/idle      = no new stdout/stderr output AND the child's CPU is
#                       ~idle, sustained for AI_GUARD_IDLE_SECS seconds
#
# The idle trigger requires BOTH no-output AND idle-CPU, so a silent-but-busy
# job (CPU-bound compile) or a CPU-light-but-streaming job (download) is not
# killed by mistake. CPU sampling degrades gracefully: if it is unavailable the
# guard keeps the wall-clock ceiling and logs which signals are active.
#
# Usage: run_guarded <label> <cmd> [args...]
# Tunables (env): AI_GUARD_TIMEOUT (default 180), AI_GUARD_IDLE_SECS (default 45),
#                 AI_GUARD_CPU_MIN (default 1, percent), AI_GUARD_POLL (default 2),
#                 AI_KILL_AFTER (default 5).
# Returns the command's exit code, or 124 if the watchdog killed it.
run_guarded() {
    local label="${1:?label required}"
    shift
    (($# > 0)) || {
        log_warn "run_guarded: no command given for '$label'"
        return 2
    }

    local wall="${AI_GUARD_TIMEOUT:-180}"
    local idle_secs="${AI_GUARD_IDLE_SECS:-45}"
    local cpu_min="${AI_GUARD_CPU_MIN:-1}"
    local poll="${AI_GUARD_POLL:-2}"
    local kill_after="${AI_KILL_AFTER:-5}"

    # The guard needs two writable scratch files to sample the child's output.
    # Honor TMPDIR (sandboxes such as VS Code set a restricted TMPDIR and make
    # the system /tmp non-writable). Try mktemp in each candidate dir; if none
    # is writable, degrade gracefully to the plain wall-clock wrapper rather
    # than failing every step by writing to a hardcoded unwritable /tmp path.
    local out_file="" err_file="" guard_tmp_dir=""
    local candidate
    for candidate in "${TMPDIR:-}" "$PWD/.ai-logs/tmp" /tmp; do
        [[ -n "$candidate" ]] || continue
        mkdir -p "$candidate" 2>/dev/null || continue
        [[ -w "$candidate" ]] || continue
        out_file="$(mktemp "$candidate/ai-guard-out.XXXXXX" 2>/dev/null)" || { out_file=""; continue; }
        err_file="$(mktemp "$candidate/ai-guard-err.XXXXXX" 2>/dev/null)" || {
            rm -f "$out_file" 2>/dev/null || true
            out_file=""
            continue
        }
        guard_tmp_dir="$candidate"
        break
    done

    if [[ -z "$out_file" || -z "$err_file" ]]; then
        log_warn "run_guarded: no writable temp dir (checked TMPDIR, ./.ai-logs/tmp, /tmp); falling back to plain timeout wrapper for '$label'."
        run_with_timeout "$wall" "$@"
        return $?
    fi
    : "$guard_tmp_dir"

    # Start the command in its own process group so we can kill the whole tree
    # without touching the parent shell. `setsid` is preferred; fall back to a
    # plain background job when it is unavailable.
    local child_pid use_setsid=0
    if command -v setsid >/dev/null 2>&1; then
        use_setsid=1
        setsid "$@" >"$out_file" 2>"$err_file" &
    else
        "$@" >"$out_file" 2>"$err_file" &
    fi
    child_pid=$!

    # Decide whether CPU sampling is available without blocking (no sleep here).
    local cpu_supported=0
    if [[ -r "/proc/$child_pid/stat" ]] || command -v ps >/dev/null 2>&1; then
        cpu_supported=1
    fi
    log_json "guard.start" "$(jq -cn \
        --arg label "$label" --arg pid "$child_pid" \
        --argjson wall "$wall" --argjson idle "$idle_secs" \
        --argjson cpu_min "$cpu_min" --argjson cpu_supported "$cpu_supported" \
        '{label:$label, pid:($pid|tonumber), wall:$wall, idle_secs:$idle, cpu_min:$cpu_min, cpu_sampling:($cpu_supported==1)}')" 2>/dev/null || true

    local start_ts last_change_ts now elapsed idle_for last_size cur_size
    start_ts="$(date +%s)"
    last_change_ts="$start_ts"
    last_size="-1"
    local reason="" killed=0
    # Number of consecutive idle-CPU samples required before declaring a hang.
    # A single low/zero reading can be a racy /proc snapshot or a sample that
    # straddled a context switch on a genuinely busy process, so we debounce:
    # any non-idle (or unsamplable) reading resets the streak.
    local idle_cpu_needed="${AI_GUARD_CPU_STREAK:-2}"
    local idle_cpu_streak=0

    while kill -0 "$child_pid" 2>/dev/null; do
        sleep "$poll"
        now="$(date +%s)"
        elapsed=$((now - start_ts))

        # 1. Wall-clock ceiling.
        if ((elapsed >= wall)); then
            reason="wall-clock ${elapsed}s >= ${wall}s"
            killed=1
            break
        fi

        # Output growth check (stdout + stderr bytes).
        cur_size="$(($(wc -c <"$out_file" 2>/dev/null || echo 0) + $(wc -c <"$err_file" 2>/dev/null || echo 0)))"
        if [[ "$cur_size" != "$last_size" ]]; then
            last_size="$cur_size"
            last_change_ts="$now"
            idle_cpu_streak=0
            continue
        fi
        idle_for=$((now - last_change_ts))
        ((idle_for >= idle_secs)) || continue

        # 2. No new output for the idle window. Require idle CPU too (when we can
        # sample it) before declaring a hang.
        if ((cpu_supported)); then
            local cpu
            cpu="$(_ai_guard_cpu_percent "$child_pid" "${AI_GUARD_CPU_SAMPLE:-1}" "$use_setsid" 2>/dev/null || true)"
            if [[ -z "$cpu" ]]; then
                # Could not sample CPU this round. Fail safe: assume the process
                # is busy/progressing and do not kill on the CPU criterion.
                idle_cpu_streak=0
                last_change_ts="$now"
            elif awk -v c="$cpu" -v m="$cpu_min" 'BEGIN{exit !(c+0 <= m+0)}'; then
                # Idle-CPU reading. Only kill once we have seen the required
                # number of consecutive idle samples (debounce against a single
                # racy or context-switch-straddling low reading).
                idle_cpu_streak=$((idle_cpu_streak + 1))
                if ((idle_cpu_streak >= idle_cpu_needed)); then
                    reason="idle ${idle_for}s with no output and CPU ${cpu}% <= ${cpu_min}% (x${idle_cpu_streak})"
                    killed=1
                    break
                fi
            else
                # CPU busy: treat as progressing, reset the idle clock.
                idle_cpu_streak=0
                last_change_ts="$now"
            fi
        else
            reason="idle ${idle_for}s with no output (CPU sampling unavailable)"
            killed=1
            break
        fi
    done

    local rc=0
    if ((killed)); then
        _ai_guard_kill_tree "$child_pid" "$use_setsid" "$kill_after"
        rc=124
    else
        wait "$child_pid"
        rc=$?
    fi

    local tail_out
    tail_out="$(tail -c 400 "$out_file" 2>/dev/null; tail -c 400 "$err_file" 2>/dev/null)"

    if ((killed)); then
        log_json "guard.killed" "$(jq -cn --arg label "$label" --arg reason "$reason" \
            --argjson elapsed "$((${now:-0} - start_ts))" --arg tail "$tail_out" \
            '{label:$label, reason:$reason, elapsed_s:$elapsed, last_output:$tail}')" 2>/dev/null || true
        log_warn "run_guarded killed '$label': $reason"
    else
        log_json "guard.done" "$(jq -cn --arg label "$label" --argjson rc "$rc" \
            '{label:$label, exit_code:$rc}')" 2>/dev/null || true
    fi

    cat "$out_file" 2>/dev/null || true
    cat "$err_file" >&2 2>/dev/null || true
    rm -f "$out_file" "$err_file" 2>/dev/null || true
    return "$rc"
}

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
