#!/usr/bin/env bash
# Tests for scripts/ai/run-repomix-file.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/run-repomix-file.sh"
cd "$REPO_ROOT"
BASH_BIN="${BASH_BIN:-$(command -v bash)}"

PASS=0 FAIL=0 SKIP=0
run_test() {
    local name="$1"
    shift
    local _rc=0
    "$@" >/dev/null 2>&1 || _rc=$?
    if ((_rc == 0)); then
        PASS=$((PASS + 1))
        printf '  \033[0;32m✓\033[0m %s\n' "$name"
    else
        FAIL=$((FAIL + 1))
        printf '  \033[0;31m✗\033[0m %s\n' "$name"
    fi
}

printf 'run-repomix-file.sh\n'

test_help() {
    "$BASH_BIN" "$SCRIPT" --help 2>&1 | grep -q 'Usage'
}
run_test "help flag works" test_help

make_fake_repomix() {
    local bin_dir="$1"

    mkdir -p "$bin_dir"
    cat >"$bin_dir/repomix" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail

OUTPUT=''

while (($# > 0)); do
    case "$1" in
    --output)
        OUTPUT="$2"
        shift 2
        ;;
    --style | --split-output | --include-logs-count)
        shift 2
        ;;
    --stdin | --compress | --include-logs | --include-diffs)
        shift
        ;;
    *)
        shift
        ;;
    esac
done

[[ -n "$OUTPUT" ]]
mkdir -p "$(dirname "$OUTPUT")"
cat >"$OUTPUT"
EOF
    chmod +x "$bin_dir/repomix"
}

test_default_output_uses_relative_repo_path() {
    local tmp repo bin_dir expected output_text manifest_text

    tmp="$(mktemp -d "${TMPDIR:-/tmp}/repomix-file-test.XXXXXX")"
    repo="$tmp/repo"
    bin_dir="$tmp/bin"
    mkdir -p "$repo/docs/ai/shared/nuxt"
    printf '{"cache":true}\n' >"$repo/docs/ai/shared/nuxt/nuxt-cache.json"
    make_fake_repomix "$bin_dir"

    PATH="$bin_dir:$PATH" "$BASH_BIN" "$SCRIPT" "$repo" "$repo/docs/ai/shared/nuxt/nuxt-cache.json"

    expected="$repo/.repomix-context/single-file/docs__ai__shared__nuxt__nuxt-cache.json.xml"
    [[ -f "$expected" ]]
    output_text="$(tr -d '\r\n' <"$expected")"
    [[ "$output_text" == "docs/ai/shared/nuxt/nuxt-cache.json" ]]

    manifest_text="$(jq -r '.file' "$repo/.repomix-context/single-file/run-manifest.json")"
    [[ "$manifest_text" == "docs/ai/shared/nuxt/nuxt-cache.json" ]]

    rm -rf "$tmp"
}
run_test "packs exact file with default output" test_default_output_uses_relative_repo_path

test_custom_output_and_style() {
    local tmp repo bin_dir expected output_text

    tmp="$(mktemp -d "${TMPDIR:-/tmp}/repomix-file-test.XXXXXX")"
    repo="$tmp/repo"
    bin_dir="$tmp/bin"
    mkdir -p "$repo/docs/ai/shared/nuxt"
    printf '{"cache":true}\n' >"$repo/docs/ai/shared/nuxt/nuxt-cache.json"
    make_fake_repomix "$bin_dir"

    PATH="$bin_dir:$PATH" "$BASH_BIN" "$SCRIPT" "$repo" "docs/ai/shared/nuxt/nuxt-cache.json" --style json --output custom/nuxt-cache.json --no-compress

    expected="$repo/custom/nuxt-cache.json"
    [[ -f "$expected" ]]
    output_text="$(tr -d '\r\n' <"$expected")"
    [[ "$output_text" == "docs/ai/shared/nuxt/nuxt-cache.json" ]]

    rm -rf "$tmp"
}
run_test "supports custom output and style" test_custom_output_and_style

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"
((FAIL == 0)) && printf '\033[0;32mPASSED\033[0m\n' || { printf '\033[0;31mFAILED\033[0m\n'; exit 1; }