#!/usr/bin/env bash
# 50-policy.sh — command classification and approval policy.
#
# Purpose: command classification, approval-env lookup, and policy enforcement.
# Allowed dependencies: 05-core.sh (log_error). No command execution, rm, git
#   reset, network calls, or snapshot logic.

[[ "${AI_LIB_POLICY_LOADED:-0}" == "1" ]] && return 0
AI_LIB_POLICY_LOADED=1

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
