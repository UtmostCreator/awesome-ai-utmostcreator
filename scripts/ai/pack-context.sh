#!/usr/bin/env bash
# Safe context packer wrapper.
#
# Wraps repomix, files-to-prompt, or code2prompt with:
# - secret scan
# - stable output path
# - token estimate
# - manifest output
# - session logging

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/ai/common.sh
source "$SCRIPT_DIR/common.sh"

usage() {
    cat <<'EOF'
Usage:
  pack-context.sh [auto|repomix|files-to-prompt|code2prompt] [tool args...]

Environment:
  OUTPUT_DIR=.repomix-context/manual
  OUTPUT_FILE=<path>
  OUTPUT_STYLE=xml
  SECRETS_SCAN=1
  TOKEN_BUDGET=80000

Examples:
  scripts/ai/pack-context.sh auto --include "docs/ai/**/*.md,tools/**/*.php"
  OUTPUT_FILE=.repomix-context/manual/docs.xml scripts/ai/pack-context.sh repomix --include "docs/**/*.md"
  scripts/ai/pack-context.sh files-to-prompt docs/ai/cli-tools.md docs/ai/tools/tool-map.md
EOF
}

backend="${1:-auto}"

case "$backend" in
auto | repomix | files-to-prompt | code2prompt)
    shift || true
    ;;
--help | -h)
    usage
    exit 0
    ;;
*)
    # Backward-compatible behaviour: first arg is probably a tool arg; use auto backend.
    backend="auto"
    ;;
esac

agent_session_init "pack-context"
require_bins jq

root="$(git_root)"
OUTPUT_DIR="${OUTPUT_DIR:-${AI_CONTEXT_DIR}/manual}"
OUTPUT_STYLE="${OUTPUT_STYLE:-xml}"
TOKEN_BUDGET="${TOKEN_BUDGET:-80000}"
timestamp="$(date +%Y%m%d-%H%M%S)"
OUTPUT_FILE="${OUTPUT_FILE:-${OUTPUT_DIR}/context-${timestamp}.${OUTPUT_STYLE}}"

mkdir -p "$OUTPUT_DIR"

args_contain_output() {
    local arg
    for arg in "$@"; do
        case "$arg" in
        --output | --output=*)
            return 0
            ;;
        esac
    done
    return 1
}

select_backend() {
    case "$backend" in
    auto)
        if command -v repomix >/dev/null 2>&1; then
            printf 'repomix\n'
        elif command -v files-to-prompt >/dev/null 2>&1; then
            printf 'files-to-prompt\n'
        elif command -v code2prompt >/dev/null 2>&1; then
            printf 'code2prompt\n'
        else
            die "no supported context packer found; install repomix, files-to-prompt, or code2prompt"
        fi
        ;;
    repomix)
        require_bins repomix
        printf 'repomix\n'
        ;;
    files-to-prompt)
        require_bins files-to-prompt
        printf 'files-to-prompt\n'
        ;;
    code2prompt)
        require_bins code2prompt
        printf 'code2prompt\n'
        ;;
    *)
        die "unknown backend: $backend"
        ;;
    esac
}

selected_backend="$(select_backend)"

section "Secrets scan"
require_clean_secret_scan "$root"

section "Pack context"
log_info "Backend: $selected_backend"
log_info "Output: $OUTPUT_FILE"

case "$selected_backend" in
repomix)
    repomix_args=("$@")

    if ! args_contain_output "${repomix_args[@]+${repomix_args[@]}}"; then
        repomix_args+=(--output "$OUTPUT_FILE")
    fi

    if [[ "$OUTPUT_STYLE" != "" ]]; then
        repomix_args+=(--style "$OUTPUT_STYLE")
    fi

    (
        cd "$root"
        repomix "${repomix_args[@]}"
    )
    ;;
files-to-prompt)
    (
        cd "$root"
        files-to-prompt "$@"
    ) >"$OUTPUT_FILE"
    ;;
code2prompt)
    (
        cd "$root"
        code2prompt "$@"
    ) >"$OUTPUT_FILE"
    ;;
*)
    die "unsupported selected backend: $selected_backend"
    ;;
esac

[[ -f "$OUTPUT_FILE" ]] || die "expected output file was not created: $OUTPUT_FILE"

tokens="$(estimate_tokens "$OUTPUT_FILE")"

if ! within_token_budget "$OUTPUT_FILE" "$TOKEN_BUDGET"; then
    log_warn "Context is ~${tokens} tokens, exceeding budget ${TOKEN_BUDGET}"
else
    log_ok "Context packed: ~${tokens} tokens"
fi

manifest="${OUTPUT_FILE%.*}.manifest.json"

jq -n \
    --arg backend "$selected_backend" \
    --arg output "$OUTPUT_FILE" \
    --arg root "$root" \
    --arg ts "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
    --argjson tokens "$tokens" \
    --argjson token_budget "$TOKEN_BUDGET" \
    --argjson args "$(printf '%s\n' "$@" | jq -R . | jq -s .)" \
    '{
      backend: $backend,
      output: $output,
      root: $root,
      ts: $ts,
      estimated_tokens: $tokens,
      token_budget: $token_budget,
      args: $args
    }' >"$manifest"

log_json "context.pack.manual" "$(cat "$manifest")"
printf '%s\n' "$OUTPUT_FILE"
