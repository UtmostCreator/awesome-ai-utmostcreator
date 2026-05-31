#!/usr/bin/env bash
set -euo pipefail

COMMON_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/ai/common.sh
source "$COMMON_DIR/common.sh"

shopt -s extglob
if ((BASH_VERSINFO[0] >= 4)); then
    shopt -s globstar
fi

usage() {
    cat <<'EOF'
Usage:
  scripts/ai/repomix-scc-router.sh <stats|plan|pack|all|clean|purge> [root] [options]

Commands:
  stats   Run scc analysis and write file/folder metrics.
  plan    Run stats and create a ranked bundle plan.
  pack    Pack bundles from an existing bundle plan.
  all     Run stats, plan, and pack.
  clean   Delete generated bundles and keep metrics files.
  purge   Delete the entire output directory.

Options:
  --output-dir <dir>          Output directory (default: .repomix-context)
  --depth <n>                 Folder grouping depth (default: 1)
  --top <n>                   Max folders to pack, 0 means all (default: 25)
  --min-code <n>              Minimum code lines per folder (default: 300)
  --min-files <n>             Minimum files per folder (default: 2)
  --min-score <n>             Minimum ranking score (default: 0)
  --min-complexity <n>        Minimum cyclomatic complexity per folder (default: 0)
  --changed-since <ref>       Limit planning and stats weighting to files changed since ref
  --churn-count <n>           Commit count used for churn weighting (default: 50)
  --style <xml|markdown|json|plain>
                              Repomix output style (default: xml)
  --split-size <size>         Repomix split size, for example 10mb
  --compress                  Enable repomix compression
  --include-logs              Include git logs in bundles
  --include-logs-count <n>    Commit count for --include-logs (default: 20)
  --include-diffs             Include git diffs in bundles
  --help                      Show this help

Examples:
  scripts/ai/repomix-scc-router.sh stats . --depth 1
  scripts/ai/repomix-scc-router.sh plan . --depth 2 --top 20
  scripts/ai/repomix-scc-router.sh all . --depth 1 --compress --split-size 10mb
  scripts/ai/repomix-scc-router.sh clean .
  scripts/ai/repomix-scc-router.sh purge .
EOF
}

die() {
    printf 'Error: %s\n' "$1" >&2
    exit 1
}

log() {
    printf '[repomix-router] %s\n' "$1"
}

confirm_context_delete() {
    local action="$1"
    local target="$2"

    log "requested destructive context action: $action -> $target"

    if [[ "${APPROVE_CONTEXT_DELETE:-0}" == "1" ]]; then
        return 0
    fi

    if [[ -t 0 ]] && [[ "${CI:-}" != "true" ]]; then
        printf 'Continue with %s on %s? [y/N] ' "$action" "$target" >&2
        read -r confirm
        [[ "$confirm" =~ ^[Yy]$ ]] && return 0
    fi

    die "context deletion requires APPROVE_CONTEXT_DELETE=1 or interactive confirmation"
}

need_bin() {
    local name="$1"
    command -v "$name" >/dev/null 2>&1 || die "required binary '$name' not found"
}

to_posix_path() {
    local input="$1"
    if [[ "$input" =~ ^[A-Za-z]:\\ ]]; then
        local drive="${input:0:1}"
        local rest="${input:2}"
        rest="${rest//\\//}"
        printf '/%s%s\n' "${drive,,}" "$rest"
        return 0
    fi

    printf '%s\n' "$input"
}

resolve_scc_bin() {
    local candidate=""
    local local_app_data="${LOCALAPPDATA:-}"
    local base=""

    if candidate="$(command -v scc 2>/dev/null)" && [[ -n "$candidate" ]]; then
        printf '%s\n' "$candidate"
        return 0
    fi

    if [[ -n "$local_app_data" ]]; then
        base="$(to_posix_path "$local_app_data")/Microsoft/WinGet/Packages"
        for candidate in "$base"/BenBoyter.scc*/scc.exe; do
            if [[ -x "$candidate" ]]; then
                printf '%s\n' "$candidate"
                return 0
            fi
        done
    fi

    for candidate in /c/Users/*/AppData/Local/Microsoft/WinGet/Packages/BenBoyter.scc*/scc.exe; do
        if [[ -x "$candidate" ]]; then
            printf '%s\n' "$candidate"
            return 0
        fi
    done

    return 1
}

abs_path() {
    local input="$1"
    if [[ "$input" = /* ]]; then
        printf '%s\n' "$input"
    else
        printf '%s\n' "$(cd "$(dirname "$input")" && pwd)/$(basename "$input")"
    fi
}

ext_for_style() {
    case "$1" in
    xml) printf 'xml\n' ;;
    markdown) printf 'md\n' ;;
    json) printf 'json\n' ;;
    plain) printf 'txt\n' ;;
    *) die "unsupported style '$1'" ;;
    esac
}

group_for_path() {
    local path="$1"
    local requested_depth="$2"
    path="${path//\\//}"
    local directory="${path%/*}"
    local parts=()
    local group_parts=()
    local group=''
    local index=0

    if [[ "$path" != */* ]]; then
        printf '_root\n'
        return 0
    fi

    mapfile -td '/' parts < <(printf '%s/' "$directory")
    for part in "${parts[@]}"; do
        [[ -n "$part" ]] || continue
        group_parts+=("$part")
        index=$((index + 1))
        if ((index >= requested_depth)); then
            break
        fi
    done

    if ((${#group_parts[@]} == 0)); then
        printf '_root\n'
    else
        printf -v group '%s/' "${group_parts[@]}"
        printf '%s\n' "${group%/}"
    fi
}

safe_group_name() {
    local name="$1"
    name="${name//\\//}"
    name="${name//\//__}"
    name="${name// /_}"
    printf '%s\n' "$name"
}

IGNORE_PATTERNS=()
COLLECTED_FILES=()
COLLECTED_CHANGED_FILES=()

# Hardcoded safety excludes: ephemeral/runtime/generated state that must never
# enter a context plan even when .repomixignore is missing or edited. These use
# the recursive directory form that path_is_ignored() now understands, so they
# apply in both the git and non-git collection branches.
AI_CONTEXT_HARD_EXCLUDES=(
    ".git"
    ".ai-backups"
    ".ai-logs"
    ".repomix-context"
    "node_modules"
    "vendor"
    "dist"
    "build"
    "coverage"
)

load_ignore_patterns() {
    local ignore_file="$ROOT/.repomixignore"
    local relative_output_dir="$OUTPUT_DIR_REL"
    local hard

    IGNORE_PATTERNS=()
    if [[ -f "$ignore_file" ]]; then
        while IFS= read -r line; do
            line="${line%$'\r'}"
            [[ -n "$line" ]] || continue
            [[ "$line" =~ ^[[:space:]]*# ]] && continue
            IGNORE_PATTERNS+=("$line")
        done <"$ignore_file"
    fi

    IGNORE_PATTERNS+=("$relative_output_dir/**")

    # Always append the hardcoded safety excludes so a missing or edited
    # .repomixignore cannot leak ephemeral state into the context plan.
    for hard in "${AI_CONTEXT_HARD_EXCLUDES[@]}"; do
        IGNORE_PATTERNS+=("$hard")
    done
}

path_is_ignored() {
    local path="$1"
    local pat norm

    # Strip a leading ./ from the path so "./foo" and "foo" compare equally.
    path="${path#./}"

    for pat in "${IGNORE_PATTERNS[@]}"; do
        [[ -n "$pat" ]] || continue

        # Glob patterns (already containing *) are matched as-is, before any
        # trailing-slash normalization, so forms like "generated/**" keep working.
        if [[ "$pat" == *"*"* ]]; then
            # shellcheck disable=SC2053
            if [[ "$path" == $pat ]]; then
                return 0
            fi
            continue
        fi

        # Normalize directory patterns: strip a trailing slash so "foo/" behaves
        # like "foo" and matches nested files via the prefix check below.
        norm="${pat%/}"
        [[ -n "$norm" ]] || continue

        # Exact match, nested-file match (foo/bar...), or ./-prefixed nested match.
        if [[ "$path" == "$norm" || "$path" == "$norm"/* || "$path" == "./$norm"/* ]]; then
            return 0
        fi
    done

    return 1
}

collect_files() {
    local path
    COLLECTED_FILES=()
    if git -C "$ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
        while IFS= read -r path; do
            [[ -n "$path" ]] || continue
            [[ -f "$ROOT/$path" ]] || continue
            if ! path_is_ignored "$path"; then
                COLLECTED_FILES+=("$path")
            fi
        done < <(git -C "$ROOT" ls-files -co --exclude-standard)
    else
        while IFS= read -r path; do
            [[ -n "$path" ]] || continue
            [[ -f "$ROOT/$path" ]] || continue
            if ! path_is_ignored "$path"; then
                COLLECTED_FILES+=("$path")
            fi
        done < <(rg --files --hidden "$ROOT")
    fi

    ((${#COLLECTED_FILES[@]} > 0)) || die "no files available after applying ignore rules"
}

collect_changed_files() {
    local path
    COLLECTED_CHANGED_FILES=()

    [[ -n "$CHANGED_SINCE" ]] || return 0

    if git -C "$ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
        while IFS= read -r path; do
            [[ -n "$path" ]] || continue
            [[ -f "$ROOT/$path" ]] || continue
            if ! path_is_ignored "$path"; then
                COLLECTED_CHANGED_FILES+=("$path")
            fi
        done < <((git -C "$ROOT" diff --name-only "$CHANGED_SINCE"...HEAD 2>/dev/null || git -C "$ROOT" diff --name-only "$CHANGED_SINCE") | sort -u)
    fi
}

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

COMMAND="${1:-}"
if [[ -z "$COMMAND" ]]; then
    usage
    exit 1
fi
if [[ "$COMMAND" == "--help" || "$COMMAND" == "-h" ]]; then
    usage
    exit 0
fi
shift || true

ROOT_INPUT='.'
if (($# > 0)) && [[ "${1:-}" != --* ]]; then
    ROOT_INPUT="$1"
    shift || true
fi

OUTPUT_DIR='.repomix-context'
DEPTH=1
TOP=25
MIN_CODE=300
MIN_FILES=2
MIN_SCORE=0
MIN_COMPLEXITY=0
CHANGED_SINCE=''
CHURN_COUNT=50
STYLE='xml'
STYLE_EXT='xml'
SPLIT_SIZE=''
COMPRESS=0
INCLUDE_LOGS=0
INCLUDE_LOGS_COUNT=20
INCLUDE_DIFFS=0

while (($# > 0)); do
    case "$1" in
    --output-dir)
        OUTPUT_DIR="$2"
        shift 2
        ;;
    --output-dir=*)
        OUTPUT_DIR="${1#*=}"
        shift
        ;;
    --depth)
        DEPTH="$2"
        shift 2
        ;;
    --depth=*)
        DEPTH="${1#*=}"
        shift
        ;;
    --top)
        TOP="$2"
        shift 2
        ;;
    --top=*)
        TOP="${1#*=}"
        shift
        ;;
    --min-code)
        MIN_CODE="$2"
        shift 2
        ;;
    --min-code=*)
        MIN_CODE="${1#*=}"
        shift
        ;;
    --min-files)
        MIN_FILES="$2"
        shift 2
        ;;
    --min-files=*)
        MIN_FILES="${1#*=}"
        shift
        ;;
    --min-score)
        MIN_SCORE="$2"
        shift 2
        ;;
    --min-score=*)
        MIN_SCORE="${1#*=}"
        shift
        ;;
    --min-complexity)
        MIN_COMPLEXITY="$2"
        shift 2
        ;;
    --min-complexity=*)
        MIN_COMPLEXITY="${1#*=}"
        shift
        ;;
    --changed-since)
        CHANGED_SINCE="$2"
        shift 2
        ;;
    --changed-since=*)
        CHANGED_SINCE="${1#*=}"
        shift
        ;;
    --churn-count)
        CHURN_COUNT="$2"
        shift 2
        ;;
    --churn-count=*)
        CHURN_COUNT="${1#*=}"
        shift
        ;;
    --style)
        STYLE="$2"
        shift 2
        ;;
    --style=*)
        STYLE="${1#*=}"
        shift
        ;;
    --split-size)
        SPLIT_SIZE="$2"
        shift 2
        ;;
    --split-size=*)
        SPLIT_SIZE="${1#*=}"
        shift
        ;;
    --compress)
        COMPRESS=1
        shift
        ;;
    --include-logs)
        INCLUDE_LOGS=1
        shift
        ;;
    --include-logs-count)
        INCLUDE_LOGS_COUNT="$2"
        shift 2
        ;;
    --include-logs-count=*)
        INCLUDE_LOGS_COUNT="${1#*=}"
        shift
        ;;
    --include-diffs)
        INCLUDE_DIFFS=1
        shift
        ;;
    --help | -h)
        usage
        exit 0
        ;;
    *)
        die "unknown option '$1'"
        ;;
    esac
done

ROOT="$(abs_path "$ROOT_INPUT")"
[[ -d "$ROOT" ]] || die "root directory '$ROOT' does not exist"

require_clean_secret_scan "$ROOT"

if [[ "$OUTPUT_DIR" = /* ]]; then
    OUTPUT_DIR_ABS="$OUTPUT_DIR"
    OUTPUT_DIR_REL="$(basename "$OUTPUT_DIR")"
else
    OUTPUT_DIR_REL="$OUTPUT_DIR"
    OUTPUT_DIR_ABS="$ROOT/$OUTPUT_DIR"
fi

STYLE_EXT="$(ext_for_style "$STYLE")"

RAW_METRICS="$OUTPUT_DIR_ABS/scc-openmetrics.txt"
FILE_METRICS_RAW="$OUTPUT_DIR_ABS/file-metrics.raw.tsv"
FILE_METRICS="$OUTPUT_DIR_ABS/file-metrics.tsv"
FOLDER_METRICS="$OUTPUT_DIR_ABS/folder-metrics.tsv"
BUNDLE_PLAN="$OUTPUT_DIR_ABS/bundle-plan.tsv"
BUNDLE_PLAN_JSON="$OUTPUT_DIR_ABS/bundle-plan.json"

case "$COMMAND" in
stats)
    run_stats
    ;;
plan)
    run_stats
    write_bundle_plan
    ;;
pack)
    run_pack
    ;;
all)
    run_stats
    write_bundle_plan
    run_pack
    ;;
clean)
    run_clean
    ;;
purge)
    run_purge
    ;;
*)
    usage
    die "unknown command '$COMMAND'"
    ;;
esac
