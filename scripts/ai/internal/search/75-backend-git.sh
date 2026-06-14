#!/usr/bin/env bash
# 75-backend-git.sh — git-aware bespoke backends (diff, history).
#
# Purpose: run_diff_mode (unified-diff added-line search with marker/new_line/
#   scope) and run_history_mode (log -S/-G pickaxe over revision metadata, with
#   optional --patch). These build their own results[] shapes and emit + exit
#   directly, because their output does not fit the path:line:text pipeline.
#   Both are read-only history queries (log/show/diff), never history mutations.
# Allowed dependencies: git, awk, grep, jq; require_git_root (60-guards.sh),
#   emit_json()/fail() (40-output-json.sh). Reads diff/history flags + query.
#
# SC2034/SC2154: pattern/case/query/root/diff/history globals and g_summary_json
# are run-state owned across modules (see ai-search.sh load order).
# shellcheck disable=SC2034,SC2154

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
