# shellcheck shell=bash
# shellcheck disable=SC2154,SC2034  # cross-module globals via dynamic scope
# repomix-scc-router/40-analysis-pack.sh — scc analysis, metrics, bundle plan, packing, clean/purge.
#
# Sourced by scripts/ai/repomix-scc-router.sh (thin loader). Not an entrypoint.
# run_clean/run_purge delete only the configured output dir after
# confirm_context_delete. Behavior is byte-for-byte identical to the monolith.

run_scc_analysis() {
    local -a files=()
    local -a changed_files=()
    local scc_bin
    local chunk_size=200
    local idx=0
    local total=0

    scc_bin="$(resolve_scc_bin)" || die "required binary 'scc' not found"
    load_ignore_patterns
    collect_files
    if [[ -n "${COLLECTED_FILES[*]-}" ]]; then
        files=("${COLLECTED_FILES[@]}")
    else
        files=()
    fi
    collect_changed_files
    if [[ -n "${COLLECTED_CHANGED_FILES[*]-}" ]]; then
        changed_files=("${COLLECTED_CHANGED_FILES[@]}")
    else
        changed_files=()
    fi

    if [[ -n "$CHANGED_SINCE" ]]; then
        log "limiting stats input to ${#changed_files[@]} changed files since $CHANGED_SINCE"
        files=("${changed_files[@]}")
    fi

    mkdir -p "$OUTPUT_DIR_ABS"

    log "running scc on ${#files[@]} files"
    : >"$RAW_METRICS"

    if ((${#files[@]} == 0)); then
        log "no files selected for analysis; writing empty metrics"
        return 0
    fi

    (
        cd "$ROOT"
        if ((${#files[@]} > chunk_size)); then
            total=${#files[@]}
            while ((idx < total)); do
                local -a chunk=("${files[@]:idx:chunk_size}")
                local chunk_output="$OUTPUT_DIR_ABS/scc-openmetrics-$idx.txt"
                "$scc_bin" --by-file --format openmetrics --output "$chunk_output" --no-cocomo "${chunk[@]}"
                cat "$chunk_output" >>"$RAW_METRICS"
                rm -f "$chunk_output"
                idx=$((idx + chunk_size))
            done
        else
            "$scc_bin" --by-file --format openmetrics --output "$RAW_METRICS" --no-cocomo "${files[@]}"
        fi
    )
}

write_file_metrics() {
    awk '
    BEGIN {
      FS = " "
      OFS = "\t"
    }
    {
      if ($0 !~ /^scc_(lines|code|comments|blanks|complexity|bytes)\{.*file="[^"]+".*\} [0-9]+$/) {
        next
      }

      split($0, parts, " ")
      metric_line = parts[1]
      value = parts[2]

      metric = metric_line
      sub(/^scc_/, "", metric)
      sub(/\{.*/, "", metric)

      file = metric_line
      sub(/^.*file="/, "", file)
      sub(/".*/, "", file)
      gsub(/\\/, "/", file)
      gsub(/\/+/ , "/", file)
      sub(/^\.\//, "", file)

      language = metric_line
      sub(/^.*language="/, "", language)
      sub(/".*/, "", language)

      seen[file] = 1
      languages[file] = language
      data[file, metric] = value + 0
    }
    END {
      print "file", "language", "lines", "code", "comments", "blanks", "complexity", "bytes"
      for (file in seen) {
        print file, languages[file], data[file, "lines"] + 0, data[file, "code"] + 0, data[file, "comments"] + 0, data[file, "blanks"] + 0, data[file, "complexity"] + 0, data[file, "bytes"] + 0
      }
    }
  ' "$RAW_METRICS" >"$FILE_METRICS_RAW"

    {
        printf 'group\tfile\tlanguage\tlines\tcode\tcomments\tblanks\tcomplexity\tbytes\n'
        tail -n +2 "$FILE_METRICS_RAW" | while IFS=$'\t' read -r file language lines code comments blanks complexity bytes; do
            [[ -n "$file" ]] || continue
            printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
                "$(group_for_path "$file" "$DEPTH")" \
                "$file" \
                "$language" \
                "$lines" \
                "$code" \
                "$comments" \
                "$blanks" \
                "$complexity" \
                "$bytes"
        done
    } >"$FILE_METRICS"
}

write_folder_metrics() {
    local summary_tmp="$OUTPUT_DIR_ABS/folder-metrics.unsorted.tsv"
    local churn_tmp="$OUTPUT_DIR_ABS/folder-churn.tsv"

    {
        printf 'group\tchurn\n'
        if git -C "$ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
            git -C "$ROOT" log --name-only --pretty=format: -"$CHURN_COUNT" |
                awk 'NF { print }' |
                awk -v depth="$DEPTH" '
            function group_for(path, depth_value,   directory, count, segment, parts, group) {
              gsub(/\\/, "/", path)
              sub(/^\.\//, "", path)
              if (path !~ /\//) {
                return "_root"
              }
              directory = path
              sub(/\/[^\/]+$/, "", directory)
              count = split(directory, parts, "/")
              group = ""
              for (i = 1; i <= count && i <= depth_value; i++) {
                if (parts[i] == "") {
                  continue
                }
                group = group (group == "" ? "" : "/") parts[i]
              }
              return group == "" ? "_root" : group
            }
            {
              churn[group_for($0, depth)] += 1
            }
            END {
              for (group in churn) {
                printf "%s\t%d\n", group, churn[group]
              }
            }
          ' |
                sort -t $'\t' -k1,1
        fi
    } >"$churn_tmp"

    awk -F'\t' '
    BEGIN { OFS = "\t" }
    FNR == 1 && NR == 1 { next }
    FILENAME == ARGV[1] {
      churn[$1] = $2 + 0
      next
    }
    FNR == 1 { next }
    {
      group = $1
      files[group] += 1
      lines[group] += $4
      code[group] += $5
      comments[group] += $6
      blanks[group] += $7
      complexity[group] += $8
      bytes[group] += $9

      total_files += 1
      total_code += $5
      total_complexity += $8
      total_bytes += $9
      total_churn += churn[group]
    }
    END {
      for (group in files) {
        code_share = total_code > 0 ? code[group] / total_code : 0
        complexity_share = total_complexity > 0 ? complexity[group] / total_complexity : 0
        file_share = total_files > 0 ? files[group] / total_files : 0
        byte_share = total_bytes > 0 ? bytes[group] / total_bytes : 0
        churn_share = total_churn > 0 ? churn[group] / total_churn : 0
        score = (code_share * 45) + (complexity_share * 20) + (file_share * 10) + (byte_share * 10) + (churn_share * 15)
        printf "%s\t%d\t%d\t%d\t%d\t%d\t%d\t%d\t%d\t%.6f\t%.6f\t%.6f\t%.6f\t%.6f\t%.6f\n", group, files[group], lines[group], code[group], comments[group], blanks[group], complexity[group], bytes[group], churn[group] + 0, code_share, complexity_share, file_share, byte_share, churn_share, score
      }
    }
  ' "$churn_tmp" "$FILE_METRICS" >"$summary_tmp"

    {
        printf 'group\tfiles\tlines\tcode\tcomments\tblanks\tcomplexity\tbytes\tchurn\tcode_share\tcomplexity_share\tfile_share\tbyte_share\tchurn_share\tscore\n'
        sort -t $'\t' -k15,15nr "$summary_tmp"
    } >"$FOLDER_METRICS"

    rm -f "$summary_tmp" "$churn_tmp"
}

run_stats() {
    run_scc_analysis
    write_file_metrics
    write_folder_metrics
    log "wrote analysis outputs to $OUTPUT_DIR_ABS"
}

write_bundle_plan() {
    local selected=0

    [[ -f "$FOLDER_METRICS" ]] || die "missing folder metrics: run 'stats' first"

    {
        printf 'rank\tgroup\tfiles\tlines\tcode\tcomments\tblanks\tcomplexity\tbytes\tchurn\tcode_share\tcomplexity_share\tfile_share\tbyte_share\tchurn_share\tscore\tbundle\n'
        tail -n +2 "$FOLDER_METRICS" | while IFS=$'\t' read -r group files lines code comments blanks complexity bytes churn code_share complexity_share file_share byte_share churn_share score; do
            [[ -n "$group" ]] || continue

            if ((TOP > 0 && selected >= TOP)); then
                break
            fi

            if ((code < MIN_CODE)); then
                continue
            fi

            if ((files < MIN_FILES)); then
                continue
            fi

            if ((complexity < MIN_COMPLEXITY)); then
                continue
            fi

            awk -v score="$score" -v min_score="$MIN_SCORE" 'BEGIN { exit !(score + 0 >= min_score + 0) }' || continue

            selected=$((selected + 1))
            printf '%d\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
                "$selected" \
                "$group" \
                "$files" \
                "$lines" \
                "$code" \
                "$comments" \
                "$blanks" \
                "$complexity" \
                "$bytes" \
                "$churn" \
                "$code_share" \
                "$complexity_share" \
                "$file_share" \
                "$byte_share" \
                "$churn_share" \
                "$score" \
                "bundles/$(safe_group_name "$group").$STYLE_EXT"
        done
    } >"$BUNDLE_PLAN"

    if [[ $(wc -l <"$BUNDLE_PLAN") -le 1 ]]; then
        die "bundle plan is empty after filtering"
    fi

    log "wrote bundle plan to $BUNDLE_PLAN"

    jq -R -s '
    split("\n")
    | map(select(length > 0) | split("\t")) as $rows
    | ($rows[0]) as $header
    | [ $rows[1:][] as $row
        | reduce range(0; $header|length) as $i ({}; . + { ($header[$i]): ($row[$i] // "") })
      ]
  ' "$BUNDLE_PLAN" >"$BUNDLE_PLAN_JSON"

    log "wrote bundle plan to $BUNDLE_PLAN_JSON"
}

pack_group() {
    local group="$1"
    local bundle_rel="$2"
    local bundle_abs="$OUTPUT_DIR_ABS/$bundle_rel"
    local list_file
    local -a repomix_args
    local include_pattern

    mkdir -p "$(dirname "$bundle_abs")"

    repomix_args=(--output "$bundle_abs" --style "$STYLE")
    if [[ "$INCLUDE_IGNORED" == "1" ]]; then
        repomix_args+=(--no-gitignore)
    fi
    if [[ "$INCLUDE_REPOMIXIGNORED" == "1" ]]; then
        # Also drop repomix's own ignore layers (.ignore files and built-in
        # default patterns) so a deliberately selected folder is never filtered
        # back out after the router already collected it.
        repomix_args+=(--no-dot-ignore --no-default-patterns)
    fi
    if [[ "$COMPRESS" == "1" ]]; then
        repomix_args+=(--compress)
    fi
    if [[ -n "$SPLIT_SIZE" ]]; then
        repomix_args+=(--split-output "$SPLIT_SIZE")
    fi
    if [[ "$INCLUDE_LOGS" == "1" ]]; then
        repomix_args+=(--include-logs --include-logs-count "$INCLUDE_LOGS_COUNT")
    fi
    if [[ "$INCLUDE_DIFFS" == "1" ]]; then
        repomix_args+=(--include-diffs)
    fi

    if [[ "$group" == "_root" ]]; then
        list_file="$(mktemp)"
        tail -n +2 "$FILE_METRICS" | while IFS=$'\t' read -r file_group file _; do
            if [[ "$file_group" == "$group" ]]; then
                printf '%s\n' "$file"
            fi
        done >"$list_file"

        [[ -s "$list_file" ]] || die "no files matched group '$group'"

        log "packing group '$group' -> $bundle_rel"
        (
            cd "$ROOT"
            repomix --stdin "${repomix_args[@]}" <"$list_file"
        )

        rm -f "$list_file"
        return 0
    fi

    include_pattern="$group/**"
    log "packing group '$group' -> $bundle_rel"
    (
        cd "$ROOT"
        repomix --include "$include_pattern" "${repomix_args[@]}"
    )
}

run_pack() {
    need_bin repomix

    [[ -f "$BUNDLE_PLAN" ]] || die "missing bundle plan: run 'plan' first"
    [[ -f "$FILE_METRICS" ]] || die "missing file metrics: run 'stats' first"

    tail -n +2 "$BUNDLE_PLAN" | while IFS=$'\t' read -r _rank group _files _lines _code _comments _blanks _complexity _bytes _churn _code_share _complexity_share _file_share _byte_share _churn_share _score bundle; do
        [[ -n "$group" ]] || continue
        pack_group "$group" "$bundle"
    done
}

run_clean() {
    local bundles_dir="$OUTPUT_DIR_ABS/bundles"

    if [[ ! -d "$bundles_dir" ]]; then
        log "no bundles directory to remove at $bundles_dir"
        return 0
    fi

    confirm_context_delete "clean" "$bundles_dir"
    rm -rf "$bundles_dir"
    log "removed generated bundles from $bundles_dir"
}

run_purge() {
    if [[ ! -e "$OUTPUT_DIR_ABS" ]]; then
        log "no output directory to remove at $OUTPUT_DIR_ABS"
        return 0
    fi

    [[ "$OUTPUT_DIR_ABS" != "/" ]] || die "refusing to delete root directory"
    [[ "$OUTPUT_DIR_ABS" != "$ROOT" ]] || die "refusing to delete repository root"

    confirm_context_delete "purge" "$OUTPUT_DIR_ABS"
    rm -rf "$OUTPUT_DIR_ABS"
    log "removed output directory $OUTPUT_DIR_ABS"
}
