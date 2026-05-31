#!/usr/bin/env bash
# Documentation verification wrapper for AI agents.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/ai/common.sh
source "$SCRIPT_DIR/common.sh"

usage() {
    cat <<'EOF'
Usage:
  ai-doc-check.sh [all|markdownlint|links|drift] [paths...]
  ai-doc-check.sh --check [paths...]

Environment:
  DOC_PATHS="README.md docs/**/*.md"
EOF
}

mode="all"
DOC_PATHS="${DOC_PATHS:-README.md docs/**/*.md}"
failures=0

if (($# > 0)); then
    case "$1" in
    all | markdownlint | links | drift)
        mode="$1"
        shift
        ;;
    --check)
        shift
        ;;
    --help | -h)
        usage
        exit 0
        ;;
    *)
        # A first argument that is neither a known mode/flag nor an existing
        # path is an unknown mode. Existing paths fall through as [paths...].
        if [[ ! -e "$1" ]]; then
            usage >&2
            die "unknown mode: $1"
        fi
        ;;
    esac
fi

shopt -s nullglob globstar

resolve_doc_paths() {
    local -a resolved=()

    if (($# > 0)); then
        local candidate
        for candidate in "$@"; do
            [[ -e "$candidate" ]] || continue
            resolved+=("$candidate")
        done
    else
        local pattern
        for pattern in $DOC_PATHS; do
            if [[ -e "$pattern" ]]; then
                resolved+=("$pattern")
                continue
            fi
            case "$pattern" in
            *[*?[]*) ;;
            *)
                continue
                ;;
            esac
            # Intentional glob expansion (globstar + nullglob enabled above).
            # shellcheck disable=SC2206
            local -a matches=( $pattern )
            if ((${#matches[@]} == 0)); then
                continue
            fi
            resolved+=("${matches[@]}")
        done
    fi

    if ((${#resolved[@]} == 0)); then
        log_warn "no documentation paths found; skipping"
        return 0
    fi

    printf '%s\n' "${resolved[@]}"
}

mapfile -t DOC_PATH_LIST < <(resolve_doc_paths "$@")

agent_session_init "ai-doc-check"

run_step() {
    local label="$1"
    shift

    echo "==> $label"

    if ! "$@"; then
        echo "FAIL: $label" >&2
        failures=$((failures + 1))
    fi
}

run_markdownlint() {
    if ((${#DOC_PATH_LIST[@]} == 0)); then
        return 0
    fi
    if command -v markdownlint >/dev/null 2>&1; then
        run_step "markdownlint" markdownlint "${DOC_PATH_LIST[@]}"
    else
        log_warn "markdownlint not installed; skipping"
    fi
}

run_links() {
    if ((${#DOC_PATH_LIST[@]} == 0)); then
        return 0
    fi
    if command -v lychee >/dev/null 2>&1; then
        run_step "lychee" lychee "${DOC_PATH_LIST[@]}"
    else
        log_warn "lychee not installed; skipping"
    fi
}

run_drift() {
    if [[ -f scripts/ai/repo-tool-inventory.sh ]]; then
        run_step "repo-tool-inventory --check" bash scripts/ai/repo-tool-inventory.sh --check
    fi

    if [[ -f tools/ai/validate-generated-artifacts.php ]]; then
        run_step "validate-generated-artifacts" php tools/ai/validate-generated-artifacts.php
    fi

    if [[ -f tools/ai/generate-agent-snippets.php ]]; then
        run_step "agent-snippets --check" php tools/ai/generate-agent-snippets.php --check
    fi
}

case "$mode" in
all)
    run_markdownlint
    run_links
    run_drift
    ;;
markdownlint)
    run_markdownlint
    ;;
links)
    run_links
    ;;
drift)
    run_drift
    ;;
--help | -h)
    usage
    ;;
*)
    usage
    die "unknown mode: $mode"
    ;;
esac

if ((failures > 0)); then
    log_json "doc-check.failed" "$(jq -cn --argjson failures "$failures" '{failures:$failures}')"
    exit 1
fi

log_json "doc-check.passed" "$(jq -cn --arg mode "$mode" '{mode:$mode}')"
echo "==> docs ok"
