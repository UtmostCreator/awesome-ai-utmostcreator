#!/usr/bin/env bash
# 00-env.sh — environment defaults and color variables.
#
# Purpose: declare the canonical AI_* directory and log path defaults, export
#   GIT_CONFIG_GLOBAL, and define the _C_* color variables.
# Allowed dependencies: none. This module must only assign variables — no
#   mkdir, git, jq, logging, or snapshot logic, and no calls into other modules.
#
# Naming: AI_* is the universal, runtime-agnostic contract. The legacy COPILOT_*
#   variables are still ACCEPTED as input fallbacks (so a user who exported
#   COPILOT_LOG_DIR keeps working), but they are no longer re-exported and no
#   shipped script reads them. Prefer AI_* everywhere.

[[ "${AI_LIB_ENV_LOADED:-0}" == "1" ]] && return 0
AI_LIB_ENV_LOADED=1

AI_LOG_DIR="${AI_LOG_DIR:-${COPILOT_LOG_DIR:-.ai-logs}}"
AI_CONTEXT_DIR="${AI_CONTEXT_DIR:-${COPILOT_CONTEXT_DIR:-.repomix-context}}"
AI_SESSION_DIR="${AI_SESSION_DIR:-${COPILOT_SESSION_DIR:-${AI_LOG_DIR}/sessions}}"
AI_SNAPSHOT_DIR="${AI_SNAPSHOT_DIR:-${COPILOT_SNAPSHOT_DIR:-${AI_LOG_DIR}/snapshots}}"
AI_EVENT_LOG="${AI_EVENT_LOG:-${COPILOT_EVENT_LOG:-${AI_LOG_DIR}/tool-usage.jsonl}}"
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
