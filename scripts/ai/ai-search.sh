#!/usr/bin/env bash
set -euo pipefail

# Early --help|-h delegate: render the JSON-driven help BEFORE sourcing any
# runtime dependencies or dispatching search logic. This mirrors the
# --introspect guard below but emits the human-readable --format=help view.
# It never executes search, never sources common.sh, and does not require a git
# repo or rg/fd/ast-grep. If PHP or the introspector is unavailable it prints a
# minimal fallback rather than crashing.
if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    _here="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
    _introspector="$_here/../../tools/ai/sh-introspect.php"
    _php_bin="${PHP_BIN:-php}"
    if command -v "$_php_bin" >/dev/null 2>&1 && [[ -f "$_introspector" ]]; then
        exec "$_php_bin" "$_introspector" --format=help "$_here/ai-search.sh"
    fi
    # Minimal fallback (no PHP / no introspector available).
    echo "ai-search.sh — unified repository search entrypoint"
    echo "Usage: ai-search.sh MODE [QUERY] [root] [flags]"
    echo "Run with --introspect for the machine-readable JSON contract."
    exit 0
fi

# shellcheck disable=SC1091
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

# Self-introspection: machine-readable contract for this script.
# Never executes search logic; delegates to the static introspector and
# replaces this process so no normal mode dispatch runs.
if [[ "${1:-}" == "--introspect" ]]; then
    _here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    exec env AI_OUTPUT=json "${PHP_BIN:-php}" \
        "$_here/../../tools/ai/sh-introspect.php" "$_here/ai-search.sh"
fi

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
    here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    tool="$here/../../tools/ai/sh-introspect.php"
    [[ -f "$tool" ]] || return 0
    command -v "${PHP_BIN:-php}" >/dev/null 2>&1 || return 0
    printf '\n---\nQuick contract (auto-generated by sh-introspect):\n\n'
    "${PHP_BIN:-php}" "$tool" --format=help "$here/ai-search.sh" 2>/dev/null || true
    printf '\nMachine contract: bash scripts/ai/ai-search.sh --introspect\n'
}

DEFAULT_MAX_RESULTS=100

json_mode="${AI_OUTPUT:-}"

# Non-fatal advisories accumulated during a run.
g_warnings=()

add_warning() {
    g_warnings+=("$1")
}

# to_json_array ITEMS... — render arguments as a JSON string array, dropping
# empty entries. Prints [] when called with no arguments.
to_json_array() {
    if [[ "$#" -eq 0 ]]; then
        printf '[]'
    else
        printf '%s\n' "$@" | jq -R -s 'split("\n") | map(select(length > 0))'
    fi
}

# Mode families. File-list modes take no query; content modes require one.
is_file_list_mode() {
    case "$1" in
    changed-files | staged-files) return 0 ;;
    *) return 1 ;;
    esac
}

is_content_mode() {
    case "$1" in
    text | tracked | files | struct | docs | changed-text | staged-text) return 0 ;;
    # Phase 4 query-required repo-aware modes.
    diff | history | tests | config | deps) return 0 ;;
    # Phase 5 structural modes take a pattern/name as the query.
    symbols | class) return 0 ;;
    *) return 1 ;;
    esac
}

# Phase 5 structural (ast-grep) modes.
is_ast_mode() {
    case "$1" in
    struct | symbols | class) return 0 ;;
    *) return 1 ;;
    esac
}

# Phase 4 modes that take no query and an optional root only.
is_no_query_mode() {
    case "$1" in
    todo | unsafe-patterns) return 0 ;;
    *) return 1 ;;
    esac
}

# Phase 4 surface-scoped text modes: search like `text` but restricted to a
# fixed glob set. `docs` is split out from `text` so it is truly scoped.
is_surface_mode() {
    case "$1" in
    docs | tests | config | deps) return 0 ;;
    *) return 1 ;;
    esac
}

# surface_globs MODE — print the rg --glob include patterns for a surface mode,
# one per line. Used to restrict docs/tests/config/deps to their file families.
surface_globs() {
    case "$1" in
    docs)
        printf '%s\n' 'README*' 'CHANGELOG*' '*.md' '*.rst' '*.adoc' 'docs/**'
        ;;
    tests)
        printf '%s\n' 'tests/**' '__tests__/**' '*.test.*' '*.spec.*' '*Test.php'
        ;;
    config)
        printf '%s\n' '.env*' 'config/**' '*.yaml' '*.yml' '*.json' '*.toml' \
            '*.ini' '*.nix' 'docker-compose*'
        ;;
    deps)
        printf '%s\n' 'composer.json' 'composer.lock' 'package.json' \
            'package-lock.json' 'pnpm-lock.yaml' 'yarn.lock' 'flake.nix' \
            'go.mod' 'Cargo.toml' 'pyproject.toml'
        ;;
    esac
}

# emit_json STATUS [MATCHES_JSON] [ERRORS_JSON] [WARNINGS_JSON]
# Renders the canonical envelope. Warnings default to g_warnings.
emit_json() {
    local status="$1" matches_json="${2:-[]}" errors="${3:-[]}" warnings="${4:-}"
    local returned truncated="${g_truncated:-false}"

    if [[ -z "$warnings" ]]; then
        warnings="$(to_json_array "${g_warnings[@]}")"
    fi

    returned="$(printf '%s' "$matches_json" | jq 'length')"

    jq -cn \
        --arg schema "1" \
        --arg status "$status" \
        --arg tool "ai-search" \
        --arg query "${g_query:-}" \
        --arg mode "${g_mode:-}" \
        --argjson matches "$matches_json" \
        --argjson results "${g_results_json:-[]}" \
        --argjson errors "$errors" \
        --argjson warnings "$warnings" \
        --argjson max_results "$g_max_results" \
        --argjson returned "$returned" \
        --argjson truncated "$truncated" \
        --argjson summary "${g_summary_json:-null}" \
        '{
            schema: $schema,
            status: $status,
            tool: $tool,
            query: $query,
            mode: $mode,
            matches: $matches,
            results: $results,
            warnings: $warnings,
            errors: $errors,
            limits: { max_results: $max_results },
            meta: { returned: $returned, truncated: $truncated }
        }
        | if $summary != null then .summary = $summary else . end'
}

# fail STATUS MESSAGE [RC] — emit an error/blocked/unavailable envelope in JSON
# mode, or a plain stderr line otherwise, then exit.
fail() {
    local status="$1" msg="$2" rc="${3:-1}"

    if [[ "$json_mode" == "json" ]]; then
        emit_json "$status" "[]" "$(jq -cn --arg m "$msg" '[$m]')"
    else
        log_error "$msg"
    fi

    exit "$rc"
}

# lines_to_matches — turn newline-delimited backend output into a JSON string
# array, dropping empty lines.
lines_to_matches() {
    jq -R -s 'split("\n") | map(select(length > 0))'
}

canonical_root() {
    local root_input="$1"

    if git -C "$root_input" rev-parse --show-toplevel >/dev/null 2>&1; then
        git -C "$root_input" rev-parse --show-toplevel
    else
        (
            cd "$root_input" 2>/dev/null && pwd -P
        )
    fi
}

# Build additive structured results[] from line-oriented grep output:
#   path:line:text
#
# This deliberately keeps matches[] unchanged. It uses a greedy path capture
# before :LINE: so paths containing colons remain valid.
lines_to_structured_results() {
    local source_tool="$1"
    local root_abs="$2"

    jq -R -s \
        --arg mode "$g_mode" \
        --arg source_tool "$source_tool" \
        --arg root "$root_abs" \
        --arg absolute "$absolute" '
        def as_string:
          if type == "string" then . else "" end;

        def lang($p):
          ($p | as_string) as $s
          | if ($s | endswith(".php")) then "php"
            elif ($s | endswith(".js")) then "js"
            elif ($s | endswith(".jsx")) then "jsx"
            elif ($s | endswith(".ts")) then "ts"
            elif ($s | endswith(".tsx")) then "tsx"
            elif ($s | endswith(".json")) then "json"
            elif (($s | endswith(".yml")) or ($s | endswith(".yaml"))) then "yaml"
            elif ($s | endswith(".md")) then "markdown"
            elif ($s | endswith(".rst")) then "rst"
            elif ($s | endswith(".adoc")) then "asciidoc"
            elif ($s | endswith(".nix")) then "nix"
            elif (($s | endswith(".sh")) or ($s | endswith(".bash"))) then "shell"
            else null
            end;

        def relpath($p):
          ($p | as_string) as $s
          | if ($root != "" and ($s | startswith($root + "/"))) then
              $s[($root|length + 1):]
            else
              $s
            end;

        split("\n")
        | map(select(length > 0))
        | map(capture("^(?<raw_path>.*):(?<line>[0-9]+):(?<text>.*)$")?)
        | map(select(. != null and (.raw_path? | type == "string") and (.line? | type == "string")))
        | map(
            .path = relpath(.raw_path)
            | .line = (.line | tonumber)
            | .column = 1
            | .mode = $mode
            | .source_tool = $source_tool
            | .root = $root
            | .language = lang(.path)
            | if ($absolute == "1") then
                .absolute_path = (
                  if ((.raw_path | as_string) | startswith("/")) then
                    .raw_path
                  else
                    $root + "/" + .path
                  end
                )
              else
                .
              end
            | del(.raw_path)
          )
    '
}

# Shared jq prelude: language detection + repo-relative path. Reused by the
# rg --json parsers below.
_rg_json_jq_prelude='
        def as_string: if type == "string" then . else "" end;
        def lang($p):
          ($p | as_string) as $s
          | if ($s | endswith(".php")) then "php"
            elif ($s | endswith(".js")) then "js"
            elif ($s | endswith(".jsx")) then "jsx"
            elif ($s | endswith(".ts")) then "ts"
            elif ($s | endswith(".tsx")) then "tsx"
            elif ($s | endswith(".json")) then "json"
            elif (($s | endswith(".yml")) or ($s | endswith(".yaml"))) then "yaml"
            elif ($s | endswith(".md")) then "markdown"
            elif ($s | endswith(".rst")) then "rst"
            elif ($s | endswith(".adoc")) then "asciidoc"
            elif ($s | endswith(".nix")) then "nix"
            elif (($s | endswith(".sh")) or ($s | endswith(".bash"))) then "shell"
            else null
            end;
        def relpath($p):
          ($p | as_string) as $s
          | if ($root != "" and ($s | startswith($root + "/"))) then $s[($root|length + 1):]
            else $s end;
        [ splits("\n") | select(length > 0) | (fromjson? // empty) ]
        | map(select(.type == "match"))
'

# rg_json_to_results — parse an `rg --json` stream into structured result
# objects with accurate 1-based column from submatch byte offsets.
rg_json_to_results() {
    local source_tool="$1" root_abs="$2"
    jq -s -R \
        --arg mode "$g_mode" \
        --arg source_tool "$source_tool" \
        --arg root "$root_abs" \
        --arg absolute "$absolute" \
        "$_rg_json_jq_prelude"'
        | map(
            .data as $d
            | ($d.path.text) as $raw
            | {
                path: relpath($raw),
                line: $d.line_number,
                column: (((($d.submatches[0]?.start) // 0) | floor) + 1),
                text: (($d.lines.text | as_string) | rtrimstr("\n")),
                mode: $mode,
                source_tool: $source_tool,
                root: $root,
                language: lang($raw)
              }
            | if ($absolute == "1") then
                .absolute_path = (if ($raw | startswith("/")) then $raw else ($root + "/" + .path) end)
              else . end
          )
        '
}

# rg_json_to_matches — legacy "path:line:text" string array from an rg --json
# stream (paths come from the JSON, so colon-in-filename is safe).
rg_json_to_matches() {
    jq -s -R "$_rg_json_jq_prelude"'
        | map(
            (.data.path.text)
            + ":" + (.data.line_number | tostring)
            + ":" + ((.data.lines.text | if type=="string" then . else "" end) | rtrimstr("\n"))
          )
    '
}

validate_non_negative_int() {
    local flag="$1"
    local value="$2"

    if [[ ! "$value" =~ ^[0-9]+$ ]]; then
        fail "error" "$flag requires a non-negative integer"
    fi
}

context_lines_json() {
    local file="$1"
    local start="$2"
    local end="$3"

    if [[ "$start" -gt "$end" || ! -f "$file" ]]; then
        printf '[]'
        return 0
    fi

    awk -v start="$start" -v end="$end" '
        NR >= start && NR <= end {
            printf "%d\t%s\n", NR, $0
        }
    ' "$file" | jq -R -s '
        split("\n")
        | map(select(length > 0))
        | map(
            capture("^(?<line>[0-9]+)\t(?<text>.*)$")
            | .line = (.line | tonumber)
          )
    '
}

add_context_to_results() {
    local root_abs="$1"
    local results_json="$2"
    local result path line file before_start before_end after_start after_end
    local before_json after_json enriched first output

    if [[ "$context_before" -eq 0 && "$context_after" -eq 0 ]]; then
        printf '%s' "$results_json"
        return 0
    fi

    first=1
    output="["

    while IFS= read -r result; do
        [[ -n "$result" ]] || continue

        path="$(printf '%s' "$result" | jq -r '.path // ""')"
        line="$(printf '%s' "$result" | jq -r '.line // 0')"

        if [[ -z "$path" || "$line" -le 0 ]]; then
            before_json="[]"
            after_json="[]"
        else
            file="$root_abs/$path"

            before_start=$((line - context_before))
            before_end=$((line - 1))
            after_start=$((line + 1))
            after_end=$((line + context_after))

            [[ "$before_start" -lt 1 ]] && before_start=1

            before_json="$(context_lines_json "$file" "$before_start" "$before_end")"
            after_json="$(context_lines_json "$file" "$after_start" "$after_end")"
        fi

        enriched="$(
            jq -cn \
                --argjson result "$result" \
                --argjson before "$before_json" \
                --argjson after "$after_json" \
                '$result + { context: { before: $before, after: $after } }'
        )"

        if [[ "$first" -eq 1 ]]; then
            output+="$enriched"
            first=0
        else
            output+=",$enriched"
        fi
    done < <(printf '%s' "$results_json" | jq -c '.[]')

    output+="]"
    printf '%s' "$output"
}

apply_max_bytes_to_results() {
    local results_json="$1"
    local bytes

    if [[ "$max_bytes" -eq 0 ]]; then
        printf '%s' "$results_json"
        return 0
    fi

    bytes="$(printf '%s' "$results_json" | wc -c | tr -d ' ')"

    if [[ "$bytes" -le "$max_bytes" ]]; then
        printf '%s' "$results_json"
        return 0
    fi

    g_truncated=true

    # Minimal safe truncation for Phase 3B: preserve result objects and match
    # identity, but remove bulky context payload.
    printf '%s' "$results_json" | jq '
        map(
            if has("context") then
                .context.before = [] | .context.after = []
            else
                .
            end
        )
    '
}

# ---------------------------------------------------------------------------
# Mode dispatch setup
# ---------------------------------------------------------------------------
mode="${1:-}"
g_mode="$mode"
g_query=""
g_max_results="$DEFAULT_MAX_RESULTS"
g_truncated=false
g_results_json="[]"
absolute=0
context_before=0
context_after=0
max_bytes=0
# Phase 3D count / file-only output. One of: none | files | count | count-matches.
count_mode="none"
g_summary_json=""
# Phase 4 diff/history controls.
diff_staged=0
diff_base=""
history_messages=0
history_patch=0
# Phase 5 structural search language (falls back to AI_LANG, then php).
lang_flag=""
# Phase 3C scope control.
case_mode="smart"      # smart | ignore | sensitive
pattern_mode="default" # default | fixed | regex | pcre2
max_depth=""
glob_args=()
type_args=()
exclude_args=()
# Ignore-file control (rg-backed modes). By DEFAULT all gitignore sources are
# honored: local .gitignore, parent .gitignore, .git/info/exclude, the global
# gitignore (git core.excludesfile), and .ignore/.rgignore files. These flags
# selectively disable those sources to surface otherwise-ignored files.
ignore_args=()

if [[ "$mode" == "--help" || "$mode" == "-h" || -z "$mode" ]]; then
    usage
    introspect_help_summary
    exit 0
fi

# doctor takes no query/root; report real tool diagnostics.
if [[ "$mode" == "doctor" ]]; then
    if [[ "$json_mode" != "json" ]]; then
        echo "ai-search doctor: ok"
        exit 0
    fi

    available=()
    missing=()
    warnings=()

    for tool in jq git rg ast-grep; do
        if command_exists "$tool"; then
            available+=("$tool")
        else
            missing+=("$tool")
        fi
    done

    fd_bin="$(find_fd_bin)"
    if [[ -n "$fd_bin" ]]; then
        available+=("$fd_bin")
    else
        warnings+=("fd/fdfind not found; files mode degraded")
    fi

    root_dir="."
    git_available=false
    if git -C "$root_dir" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
        git_available=true
    fi

    jq -cn \
        --arg schema "1" \
        --arg tool "ai-search" \
        --argjson available "$(printf '%s\n' "${available[@]}" | jq -R -s 'split("\n")|map(select(length>0))')" \
        --argjson missing "$(printf '%s\n' "${missing[@]:-}" | jq -R -s 'split("\n")|map(select(length>0))')" \
        --argjson warnings "$(printf '%s\n' "${warnings[@]:-}" | jq -R -s 'split("\n")|map(select(length>0))')" \
        --arg root "$root_dir" \
        --argjson git_available "$git_available" \
        '{
            schema: $schema,
            status: "ok",
            tool: $tool,
            mode: "doctor",
            diagnostics: {
                available: $available,
                missing: $missing,
                warnings: $warnings,
                root: $root,
                git_available: $git_available
            }
        }'

    exit 0
fi

# ---------------------------------------------------------------------------
# Argument parser. Flags are accepted in any position. Positionals are
# interpreted per mode family after legacy-alias normalization.
# ---------------------------------------------------------------------------
shift # consume MODE

positionals=()
dry_run=0

while [[ $# -gt 0 ]]; do
    case "$1" in
    --fixed)
        pattern_mode="fixed"
        ;;
    --regex)
        pattern_mode="regex"
        ;;
    --pcre2)
        pattern_mode="pcre2"
        ;;
    --absolute)
        absolute=1
        ;;
    --ignore-case | -i)
        case_mode="ignore"
        ;;
    --case-sensitive)
        case_mode="sensitive"
        ;;
    --smart-case)
        case_mode="smart"
        ;;
    --glob)
        shift
        [[ -n "${1:-}" ]] || fail "error" "--glob requires a pattern"
        glob_args+=("$1")
        ;;
    --type)
        shift
        [[ -n "${1:-}" ]] || fail "error" "--type requires a type name"
        type_args+=("$1")
        ;;
    --exclude)
        shift
        [[ -n "${1:-}" ]] || fail "error" "--exclude requires a path"
        exclude_args+=("$1")
        ;;
    --max-depth)
        shift
        validate_non_negative_int "--max-depth" "${1:-}"
        max_depth="$1"
        ;;
    --no-ignore)
        # Disable ALL ignore sources (local + parent + global gitignore,
        # .git/info/exclude, and .ignore/.rgignore files).
        ignore_args+=(--no-ignore)
        ;;
    --no-ignore-vcs)
        # Disable local + parent .gitignore and .git/info/exclude only;
        # the global gitignore is still honored.
        ignore_args+=(--no-ignore-vcs)
        ;;
    --no-ignore-global)
        # Disable only the global gitignore (git core.excludesfile);
        # local/parent .gitignore are still honored.
        ignore_args+=(--no-ignore-global)
        ;;
    --no-ignore-parent)
        # Disable .gitignore/.ignore files in parent directories only.
        ignore_args+=(--no-ignore-parent)
        ;;
    --no-ignore-dot)
        # Disable .ignore and .rgignore files (keep gitignore behavior).
        ignore_args+=(--no-ignore-dot)
        ;;
    --dry-run)
        dry_run=1
        ;;
    --context | -C)
        shift
        validate_non_negative_int "--context" "${1:-}"
        context_before="$1"
        context_after="$1"
        ;;
    --before-context | -B)
        shift
        validate_non_negative_int "--before-context" "${1:-}"
        context_before="$1"
        ;;
    --after-context | -A)
        shift
        validate_non_negative_int "--after-context" "${1:-}"
        context_after="$1"
        ;;
    --max-bytes)
        shift
        validate_non_negative_int "--max-bytes" "${1:-}"
        max_bytes="$1"
        ;;
    --max-results)
        shift
        validate_non_negative_int "--max-results" "${1:-}"
        g_max_results="$1"
        ;;
    --files-with-matches | -l)
        count_mode="files"
        ;;
    --count)
        count_mode="count"
        ;;
    --count-matches)
        count_mode="count-matches"
        ;;
    --staged)
        diff_staged=1
        ;;
    --base)
        shift
        [[ -n "${1:-}" ]] || fail "error" "--base requires a ref"
        diff_base="$1"
        ;;
    --messages)
        history_messages=1
        ;;
    --patch)
        history_patch=1
        ;;
    --lang)
        shift
        [[ -n "${1:-}" ]] || fail "error" "--lang requires a language"
        lang_flag="$1"
        ;;
    --help | -h)
        usage
        introspect_help_summary
        exit 0
        ;;
    --*)
        fail "error" "unknown flag: $1"
        ;;
    *)
        positionals+=("$1")
        ;;
    esac
    shift
done

# ---------------------------------------------------------------------------
# Legacy alias normalization.
# ---------------------------------------------------------------------------
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

# ---------------------------------------------------------------------------
# Positional interpretation by mode family.
# ---------------------------------------------------------------------------
query=""
root="."

if is_file_list_mode "$mode" && [[ "$legacy_alias" -eq 0 ]]; then
    # Canonical file-list modes take an optional root and never a query.
    if [[ ${#positionals[@]} -gt 1 ]]; then
        fail "error" "mode '$mode' does not accept a query; usage: ai-search.sh $mode [root] [flags]"
    fi

    if [[ ${#positionals[@]} -eq 1 ]]; then
        root="${positionals[0]}"
    fi

elif is_file_list_mode "$mode" && [[ "$legacy_alias" -eq 1 ]]; then
    # Legacy `changed`/`staged`: tolerate an ignored leading query so existing
    # callers like `changed dummy .` keep working during migration.
    case "${#positionals[@]}" in
    0)
        root="."
        ;;
    1)
        if [[ -d "${positionals[0]}" ]]; then
            root="${positionals[0]}"
        else
            root="."
        fi
        ;;
    2)
        root="${positionals[1]}"
        ;;
    *)
        fail "error" "too many positional arguments for legacy mode '$original_mode'"
        ;;
    esac

elif is_content_mode "$mode"; then
    if [[ ${#positionals[@]} -lt 1 ]]; then
        fail "error" "query required for mode: $mode"
    fi

    if [[ ${#positionals[@]} -gt 2 ]]; then
        fail "error" "too many positional arguments"
    fi

    query="${positionals[0]}"

    if [[ ${#positionals[@]} -eq 2 ]]; then
        root="${positionals[1]}"
    fi

elif is_no_query_mode "$mode"; then
    # todo / unsafe-patterns: optional root, never a query.
    if [[ ${#positionals[@]} -gt 1 ]]; then
        fail "error" "mode '$mode' does not accept a query; usage: ai-search.sh $mode [root] [flags]"
    fi

    if [[ ${#positionals[@]} -eq 1 ]]; then
        root="${positionals[0]}"
    fi

else
    fail "error" "unknown mode: $mode"
fi

g_query="$query"

if [[ "$dry_run" -eq 1 ]]; then
    if [[ "$json_mode" == "json" ]]; then
        emit_json "dry_run"
    else
        echo "dry-run"
    fi
    exit 0
fi

# Phase 3C case control. Default is smart-case (case-insensitive unless the
# query contains uppercase), matching rg's native --smart-case.
case_args=()
case "$case_mode" in
ignore) case_args=(--ignore-case) ;;
sensitive) case_args=(--case-sensitive) ;;
smart | *) case_args=(--smart-case) ;;
esac

# Phase 3C pattern control: literal (--fixed), regex (default/--regex), or PCRE2.
rg_fixed_args=()
case "$pattern_mode" in
fixed) rg_fixed_args=(--fixed-strings) ;;
pcre2) rg_fixed_args=(--pcre2) ;;
*) : ;;
esac

# Global gitignore robustness. rg only auto-reads the global gitignore from
# git's GLOBAL/system config (or $XDG_CONFIG_HOME/git/ignore), not a repo-local
# core.excludesfile. To honor the global gitignore deterministically, resolve it
# and pass it via --ignore-file. Skipped when the user disabled global or all
# ignore sources.
resolve_global_gitignore() {
    local f
    f="$(git config --get core.excludesfile 2>/dev/null || true)"
    if [[ -z "$f" ]]; then
        f="${XDG_CONFIG_HOME:-$HOME/.config}/git/ignore"
    fi
    # Expand a leading literal ~ to $HOME. git stores core.excludesfile verbatim,
    # so a configured "~/path" arrives as a literal tilde that we must expand
    # ourselves. SC2088 warns about tilde-in-quotes, but matching the literal
    # prefix is exactly the intent here.
    # shellcheck disable=SC2088
    case "$f" in
    "~/"*) f="$HOME/${f#"~/"}" ;;
    esac
    if [[ -f "$f" ]]; then
        printf '%s' "$f"
    fi
    # Always succeed: an absent global gitignore is normal, not an error. The
    # trailing `[[ ]] && cmd` footgun under `set -e` would otherwise abort.
    return 0
}

ignore_disables_global=0
for _ia in "${ignore_args[@]+"${ignore_args[@]}"}"; do
    [[ "$_ia" == "--no-ignore" || "$_ia" == "--no-ignore-global" ]] && ignore_disables_global=1
done
if [[ "$ignore_disables_global" -eq 0 ]]; then
    global_gitignore="$(resolve_global_gitignore)"
    if [[ -n "$global_gitignore" ]]; then
        ignore_args+=(--ignore-file "$global_gitignore")
    fi
fi

# Directories excluded by default; callers can extend via --exclude.
DEFAULT_EXCLUDES=(vendor node_modules dist build coverage)

# rg_scope_args — assemble glob/type/exclude/max-depth filters for content
# searches. Emitted into a global array so multiple backends can reuse it.
rg_scope_args=()
build_rg_scope_args() {
    rg_scope_args=()
    local g t e d
    for g in "${glob_args[@]+"${glob_args[@]}"}"; do
        rg_scope_args+=(--glob "$g")
    done
    for t in "${type_args[@]+"${type_args[@]}"}"; do
        rg_scope_args+=(--type "$t")
    done
    for e in "${exclude_args[@]+"${exclude_args[@]}"}"; do
        rg_scope_args+=(--glob "!$e" --glob "!$e/**")
    done
    for d in "${DEFAULT_EXCLUDES[@]}"; do
        rg_scope_args+=(--glob "!$d" --glob "!$d/**")
    done
    if [[ -n "$max_depth" ]]; then
        rg_scope_args+=(--max-depth "$max_depth")
    fi
    return 0
}
build_rg_scope_args

# ---------------------------------------------------------------------------
# Core-tool guards. A missing core backend must be a hard `error`, not a silent
# `no_matches` (the backend commands suppress stderr, so a missing tool would
# otherwise collapse to rc!=2 and look like an empty result set).
# ---------------------------------------------------------------------------
mode_needs_rg() {
    case "$1" in
    text | docs | tests | config | deps | changed-text | staged-text | \
        todo | unsafe-patterns) return 0 ;;
    *) return 1 ;;
    esac
}

mode_needs_git() {
    case "$1" in
    changed-files | staged-files | changed-text | staged-text | tracked | \
        diff | history) return 0 ;;
    *) return 1 ;;
    esac
}

if mode_needs_rg "$mode" && ! command_exists rg; then
    fail "error" "required tool 'rg' (ripgrep) not found on PATH; mode '$mode' unavailable"
fi

if mode_needs_git "$mode" && ! command_exists git; then
    fail "error" "required tool 'git' not found on PATH; mode '$mode' unavailable"
fi

# ---------------------------------------------------------------------------
# Backends.
# ---------------------------------------------------------------------------
require_git_root() {
    git -C "$root" rev-parse --is-inside-work-tree >/dev/null 2>&1 ||
        fail "error" "not a git repository: $root"
}

# search_git_scoped_files SCOPE — content search restricted to the changed or
# staged file set, run from the repository root so reported paths are
# repo-relative. Sets the global `out`.
search_git_scoped_files() {
    local scope="$1" repo_root rc=0
    local files=()
    local cleaned=()
    local f

    repo_root="$(git -C "$root" rev-parse --show-toplevel 2>/dev/null)" ||
        fail "error" "not a git repository: $root"

    case "$scope" in
    changed)
        mapfile -d '' files < <(git -C "$repo_root" diff --name-only -z --)
        ;;
    staged)
        mapfile -d '' files < <(git -C "$repo_root" diff --name-only --cached -z --)
        ;;
    *)
        fail "error" "unknown scoped search: $scope"
        ;;
    esac

    for f in "${files[@]}"; do
        [[ -n "$f" ]] && cleaned+=("$f")
    done

    if [[ ${#cleaned[@]} -eq 0 ]]; then
        out=""
        return 0
    fi

    # -H forces path prefixes even when a single file is searched, so matches
    # stay in the canonical "path:line:text" shape.
    out="$(cd "$repo_root" && rg "${case_args[@]}" "${rg_fixed_args[@]}" -H -n -- "$query" "${cleaned[@]}" 2>/dev/null)" || rc=$?

    if [[ "$rc" -eq 2 ]]; then
        fail "error" "search backend error in $scope files: $query"
    fi

    return 0
}

# ---------------------------------------------------------------------------
# Phase 4 bespoke modes. These build their own results[] shapes and emit
# directly, because their output does not fit the path:line:text pipeline.
# ---------------------------------------------------------------------------

# query_matches_line LINE — return 0 when the parsed query matches the given
# text under the active pattern/case mode. Used by diff/history line filters.
query_matches_line() {
    local line="$1" grep_args=()
    case "$pattern_mode" in
    fixed) grep_args+=(-F) ;;
    pcre2) grep_args+=(-P) ;;
    *) grep_args+=(-E) ;;
    esac
    case "$case_mode" in
    ignore) grep_args+=(-i) ;;
    sensitive) : ;;
    smart | *) [[ "$query" =~ [[:upper:]] ]] || grep_args+=(-i) ;;
    esac
    printf '%s' "$line" | grep -q "${grep_args[@]}" -- "$query"
}

run_diff_mode() {
    require_git_root
    local repo_root diff_out git_args=()
    repo_root="$(git -C "$root" rev-parse --show-toplevel 2>/dev/null)" ||
        fail "error" "not a git repository: $root"

    if [[ -n "$diff_base" ]]; then
        git_args=(diff "$diff_base")
    elif [[ "$diff_staged" -eq 1 ]]; then
        git_args=(diff --cached)
    else
        git_args=(diff)
    fi

    diff_out="$(cd "$repo_root" && git "${git_args[@]}" -U0 2>/dev/null || true)"

    # Walk the unified diff: track current file from +++ headers and the new
    # line number from @@ hunk headers; collect added lines matching the query.
    local results
    results="$(
        printf '%s\n' "$diff_out" | awk '
            /^\+\+\+ / {
                p = $2; sub(/^b\//, "", p); cur = p; next
            }
            /^@@ / {
                # @@ -a,b +c,d @@  -> new-file start = c
                match($0, /\+[0-9]+/); ns = substr($0, RSTART+1, RLENGTH-1);
                new_line = ns + 0; next
            }
            /^\+/ && !/^\+\+\+/ {
                text = substr($0, 2);
                printf "%s\t%d\t%s\n", cur, new_line, text;
                new_line++; next
            }
            /^ / { new_line++; next }
        '
    )"

    local result_objs=() path line text
    while IFS=$'\t' read -r path line text; do
        [[ -n "$path" ]] || continue
        query_matches_line "$text" || continue
        result_objs+=("$(jq -cn \
            --arg path "$path" --argjson new_line "$line" --arg text "$text" \
            '{path: $path, marker: "+", new_line: $new_line, text: $text}')")
    done <<<"$results"

    local scope="unstaged"
    [[ "$diff_staged" -eq 1 ]] && scope="staged"
    [[ -n "$diff_base" ]] && scope="base:$diff_base"

    g_results_json="$(printf '%s\n' "${result_objs[@]:-}" |
        jq -s 'map(select(. != null))')"
    g_results_json="$(printf '%s' "$g_results_json" |
        jq --arg scope "$scope" 'map(.scope = $scope)')"

    local matches_json status
    matches_json="$(printf '%s' "$g_results_json" |
        jq '[.[] | (.path + ":" + (.new_line|tostring) + ":" + .text)]')"
    status="ok"
    [[ "$(printf '%s' "$matches_json" | jq 'length')" -eq 0 ]] && status="no_matches"

    if [[ "$json_mode" == "json" ]]; then
        g_summary_json="$(printf '%s' "$g_results_json" |
            jq -c '{scope: (.[0].scope // null)}')"
        emit_json "$status" "$matches_json"
    else
        printf '%s' "$g_results_json" | jq -r '.[] | "\(.path):\(.new_line):\(.text)"'
    fi
    exit 0
}

run_history_mode() {
    require_git_root
    local repo_root log_args=() raw
    repo_root="$(git -C "$root" rev-parse --show-toplevel 2>/dev/null)" ||
        fail "error" "not a git repository: $root"

    # Field-separated commit metadata; %x1f unit separator, %x1e record sep.
    local fmt='%H%x1f%an%x1f%aI%x1f%s'

    if [[ "$history_messages" -eq 1 ]]; then
        log_args=(log "--grep=$query" "--format=$fmt")
        [[ "$pattern_mode" == "fixed" ]] && log_args+=(--fixed-strings)
        [[ "$case_mode" == "ignore" ]] && log_args+=(-i)
    elif [[ "$pattern_mode" == "regex" || "$pattern_mode" == "pcre2" ]]; then
        log_args=(log "-G$query" "--format=$fmt" --name-only)
    else
        # Default/fixed: -S pickaxe is literal by default.
        log_args=(log "-S$query" "--format=$fmt" --name-only)
    fi

    raw="$(cd "$repo_root" && git "${log_args[@]}" 2>/dev/null || true)"

    # Parse: a metadata line (contains \x1f) starts a commit; subsequent plain
    # lines are file paths (present when --name-only is used).
    local commits_json
    commits_json="$(
        printf '%s\n' "$raw" | jq -R -s --arg us $'\x1f' '
            split("\n")
            | reduce .[] as $line ({commits: [], cur: null};
                if ($line | contains($us)) then
                    (if .cur != null then .commits += [.cur] else . end)
                    | ($line | split($us)) as $f
                    | .cur = {
                        commit: $f[0], author: $f[1], date: $f[2],
                        message: $f[3], files: []
                      }
                elif ($line | length) > 0 and (.cur != null) then
                    .cur.files += [$line]
                else . end
              )
            | (if .cur != null then .commits += [.cur] else . end)
            | .commits
        '
    )"

    # Expand to one result per (commit, file). When no files (message search),
    # keep a single row with the commit-level path null.
    local results_json
    results_json="$(printf '%s' "$commits_json" | jq -c '
        map(
            . as $c
            | if (($c.files // []) | length) > 0 then
                ($c.files[] | { commit: $c.commit, author: $c.author,
                  date: $c.date, message: $c.message, path: . })
              else
                { commit: $c.commit, author: $c.author, date: $c.date,
                  message: $c.message, path: null }
              end
        )
    ')"

    if [[ "$history_patch" -eq 1 ]]; then
        # Attach the commit patch text on request only.
        local enriched=() row commit_hash patch
        while IFS= read -r row; do
            [[ -n "$row" ]] || continue
            commit_hash="$(printf '%s' "$row" | jq -r '.commit')"
            patch="$(cd "$repo_root" && git show --format= --patch "$commit_hash" 2>/dev/null || true)"
            enriched+=("$(printf '%s' "$row" | jq -c --arg p "$patch" '.patch = $p')")
        done < <(printf '%s' "$results_json" | jq -c '.[]')
        results_json="$(printf '%s\n' "${enriched[@]:-}" | jq -s 'map(select(. != null))')"
    fi

    g_results_json="$results_json"
    local matches_json status
    matches_json="$(printf '%s' "$g_results_json" |
        jq '[.[] | (.commit + " " + (.message // ""))]')"
    status="ok"
    [[ "$(printf '%s' "$g_results_json" | jq 'length')" -eq 0 ]] && status="no_matches"

    if [[ "$json_mode" == "json" ]]; then
        emit_json "$status" "$matches_json"
    else
        printf '%s' "$g_results_json" | jq -r '.[] | "\(.commit) \(.message)"'
    fi
    exit 0
}

run_todo_mode() {
    local tag_re='TODO|FIXME|HACK|XXX|deprecated|temporary|workaround|legacy'
    local out rc=0
    out="$(rg --json --ignore-case "${ignore_args[@]+"${ignore_args[@]}"}" "${rg_scope_args[@]}" -e "$tag_re" "$root" 2>/dev/null)" || rc=$?
    [[ "$rc" -eq 2 ]] && fail "error" "todo scan backend error"
    local root_abs
    root_abs="$(canonical_root "$root")"

    g_results_json="$(printf '%s' "$out" | jq -s -R \
        --arg root "$root_abs" '
        def relpath($p): ($p|if type=="string" then . else "" end) as $s
          | if ($root != "" and ($s | startswith($root + "/"))) then $s[($root|length+1):] else $s end;
        [ splits("\n") | select(length>0) | (fromjson? // empty) ]
        | map(select(.type == "match"))
        | map({
            path: relpath(.data.path.text),
            line: .data.line_number,
            text: (.data.lines.text | if type=="string" then . else "" end | rtrimstr("\n"))
          })
        | group_by(.path)
        | map({
            path: .[0].path,
            matches: map({
              tag: (
                (.text | ascii_downcase) as $lt
                | if ($lt|contains("todo")) then "TODO"
                  elif ($lt|contains("fixme")) then "FIXME"
                  elif ($lt|contains("hack")) then "HACK"
                  elif ($lt|contains("xxx")) then "XXX"
                  elif ($lt|contains("deprecated")) then "deprecated"
                  elif ($lt|contains("temporary")) then "temporary"
                  elif ($lt|contains("workaround")) then "workaround"
                  elif ($lt|contains("legacy")) then "legacy"
                  else null end
              ),
              line: .line,
              text: .text
            })
          })
    ')"

    local matches_json status
    matches_json="$(printf '%s' "$g_results_json" | jq '[.[].path]')"
    status="ok"
    [[ "$(printf '%s' "$g_results_json" | jq 'length')" -eq 0 ]] && status="no_matches"

    if [[ "$json_mode" == "json" ]]; then
        emit_json "$status" "$matches_json"
    else
        printf '%s' "$g_results_json" | jq -r '.[].path'
    fi
    exit 0
}

run_unsafe_patterns_mode() {
    # Curated risky patterns with a rule label and severity. Not a free scan.
    local rules=(
        'eval\(|rule=eval|high'
        'unserialize\(|rule=unserialize|high'
        'system\(|rule=system|high'
        'exec\(|rule=exec|high'
        'shell_exec\(|rule=shell_exec|high'
        'md5\(|rule=weak-hash|medium'
        'mt_rand\(|rule=weak-random|low'
    )
    local pattern_args=() spec re
    for spec in "${rules[@]}"; do
        re="${spec%%|rule=*}"
        pattern_args+=(-e "$re")
    done

    local out rc=0
    out="$(rg --json "${ignore_args[@]+"${ignore_args[@]}"}" "${rg_scope_args[@]}" "${pattern_args[@]}" "$root" 2>/dev/null)" || rc=$?
    [[ "$rc" -eq 2 ]] && fail "error" "unsafe-patterns scan backend error"
    local root_abs
    root_abs="$(canonical_root "$root")"

    g_results_json="$(printf '%s' "$out" | jq -s -R \
        --arg root "$root_abs" '
        def relpath($p): ($p|if type=="string" then . else "" end) as $s
          | if ($root != "" and ($s | startswith($root + "/"))) then $s[($root|length+1):] else $s end;
        def classify($t):
          if ($t|contains("eval(")) then {rule:"eval", severity:"high"}
          elif ($t|contains("unserialize(")) then {rule:"unserialize", severity:"high"}
          elif ($t|contains("system(")) then {rule:"system", severity:"high"}
          elif ($t|contains("shell_exec(")) then {rule:"shell_exec", severity:"high"}
          elif ($t|contains("exec(")) then {rule:"exec", severity:"high"}
          elif ($t|contains("md5(")) then {rule:"weak-hash", severity:"medium"}
          elif ($t|contains("mt_rand(")) then {rule:"weak-random", severity:"low"}
          else {rule:"unsafe", severity:"medium"} end;
        [ splits("\n") | select(length>0) | (fromjson? // empty) ]
        | map(select(.type == "match"))
        | map(
            (.data.lines.text | if type=="string" then . else "" end | rtrimstr("\n")) as $t
            | (classify($t)) as $c
            | {
                path: relpath(.data.path.text),
                line: .data.line_number,
                text: $t,
                rule: $c.rule,
                severity: $c.severity
              }
          )
    ')"

    local matches_json status
    matches_json="$(printf '%s' "$g_results_json" |
        jq '[.[] | (.path + ":" + (.line|tostring) + ":" + .rule)]')"
    status="ok"
    [[ "$(printf '%s' "$g_results_json" | jq 'length')" -eq 0 ]] && status="no_matches"

    if [[ "$json_mode" == "json" ]]; then
        emit_json "$status" "$matches_json"
    else
        printf '%s' "$g_results_json" | jq -r '.[] | "\(.path):\(.line):\(.rule)"'
    fi
    exit 0
}

# run_ast_mode — Phase 5 structural search via ast-grep, emitting structured
# results[] with name/kind/path/start/end/language.
run_ast_mode() {
    command_exists ast-grep ||
        fail "unavailable" "ast-grep not installed; $mode mode unavailable"

    local lang="${lang_flag:-${AI_LANG:-php}}"
    local pattern kind=""

    case "$mode" in
    struct)
        pattern="$query"
        ;;
    class)
        kind="class"
        pattern="class $query"
        ;;
    symbols)
        # Resolve a bare name to its definition. Default to class def; callers
        # use the dedicated shortcuts for other kinds.
        kind="class"
        pattern="class $query"
        ;;
    esac

    local out rc=0 root_abs
    out="$(ast-grep run --lang "$lang" --pattern "$pattern" --json "$root" 2>/dev/null)" || rc=$?
    root_abs="$(canonical_root "$root")"

    g_results_json="$(printf '%s' "$out" | jq -c \
        --argjson n "$g_max_results" \
        --arg mode "$g_mode" \
        --arg lang "$lang" \
        --arg kind "$kind" \
        --arg query "$query" \
        --arg root "$root_abs" '
        def relpath($p): ($p|if type=="string" then . else "" end) as $s
          | if ($root != "" and ($s | startswith($root + "/"))) then $s[($root|length+1):] else $s end;
        (if type == "array" then . else [] end)
        | .[:$n]
        | map({
            path: relpath(.file),
            text: .text,
            start: ((.range.start.line // 0) + 1),
            end: ((.range.end.line // 0) + 1),
            language: $lang,
            mode: $mode,
            source_tool: "ast-grep"
          }
          + (if $kind != "" then {
                kind: $kind,
                name: ((.metaVariables.single.NAME.text) // $query)
             } else {} end))
    ')"

    local matches_json status
    matches_json="$(printf '%s' "$g_results_json" |
        jq '[.[] | (.path + ":" + (.start|tostring) + ":" + (.name // .text))]')"
    status="ok"
    [[ "$(printf '%s' "$g_results_json" | jq 'length')" -eq 0 ]] && status="no_matches"

    if [[ "$mode" == "symbols" || "$mode" == "class" ]]; then
        # Symbol modes publish symbols[] in addition to results[].
        if [[ "$json_mode" == "json" ]]; then
            local symbols_json
            symbols_json="$g_results_json"
            jq -cn \
                --arg schema "1" --arg status "$status" --arg tool "ai-search" \
                --arg query "$g_query" --arg mode "$g_mode" \
                --argjson results "$g_results_json" \
                --argjson symbols "$symbols_json" \
                --argjson matches "$matches_json" \
                --argjson warnings "$(to_json_array "${g_warnings[@]+"${g_warnings[@]}"}")" \
                --argjson max_results "$g_max_results" '
                {
                    schema: $schema, status: $status, tool: $tool,
                    query: $query, mode: $mode,
                    matches: $matches, results: $results, symbols: $symbols,
                    warnings: $warnings, errors: [],
                    limits: { max_results: $max_results },
                    meta: { returned: ($results|length), truncated: false }
                }'
            exit 0
        fi
    fi

    if [[ "$json_mode" == "json" ]]; then
        emit_json "$status" "$matches_json"
    else
        printf '%s' "$g_results_json" | jq -r '.[] | "\(.path):\(.start):\(.text)"'
    fi
    exit 0
}

# Early dispatch for bespoke result shapes.
case "$mode" in
diff) run_diff_mode ;;
history) run_history_mode ;;
todo) run_todo_mode ;;
unsafe-patterns) run_unsafe_patterns_mode ;;
struct | symbols | class) run_ast_mode ;;
esac

case "$mode" in
changed-files)
    require_git_root
    out="$(git -C "$root" diff --name-only 2>/dev/null | tr -d '\r' || true)"
    ;;
staged-files)
    require_git_root
    out="$(git -C "$root" diff --name-only --cached 2>/dev/null | tr -d '\r' || true)"
    ;;
changed-text)
    require_git_root
    search_git_scoped_files changed
    ;;
staged-text)
    require_git_root
    search_git_scoped_files staged
    ;;
tracked)
    require_git_root
    # git grep does not support rg's --smart-case/--pcre2 spellings, so map the
    # case/pattern modes to git-grep-compatible flags here.
    git_grep_args=()
    case "$case_mode" in
    ignore) git_grep_args+=(-i) ;;
    sensitive) : ;;
    smart | *) [[ "$query" =~ [[:upper:]] ]] || git_grep_args+=(-i) ;;
    esac
    case "$pattern_mode" in
    fixed) git_grep_args+=(--fixed-strings) ;;
    pcre2) git_grep_args+=(-P) ;;
    *) : ;;
    esac
    rc=0
    out="$(git -C "$root" grep "${git_grep_args[@]}" -n -- "$query" 2>/dev/null)" || rc=$?
    # git grep: 0 = match, 1 = no match, >=2 = error.
    [[ "$rc" -ge 2 ]] && fail "error" "git grep error for query: $query"
    ;;
text)
    rc=0
    out="$(rg --json "${case_args[@]}" "${rg_fixed_args[@]}" "${ignore_args[@]+"${ignore_args[@]}"}" "${rg_scope_args[@]}" -- "$query" "$root" 2>/dev/null)" || rc=$?
    # rg: 0 = match, 1 = no match, 2 = error.
    [[ "$rc" -eq 2 ]] && fail "error" "search backend error (invalid regex or unreadable path): $query"
    ;;
docs | tests | config | deps)
    # Surface-scoped text search: same engine as `text` but restricted to the
    # mode's file family via include globs (default excludes still apply).
    surface_glob_args=()
    while IFS= read -r _sg; do
        [[ -n "$_sg" ]] && surface_glob_args+=(--glob "$_sg")
    done < <(surface_globs "$mode")
    rc=0
    out="$(rg --json "${case_args[@]}" "${rg_fixed_args[@]}" "${ignore_args[@]+"${ignore_args[@]}"}" "${rg_scope_args[@]}" "${surface_glob_args[@]}" -- "$query" "$root" 2>/dev/null)" || rc=$?
    [[ "$rc" -eq 2 ]] && fail "error" "search backend error (invalid regex or unreadable path): $query"
    ;;
files)
    fd_bin="$(find_fd_bin)"
    [[ -n "$fd_bin" ]] || fail "unavailable" "fd/fdfind not installed; files mode unavailable"
    # Translate the rg-style ignore flags to fd-compatible ones. fd shares
    # --no-ignore/--no-ignore-vcs/--no-ignore-parent; it has no separate
    # --no-ignore-global/--no-ignore-dot, so those map up to --no-ignore.
    fd_ignore_args=()
    for _ia in "${ignore_args[@]+"${ignore_args[@]}"}"; do
        case "$_ia" in
        --no-ignore | --no-ignore-vcs | --no-ignore-parent) fd_ignore_args+=("$_ia") ;;
        --no-ignore-global | --no-ignore-dot) fd_ignore_args+=(--no-ignore) ;;
        esac
    done
    out="$("$fd_bin" --hidden "${fd_ignore_args[@]+"${fd_ignore_args[@]}"}" --exclude .git -- "$query" "$root" 2>/dev/null || true)"
    ;;
*)
    fail "error" "unknown mode: $mode"
    ;;
esac

if [[ "$json_mode" == "json" ]]; then
    # Phase 3A/3B/3C: additive structured results for content searches.
    # text/docs come from an rg --json stream (accurate column, colon-safe
    # paths); tracked/changed-text/staged-text are line-oriented.
    case "$mode" in
    text | docs | tests | config | deps)
        root_abs="$(canonical_root "$root")"
        matches_json="$(printf '%s' "$out" | rg_json_to_matches)"
        g_results_json="$(printf '%s' "$out" | rg_json_to_results "rg" "$root_abs")"
        g_results_json="$(add_context_to_results "$root_abs" "$g_results_json")"

        if [[ "$max_bytes" -gt 0 ]]; then
            results_bytes="$(printf '%s' "$g_results_json" | wc -c | tr -d ' ')"

            if [[ "$results_bytes" -gt "$max_bytes" ]]; then
                g_truncated=true
                g_results_json="$(
                    printf '%s' "$g_results_json" | jq '
                        map(if has("context") then .context.before = [] | .context.after = [] else . end)
                    '
                )"
            fi
        fi
        ;;
    tracked | changed-text | staged-text)
        matches_json="$(printf '%s' "$out" | lines_to_matches)"
        root_abs="$(canonical_root "$root")"
        source_tool="rg"

        if [[ "$mode" == "tracked" ]]; then
            source_tool="git-grep"
        fi

        g_results_json="$(printf '%s' "$out" | lines_to_structured_results "$source_tool" "$root_abs")"
        g_results_json="$(add_context_to_results "$root_abs" "$g_results_json")"

        if [[ "$max_bytes" -gt 0 ]]; then
            results_bytes="$(printf '%s' "$g_results_json" | wc -c | tr -d ' ')"

            if [[ "$results_bytes" -gt "$max_bytes" ]]; then
                g_truncated=true

                # Preserve match identity, but remove bulky context payload.
                g_results_json="$(
                    printf '%s' "$g_results_json" | jq '
                        map(
                            if has("context") then
                                .context.before = [] | .context.after = []
                            else
                                .
                            end
                        )
                    '
                )"
            fi
        fi
        ;;
    *)
        # File-list and structural modes: plain string matches, no results[].
        matches_json="$(printf '%s' "$out" | lines_to_matches)"
        g_results_json="[]"
        ;;
    esac

    # Phase 3D: count / file-only output. Aggregate the structured results into
    # per-file rows and publish a summary, without dumping every match line.
    # `matches[]` (the legacy string array) is preserved unchanged.
    if [[ "$count_mode" != "none" ]]; then
        g_summary_json="$(
            printf '%s' "$g_results_json" | jq -c '
                {
                    total_files: ([.[].path] | unique | length),
                    total_matches: length
                }
            '
        )"

        case "$count_mode" in
        files)
            g_results_json="$(
                printf '%s' "$g_results_json" | jq -c '
                    [.[].path] | unique | map({ path: . })
                '
            )"
            ;;
        count)
            g_results_json="$(
                printf '%s' "$g_results_json" | jq -c '
                    group_by(.path)
                    | map({ path: .[0].path, count: length })
                '
            )"
            ;;
        count-matches)
            g_results_json="$(
                printf '%s' "$g_results_json" | jq -c '
                    group_by(.path)
                    | map({ path: .[0].path, count: length })
                '
            )"
            ;;
        esac
    fi

    count="$(printf '%s' "$matches_json" | jq 'length')"

    if [[ "$count" -gt "$g_max_results" ]]; then
        matches_json="$(printf '%s' "$matches_json" | jq --argjson n "$g_max_results" '.[:$n]')"
        # In count modes results[] are aggregated per-file rows, not per match
        # line, so the match-line cap must not truncate them.
        if [[ "$count_mode" == "none" ]]; then
            g_results_json="$(printf '%s' "$g_results_json" | jq --argjson n "$g_max_results" '.[:$n]')"
        fi
        g_truncated=true
    fi

    final="$(printf '%s' "$matches_json" | jq 'length')"

    if [[ "$final" -eq 0 ]]; then
        emit_json "no_matches" "$matches_json"
    else
        emit_json "ok" "$matches_json"
    fi
else
    printf '%s\n' "$out"
fi
