#!/usr/bin/env bash
# 45-results-rg.sh — structured result builders for rg/git-grep output.
#
# Purpose: transform backend output into the additive results[] structures —
#   line-oriented "path:line:text" (lines_to_structured_results) and rg --json
#   streams (rg_json_to_results / rg_json_to_matches). The legacy matches[]
#   string array stays unchanged.
# Allowed dependencies: jq. Reads g_mode and absolute via jq --arg.
#
# SC2154: g_mode/absolute are run-state globals set by sibling modules.
# shellcheck disable=SC2154

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
# shellcheck disable=SC2016  # single-quoted on purpose: $p/$s/$x are jq variables, not shell.
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
