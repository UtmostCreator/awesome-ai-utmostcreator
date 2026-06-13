#!/usr/bin/env bash
# Full PR context wrapper for review and context packing.

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
# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

require_bins gh jq

usage() {
    echo "Usage: $0 <PR-number> [--diff] [--checks] [--reviews] [--pack] [--json]"
}

pr="${1:?PR number required}"
shift || true

WANT_DIFF=0
WANT_CHECKS=0
WANT_REVIEWS=0
WANT_PACK=0
OUTPUT_FORMAT="${OUTPUT_FORMAT:-plain}"

while [[ $# -gt 0 ]]; do
    case "$1" in
    --diff) WANT_DIFF=1 ;;
    --checks) WANT_CHECKS=1 ;;
    --reviews) WANT_REVIEWS=1 ;;
    --pack) WANT_PACK=1 ;;
    --json) OUTPUT_FORMAT="json" ;;
    --help | -h)
        usage
        exit 0
        ;;
    *) die "unknown option: $1" ;;
    esac
    shift
done

agent_session_init "gh-pr-context"

section "PR #$pr metadata"

pr_json="$(gh pr view "$pr" \
    --json title,body,author,state,baseRefName,headRefName,files,commits,labels,assignees,reviewRequests,isDraft,url,mergedAt,closedAt,createdAt,updatedAt)"

checks_json="null"
if [[ "$WANT_CHECKS" == "1" ]]; then
    section "CI checks"
    checks_json="$(gh pr checks "$pr" --json name,state,conclusion,startedAt,completedAt,link 2>/dev/null || echo '[]')"
fi

reviews_json="null"
if [[ "$WANT_REVIEWS" == "1" ]]; then
    section "Reviews"
    reviews_json="$(gh pr view "$pr" --json reviews --jq '.reviews | map({author:.author.login, state:.state, body:.body, submittedAt:.submittedAt})')"
fi

diff_content=""
if [[ "$WANT_DIFF" == "1" ]]; then
    section "Diff"
    diff_content="$(gh pr diff "$pr" 2>/dev/null || echo '(diff unavailable)')"
fi

if [[ "$OUTPUT_FORMAT" == "json" ]]; then
    jq -n \
        --argjson pr "$pr_json" \
        --argjson checks "${checks_json:-null}" \
        --argjson reviews "${reviews_json:-null}" \
        --arg diff "$diff_content" \
        '{
      pr: {
        title: $pr.title,
        state: $pr.state,
        isDraft: $pr.isDraft,
        url: $pr.url,
        author: $pr.author.login,
        base: $pr.baseRefName,
        head: $pr.headRefName,
        labels: [$pr.labels[].name],
        assignees: [$pr.assignees[].login],
        commitCount: ($pr.commits | length),
        fileCount: ($pr.files | length),
        files: [$pr.files[].path],
        createdAt: $pr.createdAt,
        updatedAt: $pr.updatedAt
      },
      checks: $checks,
      reviews: $reviews,
      diff: (if $diff != "" then $diff else null end)
    }'
else
    printf '# PR #%s - %s\n\n' "$pr" "$(echo "$pr_json" | jq -r '.title')"
    echo "$pr_json" | jq -r '"**State:** \(.state)  |  **Author:** \(.author.login)  |  **Draft:** \(.isDraft)"'
    echo "$pr_json" | jq -r '"**Base:** \(.baseRefName)  <-  **Head:** \(.headRefName)"'
    echo "$pr_json" | jq -r '"**Files changed:** \(.files | length)  |  **Commits:** \(.commits | length)"'
    echo
    echo "## Files changed"
    echo "$pr_json" | jq -r '.files[].path | "- " + .'
    echo
    echo "## Description"
    echo "$pr_json" | jq -r '.body // "(no description)"'

    if [[ "$WANT_CHECKS" == "1" ]] && [[ "$checks_json" != "null" ]]; then
        echo
        echo "## CI Checks"
        printf '%-50s  %-12s  %s\n' "NAME" "STATE" "CONCLUSION"
        echo "$checks_json" | jq -r '.[] | [.name, .state, (.conclusion // "-")] | @tsv' |
            while IFS=$'\t' read -r name state conclusion; do
                printf '%-50s  %-12s  %s\n' "$name" "$state" "$conclusion"
            done
    fi

    if [[ "$WANT_REVIEWS" == "1" ]] && [[ "$reviews_json" != "null" ]]; then
        echo
        echo "## Reviews"
        echo "$reviews_json" | jq -r '.[] | "- **\(.author)** [\(.state)]: \(.body // "(no comment)")"'
    fi

    if [[ "$WANT_DIFF" == "1" ]] && [[ -n "$diff_content" ]]; then
        echo
        echo "## Diff"
        echo '```diff'
        printf '%s\n' "$diff_content"
        echo '```'
    fi
fi

if [[ "$WANT_PACK" == "1" ]]; then
    section "Packing PR files as AI context"
    "$(dirname "${BASH_SOURCE[0]}")/ai-diff-context.sh" pr "$pr"
fi

log_json "gh-pr-context.done" \
    "$(jq -cn --arg pr "$pr" --argjson diff "$WANT_DIFF" --argjson checks "$WANT_CHECKS" --argjson reviews "$WANT_REVIEWS" '{pr:$pr, diff:$diff, checks:$checks, reviews:$reviews}')"
