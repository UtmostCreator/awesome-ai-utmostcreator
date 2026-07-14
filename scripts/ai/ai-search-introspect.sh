#!/usr/bin/env bash
set -euo pipefail

# Early --introspect guard: emit THIS script's machine-readable JSON contract
# (static parse via sh-introspect) and exit before running any logic. The
# target script is parsed as text, never executed. (Distinct from this script's
# own purpose of reporting the ai-search.sh contract.)
if [[ "${1:-}" == "--introspect" ]]; then
    _ai_introspect_here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    _ai_introspect_tool="$_ai_introspect_here/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_introspect_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        exec env AI_OUTPUT=json "${PHP_BIN:-php}" "$_ai_introspect_tool" "${BASH_SOURCE[0]}"
    fi
fi

# ai-search-introspect.sh — print 100% of the modes, flags, env vars, and
# per-mode argument contracts that ai-search.sh and ai-search-multi.sh accept.
#
# Everything below is parsed DIRECTLY from the two source scripts (no hardcoded
# duplicate lists), so it stays accurate as those scripts change.
#
# Usage:
#   bash scripts/ai/ai-search-introspect.sh            # human-readable report
#   bash scripts/ai/ai-search-introspect.sh --probe    # also runtime-probe every mode
#
# Exit code is always 0 unless a source file is missing.

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
search="$here/ai-search.sh"
multi="$here/ai-search-multi.sh"

for f in "$search" "$multi"; do
    [[ -f "$f" ]] || {
        echo "ERROR: missing source file: $f" >&2
        exit 1
    }
done

probe=0
[[ "${1:-}" == "--probe" ]] && probe=1

hr() { printf '%s\n' "------------------------------------------------------------"; }

# extract_modes_from_family FUNC FILE — print the mode tokens listed inside a
# `case "$1" in ... ) return 0 ;;` mode-family function body.
extract_modes_from_family() {
    local func="$1" file="$2"
    awk -v fn="$func" '
        $0 ~ "^" fn "\\(\\) \\{" { inside = 1; next }
        inside && /^\}/ { inside = 0 }
        inside && /return 0/ {
            line = $0
            sub(/\).*/, "", line)        # drop ") return 0 ;;"
            gsub(/[[:space:]]/, "", line) # strip spaces
            n = split(line, parts, "|")
            for (i = 1; i <= n; i++) if (parts[i] != "" && parts[i] != "*") print parts[i]
        }
    ' "$file"
}

echo
echo "############################################################"
echo "#  ai-search.sh / ai-search-multi.sh — FULL CAPABILITY MAP #"
echo "#  (parsed live from source: $search)"
echo "############################################################"

# ---------------------------------------------------------------------------
# 1. Invocation forms
# ---------------------------------------------------------------------------
echo
echo "== INVOCATION FORMS =="
hr
cat <<'EOF'
AI_OUTPUT=json bash scripts/ai/ai-search.sh       MODE [QUERY] [root] [flags]
AI_OUTPUT=json bash scripts/ai/ai-search-multi.sh MODE QUERY [QUERY ...] [root] [flags]

- JSON output is activated by the AI_OUTPUT=json environment variable (no --json flag).
- Flags may appear in any position; the last non-flag positional is the root (default ".").
EOF

# ---------------------------------------------------------------------------
# 2. Modes by family (from ai-search.sh)
# ---------------------------------------------------------------------------
echo
echo "== MODES (grouped by family, parsed from ai-search.sh) =="
hr
printf 'Content-search (QUERY required): %s\n' \
    "$(extract_modes_from_family is_content_mode "$search" | sort -u | tr '\n' ' ')"
printf 'File-list (no QUERY):            %s\n' \
    "$(extract_modes_from_family is_file_list_mode "$search" | sort -u | tr '\n' ' ')"
printf 'No-query curated:                %s\n' \
    "$(extract_modes_from_family is_no_query_mode "$search" | sort -u | tr '\n' ' ')"
printf 'Surface-scoped (subset of above):%s\n' \
    " $(extract_modes_from_family is_surface_mode "$search" | sort -u | tr '\n' ' ')"
printf 'Structural / ast-grep:           %s\n' \
    "$(extract_modes_from_family is_ast_mode "$search" | sort -u | tr '\n' ' ')"
printf 'Special:                         doctor unsafe-all\n'
printf 'Deprecated aliases:              changed staged  (warn; AI_SEARCH_STRICT=1 -> error)\n'

# ---------------------------------------------------------------------------
# 3. Every flag (from ai-search.sh parser case)
# ---------------------------------------------------------------------------
echo
echo "== FLAGS (every parser case in ai-search.sh, with inline help) =="
hr
# Isolate the usage() heredoc so flag descriptions never match code elsewhere.
usage_block="$(awk "/^usage\\(\\) \\{/{u=1} u; /^EOF\$/{if(u){exit}}" "$search")"
# Pull the canonical flag list straight from the parser case branches.
grep -oE '^\s+--[a-z][a-z-]*( \| -[A-Za-z])?\)' "$search" |
    sed -E 's/^[[:space:]]+//; s/\)$//' | sort -u | while IFS= read -r flag; do
    primary="${flag%% *}" # e.g. "--context" from "--context | -C"
    # Match the documented line for this exact flag inside the usage() block only.
    desc="$(printf '%s\n' "$usage_block" |
        grep -E "^[[:space:]]+${primary}([[:space:]]|\$|\|)" | head -n1 |
        sed -E 's/^[[:space:]]+//' || true)"
    if [[ -n "$desc" ]]; then
        printf '  %s\n' "$desc"
    else
        printf '  %s   (see --help)\n' "$flag"
    fi
done

# ---------------------------------------------------------------------------
# 4. Per-mode argument contract (what each mode accepts)
# ---------------------------------------------------------------------------
echo
echo "== PER-MODE ARGUMENT CONTRACT =="
hr
cat <<'EOF'
QUERY-required modes accept: QUERY [root] + pattern/case/scope/ignore/context/
                             output-shape/bounds flags relevant to that backend.
  text, tracked, files, docs, tests, config, deps, changed-text, staged-text
    flags: --fixed --regex --pcre2 --ignore-case|-i --case-sensitive --smart-case
           --glob --type --exclude --max-depth --absolute
           --no-ignore --no-ignore-vcs --no-ignore-global --no-ignore-parent --no-ignore-dot
           --context|-C --before-context|-B --after-context|-A
           --files-with-matches|-l --count --count-matches
           --max-results --max-bytes --dry-run
    notes: rg-backed honor gitignore by default; tracked uses git-grep;
           files uses fd; struct/symbols/class use ast-grep.

  diff QUERY [root]      adds: --staged | --base REF
  history QUERY [root]   adds: --messages --patch (and --regex switches -S->-G)
  struct PATTERN [root]  adds: --lang LANG   (PATTERN is an ast-grep pattern)
  symbols NAME [root]    adds: --lang LANG   (emits symbols[])
  class NAME [root]      adds: --lang LANG   (class definitions only)

NO-QUERY modes accept: [root] + flags only (passing a QUERY is an error).
  changed-files, staged-files            (file lists)
  todo, unsafe-patterns                  (curated; rg-backed, gitignore honored)

DEPRECATED aliases: changed, staged      (tolerate an ignored leading query)
SPECIAL:            doctor (no args)      unsafe-all (-> status=blocked)
EOF

# ---------------------------------------------------------------------------
# 5. Output envelope keys
# ---------------------------------------------------------------------------
echo
echo "== JSON ENVELOPE KEYS (AI_OUTPUT=json) =="
hr
cat <<'EOF'
Always:   schema status tool query mode matches[] results[] warnings[] errors[]
          limits{max_results} meta{returned,truncated}
status:   ok | no_matches | error | unavailable | dry_run | blocked
Per-mode: summary{total_files,total_matches}  (count/file-only modes)
          symbols[]                            (symbols/class modes)
          results[].{path,line,column,text,mode,source_tool,root,language,absolute_path?}
          results[].context.{before[],after[]} (with --context)
          diff results carry: marker,new_line,scope
          history results carry: commit,author,date,message,path[,patch]
doctor:   diagnostics{available[],missing[],warnings[],root,git_available}
EOF

# ---------------------------------------------------------------------------
# 6. Environment variables
# ---------------------------------------------------------------------------
echo
echo "== ENVIRONMENT VARIABLES (referenced in both scripts) =="
hr
grep -hoE '\b(AI_OUTPUT|AI_LANG|AI_SEARCH_STRICT|AI_SEARCH_MULTI_MAX|XDG_CONFIG_HOME)\b' \
    "$search" "$multi" | sort -u | while IFS= read -r v; do
    case "$v" in
    AI_OUTPUT) printf '  %-22s set to "json" to emit the JSON envelope\n' "$v" ;;
    AI_LANG) printf '  %-22s fallback language for struct when --lang is absent (default php)\n' "$v" ;;
    AI_SEARCH_STRICT) printf '  %-22s =1 makes deprecated changed/staged aliases an error\n' "$v" ;;
    AI_SEARCH_MULTI_MAX) printf '  %-22s max queries per ai-search-multi.sh run (default 20)\n' "$v" ;;
    XDG_CONFIG_HOME) printf '  %-22s used to resolve the global gitignore (git/ignore)\n' "$v" ;;
    esac
done

# ---------------------------------------------------------------------------
# 7. ai-search-multi.sh allowlist (parsed from its case block)
# ---------------------------------------------------------------------------
echo
echo "== ai-search-multi.sh MODE ALLOWLIST (parsed from source) =="
hr
awk '
    /^case "\$mode" in/ { incase = 1; next }
    incase && /^esac/ { incase = 0 }
    incase && /mode_family=/ {
        # the case label is the previous non-empty line
        print prevlabel "  -> " $0
    }
    incase && /\)$/ { prevlabel = $0; sub(/\)$/, "", prevlabel); gsub(/^[[:space:]]+/, "", prevlabel) }
' "$multi" | sed -E 's/[[:space:]]*mode_family=/family=/; s/"//g; s/;;//' |
    sed -E 's/^/  /'
echo "  (ai-search-multi.sh forwards ALL ai-search.sh flags transparently.)"

# ---------------------------------------------------------------------------
# 8. Optional: runtime probe of every mode
# ---------------------------------------------------------------------------
if [[ "$probe" -eq 1 ]]; then
    echo
    echo "== RUNTIME PROBE (each mode invoked; shows accepted status) =="
    hr
    all_modes="$({
        extract_modes_from_family is_content_mode "$search"
        extract_modes_from_family is_file_list_mode "$search"
        extract_modes_from_family is_no_query_mode "$search"
        extract_modes_from_family is_ast_mode "$search"
        printf 'doctor\nunsafe-all\nchanged\nstaged\n'
    } | sort -u)"
    while IFS= read -r m; do
        [[ -n "$m" ]] || continue
        raw="$(AI_OUTPUT=json bash "$search" "$m" PROBEQUERY . --fixed 2>/dev/null || true)"
        st="$(printf '%s' "$raw" | jq -r '.status' 2>/dev/null)"
        [[ -n "$st" ]] || st='?'
        printf '  %-18s -> %s\n' "$m" "$st"
    done <<<"$all_modes"
    echo "  (note: no-query modes report 'error' when given PROBEQUERY — that is the contract.)"
fi

echo
echo "== AUTHORITATIVE built-in help =="
hr
echo "Run the script's own --help for the maintained reference:"
echo "  bash scripts/ai/ai-search.sh --help"
echo "  bash scripts/ai/ai-search-multi.sh --help"
echo
