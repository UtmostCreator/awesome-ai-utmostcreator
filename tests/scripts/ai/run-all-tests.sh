#!/usr/bin/env bash
# Run all script test suites in priority order.
# Usage: bash tests/scripts/ai/run-all-tests.sh [filter]
#   filter: optional substring to match test file names (e.g. "common" or "rg-code")
#
# Environment:
#   SUITE_TIMEOUT=120   Per-suite timeout in seconds (default: 120). Set 0 to disable.
#   SCRIPT_TEST_JOBS=8  Number of shell test suites to run concurrently.

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
SCRIPT_TEST_JOBS="${SCRIPT_TEST_JOBS:-8}"

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
        bash "$file" 2>&1 &
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
        bash "$file" 2>&1 || rc=$?
    fi

    if ((rc == 0)); then
        PASS=$((PASS + 1))
    else
        FAIL=$((FAIL + 1))
        FAILED_SUITES+=("$name")
    fi
}

printf '\033[1m=== AI Script Test Runner ===\033[0m\n'

SUITES=(
    "common.sh|tests/scripts/ai/test-common.sh"
    "ai-search.sh|tests/scripts/ai/test-ai-search.sh"
    "rg-code.sh|tests/scripts/ai/test-rg-code.sh"
    "fd-files.sh|tests/scripts/ai/test-fd-files.sh"
    "preview-file.sh|tests/scripts/ai/test-preview-file.sh"
    "pre-tool-use.sh|tests/scripts/ai/test-pre-tool-use.sh"
    "post-tool-use.sh|tests/scripts/ai/test-post-tool-use.sh"
    "ai-verify.sh|tests/scripts/ai/test-ai-verify.sh"
    "ai-diff-context.sh|tests/scripts/ai/test-ai-diff-context.sh"
    "query-usage.sh|tests/scripts/ai/test-query-usage.sh"
    "ai-test-select.sh|tests/scripts/ai/test-ai-test-select.sh"
    "ai-task.sh|tests/scripts/ai/test-ai-task.sh"
    "ai-doc-check.sh|tests/scripts/ai/test-ai-doc-check.sh"
    "session-checkpoint.sh|tests/scripts/ai/test-session-checkpoint.sh"
    "git-forensics.sh|tests/scripts/ai/test-git-forensics.sh"
    "gh-pr-context.sh|tests/scripts/ai/test-gh-pr-context.sh"
    "ai-structured.sh|tests/scripts/ai/test-ai-structured.sh"
    "pack-context.sh|tests/scripts/ai/test-pack-context.sh"
    "repomix-scc-router.sh|tests/scripts/ai/test-repomix-scc-router.sh"
    "repomix-context-tree.sh|tests/scripts/ai/test-repomix-context-tree.sh"
    "run-repomix-context.sh|tests/scripts/ai/test-run-repomix-context.sh"
    "repo-tool-inventory.sh|tests/scripts/ai/test-repo-tool-inventory.sh"
    "watch-loop.sh|tests/scripts/ai/test-watch-loop.sh"
    "ai-edit.sh|tests/scripts/ai/test-ai-edit.sh"
    "ai-rollback.sh|tests/scripts/ai/test-ai-rollback.sh"
    "install-mandatory-tools.sh|tests/scripts/ai/test-install-mandatory-tools.sh"
)

run_suite_with_timeout() {
    local file="$1"
    local pid elapsed

    if [[ "$SUITE_TIMEOUT" -le 0 ]]; then
        bash "$file"
        return
    fi

    bash "$file" &
    pid=$!
    elapsed=0
    while kill -0 "$pid" 2>/dev/null; do
        if ((elapsed >= SUITE_TIMEOUT)); then
            kill -TERM "$pid" 2>/dev/null || true
            sleep 1
            kill -9 "$pid" 2>/dev/null || true
            wait "$pid" 2>/dev/null || true
            return 124
        fi
        sleep 1
        elapsed=$((elapsed + 1))
    done
    wait "$pid"
}

run_parallel_suites() {
    local idx=0 entry name file log rc
    local pids=() names=() logs=()

    PARALLEL_TMP_DIR="$(mktemp -d)"
    trap 'rm -rf "${PARALLEL_TMP_DIR:-}"' EXIT

    for entry in "${SUITES[@]}"; do
        name="${entry%%|*}"
        file="${entry#*|}"

        if [[ -n "$FILTER" && "$name" != *"$FILTER"* ]]; then
            continue
        fi

        if [[ ! -f "$file" ]]; then
            printf '  \033[0;33m⊘\033[0m %s (not yet created)\n' "$name"
            SKIP=$((SKIP + 1))
            continue
        fi

        log="$PARALLEL_TMP_DIR/${name//[^A-Za-z0-9_.-]/_}.log"
        printf '\n\033[1m━━━ %s (queued) ━━━\033[0m\n' "$name"
        (run_suite_with_timeout "$file") >"$log" 2>&1 &
        pids[idx]=$!
        names[idx]="$name"
        logs[idx]="$log"
        idx=$((idx + 1))
    done

    for idx in "${!pids[@]}"; do
        rc=0
        wait "${pids[$idx]}" || rc=$?
        name="${names[$idx]}"
        log="${logs[$idx]}"

        printf '\n\033[1m━━━ %s ━━━\033[0m\n' "$name"
        cat "$log"

        if ((rc == 0)); then
            PASS=$((PASS + 1))
        elif ((rc == 124)); then
            printf '  \033[0;31m⏱\033[0m %s (killed after %ds timeout)\n' "$name" "$SUITE_TIMEOUT"
            FAIL=$((FAIL + 1))
            TIMEOUT_COUNT=$((TIMEOUT_COUNT + 1))
            FAILED_SUITES+=("$name (TIMEOUT)")
        else
            FAIL=$((FAIL + 1))
            FAILED_SUITES+=("$name")
        fi
    done
}

if ((SCRIPT_TEST_JOBS > 1)); then
    run_parallel_suites
else
    for entry in "${SUITES[@]}"; do
        run_suite "${entry%%|*}" "${entry#*|}"
    done
fi

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
