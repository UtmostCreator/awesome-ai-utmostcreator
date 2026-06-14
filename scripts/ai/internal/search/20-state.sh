#!/usr/bin/env bash
# 20-state.sh — global defaults and per-run state.
#
# Purpose: declare the canonical defaults (DEFAULT_MAX_RESULTS, json_mode,
#   warning accumulator) and init_run_state(), which sets every mutable global
#   the parser and backends read. Keeping these as plain (non-local) globals is
#   intentional: downstream functions assign and read them across boundaries.
# Allowed dependencies: none. Pure variable setup.
#
# SC2034: these globals are read by sibling modules (output, parser, backends,
# dispatch), not within this file, so shellcheck cannot see their use when this
# module is linted standalone. They are intentionally cross-module state.
# shellcheck disable=SC2034

DEFAULT_MAX_RESULTS=100

json_mode="${AI_OUTPUT:-}"

# Non-fatal advisories accumulated during a run.
g_warnings=()

add_warning() {
    g_warnings+=("$1")
}

# init_run_state MODE — initialise all per-run globals from the resolved mode.
# Mirrors the pre-split "Mode dispatch setup" block. These stay global so the
# parser, scope builder, guards, and backends can share them.
init_run_state() {
    mode="$1"
    g_mode="$mode"
    g_query=""
    g_max_results="$DEFAULT_MAX_RESULTS"
    g_truncated=false
    g_results_json="[]"
    absolute=0
    context_before=0
    context_after=0
    max_bytes=0
    # Phase 3D count / file-only output. One of: none | files | count | count-matches.
    count_mode="none"
    g_summary_json=""
    # Phase 4 diff/history controls.
    diff_staged=0
    diff_base=""
    history_messages=0
    history_patch=0
    # Phase 5 structural search language (falls back to AI_LANG, then php).
    lang_flag=""
    # Phase 3C scope control.
    case_mode="smart"      # smart | ignore | sensitive
    pattern_mode="default" # default | fixed | regex | pcre2
    max_depth=""
    glob_args=()
    type_args=()
    exclude_args=()
    # Ignore-file control (rg-backed modes). By DEFAULT all gitignore sources are
    # honored: local .gitignore, parent .gitignore, .git/info/exclude, the global
    # gitignore (git core.excludesfile), and .ignore/.rgignore files. These flags
    # selectively disable those sources to surface otherwise-ignored files.
    ignore_args=()

    # Parser scratch globals.
    positionals=()
    dry_run=0
    # Positional interpretation results.
    query=""
    root="."
    original_mode="$mode"
    legacy_alias=0
}
