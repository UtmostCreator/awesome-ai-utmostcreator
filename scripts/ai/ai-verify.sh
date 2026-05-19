#!/usr/bin/env bash
# Project-aware verification gate for AI-driven changes.

set -euo pipefail

# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

root="${1:-.}"

VERIFY_FULL="${VERIFY_FULL:-0}"
VERIFY_TIMEOUT="${VERIFY_TIMEOUT:-180}"
SHELLCHECK_ARGS="${SHELLCHECK_ARGS:--x -e SC1091}"
AI_VERIFY_SCOPE="${AI_VERIFY_SCOPE:-ai}"
VERIFY_SECRETS="${VERIFY_SECRETS:-${SECRETS_SCAN:-1}}"

failures=0

cd "$root"

run_step() {
    local label="$1"
    shift

    echo "==> $label"

    if ! run_with_timeout "$VERIFY_TIMEOUT" "$@"; then
        echo "FAIL: $label failed" >&2
        failures=$((failures + 1))
    fi
}

has_package_script() {
    local script_name="${1:?script name required}"
    [[ -f package.json ]] || return 1
    jq -e --arg name "$script_name" '.scripts[$name] // empty' package.json >/dev/null 2>&1
}

has_package_dependency() {
    local package_name="${1:?package name required}"
    [[ -f package.json ]] || return 1
    jq -e --arg name "$package_name" '
      (.dependencies[$name] // .devDependencies[$name] // .peerDependencies[$name] // empty)
    ' package.json >/dev/null 2>&1
}

tracked_existing_shell_files() {
    case "$AI_VERIFY_SCOPE" in
        ai)
            git ls-files -co --exclude-standard 'scripts/ai/*.sh' |
            while IFS= read -r script; do
                [[ -f "$script" ]] || continue
                [[ "$script" == scripts/ai/check-batch*.sh ]] && continue
                printf '%s\n' "$script"
            done
            ;;
        changed)
            {
                git diff --name-only --diff-filter=ACMRT -- '*.sh'
                git diff --cached --name-only --diff-filter=ACMRT -- '*.sh'
                git ls-files --others --exclude-standard -- '*.sh'
            } |
            sort -u |
            while IFS= read -r script; do
                [[ -f "$script" ]] || continue
                [[ "$script" == scripts/ai/check-batch*.sh ]] && continue
                printf '%s\n' "$script"
            done
            ;;
        all)
            git ls-files -co --exclude-standard '*.sh' |
            while IFS= read -r script; do
                [[ -f "$script" ]] || continue
                printf '%s\n' "$script"
            done
            ;;
        *)
            die "unknown AI_VERIFY_SCOPE: $AI_VERIFY_SCOPE"
            ;;
    esac
}

echo "==> repository"
git status --short || true

if command -v shellcheck >/dev/null 2>&1; then
    while IFS= read -r script; do
        [[ -n "$script" ]] || continue
        # shellcheck disable=SC2086
        run_step "shellcheck $script" shellcheck $SHELLCHECK_ARGS "$script"
    done < <(tracked_existing_shell_files)
fi

if command -v shfmt >/dev/null 2>&1; then
    while IFS= read -r script; do
        [[ -n "$script" ]] || continue
        run_step "shfmt -d $script" shfmt -d "$script"
    done < <(tracked_existing_shell_files)
fi

if command -v actionlint >/dev/null 2>&1 && [[ -d .github/workflows ]]; then
    run_step 'actionlint' actionlint
fi

if command -v lychee >/dev/null 2>&1; then
    if [[ -f scripts/run-link-check.sh ]]; then
        run_step 'bash scripts/run-link-check.sh' bash scripts/run-link-check.sh
    else
        run_step 'lychee README.md docs/**/*.md' lychee README.md docs/**/*.md
    fi
fi

if [[ -f composer.json ]]; then
    if command -v composer >/dev/null 2>&1; then
        run_step 'composer validate --strict' composer validate --strict
        run_step 'composer audit' composer audit
    fi

    if [[ -x vendor/bin/pint ]]; then
        run_step 'vendor/bin/pint --test' vendor/bin/pint --test
    fi

    if [[ -x vendor/bin/phpstan ]]; then
        run_step 'vendor/bin/phpstan analyse --memory-limit=1G' vendor/bin/phpstan analyse --memory-limit=1G
    fi

    if [[ -x vendor/bin/psalm ]]; then
        run_step 'vendor/bin/psalm --no-cache' vendor/bin/psalm --no-cache
    fi

    if [[ "$VERIFY_FULL" == "1" ]]; then
        if [[ -x vendor/bin/phpunit ]]; then
            run_step 'vendor/bin/phpunit' vendor/bin/phpunit
        fi

        if [[ -x vendor/bin/pest ]]; then
            run_step 'vendor/bin/pest' vendor/bin/pest
        fi
    else
        log_warn "Skipping full PHP test suite. Use VERIFY_FULL=1 to run phpunit/pest."
    fi
fi

if [[ -f package.json ]]; then
    if command -v pnpm >/dev/null 2>&1; then
        if has_package_script lint; then
            run_step 'pnpm run lint' pnpm run lint
        elif has_package_dependency eslint; then
            run_step 'pnpm exec eslint .' pnpm exec eslint .
        fi

        if has_package_script typecheck; then
            run_step 'pnpm run typecheck' pnpm run typecheck
        elif [[ -f tsconfig.json ]] && has_package_dependency typescript; then
            run_step 'pnpm exec tsc --noEmit' pnpm exec tsc --noEmit
        fi

        if has_package_dependency vue-tsc; then
            run_step 'pnpm exec vue-tsc --noEmit' pnpm exec vue-tsc --noEmit
        fi

        if has_package_dependency nuxt || has_package_dependency nuxi; then
            run_step 'pnpm exec nuxi typecheck' pnpm exec nuxi typecheck
        fi

        if has_package_dependency @graphql-codegen/cli && [[ -f codegen.yml || -f codegen.yaml || -f codegen.ts ]]; then
            run_step 'pnpm exec graphql-codegen' pnpm exec graphql-codegen
        fi

        if has_package_dependency @graphql-eslint/eslint-plugin; then
            run_step 'pnpm exec graphql-eslint .' pnpm exec graphql-eslint .
        fi

        if has_package_dependency biome; then
            run_step 'pnpm exec biome check .' pnpm exec biome check .
        fi

        if has_package_dependency knip; then
            run_step 'pnpm exec knip' pnpm exec knip
        fi

        if has_package_script test; then
            if [[ "$VERIFY_FULL" == "1" ]]; then
                run_step 'pnpm test' pnpm test
            else
                log_warn "Skipping full JS test suite. Use VERIFY_FULL=1 to run pnpm test."
            fi
        fi
    elif command -v npm >/dev/null 2>&1; then
        if has_package_script lint; then
            run_step 'npm run lint' npm run lint
        fi

        if has_package_script typecheck; then
            run_step 'npm run typecheck' npm run typecheck
        fi

        if has_package_script test; then
            if [[ "$VERIFY_FULL" == "1" ]]; then
                run_step 'npm test' npm test
            else
                log_warn "Skipping full JS test suite. Use VERIFY_FULL=1 to run npm test."
            fi
        fi
    fi
fi

if [[ "$VERIFY_SECRETS" == "1" ]]; then
    if command -v gitleaks >/dev/null 2>&1; then
        run_step 'gitleaks detect --source . --redact --no-banner' gitleaks detect --source . --redact --no-banner
    fi
else
    log_warn "Skipping secret scan. Use VERIFY_SECRETS=1 to enable gitleaks."
fi

if command -v trivy >/dev/null 2>&1; then
    run_step 'trivy fs --scanners vuln,misconfig,secret .' trivy fs --scanners vuln,misconfig,secret .
fi

if command -v semgrep >/dev/null 2>&1; then
    run_step 'semgrep scan --config auto .' semgrep scan --config auto .
fi

if command -v osv-scanner >/dev/null 2>&1; then
    run_step 'osv-scanner scan --lockfile=.' osv-scanner scan --lockfile=.
fi

if ((failures > 0)); then
    echo "==> failed: $failures verification step(s)" >&2
    log_json "verify.failed" "$(jq -cn --argjson failures "$failures" '{failures:$failures}')" || true
    exit 1
fi

echo '==> done'
log_json "verify.passed" "$(jq -cn '{status:"passed"}')" || true