#!/usr/bin/env bash
# Shared library for repository AI tooling scripts.

set -euo pipefail

AI_LOG_DIR="${AI_LOG_DIR:-${COPILOT_LOG_DIR:-.ai-logs}}"
COPILOT_LOG_DIR="${COPILOT_LOG_DIR:-$AI_LOG_DIR}"
AI_CONTEXT_DIR="${AI_CONTEXT_DIR:-${COPILOT_CONTEXT_DIR:-.repomix-context}}"
COPILOT_CONTEXT_DIR="${COPILOT_CONTEXT_DIR:-$AI_CONTEXT_DIR}"
AI_SESSION_DIR="${AI_SESSION_DIR:-${COPILOT_SESSION_DIR:-${AI_LOG_DIR}/sessions}}"
COPILOT_SESSION_DIR="${COPILOT_SESSION_DIR:-$AI_SESSION_DIR}"
AI_SNAPSHOT_DIR="${AI_SNAPSHOT_DIR:-${COPILOT_SNAPSHOT_DIR:-${AI_LOG_DIR}/snapshots}}"
COPILOT_SNAPSHOT_DIR="${COPILOT_SNAPSHOT_DIR:-$AI_SNAPSHOT_DIR}"
AI_EVENT_LOG="${AI_EVENT_LOG:-${COPILOT_EVENT_LOG:-${AI_LOG_DIR}/tool-usage.jsonl}}"
COPILOT_EVENT_LOG="${COPILOT_EVENT_LOG:-$AI_EVENT_LOG}"
AI_SESSION_GENERATED_DIR="${AI_SESSION_GENERATED_DIR:-docs/ai/generated/sessions}"


# Keep repo-local git reads working inside IDE sandboxes that cannot access the
# user's global include chain (for example ~/.gitconfig-work on macOS).
export GIT_CONFIG_GLOBAL="${GIT_CONFIG_GLOBAL:-/dev/null}"

if [[ -z "${NO_COLOR:-}" ]] && [[ -t 2 ]]; then
    _C_RESET=$'\033[0m'
    _C_RED=$'\033[0;31m'
    _C_YELLOW=$'\033[0;33m'
    _C_GREEN=$'\033[0;32m'
    _C_CYAN=$'\033[0;36m'
    _C_BOLD=$'\033[1m'
else
    _C_RESET=''
    _C_RED=''
    _C_YELLOW=''
    _C_GREEN=''
    _C_CYAN=''
    _C_BOLD=''
fi

agent_session_init() {
    local name="${1:-$(basename "$0" .sh)}"
    local session_base="${AI_SESSION_DIR:-$COPILOT_SESSION_DIR}"
    local log_dir="${AI_LOG_DIR:-$COPILOT_LOG_DIR}"
    local snapshot_dir="${AI_SNAPSHOT_DIR:-$COPILOT_SNAPSHOT_DIR}"
    SESSION_ID="${SESSION_ID:-${name}-$(date +%Y%m%d-%H%M%S)-$$}"
    TRACE_ID="${TRACE_ID:-trc-${SESSION_ID}}"
    TASK_ID="${TASK_ID:-tsk-${SESSION_ID}}"
    SESSION_DIR="${session_base}/${SESSION_ID}"
    SESSION_LOG="${SESSION_DIR}/session.jsonl"
    mkdir -p "$SESSION_DIR" "$log_dir" "$snapshot_dir"
    log_json "session.start" '{}' || true
}

append_log_entry() {
    local entry="${1:?entry required}"
    local repo_root
    local log_dir="${AI_LOG_DIR:-$COPILOT_LOG_DIR}"
    local event_log="${AI_EVENT_LOG:-$COPILOT_EVENT_LOG}"

    mkdir -p "$log_dir"
    printf '%s\n' "$entry" >>"$event_log"

    if [[ -n "${SESSION_LOG:-}" ]]; then
        printf '%s\n' "$entry" >>"$SESSION_LOG"
    fi

    if [[ "${AI_SESSION_DURABLE_LOG:-1}" == "1" && -n "${SESSION_ID:-}" ]]; then
        repo_root="$(git_root 2>/dev/null || pwd)"
        if command -v php >/dev/null 2>&1 && [[ -f "$repo_root/tools/ai/agent-log.php" ]]; then
            php "$repo_root/tools/ai/agent-log.php" --root "$repo_root" --session-id "$SESSION_ID" --event-json "$entry" >/dev/null 2>&1 || true
        fi
    fi
}

log_json() {
    local event="${1:-event}"
    local payload="${2:-{}}"
    local caller="${3:-$(basename "${BASH_SOURCE[1]:-unknown}" .sh)}"
    local payload_json
    local entry

    if ! payload_json="$(jq -c . <<<"$payload" 2>/dev/null)"; then
        payload_json="$(jq -cn --arg raw "$payload" '{raw:$raw}')"
    fi

    entry="$(jq -cn \
        --arg event_version "2.0" \
        --arg event_type "$event" \
        --arg trace_id "${TRACE_ID:-unknown}" \
        --arg session_id "${SESSION_ID:-unknown}" \
        --arg task_id "${TASK_ID:-unknown}" \
        --arg timestamp "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
        --arg actor_id "${ACTOR_ID:-$caller}" \
        --arg delegated_by "${DELEGATED_BY:-}" \
        --arg tool_name "$caller" \
        --arg repo_root "$(git_root 2>/dev/null || pwd)" \
        --arg git_branch "$(git rev-parse --abbrev-ref HEAD 2>/dev/null || printf 'unknown')" \
        --arg git_commit "$(git rev-parse HEAD 2>/dev/null || printf 'unknown')" \
        --argjson data "$payload_json" \
        '{
                    event_version: $event_version,
                    event_type: $event_type,
                    trace_id: $trace_id,
                    session_id: $session_id,
                    task_id: $task_id,
                    timestamp: $timestamp,
                    actor: {
                        type: "agent",
                        id: $actor_id,
                        delegated_by: (if $delegated_by == "" then null else $delegated_by end)
                    },
                    tool: {
                        name: $tool_name,
                        category: null,
                        args_hash: null,
                        mutates_state: false
                    },
                    authorization: {
                        policy_version: null,
                        decision: "unknown",
                        approval_required: null,
                        approved_by: null,
                        reason: null
                    },
                    execution: {
                        status: "unknown",
                        latency_ms: null,
                        retry_count: 0,
                        exit_code: null,
                        output_truncated: null
                    },
                    cost: {
                        model: null,
                        input_tokens: null,
                        output_tokens: null,
                        estimated_cost_usd: null
                    },
                    failure: {
                        category: null,
                        message: null,
                        resolution: null
                    },
                    repository: {
                        root: $repo_root,
                        git_branch: (if $git_branch == "" or $git_branch == "unknown" then null else $git_branch end),
                        git_commit: (if $git_commit == "" or $git_commit == "unknown" then null else $git_commit end)
                    },
                    output: {
                        preview: null
                    },
                    details: (if ($data | type) == "object" then $data else {raw: $data} end)
                }')"

    append_log_entry "$entry"
}

log_info() { printf '%b[INFO]%b  %s\n' "$_C_CYAN" "$_C_RESET" "$*" >&2; }
log_ok() { printf '%b[OK]%b    %s\n' "$_C_GREEN" "$_C_RESET" "$*" >&2; }
log_warn() { printf '%b[WARN]%b  %s\n' "$_C_YELLOW" "$_C_RESET" "$*" >&2; }
log_error() { printf '%b[ERROR]%b %s\n' "$_C_RED" "$_C_RESET" "$*" >&2; }

command_exists() {
    command -v "$1" >/dev/null 2>&1
}

require_bash_version() {
    local min="${1:-4}"
    ((BASH_VERSINFO[0] >= min)) || die "bash $min+ required"
}

json_available() {
    command_exists jq
}

find_timeout_bin() {
    if command_exists gtimeout; then
        printf 'gtimeout\n'
    elif command_exists timeout; then
        printf 'timeout\n'
    else
        printf '\n'
    fi
}

find_fd_bin() {
    if command_exists fd; then
        printf 'fd\n'
    elif command_exists fdfind; then
        printf 'fdfind\n'
    else
        printf '\n'
    fi
}

now_ms() {
    local s
    s="$(date +%s)"
    printf '%s000\n' "$s"
}

redact_sensitive_text() {
    local in
    in="$(cat)"
    in="$(printf '%s' "$in" | sed -E 's/([Tt]oken|[Pp]assword|[Aa]pi[_-]?[Kk]ey|[Ss]ecret)[[:space:]]*=[[:space:]]*[^[:space:]]+/\1=REDACTED/g')"
    in="$(printf '%s' "$in" | sed -E 's/(Authorization:[[:space:]]*Bearer)[[:space:]]+[^[:space:]]+/\1 REDACTED/g')"
    in="$(printf '%s' "$in" | sed -E 's/[A-Za-z0-9_\/+=-]{48,}/REDACTED_LONG_SECRET/g')"
    printf '%s' "$in"
}

redact_json_payload() {
    jq -c 'walk(if type == "object" then with_entries(if (.key|ascii_downcase|test("token|secret|password|api[_-]?key|authorization")) then .value = "REDACTED" else . end) else . end)' 2>/dev/null || jq -cn --arg raw "$(cat)" '{raw:$raw}'
}

json_compact_or_raw() {
    local payload="${1:-}"
    jq -c . <<<"$payload" 2>/dev/null || jq -cn --arg raw "$payload" '{raw:$raw}'
}

emit_envelope() {
    local status="${1:-ok}" tool="${2:-unknown}" content="${3:-{}}" warnings="${4:-[]}" errors="${5:-[]}" elapsed="${6:-0}" truncated="${7:-false}"
    local parsed_content parsed_warnings parsed_errors parsed_truncated
    parsed_content="$(json_compact_or_raw "$content")"
    parsed_warnings="$(jq -c . <<<"$warnings" 2>/dev/null || printf '[]')"
    parsed_errors="$(jq -c . <<<"$errors" 2>/dev/null || printf '[]')"
    parsed_truncated="$(jq -c . <<<"$truncated" 2>/dev/null || printf 'false')"
    jq -cn \
        --arg schema "1" \
        --arg status "$status" \
        --arg tool "$tool" \
        --arg content_raw "$parsed_content" \
        --arg warnings_raw "$parsed_warnings" \
        --arg errors_raw "$parsed_errors" \
        --arg elapsed_raw "$elapsed" \
        --arg truncated_raw "$parsed_truncated" \
        '{
        schema: ($schema|tonumber),
        status: $status,
        tool: $tool,
        content: (try ($content_raw|fromjson) catch {raw:$content_raw}),
        warnings: (try ($warnings_raw|fromjson) catch []),
        errors: (try ($errors_raw|fromjson) catch []),
        meta: {
          elapsed_ms: (try ($elapsed_raw|tonumber) catch 0),
          truncated: (try ($truncated_raw|fromjson) catch false)
        }
      }'
}

emit_blocked_envelope() {
    local reason="${1:-blocked}"
    local errors_json
    errors_json="$(jq -cn --arg reason "$reason" '[$reason]')"
    emit_envelope "unsafe_blocked" "unknown" '{}' '[]' "$errors_json" 0 false
}

rotate_log_if_needed_locked() {
    local file="${1:?file required}"
    local max="${AI_LOG_MAX_BYTES:-1048576}"
    [[ -f "$file" ]] || return 0
    local size
    size="$(wc -c <"$file" | tr -d ' ')"
    if ((size > max)); then
        mv "$file" "$file.$(date +%s).bak"
    fi
}

append_jsonl_safe() {
    local file="${1:?file required}" line="${2:?line required}"
    mkdir -p "$(dirname "$file")"
    printf '%s\n' "$line" >>"$file"
}

repo_root() {
    git_root
}

classify_command() {
    local tool="${1:-}" sub="${2:-}"
    case "$tool" in
    rg | fd | fdfind | cat | bat | sed | awk | jq | yq) echo read ;;
    rm | rmdir | mv | truncate | dd) echo destructive ;;
    curl | wget | ssh | scp | rsync) echo network ;;
    brew | apt | apt-get | winget | choco) echo install ;;
    npm)
        case "$sub" in
        install | add | update | remove | upgrade | require | global) echo install ;;
        test | run | exec | lint | validate | check) echo write ;;
        *) echo unknown ;;
        esac
        ;;
    git)
        case "$sub" in
        status | diff | show | log | grep | rev-parse | ls-files | branch) echo read ;;
        reset | clean | checkout | restore | push | pull | commit) echo destructive ;;
        *) echo unknown ;;
        esac
        ;;
    php | node | python | python3 | bash | sh | zsh | make | just) echo write ;;
    *) echo unknown ;;
    esac
}

approval_env_for_category() {
    case "${1:-}" in
    destructive) echo AI_APPROVE_DESTRUCTIVE ;;
    network) echo AI_APPROVE_NETWORK ;;
    install) echo AI_APPROVE_INSTALL ;;
    unknown) echo AI_APPROVE_UNKNOWN_COMMAND ;;
    *) echo "" ;;
    esac
}

command_basename() {
    [[ -n "${1:-}" ]] || {
        echo ""
        return 0
    }
    basename "$1"
}

realpath_safe() {
    local p="${1:?path required}"
    if command_exists realpath; then
        realpath "$p"
    else
        printf '%s/%s\n' "$(cd "$(dirname "$p")" && pwd)" "$(basename "$p")"
    fi
}

assert_inside_repo() {
    local p
    local root

    p="$(realpath_safe "${1:?path required}")"
    root="$(repo_root)"
    [[ "$p" == "$root" || "$p" == "$root"/* ]] || die "path outside repo: $p"
}

repo_relative_path() {
    local p
    local root

    p="$(realpath_safe "${1:?path required}")"
    root="$(repo_root)"
    if [[ "$p" == "$root" ]]; then
        echo "."
    else
        echo "${p#"$root"/}"
    fi
}

assert_relative_safe_path() {
    local p="${1:-}"
    [[ -n "$p" ]] || die "empty path"
    [[ "$p" != /* ]] || die "absolute path not allowed"
    [[ "$p" != *".."* ]] || die "path traversal not allowed"
    [[ "$p" != ".git"* ]] || die ".git path not allowed"
}

path_matches_protected_pattern() {
    local p="${1,,}"
    case "$p" in
    .env | .env.* | *.key | *.pem | *.crt | *.p12 | *.pfx | *secret* | agents.md | .github/* | docs/ai/generated/*) return 0 ;;
    *) return 1 ;;
    esac
}

estimate_tokens_string() {
    local s="${1-}"
    local n=${#s}
    echo $(((n + 3) / 4))
}

truncate_file_preview() {
    local file="${1:?file required}" max="${2:-4096}"
    if command_exists head; then
        head -c "$max" "$file"
    else
        cat "$file"
    fi
}

require_approval() {
    local action="${1:-action}" env_var="${2:-}"
    [[ -n "$env_var" ]] || return 0
    if [[ "${!env_var:-0}" != "1" ]]; then
        log_error "approval required for $action ($env_var=1)"
        exit 2
    fi
}

enforce_command_policy() {
    local action="${1:-cmd}"

    shift || true
    local tool="${1:-}"
    local sub="${2:-}"
    local category
    category="$(classify_command "$tool" "$sub")"
    case "$category" in
    read) return 0 ;;
    destructive | network | install | unknown)
        require_approval "$action" "$(approval_env_for_category "$category")"
        ;;
    write)
        [[ -n "${AI_TASK_SCOPE:-}" ]] || {
            log_error "AI_TASK_SCOPE required for write commands"
            exit 2
        }
        ;;
    esac
}

wait_for_capture_flag() {
    local f="${1:?flag required}"
    [[ -s "$f" ]] && return 0
    printf 'true' >"$f"
}

die() {
    log_error "$*"
    log_json "error" "$(jq -cn --arg msg "$*" '{msg:$msg}')" || true
    exit 1
}

section() {
    printf '\n%b==> %s%b\n' "$_C_BOLD" "$*" "$_C_RESET" >&2
}

require_bins() {
    local missing=()
    local bin
    for bin in "$@"; do
        command -v "$bin" >/dev/null 2>&1 || missing+=("$bin")
    done
    if ((${#missing[@]} > 0)); then
        die "required tools not found: ${missing[*]}"
    fi
}

require_clean_tree() {
    git rev-parse --is-inside-work-tree >/dev/null 2>&1 || die "not inside a git repository"
    if ! git diff --quiet || ! git diff --cached --quiet; then
        die "working tree is not clean; commit or stash changes first"
    fi
}

git_root() {
    git rev-parse --show-toplevel 2>/dev/null || pwd
}

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
            cpu="$(_guard_cpu_percent "$child_pid" "${AI_GUARD_CPU_SAMPLE:-1}" "$use_setsid" 2>/dev/null || true)"
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
        _guard_kill_tree "$child_pid" "$use_setsid" "$kill_after"
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
_guard_pid_jiffies() {
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
_guard_proc_jiffies() {
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
_guard_cpu_percent() {
    local _errexit_was_set=0 _rc=0 _out=""
    case "$-" in *e*) _errexit_was_set=1 ;; esac
    set +e
    _out="$(_guard_cpu_percent_impl "$@")"
    _rc=$?
    ((_errexit_was_set)) && set -e
    [[ -n "$_out" ]] && printf '%s\n' "$_out"
    return "$_rc"
}

_guard_cpu_percent_impl() {
    local pid="${1:?pid required}"
    local sample="${2:-1}"
    local group_mode="${3:-0}"

    if [[ -r "/proc/$pid/stat" ]]; then
        local j0 j1 hz
        if ((group_mode)); then
            j0="$(_guard_proc_jiffies "$pid")" || return 1
        else
            j0="$(_guard_pid_jiffies "$pid")" || return 1
        fi
        sleep "$sample"
        kill -0 "$pid" 2>/dev/null || { printf '0\n'; return 0; }
        if ((group_mode)); then
            j1="$(_guard_proc_jiffies "$pid")" || return 1
        else
            j1="$(_guard_pid_jiffies "$pid")" || return 1
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
_guard_kill_tree() {
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

require_clean_secret_scan() {
    local target="${1:-.}"

    if [[ "${SECRETS_SCAN:-1}" != "1" ]]; then
        log_warn "SECRETS_SCAN disabled"
        return 0
    fi

    if ! secrets_scan "$target"; then
        die "secrets detected; refusing to continue"
    fi
}

estimate_file_tokens_fallback() {
    local file="${1:?file required}"
    local bytes
    bytes="$(wc -c <"$file" | tr -d ' ')"
    echo $(((bytes + 3) / 4))
}

estimate_tokens() {
    local file="${1:?file required}"

    if [[ -n "${TOKEN_ESTIMATOR_CMD:-}" ]]; then
        local estimated=""

        estimated="$($TOKEN_ESTIMATOR_CMD "$file" 2>/dev/null || true)"

        if [[ "$estimated" =~ ^[0-9]+$ ]]; then
            printf '%s\n' "$estimated"
            return 0
        fi

        log_warn "TOKEN_ESTIMATOR_CMD failed or returned non-integer output; falling back to bytes/4"
    fi

    estimate_file_tokens_fallback "$file"
}

within_token_budget() {
    local file="${1:?file required}"
    local max="${2:-128000}"
    local tokens
    tokens="$(estimate_tokens "$file")"
    ((tokens <= max))
}

secrets_scan() {
    local target="${1:-.}"
    if command -v gitleaks >/dev/null 2>&1; then
        gitleaks detect --source "$target" --redact --no-banner --exit-code 1 >/dev/null 2>&1
    else
        log_warn "gitleaks not installed; skipping secrets scan"
        return 0
    fi
}

snapshot_create() {
    local label="${1:-snap}"
    local session="${SESSION_ID:-manual}"
    local timestamp
    local snap_base
    local patch_file
    local manifest_file
    local manifest_tmp
    local untracked_list
    local untracked_archive
    local base_ref
    local root
    local has_untracked_archive_json=false

    git rev-parse --is-inside-work-tree >/dev/null 2>&1 || die "not inside a git repository"

    root="$(git_root)"
    timestamp="$(date +%H%M%S)"
    base_ref="$(git -C "$root" rev-parse HEAD)"

    mkdir -p "$COPILOT_SNAPSHOT_DIR"

    snap_base="${COPILOT_SNAPSHOT_DIR}/${session}-${label}-${timestamp}"
    patch_file="${snap_base}.patch"
    manifest_file="${snap_base}.manifest.json"
    manifest_tmp="${manifest_file}.tmp"
    untracked_list="${snap_base}.untracked.txt"
    untracked_archive="${snap_base}.untracked.tar.gz"

    pushd "$root" >/dev/null

    git diff --binary HEAD >"$patch_file"
    git ls-files --others --exclude-standard >"$untracked_list"

    if [[ -s "$untracked_list" ]]; then
        if command -v tar >/dev/null 2>&1; then
            if tar -czf "$untracked_archive" -T "$untracked_list" 2>/dev/null; then
                has_untracked_archive_json=true
            else
                rm -f "$untracked_archive"
                log_warn "failed to archive untracked files for snapshot"
            fi
        else
            log_warn "tar not installed; untracked file contents will not be archived"
        fi
    fi

    popd >/dev/null

    jq -n \
        --arg version "2" \
        --arg session "$session" \
        --arg label "$label" \
        --arg base_ref "$base_ref" \
        --arg root "$root" \
        --arg patch_file "$patch_file" \
        --arg manifest_file "$manifest_file" \
        --arg untracked_list "$untracked_list" \
        --arg untracked_archive "$untracked_archive" \
        --arg ts "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
        --argjson has_untracked_archive "$has_untracked_archive_json" \
        '{
          version: ($version | tonumber),
          session: $session,
          label: $label,
          base_ref: $base_ref,
          root: $root,
          patch_file: $patch_file,
          manifest_file: $manifest_file,
          untracked_list: $untracked_list,
          untracked_archive: (if $has_untracked_archive then $untracked_archive else null end),
          has_untracked_archive: $has_untracked_archive,
          ts: $ts
        }' >"$manifest_tmp"

    mv "$manifest_tmp" "$manifest_file"

    log_json "snapshot.create" "$(cat "$manifest_file")" || true
    printf '%s\n' "$manifest_file"
}

_snapshot_manifest_value() {
    local manifest="${1:?manifest required}"
    local key="${2:?key required}"
    jq -r --arg key "$key" '.[$key] // empty' "$manifest"
}

_snapshot_path_from_manifest() {
    local manifest="${1:?manifest required}"
    local key="${2:?key required}"
    local value
    local dir

    value="$(_snapshot_manifest_value "$manifest" "$key")"
    [[ -n "$value" && "$value" != "null" ]] || return 1

    if [[ "$value" = /* || "$value" =~ ^[A-Za-z]: ]]; then
        printf '%s\n' "$value"
        return 0
    fi

    dir="$(dirname "$manifest")"
    printf '%s/%s\n' "$dir" "$value"
}

_snapshot_protected_untracked_path() {
    local path="${1:?path required}"

    case "$path" in
    .git | .git/*)
        return 0
        ;;
    "$COPILOT_LOG_DIR" | "$COPILOT_LOG_DIR"/*)
        return 0
        ;;
    "$COPILOT_CONTEXT_DIR" | "$COPILOT_CONTEXT_DIR"/*)
        return 0
        ;;
    .ai-logs | .ai-logs/*)
        return 0
        ;;
    .repomix-context | .repomix-context/*)
        return 0
        ;;
    esac

    return 1
}

_snapshot_untracked_existed() {
    local file="${1:?file required}"
    local list="${2:?list required}"

    [[ -f "$list" ]] || return 1
    grep -Fxq "$file" "$list"
}

snapshot_apply_manifest() {
    local manifest="${1:?manifest file required}"
    local root
    local base_ref
    local patch_file
    local untracked_list
    local untracked_archive
    local current_untracked=()
    local path

    [[ -f "$manifest" ]] || die "snapshot manifest not found: $manifest"

    require_bins jq git

    root="$(_snapshot_manifest_value "$manifest" root)"
    base_ref="$(_snapshot_manifest_value "$manifest" base_ref)"
    patch_file="$(_snapshot_path_from_manifest "$manifest" patch_file || true)"
    untracked_list="$(_snapshot_path_from_manifest "$manifest" untracked_list || true)"
    untracked_archive="$(_snapshot_path_from_manifest "$manifest" untracked_archive || true)"

    [[ -n "$root" && -d "$root" ]] || root="$(git_root)"
    [[ -n "$base_ref" ]] || die "snapshot manifest missing base_ref"

    (
        cd "$root"

        log_warn "Applying rollback snapshot. This will reset tracked files to snapshot state."

        mapfile -t current_untracked < <(git ls-files --others --exclude-standard)

        git reset --hard "$base_ref" >/dev/null

        if [[ -n "$patch_file" && -f "$patch_file" && -s "$patch_file" ]]; then
            git apply --whitespace=fix "$patch_file"
        fi

        if [[ "${ROLLBACK_REMOVE_CREATED_UNTRACKED:-1}" == "1" ]]; then
            for path in "${current_untracked[@]+${current_untracked[@]}}"; do
                [[ -n "$path" ]] || continue

                if _snapshot_protected_untracked_path "$path"; then
                    continue
                fi

                if ! _snapshot_untracked_existed "$path" "$untracked_list"; then
                    rm -f -- "$path"
                fi
            done
        else
            log_warn "ROLLBACK_REMOVE_CREATED_UNTRACKED=0; created untracked files were not removed"
        fi

        if [[ -n "$untracked_archive" && -f "$untracked_archive" ]]; then
            tar -xzf "$untracked_archive"
        fi
    )

    log_json "snapshot.apply" "$(cat "$manifest")" || true
}

snapshot_apply() {
    local snap_file="${1:?snapshot file required}"

    [[ -f "$snap_file" ]] || die "snapshot not found: $snap_file"

    case "$snap_file" in
    *.manifest.json)
        snapshot_apply_manifest "$snap_file"
        ;;
    *.ref)
        local ref
        ref="$(<"$snap_file")"
        git reset --hard "$ref"
        log_json "snapshot.apply.legacy_ref" "$(jq -cn --arg file "$snap_file" --arg ref "$ref" '{file:$file, ref:$ref}')" || true
        ;;
    *.patch)
        git apply --whitespace=fix "$snap_file"
        log_json "snapshot.apply.legacy_patch" "$(jq -cn --arg file "$snap_file" '{file:$file}')" || true
        ;;
    *)
        die "unsupported snapshot type: $snap_file"
        ;;
    esac
}
