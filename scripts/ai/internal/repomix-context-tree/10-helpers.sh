# shellcheck shell=bash
# shellcheck disable=SC2154,SC2034  # cross-module globals via dynamic scope
# repomix-context-tree/10-helpers.sh — logging, path, token-budget helpers.
#
# Sourced by scripts/ai/repomix-context-tree.sh (thin loader). Not an entrypoint.
# Behavior is byte-for-byte identical to the previous monolithic version.

die() {
    printf 'Error: %s\n' "$1" >&2
    exit 1
}

log() {
    printf '[repomix-tree] %s\n' "$1"
}

confirm_context_delete() {
    local action="$1"
    local target="$2"

    log "requested destructive context action: $action -> $target"

    if [[ "${APPROVE_CONTEXT_DELETE:-0}" == "1" ]]; then
        return 0
    fi

    if [[ -t 0 ]] && [[ "${CI:-}" != "true" ]]; then
        printf 'Continue with %s on %s? [y/N] ' "$action" "$target" >&2
        read -r confirm
        [[ "$confirm" =~ ^[Yy]$ ]] && return 0
    fi

    die "context deletion requires APPROVE_CONTEXT_DELETE=1 or interactive confirmation"
}

add_winget_paths() {
    local user_name="${USER:-${USERNAME:-}}"
    local base="/c/Users/${user_name}/AppData/Local/Microsoft/WinGet/Packages"
    [[ -d "$base" ]] || return 0
    local dir
    while IFS= read -r dir; do
        case ":$PATH:" in
        *":$dir:"*) ;;
        *) PATH="$PATH:$dir" ;;
        esac
    done < <(find "$base" -maxdepth 3 -type f -name '*.exe' -printf '%h\n' 2>/dev/null | sort -u)
}

need_bin() {
    local name="$1"
    command -v "$name" >/dev/null 2>&1 || die "required binary '$name' not found"
}

ext_for_style() {
    case "$1" in
    xml) printf 'xml\n' ;;
    markdown) printf 'md\n' ;;
    json) printf 'json\n' ;;
    plain) printf 'txt\n' ;;
    *) die "unsupported style '$1'" ;;
    esac
}

abs_path() {
    local input="$1"
    if [[ "$input" = /* ]]; then
        printf '%s\n' "$input"
    else
        printf '%s\n' "$(cd "$(dirname "$input")" && pwd)/$(basename "$input")"
    fi
}

safe_name() {
    local name="$1"
    name="${name//\//__}"
    name="${name// /_}"
    printf '%s\n' "$name"
}

estimate_tokens() {
    local bytes="$1"
    awk -v b="$bytes" 'BEGIN { printf "%d", int((b + 3) / 4) }'
}

usable_budget() {
    awk -v cw="$CONTEXT_WINDOW" -v ro="$RESERVED_OUTPUT" -v io="$INSTRUCTION_OVERHEAD" -v sf="$SAFETY_FACTOR" 'BEGIN {
      raw = cw - ro - io
      if (raw < 0) raw = 0
      usable = int(raw * sf)
      if (usable < 0) usable = 0
      printf "%d", usable
    }'
}
