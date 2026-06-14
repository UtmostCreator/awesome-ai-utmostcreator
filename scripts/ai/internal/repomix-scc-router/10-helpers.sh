# shellcheck shell=bash
# shellcheck disable=SC2154,SC2034  # cross-module globals via dynamic scope
# repomix-scc-router/10-helpers.sh — logging, path, ignore, and file-collection helpers.
#
# Sourced by scripts/ai/repomix-scc-router.sh (thin loader). Not an entrypoint.
# Behavior is byte-for-byte identical to the previous monolithic version.

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
    ".cache"
    ".next"
    ".nuxt"
    ".repomix-context"
    ".turbo"
    "docs/ai/generated"
    "node_modules"
    "vendor"
    "dist"
    "build"
    "coverage"
    "logs"
    "tmp"
    "temp"
    "cache"
    "storage/logs"
    "storage/framework/cache"
    "storage/framework/sessions"
    "storage/framework/views"
    "target"
)

load_ignore_patterns() {
    local ignore_file="$ROOT/.repomixignore"
    local relative_output_dir="$OUTPUT_DIR_REL"
    local hard

    IGNORE_PATTERNS=()

    # Full bypass (--no-ignore / --include-repomixignored): skip .repomixignore
    # and the soft hard-excludes so an explicitly selected folder is always
    # packable. Only the unsafe-to-pack roots below stay excluded to avoid
    # recursing into the output dir or leaking .git internals.
    if [[ "$INCLUDE_REPOMIXIGNORED" == "1" ]]; then
        IGNORE_PATTERNS+=("$relative_output_dir/**")
        IGNORE_PATTERNS+=(".git")
        IGNORE_PATTERNS+=(".repomix-context")
        return
    fi

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
        local -a ls_files_args=(ls-files -co)
        # By default git's --exclude-standard hides .gitignore'd files. With
        # --include-ignored we drop it so ignored folders (e.g. a JSON cache
        # under storage/tmp) can still be collected for analysis.
        if [[ "$INCLUDE_IGNORED" != "1" ]]; then
            ls_files_args+=(--exclude-standard)
        fi
        while IFS= read -r path; do
            [[ -n "$path" ]] || continue
            [[ -f "$ROOT/$path" ]] || continue
            if ! path_is_ignored "$path"; then
                COLLECTED_FILES+=("$path")
            fi
        done < <(git -C "$ROOT" "${ls_files_args[@]}")
    else
        local relative_path
        while IFS= read -r path; do
            [[ -n "$path" ]] || continue
            relative_path="${path#./}"
            [[ -f "$ROOT/$relative_path" ]] || continue
            if ! path_is_ignored "$relative_path"; then
                COLLECTED_FILES+=("$relative_path")
            fi
        done < <(
            cd "$ROOT"
            rg --files --hidden .
        )
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
