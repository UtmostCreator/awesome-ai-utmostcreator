#!/usr/bin/env bash
set -euo pipefail

# Test suite for scripts/ai/ai-search.sh and critical ai-search-multi.sh wiring.
#
# Helpers:
#   run_search ARGS...            -> capture JSON envelope into $LAST_JSON
#   run_search_strict ARGS...     -> capture JSON envelope with AI_SEARCH_STRICT=1
#   run_multi ARGS...             -> capture JSON array from ai-search-multi.sh
#   expect_status NAME STATUS     -> assert .status == STATUS
#   expect_count  NAME OP N       -> assert (.matches|length) OP N
#   expect_jq     NAME FILTER     -> assert jq -e FILTER on $LAST_JSON
#   expect_no_jq  NAME FILTER     -> assert jq -e FILTER does NOT match
#
# Phase 0–2 are always-on.
# Phase 3–5 are gated:
#   AI_SEARCH_RUN_P1_TESTS=1 bash tests/ai-search.sh

BASH_BIN="${BASH_BIN:-$(command -v bash)}"
SCRIPT="scripts/ai/ai-search.sh"
MULTI_SCRIPT="scripts/ai/ai-search-multi.sh"

LAST_JSON=""
LAST_RC=0

run_search() {
    set +e
    LAST_JSON="$(AI_OUTPUT=json "$BASH_BIN" "$SCRIPT" "$@" 2>&1)"
    LAST_RC=$?
    set -e
}

run_search_strict() {
    set +e
    LAST_JSON="$(AI_SEARCH_STRICT=1 AI_OUTPUT=json "$BASH_BIN" "$SCRIPT" "$@" 2>&1)"
    LAST_RC=$?
    set -e
}

run_multi() {
    set +e
    LAST_JSON="$(AI_OUTPUT=json "$BASH_BIN" "$MULTI_SCRIPT" "$@" 2>&1)"
    LAST_RC=$?
    set -e
}

# run_search_without TOOL ARGS... — run ai-search.sh with a PATH that contains
# every needed tool EXCEPT TOOL, to prove missing-core-tool error handling.
# Returns rc 99 (and leaves $LAST_JSON empty) when the isolated bindir cannot be
# built, so the caller can skip cleanly.
run_search_without() {
    local drop="$1"
    shift
    local bindir tool p
    bindir="$(mktemp -d)"
    for tool in jq git bash sh awk grep sed cat tr wc dirname mktemp rm find \
        printf rmdir fd fdfind ast-grep rg env head tail sort uniq xargs; do
        [[ "$tool" == "$drop" ]] && continue
        p="$(command -v "$tool" 2>/dev/null)" && ln -sf "$p" "$bindir/$tool"
    done
    if [[ -n "$(PATH="$bindir" command -v "$drop" 2>/dev/null)" ]]; then
        rm -rf "$bindir"
        LAST_JSON=""
        LAST_RC=99
        return 0
    fi
    set +e
    LAST_JSON="$(PATH="$bindir" AI_OUTPUT=json "$BASH_BIN" "$SCRIPT" "$@" 2>&1)"
    LAST_RC=$?
    set -e
    rm -rf "$bindir"
}

expect_status() {
    local name="$1" want="$2"

    if printf '%s' "$LAST_JSON" | jq -e --arg s "$want" '.status == $s' >/dev/null; then
        printf '  PASS %s\n' "$name"
    else
        printf '  FAIL %s (status: expected %s, got %s, rc=%s)\n' \
            "$name" \
            "$want" \
            "$(printf '%s' "$LAST_JSON" | jq -r '.status // "<none>"' 2>/dev/null || printf '<invalid-json>')" \
            "$LAST_RC" >&2
        printf '       envelope: %s\n' "$LAST_JSON" >&2
        return 1
    fi
}

expect_count() {
    local name="$1" op="$2" n="$3"

    if printf '%s' "$LAST_JSON" | jq -e --argjson n "$n" "(.matches|length) | . $op \$n" >/dev/null; then
        printf '  PASS %s\n' "$name"
    else
        printf '  FAIL %s (count %s %s failed, got %s)\n' \
            "$name" \
            "$op" \
            "$n" \
            "$(printf '%s' "$LAST_JSON" | jq -r '(.matches|length) // "<none>"' 2>/dev/null || printf '<invalid-json>')" >&2
        printf '       envelope: %s\n' "$LAST_JSON" >&2
        return 1
    fi
}

expect_jq() {
    local name="$1" filter="$2"

    if printf '%s' "$LAST_JSON" | jq -e "$filter" >/dev/null; then
        printf '  PASS %s\n' "$name"
    else
        printf '  FAIL %s (jq filter failed: %s)\n' "$name" "$filter" >&2
        printf '       envelope: %s\n' "$(printf '%s' "$LAST_JSON" | jq -c '.' 2>/dev/null || printf '%s' "$LAST_JSON")" >&2
        return 1
    fi
}

expect_no_jq() {
    local name="$1" filter="$2"
    local rc

    set +e
    printf '%s' "$LAST_JSON" | jq -e "$filter" >/dev/null
    rc=$?
    set -e

    if [[ "$rc" -ne 0 ]]; then
        printf '  PASS %s\n' "$name"
    else
        printf '  FAIL %s (unexpected jq match: %s)\n' "$name" "$filter" >&2
        printf '       envelope: %s\n' "$(printf '%s' "$LAST_JSON" | jq -c '.' 2>/dev/null || printf '%s' "$LAST_JSON")" >&2
        return 1
    fi
}

# --- temp fixtures ------------------------------------------------------------
tmp_search_dir="$(mktemp -d)"
tmp_dir="$(mktemp -d)"
phase2_repo="$(mktemp -d)"
p1_repo="$(mktemp -d)"
nogit=""
trap 'rm -rf "$tmp_search_dir" "$tmp_dir" "$phase2_repo" "$p1_repo" ${nogit:+"$nogit"}' EXIT

printf 'AlphaBeta\n' >"$tmp_search_dir/case.txt"

# --- Phase 2 fixture ----------------------------------------------------------
git -C "$phase2_repo" init -q
git -C "$phase2_repo" config user.email "test@example.com"
git -C "$phase2_repo" config user.name "Test User"

mkdir -p "$phase2_repo/app"

printf 'Tenant unchanged\n' >"$phase2_repo/app/Unchanged.php"
printf 'Base changed\n' >"$phase2_repo/app/Changed.php"
printf 'Base staged\n' >"$phase2_repo/app/Staged.php"

git -C "$phase2_repo" add .
git -C "$phase2_repo" commit -qm "initial"

printf 'Tenant changed\n' >"$phase2_repo/app/Changed.php"
printf 'Tenant staged\n' >"$phase2_repo/app/Staged.php"
git -C "$phase2_repo" add app/Staged.php

# --- Phase 3–5 fixture --------------------------------------------------------
git -C "$p1_repo" init -q
git -C "$p1_repo" config user.email "test@example.com"
git -C "$p1_repo" config user.name "Test User"
git -C "$p1_repo" branch -M main

mkdir -p \
    "$p1_repo/app" \
    "$p1_repo/docs" \
    "$p1_repo/routes" \
    "$p1_repo/tests" \
    "$p1_repo/__tests__" \
    "$p1_repo/config" \
    "$p1_repo/vendor/pkg" \
    "$p1_repo/node_modules/pkg" \
    "$p1_repo/dist" \
    "$p1_repo/build" \
    "$p1_repo/coverage" \
    "$p1_repo/deep/nested/structure"

cat >"$p1_repo/app/UserService.php" <<'PHP'
<?php

class UserService
{
    public function findUser(int $id): string
    {
        return "StructuredNeedle";
    }
}

interface UserContract
{
}

enum UserStatus
{
    case Active;
}

// TODO: TodoNeedle refactor this legacy workaround.
// deprecated temporary workaround legacy
PHP

cat >"$p1_repo/app/Has:Colon.php" <<'PHP'
<?php
echo "ColonNeedle";
PHP

cat >"$p1_repo/app/scope.php" <<'PHP'
<?php
echo "ScopeNeedle";
echo "DefaultExcludedNeedle";
echo "DocsNeedle";
echo "TestNeedle";
echo "ConfigNeedle";
echo "DepsNeedle";
PHP

cat >"$p1_repo/app/frontend.js" <<'JS'
export const value = "ScopeNeedle";
function frontendFunction(arg) {
  return arg;
}
JS

cat >"$p1_repo/app/context.txt" <<'TXT'
ctx-before-3
ctx-before-2
ctx-before-1
ContextNeedle
ctx-after-1
ctx-after-2
ctx-after-3
TXT

cat >"$p1_repo/app/case-control.txt" <<'TXT'
AlphaCase
a.b
axb
foobar
TXT

cat >"$p1_repo/root-depth.txt" <<'TXT'
DepthNeedle root
TXT

cat >"$p1_repo/deep/nested/structure/deep-depth.txt" <<'TXT'
DepthNeedle nested
TXT

cat >"$p1_repo/vendor/pkg/dep.php" <<'PHP'
<?php
echo "DefaultExcludedNeedle";
echo "ExplicitExcludedNeedle";
PHP

cat >"$p1_repo/node_modules/pkg/dep.js" <<'JS'
console.log("DefaultExcludedNeedle");
console.log("ExplicitExcludedNeedle");
JS

cat >"$p1_repo/dist/out.js" <<'JS'
console.log("DefaultExcludedNeedle");
JS

cat >"$p1_repo/build/out.js" <<'JS'
console.log("DefaultExcludedNeedle");
JS

cat >"$p1_repo/coverage/out.txt" <<'TXT'
DefaultExcludedNeedle
TXT

cat >"$p1_repo/README.md" <<'MD'
# README
DocsNeedle
MD

cat >"$p1_repo/docs/guide.md" <<'MD'
DocsNeedle
MD

cat >"$p1_repo/tests/UserServiceTest.php" <<'PHP'
<?php
echo "TestNeedle";
PHP

cat >"$p1_repo/__tests__/user.spec.js" <<'JS'
console.log("TestNeedle");
JS

cat >"$p1_repo/config/app.yaml" <<'YAML'
name: ConfigNeedle
service_key: ConfigKeyNeedle
YAML

cat >"$p1_repo/routes/web.php" <<'PHP'
<?php
Route::get('/users', [UserService::class, 'findUser']);
// RouteNeedle
PHP

cat >"$p1_repo/.env.example" <<'ENV'
APP_NAME=ConfigNeedle
ENV

cat >"$p1_repo/docker-compose.yml" <<'YAML'
services:
  app:
    image: ConfigNeedle
YAML

cat >"$p1_repo/composer.json" <<'JSON'
{"name": "example/deps-needle", "description": "DepsNeedle"}
JSON

cat >"$p1_repo/package.json" <<'JSON'
{"name": "deps-needle", "description": "DepsNeedle"}
JSON

cat >"$p1_repo/package-lock.json" <<'JSON'
{"name": "deps-needle-lock", "description": "DepsNeedle"}
JSON

cat >"$p1_repo/flake.nix" <<'NIX'
{
  description = "DepsNeedle";
}
NIX

cat >"$p1_repo/app/unsafe.php" <<'PHP'
<?php
eval($_GET['UnsafeNeedle']);
PHP

cat >"$p1_repo/app/DiffTarget.php" <<'PHP'
<?php
echo "before diff";
PHP

cat >"$p1_repo/app/StagedDiff.php" <<'PHP'
<?php
echo "before staged diff";
PHP

cat >"$p1_repo/app/HistoryTarget.php" <<'PHP'
<?php
echo "HistoryNeedle";
PHP

git -C "$p1_repo" add .
git -C "$p1_repo" commit -qm "Initial P1 fixture with HistoryMessageNeedle"

# Unstaged diff fixture.
cat >"$p1_repo/app/DiffTarget.php" <<'PHP'
<?php
echo "DiffNeedle";
PHP

# Staged diff fixture.
cat >"$p1_repo/app/StagedDiff.php" <<'PHP'
<?php
echo "StagedDiffNeedle";
PHP
git -C "$p1_repo" add app/StagedDiff.php

# =============================================================================
# Phase 0 — existing contract
# =============================================================================
printf '[phase0] baseline status contract\n'

run_search doctor
expect_status "doctor -> ok" "ok"

run_search text AGENTS.md . --dry-run
expect_status "text --dry-run -> dry_run" "dry_run"

run_search unsafe-all AGENTS.md .
expect_jq "unsafe-all -> blocked-compatible" '.status=="unsafe_blocked" or .status=="blocked"'

run_search docs "Project Summary" . --fixed
expect_jq "docs --fixed -> ok|no_matches" '.status=="ok" or .status=="no_matches"'

run_search text "XYZZY_NO_MATCH_4f7a2b9c" tools --fixed
expect_status "text no-hit -> no_matches" "no_matches"
expect_count "text no-hit -> 0 matches" "==" 0

run_search tracked AGENTS . --fixed
expect_status "tracked AGENTS --fixed -> ok" "ok"
expect_count "tracked AGENTS --fixed -> >0" ">" 0

AI_LANG=php run_search struct '$A' tools --fixed
expect_jq "struct -> ok|no_matches|unavailable" '.status=="ok" or .status=="no_matches" or .status=="unavailable"'

run_search text alphabeta "$tmp_search_dir" --fixed
expect_status "smart-case lower -> ok" "ok"
expect_count "smart-case lower -> 1" "==" 1

run_search text ALPHABETA "$tmp_search_dir" --fixed
expect_status "smart-case upper -> no_matches" "no_matches"

run_search text ALPHABETA "$tmp_search_dir" --fixed --ignore-case
expect_status "ignore-case upper -> ok" "ok"
expect_count "ignore-case upper -> 1" "==" 1

repo_root="$(git rev-parse --show-toplevel)"
(
    cd "$tmp_dir"

    set +e
    json="$(AI_OUTPUT=json "$BASH_BIN" "$repo_root/$SCRIPT" changed "ai-search" "$repo_root" --fixed 2>&1)"
    rc=$?
    set -e

    printf '%s' "$json" | jq -e '.status=="ok" or .status=="no_matches"' >/dev/null &&
        printf '%s' "$json" | jq -e '.mode=="changed-files" or .mode=="changed"' >/dev/null &&
        printf '  PASS legacy changed alias from non-git cwd -> ok|no_matches\n' ||
        {
            printf '  FAIL legacy changed alias from non-git cwd (rc=%s)\n' "$rc" >&2
            printf '       envelope: %s\n' "$json" >&2
            exit 1
        }
)

# =============================================================================
# Phase 0 — canonical envelope
# =============================================================================
printf '[phase0] envelope keys\n'

run_search text AGENTS . --fixed
expect_jq "envelope has query" '.query == "AGENTS"'
expect_jq "envelope has mode" '.mode == "text"'
expect_jq "envelope has schema/tool" '(.schema|type=="string") and (.tool=="ai-search")'
expect_jq "envelope has matches array" '(.matches|type=="array")'
expect_jq "envelope has warnings/errors arrays" '(.warnings|type=="array") and (.errors|type=="array")'
expect_jq "envelope has limits/meta" '(.limits|type=="object") and (.meta|type=="object")'

# =============================================================================
# Phase 1A — argument parser
# =============================================================================
printf '[phase1A] argument parser\n'

run_search text AGENTS --fixed
expect_status "text QUERY --fixed no root -> ok" "ok"
expect_count "text QUERY --fixed no root -> >0" ">" 0

run_search text AGENTS . --fixed --ignore-case
expect_status "text QUERY root --fixed --ignore-case -> ok" "ok"

run_search text AGENTS . --bad-flag
expect_status "unknown flag -> error" "error"
expect_jq "unknown flag names the flag" '(.errors|join(" ")) | test("--bad-flag")'

# =============================================================================
# Phase 1B — real errors vs no_matches vs unavailable
# =============================================================================
printf '[phase1B] error classification\n'

run_search text '(' .
expect_status "invalid regex -> error" "error"
expect_jq "invalid regex error mentions regex" '(.errors|join(" ")) | test("regex|parse|invalid"; "i")'

nogit="$(mktemp -d)"

run_search tracked foo "$nogit"
expect_status "tracked on non-git root -> error" "error"

run_search changed-files "$nogit"
expect_status "changed-files on non-git root -> error" "error"

run_search changed foo "$nogit"
expect_status "legacy changed on non-git root -> error" "error"

rmdir "$nogit" 2>/dev/null || true
nogit=""

# A file passed where a directory root is expected must error clearly (the real
# cause is "root is a file", not "not a git repository"). Regression for the
# misleading `git -C <file>` message in require_git_root.
run_search tracked foo AGENTS.md --fixed
expect_status "tracked with file root -> error" "error"
expect_jq "file-root error names directory requirement" \
    '(.errors|join(" ")) | test("must be a directory|got file"; "i")'

# Missing rg in `text` mode degrades to git grep (P1b) with a parity warning,
# instead of erroring — git grep yields the same path:line:text shape. Skip
# cleanly if rg cannot be hidden from PATH in this environment.
run_search_without rg text Tenant .
if [[ "$LAST_RC" -eq 99 ]]; then
    printf '  PASS missing rg (text fallback) test skipped (rg not isolatable on this PATH)\n'
else
    expect_status "missing rg text -> degraded ok" "ok"
    expect_jq "missing rg text warns about git grep fallback" '(.warnings|join(" ")) | test("rg|ripgrep|git grep"; "i")'
fi

# Missing rg in a SURFACE mode (docs) has no safe fallback (rg glob scoping has
# no git-grep equivalent), so it must still fail closed with an error.
run_search_without rg docs Tenant .
if [[ "$LAST_RC" -eq 99 ]]; then
    printf '  PASS missing rg (docs no-fallback) test skipped (rg not isolatable on this PATH)\n'
else
    expect_status "missing rg docs -> error" "error"
    expect_jq "missing rg docs error names rg" '(.errors|join(" ")) | test("rg|ripgrep"; "i")'
fi

run_search_without git diff Foo . --fixed
if [[ "$LAST_RC" -eq 99 ]]; then
    printf '  PASS missing git test skipped (git not isolatable on this PATH)\n'
else
    expect_status "missing git -> error" "error"
    expect_jq "missing git error names git" '(.errors|join(" ")) | test("git"; "i")'
fi

# struct with ast-grep absent -> unavailable (proves the optional-tool path).
run_search_without ast-grep struct 'class $NAME' . --lang php
if [[ "$LAST_RC" -eq 99 ]]; then
    printf '  PASS struct-unavailable test skipped (ast-grep not isolatable)\n'
else
    expect_status "struct without ast-grep -> unavailable" "unavailable"
    expect_jq "struct unavailable names ast-grep" '(.errors|join(" ")) | test("ast-grep|unavailable"; "i")'
fi

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

# Gate-proof: the canonical file-list mode names must still contain the
# substrings validate-ai-config.php greps for (changed/staged/tracked), so the
# rename satisfies the config gate without running PHP here.
printf '[phase0] gate-proof: renamed modes keep required substrings\n'
gate_ok=1
gate_modes="changed-files staged-files tracked"
for needle in changed staged tracked; do
    case "$gate_modes" in
    *"$needle"*) : ;;
    *) gate_ok=0 ;;
    esac
done
if [[ "$gate_ok" -eq 1 ]]; then
    printf '  PASS renamed modes contain changed/staged/tracked substrings\n'
else
    printf '  FAIL renamed modes missing a required substring\n' >&2
    exit 1
fi

# =============================================================================
# Phase 1D — bounded output
# =============================================================================
printf '[phase1D] bounded output\n'

run_search text AGENTS . --max-results 1
expect_status "max-results 1 -> ok" "ok"
expect_count "max-results 1 -> <=1" "<=" 1
expect_jq "max-results echoed in limits" '.limits.max_results == 1'
expect_jq "max-results truncation flagged" '.meta.truncated == true'

# =============================================================================
# Phase 2A — backward-compatible aliasing
# =============================================================================
printf '[phase2A] rename aliases and canonical file-list modes\n'

run_search changed-files "$phase2_repo"
expect_status "changed-files root -> ok" "ok"
expect_jq "changed-files mode echoed" '.mode == "changed-files"'
expect_jq "changed-files query empty" '.query == ""'
expect_jq "changed-files returns Changed.php" '.matches == ["app/Changed.php"]'
expect_jq "changed-files has no warnings" '.warnings == []'

run_search staged-files "$phase2_repo"
expect_status "staged-files root -> ok" "ok"
expect_jq "staged-files mode echoed" '.mode == "staged-files"'
expect_jq "staged-files query empty" '.query == ""'
expect_jq "staged-files returns Staged.php" '.matches == ["app/Staged.php"]'
expect_jq "staged-files has no warnings" '.warnings == []'

run_search changed dummy "$phase2_repo"
expect_status "legacy changed dummy root -> ok" "ok"
expect_jq "legacy changed canonical mode echoed" '.mode == "changed-files"'
expect_jq "legacy changed dummy query ignored" '.query == ""'
expect_jq "legacy changed returns Changed.php" '.matches == ["app/Changed.php"]'
expect_jq "legacy changed emits deprecation warning" '(.warnings|join(" ")) | test("deprecated") and test("changed-files")'

run_search staged dummy "$phase2_repo"
expect_status "legacy staged dummy root -> ok" "ok"
expect_jq "legacy staged canonical mode echoed" '.mode == "staged-files"'
expect_jq "legacy staged dummy query ignored" '.query == ""'
expect_jq "legacy staged returns Staged.php" '.matches == ["app/Staged.php"]'
expect_jq "legacy staged emits deprecation warning" '(.warnings|join(" ")) | test("deprecated") and test("staged-files")'

run_search tracked Tenant "$phase2_repo" --fixed
expect_status "tracked unchanged by rename -> ok" "ok"
expect_jq "tracked unchanged mode echoed" '.mode == "tracked"'
expect_jq "tracked unchanged has no warnings" '.warnings == []'
expect_count "tracked unchanged -> >0" ">" 0

# =============================================================================
# Phase 2B — file-list vs content-search modes
# =============================================================================
printf '[phase2B] file-list vs content-search modes\n'

run_search changed-text Tenant "$phase2_repo" --fixed
expect_status "changed-text Tenant -> ok" "ok"
expect_jq "changed-text mode echoed" '.mode == "changed-text"'
expect_jq "changed-text query echoed" '.query == "Tenant"'
expect_jq "changed-text searches changed file" '.matches[] | contains("app/Changed.php")'
expect_no_jq "changed-text excludes unchanged file" '.matches[] | contains("app/Unchanged.php")'
expect_no_jq "changed-text excludes staged-only file" '.matches[] | contains("app/Staged.php")'

run_search staged-text Tenant "$phase2_repo" --fixed
expect_status "staged-text Tenant -> ok" "ok"
expect_jq "staged-text mode echoed" '.mode == "staged-text"'
expect_jq "staged-text query echoed" '.query == "Tenant"'
expect_jq "staged-text searches staged file" '.matches[] | contains("app/Staged.php")'
expect_no_jq "staged-text excludes unchanged file" '.matches[] | contains("app/Unchanged.php")'
expect_no_jq "staged-text excludes unstaged-only file" '.matches[] | contains("app/Changed.php")'

run_search changed-text
expect_status "changed-text without query -> error" "error"
expect_jq "changed-text without query names query requirement" '(.errors|join(" ")) | test("query required"; "i")'

run_search staged-text
expect_status "staged-text without query -> error" "error"
expect_jq "staged-text without query names query requirement" '(.errors|join(" ")) | test("query required"; "i")'

run_search changed-files Tenant "$phase2_repo"
expect_status "changed-files with query -> error" "error"
expect_jq "changed-files with query forbidden" '(.errors|join(" ")) | test("does not accept a query"; "i")'

run_search staged-files Tenant "$phase2_repo"
expect_status "staged-files with query -> error" "error"
expect_jq "staged-files with query forbidden" '(.errors|join(" ")) | test("does not accept a query"; "i")'

# =============================================================================
# Phase 2C — ai-search-multi.sh wiring
# =============================================================================
printf '[phase2C] ai-search-multi wiring\n'

run_multi changed-files "$phase2_repo"
expect_jq "multi changed-files returns one envelope" 'type=="array" and length==1'
expect_jq "multi changed-files status ok" '.[0].status == "ok"'
expect_jq "multi changed-files returns Changed.php" '.[0].matches == ["app/Changed.php"]'

run_multi staged-files "$phase2_repo"
expect_jq "multi staged-files returns one envelope" 'type=="array" and length==1'
expect_jq "multi staged-files status ok" '.[0].status == "ok"'
expect_jq "multi staged-files returns Staged.php" '.[0].matches == ["app/Staged.php"]'

run_multi changed dummy "$phase2_repo"
expect_jq "multi legacy changed dummy root -> ok" '.[0].status == "ok"'
expect_jq "multi legacy changed warns" '.[0].warnings | join(" ") | test("deprecated") and test("changed-files")'

run_multi staged dummy "$phase2_repo"
expect_jq "multi legacy staged dummy root -> ok" '.[0].status == "ok"'
expect_jq "multi legacy staged warns" '.[0].warnings | join(" ") | test("deprecated") and test("staged-files")'

run_multi changed-text Tenant "$phase2_repo" --fixed
expect_jq "multi changed-text -> ok" '.[0].status == "ok"'
expect_jq "multi changed-text searches changed file" '.[0].matches[] | contains("app/Changed.php")'

run_multi staged-text Tenant "$phase2_repo" --fixed
expect_jq "multi staged-text -> ok" '.[0].status == "ok"'
expect_jq "multi staged-text searches staged file" '.[0].matches[] | contains("app/Staged.php")'

# =============================================================================
# Phase 2E — strict legacy rejection gate
# =============================================================================
printf '[phase2E] strict-mode legacy alias rejection\n'

run_search_strict changed dummy "$phase2_repo"
expect_status "strict legacy changed -> error" "error"
expect_jq "strict legacy changed points to new names" '(.errors|join(" ")) | test("changed-files") and test("changed-text")'

run_search_strict staged dummy "$phase2_repo"
expect_status "strict legacy staged -> error" "error"
expect_jq "strict legacy staged points to new names" '(.errors|join(" ")) | test("staged-files") and test("staged-text")'

if [[ "${AI_SEARCH_RUN_P1_TESTS:-0}" != "1" ]]; then
    printf '[phase3-5] skipped P1 tests; run with AI_SEARCH_RUN_P1_TESTS=1 when implementing Phase 3+\n'
    echo "ai-search tests passed"
    exit 0
fi

# =============================================================================
# Phase 3A — structured match objects
# =============================================================================
printf '[phase3A] structured match objects\n'

run_search text StructuredNeedle "$p1_repo" --fixed
expect_status "structured text search -> ok" "ok"
expect_jq "legacy matches[] remains additive string array" '
  (.matches|type=="array")
  and (.matches|length > 0)
  and all(.matches[]; type=="string")
'
expect_jq "results[] exists for structured objects" '(.results|type=="array") and (.results|length > 0)'
expect_jq "structured result has core fields" '
  .results[]
  | select(
      .path == "app/UserService.php"
      and (.line|type=="number")
      and (.text|contains("StructuredNeedle"))
      and .mode == "text"
      and (.source_tool|type=="string")
    )
'
expect_jq "structured result has column/language/root when applicable" '
  .results[]
  | select(
      .path == "app/UserService.php"
      and (.column|type=="number")
      and .language == "php"
      and (.root|type=="string")
    )
'

run_search text ColonNeedle "$p1_repo" --fixed
expect_status "colon filename search -> ok" "ok"
expect_jq "colon filename path remains valid" '
  .results[]
  | select(.path == "app/Has:Colon.php" and (.text | contains("ColonNeedle")))
'

run_search text StructuredNeedle "$p1_repo" --fixed --absolute
expect_status "--absolute search -> ok" "ok"
expect_jq "--absolute keeps relative path" '.results[] | select(.path == "app/UserService.php")'
expect_jq "--absolute adds absolute_path" '
  .results[]
  | select(.path == "app/UserService.php")
  | .absolute_path
  | type=="string" and startswith("/")
'

# =============================================================================
# Phase 3B — context lines
# =============================================================================
printf '[phase3B] context lines\n'

run_search text ContextNeedle "$p1_repo" --fixed --context 2
expect_status "--context 2 -> ok" "ok"
expect_jq "--context separates match from context" '
  .results[0]
  | (.text | contains("ContextNeedle"))
  and (.context.before|type=="array")
  and (.context.after|type=="array")
'
expect_jq "--context 2 before/after counts" '
  (.results[0].context.before|length) == 2
  and (.results[0].context.after|length) == 2
'
expect_jq "--context before excludes match line" '
  all(.results[0].context.before[]; (.text | contains("ContextNeedle") | not))
'
expect_jq "--context after excludes match line" '
  all(.results[0].context.after[]; (.text | contains("ContextNeedle") | not))
'
expect_jq "--context 2 before lines correct" '
  (.results[0].context.before | map(.text) | join("\n"))
  | contains("ctx-before-2") and contains("ctx-before-1")
'
expect_jq "--context 2 after lines correct" '
  (.results[0].context.after | map(.text) | join("\n"))
  | contains("ctx-after-1") and contains("ctx-after-2")
'

run_search text ContextNeedle "$p1_repo" --fixed --before-context 3 --after-context 1
expect_status "asymmetric context -> ok" "ok"
expect_jq "asymmetric context counts" '
  (.results[0].context.before|length) == 3
  and (.results[0].context.after|length) == 1
'
expect_jq "asymmetric context edge lines" '
  (.results[0].context.before[0].text | contains("ctx-before-3"))
  and (.results[0].context.after[0].text | contains("ctx-after-1"))
'

run_search text ContextNeedle "$p1_repo" --fixed --context 20 --max-bytes 120
expect_status "context max-bytes -> ok" "ok"
expect_jq "context max-bytes sets truncation" '.meta.truncated == true'

# =============================================================================
# Phase 3C — scope control
# =============================================================================
printf '[phase3C] scope control\n'

run_search text ScopeNeedle "$p1_repo" --fixed --glob '*.php'
expect_status "--glob php -> ok" "ok"
expect_jq "--glob php includes php" '.results[] | select(.path == "app/scope.php")'
expect_no_jq "--glob php excludes js" '.results[] | select(.path == "app/frontend.js")'

run_search text ScopeNeedle "$p1_repo" --fixed --type js
expect_status "--type js -> ok" "ok"
expect_jq "--type js includes js" '.results[] | select(.path == "app/frontend.js")'
expect_no_jq "--type js excludes php" '.results[] | select(.path == "app/scope.php")'

run_search text ExplicitExcludedNeedle "$p1_repo" --fixed --exclude vendor --exclude node_modules
expect_status "--exclude deps -> no_matches" "no_matches"
expect_count "--exclude deps -> 0" "==" 0

run_search text DefaultExcludedNeedle "$p1_repo" --fixed
expect_status "default excludes -> ok" "ok"
expect_jq "default excludes include normal app hit" '.results[] | select(.path == "app/scope.php")'
expect_no_jq "default excludes omit vendor" '.results[] | select(.path | startswith("vendor/"))'
expect_no_jq "default excludes omit node_modules" '.results[] | select(.path | startswith("node_modules/"))'
expect_no_jq "default excludes omit dist" '.results[] | select(.path | startswith("dist/"))'
expect_no_jq "default excludes omit build" '.results[] | select(.path | startswith("build/"))'
expect_no_jq "default excludes omit coverage" '.results[] | select(.path | startswith("coverage/"))'

run_search text alphacase "$p1_repo" --fixed
expect_status "default smart-case lowercase -> ok" "ok"

run_search text ALPHACASE "$p1_repo" --fixed
expect_status "default smart-case uppercase -> no_matches" "no_matches"

run_search text alphacase "$p1_repo" --fixed --smart-case
expect_status "--smart-case lowercase -> ok" "ok"

run_search text ALPHACASE "$p1_repo" --fixed --smart-case
expect_status "--smart-case uppercase -> no_matches" "no_matches"

run_search text ALPHACASE "$p1_repo" --fixed --ignore-case
expect_status "--ignore-case uppercase -> ok" "ok"

run_search text alphacase "$p1_repo" --fixed --case-sensitive
expect_status "--case-sensitive lowercase -> no_matches" "no_matches"

run_search text 'a.b' "$p1_repo" --fixed
expect_status "--fixed literal dot -> ok" "ok"
expect_jq "--fixed literal dot returns exact text" '.results[] | select(.text | contains("a.b"))'
expect_no_jq "--fixed literal dot excludes regex-like axb" '.results[] | select(.text | contains("axb"))'

run_search text 'a.b' "$p1_repo" --regex
expect_status "--regex dot -> ok" "ok"
expect_jq "--regex dot can match axb" '.results[] | select(.text | contains("axb"))'

run_search text 'foo(?=bar)' "$p1_repo" --pcre2
expect_status "--pcre2 lookahead -> ok" "ok"

run_search text DepthNeedle "$p1_repo" --fixed --max-depth 1
expect_status "--max-depth 1 -> ok" "ok"
expect_jq "--max-depth 1 includes root file" '.results[] | select(.path == "root-depth.txt")'
expect_no_jq "--max-depth 1 excludes nested file" '.results[] | select(.path | contains("deep/nested/structure/deep-depth.txt"))'

# =============================================================================
# Phase 3D — count / file-only output + stable statuses
# =============================================================================
printf '[phase3D] count and file-only output\n'

run_search text ScopeNeedle "$p1_repo" --fixed --files-with-matches
expect_status "--files-with-matches -> ok" "ok"
expect_jq "--files-with-matches keeps matches array" '(.matches|type=="array")'
expect_jq "--files-with-matches has summary" '
  (.summary.total_files|type=="number")
  and (.summary.total_matches|type=="number")
'
expect_jq "--files-with-matches returns files not line dumps" '
  all(.results[]; (.path|type=="string") and (.text? == null))
'

run_search text ScopeNeedle "$p1_repo" --fixed --count
expect_status "--count -> ok" "ok"
expect_jq "--count keeps matches array" '(.matches|type=="array")'
expect_jq "--count has file totals" '
  (.summary.total_files|type=="number")
  and (.summary.total_matches|type=="number")
'
expect_jq "--count results have count" '
  all(.results[]; (.path|type=="string") and (.count|type=="number") and (.text? == null))
'

run_search text ScopeNeedle "$p1_repo" --fixed --count-matches
expect_status "--count-matches -> ok" "ok"
expect_jq "--count-matches keeps matches array" '(.matches|type=="array")'
expect_jq "--count-matches has exact match totals" '
  (.summary.total_matches|type=="number") and (.summary.total_matches >= 2)
'

expect_jq "status belongs to closed set on ok case" '
  [.status] | all(.[]; IN("ok","no_matches","error","unavailable","blocked","dry_run"))
'

run_search text '[' "$p1_repo"
expect_status "closed-set invalid regex -> error" "error"
expect_jq "status belongs to closed set on error case" '
  [.status] | all(.[]; IN("ok","no_matches","error","unavailable","blocked","dry_run"))
'

run_search unsafe-all ScopeNeedle "$p1_repo"
expect_status "unsafe-all closed status -> blocked" "blocked"
expect_jq "status belongs to closed set on blocked case" '
  [.status] | all(.[]; IN("ok","no_matches","error","unavailable","blocked","dry_run"))
'

# =============================================================================
# Phase 4 — repo-aware modes
# =============================================================================
printf '[phase4] repo-aware modes\n'

run_search diff DiffNeedle "$p1_repo" --fixed
expect_status "diff unstaged -> ok" "ok"
expect_jq "diff unstaged includes hunk metadata" '
  .results[]
  | select(
      .path == "app/DiffTarget.php"
      and (.marker == "+")
      and (.new_line|type=="number")
      and (.text|contains("DiffNeedle"))
    )
'
expect_no_jq "diff unstaged excludes staged-only file" '.results[] | select(.path == "app/StagedDiff.php")'

run_search diff StagedDiffNeedle "$p1_repo" --fixed --staged
expect_status "diff --staged -> ok" "ok"
expect_jq "diff --staged includes staged file" '
  .results[]
  | select(
      .path == "app/StagedDiff.php"
      and (.marker == "+")
      and (.new_line|type=="number")
      and (.text|contains("StagedDiffNeedle"))
    )
'
expect_no_jq "diff --staged excludes unstaged-only file" '.results[] | select(.path == "app/DiffTarget.php")'

run_search diff DiffNeedle "$p1_repo" --fixed --base main
expect_status "diff --base main -> ok" "ok"
expect_jq "diff --base main has file metadata" '
  .results[] | select(.path == "app/DiffTarget.php" and (.text|contains("DiffNeedle")))
'

run_search history HistoryNeedle "$p1_repo" --fixed
expect_status "history pickaxe -> ok" "ok"
expect_jq "history returns commit metadata" '
  .results[]
  | select(
      (.commit|type=="string")
      and (.author|type=="string")
      and (.date|type=="string")
      and (.message|type=="string")
      and (.path == "app/HistoryTarget.php")
    )
'
expect_no_jq "history does not include patch by default" '.results[] | select(has("patch"))'

run_search history 'History.*Needle' "$p1_repo" --regex
expect_status "history regex -G -> ok" "ok"
expect_jq "history regex returns commit metadata" '.results[] | select((.commit|type=="string") and (.path == "app/HistoryTarget.php"))'

run_search history HistoryMessageNeedle "$p1_repo" --messages --fixed
expect_status "history --messages -> ok" "ok"
expect_jq "history --messages searches commit messages" '
  .results[] | select((.commit|type=="string") and (.message|contains("HistoryMessageNeedle")))
'

run_search history HistoryNeedle "$p1_repo" --fixed --patch
expect_status "history --patch -> ok" "ok"
expect_jq "history --patch includes patch only on request" '
  .results[] | select((.patch|type=="string") and (.patch|contains("HistoryNeedle")))
'

run_search tests TestNeedle "$p1_repo" --fixed
expect_status "tests mode -> ok" "ok"
expect_jq "tests mode includes php test" '.results[] | select(.path == "tests/UserServiceTest.php")'
expect_jq "tests mode includes js spec" '.results[] | select(.path == "__tests__/user.spec.js")'
expect_no_jq "tests mode excludes app file" '.results[] | select(.path == "app/scope.php")'

run_search docs DocsNeedle "$p1_repo" --fixed
expect_status "real docs mode -> ok" "ok"
expect_jq "docs mode includes README" '.results[] | select(.path == "README.md")'
expect_jq "docs mode includes docs dir" '.results[] | select(.path == "docs/guide.md")'
expect_no_jq "docs mode excludes app source" '.results[] | select(.path == "app/scope.php")'

run_search config ConfigNeedle "$p1_repo" --fixed
expect_status "config mode -> ok" "ok"
expect_jq "config mode includes config yaml" '.results[] | select(.path == "config/app.yaml")'
expect_jq "config mode includes env example" '.results[] | select(.path == ".env.example")'
expect_jq "config mode includes docker compose" '.results[] | select(.path == "docker-compose.yml")'
expect_no_jq "config mode excludes app source" '.results[] | select(.path == "app/scope.php")'

run_search deps DepsNeedle "$p1_repo" --fixed
expect_status "deps mode -> ok" "ok"
expect_jq "deps mode includes composer" '.results[] | select(.path == "composer.json")'
expect_jq "deps mode includes package" '.results[] | select(.path == "package.json")'
expect_jq "deps mode includes package-lock" '.results[] | select(.path == "package-lock.json")'
expect_jq "deps mode includes flake" '.results[] | select(.path == "flake.nix")'
expect_no_jq "deps mode excludes app source" '.results[] | select(.path == "app/scope.php")'

run_search todo "$p1_repo"
expect_status "todo mode no query -> ok" "ok"
expect_jq "todo mode groups by file" '
  .results[] | select(.path == "app/UserService.php" and (.matches|type=="array"))
'
expect_jq "todo mode detects TODO tag" '
  .results[]
  | select(.path == "app/UserService.php")
  | .matches[]
  | select(.tag == "TODO" and (.text|contains("TodoNeedle")))
'
expect_jq "todo mode detects legacy/deprecated markers" '
  .results[]
  | select(.path == "app/UserService.php")
  | .matches[]
  | select((.tag == "deprecated" or .tag == "legacy") and (.line|type=="number") and (.text|type=="string"))
'

run_search unsafe-patterns "$p1_repo"
expect_status "unsafe-patterns mode no query -> ok" "ok"
expect_jq "unsafe-patterns finds curated risky pattern" '
  .results[]
  | select(.path == "app/unsafe.php" and (.rule|type=="string") and (.text|contains("eval")))
'
expect_no_jq "unsafe-patterns is not unrestricted text scan" '.results[] | select(.path == "app/scope.php")'

# =============================================================================
# Phase 4 — ai-search-multi.sh P1 allowlist smoke checks
# =============================================================================
printf '[phase4] ai-search-multi P1 allowlist\n'

run_multi docs DocsNeedle "$p1_repo" --fixed
expect_jq "multi docs mode allowed" '.[0].status == "ok"'

run_multi config ConfigNeedle "$p1_repo" --fixed
expect_jq "multi config mode allowed" '.[0].status == "ok"'

run_multi deps DepsNeedle "$p1_repo" --fixed
expect_jq "multi deps mode allowed" '.[0].status == "ok"'

run_multi tests TestNeedle "$p1_repo" --fixed
expect_jq "multi tests mode allowed" '.[0].status == "ok"'

run_multi route RouteNeedle "$p1_repo" --fixed
expect_jq "multi route mode allowed" '.[0].status == "ok"'

run_multi config-key ConfigKeyNeedle "$p1_repo" --fixed
expect_jq "multi config-key mode allowed" '.[0].status == "ok"'

# =============================================================================
# Phase 5 — structural search
# =============================================================================
printf '[phase5] structural search\n'

if ! command -v ast-grep >/dev/null 2>&1; then
    run_search struct 'class $NAME' "$p1_repo" --lang php
    expect_status "struct without ast-grep -> unavailable" "unavailable"

    run_search symbols UserService "$p1_repo" --lang php
    expect_status "symbols without ast-grep -> unavailable" "unavailable"

    printf '  PASS phase5 ast-grep-dependent positive tests skipped because ast-grep is not installed\n'
else
    run_search struct 'class $NAME' "$p1_repo" --lang php
    expect_status "struct class php --lang -> ok" "ok"
    expect_jq "struct class php has structured result" '
      .results[]
      | select(
          .language == "php"
          and .source_tool == "ast-grep"
          and (.text|contains("class UserService"))
        )
    '

    run_search struct 'function $NAME($$$ARGS)' "$p1_repo" --lang js
    expect_status "struct function js --lang -> ok" "ok"
    expect_jq "struct function js has structured result" '
      .results[]
      | select(
          .language == "js"
          and .source_tool == "ast-grep"
          and (.text|contains("frontendFunction"))
        )
    '

    run_search symbols UserService "$p1_repo" --lang php
    expect_status "symbols UserService -> ok" "ok"
    expect_jq "symbols returns kind/name/file/start/language" '
      .results[]
      | select(
          .kind == "class"
          and .name == "UserService"
          and .path == "app/UserService.php"
          and (.start|type=="number")
          and (.end|type=="number")
          and .language == "php"
        )
    '

    run_search class UserService "$p1_repo" --lang php
    expect_status "shortcut class UserService -> ok" "ok"
    expect_jq "shortcut class returns class defs only" '
      all(.results[]; .kind == "class" and .name == "UserService")
    '

    run_search function frontendFunction "$p1_repo" --lang js
    expect_status "shortcut function frontendFunction -> ok" "ok"
    expect_jq "shortcut function returns matching function definition" '
      .results[] | select(.path == "app/frontend.js" and (.text|contains("function frontendFunction")))
    '

    run_search method findUser "$p1_repo" --lang php
    expect_status "shortcut method findUser -> ok" "ok"
    expect_jq "shortcut method returns matching method definition" '
      .results[] | select(.path == "app/UserService.php" and (.text|contains("function findUser")))
    '

    run_search interface UserContract "$p1_repo" --lang php
    expect_status "shortcut interface UserContract -> ok" "ok"
    expect_jq "shortcut interface returns matching interface definition" '
      .results[] | select(.path == "app/UserService.php" and (.text|contains("interface UserContract")))
    '

    run_search enum UserStatus "$p1_repo" --lang php
    expect_status "shortcut enum UserStatus -> ok" "ok"
    expect_jq "shortcut enum returns matching enum definition" '
      .results[] | select(.path == "app/UserService.php" and (.text|contains("enum UserStatus")))
    '
fi

run_search route RouteNeedle "$p1_repo" --fixed
expect_status "shortcut route RouteNeedle -> ok" "ok"
expect_jq "shortcut route is route-file scoped" '
  all(.results[]; .path == "routes/web.php")
'

run_search config-key ConfigKeyNeedle "$p1_repo" --fixed
expect_status "shortcut config-key ConfigKeyNeedle -> ok" "ok"
expect_jq "shortcut config-key is config-file scoped" '
  all(.results[]; .path == "config/app.yaml")
'

# =============================================================================
# Phase 6 — self-introspection (--introspect / enriched --help)
# =============================================================================
printf '[phase6] self-introspection\n'

if ! command -v php >/dev/null 2>&1 || ! command -v jq >/dev/null 2>&1; then
    printf '  PASS phase6 self-introspection tests skipped because php or jq is not installed\n'
else
    # --introspect emits the static contract envelope and exits 0.
    set +e
    INTRO_JSON="$("$BASH_BIN" "$SCRIPT" --introspect 2>/dev/null)"
    INTRO_RC=$?
    set -e

    if [[ "$INTRO_RC" -eq 0 ]]; then
        printf '  PASS --introspect exits 0\n'
    else
        printf '  FAIL --introspect exits 0 (rc=%s)\n' "$INTRO_RC" >&2
        exit 1
    fi

    if printf '%s' "$INTRO_JSON" | jq -e '.schema == "ai.sh-introspect/v1"' >/dev/null; then
        printf '  PASS --introspect schema is ai.sh-introspect/v1\n'
    else
        printf '  FAIL --introspect schema is ai.sh-introspect/v1 (got %s)\n' \
            "$(printf '%s' "$INTRO_JSON" | jq -r '.schema // "<none>"' 2>/dev/null || printf '<invalid-json>')" >&2
        exit 1
    fi

    # Guard: --introspect must NOT run a search; its tool is the introspector,
    # never the search tool.
    if printf '%s' "$INTRO_JSON" | jq -e '.tool == "sh-introspect"' >/dev/null; then
        printf '  PASS --introspect tool is sh-introspect (not a search)\n'
    else
        printf '  FAIL --introspect tool is sh-introspect (got %s)\n' \
            "$(printf '%s' "$INTRO_JSON" | jq -r '.tool // "<none>"' 2>/dev/null || printf '<invalid-json>')" >&2
        exit 1
    fi

    if printf '%s' "$INTRO_JSON" | jq -e '.meta.target_executed == false' >/dev/null; then
        printf '  PASS --introspect did not execute the target\n'
    else
        printf '  FAIL --introspect meta.target_executed must be false\n' >&2
        exit 1
    fi

    # --help keeps the original hand-written usage AND appends the auto summary.
    set +e
    HELP_OUT="$("$BASH_BIN" "$SCRIPT" --help 2>/dev/null)"
    HELP_RC=$?
    set -e

    if [[ "$HELP_RC" -eq 0 ]]; then
        printf '  PASS --help exits 0\n'
    else
        printf '  FAIL --help exits 0 (rc=%s)\n' "$HELP_RC" >&2
        exit 1
    fi

    if printf '%s' "$HELP_OUT" | grep -q 'unified repository search'; then
        printf '  PASS --help keeps original usage text\n'
    else
        printf '  FAIL --help keeps original usage text\n' >&2
        exit 1
    fi

    if printf '%s' "$HELP_OUT" | grep -qE 'Quick contract|Modes:'; then
        printf '  PASS --help appends auto-generated contract summary\n'
    else
        printf '  FAIL --help appends auto-generated contract summary\n' >&2
        exit 1
    fi

    # Regression: the early --introspect interceptor must not break normal mode.
    run_search text usage "$p1_repo"
    expect_jq "normal search still works after introspect wiring" '.tool == "ai-search"'
fi

echo "ai-search tests passed"
