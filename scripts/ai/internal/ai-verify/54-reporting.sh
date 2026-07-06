# shellcheck shell=bash
# Per-tool verification report-file helpers for the AI verification gate.
#
# Part of docs/tickets/arch-todo-safe-language-verify-scripts-20260706-003959
# (§8-P1). This module is intended to be sourced by scripts/ai/ai-verify.sh
# (the thin root loader), the same way every other scripts/ai/internal/ai-verify/
# module is; it is NOT an entrypoint and must not be executed directly. AS OF
# THIS SLICE (P1) it is NOT YET sourced by scripts/ai/ai-verify.sh — that wiring
# is deferred to a later slice (P2/P3). Until then this file is only exercised
# directly, by sourcing it in tests (see tests/shell/ai-verify-reporting.bats).
#
# Dependencies: only scripts/ai/common.sh (for $AI_LOG_DIR, defaulted in
# scripts/ai/internal/lib/00-env.sh). No dependency on any other
# internal/ai-verify/*.sh module, so this file's eventual source position in
# ai-verify.sh's load order is unconstrained relative to the other numbered
# modules.
#
# FLAG-1 (plan.md §1b): this repo's canonical evidence root is $AI_LOG_DIR
# (default .ai-logs/). An externally reviewed suggestion for this feature
# proposed a competing, hardcoded top-level report directory instead (a
# dot-ai-prefixed directory with its own "verify" subfolder, distinct from
# $AI_LOG_DIR) -- that would introduce a second, un-gitignored evidence root and
# is explicitly rejected here. VERIFY_REPORT_DIR therefore defaults FROM
# $AI_LOG_DIR below, so a caller who only overrides AI_LOG_DIR still gets a
# consistent, single evidence root without needing a second override. No
# hardcoded evidence-directory literal other than the $AI_LOG_DIR-derived
# default may appear in this file (enforced by
# tests/shell/ai-verify-reporting.bats).
#
# FLAG-2 (plan.md §1b): structured *events* already have a home: log_json()
# in scripts/ai/internal/lib/30-logging.sh writes a rich, versioned JSONL
# envelope (trace/session/repo/git context) to ${AI_LOG_DIR}/tool-usage.jsonl,
# and is already used by this pipeline (see 90-run.sh, 35-jscpd.sh,
# 30-linecount.sh). This module deliberately does NOT add a
# `write_verify_event` function or a competing events.jsonl writer — that would
# be a near-total reimplementation of log_json (>=75% overlap; see AGENTS.md
# reuse rule). Callers that need a structured verify event should call
# log_json directly, e.g.:
#   log_json "verify.tool.ran" "$(jq -cn --arg tool "$name" '{tool:$tool}')"
# No thin wrapper is added around log_json here: the call above is already a
# single, clear line, so an extra indirection layer would not improve
# call-site clarity and would only add a second name for the same thing.
#
# The one genuinely new need this module fills is writing a tool's raw
# (non-JSONL) report output -- e.g. eslint's own --format json output, or
# phpstan's plain-text output -- to a per-tool file on disk.

# Resolve (and ensure) the directory verification report files are written
# under. Defaults from $AI_LOG_DIR (never a second, hardcoded evidence root) so
# reports always live beside this repo's other local evidence. Prints the
# resolved, created directory path.
verify_report_dir() {
    local dir="${VERIFY_REPORT_DIR:-${AI_LOG_DIR:-.ai-logs}/verify}"
    mkdir -p "$dir"
    printf '%s\n' "$dir"
}

# Write $content verbatim to <verify_report_dir>/<tool_name>.<extension>
# (e.g. eslint.json, phpstan.txt), creating the report directory as needed.
# This is a pure file write: it does not emit any JSONL event (see log_json
# above for that). Prints the path of the file written.
write_verify_report_file() {
    local tool_name="${1:?tool_name required}"
    local extension="${2:?extension required}"
    local content="${3:-}"
    local dir file

    dir="$(verify_report_dir)"
    file="$dir/$tool_name.$extension"
    printf '%s' "$content" >"$file"
    printf '%s\n' "$file"
}
