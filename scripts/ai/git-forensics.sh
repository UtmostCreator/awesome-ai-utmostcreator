#!/usr/bin/env bash
# Repo-aware git history and blame wrapper.

set -euo pipefail

# Early --introspect / --help guard: when invoked with --introspect or --help/-h
# as the FIRST argument, emit this script's machine-readable JSON contract or its
# human-readable contract (static parse via sh-introspect) and exit before running
# any logic. The target script is parsed as text, never executed.
if [[ "${1:-}" == "--introspect" || "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    _ai_self_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    _ai_self_tool="$_ai_self_dir/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_self_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        if [[ "${1:-}" == "--introspect" ]]; then
            exec env AI_OUTPUT=json "${PHP_BIN:-php}" "$_ai_self_tool" "${BASH_SOURCE[0]}"
        fi
        exec "${PHP_BIN:-php}" "$_ai_self_tool" --format=help "${BASH_SOURCE[0]}"
    fi
fi
# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

require_bins git

usage() {
    cat <<'EOF'
Usage:
  git-forensics.sh MODE TARGET [file] [--json]

Modes:
  S      search by added/removed string via git log -S
  G      search by regex via git log -G
  L      line history via git log -L
  blame  annotate a line range in a file
EOF
}

case "${1:-}" in
--help | -h)
    usage
    exit 0
    ;;
esac

if [[ $# -lt 2 ]]; then
    usage >&2
    die "mode and target required"
fi

mode="$1"
search_target="$2"
file="${3:-}"

if [[ -n "$file" ]] && [[ "$file" == --* ]]; then
    file=""
fi

shift 2 || true
if [[ -n "$file" ]]; then
    shift || true
fi

OUTPUT_JSON=0
while [[ $# -gt 0 ]]; do
    case "$1" in
    --json)
        OUTPUT_JSON=1
        shift
        ;;
    *) die "unknown option: $1" ;;
    esac
done

run_and_capture() {
    local cmd=("$@")
    if [[ "$OUTPUT_JSON" == "1" ]]; then
        local output
        output="$("${cmd[@]}" 2>&1 || true)"
        jq -n --arg mode "$mode" --arg target "$search_target" --arg file "$file" --arg output "$output" '{mode:$mode, target:$target, file:(if $file == "" then null else $file end), output:$output}'
    else
        "${cmd[@]}"
    fi
}

case "$mode" in
S)
    if [[ -n "$file" ]]; then
        run_and_capture git log -S "$search_target" -p -- "$file"
    else
        run_and_capture git log -S "$search_target" -p
    fi
    ;;
G)
    if [[ -n "$file" ]]; then
        run_and_capture git log -G "$search_target" -p -- "$file"
    else
        run_and_capture git log -G "$search_target" -p
    fi
    ;;
L)
    run_and_capture git log -L "$search_target"
    ;;
blame)
    [[ -n "$file" ]] || die "file required for blame mode"
    if git blame -L "$search_target" -- "$file" >/dev/null 2>&1; then
        run_and_capture git blame -L "$search_target" -- "$file"
    elif git blame -L "$search_target" "$file" >/dev/null 2>&1; then
        run_and_capture git blame -L "$search_target" "$file"
    else
        # Fallback for files not yet in HEAD/history in fixture-heavy worktrees.
        run_and_capture sed -n "$search_target"p "$file"
    fi
    ;;
*) die "unknown mode: $mode" ;;
esac
