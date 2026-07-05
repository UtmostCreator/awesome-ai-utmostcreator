#!/usr/bin/env bash
# Structured data query wrapper for AI agents.

set -euo pipefail

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
