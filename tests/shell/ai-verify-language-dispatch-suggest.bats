#!/usr/bin/env bats
# Tests for the §8-P4/P5 additions to the per-language dispatcher
# (scripts/ai/internal/ai-verify/53-language-dispatch.sh): Rector dry-run,
# VERIFY_OUTPUT_FORMAT=json for phpstan/psalm + report files, per-language
# composer validate/audit, AI_VERIFY_MODE=suggest eslint diagnostics, and
# `vitest run --changed`.
#
# Part of docs/tickets/arch-todo-safe-language-verify-scripts-20260706-003959
# (§8-P4/P5). Kept separate from tests/shell/ai-verify-language-dispatch.bats
# (the §8-P2 suite) to avoid growing that file past a reasonable size, per the
# task instructions. Hermetic: every test runs the real ai-verify.sh against a
# throwaway git fixture repo with PATH-stubbed pnpm/composer and vendor/bin
# tool stubs, so no real tool is ever invoked.

REPO_ROOT="$(cd "$(dirname "$BATS_TEST_FILENAME")/../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/ai-verify.sh"

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

stub_composer() {
    cat >"$STUB_BIN/composer" <<'EOF'
#!/usr/bin/env bash
echo "STUB-COMPOSER-RAN $*"
exit 0
EOF
    chmod +x "$STUB_BIN/composer"
}

# Plain pnpm stub: echoes its args and always succeeds. Used by the vitest and
# composer-unrelated tests where no special-cased branch is needed.
stub_pnpm_plain() {
    cat >"$STUB_BIN/pnpm" <<'EOF'
#!/usr/bin/env bash
echo "STUB-PNPM-RAN $*"
exit 0
EOF
    chmod +x "$STUB_BIN/pnpm"
}

# pnpm stub that special-cases `exec eslint ... --fix-dry-run ...` (the
# AI_VERIFY_MODE=suggest invocation): it prints a JSON findings payload and
# exits 1, so the advisory-only, never-fails contract can be proven. Every
# other invocation (including the plain `exec eslint <files>` check-mode
# invocation, which has no --fix-dry-run flag) falls through to the normal
# success stub, so default check-mode behavior stays provably unchanged.
stub_pnpm_eslint_suggest() {
    cat >"$STUB_BIN/pnpm" <<'EOF'
#!/usr/bin/env bash
if [[ "$1" == "exec" && "$2" == "eslint" && "$*" == *"--fix-dry-run"* ]]; then
    echo '[{"filePath":"new.js","messages":[{"ruleId":"no-console"}]}]'
    exit 1
fi
echo "STUB-PNPM-RAN $*"
exit 0
EOF
    chmod +x "$STUB_BIN/pnpm"
}

run_language() {
    local lang="$1"
    run env PATH="$STUB_BIN:$PATH" bash "$SCRIPT" --language "$lang" "$TMP_REPO"
}

# --- Rector: process --dry-run only, never bare process (apply) ------------

@test "php: rector runs process --dry-run (never bare process/apply)" {
    stub_php_tool rector
    printf '<?php\necho 1;\n' >"$TMP_REPO/new.php"
    run_language php
    [ "$status" -eq 0 ]
    [[ "$output" == *"rector dry-run (1 file(s))"* ]]
    [[ "$output" == *"STUB-RECTOR-RAN process --dry-run new.php"* ]]
}

@test "php: rector is skipped cleanly when vendor/bin/rector is absent" {
    stub_php_tool pint
    printf '<?php\necho 1;\n' >"$TMP_REPO/new.php"
    run_language php
    [ "$status" -eq 0 ]
    [[ "$output" != *"rector"* ]]
}

# --- VERIFY_OUTPUT_FORMAT=json: phpstan/psalm flags + report files ----------

@test "VERIFY_OUTPUT_FORMAT=json: phpstan/psalm get json flags and write report files" {
    stub_php_tool phpstan
    stub_php_tool psalm
    printf '<?php\necho 1;\n' >"$TMP_REPO/new.php"
    run env PATH="$STUB_BIN:$PATH" VERIFY_OUTPUT_FORMAT=json bash "$SCRIPT" --language php "$TMP_REPO"
    [ "$status" -eq 0 ]
    [[ "$output" == *"STUB-PHPSTAN-RAN analyse --memory-limit=1G --error-format=json new.php"* ]]
    [[ "$output" == *"STUB-PSALM-RAN --no-cache --output-format=json new.php"* ]]
    [ -f "$TMP_REPO/.ai-logs/verify/phpstan.json" ]
    [ -f "$TMP_REPO/.ai-logs/verify/psalm.json" ]
    run cat "$TMP_REPO/.ai-logs/verify/phpstan.json"
    [[ "$output" == *"STUB-PHPSTAN-RAN"* ]]
    run cat "$TMP_REPO/.ai-logs/verify/psalm.json"
    [[ "$output" == *"STUB-PSALM-RAN"* ]]
}

@test "VERIFY_OUTPUT_FORMAT default (table/unset): no json flags, no report files (regression)" {
    stub_php_tool phpstan
    stub_php_tool psalm
    printf '<?php\necho 1;\n' >"$TMP_REPO/new.php"
    run_language php
    [ "$status" -eq 0 ]
    [[ "$output" == *"STUB-PHPSTAN-RAN analyse --memory-limit=1G new.php"* ]]
    [[ "$output" != *"--error-format=json"* ]]
    [[ "$output" != *"--output-format=json"* ]]
    [ ! -f "$TMP_REPO/.ai-logs/verify/phpstan.json" ]
    [ ! -f "$TMP_REPO/.ai-logs/verify/psalm.json" ]
}

# --- per-language composer validate/audit -----------------------------------

@test "php: composer validate/audit run when composer.json is in the scoped changed set" {
    stub_php_tool pint
    stub_composer
    printf '<?php\necho 1;\n' >"$TMP_REPO/new.php"
    printf '{}' >"$TMP_REPO/composer.json"
    run_language php
    [ "$status" -eq 0 ]
    [[ "$output" == *"composer validate --strict"* ]]
    [[ "$output" == *"STUB-COMPOSER-RAN validate --strict"* ]]
    [[ "$output" == *"composer audit"* ]]
    [[ "$output" == *"STUB-COMPOSER-RAN audit"* ]]
}

@test "php: composer validate/audit is skipped when composer.json is unchanged in scope" {
    stub_php_tool pint
    stub_composer
    printf '{}' >"$TMP_REPO/composer.json"
    git -C "$TMP_REPO" add composer.json
    git -C "$TMP_REPO" commit --quiet -m "add composer.json"
    printf '<?php\necho 1;\n' >"$TMP_REPO/new.php"
    run_language php
    [ "$status" -eq 0 ]
    [[ "$output" != *"composer validate"* ]]
    [[ "$output" != *"STUB-COMPOSER-RAN"* ]]
}

# Builds a PATH-equivalent directory of symlinks to every executable
# currently reachable on $PATH, EXCEPT `composer`, mirroring the
# path_hiding_tool idiom in tests/shell/ai-verify-tool-policy.bats. Used to
# deterministically prove the `command -v composer` guard, regardless of
# whether this host happens to have composer installed for its own dev use.
hide_composer() {
    local hide_dir="$1" d f name
    mkdir -p "$hide_dir"
    while IFS= read -r d; do
        [[ -d "$d" ]] || continue
        for f in "$d"/*; do
            [[ -x "$f" && -f "$f" ]] || continue
            name="$(basename "$f")"
            [[ "$name" == "composer" ]] && continue
            [[ -e "$hide_dir/$name" ]] && continue
            ln -sf "$f" "$hide_dir/$name" 2>/dev/null || true
        done
    done < <(printf '%s' "$PATH" | tr ':' '\n')
}

@test "php: composer validate/audit is skipped when composer is not on PATH" {
    HIDE_DIR="$(mktemp -d)"
    hide_composer "$HIDE_DIR"
    printf '{}' >"$TMP_REPO/composer.json"
    printf '<?php\necho 1;\n' >"$TMP_REPO/new.php"
    run env PATH="$HIDE_DIR" bash "$SCRIPT" --language php "$TMP_REPO"
    rm -rf "$HIDE_DIR"
    [ "$status" -eq 0 ]
    [[ "$output" != *"STUB-COMPOSER-RAN"* ]]
    [[ "$output" != *"composer validate"* ]]
}

# --- AI_VERIFY_MODE=suggest: advisory-only eslint --fix-dry-run diagnostics -

@test "AI_VERIFY_MODE=suggest: eslint --fix-dry-run is advisory-only and never fails the run" {
    stub_pnpm_eslint_suggest
    write_package_json '{"devDependencies":{"eslint":"^9.0.0"}}'
    printf 'console.log(1)\n' >"$TMP_REPO/new.js"
    run env PATH="$STUB_BIN:$PATH" AI_VERIFY_MODE=suggest bash "$SCRIPT" --language js "$TMP_REPO"
    [ "$status" -eq 0 ]
    [[ "$output" == *"eslint --fix-dry-run (suggest mode, 1 file(s))"* ]]
    [ -f "$TMP_REPO/.ai-logs/verify/eslint-suggest.json" ]
    run cat "$TMP_REPO/.ai-logs/verify/eslint-suggest.json"
    [[ "$output" == *"no-console"* ]]
}

@test "AI_VERIFY_MODE default (check): plain eslint invocation unchanged, no suggest report" {
    stub_pnpm_eslint_suggest
    write_package_json '{"devDependencies":{"eslint":"^9.0.0"}}'
    printf 'console.log(1)\n' >"$TMP_REPO/new.js"
    run_language js
    [ "$status" -eq 0 ]
    [[ "$output" == *"eslint (1 file(s))"* ]]
    [[ "$output" == *"STUB-PNPM-RAN exec eslint new.js"* ]]
    [[ "$output" != *"suggest mode"* ]]
    [ ! -f "$TMP_REPO/.ai-logs/verify/eslint-suggest.json" ]
}

# --- vitest run --changed: reachable via js/ts/vue shared lint dispatch ----

@test "js: vitest run --changed runs when vitest dependency is declared" {
    stub_pnpm_plain
    write_package_json '{"devDependencies":{"vitest":"^2.0.0"}}'
    printf 'console.log(1)\n' >"$TMP_REPO/new.js"
    run_language js
    [ "$status" -eq 0 ]
    [[ "$output" == *"pnpm exec vitest run --changed"* ]]
    [[ "$output" == *"STUB-PNPM-RAN exec vitest run --changed"* ]]
}

@test "js: vitest run --changed is NOT invoked when the vitest dependency is absent" {
    stub_pnpm_plain
    write_package_json '{"devDependencies":{"lodash":"^4.0.0"}}'
    printf 'console.log(1)\n' >"$TMP_REPO/new.js"
    run_language js
    [ "$status" -eq 0 ]
    [[ "$output" != *"vitest"* ]]
}

@test "ts: vitest run --changed also runs via the shared lint dispatch" {
    stub_pnpm_plain
    write_package_json '{"devDependencies":{"vitest":"^2.0.0"}}'
    printf 'const x: number = 1;\n' >"$TMP_REPO/new.ts"
    run_language ts
    [ "$status" -eq 0 ]
    [[ "$output" == *"pnpm exec vitest run --changed"* ]]
}

# --- nuxi typecheck: locks in existing empty-file-list early-return ---------

@test "vue: nuxi typecheck only fires for a non-empty changed-file list (locks in existing behavior)" {
    stub_pnpm_plain
    write_package_json '{"devDependencies":{"nuxt":"^3.0.0"}}'
    run_language vue
    [ "$status" -eq 0 ]
    [[ "$output" == *"No changed Vue files in scope"* ]]
    [[ "$output" != *"nuxi typecheck"* ]]

    printf '<template><div/></template>\n' >"$TMP_REPO/new.vue"
    run_language vue
    [ "$status" -eq 0 ]
    [[ "$output" == *"pnpm exec nuxi typecheck"* ]]
}
