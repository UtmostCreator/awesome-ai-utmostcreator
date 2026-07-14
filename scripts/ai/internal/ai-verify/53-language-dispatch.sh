# shellcheck shell=bash
# Per-language dispatcher for the AI verification gate.
#
# Part of docs/tickets/arch-todo-safe-language-verify-scripts-20260706-003959.
# This module is sourced by scripts/ai/ai-verify.sh (the thin root loader); it
# is NOT an entrypoint and must not be executed directly. It must be sourced
# AFTER 40-step-runner.sh (run_step/run_step_js/has_package_script/
# has_package_dependency), 50-tool-policy.sh (can_run_tool), 51-language-files.sh
# (scoped_language_files), 54-reporting.sh (write_verify_report_file), and
# 90-run.sh (scoped_language_files calls scoped_changed_files_by_pathspec, and
# this file calls scope_has_exact_changed_path, both defined there, at CALL
# time). The root loader's ai_verify_language "$AI_VERIFY_LANGUAGE" call happens
# after this file is sourced, so every dependency is already resolvable by then.
#
# §8-P2 shipped `check` mode only. §8-P4/P5 (this revision) add the first
# non-check-mode behavior:
#   - `AI_VERIFY_MODE=suggest` (default remains `check`, unchanged behavior):
#     JS/TS/Vue eslint diagnostics switch to a non-mutating, ADVISORY-ONLY
#     `--fix-dry-run --format json` run (run_eslint_suggest_files) that never
#     increments $failures. `fix` mode is still NOT implemented anywhere in
#     this file (fix mutates files and needs its own separately-approved
#     slice; see plan.md §5 Things To Avoid).
#   - `VERIFY_OUTPUT_FORMAT=json` (default `table`, i.e. unchanged plain
#     stdout): phpstan/psalm additionally get `--error-format=json`/
#     `--output-format=json` and write a raw-output report file.
#   - Rector (`process --dry-run` only, never bare `process`/apply) and
#     scoped `composer validate --strict`/`composer audit` join the PHP path.
#   - `vitest run --changed` joins the shared JS/TS/Vue lint dispatch as a
#     lightweight default (non-VERIFY_FULL) check.
#
# Only five languages are dispatched (php, js, ts, vue, html) via small,
# hand-written per-language runner functions -- this deliberately does NOT
# build a generic metadata-driven tool-adapter framework (plan.md §1b reject
# list); each runner is a short, readable list of `can_run_tool`/
# `has_package_*` guards mirroring the equivalent inline checks already in
# 90-run.sh, reused via can_run_tool/run_step/run_step_js rather than
# reimplemented.

# Shared eslint/biome lint dispatch for both JS and TS file sets (and reused by
# the Vue dispatch too). Kept as one function so the three callers
# (run_js_language_files, run_ts_language_files, run_vue_language_files) never
# duplicate the same conditionals (repo `>=75%` reuse rule). This is also
# where `vitest run --changed` is added (§8-P5): all three language dispatch
# functions funnel through here, so adding it once naturally covers js/ts/vue
# without a third copy of the same `has_package_dependency` guard.
run_js_or_ts_lint_files() {
    local files=("$@")
    local mode="${AI_VERIFY_MODE:-check}"

    if has_package_script lint; then
        run_step_js 'pnpm run lint' pnpm run lint
    elif [[ "$mode" == "suggest" ]] && can_run_tool eslint; then
        run_eslint_suggest_files "${files[@]}"
    elif can_run_tool eslint; then
        run_step_js "eslint (${#files[@]} file(s))" pnpm exec eslint "${files[@]}"
    fi

    if can_run_tool biome; then
        run_step_js "biome check (${#files[@]} file(s))" pnpm exec biome check "${files[@]}"
    fi

    # Lightweight, default (non-VERIFY_FULL) check: only affected tests for
    # changed source, distinct from the full unscoped `vitest run` (deferred
    # to §8-P6, gated behind VERIFY_FULL in 90-run.sh, not here).
    if has_package_dependency vitest; then
        run_step_js 'pnpm exec vitest run --changed' pnpm exec vitest run --changed
    fi
}

# ADVISORY-ONLY diagnostic run for AI_VERIFY_MODE=suggest: reports eslint's
# --fix-dry-run findings as JSON without ever mutating a file and, critically,
# without ever incrementing $failures -- suggest mode only reports, it never
# fails the run (unlike run_step/run_step_js, which always tally a non-zero
# exit as a failure). This is why this path is NOT routed through run_step:
# output is captured directly with `|| true`-equivalent handling of $rc, but
# still runs under the same VERIFY_TIMEOUT anti-freeze bound as every other
# external-process invocation in this pipeline, mirroring the established
# "capture output, never hard-fail" convention in check_jscpd (35-jscpd.sh).
run_eslint_suggest_files() {
    local files=("$@")

    echo "==> eslint --fix-dry-run (suggest mode, ${#files[@]} file(s))"

    local output rc=0
    output="$(run_with_timeout "$VERIFY_TIMEOUT" pnpm exec eslint --fix-dry-run --format json "${files[@]}" 2>&1)" || rc=$?

    write_verify_report_file eslint-suggest json "$output" >/dev/null
    log_json "verify.eslint_suggest" "$(jq -cn --argjson exit_code "$rc" '{mode:"suggest",exit_code:$exit_code}')" || true

    if ((rc != 0)); then
        log_warn "eslint --fix-dry-run (suggest mode) reported findings (exit $rc); advisory only, not counted as a failure."
    else
        log_ok "eslint --fix-dry-run (suggest mode): no findings."
    fi
}

run_php_language_files() {
    local files=("$@")

    # composer validate/audit is evaluated independently of the per-file php
    # list below and is placed BEFORE the empty-files early return:
    # composer.json/composer.lock can change in scope even when no *.php file
    # does (e.g. a dependency bump with no source edits), and this check must
    # still fire in that case. Mirrors 90-run.sh's existing changed/branch-scope
    # gate (90-run.sh:141-154) by calling the SAME scope_has_exact_changed_path
    # function defined there -- the change-detection logic itself is never
    # reimplemented here, only reused. Same "ai"->"changed" translation as
    # ai_verify_language's $lang_scope above (this per-language dispatch always
    # treats the default "ai" scope as scoped/changed-like, unlike 90-run.sh's
    # own top-level pipeline where "ai" runs composer checks unconditionally).
    local composer_scope="$AI_VERIFY_SCOPE"
    if [[ "$composer_scope" == "ai" ]]; then
        composer_scope="changed"
    fi
    if scope_has_exact_changed_path "$composer_scope" composer.json composer.lock &&
        command -v composer >/dev/null 2>&1; then
        run_step 'composer validate --strict' composer validate --strict
        run_step 'composer audit' composer audit
    fi

    if ((${#files[@]} == 0)); then
        log_warn "No changed PHP files in scope ($AI_VERIFY_SCOPE); skipping PHP checks."
        return 0
    fi

    if can_run_tool pint; then
        run_step "pint check (${#files[@]} file(s))" vendor/bin/pint --test "${files[@]}"
    fi

    # VERIFY_OUTPUT_FORMAT (default "table", i.e. today's unchanged plain
    # stdout behavior). Chosen mechanics, documented since exact behavior
    # matters for tests: run_step ALWAYS performs the real gating invocation
    # exactly as before (same watchdog/streaming/failure-tally behavior --
    # only the appended --error-format=json/--output-format=json flag differs
    # in json mode). In json mode a SEPARATE, second, non-gating invocation of
    # the SAME command additionally captures combined stdout+stderr into a
    # string written verbatim to the per-tool report file via
    # write_verify_report_file (54-reporting.sh). This follow-up-capture
    # approach is safe here specifically because phpstan analyse / psalm are
    # read-only, idempotent static analysers that never mutate source, so
    # running the same analysis twice changes no file and no exit-code-driven
    # behavior -- this is NOT a pattern to copy for any mutating command. The
    # second run is intentionally NOT routed through run_step so it can never
    # double-count $failures.
    local output_format="${VERIFY_OUTPUT_FORMAT:-table}"

    if can_run_tool phpstan; then
        local phpstan_cmd=(vendor/bin/phpstan analyse --memory-limit=1G)
        [[ "$output_format" == "json" ]] && phpstan_cmd+=(--error-format=json)
        run_step "phpstan (${#files[@]} file(s))" "${phpstan_cmd[@]}" "${files[@]}"
        if [[ "$output_format" == "json" ]]; then
            local phpstan_out
            phpstan_out="$("${phpstan_cmd[@]}" "${files[@]}" 2>&1)" || true
            write_verify_report_file phpstan json "$phpstan_out" >/dev/null
        fi
    fi

    if can_run_tool psalm; then
        local psalm_cmd=(vendor/bin/psalm --no-cache)
        [[ "$output_format" == "json" ]] && psalm_cmd+=(--output-format=json)
        run_step "psalm (${#files[@]} file(s))" "${psalm_cmd[@]}" "${files[@]}"
        if [[ "$output_format" == "json" ]]; then
            local psalm_out
            psalm_out="$("${psalm_cmd[@]}" "${files[@]}" 2>&1)" || true
            write_verify_report_file psalm json "$psalm_out" >/dev/null
        fi
    fi

    # Non-mutating: `process --dry-run` only, NEVER bare `rector process`
    # (which would rewrite files in place). See plan.md §5 Things To Avoid.
    if can_run_tool rector; then
        run_step "rector dry-run (${#files[@]} file(s))" vendor/bin/rector process --dry-run "${files[@]}"
    fi
}

run_js_language_files() {
    local files=("$@")

    if ((${#files[@]} == 0)); then
        log_warn "No changed JS files in scope ($AI_VERIFY_SCOPE); skipping JS checks."
        return 0
    fi

    run_js_or_ts_lint_files "${files[@]}"

    # knip is project-wide by nature (it analyses the whole dependency graph),
    # so it is never passed a file list -- matches the equivalent inline check
    # in 90-run.sh:301-303.
    if can_run_tool knip; then
        run_step_js 'pnpm exec knip' pnpm exec knip
    fi
}

run_ts_language_files() {
    local files=("$@")

    if ((${#files[@]} == 0)); then
        log_warn "No changed TS files in scope ($AI_VERIFY_SCOPE); skipping TS checks."
        return 0
    fi

    # tsc --noEmit is inherently project-wide (it resolves the whole
    # tsconfig.json project graph), so it is never passed a file list --
    # matches the equivalent inline check in 90-run.sh:268-272.
    if has_package_script typecheck; then
        run_step_js 'pnpm run typecheck' pnpm run typecheck
    elif [[ -f tsconfig.json ]] && has_package_dependency typescript; then
        run_step_js 'pnpm exec tsc --noEmit' pnpm exec tsc --noEmit
    fi

    run_js_or_ts_lint_files "${files[@]}"
}

run_vue_language_files() {
    local files=("$@")

    if ((${#files[@]} == 0)); then
        log_warn "No changed Vue files in scope ($AI_VERIFY_SCOPE); skipping Vue checks."
        return 0
    fi

    run_js_or_ts_lint_files "${files[@]}"

    # vue-tsc/nuxi typecheck are project-wide, not file-scoped -- matches the
    # equivalent inline checks in 90-run.sh:274-280.
    if can_run_tool vue-tsc; then
        run_step_js 'pnpm exec vue-tsc --noEmit' pnpm exec vue-tsc --noEmit
    fi

    if can_run_tool nuxt; then
        run_step_js 'pnpm exec nuxi typecheck' pnpm exec nuxi typecheck
    fi
}

# Detection order (fixed, per plan.md Q3): biome -> htmlhint -> clean skip.
# There is no safe "configured eslint" fallback wired here yet (HTML-aware
# eslint configs vary too much to guess safely); biome/htmlhint cover the
# common case and anything else cleanly skips with a log_warn, never a
# failure.
run_html_language_files() {
    local files=("$@")

    if ((${#files[@]} == 0)); then
        log_warn "No changed HTML files in scope ($AI_VERIFY_SCOPE); skipping HTML checks."
        return 0
    fi

    if can_run_tool biome; then
        run_step_js "biome check (${#files[@]} file(s))" pnpm exec biome check "${files[@]}"
    elif has_package_dependency htmlhint; then
        run_step_js "htmlhint (${#files[@]} file(s))" pnpm exec htmlhint "${files[@]}"
    else
        log_warn "No configured HTML verifier found (biome/htmlhint); skipping HTML."
    fi
}

# Entry point called by the root loader when --language <lang> is passed.
# Runs ONLY that language's check subset and exits 0/1 based on the shared
# $failures tally, mirroring ai_verify_run's own exit contract (90-run.sh).
ai_verify_language() {
    local lang="${1:?language required}"

    case "$lang" in
    php | js | ts | vue | html) ;;
    *)
        die "unknown language: $lang"
        ;;
    esac

    # CRITICAL: AI_VERIFY_SCOPE defaults to "ai" (scripts/ai/ai-verify.sh), but
    # scoped_changed_files_by_pathspec (90-run.sh) only has case arms for
    # "branch" and "changed" -- its default arm (`*) return 0`) silently
    # returns ZERO files for scope "ai". Translate ai -> changed before calling
    # scoped_language_files, exactly like 90-run.sh's own PHP-scoping
    # workaround (php_scope_source="$AI_VERIFY_SCOPE"; ai) php_scope_source=
    # "changed" ;; ...; AI_VERIFY_SCOPE="$php_scope_source" scoped_php_files).
    # Without this translation, `bash scripts/ai/ai-verify-php.sh .` run with
    # no env vars set (the common case) would silently find zero files and
    # report everything skipped.
    local lang_scope="$AI_VERIFY_SCOPE"
    if [[ "$lang_scope" == "ai" ]]; then
        lang_scope="changed"
    fi

    local files=()
    while IFS= read -r f; do
        [[ -n "$f" ]] && files+=("$f")
    done < <(AI_VERIFY_SCOPE="$lang_scope" scoped_language_files "$lang")

    echo "==> language:$lang"

    case "$lang" in
    php)
        run_php_language_files "${files[@]}"
        ;;
    js)
        run_js_language_files "${files[@]}"
        ;;
    ts)
        run_ts_language_files "${files[@]}"
        ;;
    vue)
        run_vue_language_files "${files[@]}"
        ;;
    html)
        run_html_language_files "${files[@]}"
        ;;
    esac

    echo "==> done"

    # shellcheck disable=SC2154 # $failures is the root loader's global tally
    ((failures > 0)) && exit 1
    exit 0
}
