# shellcheck shell=bash
# exec-guard/40-run-guarded.sh — hang/freeze watchdog around a command.
#
# Sourced via scripts/ai/internal/lib/60-exec-guard.sh (loader), AFTER the
# timeout wrapper, CPU sampling, and kill-tree helpers it depends on. Not an
# entrypoint. Behavior is byte-for-byte identical to the previous monolithic
# 60-exec-guard.sh.

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
        out_file="$(mktemp "$candidate/ai-guard-out.XXXXXX" 2>/dev/null)" || {
            out_file=""
            continue
        }
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
    tail_out="$(
        tail -c 400 "$out_file" 2>/dev/null
        tail -c 400 "$err_file" 2>/dev/null
    )"

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
