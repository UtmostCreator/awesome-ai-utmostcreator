#!/usr/bin/env bash
# Repo-aware git history and blame wrapper.

set -euo pipefail
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

mode="${1:?mode required}"
search_target="${2:?target required}"
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
    --help | -h)
        usage
        exit 0
        ;;
    *) die "unknown option: $1" ;;
    esac
done

run_and_capture() {
    local cmd=("$@")
    if [[ "$OUTPUT_JSON" == "1" ]]; then
        jq -n --arg mode "$mode" --arg target "$search_target" --arg file "$file" --arg output "$("${cmd[@]}")" '{mode:$mode, target:$target, file:(if $file == "" then null else $file end), output:$output}'
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
    run_and_capture git blame -L "$search_target" "$file"
    ;;
*) die "unknown mode: $mode" ;;
esac
