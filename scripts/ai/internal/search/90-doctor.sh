#!/usr/bin/env bash
# 90-doctor.sh — doctor mode.
#
# Purpose: run_doctor_mode reports tool availability (jq/git/rg/ast-grep/fd),
#   the root, and git_available as a diagnostics{} envelope. Takes no query or
#   root; emits + exits directly.
# Allowed dependencies: command_exists()/find_fd_bin() (common.sh), jq, git.
#
# SC2154: json_mode is a run-state global set by 20-state.sh.
# shellcheck disable=SC2154

# run_doctor_mode — diagnostics for the doctor mode. Plain mode prints a short
# status line; JSON mode emits the diagnostics{} envelope.
run_doctor_mode() {
    if [[ "$json_mode" != "json" ]]; then
        echo "ai-search doctor: ok"
        exit 0
    fi

    local available=() missing=() warnings=()
    local tool fd_bin root_dir git_available

    for tool in jq git rg ast-grep; do
        if command_exists "$tool"; then
            available+=("$tool")
        else
            missing+=("$tool")
        fi
    done

    fd_bin="$(find_fd_bin)"
    if [[ -n "$fd_bin" ]]; then
        available+=("$fd_bin")
    else
        warnings+=("fd/fdfind not found; files mode degraded")
    fi

    root_dir="."
    git_available=false
    if git -C "$root_dir" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
        git_available=true
    fi

    jq -cn \
        --arg schema "1" \
        --arg tool "ai-search" \
        --argjson available "$(printf '%s\n' "${available[@]}" | jq -R -s 'split("\n")|map(select(length>0))')" \
        --argjson missing "$(printf '%s\n' "${missing[@]:-}" | jq -R -s 'split("\n")|map(select(length>0))')" \
        --argjson warnings "$(printf '%s\n' "${warnings[@]:-}" | jq -R -s 'split("\n")|map(select(length>0))')" \
        --arg root "$root_dir" \
        --argjson git_available "$git_available" \
        '{
            schema: $schema,
            status: "ok",
            tool: $tool,
            mode: "doctor",
            diagnostics: {
                available: $available,
                missing: $missing,
                warnings: $warnings,
                root: $root,
                git_available: $git_available
            }
        }'

    exit 0
}
