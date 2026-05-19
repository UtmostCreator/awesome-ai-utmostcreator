#!/usr/bin/env bash
# Documentation verification wrapper for AI agents.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/ai/common.sh
source "$SCRIPT_DIR/common.sh"

usage() {
    cat <<'EOF'
Usage:
  ai-doc-check.sh [all|markdownlint|links|drift]

Environment:
  DOC_PATHS="README.md docs/**/*.md"
EOF
}

mode="${1:-all}"
DOC_PATHS="${DOC_PATHS:-README.md docs/**/*.md}"
failures=0

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
    if command -v markdownlint >/dev/null 2>&1; then
        # shellcheck disable=SC2086
        run_step "markdownlint" markdownlint $DOC_PATHS
    else
        log_warn "markdownlint not installed; skipping"
    fi
}

run_links() {
    if command -v lychee >/dev/null 2>&1; then
        # shellcheck disable=SC2086
        run_step "lychee" lychee $DOC_PATHS
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
--help|-h)
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