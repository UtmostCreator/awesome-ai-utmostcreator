#!/usr/bin/env bash
# Project task discovery wrapper for AI agents.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/ai/common.sh
source "$SCRIPT_DIR/common.sh"

usage() {
    cat <<'EOF'
Usage:
  ai-task.sh list
  ai-task.sh verify
  ai-task.sh test
  ai-task.sh lint
  ai-task.sh typecheck
  ai-task.sh json

Purpose:
  Discover project-defined commands before guessing raw commands.
EOF
}

mode="${1:-list}"
shift || true

agent_session_init "ai-task"

has_package_script() {
    local name="${1:?script name required}"
    [[ -f package.json ]] || return 1
    command -v jq >/dev/null 2>&1 || return 1
    jq -e --arg name "$name" '.scripts[$name] // empty' package.json >/dev/null 2>&1
}

package_manager() {
    if [[ -f package.json ]] && command -v jq >/dev/null 2>&1; then
        declared="$(jq -r '.packageManager // empty' package.json)"
        if [[ -n "$declared" ]]; then
            printf '%s\n' "${declared%%@*}"
            return 0
        fi
    fi

    if [[ -f pnpm-lock.yaml ]]; then
        printf 'pnpm\n'
    elif [[ -f package-lock.json ]]; then
        printf 'npm\n'
    elif [[ -f yarn.lock ]]; then
        printf 'yarn\n'
    elif [[ -f package.json ]]; then
        printf 'npm\n'
    else
        printf 'unknown\n'
    fi
}

composer_scripts_json() {
    if [[ -f composer.json ]] && command -v jq >/dev/null 2>&1; then
        jq '.scripts // {}' composer.json
    else
        printf '{}\n'
    fi
}

package_scripts_json() {
    if [[ -f package.json ]] && command -v jq >/dev/null 2>&1; then
        jq '.scripts // {}' package.json
    else
        printf '{}\n'
    fi
}

just_tasks_json() {
    if command -v just >/dev/null 2>&1 && [[ -f justfile || -f Justfile ]]; then
        just --summary 2>/dev/null | tr ' ' '\n' | sed '/^$/d' | jq -R . | jq -s .
    else
        printf '[]\n'
    fi
}

make_tasks_json() {
    if [[ -f Makefile ]]; then
        awk -F: '/^[A-Za-z0-9_.-]+:/ {print $1}' Makefile | sort -u | jq -R . | jq -s .
    else
        printf '[]\n'
    fi
}

taskfile_tasks_json() {
    if [[ -f Taskfile.yml || -f Taskfile.yaml ]] && command -v yq >/dev/null 2>&1; then
        yq -o=json '.tasks | keys' Taskfile.yml 2>/dev/null ||
            yq -o=json '.tasks | keys' Taskfile.yaml 2>/dev/null ||
            printf '[]\n'
    else
        printf '[]\n'
    fi
}

build_inventory() {
    jq -n \
        --arg package_manager "$(package_manager)" \
        --argjson package_scripts "$(package_scripts_json)" \
        --argjson composer_scripts "$(composer_scripts_json)" \
        --argjson just_tasks "$(just_tasks_json)" \
        --argjson make_tasks "$(make_tasks_json)" \
        --argjson taskfile_tasks "$(taskfile_tasks_json)" \
        '{
          package_manager: $package_manager,
          package_scripts: $package_scripts,
          composer_scripts: $composer_scripts,
          just_tasks: $just_tasks,
          make_tasks: $make_tasks,
          taskfile_tasks: $taskfile_tasks
        }'
}

recommend_command() {
    local intent="${1:?intent required}"
    local pm
    pm="$(package_manager)"
    if [[ "$pm" == "unknown" ]]; then
        pm=""
    fi

    case "$intent" in
    verify)
        if command -v just >/dev/null 2>&1 && just --summary 2>/dev/null | grep -qw verify; then
            printf 'just verify\n'
        elif has_package_script verify; then
            if [[ -n "$pm" ]]; then
                printf '%s run verify\n' "$pm"
            else
                printf 'scripts/ai/ai-verify.sh .\n'
            fi
        elif [[ -f composer.json ]] && jq -e '.scripts.verify // empty' composer.json >/dev/null 2>&1; then
            printf 'composer run-script verify\n'
        else
            printf 'scripts/ai/ai-verify.sh .\n'
        fi
        ;;

    test)
        if has_package_script test; then
            printf '%s test\n' "$pm"
        elif [[ -f composer.json ]] && [[ -x vendor/bin/phpunit ]]; then
            printf 'vendor/bin/phpunit\n'
        elif [[ -f composer.json ]] && [[ -x vendor/bin/pest ]]; then
            printf 'vendor/bin/pest\n'
        else
            printf 'scripts/ai/ai-verify.sh .\n'
        fi
        ;;

    lint)
        if has_package_script lint; then
            printf '%s run lint\n' "$pm"
        elif [[ -f composer.json ]] && [[ -x vendor/bin/pint ]]; then
            printf 'vendor/bin/pint --test\n'
        else
            printf 'scripts/ai/ai-verify.sh .\n'
        fi
        ;;

    typecheck)
        if has_package_script typecheck; then
            printf '%s run typecheck\n' "$pm"
        elif [[ -f tsconfig.json ]]; then
            printf '%s exec tsc --noEmit\n' "$pm"
        elif [[ -f composer.json ]] && [[ -x vendor/bin/phpstan ]]; then
            printf 'vendor/bin/phpstan analyse\n'
        else
            printf 'scripts/ai/ai-verify.sh .\n'
        fi
        ;;

    *)
        die "unknown recommendation intent: $intent"
        ;;
    esac
}

case "$mode" in
list)
    build_inventory
    ;;

json)
    build_inventory
    ;;

verify | test | lint | typecheck)
    recommend_command "$mode"
    ;;

--help | -h)
    usage
    ;;

*)
    usage
    die "unknown mode: $mode"
    ;;
esac

log_json "task.query" "$(jq -cn --arg mode "$mode" '{mode:$mode}')"
