# shellcheck shell=bash
# File-scope discovery helpers for the AI verification gate.
#
# This module is sourced by scripts/ai/ai-verify.sh (the thin root loader);
# it is NOT an entrypoint and must not be executed directly. It is sourced
# AFTER common.sh and AFTER 20-shipped-filters.sh, so it may use common.sh
# helpers (e.g. log_*) and the shipped-file predicates.
#
# Behavior is byte-for-byte identical to the previous monolithic ai-verify.sh;
# only the file layout changed. The single exception is the git-branch-origin.sh
# path lookup in resolve_branch_base: the original used
# "$(dirname "${BASH_SOURCE[0]}")" which, from the root file, resolves to
# scripts/ai. Because this function now lives under internal/ai-verify/, the root
# loader exports the same directory as $_ai_verify_dir and we reference it here so
# the resolved path string is identical to before.

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
    # _ai_verify_dir is a global assigned by the root loader before this module
    # is sourced (see scripts/ai/ai-verify.sh).
    # shellcheck disable=SC2154
    origin_script="$_ai_verify_dir/git-branch-origin.sh"
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

is_changed_or_branch_scope() {
    [[ "$AI_VERIFY_SCOPE" == "changed" || "$AI_VERIFY_SCOPE" == "branch" ]]
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
