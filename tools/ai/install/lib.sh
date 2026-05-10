#!/usr/bin/env bash
set -euo pipefail

install_log() {
    printf '[install-ai-kit] %s\n' "$*"
}

install_die() {
    printf 'Error: %s\n' "$*" >&2
    exit 1
}

install_walk_files() {
    local src="$1"
    local max_depth="${2:-}"

    if command -v fd >/dev/null 2>&1; then
        if [[ -n "$max_depth" ]]; then
            fd --type f --max-depth "$max_depth" -0 . "$src"
            return
        fi
        fd --type f -0 . "$src"
        return
    fi

    if [[ -n "$max_depth" ]]; then
        find "$src" -maxdepth "$max_depth" -type f -print0
        return
    fi

    find "$src" -type f -print0
}

normalize_bool() {
    case "${1:-0}" in
    1 | true | TRUE | yes | YES) printf '1\n' ;;
    *) printf '0\n' ;;
    esac
}

copy_file() {
    local source_root="$1"
    local target_root="$2"
    local force="$3"
    local dry_run="$4"
    local src_rel="$5"
    local dest_rel="$6"
    local src="$source_root/$src_rel"
    local dest="$target_root/$dest_rel"

    [[ -f "$src" ]] || install_die "missing source file: $src_rel"

    if [[ -f "$dest" && "$force" -ne 1 ]]; then
        install_log "skip existing file (use --force to overwrite): $dest_rel"
        return 0
    fi

    if [[ "$dry_run" -eq 1 ]]; then
        install_log "copy file: $src_rel -> $dest_rel"
        return 0
    fi

    mkdir -p "$(dirname "$dest")"
    cp "$src" "$dest"
    install_log "copied file: $dest_rel"
}

copy_dir() {
    local source_root="$1"
    local target_root="$2"
    local force="$3"
    local dry_run="$4"
    local src_rel="$5"
    local dest_rel="$6"
    local src="$source_root/$src_rel"
    local dest="$target_root/$dest_rel"

    [[ -d "$src" ]] || install_die "missing source directory: $src_rel"

    if [[ -e "$dest" && "$force" -ne 1 ]]; then
        install_log "skip existing directory (use --force to overwrite): $dest_rel"
        return 0
    fi

    if [[ "$dry_run" -eq 1 ]]; then
        install_log "copy directory: $src_rel -> $dest_rel"
        return 0
    fi

    rm -rf "$dest"
    mkdir -p "$(dirname "$dest")"
    cp -R "$src" "$dest"
    install_log "copied directory: $dest_rel"
}

copy_dir_with_rename() {
    local source_root="$1"
    local target_root="$2"
    local force="$3"
    local dry_run="$4"
    local src_rel="$5"
    local dest_rel="$6"
    local new_ext="$7"
    local src="$source_root/$src_rel"
    local dest="$target_root/$dest_rel"
    local file rel dir base target

    [[ -d "$src" ]] || install_die "missing source directory: $src_rel"

    if [[ -e "$dest" && "$force" -ne 1 ]]; then
        install_log "skip existing directory (use --force to overwrite): $dest_rel"
        return 0
    fi

    if [[ "$dry_run" -eq 1 ]]; then
        install_log "copy directory with rename: $src_rel -> $dest_rel (* -> *$new_ext)"
        return 0
    fi

    rm -rf "$dest"
    mkdir -p "$dest"

    while IFS= read -r -d '' file; do
        rel="${file#"$src"/}"
        dir="$(dirname "$rel")"
        base="$(basename "$rel")"
        base="${base%.*}$new_ext"
        if [[ "$dir" == "." ]]; then
            target="$dest/$base"
        else
            mkdir -p "$dest/$dir"
            target="$dest/$dir/$base"
        fi
        cp "$file" "$target"
    done < <(install_walk_files "$src")

    install_log "copied directory with rename: $dest_rel"
}

copy_dir_as_skill_dirs() {
    local source_root="$1"
    local target_root="$2"
    local force="$3"
    local dry_run="$4"
    local src_rel="$5"
    local dest_rel="$6"
    local src="$source_root/$src_rel"
    local dest="$target_root/$dest_rel"
    local file skill_name skill_dir

    [[ -d "$src" ]] || install_die "missing source directory: $src_rel"

    if [[ -e "$dest" && "$force" -ne 1 ]]; then
        install_log "skip existing directory (use --force to overwrite): $dest_rel"
        return 0
    fi

    if [[ "$dry_run" -eq 1 ]]; then
        install_log "copy directory as skill dirs: $src_rel -> $dest_rel"
        return 0
    fi

    rm -rf "$dest"
    mkdir -p "$dest"

    while IFS= read -r -d '' file; do
        skill_name="$(basename "$file" .md)"
        skill_dir="$dest/$skill_name"
        mkdir -p "$skill_dir"
        cp "$file" "$skill_dir/SKILL.md"
    done < <(install_walk_files "$src" 1)

    install_log "copied directory as skill dirs: $dest_rel"
}
