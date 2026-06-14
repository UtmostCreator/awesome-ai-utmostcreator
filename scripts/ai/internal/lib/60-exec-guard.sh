#!/usr/bin/env bash
# 60-exec-guard.sh — timeout and hang/freeze execution guards (thin loader).
#
# Purpose: hard-timeout wrapper, idle/hung-process watchdog, process-group kill,
#   and CPU sampling helpers. Private helpers use the _ai_guard_ prefix.
# Allowed dependencies: 05-core.sh (log_warn), 30-logging.sh (log_json),
#   20-paths.sh (timeout discovery is inline here). No policy, approval prompts,
#   snapshots, or secret scanning.
#
# The implementation lives in load-ordered modules under exec-guard/; this file
# stays the sourced facade (common.sh sources it by path) and preserves the
# one-time load guard. Behavior is byte-for-byte identical to the previous
# monolithic version.

[[ "${AI_LIB_EXEC_GUARD_LOADED:-0}" == "1" ]] && return 0
AI_LIB_EXEC_GUARD_LOADED=1

_ai_exec_guard_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/exec-guard"
# shellcheck source=scripts/ai/internal/lib/exec-guard/10-run-timeout.sh
source "$_ai_exec_guard_dir/10-run-timeout.sh"
# shellcheck source=scripts/ai/internal/lib/exec-guard/20-cpu-sampling.sh
source "$_ai_exec_guard_dir/20-cpu-sampling.sh"
# shellcheck source=scripts/ai/internal/lib/exec-guard/30-kill-tree.sh
source "$_ai_exec_guard_dir/30-kill-tree.sh"
# shellcheck source=scripts/ai/internal/lib/exec-guard/40-run-guarded.sh
source "$_ai_exec_guard_dir/40-run-guarded.sh"
unset _ai_exec_guard_dir
