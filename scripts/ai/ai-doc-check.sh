#!/usr/bin/env bash
# Documentation verification wrapper for AI agents.

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
  ai-doc-check.sh [all|markdownlint|links|drift] [paths...]
  ai-doc-check.sh --check [paths...]

Environment:
  DOC_PATHS="README.md docs/**/*.md"
  Link checks always run lychee with --offline.
EOF
}

# Generated/aggregated docs are gitignored build artifacts, not authored documentation.
# Their concatenated relative links resolve against the wrong base and produce false
# broken-link errors, so they are excluded from doc checks.
is_excluded_doc_path() {
    case "$1" in
    docs/ai/generated/* | ./docs/ai/generated/*)
        return 0
        ;;
    esac
    return 1
}

resolve_doc_paths() {
    local -a resolved=()

    if (($# > 0)); then
        local candidate
        for candidate in "$@"; do
            [[ -e "$candidate" ]] || continue
            is_excluded_doc_path "$candidate" && continue
            resolved+=("$candidate")
        done
    else
        local pattern
        for pattern in $DOC_PATHS; do
            if [[ -e "$pattern" ]]; then
                is_excluded_doc_path "$pattern" && continue
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
            local -a matches=($pattern)
            if ((${#matches[@]} == 0)); then
                continue
            fi
            local match
            for match in "${matches[@]}"; do
                is_excluded_doc_path "$match" && continue
                resolved+=("$match")
            done
        done
    fi

    if ((${#resolved[@]} == 0)); then
        log_warn "no documentation paths found; skipping"
        return 0
    fi

    printf '%s\n' "${resolved[@]}"
}

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
        # Accept 403/429: these mean the resource exists but blocks automated checks
        # (anti-bot / rate limiting), which must not be treated as a broken link.
        run_step "lychee --offline" lychee --offline --accept "200..=299,403,429" "${DOC_PATH_LIST[@]}"
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

    if [[ -f tools/ai/validate-context-budgets.php ]]; then
        run_step "validate-context-budgets" php tools/ai/validate-context-budgets.php
    fi

    if [[ -f tools/ai/validate-agent-spec.php ]]; then
        run_step "validate-agent-spec --self-test" php tools/ai/validate-agent-spec.php --self-test
    fi

    if [[ -f tools/ai/validate-stub-surfaces.php ]]; then
        run_step "validate-stub-surfaces" php tools/ai/validate-stub-surfaces.php --root=.
    fi

    if [[ -f tools/ai/validate-catalog-drift.php ]]; then
        run_step "validate-catalog-drift" php tools/ai/validate-catalog-drift.php --root=.
    fi

    if [[ -f tools/ai/validate-schemas.php ]]; then
        run_step "validate-schemas" php tools/ai/validate-schemas.php --root=.
    fi

    if [[ -f tools/ai/validate-mentor-parity.php ]]; then
        run_step "validate-mentor-parity" php tools/ai/validate-mentor-parity.php
    fi
}

main() {
    local mode="all"
    local DOC_PATHS="${DOC_PATHS:-README.md docs/**/*.md}"
    local failures=0

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

    mapfile -t DOC_PATH_LIST < <(resolve_doc_paths "$@")

    agent_session_init "ai-doc-check"

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
}

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
    main "$@"
fi
