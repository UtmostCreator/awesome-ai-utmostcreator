#!/usr/bin/env bash
# 10-contract.sh — user-facing contract: usage() text and help summary.
#
# Purpose: hold the hand-written usage() help and the auto-generated contract
#   summary printer. Pure output helpers; no search logic, no global state.
# Allowed dependencies: PHP + sh-introspect for the optional summary (degrades
#   silently when absent). Resolves the entrypoint dir via AI_SEARCH_ENTRYPOINT.

# ai-search.sh — unified repository search entrypoint.
#
# JSON mode is activated by AI_OUTPUT=json. There is no --json flag.
#
# Envelope contract:
#   {
#     schema, status, tool, query, mode,
#     matches[], results[], warnings[], errors[],
#     limits{max_results}, meta{returned,truncated}
#   }
#
# `matches[]` is the legacy string array and must remain backward-compatible.
# `results[]` is the additive structured output introduced in Phase 3A.
#
# Status emitted by this script:
#   ok | no_matches | error | unavailable | dry_run | blocked

usage() {
    cat <<'EOF'
ai-search.sh — unified repository search entrypoint.

Usage:
    ai-search.sh MODE [QUERY] [root] [flags]

JSON output is activated by the AI_OUTPUT=json environment variable (no --json
flag). The envelope is: {schema,status,tool,query,mode,matches[],results[],
warnings[],errors[],limits,meta[,summary,symbols]}. Status is one of:
ok | no_matches | error | unavailable | dry_run | blocked.

Content-search modes (QUERY required; structured results[] via rg):
    text                    search a root (rg)
    tracked                 git-grep over tracked files (requires git root)
    changed-text            search only unstaged-changed files
    staged-text             search only staged files
    files                   filename search (fd; unavailable if fd absent)

Surface-scoped content modes (QUERY required; restricted file family):
    docs                    README*/CHANGELOG*/docs/**/*.md/*.rst/*.adoc
    tests                   tests/**/__tests__/**/*.test.*/*.spec.*/*Test.php
    config                  .env*/config/**/*.yaml|yml|json|toml|ini|nix/docker-compose*
    deps                    composer/package/lock files, flake.nix, go.mod, Cargo.toml, pyproject.toml

File-list modes (no QUERY; optional root):
    changed-files           list unstaged-changed files
    staged-files            list staged files
    changed | staged        deprecated aliases (warn; AI_SEARCH_STRICT=1 -> error)

Git-aware modes:
    diff QUERY              unstaged hunks; --staged or --base REF; results carry
                            path/marker/new_line/text/scope
    history QUERY           git log pickaxe (-S; --regex -> -G); --messages,
                            --patch; results carry commit/author/date/message/path

Curated no-query modes (optional root):
    todo                    TODO|FIXME|HACK|XXX|deprecated|temporary|workaround|legacy,
                            grouped by file with tag/line/text
    unsafe-patterns         curated risky patterns with rule + severity

Structural modes (ast-grep; unavailable if ast-grep absent):
    struct PATTERN          ast-grep pattern; --lang LANG (or AI_LANG, default php)
    symbols NAME            resolve a symbol; emits symbols[] (kind/name/path/start/end/language)
    class NAME              class definitions only (symbols[] with kind=class)

Other modes:
    doctor                  diagnostics{} of available/missing/warnings/root/git_available
    unsafe-all              approval-gated; always returns status=blocked

Flags:
  Pattern / case:
    --fixed                 literal fixed-string match
    --regex                 regex match (default)
    --pcre2                 PCRE2 regex
    --ignore-case | -i      case-insensitive
    --case-sensitive        force case-sensitive
    --smart-case            case-insensitive unless query has uppercase (default)
  Scope:
    --glob PATTERN          include glob (repeatable)
    --type NAME             rg type filter (repeatable)
    --exclude PATH          exclude path (repeatable; on top of default excludes
                            vendor,node_modules,dist,build,coverage,.git)
    --max-depth N           bound traversal depth
    --absolute              add absolute_path to structured results
  Ignore files (gitignore honored BY DEFAULT: local + parent + global gitignore,
  .git/info/exclude, and .ignore/.rgignore; the global gitignore is resolved from
  git core.excludesfile / $XDG_CONFIG_HOME/git/ignore and applied explicitly):
    --no-ignore             disable ALL ignore sources (local+parent+global+.ignore)
    --no-ignore-vcs         disable local + parent .gitignore (keep global)
    --no-ignore-global      disable only the global gitignore
    --no-ignore-parent      disable parent-directory ignore files
    --no-ignore-dot         disable .ignore / .rgignore files
    (applied to all rg-backed modes; files mode maps the supported subset to fd;
     tracked/changed-text/staged-text search an explicit file set so ignores
     do not apply.)
  Context (text/docs):
    --context N | -C N      N lines before+after
    --before-context N | -B N   N lines before match
    --after-context N | -A N    N lines after match
  Output shape:
    --files-with-matches | -l   results[] of {path} only + summary{}
    --count                 results[] of {path,count} + summary{}
    --count-matches         summary{} match totals only
  Bounds:
    --max-results N         cap returned matches; default 100; sets meta.truncated
    --max-bytes N           drop context payload past N bytes; sets meta.truncated
  Git-aware:
    --staged                diff: staged hunks
    --base REF              diff: against REF
    --messages              history: search commit messages
    --patch                 history: attach commit patch text
  Structural:
    --lang LANG             struct/symbols/class language
  Misc:
    --dry-run               report dry_run without searching
    --introspect            print full machine-readable JSON contract (sh-introspect)
    --help | -h             show this help

Examples:
    AI_OUTPUT=json bash scripts/ai/ai-search.sh text TenantResolver . --fixed
    AI_OUTPUT=json bash scripts/ai/ai-search.sh changed-text Tenant . --fixed
    AI_OUTPUT=json bash scripts/ai/ai-search.sh diff Needle . --fixed --staged
    AI_OUTPUT=json bash scripts/ai/ai-search.sh class UserService . --lang php
EOF
}

# Print the auto-derived compact contract summary (modes + param:type) beneath
# the hand-written usage() text. Falls back silently when the introspector or
# php is unavailable, so --help never breaks; it only ADDS a summary on success.
introspect_help_summary() {
    local here tool
    here="$(cd "$(dirname "${AI_SEARCH_ENTRYPOINT:-${BASH_SOURCE[0]}}")" && pwd)"
    tool="$here/../../tools/ai/sh-introspect.php"
    # The introspector inlines sourced modules, so the entrypoint yields the
    # full aggregated contract.
    [[ -f "$tool" ]] || return 0
    command -v "${PHP_BIN:-php}" >/dev/null 2>&1 || return 0
    printf '\n---\nQuick contract (auto-generated by sh-introspect):\n\n'
    "${PHP_BIN:-php}" "$tool" --format=help "$here/ai-search.sh" 2>/dev/null || true
    printf '\nMachine contract: bash scripts/ai/ai-search.sh --introspect\n'
}

# normalize_legacy_alias — map deprecated `changed`/`staged` modes to the
# canonical file-list modes, warning (or erroring under strict mode). Then
# short-circuit the approval-gated unsafe-all mode.
#
# This lives in the contract module (not the parser) on purpose: sh-introspect
# statically reads the deprecation case bodies below to derive the deprecated
# modes and their replacements for --introspect / --help. Keeping it here means
# the single-file introspection target (this module) carries the full contract.
#
# Runtime dependencies (fail/add_warning, set up by later modules) are only
# invoked when ai_search_main calls this, by which point all modules are loaded.
# shellcheck disable=SC2034,SC2154
normalize_legacy_alias() {
    original_mode="$mode"
    legacy_alias=0

    case "$mode" in
    changed)
        legacy_alias=1
        if [[ "${AI_SEARCH_STRICT:-0}" == "1" ]]; then
            fail "error" "mode 'changed' is deprecated; use 'changed-files' for file lists or 'changed-text' for content search"
        fi
        add_warning "mode 'changed' is deprecated; use 'changed-files' for file lists or 'changed-text' for content search"
        mode="changed-files"
        ;;
    staged)
        legacy_alias=1
        if [[ "${AI_SEARCH_STRICT:-0}" == "1" ]]; then
            fail "error" "mode 'staged' is deprecated; use 'staged-files' for file lists or 'staged-text' for content search"
        fi
        add_warning "mode 'staged' is deprecated; use 'staged-files' for file lists or 'staged-text' for content search"
        mode="staged-files"
        ;;
    esac

    g_mode="$mode"

    # unsafe-all is approval-gated and never executes a scan here.
    if [[ "$mode" == "unsafe-all" ]]; then
        fail "blocked" "unsafe-all requires approval" 0
    fi
}
