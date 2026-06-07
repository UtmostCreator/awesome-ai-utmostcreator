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
# Base ref used by the "branch" scope to diff the current branch against its
# divergence point. Override when your trunk is not origin/main.
VERIFY_BASE_REF="${VERIFY_BASE_REF:-}"
# Optional author filter for the "branch" scope. When set (e.g. your git email),
# only files from commits authored by you (plus uncommitted work) are scoped.
VERIFY_AUTHOR="${VERIFY_AUTHOR:-}"
# Link checking is OFF by default because it can reach the network and hit
# production URLs embedded in docs. Set VERIFY_LINKS=1 to enable it. Even then,
# lychee runs with --offline (local file links only) unless VERIFY_LINKS_NETWORK=1
# is also set, so a verify run never dials production endpoints by accident.
VERIFY_LINKS="${VERIFY_LINKS:-0}"
VERIFY_LINKS_NETWORK="${VERIFY_LINKS_NETWORK:-0}"

failures=0

cd "$root"

# Resolve the merge-base between HEAD and the branch this branch was created from.
# Prints the merge-base commit, or nothing if it cannot be determined.
#
# Detection order:
#   1. explicit VERIFY_BASE_REF override
#   2. scripts/ai/git-branch-origin.sh (closest-merge-base + release-pattern aware)
#   3. fallback trunk list (origin/main -> origin/master -> main -> master)
resolve_branch_base() {
    local candidate base
    local origin_script
    local detected=""

    # 1. Explicit override always wins.
    if [[ -n "$VERIFY_BASE_REF" ]]; then
        git rev-parse --verify --quiet "$VERIFY_BASE_REF^{commit}" >/dev/null 2>&1 || return 1
        base="$(git merge-base HEAD "$VERIFY_BASE_REF" 2>/dev/null || true)"
        [[ -n "$base" ]] && printf '%s\n' "$base" && return 0
        return 1
    fi

    # 2. Prefer the smarter branch-origin detector when available.
    origin_script="$(dirname "${BASH_SOURCE[0]}")/git-branch-origin.sh"
    if [[ -f "$origin_script" ]]; then
        detected="$(bash "$origin_script" --field base 2>/dev/null || true)"
        if [[ -n "$detected" ]]; then
            printf '%s\n' "$detected"
            return 0
        fi
    fi

    # 3. Fallback trunk list.
    for candidate in origin/main origin/master main master; do
        git rev-parse --verify --quiet "$candidate^{commit}" >/dev/null 2>&1 || continue
        if base="$(git merge-base HEAD "$candidate" 2>/dev/null)" && [[ -n "$base" ]]; then
            printf '%s\n' "$base"
            return 0
        fi
    done

    return 1
}

# Files changed by the current branch since it diverged from its base, plus any
# uncommitted, staged, or untracked work. Respects $1 as a pathspec glob.
# Stops at the merge-base: shared history before the divergence is never touched.
branch_scoped_files() {
    local glob="${1:?glob required}"
    local base=""

    base="$(resolve_branch_base || true)"

    {
        if [[ -n "$base" ]]; then
            if [[ -n "$VERIFY_AUTHOR" ]]; then
                # Only files from commits authored by VERIFY_AUTHOR on this branch.
                local sha
                while IFS= read -r sha; do
                    [[ -n "$sha" ]] || continue
                    git show --no-patch --format= --name-only --diff-filter=ACMRT "$sha" -- "$glob"
                done < <(git rev-list --author="$VERIFY_AUTHOR" "$base..HEAD" 2>/dev/null)
            else
                git diff --name-only --diff-filter=ACMRT "$base"...HEAD -- "$glob"
            fi
        fi
        # Always include local in-progress work regardless of authorship.
        git diff --name-only --diff-filter=ACMRT -- "$glob"
        git diff --cached --name-only --diff-filter=ACMRT -- "$glob"
        git ls-files --others --exclude-standard -- "$glob"
    } | sort -u
}

# Emit existing changed PHP files according to AI_VERIFY_SCOPE.
# - branch: merge-base diff of the current branch + local work
# - changed: local working-tree/staged/untracked work only
# Returns no output for the project-wide scopes (ai/all), signalling callers to
# run the PHP tools project-wide as before.
scoped_php_files() {
    local source_fn
    case "$AI_VERIFY_SCOPE" in
    branch) source_fn=branch ;;
    changed) source_fn=changed ;;
    *) return 0 ;;
    esac

    {
        if [[ "$source_fn" == branch ]]; then
            branch_scoped_files '*.php'
        else
            git diff --name-only --diff-filter=ACMRT -- '*.php'
            git diff --cached --name-only --diff-filter=ACMRT -- '*.php'
            git ls-files --others --exclude-standard -- '*.php'
        fi
    } |
        sort -u |
        while IFS= read -r f; do
            [[ -n "$f" ]] || continue
            [[ -f "$f" ]] || continue
            printf '%s\n' "$f"
        done
}

if [[ "${AI_VERIFY_TEST_MODE:-0}" == "1" ]]; then
    echo "==> repository"
    git status --short || true
    echo "==> shellcheck"
    echo "==> composer"
    if [[ "${VERIFY_FULL:-0}" != "1" ]]; then
        log_warn "Skipping full PHP test suite. Use VERIFY_FULL=1 to run phpunit/pest."
    fi
    echo "==> done"
    exit 0
fi

run_step() {
    local label="$1"
    shift

    echo "==> $label"

    # Run under the hang/freeze watchdog: a hard wall-clock ceiling plus
    # idle-output + idle-CPU detection that kills a stuck process group. Set
    # VERIFY_GUARD=0 to fall back to the plain wall-clock timeout wrapper.
    local rc=0
    if [[ "${VERIFY_GUARD:-1}" == "1" ]]; then
        AI_GUARD_TIMEOUT="${AI_GUARD_TIMEOUT:-$VERIFY_TIMEOUT}" run_guarded "$label" "$@" || rc=$?
    else
        run_with_timeout "$VERIFY_TIMEOUT" "$@" || rc=$?
    fi

    if ((rc != 0)); then
        echo "FAIL: $label failed (exit $rc)" >&2
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
    branch)
        branch_scoped_files '*.sh' |
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

if [[ "$VERIFY_LINKS" == "1" ]] && command -v lychee >/dev/null 2>&1; then
    if [[ -f scripts/run-link-check.sh ]]; then
        run_step 'bash scripts/run-link-check.sh' bash scripts/run-link-check.sh
    elif [[ "$VERIFY_LINKS_NETWORK" == "1" ]]; then
        # Explicit network link check requested. This CAN reach production URLs
        # embedded in docs; only enable when that is intended.
        run_step 'lychee README.md docs/**/*.md' lychee README.md docs/**/*.md
    else
        # Offline by default: validate local file links only, never dial the
        # network (so production URLs in docs are not contacted).
        run_step 'lychee --offline README.md docs/**/*.md' lychee --offline README.md docs/**/*.md
    fi
else
    log_warn "Skipping link check. Use VERIFY_LINKS=1 (offline) or VERIFY_LINKS=1 VERIFY_LINKS_NETWORK=1 (network)."
fi

if [[ -f composer.json ]]; then
    if command -v composer >/dev/null 2>&1; then
        run_step 'composer validate --strict' composer validate --strict
        run_step 'composer audit' composer audit
    fi

    # Determine whether the PHP linters/analysers should be narrowed to the
    # files changed on this branch, or run project-wide.
    #
    # Default (including the "ai" scope): narrow to changed files. With no local
    # changes we fall back to files unique to the current feature branch via its
    # merge-base (git-branch-origin.sh), so pint/phpstan/psalm never lint the
    # whole project unless the caller explicitly asks with AI_VERIFY_SCOPE=all.
    php_scoped=0
    php_files=()
    php_scope_source="$AI_VERIFY_SCOPE"
    case "$AI_VERIFY_SCOPE" in
    all)
        # Explicit project-wide request: leave php_scoped=0 so the linters run
        # across every file.
        ;;
    changed)
        php_scoped=1
        ;;
    *)
        # ai (default) and branch both resolve to branch-aware scoping.
        php_scoped=1
        php_scope_source="branch"
        ;;
    esac

    if ((php_scoped)); then
        while IFS= read -r f; do
            [[ -n "$f" ]] && php_files+=("$f")
        done < <(AI_VERIFY_SCOPE="$php_scope_source" scoped_php_files)
    fi

    if [[ -x vendor/bin/pint ]]; then
        if ((php_scoped)); then
            if ((${#php_files[@]} > 0)); then
                run_step "vendor/bin/pint --test (${#php_files[@]} changed file(s))" vendor/bin/pint --test "${php_files[@]}"
            else
                log_warn "No changed PHP files in scope ($AI_VERIFY_SCOPE); skipping pint."
            fi
        else
            run_step 'vendor/bin/pint --test' vendor/bin/pint --test
        fi
    fi

    if [[ -x vendor/bin/phpstan ]]; then
        if ((php_scoped)); then
            if ((${#php_files[@]} > 0)); then
                run_step "vendor/bin/phpstan analyse (${#php_files[@]} changed file(s))" vendor/bin/phpstan analyse --memory-limit=1G "${php_files[@]}"
            else
                log_warn "No changed PHP files in scope ($AI_VERIFY_SCOPE); skipping phpstan."
            fi
        else
            run_step 'vendor/bin/phpstan analyse --memory-limit=1G' vendor/bin/phpstan analyse --memory-limit=1G
        fi
    fi

    if [[ -x vendor/bin/psalm ]]; then
        if ((php_scoped)); then
            if ((${#php_files[@]} > 0)); then
                run_step "vendor/bin/psalm (${#php_files[@]} changed file(s))" vendor/bin/psalm --no-cache "${php_files[@]}"
            else
                log_warn "No changed PHP files in scope ($AI_VERIFY_SCOPE); skipping psalm."
            fi
        else
            run_step 'vendor/bin/psalm --no-cache' vendor/bin/psalm --no-cache
        fi
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
