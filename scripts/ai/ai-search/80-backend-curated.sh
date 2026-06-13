#!/usr/bin/env bash
# 80-backend-curated.sh — curated no-query backends (todo, unsafe-patterns).
#
# Purpose: run_todo_mode (grouped TODO/FIXME/legacy markers) and
#   run_unsafe_patterns_mode (curated risky patterns with rule + severity).
#   Both build their own results[] shapes and emit + exit directly.
# Allowed dependencies: rg, jq; canonical_root()/emit_json()/fail()
#   (40-output-json.sh). Reads ignore_args, rg_scope_args, root.
#
# Note: the approval-gated unsafe-all mode is short-circuited earlier in
#   normalize_legacy_alias (35-parse-positionals.sh); it never reaches a scan.
#
# SC2154: ignore_args/rg_scope_args/root/json_mode are run-state globals.
# shellcheck disable=SC2154

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
