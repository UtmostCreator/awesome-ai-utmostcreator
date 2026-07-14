# shellcheck shell=bash
# Procedural verification flow for the AI verification gate.
#
# This module is sourced by scripts/ai/ai-verify.sh (the thin root loader);
# it is NOT an entrypoint and must not be executed directly. It is sourced LAST,
# after common.sh and every helper module, and the root loader calls
# ai_verify_run at the very end.
#
# ai_verify_run wraps the procedural body that used to run at the top level of
# the monolithic ai-verify.sh (the AI_VERIFY_TEST_MODE block plus the full
# repository/shellcheck/composer/pnpm/secrets/security flow). It deliberately
# uses the GLOBAL $failures (never a local) and preserves every top-level
# `exit` so the process-exit semantics are byte-for-byte identical to before.
# Only the file layout changed; no check logic, command, message, threshold, or
# ordering was altered.
#
# $failures is a global assigned by the root loader (scripts/ai/ai-verify.sh)
# before this module is sourced; this function intentionally mutates that global.
# shellcheck disable=SC2154

# Emit scoped changed files matching one or more git pathspec globs. The output
# includes modified/staged/untracked files and is de-duplicated.
scoped_changed_files_by_pathspec() {
    local scope="${1:?scope required}"
    shift

    case "$scope" in
    branch)
        local pattern
        for pattern in "$@"; do
            branch_scoped_files "$pattern"
        done
        ;;
    changed)
        git diff --name-only --diff-filter=ACMRT -- "$@"
        git diff --cached --name-only --diff-filter=ACMRT -- "$@"
        git ls-files --others --exclude-standard -- "$@"
        ;;
    *)
        return 0
        ;;
    esac | sort -u
}

# True when any scoped changed file is exactly one of the provided paths.
scope_has_exact_changed_path() {
    local scope="${1:?scope required}"
    shift

    local changed wanted
    while IFS= read -r changed; do
        [[ -n "$changed" ]] || continue
        for wanted in "$@"; do
            if [[ "$changed" == "$wanted" ]]; then
                return 0
            fi
        done
    done < <(scoped_changed_files_by_pathspec "$scope" "$@")

    return 1
}

# Advisory-only composer-unused check (docs/tickets/arch-todo-safe-language-verify-scripts-20260706-003959
# §8-P6): reports packages that appear unused, but NEVER increments the global
# $failures tally, mirroring the advisory-tiering pattern already used by
# check_jscpd (35-jscpd.sh) for its default WARN-only tier. Full-gate only:
# called exclusively from the PHP VERIFY_FULL branch below. write_verify_report_file
# is defined in 54-reporting.sh, sourced BEFORE this module by scripts/ai/ai-verify.sh
# (see that file's load-ordered `source` list), so it is resolvable here both at
# source time and at this function's call time.
check_composer_unused() {
    [[ -x vendor/bin/composer-unused ]] || return 0

    echo "==> vendor/bin/composer-unused (advisory)"

    local output rc=0
    output="$(vendor/bin/composer-unused 2>&1)" || rc=$?
    write_verify_report_file composer-unused txt "$output" >/dev/null 2>&1 || true

    if ((rc != 0)); then
        log_warn "composer-unused reported findings (exit $rc); advisory only, does not fail verification. See ${AI_LOG_DIR:-.ai-logs}/verify/composer-unused.txt"
    else
        log_ok "composer-unused: no unused packages reported"
    fi
}

ai_verify_run() {
    if [[ "${AI_VERIFY_TEST_MODE:-0}" == "1" ]]; then
        echo "==> repository"
        git status --short || true
        echo "==> shellcheck"
        echo "==> composer"
        if [[ "${VERIFY_FULL:-0}" != "1" ]]; then
            log_warn "Skipping full PHP test suite. Use VERIFY_FULL=1 to run phpunit/pest."
        fi
        check_line_counts
        check_jscpd
        check_plan_status
        echo "==> done"
        # Test mode stubs the heavy steps but still surfaces a real line-count
        # failure so the URGENT-refactor gate is exercisable without a full run.
        ((failures > 0)) && exit 1
        exit 0
    fi

    echo "==> repository"
    git status --short || true

    check_line_counts
    check_jscpd
    check_plan_status

    if command -v shellcheck >/dev/null 2>&1; then
        while IFS= read -r script; do
            [[ -n "$script" ]] || continue
            # SC1071: the linter only supports sh/bash/dash/ksh/busybox-sh. Skip
            # scripts whose shebang is another shell (e.g. zsh); shfmt still covers
            # their formatting below.
            if IFS= read -r _first_line <"$script" 2>/dev/null &&
                [[ "$_first_line" == "#!"*zsh* || "$_first_line" == "#!"*fish* ]]; then
                log_warn "Skipping shellcheck for $script: unsupported shell shebang (shellcheck only lints sh/bash/dash/ksh)."
                continue
            fi
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
        if is_changed_or_branch_scope; then
            workflow_files=()
            while IFS= read -r wf; do
                [[ -n "$wf" ]] || continue
                [[ -f "$wf" ]] || continue
                should_skip_shipped_ai_kit_workflow_file "$wf" && continue
                workflow_files+=("$wf")
            done < <(scoped_changed_files_by_pathspec "$AI_VERIFY_SCOPE" '.github/workflows/*.yml' '.github/workflows/*.yaml')

            if ((${#workflow_files[@]} > 0)); then
                run_step "actionlint (${#workflow_files[@]} changed workflow file(s))" actionlint "${workflow_files[@]}"
            else
                log_warn "Skipping actionlint in $AI_VERIFY_SCOPE scope: no changed workflow files in scope."
            fi
        else
            run_step 'actionlint' actionlint
        fi
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
            if is_changed_or_branch_scope; then
                if scope_has_exact_changed_path "$AI_VERIFY_SCOPE" composer.json composer.lock; then
                    run_step 'composer validate --strict' composer validate --strict
                    run_step 'composer audit' composer audit
                else
                    log_warn "Skipping composer validate/audit in $AI_VERIFY_SCOPE scope: composer.json/composer.lock unchanged."
                fi
            else
                run_step 'composer validate --strict' composer validate --strict
                run_step 'composer audit' composer audit
            fi
        fi

        # Determine whether the PHP linters/analysers should be narrowed to
        # changed files, branch files, or run project-wide.
        #
        # Default "ai" scope is intentionally local/dirty-only (same as changed)
        # so plain `bash scripts/ai/ai-verify.sh .` stays fast and bounded in
        # shipped target repos. Use AI_VERIFY_SCOPE=branch to include committed
        # files unique to the current branch, or AI_VERIFY_SCOPE=all for explicit
        # project-wide verification.
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
        ai)
            php_scoped=1
            php_scope_source="changed"
            ;;
        branch)
            php_scoped=1
            php_scope_source="branch"
            ;;
        *)
            die "unknown AI_VERIFY_SCOPE: $AI_VERIFY_SCOPE"
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

            # Full-gate-only PHP architecture/dependency checks (§8-P6).
            if [[ -x vendor/bin/deptrac ]]; then
                run_step 'vendor/bin/deptrac analyse' vendor/bin/deptrac analyse
            fi

            if [[ -x vendor/bin/composer-require-checker ]]; then
                run_step 'vendor/bin/composer-require-checker check composer.json' vendor/bin/composer-require-checker check composer.json
            fi

            check_composer_unused
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

            # Broadened Biome detection (name-partial `has_package_dependency
            # biome` alone misses the real npm package `@biomejs/biome` and a
            # bare `biome.json`/`biome.jsonc` config with no lockfile entry).
            # This mirrors the dispatch that 50-tool-policy.sh's `can_run_tool
            # biome` will centralize once that module is wired into the root
            # loader (a later slice); kept inline here so today's pipeline
            # benefits without requiring that not-yet-sourced module.
            if has_package_dependency '@biomejs/biome' || has_package_dependency biome || [[ -f biome.json || -f biome.jsonc ]]; then
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

            # Full-gate-only JS checks (§8-P6): dedicated Playwright/Vitest
            # invocations, distinct from the generic `pnpm test` alias above, so a
            # project without a `test` script alias still gets these covered.
            if [[ "$VERIFY_FULL" == "1" ]]; then
                if has_package_dependency '@playwright/test'; then
                    run_step_js 'pnpm exec playwright test' pnpm exec playwright test
                fi

                if has_package_dependency vitest; then
                    run_step_js 'pnpm exec vitest run' pnpm exec vitest run
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

    # Broad, repo-wide security scanners (trivy/semgrep/osv-scanner) stay off by
    # default in changed/branch scope; they run only when explicitly requested
    # via AI_VERIFY_SCOPE=all (existing behavior) or the VERIFY_SECURITY=1
    # opt-in (new), so a per-language/changed-only run never silently pays the
    # cost of a full-repo scan.
    if is_changed_or_branch_scope && [[ "${VERIFY_SECURITY:-0}" != "1" ]]; then
        log_warn "Skipping broad security scanners in $AI_VERIFY_SCOPE scope. Use AI_VERIFY_SCOPE=all or VERIFY_SECURITY=1 to run trivy/semgrep/osv-scanner."
    elif command -v trivy >/dev/null 2>&1; then
        run_step 'trivy fs --scanners vuln,misconfig,secret .' trivy fs --scanners vuln,misconfig,secret .
        if command -v semgrep >/dev/null 2>&1; then
            run_step 'semgrep scan --config auto .' semgrep scan --config auto .
        fi
        if command -v osv-scanner >/dev/null 2>&1; then
            run_step 'osv-scanner scan source -r .' osv-scanner scan source -r .
        fi
    else
        if command -v semgrep >/dev/null 2>&1; then
            run_step 'semgrep scan --config auto .' semgrep scan --config auto .
        fi
        if command -v osv-scanner >/dev/null 2>&1; then
            run_step 'osv-scanner scan source -r .' osv-scanner scan source -r .
        fi
    fi

    if ((failures > 0)); then
        echo "==> failed: $failures verification step(s)" >&2
        log_json "verify.failed" "$(jq -cn --argjson failures "$failures" '{failures:$failures}')" || true
        exit 1
    fi

    echo '==> done'
    log_json "verify.passed" "$(jq -cn '{status:"passed"}')" || true
}
