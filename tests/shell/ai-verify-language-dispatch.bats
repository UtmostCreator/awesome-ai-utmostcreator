#!/usr/bin/env bats
# Tests for the per-language dispatcher
# (scripts/ai/internal/ai-verify/53-language-dispatch.sh), the `--language`
# flag on scripts/ai/ai-verify.sh, and the five thin wrapper scripts it powers.
#
# Part of docs/tickets/arch-todo-safe-language-verify-scripts-20260706-003959
# (§8-P2). Hermetic: every test runs the real ai-verify.sh/wrapper scripts
# against a throwaway git fixture repo with PATH-stubbed pnpm and vendor/bin
# tool stubs, so no real tool is ever invoked.

REPO_ROOT="$(cd "$(dirname "$BATS_TEST_FILENAME")/../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/ai-verify.sh"
WRAPPERS_DIR="$REPO_ROOT/scripts/ai"

setup() {
    TMP_REPO="$(mktemp -d)"
    STUB_BIN="$(mktemp -d)"
    git -C "$TMP_REPO" init --quiet
    git -C "$TMP_REPO" config user.email test@example.com
    git -C "$TMP_REPO" config user.name Tester
}

teardown() {
    rm -rf "$TMP_REPO" "$STUB_BIN" 2>/dev/null || true
}

write_package_json() {
    printf '%s' "$1" >"$TMP_REPO/package.json"
}

stub_pnpm() {
    cat >"$STUB_BIN/pnpm" <<'EOF'
#!/usr/bin/env bash
echo "STUB-PNPM-RAN $*"
exit 0
EOF
    chmod +x "$STUB_BIN/pnpm"
}

stub_php_tool() {
    local name="$1"
    mkdir -p "$TMP_REPO/vendor/bin"
    cat >"$TMP_REPO/vendor/bin/$name" <<EOF
#!/usr/bin/env bash
echo "STUB-${name^^}-RAN \$*"
exit 0
EOF
    chmod +x "$TMP_REPO/vendor/bin/$name"
}

run_language() {
    local lang="$1"
    run env PATH="$STUB_BIN:$PATH" bash "$SCRIPT" --language "$lang" "$TMP_REPO"
}

run_wrapper() {
    local lang="$1"
    run env PATH="$STUB_BIN:$PATH" bash "$WRAPPERS_DIR/ai-verify-$lang.sh" "$TMP_REPO"
}

# --- CRITICAL: AI_VERIFY_SCOPE unset (defaults to "ai") must still find
# files via the ai->changed translation, for at least two languages ---------

@test "php: default scope (AI_VERIFY_SCOPE unset) finds an untracked php file and runs pint" {
    stub_php_tool pint
    printf '<?php\necho 1;\n' >"$TMP_REPO/new.php"
    run_language php
    [ "$status" -eq 0 ]
    [[ "$output" == *"==> language:php"* ]]
    [[ "$output" == *"pint check (1 file(s))"* ]]
    [[ "$output" == *"STUB-PINT-RAN --test new.php"* ]]
    [[ "$output" == *"==> done"* ]]
}

@test "js: default scope (AI_VERIFY_SCOPE unset) finds an untracked js file and runs eslint" {
    stub_pnpm
    write_package_json '{"devDependencies":{"eslint":"^9.0.0"}}'
    printf 'console.log(1)\n' >"$TMP_REPO/new.js"
    run_language js
    [ "$status" -eq 0 ]
    [[ "$output" == *"==> language:js"* ]]
    [[ "$output" == *"eslint (1 file(s))"* ]]
    [[ "$output" == *"STUB-PNPM-RAN exec eslint new.js"* ]]
}

# --- each language runs ONLY its own tool subset ----------------------------

@test "ai_verify_language php never invokes eslint/biome even when JS tooling is present" {
    stub_php_tool pint
    stub_pnpm
    write_package_json '{"devDependencies":{"eslint":"^9.0.0","@biomejs/biome":"^1.0.0"}}'
    printf '<?php\necho 1;\n' >"$TMP_REPO/new.php"
    printf 'console.log(1)\n' >"$TMP_REPO/new.js"
    run_language php
    [ "$status" -eq 0 ]
    [[ "$output" == *"pint check"* ]]
    [[ "$output" != *"eslint"* ]]
    [[ "$output" != *"biome"* ]]
}

@test "ai_verify_language js never invokes pint/phpstan even when PHP tooling is present" {
    stub_php_tool pint
    stub_php_tool phpstan
    stub_pnpm
    write_package_json '{"devDependencies":{"eslint":"^9.0.0"}}'
    printf '<?php\necho 1;\n' >"$TMP_REPO/new.php"
    printf 'console.log(1)\n' >"$TMP_REPO/new.js"
    run_language js
    [ "$status" -eq 0 ]
    [[ "$output" == *"eslint"* ]]
    [[ "$output" != *"pint"* ]]
    [[ "$output" != *"phpstan"* ]]
}

# --- clean skip (exit 0, no failures) when tools/deps absent + no files -----

@test "php: skips cleanly (exit 0) when no php files and no tools" {
    run_language php
    [ "$status" -eq 0 ]
    [[ "$output" == *"No changed PHP files in scope"* ]]
    [[ "$output" == *"==> done"* ]]
}

@test "js: skips cleanly (exit 0) when no js files and no package.json" {
    run_language js
    [ "$status" -eq 0 ]
    [[ "$output" == *"No changed JS files in scope"* ]]
}

@test "ts: skips cleanly (exit 0) when no ts files" {
    run_language ts
    [ "$status" -eq 0 ]
    [[ "$output" == *"No changed TS files in scope"* ]]
}

@test "vue: skips cleanly (exit 0) when no vue files" {
    run_language vue
    [ "$status" -eq 0 ]
    [[ "$output" == *"No changed Vue files in scope"* ]]
}

@test "html: skips cleanly (exit 0) when no html files" {
    run_language html
    [ "$status" -eq 0 ]
    [[ "$output" == *"No changed HTML files in scope"* ]]
}

# --- html detection order: biome -> htmlhint -> clean skip ------------------

@test "html: biome runs first when both biome and htmlhint are available" {
    stub_pnpm
    write_package_json '{"devDependencies":{"@biomejs/biome":"^1.0.0","htmlhint":"^1.0.0"}}'
    printf '<div></div>\n' >"$TMP_REPO/x.html"
    run_language html
    [ "$status" -eq 0 ]
    [[ "$output" == *"biome check (1 file(s))"* ]]
    [[ "$output" != *"htmlhint"* ]]
}

@test "html: htmlhint runs when biome is unavailable" {
    stub_pnpm
    write_package_json '{"devDependencies":{"htmlhint":"^1.0.0"}}'
    printf '<div></div>\n' >"$TMP_REPO/x.html"
    run_language html
    [ "$status" -eq 0 ]
    [[ "$output" == *"htmlhint (1 file(s))"* ]]
    [[ "$output" != *"biome check"* ]]
}

@test "html: clean skip when neither biome nor htmlhint is available" {
    stub_pnpm
    write_package_json '{"devDependencies":{"lodash":"^4.0.0"}}'
    printf '<div></div>\n' >"$TMP_REPO/x.html"
    run_language html
    [ "$status" -eq 0 ]
    [[ "$output" == *"No configured HTML verifier found (biome/htmlhint); skipping HTML."* ]]
}

# --- wrapper scripts: <=10 lines, exec-only, no tool logic ------------------

@test "each wrapper script stays small (introspect-shim pattern, well under the 550-line ceiling)" {
    # Originally targeted <=10 lines for a bare `exec ai-verify.sh --language <lang>
    # "$@"` body, but ShIntrospectIndexTest::testEveryAiScriptSupportsIntrospect
    # requires every top-level scripts/ai/*.sh to answer `--introspect` with a
    # static ai.sh-introspect/v1 envelope for the CANONICAL implementation (never
    # the wrapper's own 3-line body). Satisfying that repo-wide invariant requires
    # the same delegating-shim pattern already used by
    # scripts/ai/bin/verify/ai-verify.sh (~22 lines here). The line ceiling below
    # (25) still proves "thin wrapper, zero tool logic" (see the two tests below),
    # just not the original bare-exec byte count.
    for lang in php js ts vue html; do
        run wc -l <"$WRAPPERS_DIR/ai-verify-$lang.sh"
        [ "$status" -eq 0 ]
        [ "$output" -le 25 ]
    done
}

@test "each wrapper script contains no tool-name literal other than the language keyword" {
    for lang in php js ts vue html; do
        run grep -Ei 'pint|phpstan|psalm|eslint|biome|htmlhint|vue-tsc|nuxi|nuxt|tsc|knip' \
            "$WRAPPERS_DIR/ai-verify-$lang.sh"
        [ "$status" -ne 0 ]
        [ -z "$output" ]
    done
}

@test "each wrapper uses exec delegation only (never source)" {
    for lang in php js ts vue html; do
        run grep -n '^exec ' "$WRAPPERS_DIR/ai-verify-$lang.sh"
        [ "$status" -eq 0 ]
        run grep -nw source "$WRAPPERS_DIR/ai-verify-$lang.sh"
        [ "$status" -ne 0 ]
    done
}

# --- wrappers reach ai_verify_language end-to-end ---------------------------

@test "each wrapper reaches ai_verify_language via the ==> language:<lang> marker" {
    for lang in php js ts vue html; do
        run_wrapper "$lang"
        [ "$status" -eq 0 ]
        [[ "$output" == *"==> language:$lang"* ]]
    done
}

# --- unknown language dies loudly -------------------------------------------

@test "ai-verify.sh --language <unknown> dies loudly" {
    run bash "$SCRIPT" --language cobol "$TMP_REPO"
    [ "$status" -ne 0 ]
    [[ "$output" == *"unknown language: cobol"* ]]
}

# --- --language flag order independence -------------------------------------

@test "--language works before or after the root path argument" {
    stub_php_tool pint
    printf '<?php\necho 1;\n' >"$TMP_REPO/new.php"
    run env PATH="$STUB_BIN:$PATH" bash "$SCRIPT" --language php "$TMP_REPO"
    [ "$status" -eq 0 ]
    [[ "$output" == *"==> language:php"* ]]
    run env PATH="$STUB_BIN:$PATH" bash "$SCRIPT" "$TMP_REPO" --language php
    [ "$status" -eq 0 ]
    [[ "$output" == *"==> language:php"* ]]
}

# --- existing full-pipeline behavior unchanged (no --language) -------------

@test "AI_VERIFY_TEST_MODE full pipeline still runs its existing stub shape without --language" {
    run env AI_VERIFY_TEST_MODE=1 VERIFY_LINECOUNT=0 VERIFY_PLAN_STATUS=0 \
        bash "$SCRIPT" "$TMP_REPO"
    [[ "$output" == *"==> repository"* ]]
    [[ "$output" == *"==> shellcheck"* ]]
    [[ "$output" == *"==> composer"* ]]
    [[ "$output" == *"==> done"* ]]
    [ "$status" -eq 0 ]
}
