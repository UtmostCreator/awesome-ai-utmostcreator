#!/usr/bin/env bash
# 05-core.sh — primitive core helpers and generic utilities.
#
# Purpose: low-level logging primitives, command/version probes, and small
#   generic utilities used everywhere.
# Allowed dependencies: 00-env.sh (color vars). die() calls log_json (30-logging)
#   at run time, which is resolved lazily by bash — no source-time cross-calls.

[[ "${AI_LIB_CORE_LOADED:-0}" == "1" ]] && return 0
AI_LIB_CORE_LOADED=1

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

now_ms() {
    local s
    s="$(date +%s)"
    printf '%s000\n' "$s"
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

wait_for_capture_flag() {
    local f="${1:?flag required}"
    [[ -s "$f" ]] && return 0
    printf 'true' >"$f"
}

ai_load_config_list() {
    local -n _ai_list_ref=$1
    local _ai_list_file="$2"
    shift 2

    _ai_list_ref=()
    if [[ -f "$_ai_list_file" ]]; then
        local _ai_line
        while IFS= read -r _ai_line || [[ -n "$_ai_line" ]]; do
            _ai_line="${_ai_line%%#*}"
            _ai_line="${_ai_line#${_ai_line%%[![:space:]]*}}"
            _ai_line="${_ai_line%${_ai_line##*[![:space:]]}}"
            [[ -n "$_ai_line" ]] || continue
            _ai_list_ref+=("$_ai_line")
        done <"$_ai_list_file"
    fi

    if ((${#_ai_list_ref[@]} == 0)); then
        _ai_list_ref=("$@")
    fi
}
