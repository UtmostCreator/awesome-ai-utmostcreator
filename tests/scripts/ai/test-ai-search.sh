#!/usr/bin/env bash
set -euo pipefail

# Test suite for scripts/ai/ai-search.sh.
#
# Structured case runner (Phase 0 of ai-search-todo.md). Helpers:
#   run_search ARGS...            -> capture JSON envelope into $LAST_JSON
#   expect_status NAME STATUS     -> assert .status == STATUS
#   expect_count  NAME OP N       -> assert (.matches|length) OP N  (OP: eq|ge|le|gt)
#   expect_jq     NAME FILTER     -> assert an arbitrary jq -e FILTER on $LAST_JSON
#
# Every case prints PASS/FAIL with its name; the suite exits non-zero on first FAIL
# (set -e via the assertion helpers) so it integrates with run-all-tests.sh.

BASH_BIN="${BASH_BIN:-$(command -v bash)}"
SCRIPT="scripts/ai/ai-search.sh"

LAST_JSON=""

run_search() {
    LAST_JSON="$(AI_OUTPUT=json "$BASH_BIN" "$SCRIPT" "$@")"
}

expect_status() {
    local name="$1" want="$2"
    if printf '%s' "$LAST_JSON" | jq -e --arg s "$want" '.status == $s' >/dev/null; then
        printf '  PASS %s\n' "$name"
    else
        printf '  FAIL %s (status: expected %s, got %s)\n' \
            "$name" "$want" "$(printf '%s' "$LAST_JSON" | jq -r '.status // "<none>"')" >&2
        return 1
    fi
}

expect_count() {
    local name="$1" op="$2" n="$3"
    if printf '%s' "$LAST_JSON" | jq -e --argjson n "$n" "(.matches|length) | . $op \$n" >/dev/null; then
        printf '  PASS %s\n' "$name"
    else
        printf '  FAIL %s (count %s %s failed, got %s)\n' \
            "$name" "$op" "$n" "$(printf '%s' "$LAST_JSON" | jq -r '(.matches|length) // "<none>"')" >&2
        return 1
    fi
}

expect_jq() {
    local name="$1" filter="$2"
    if printf '%s' "$LAST_JSON" | jq -e "$filter" >/dev/null; then
        printf '  PASS %s\n' "$name"
    else
        printf '  FAIL %s (jq filter failed: %s)\n' "$name" "$filter" >&2
        printf '       envelope: %s\n' "$(printf '%s' "$LAST_JSON" | jq -c '.')" >&2
        return 1
    fi
}

# --- temp fixtures (isolated; never write into the repo tree) -----------------
tmp_search_dir="$(mktemp -d)"
tmp_dir="$(mktemp -d)"
trap 'rm -rf "$tmp_search_dir" "$tmp_dir"' EXIT

printf 'AlphaBeta\n' >"$tmp_search_dir/case.txt"

# =============================================================================
# Phase 0 — existing contract (kept green; do not weaken)
# =============================================================================
printf '[phase0] baseline status contract\n'

run_search doctor
expect_status "doctor -> ok" "ok"

run_search text AGENTS.md . --dry-run
expect_status "text --dry-run -> dry_run" "dry_run"

run_search unsafe-all AGENTS.md .
expect_status "unsafe-all -> unsafe_blocked" "unsafe_blocked"

run_search docs "Project Summary" . --fixed
expect_jq "docs --fixed -> ok|no_matches" '.status=="ok" or .status=="no_matches"'

run_search text "XYZZY_NO_MATCH_4f7a2b9c" tools --fixed
expect_status "text no-hit -> no_matches" "no_matches"
expect_count "text no-hit -> 0 matches" "==" 0

run_search tracked AGENTS . --fixed
expect_status "tracked AGENTS --fixed -> ok" "ok"
expect_count "tracked AGENTS --fixed -> >0" ">" 0

AI_LANG=php run_search struct '$A' tools --fixed
expect_jq "struct -> ok|no_matches" '.status=="ok" or .status=="no_matches"'

run_search text alphabeta "$tmp_search_dir" --fixed
expect_status "smart-case lower -> ok" "ok"
expect_count "smart-case lower -> 1" "==" 1

run_search text ALPHABETA "$tmp_search_dir" --fixed
expect_status "smart-case upper -> no_matches" "no_matches"

run_search text ALPHABETA "$tmp_search_dir" --fixed --ignore-case
expect_status "ignore-case upper -> ok" "ok"
expect_count "ignore-case upper -> 1" "==" 1

# changed mode against a non-git CWD must not crash the envelope
repo_root="$(git rev-parse --show-toplevel)"
(
    cd "$tmp_dir"
    LAST_JSON="$(AI_OUTPUT=json "$BASH_BIN" "$repo_root/$SCRIPT" changed "ai-search" "$repo_root" --fixed)"
    printf '%s' "$LAST_JSON" | jq -e '.status=="ok" or .status=="no_matches"' >/dev/null \
        && printf '  PASS changed (repo root) -> ok|no_matches\n' \
        || { printf '  FAIL changed (repo root)\n' >&2; exit 1; }
)

# =============================================================================
# Phase 0 — canonical envelope (query + mode echoed back)
# =============================================================================
printf '[phase0] envelope keys\n'

run_search text AGENTS . --fixed
expect_jq "envelope has query" '.query == "AGENTS"'
expect_jq "envelope has mode" '.mode == "text"'
expect_jq "envelope has schema/tool" '(.schema|type=="string") and (.tool=="ai-search")'

# =============================================================================
# Phase 1A — argument parser (flags in any position; unknown flag is an error)
# =============================================================================
printf '[phase1A] argument parser\n'

# Flag without an explicit root must still search "." (not treat --fixed as root).
run_search text AGENTS --fixed
expect_status "text QUERY --fixed (no root) -> ok" "ok"
expect_count "text QUERY --fixed (no root) -> >0" ">" 0

# Root given explicitly, plus trailing flags in any order.
run_search text AGENTS . --fixed --ignore-case
expect_status "text QUERY root --fixed --ignore-case -> ok" "ok"

# Unknown flag must be a hard error that names the flag, not silently ignored.
run_search text AGENTS . --bad-flag
expect_status "unknown flag -> error" "error"
expect_jq "unknown flag names the flag" '(.errors|join(" ")) | test("--bad-flag")'

# =============================================================================
# Phase 1B — real errors vs no_matches vs unavailable
# =============================================================================
printf '[phase1B] error classification\n'

# Invalid regex must be a real error, not no_matches.
run_search text '(' .
expect_status "invalid regex -> error" "error"
expect_jq "invalid regex error mentions regex" '(.errors|join(" ")) | test("regex|parse|invalid"; "i")'

# git modes against a non-git directory must error, not collapse to no_matches.
nogit="$(mktemp -d)"
run_search tracked foo "$nogit"
expect_status "tracked on non-git root -> error" "error"
run_search changed foo "$nogit"
expect_status "changed on non-git root -> error" "error"
rmdir "$nogit" 2>/dev/null || true

# =============================================================================
# Phase 1C — real doctor diagnostics
# =============================================================================
printf '[phase1C] doctor diagnostics\n'

run_search doctor
expect_jq "doctor reports available[]" '(.diagnostics.available|type=="array")'
expect_jq "doctor reports missing[]" '(.diagnostics.missing|type=="array")'
expect_jq "doctor reports warnings[]" '(.diagnostics.warnings|type=="array")'
expect_jq "doctor lists core tools" '
  (.diagnostics.available|index("jq"))
  and (.diagnostics.available|index("git"))
  and ((.diagnostics.available|index("rg")) or (.diagnostics.missing|index("rg")))
'
expect_jq "doctor reports root + git_available" '
  (.diagnostics|has("root")) and (.diagnostics|has("git_available"))
'

# =============================================================================
# Phase 1D — bounded output
# =============================================================================
printf '[phase1D] bounded output\n'

# Broad query is capped and reports truncation in meta.
run_search text AGENTS . --max-results 1
expect_status "max-results 1 -> ok" "ok"
expect_count "max-results 1 -> <=1" "<=" 1
expect_jq "max-results echoed in limits" '.limits.max_results == 1'
expect_jq "max-results truncation flagged" '.meta.truncated == true'

echo "ai-search tests passed"
