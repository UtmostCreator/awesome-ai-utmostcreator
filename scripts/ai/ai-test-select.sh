#!/usr/bin/env bash
# Select focused tests for AI-driven changes.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/ai/common.sh
source "$SCRIPT_DIR/common.sh"

usage() {
    cat <<'EOF'
Usage:
  ai-test-select.sh changed
  ai-test-select.sh file PATH
  ai-test-select.sh symbol SYMBOL
  ai-test-select.sh json

Purpose:
  Select focused tests before running broad verification.
EOF
}

mode="${1:-}"
[[ -n "$mode" ]] || {
    usage
    exit 2
}
shift || true

agent_session_init "ai-test-select"
require_bins jq git

repo_files() {
    git ls-files
}

changed_files() {
    {
        git diff --name-only --diff-filter=ACMRT
        git diff --cached --name-only --diff-filter=ACMRT
        git ls-files --others --exclude-standard
    } | sort -u | existing_files_only
}

existing_files_only() {
    while IFS= read -r file; do
        [[ -n "$file" && -f "$file" ]] || continue
        printf '%s\n' "$file"
    done
}

lines_to_json_array() {
    sed '/^$/d' | jq -R . | jq -s .
}

stem_for_file() {
    local file="$1"
    local base
    base="$(basename "$file")"
    printf '%s\n' "${base%.*}"
}

find_tests_for_stem() {
    local stem="${1:-}"

    [[ -n "$stem" ]] || return 0

    {
        repo_files | grep -E "(^|/)(tests?|spec|__tests__)/.*${stem}.*\.(php|js|ts|jsx|tsx|vue)$" || true
        repo_files | grep -E "(^|/)${stem}(Test|Spec)?\.(php|js|ts|jsx|tsx)$" || true
        repo_files | grep -E "(^|/)${stem}\.(test|spec)\.(js|ts|jsx|tsx)$" || true
    } | sort -u
}

find_tests_for_symbol() {
    local symbol="${1:?symbol required}"

    if command -v rg >/dev/null 2>&1; then
        rg -l --hidden \
            -g 'tests/**' \
            -g 'test/**' \
            -g 'spec/**' \
            -g '__tests__/**' \
            -g '*.{php,js,ts,jsx,tsx,vue}' \
            "$symbol" . 2>/dev/null | sed 's#^\./##' | sort -u
    fi
}

command_for_test() {
    local test_file="$1"

    case "$test_file" in
    *.php)
        if [[ -f artisan ]]; then
            printf 'php artisan test %s\n' "$test_file"
        elif [[ -x vendor/bin/pest ]]; then
            printf 'vendor/bin/pest %s\n' "$test_file"
        elif [[ -x vendor/bin/phpunit ]]; then
            printf 'vendor/bin/phpunit %s\n' "$test_file"
        fi
        ;;
    *.js | *.ts | *.jsx | *.tsx | *.vue)
        if [[ -f pnpm-lock.yaml ]]; then
            printf 'pnpm test -- %s\n' "$test_file"
        elif [[ -f package.json ]]; then
            printf 'npm test -- %s\n' "$test_file"
        fi
        ;;
    esac
}

emit_json() {
    local files_json="$1"
    local tests_json="$2"
    local commands_json="$3"

    jq -n \
        --argjson files "$files_json" \
        --argjson tests "$tests_json" \
        --argjson commands "$commands_json" \
        '{
          input_files: $files,
          candidate_tests: $tests,
          recommended_commands: $commands
        }'
}

select_for_files() {
    local input_files=("$@")
    local tests=()
    local commands=()
    local file
    local stem
    local test_file

    for file in "${input_files[@]+${input_files[@]}}"; do
        [[ -n "$file" ]] || continue
        stem="$(stem_for_file "$file")"

        while IFS= read -r test_file; do
            [[ -n "$test_file" ]] || continue
            tests+=("$test_file")
        done < <(find_tests_for_stem "$stem")
    done

    mapfile -t tests < <(printf '%s\n' "${tests[@]+${tests[@]}}" | sed '/^$/d' | sort -u)

    for test_file in "${tests[@]+${tests[@]}}"; do
        while IFS= read -r command; do
            [[ -n "$command" ]] || continue
            commands+=("$command")
        done < <(command_for_test "$test_file")
    done

    mapfile -t commands < <(printf '%s\n' "${commands[@]+${commands[@]}}" | sed '/^$/d' | sort -u)

    emit_json \
        "$(printf '%s\n' "${input_files[@]+${input_files[@]}}" | lines_to_json_array)" \
        "$(printf '%s\n' "${tests[@]+${tests[@]}}" | lines_to_json_array)" \
        "$(printf '%s\n' "${commands[@]+${commands[@]}}" | lines_to_json_array)"
}

case "$mode" in
changed)
    mapfile -t files < <(changed_files)
    select_for_files "${files[@]+${files[@]}}"
    ;;

file)
    file="${1:?file required}"
    select_for_files "$file"
    ;;

symbol)
    symbol="${1:?symbol required}"
    mapfile -t tests < <(find_tests_for_symbol "$symbol")
    commands=()

    for test_file in "${tests[@]+${tests[@]}}"; do
        while IFS= read -r command; do
            [[ -n "$command" ]] || continue
            commands+=("$command")
        done < <(command_for_test "$test_file")
    done

    mapfile -t commands < <(printf '%s\n' "${commands[@]+${commands[@]}}" | sed '/^$/d' | sort -u)

    emit_json \
        "$(jq -n --arg symbol "$symbol" '[$symbol]')" \
        "$(printf '%s\n' "${tests[@]+${tests[@]}}" | lines_to_json_array)" \
        "$(printf '%s\n' "${commands[@]+${commands[@]}}" | lines_to_json_array)"
    ;;

json)
    mapfile -t files < <(changed_files)
    select_for_files "${files[@]+${files[@]}}"
    ;;

--help | -h)
    usage
    ;;

*)
    usage
    die "unknown mode: $mode"
    ;;
esac

log_json "test-select.query" "$(jq -cn --arg mode "$mode" '{mode:$mode}')"
