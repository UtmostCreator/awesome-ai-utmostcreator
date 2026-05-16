#!/usr/bin/env bash
set -euo pipefail
# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

usage() {
    cat <<'EOF'
Usage:
  ai-search.sh MODE QUERY [root] [--fixed] [--dry-run]

Modes:
  changed | staged | tracked | text | files | struct | docs | doctor | unsafe-all
EOF
}

emit_json() {
    local status="$1" matches_json="${2:-[]}" errors="${3:-[]}" warnings="${4:-[]}"
    local tmp_matches tmp_errors tmp_warnings
    tmp_matches="$(mktemp)"
    tmp_errors="$(mktemp)"
    tmp_warnings="$(mktemp)"
    printf '%s' "$matches_json" >"$tmp_matches"
    printf '%s' "$errors" >"$tmp_errors"
    printf '%s' "$warnings" >"$tmp_warnings"
    jq -cn \
        --arg schema "1" \
        --arg status "$status" \
        --arg tool "ai-search" \
        --slurpfile matches "$tmp_matches" \
        --slurpfile errors "$tmp_errors" \
        --slurpfile warnings "$tmp_warnings" \
        '{schema:$schema,status:$status,tool:$tool,matches:$matches[0],warnings:$warnings[0],errors:$errors[0]}'
    local rc=$?
    rm -f "$tmp_matches" "$tmp_errors" "$tmp_warnings"
    return $rc
}

json_mode="${AI_OUTPUT:-}"
mode="${1:-}"

if [[ "$mode" == "--help" || "$mode" == "-h" || -z "$mode" ]]; then
    usage
    exit 0
fi

if [[ "$mode" == "doctor" ]]; then
    if [[ "$json_mode" == "json" ]]; then
        emit_json "ok" "[]"
    else
        echo "ai-search doctor: ok"
    fi
    exit 0
fi

query="${2:-}"
root="${3:-.}"
shift 3 2>/dev/null || true

fixed=0
dry_run=0
while [[ $# -gt 0 ]]; do
    case "$1" in
        --fixed) fixed=1; shift ;;
        --dry-run) dry_run=1; shift ;;
        --help|-h) usage; exit 0 ;;
        *) shift ;;
    esac
done

if [[ "$mode" == "unsafe-all" ]]; then
    if [[ "$json_mode" == "json" ]]; then
        emit_json "unsafe_blocked" "[]" '["unsafe-all requires approval"]'
    else
        echo "unsafe-all blocked"
    fi
    exit 0
fi

if [[ -z "$query" ]]; then
    if [[ "$json_mode" == "json" ]]; then
        emit_json "error" "[]" '["query required"]'
    else
        echo "[ERROR] query required" >&2
    fi
    exit 1
fi

if [[ "$dry_run" == "1" ]]; then
    if [[ "$json_mode" == "json" ]]; then
        emit_json "dry_run" "[]"
    else
        echo "dry-run"
    fi
    exit 0
fi

run_text() {
    if [[ "$fixed" == "1" ]]; then
        rg -n --fixed-strings -- "$query" "$root" 2>/dev/null || true
    else
        rg -n -- "$query" "$root" 2>/dev/null || true
    fi
}

run_files() {
    if command_exists fd; then
        fd --hidden --exclude .git -- "$query" "$root" 2>/dev/null || true
    elif command_exists fdfind; then
        fdfind --hidden --exclude .git -- "$query" "$root" 2>/dev/null || true
    else
        true
    fi
}

case "$mode" in
    changed)
        out="$(git -C "$root" diff --name-only 2>/dev/null | tr -d '\r' || true)"
        ;;
    staged)
        out="$(git -C "$root" diff --name-only --cached 2>/dev/null | tr -d '\r' || true)"
        ;;
    tracked)
        if [[ "$fixed" == "1" ]]; then
            out="$(git -C "$root" grep -n --fixed-strings -- "$query" 2>/dev/null || true)"
        else
            out="$(git -C "$root" grep -n -- "$query" 2>/dev/null || true)"
        fi
        ;;
    text|docs)
        out="$(run_text)"
        ;;
    files)
        out="$(run_files)"
        ;;
    struct)
        if command_exists ast-grep; then
            out="$(ast-grep run --lang "${AI_LANG:-php}" --pattern "$query" "$root" 2>/dev/null || true)"
        else
            out=""
        fi
        ;;
    *)
        if [[ "$json_mode" == "json" ]]; then
            emit_json "error" "[]" '["unknown mode"]'
        else
            echo "[ERROR] unknown mode: $mode" >&2
        fi
        exit 1
        ;;
esac

if [[ "$json_mode" == "json" ]]; then
    matches_json="$(printf '%s' "$out" | jq -R -s 'split("\n")|map(select(length>0))')"
    count="$(printf '%s' "$matches_json" | jq 'length')"
    if [[ "$count" == "0" ]]; then
        emit_json "no_matches" "$matches_json"
    else
        emit_json "ok" "$matches_json"
    fi
else
    printf '%s\n' "$out"
fi
