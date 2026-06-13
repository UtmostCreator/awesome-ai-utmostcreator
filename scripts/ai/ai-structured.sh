#!/usr/bin/env bash
# Structured data query wrapper for AI agents.

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

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/ai/common.sh
source "$SCRIPT_DIR/common.sh"

usage() {
    cat <<'EOF'
Usage:
  ai-structured.sh json FILE QUERY
  ai-structured.sh yaml FILE QUERY
  ai-structured.sh validate-json FILE
  ai-structured.sh validate-yaml FILE
  ai-structured.sh csv FILE [--head N]
  ai-structured.sh xml FILE [XPATH]

Examples:
  scripts/ai/ai-structured.sh json package.json '.scripts'
  scripts/ai/ai-structured.sh yaml .github/workflows/ci.yml '.jobs | keys'
  scripts/ai/ai-structured.sh validate-json composer.json
  scripts/ai/ai-structured.sh csv data.csv --head 20
EOF
}

mode="${1:-}"
[[ -n "$mode" ]] || {
    usage
    exit 2
}
shift || true

agent_session_init "ai-structured"

case "$mode" in
json)
    require_bins jq
    file="${1:?file required}"
    query="${2:?jq query required}"
    [[ -f "$file" ]] || die "file not found: $file"
    jq "$query" "$file"
    ;;

yaml | yml)
    require_bins yq
    file="${1:?file required}"
    query="${2:?yq query required}"
    [[ -f "$file" ]] || die "file not found: $file"
    yq "$query" "$file"
    ;;

validate-json)
    require_bins jq
    file="${1:?file required}"
    [[ -f "$file" ]] || die "file not found: $file"
    jq empty "$file"
    log_ok "valid JSON: $file"
    ;;

validate-yaml | validate-yml)
    require_bins yq
    file="${1:?file required}"
    [[ -f "$file" ]] || die "file not found: $file"
    yq '.' "$file" >/dev/null
    log_ok "valid YAML: $file"
    ;;

csv)
    file="${1:?file required}"
    shift || true
    [[ -f "$file" ]] || die "file not found: $file"

    head_count=20
    while (($# > 0)); do
        case "$1" in
        --head)
            head_count="${2:?head count required}"
            shift 2
            ;;
        --head=*)
            head_count="${1#*=}"
            shift
            ;;
        *) die "unknown option: $1" ;;
        esac
    done

    if command -v mlr >/dev/null 2>&1; then
        mlr --icsv --opprint head -n "$head_count" "$file"
    elif command -v csvcut >/dev/null 2>&1; then
        csvcut "$file" | head -n "$head_count"
    else
        head -n "$head_count" "$file"
    fi
    ;;

xml)
    file="${1:?file required}"
    xpath="${2:-}"
    [[ -f "$file" ]] || die "file not found: $file"

    if command -v xmllint >/dev/null 2>&1; then
        if [[ -n "$xpath" ]]; then
            xmllint --xpath "$xpath" "$file"
        else
            xmllint --format "$file"
        fi
    else
        die "xmllint not installed"
    fi
    ;;

--help | -h)
    usage
    ;;

*)
    usage
    die "unknown mode: $mode"
    ;;
esac

log_json "structured.query" "$(jq -cn --arg mode "$mode" '{mode:$mode}')"
