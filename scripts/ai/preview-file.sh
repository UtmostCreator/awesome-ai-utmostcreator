#!/usr/bin/env bash
set -euo pipefail
# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

usage() {
    cat <<'EOF'
Usage:
  preview-file.sh FILE [--plain] [--lines N] [--range A:B] [--around L --context N]
                  [--dry-run] [--force] [--max-bytes N] [--max-columns N]
EOF
}

emit_json() {
    local status="$1" path="$2" content="$3" errors="${4:-[]}" warnings="${5:-[]}"
    jq -cn \
        --arg schema "1" \
        --arg status "$status" \
        --arg tool "preview-file" \
        --arg path "$path" \
        --arg content "$content" \
        --argjson errors "$errors" \
        --argjson warnings "$warnings" \
        '{schema:$schema,status:$status,tool:$tool,path:$path,content:$content,warnings:$warnings,errors:$errors}'
}

parse_size() {
    local raw="${1:-0}"
    raw="${raw// /}"
    if [[ "$raw" =~ ^([0-9]+)([KkMmGg])$ ]]; then
        local n="${BASH_REMATCH[1]}"
        case "${BASH_REMATCH[2],,}" in
            k) echo $((n * 1024)) ;;
            m) echo $((n * 1024 * 1024)) ;;
            g) echo $((n * 1024 * 1024 * 1024)) ;;
        esac
    else
        echo "$raw"
    fi
}

json_mode="${AI_OUTPUT:-}"

file=""
plain=0
dry_run=0
force=0
lines=""
range=""
around=""
context=3
max_bytes_raw="65536"
max_columns=200

while [[ $# -gt 0 ]]; do
    case "$1" in
        --help|-h) usage; exit 0 ;;
        --plain) plain=1; shift ;;
        --dry-run) dry_run=1; shift ;;
        --force) force=1; shift ;;
        --lines) lines="${2:-}"; shift 2 ;;
        --lines=*) lines="${1#*=}"; shift ;;
        --range) range="${2:-}"; shift 2 ;;
        --range=*) range="${1#*=}"; shift ;;
        --around) around="${2:-}"; shift 2 ;;
        --around=*) around="${1#*=}"; shift ;;
        --context) context="${2:-3}"; shift 2 ;;
        --context=*) context="${1#*=}"; shift ;;
        --max-bytes) max_bytes_raw="${2:-65536}"; shift 2 ;;
        --max-bytes=*) max_bytes_raw="${1#*=}"; shift ;;
        --max-columns) max_columns="${2:-200}"; shift 2 ;;
        --max-columns=*) max_columns="${1#*=}"; shift ;;
        --*)
            if [[ "$json_mode" == "json" ]]; then
                emit_json "error" "" "" "[\"unknown option: $1\"]"
                exit 1
            fi
            die "unknown option: $1"
            ;;
        *)
            if [[ -z "$file" ]]; then file="$1"; shift; else die "unexpected arg: $1"; fi
            ;;
    esac
done

max_bytes="$(parse_size "$max_bytes_raw")"

if [[ -z "$file" ]]; then
    usage
    exit 2
fi

if [[ ! -f "$file" ]]; then
    if [[ "$json_mode" == "json" ]]; then
        emit_json "error" "$file" "" '["file not found"]'
    else
        echo "[ERROR] file not found: $file" >&2
    fi
    exit 1
fi

if [[ "$file" == *"/.git/"* || "$file" == .git/* ]]; then
    if [[ "$force" != "1" ]]; then
        if [[ "$json_mode" == "json" ]]; then
            emit_json "error" "$file" "" '[".git internals blocked"]'
        else
            echo "[ERROR] .git internals blocked" >&2
        fi
        exit 1
    fi
fi

if [[ "$dry_run" == "1" ]]; then
    if [[ "$json_mode" == "json" ]]; then
        emit_json "dry_run" "$file" ""
    else
        echo "dry-run"
    fi
    exit 0
fi

size="$(wc -c <"$file" | tr -d ' ')"
if (( size > max_bytes )) && [[ "$force" != "1" ]]; then
    if [[ "$json_mode" == "json" ]]; then
        emit_json "error" "$file" "" '["max-bytes exceeded"]'
    else
        echo "[ERROR] max-bytes exceeded" >&2
    fi
    exit 1
fi

# Real binary detection: count NUL bytes via tr (avoid bash $'\x00' empty-string bug).
nul_count="$(LC_ALL=C tr -cd '\000' <"$file" | wc -c | tr -d ' ')"
if (( nul_count > 0 )) && [[ "$force" != "1" ]]; then
    if [[ "$json_mode" == "json" ]]; then
        emit_json "error" "$file" "" '["binary file blocked"]'
    else
        echo "[ERROR] binary file blocked" >&2
    fi
    exit 1
fi

if [[ -n "$lines" ]] && ! [[ "$lines" =~ ^[0-9]+$ ]]; then
    if [[ "$json_mode" == "json" ]]; then
        emit_json "error" "$file" "" '["invalid --lines"]'
    else
        echo "[ERROR] invalid --lines" >&2
    fi
    exit 1
fi

if [[ -n "$range" ]]; then
    if ! [[ "$range" =~ ^([0-9]+):([0-9]+)$ ]]; then
        if [[ "$json_mode" == "json" ]]; then
            emit_json "error" "$file" "" '["invalid --range"]'
        else
            echo "[ERROR] invalid --range" >&2
        fi
        exit 1
    fi
    start="${BASH_REMATCH[1]}"; end="${BASH_REMATCH[2]}"
    if (( start > end )); then
        if [[ "$json_mode" == "json" ]]; then
            emit_json "error" "$file" "" '["invalid --range"]'
        else
            echo "[ERROR] invalid --range" >&2
        fi
        exit 1
    fi
    content="$(sed -n "${start},${end}p" "$file")"
elif [[ -n "$around" ]]; then
    if ! [[ "$around" =~ ^[0-9]+$ ]]; then
        echo "[ERROR] invalid --around" >&2
        exit 1
    fi
    if ! [[ "$context" =~ ^[0-9]+$ ]]; then
        echo "[ERROR] invalid --context" >&2
        exit 1
    fi
    start=$((around-context)); ((start<1)) && start=1
    end=$((around+context))
    content="$(sed -n "${start},${end}p" "$file")"
elif [[ -n "$lines" ]]; then
    content="$(sed -n "1,${lines}p" "$file")"
else
    content="$(sed -n '1,200p' "$file")"
fi

# Column truncation (kept readable for plain output, JSON gets raw content).
truncated_content="$(printf '%s\n' "$content" | awk -v m="$max_columns" '{ if (length($0)>m) { print substr($0,1,m) " ...truncated" } else print }')"

if [[ "$json_mode" == "json" ]]; then
    emit_json "ok" "$file" "$content"
else
    if [[ "$plain" == "0" ]] && command_exists bat; then
        printf '%s\n' "$truncated_content" | bat --paging=never --style=plain --color=always
    else
        printf '%s\n' "$truncated_content"
    fi
fi
