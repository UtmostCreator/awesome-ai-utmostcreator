#!/opt/homebrew/bin/bash
# Run all script test suites in priority order.
# Usage: /opt/homebrew/bin/bash tests/scripts/ai/run-all-tests.sh [filter]
#   filter: optional substring to match test file names (e.g. "common" or "rg-code")
#
# Environment:
#   SUITE_TIMEOUT=120   Per-suite timeout in seconds (default: 120). Set 0 to disable.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
cd "$REPO_ROOT"

FILTER="${1:-}"
SUITE_TIMEOUT="${SUITE_TIMEOUT:-120}"
PASS=0
FAIL=0
SKIP=0
TIMEOUT_COUNT=0
FAILED_SUITES=()

run_suite() {
    local name="$1"
    local file="$2"

    if [[ -n "$FILTER" && "$name" != *"$FILTER"* ]]; then
        return 0
    fi

    if [[ ! -f "$file" ]]; then
        printf '  \033[0;33m⊘\033[0m %s (not yet created)\n' "$name"
        SKIP=$((SKIP + 1))
        return 0
    fi

    printf '\n\033[1m━━━ %s ━━━\033[0m\n' "$name"

    local rc=0
    if [[ "$SUITE_TIMEOUT" -gt 0 ]]; then
        # Run with timeout; kill the suite if it hangs
        local pid
        /opt/homebrew/bin/bash "$file" 2>&1 &
        pid=$!

        local elapsed=0
        while kill -0 "$pid" 2>/dev/null; do
            if ((elapsed >= SUITE_TIMEOUT)); then
                kill -TERM "$pid" 2>/dev/null || true
                sleep 1
                kill -9 "$pid" 2>/dev/null || true
                wait "$pid" 2>/dev/null || true
                printf '  \033[0;31m⏱\033[0m %s (killed after %ds timeout)\n' "$name" "$SUITE_TIMEOUT"
                FAIL=$((FAIL + 1))
                TIMEOUT_COUNT=$((TIMEOUT_COUNT + 1))
                FAILED_SUITES+=("$name (TIMEOUT)")
                return 0
            fi
            sleep 1
            elapsed=$((elapsed + 1))
        done
        wait "$pid" || rc=$?
    else
        /opt/homebrew/bin/bash "$file" 2>&1 || rc=$?
    fi

    if ((rc == 0)); then
        PASS=$((PASS + 1))
    else
        FAIL=$((FAIL + 1))
        FAILED_SUITES+=("$name")
    fi
}

printf '\033[1m=== AI Script Test Runner ===\033[0m\n'

# P0 — Critical path
run_suite "common.sh"          "tests/scripts/ai/test-common.sh"
run_suite "ai-search.sh"       "tests/scripts/ai/test-ai-search.sh"
run_suite "rg-code.sh"         "tests/scripts/ai/test-rg-code.sh"
run_suite "fd-files.sh"        "tests/scripts/ai/test-fd-files.sh"
run_suite "preview-file.sh"    "tests/scripts/ai/test-preview-file.sh"
run_suite "pre-tool-use.sh"    "tests/scripts/ai/test-pre-tool-use.sh"
run_suite "post-tool-use.sh"   "tests/scripts/ai/test-post-tool-use.sh"
run_suite "ai-verify.sh"       "tests/scripts/ai/test-ai-verify.sh"

# P1 — Important
run_suite "ai-diff-context.sh"    "tests/scripts/ai/test-ai-diff-context.sh"
run_suite "query-usage.sh"        "tests/scripts/ai/test-query-usage.sh"
run_suite "ai-test-select.sh"     "tests/scripts/ai/test-ai-test-select.sh"
run_suite "ai-task.sh"            "tests/scripts/ai/test-ai-task.sh"
run_suite "ai-doc-check.sh"       "tests/scripts/ai/test-ai-doc-check.sh"
run_suite "session-checkpoint.sh" "tests/scripts/ai/test-session-checkpoint.sh"

# P2 — Important but less frequent
run_suite "git-forensics.sh"         "tests/scripts/ai/test-git-forensics.sh"
run_suite "gh-pr-context.sh"         "tests/scripts/ai/test-gh-pr-context.sh"
run_suite "ai-structured.sh"         "tests/scripts/ai/test-ai-structured.sh"
run_suite "pack-context.sh"          "tests/scripts/ai/test-pack-context.sh"
run_suite "repomix-scc-router.sh"    "tests/scripts/ai/test-repomix-scc-router.sh"
run_suite "repomix-context-tree.sh"  "tests/scripts/ai/test-repomix-context-tree.sh"

# P3 — Useful
run_suite "run-repomix-context.sh"   "tests/scripts/ai/test-run-repomix-context.sh"
run_suite "repo-tool-inventory.sh"   "tests/scripts/ai/test-repo-tool-inventory.sh"
run_suite "watch-loop.sh"            "tests/scripts/ai/test-watch-loop.sh"

# P4 — Controlled
run_suite "ai-edit.sh"              "tests/scripts/ai/test-ai-edit.sh"
run_suite "ai-rollback.sh"          "tests/scripts/ai/test-ai-rollback.sh"
run_suite "install-mandatory-tools.sh" "tests/scripts/ai/test-install-mandatory-tools.sh"

printf '\n\033[1m=== Suite Summary ===\033[0m\n'
printf '  Passed:   %d\n' "$PASS"
printf '  Failed:   %d\n' "$FAIL"
printf '  Timeouts: %d\n' "$TIMEOUT_COUNT"
printf '  Skipped:  %d\n' "$SKIP"

if ((FAIL > 0)); then
    printf '\nFailed suites:\n'
    for s in "${FAILED_SUITES[@]}"; do
        printf '  \033[0;31m✗\033[0m %s\n' "$s"
    done
    exit 1
fi

printf '\n\033[0;32mAll suites passed.\033[0m\n'
