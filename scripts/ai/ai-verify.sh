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
# Link checking is OFF by default. Set VERIFY_LINKS=1 to enable local-only link
# validation. Lychee always runs with --offline from this wrapper; shipped verify
# commands must never dial production URLs embedded in target-project docs.
VERIFY_LINKS="${VERIFY_LINKS:-0}"

# File line-count guardrail. Long files are a refactor signal, so this check
# tiers by size: info at LINECOUNT_INFO, warning at LINECOUNT_WARN, and a hard
# verification failure at LINECOUNT_ERROR (urgent refactor needed). Set
# VERIFY_LINECOUNT=0 to disable entirely. Scope follows AI_VERIFY_SCOPE: it only
# inspects changed/added/untracked files unless AI_VERIFY_SCOPE=all is requested.
VERIFY_LINECOUNT="${VERIFY_LINECOUNT:-1}"
LINECOUNT_INFO="${LINECOUNT_INFO:-350}"
LINECOUNT_WARN="${LINECOUNT_WARN:-550}"
LINECOUNT_ERROR="${LINECOUNT_ERROR:-800}"

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
            # In an installed target repo the kit's own tools/ai/**/*.php files
            # are shipped support code, not the user's project code to lint.
            should_skip_shipped_ai_kit_php_file "$f" && continue
            printf '%s\n' "$f"
        done
}

# Emit every tracked/untracked PHP file except the kit's shipped tools/ai/**
# files. Used for AI_VERIFY_SCOPE=all in an installed target repo so a
# project-wide pint/phpstan/psalm run still never lints shipped support code.
all_php_files_excluding_shipped() {
    git ls-files -co --exclude-standard -- '*.php' |
        sort -u |
        while IFS= read -r f; do
            [[ -n "$f" ]] || continue
            [[ -f "$f" ]] || continue
            should_skip_shipped_ai_kit_php_file "$f" && continue
            printf '%s\n' "$f"
        done
}

is_shipped_ai_kit_shell_file() {
    case "$1" in
    install-ai-kit.sh | \
        .github/hooks/scripts/*.sh | \
        scripts/ai/*.sh | \
        scripts/hooks/*.sh | \
        tools/ai/install-*.sh | \
        tools/ai/install/*.sh)
        return 0
        ;;
    esac

    return 1
}

is_changed_or_branch_scope() {
    [[ "$AI_VERIFY_SCOPE" == "changed" || "$AI_VERIFY_SCOPE" == "branch" ]]
}

# Shipped AI-kit PHP files. The kit ships its tooling under tools/ai/**, so in an
# installed target repository those files are vendored support code, not the
# user's project code to lint with pint/phpstan/psalm. A case-glob '*' matches
# '/', so this single pattern covers every nesting depth under tools/ai/.
is_shipped_ai_kit_php_file() {
    case "$1" in
    tools/ai/*.php)
        return 0
        ;;
    esac

    return 1
}

# A shipped AI-kit shell file should be skipped only in an installed target
# repository. Inside the kit's own authoring repo these scripts ARE the product
# under test, so they must remain part of changed/branch verification here.
should_skip_shipped_ai_kit_shell_file() {
    is_shipped_ai_kit_shell_file "$1" && ! is_ai_kit_source_repo
}

# A shipped AI-kit PHP file should be skipped only in an installed target
# repository; inside the kit's own authoring repo it remains in scope.
should_skip_shipped_ai_kit_php_file() {
    is_shipped_ai_kit_php_file "$1" && ! is_ai_kit_source_repo
}

# True only when running inside the AI-kit's own authoring repository, where the
# shipped scripts/ai/*.sh wrappers and install-*.sh scripts ARE the product
# under test and should be linted/formatted. Installed target repositories
# receive scripts/ai/* but never the kit package source authoring layout, so in
# a target the shipped wrappers must not become part of that project's
# verification burden.
#
# Detection requires the authoring-only artifacts together (not a single
# vendorable file like catalog.json), matching scripts/hooks/pre-commit.sh so
# "delivered vs source" is determined the same way across the kit. Override with
# AI_KIT_SELF_VERIFY=1 (force self-verify) or 0 (force target mode).
is_ai_kit_source_repo() {
    case "${AI_KIT_SELF_VERIFY:-auto}" in
    1) return 0 ;;
    0) return 1 ;;
    esac
    [[ -d packages/ai-universal-rules/templates &&
        -f packages/ai-universal-rules/package-lock.ai.json &&
        -f tools/ai/ai.php &&
        -f tools/ai/generate-ai-catalog.php ]]
}

# Enumerate the files the line-count guardrail should inspect, honoring
# AI_VERIFY_SCOPE. The default scopes (ai/changed/branch) only look at files the
# current work touched (added/modified/renamed + staged + untracked); repository-
# wide inspection happens ONLY when the caller explicitly asks with
# AI_VERIFY_SCOPE=all. Binary blobs and the .git dir are never included.
linecount_scoped_files() {
    case "$AI_VERIFY_SCOPE" in
    all)
        git ls-files -co --exclude-standard
        ;;
    branch)
        branch_scoped_files '*'
        ;;
    *)
        # ai (default) and changed both mean "only what this slice touched".
        {
            git diff --name-only --diff-filter=ACMRT
            git diff --cached --name-only --diff-filter=ACMRT
            git ls-files --others --exclude-standard
        } | sort -u
        ;;
    esac
}

# Tiered file line-count guardrail. Counts lines per in-scope file and reports:
#   >= LINECOUNT_INFO  -> info  (heads-up)
#   >= LINECOUNT_WARN  -> warn  (should refactor soon)
#   >= LINECOUNT_ERROR -> error (urgent refactor; counts as a verification failure)
# Each file is reported at its highest matching tier only. Files that exceed the
# error threshold increment the global failure tally so ai-verify exits non-zero.
check_line_counts() {
    [[ "$VERIFY_LINECOUNT" == "1" ]] || {
        log_warn "Skipping line-count check. Use VERIFY_LINECOUNT=1 to enable."
        return 0
    }

    echo "==> line-count"

    local file lines errors=0 flagged=0
    while IFS= read -r file; do
        [[ -n "$file" ]] || continue
        [[ -f "$file" ]] || continue
        # Skip binary files: grep -Iq prints nothing and returns 1 for binaries.
        grep -Iq . "$file" 2>/dev/null || continue

        lines="$(wc -l <"$file" 2>/dev/null | tr -d ' ')"
        [[ "$lines" =~ ^[0-9]+$ ]] || continue

        if ((lines >= LINECOUNT_ERROR)); then
            log_error "line-count $file = $lines lines >= $LINECOUNT_ERROR (URGENT refactor needed)"
            errors=$((errors + 1))
            flagged=$((flagged + 1))
        elif ((lines >= LINECOUNT_WARN)); then
            log_warn "line-count $file = $lines lines >= $LINECOUNT_WARN (refactor recommended)"
            flagged=$((flagged + 1))
        elif ((lines >= LINECOUNT_INFO)); then
            log_info "line-count $file = $lines lines >= $LINECOUNT_INFO (getting large)"
            flagged=$((flagged + 1))
        fi
    done < <(linecount_scoped_files)

    if ((errors > 0)); then
        echo "FAIL: line-count $errors file(s) >= $LINECOUNT_ERROR lines (urgent refactor)" >&2
        failures=$((failures + errors))
        log_json "verify.linecount" "$(jq -cn --argjson errors "$errors" --argjson flagged "$flagged" \
            --argjson error_threshold "$LINECOUNT_ERROR" \
            '{errors:$errors, flagged:$flagged, error_threshold:$error_threshold}')" || true
    elif ((flagged == 0)); then
        log_ok "line-count: all in-scope files under $LINECOUNT_INFO lines"
    fi
}

if [[ "${AI_VERIFY_TEST_MODE:-0}" == "1" ]]; then
    echo "==> repository"
    git status --short || true
    echo "==> shellcheck"
    echo "==> composer"
    if [[ "${VERIFY_FULL:-0}" != "1" ]]; then
        log_warn "Skipping full PHP test suite. Use VERIFY_FULL=1 to run phpunit/pest."
    fi
    check_line_counts
    echo "==> done"
    # Test mode stubs the heavy steps but still surfaces a real line-count
    # failure so the URGENT-refactor gate is exercisable without a full run.
    ((failures > 0)) && exit 1
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

    # Expose the last step's exit code without changing this function's own
    # return semantics: run_step has always effectively returned success so that
    # bare callers under `set -e` keep running every step and tally failures.
    last_step_rc="$rc"

    if ((rc != 0)); then
        echo "FAIL: $label failed (exit $rc)" >&2
        failures=$((failures + 1))
    fi
}

last_step_rc=0

# Wrapper for pnpm/JS verification steps. Behaves exactly like run_step (same
# streaming, watchdog, and failure counting) but, on failure, runs a focused
# private-registry auth diagnostic so a missing token does not masquerade as a
# typecheck/lint failure. Does not alter exit-code or failure-count behavior.
run_step_js() {
    local label="$1"
    run_step "$@"
    if ((last_step_rc != 0)); then
        diagnose_pnpm_auth "$label"
    fi
}

# Detect the common "implicit pnpm install hit a private registry without a
# token" failure mode. pnpm runs a deps-status check before `pnpm exec`, so an
# unset ${NPM_TOKEN} referenced by .npmrc surfaces as ERR_PNPM_FETCH_401 on a
# step that looks like a typecheck. This check is deterministic (it inspects
# .npmrc + env, not captured output) and only prints an advisory hint.
diagnose_pnpm_auth() {
    local label="${1:-pnpm step}"
    local npmrc found_ref="" referenced_var=""

    for npmrc in .npmrc "$HOME/.npmrc"; do
        [[ -f "$npmrc" ]] || continue
        # Find an auth line that interpolates an env var, e.g.
        #   //npm.pkg.github.com/:_authToken=${NPM_TOKEN}
        referenced_var="$(
            sed -n 's/.*_authToken=\${\([A-Za-z_][A-Za-z0-9_]*\)}.*/\1/p' "$npmrc" 2>/dev/null | head -n1
        )"
        if [[ -n "$referenced_var" ]]; then
            found_ref="$npmrc"
            break
        fi
    done

    [[ -n "$found_ref" ]] || return 0

    # If the referenced token variable is unset/empty, the implicit install will
    # fail with a 401 before the actual check runs.
    if [[ -z "${!referenced_var:-}" ]]; then
        log_warn "$label: '$found_ref' uses \${$referenced_var} for private-registry auth, but \$$referenced_var is unset."
        log_warn "$label: a 401/ERR_PNPM_FETCH_401 here is almost certainly missing registry auth, not a real type/lint error."
        log_warn "$label: set $referenced_var (token with read:packages) and re-run, e.g.: export $referenced_var=<token>; pnpm install"
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
        # In an installed target repo the shipped scripts/ai/*.sh wrappers are
        # not the user's code to verify; only self-verify them inside the kit's
        # own authoring repository.
        is_ai_kit_source_repo || return 0
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
                # In installed target repositories, AI-kit shell files are
                # shipped support files. Changed-scope verification is for the
                # user's slice, not re-linting shipped wrappers after install.
                # Inside the kit's own source repo they remain in scope.
                should_skip_shipped_ai_kit_shell_file "$script" && continue
                [[ "$script" == scripts/ai/check-batch*.sh ]] && continue
                printf '%s\n' "$script"
            done
        ;;
    branch)
        branch_scoped_files '*.sh' |
            while IFS= read -r script; do
                [[ -f "$script" ]] || continue
                # Branch scope can include freshly installed kit shell files in
                # a target repository; do not make shipped wrappers part of the
                # target project's verification burden. Inside the kit's own
                # source repo they remain in scope.
                should_skip_shipped_ai_kit_shell_file "$script" && continue
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

check_line_counts

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
    # Always offline: validate local file links only, never dial the network
    # (so production URLs in docs are not contacted). Do not delegate to a
    # target project's link-check wrapper because it may perform network checks.
    run_step 'lychee --offline README.md docs/**/*.md' lychee --offline README.md docs/**/*.md
else
    log_warn "Skipping link check. Use VERIFY_LINKS=1 to run lychee in offline mode."
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
    php_all_excluding_shipped=0
    php_files=()
    php_scope_source="$AI_VERIFY_SCOPE"
    case "$AI_VERIFY_SCOPE" in
    all)
        # Explicit project-wide request. In the kit's own source repo we lint
        # every file (php_scoped=0). In an installed target repo we still cover
        # the whole project but pass an explicit file list that excludes the
        # kit's shipped tools/ai/** files, so they are never linted.
        if ! is_ai_kit_source_repo; then
            php_scoped=1
            php_all_excluding_shipped=1
        fi
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

    # Human-readable description of what the scoped file list represents, used in
    # the pint/phpstan/psalm step labels.
    php_files_label="changed"
    if ((php_scoped)); then
        if ((php_all_excluding_shipped)); then
            php_files_label="project, excluding shipped"
            while IFS= read -r f; do
                [[ -n "$f" ]] && php_files+=("$f")
            done < <(all_php_files_excluding_shipped)
        else
            while IFS= read -r f; do
                [[ -n "$f" ]] && php_files+=("$f")
            done < <(AI_VERIFY_SCOPE="$php_scope_source" scoped_php_files)
        fi
    fi

    if [[ -x vendor/bin/pint ]]; then
        if ((php_scoped)); then
            if ((${#php_files[@]} > 0)); then
                run_step "vendor/bin/pint --test (${#php_files[@]} ${php_files_label} file(s))" vendor/bin/pint --test "${php_files[@]}"
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
                run_step "vendor/bin/phpstan analyse (${#php_files[@]} ${php_files_label} file(s))" vendor/bin/phpstan analyse --memory-limit=1G "${php_files[@]}"
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
                run_step "vendor/bin/psalm (${#php_files[@]} ${php_files_label} file(s))" vendor/bin/psalm --no-cache "${php_files[@]}"
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
            run_step_js 'pnpm run lint' pnpm run lint
        elif has_package_dependency eslint; then
            run_step_js 'pnpm exec eslint .' pnpm exec eslint .
        fi

        if has_package_script typecheck; then
            run_step_js 'pnpm run typecheck' pnpm run typecheck
        elif [[ -f tsconfig.json ]] && has_package_dependency typescript; then
            run_step_js 'pnpm exec tsc --noEmit' pnpm exec tsc --noEmit
        fi

        if has_package_dependency vue-tsc; then
            run_step_js 'pnpm exec vue-tsc --noEmit' pnpm exec vue-tsc --noEmit
        fi

        if has_package_dependency nuxt || has_package_dependency nuxi; then
            run_step_js 'pnpm exec nuxi typecheck' pnpm exec nuxi typecheck
        fi

        if has_package_dependency @graphql-codegen/cli && [[ -f codegen.yml || -f codegen.yaml || -f codegen.ts ]]; then
            run_step_js 'pnpm exec graphql-codegen' pnpm exec graphql-codegen
        fi

        if has_package_dependency @graphql-eslint/eslint-plugin; then
            run_step_js 'pnpm exec graphql-eslint .' pnpm exec graphql-eslint .
        fi

        if has_package_dependency biome; then
            run_step_js 'pnpm exec biome check .' pnpm exec biome check .
        fi

        if has_package_dependency knip; then
            run_step_js 'pnpm exec knip' pnpm exec knip
        fi

        if has_package_script test; then
            if [[ "$VERIFY_FULL" == "1" ]]; then
                run_step_js 'pnpm test' pnpm test
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

if is_changed_or_branch_scope; then
    log_warn "Skipping broad security scanners in $AI_VERIFY_SCOPE scope. Use AI_VERIFY_SCOPE=all to run trivy/semgrep/osv-scanner."
elif command -v trivy >/dev/null 2>&1; then
    run_step 'trivy fs --scanners vuln,misconfig,secret .' trivy fs --scanners vuln,misconfig,secret .
    if command -v semgrep >/dev/null 2>&1; then
        run_step 'semgrep scan --config auto .' semgrep scan --config auto .
    fi
    if command -v osv-scanner >/dev/null 2>&1; then
        run_step 'osv-scanner scan --lockfile=.' osv-scanner scan --lockfile=.
    fi
else
    if command -v semgrep >/dev/null 2>&1; then
        run_step 'semgrep scan --config auto .' semgrep scan --config auto .
    fi
    if command -v osv-scanner >/dev/null 2>&1; then
        run_step 'osv-scanner scan --lockfile=.' osv-scanner scan --lockfile=.
    fi
fi

if ((failures > 0)); then
    echo "==> failed: $failures verification step(s)" >&2
    log_json "verify.failed" "$(jq -cn --argjson failures "$failures" '{failures:$failures}')" || true
    exit 1
fi

echo '==> done'
log_json "verify.passed" "$(jq -cn '{status:"passed"}')" || true
