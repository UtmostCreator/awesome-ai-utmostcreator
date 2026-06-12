#!/usr/bin/env bash
set -euo pipefail

# shellcheck disable=SC1091
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

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
Usage:
    ai-search.sh MODE [QUERY] [root] [flags]

File-list modes:
    changed-files | staged-files
    changed | staged        deprecated aliases; allowed unless AI_SEARCH_STRICT=1

Content-search modes:
    text | tracked | files | struct | docs
    changed-text | staged-text

Other modes:
    doctor | unsafe-all

Flags:
    --fixed                 literal fixed-string match
    --absolute              add absolute_path to structured results
    --ignore-case | -i      case-insensitive match
    --max-results N         cap returned matches; default 100
    --files-with-matches    results[] of {path} only + summary{}; -l alias
    --count                 results[] of {path,count} + summary{}
    --count-matches         summary{} match totals only
    --dry-run               report dry_run without searching
    --help | -h             show this help
EOF
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
    *) return 1 ;;
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
# Phase 3C scope control.
case_mode="smart"      # smart | ignore | sensitive
pattern_mode="default" # default | fixed | regex | pcre2
max_depth=""
glob_args=()
type_args=()
exclude_args=()

if [[ "$mode" == "--help" || "$mode" == "-h" || -z "$mode" ]]; then
    usage
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
    --help | -h)
        usage
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
text | docs)
    rc=0
    out="$(rg --json "${case_args[@]}" "${rg_fixed_args[@]}" "${rg_scope_args[@]}" -- "$query" "$root" 2>/dev/null)" || rc=$?
    # rg: 0 = match, 1 = no match, 2 = error.
    [[ "$rc" -eq 2 ]] && fail "error" "search backend error (invalid regex or unreadable path): $query"
    ;;
files)
    fd_bin="$(find_fd_bin)"
    [[ -n "$fd_bin" ]] || fail "unavailable" "fd/fdfind not installed; files mode unavailable"
    out="$("$fd_bin" --hidden --exclude .git -- "$query" "$root" 2>/dev/null || true)"
    ;;
struct)
    command_exists ast-grep || fail "unavailable" "ast-grep not installed; struct mode unavailable"
    out="$(ast-grep run --lang "${AI_LANG:-php}" --pattern "$query" "$root" 2>/dev/null || true)"
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
    text | docs)
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
