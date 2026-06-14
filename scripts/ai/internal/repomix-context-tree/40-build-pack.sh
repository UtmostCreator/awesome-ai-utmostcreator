# shellcheck shell=bash
# shellcheck disable=SC2154,SC2034  # cross-module globals via dynamic scope
# repomix-context-tree/40-build-pack.sh — tree outputs, plan/index build, pack, clean/purge.
#
# Sourced by scripts/ai/repomix-context-tree.sh (thin loader). Not an entrypoint.
# run_clean/run_purge delete only the configured tree-context dir after
# confirm_context_delete. Behavior is byte-for-byte identical to the monolith.

ensure_tree_outputs() {
    mkdir -p "$TREE_DIR" "$BUNDLES_DIR" "$INDEXES_DIR"
}

generate_child_index() {
    local route="$1"
    local decision="$2"
    local output_rel="$3"
    local reason="$4"
    local output_abs="$TREE_DIR/$output_rel"

    [[ "$decision" == "split" ]] || return 0
    mkdir -p "$(dirname "$output_abs")"

    # shellcheck disable=SC2016  # single-quoted printf formats: backticks are literal markdown, %s is filled by args.
    {
        printf '# Child Context Index\n\n'
        printf 'Route: `%s`\n\n' "$route"
        printf 'Reason: `%s`\n\n' "$reason"
        printf 'This route exceeds budget. Create deeper bundles by rerunning with a larger `--depth` or a smaller scope.\n\n'
        printf '## Suggested Next Actions\n\n'
        printf '1. Re-run `scripts/ai/repomix-context-tree.sh plan . --depth %s` to split this route further.\n' "$((DEPTH + 1))"
        printf '2. Open the resulting child route with decision `pack`.\n'
        printf '3. Keep sibling routes closed unless the task crosses boundaries.\n'
    } >"$output_abs"
}

ensure_actionable_route() {
    local usable="$1"
    local fallback_row=''
    local fallback_group=''
    local fallback_bytes=''
    local fallback_tokens=''
    local fallback_decision=''
    local fallback_type=''
    local fallback_output=''
    local fallback_reason=''
    local temp_plan=''

    if awk -F'\t' 'NR > 1 && ($3 == "pack" || $3 == "split") { found = 1 } END { exit found ? 0 : 1 }' "$TREE_PLAN_TSV"; then
        return 0
    fi

    fallback_row="$(tail -n +2 "$ROUTER_FOLDER_METRICS" | awk -F'\t' '$1 != "" && $4 + 0 > 0 { print; exit }')"
    [[ -n "$fallback_row" ]] || return 0

    IFS=$'\t' read -r fallback_group _fallback_files _fallback_lines _fallback_code _fallback_comments _fallback_blanks _fallback_complexity fallback_bytes _fallback_churn _fallback_code_share _fallback_complexity_share _fallback_file_share _fallback_byte_share _fallback_churn_share _fallback_score <<<"$fallback_row"

    fallback_tokens="$(estimate_tokens "$fallback_bytes")"
    if ((fallback_tokens <= usable)); then
        fallback_decision='pack'
        fallback_type='bundle'
        fallback_output="bundles/$(safe_name "$fallback_group").$STYLE_EXT"
        fallback_reason='fallback route because no route met thresholds; estimated tokens fit route budget'
    else
        fallback_decision='split'
        fallback_type='index'
        fallback_output="indexes/$(safe_name "$fallback_group").md"
        fallback_reason='fallback route because no route met thresholds; estimated tokens exceed route budget'
    fi

    temp_plan="$TREE_DIR/.tree-plan.tsv.tmp"
    awk -F'\t' -v OFS='\t' \
        -v target_group="$fallback_group" \
        -v target_type="$fallback_type" \
        -v target_decision="$fallback_decision" \
        -v target_tokens="$fallback_tokens" \
        -v target_budget="$usable" \
        -v target_output="$fallback_output" \
        -v target_reason="$fallback_reason" \
        'NR == 1 { print; next }
         $1 == target_group && replaced == 0 {
             print $1, target_type, target_decision, target_tokens, target_budget, target_output, target_reason
             replaced = 1
             next
         }
         { print }
        ' "$TREE_PLAN_TSV" >"$temp_plan"
    mv "$temp_plan" "$TREE_PLAN_TSV"
}

build_plan() {
    local usable
    local selected=0

    usable="$(usable_budget)"
    [[ -f "$ROUTER_FOLDER_METRICS" ]] || die "missing folder metrics: $ROUTER_FOLDER_METRICS"

    {
        printf 'route\ttype\tdecision\testimated_tokens\tbudget\toutput\treason\n'
        tail -n +2 "$ROUTER_FOLDER_METRICS" | while IFS=$'\t' read -r group files _lines code _comments _blanks complexity bytes _churn _code_share _complexity_share _file_share _byte_share _churn_share score; do
            [[ -n "$group" ]] || continue

            if ((TOP > 0 && selected >= TOP)); then
                decision='skip'
                type='skipped'
                output='-'
                reason='exceeds top limit'
                tokens="$(estimate_tokens "$bytes")"
                printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\n' "$group" "$type" "$decision" "$tokens" "$usable" "$output" "$reason"
                continue
            fi

            if ((code < MIN_CODE)); then
                decision='skip'
                type='skipped'
                output='-'
                reason='below min-code threshold'
                tokens="$(estimate_tokens "$bytes")"
                printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\n' "$group" "$type" "$decision" "$tokens" "$usable" "$output" "$reason"
                continue
            fi

            if ((files < MIN_FILES)); then
                decision='skip'
                type='skipped'
                output='-'
                reason='below min-files threshold'
                tokens="$(estimate_tokens "$bytes")"
                printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\n' "$group" "$type" "$decision" "$tokens" "$usable" "$output" "$reason"
                continue
            fi

            if ((complexity < MIN_COMPLEXITY)); then
                decision='skip'
                type='skipped'
                output='-'
                reason='below min-complexity threshold'
                tokens="$(estimate_tokens "$bytes")"
                printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\n' "$group" "$type" "$decision" "$tokens" "$usable" "$output" "$reason"
                continue
            fi

            awk -v score_value="$score" -v min_score_value="$MIN_SCORE" 'BEGIN { exit !(score_value + 0 >= min_score_value + 0) }' || {
                decision='skip'
                type='skipped'
                output='-'
                reason='below min-score threshold'
                tokens="$(estimate_tokens "$bytes")"
                printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\n' "$group" "$type" "$decision" "$tokens" "$usable" "$output" "$reason"
                continue
            }

            selected=$((selected + 1))
            tokens="$(estimate_tokens "$bytes")"

            if ((tokens <= usable)); then
                decision='pack'
                type='bundle'
                output="bundles/$(safe_name "$group").$STYLE_EXT"
                reason='estimated tokens fit route budget'
            else
                decision='split'
                type='index'
                output="indexes/$(safe_name "$group").md"
                reason='estimated tokens exceed route budget'
            fi

            printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\n' "$group" "$type" "$decision" "$tokens" "$usable" "$output" "$reason"
        done
    } >"$TREE_PLAN_TSV"

    if [[ $(wc -l <"$TREE_PLAN_TSV") -le 1 ]]; then
        die "no routes generated"
    fi

    ensure_actionable_route "$usable"

    jq -R -s '
      split("\n") | map(select(length > 0) | split("\t")) as $rows
      | ($rows[0]) as $header
      | [ $rows[1:][] as $row
          | reduce range(0; $header|length) as $i ({}; . + { ($header[$i]): ($row[$i] // "") })
        ]
    ' "$TREE_PLAN_TSV" >"$TREE_PLAN_JSON"

    jq -n \
        --arg root "$ROOT" \
        --arg generated_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
        --argjson context_window "$CONTEXT_WINDOW" \
        --argjson reserved_output "$RESERVED_OUTPUT" \
        --argjson instruction_overhead "$INSTRUCTION_OVERHEAD" \
        --argjson safety_factor "$SAFETY_FACTOR" \
        --argjson usable_budget "$usable" \
        --arg style "$STYLE" \
        --arg compress "$COMPRESS" \
        --arg changed_since "$CHANGED_SINCE" \
        --slurpfile plan "$TREE_PLAN_JSON" \
        '{
        root: $root,
        generated_at: $generated_at,
        budget: {
          context_window: $context_window,
          reserved_output: $reserved_output,
          instruction_overhead: $instruction_overhead,
          safety_factor: $safety_factor,
          usable_budget: $usable_budget
        },
        repomix: {
          style: $style,
          compress: ($compress == "1"),
          changed_since: (if $changed_since == "" then null else $changed_since end)
        },
        routes: ($plan[0] // [])
      }' >"$TREE_MANIFEST_JSON"

    jq -n --slurpfile plan "$TREE_PLAN_JSON" '{generated_at: now, routes: ($plan[0] // [])}' >"$INDEX_JSON"
}

build_human_index() {
    # shellcheck disable=SC2016  # single-quoted printf formats: backticks are literal markdown, %s is filled by args.
    {
        printf '# Context Index\n\n'
        printf '## Purpose\n\n'
        printf 'Route repository context into the smallest useful bundle before loading broader areas.\n\n'
        printf '## Open This First\n\n'
        printf 'Open one route marked `pack` that matches your task scope. If all relevant routes are `split`, open that child index first.\n\n'
        printf '## Top-Level Routes\n\n'
        printf '| Route | Type | Decision | Estimated Tokens | Budget | Why | Open |\n'
        printf '| --- | --- | --- | ---: | ---: | --- | --- |\n'
        tail -n +2 "$TREE_PLAN_TSV" | while IFS=$'\t' read -r route type decision estimated_tokens budget output reason; do
            printf '| `%s` | `%s` | `%s` | %s | %s | %s | `%s` |\n' "$route" "$type" "$decision" "$estimated_tokens" "$budget" "$reason" "$output"
        done
        printf '\n## Next Steps For AI Agents\n\n'
        printf 'If decision is `pack`: open the bundle and start work there; avoid sibling bundles unless scope expands.\n\n'
        printf 'If decision is `split`: open the child index and continue route selection until you reach a `pack` route.\n\n'
        printf 'If decision is `skip`: avoid as primary context unless the task explicitly targets that path.\n\n'
        printf '## Wiring Locations\n\n'
        printf '%s\n' '- `AGENTS.md`'
        printf '%s\n' '- `.github/copilot-instructions.md`'
        printf '%s\n' '- `opencode.jsonc`'
        printf '%s\n' '- `docs/ai/adapter-contract.md`'
        printf '%s\n\n' '- `docs/ai/context-packing.md`'
        printf '## Machine Files\n\n'
        printf '%s\n' '- `tree-plan.tsv`'
        printf '%s\n' '- `tree-plan.json`'
        printf '%s\n' '- `tree-manifest.json`'
        printf '%s\n\n' '- `index.json`'
        printf '## Regeneration Command\n\n'
        printf '`scripts/ai/repomix-context-tree.sh all . --compress --style %s`\n' "$STYLE"
    } >"$INDEX_MD"

    tail -n +2 "$TREE_PLAN_TSV" | while IFS=$'\t' read -r route _type decision _estimated_tokens _budget output reason; do
        generate_child_index "$route" "$decision" "$output" "$reason"
    done
}

run_analyze() {
    need_bin jq
    ensure_tree_outputs
    (cd "$ROOT" && bash "${router_args[@]}")
    build_plan
    build_human_index
    log "wrote $TREE_PLAN_TSV"
    log "wrote $TREE_PLAN_JSON"
    log "wrote $TREE_MANIFEST_JSON"
    log "wrote $INDEX_MD"
    log "wrote $INDEX_JSON"
}

pack_route() {
    local route="$1"
    local output="$2"
    local out_abs="$TREE_DIR/$output"
    local repomix_args=(--output "$out_abs" --style "$STYLE")

    mkdir -p "$(dirname "$out_abs")"
    [[ "$INCLUDE_IGNORED" == "1" ]] && repomix_args+=(--no-gitignore)
    [[ "$INCLUDE_REPOMIXIGNORED" == "1" ]] && repomix_args+=(--no-dot-ignore --no-default-patterns)
    [[ "$COMPRESS" == "1" ]] && repomix_args+=(--compress)
    [[ -n "$SPLIT_SIZE" ]] && repomix_args+=(--split-output "$SPLIT_SIZE")
    [[ "$INCLUDE_LOGS" == "1" ]] && repomix_args+=(--include-logs --include-logs-count "$INCLUDE_LOGS_COUNT")
    [[ "$INCLUDE_DIFFS" == "1" ]] && repomix_args+=(--include-diffs)

    if [[ "$route" == "_root" ]]; then
        local list_file
        list_file="$(mktemp)"
        awk -F'\t' 'NR > 1 && $1 == "_root" { print $2 }' "$ROUTER_FILE_METRICS" >"$list_file"
        [[ -s "$list_file" ]] || {
            rm -f "$list_file"
            log "skip packing '$route' because no files matched"
            return 0
        }
        (cd "$ROOT" && repomix --stdin "${repomix_args[@]}" <"$list_file")
        rm -f "$list_file"
    else
        (cd "$ROOT" && repomix --include "$route/**" "${repomix_args[@]}")
    fi
}

run_pack() {
    need_bin repomix
    [[ -f "$TREE_PLAN_TSV" ]] || run_analyze
    [[ -f "$ROUTER_FILE_METRICS" ]] || die "missing file metrics for packing: $ROUTER_FILE_METRICS"

    local packed=0
    tail -n +2 "$TREE_PLAN_TSV" | while IFS=$'\t' read -r route _type decision _estimated_tokens _budget output _reason; do
        [[ "$decision" == "pack" ]] || continue
        pack_route "$route" "$output"
        packed=$((packed + 1))
    done

    # The while loop runs in a subshell in some shells, so $packed is unreliable
    # here. Verify that at least one bundle file was actually produced rather than
    # that the (always-present) bundles directory merely exists.
    if ! find "$BUNDLES_DIR" -type f -print -quit 2>/dev/null | grep -q .; then
        die "no bundles generated"
    fi
}

run_all() {
    run_analyze
    run_pack
}

run_clean() {
    confirm_context_delete "clean" "$TREE_DIR"
    rm -rf "$BUNDLES_DIR" "$INDEXES_DIR" "$INDEX_MD" "$INDEX_JSON"
    log "removed generated bundles and indexes from $TREE_DIR"
}

run_purge() {
    [[ -d "$TREE_DIR" ]] || {
        log "no tree-context directory at $TREE_DIR"
        return 0
    }

    [[ "$TREE_DIR" != "/" ]] || die "refusing to delete root directory"
    [[ "$TREE_DIR" != "$ROOT" ]] || die "refusing to delete repository root"

    confirm_context_delete "purge" "$TREE_DIR"
    rm -rf "$TREE_DIR"
    log "removed tree-context directory $TREE_DIR"
}
