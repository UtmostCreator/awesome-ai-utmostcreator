#!/usr/bin/env bash
# 25-modes.sh — mode taxonomy.
#
# Purpose: classify modes into families (file-list, content, ast, no-query,
#   surface) and provide surface_globs() for the surface-scoped modes. Pure
#   predicates; no side effects, no global state.
# Allowed dependencies: none.

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
    # Phase 4 query-required repo-aware modes.
    diff | history | tests | config | deps) return 0 ;;
    # Phase 5 structural modes take a pattern/name as the query.
    symbols | class) return 0 ;;
    *) return 1 ;;
    esac
}

# Phase 5 structural (ast-grep) modes.
is_ast_mode() {
    case "$1" in
    struct | symbols | class) return 0 ;;
    *) return 1 ;;
    esac
}

# Phase 4 modes that take no query and an optional root only.
is_no_query_mode() {
    case "$1" in
    todo | unsafe-patterns) return 0 ;;
    *) return 1 ;;
    esac
}

# Phase 4 surface-scoped text modes: search like `text` but restricted to a
# fixed glob set. `docs` is split out from `text` so it is truly scoped.
is_surface_mode() {
    case "$1" in
    docs | tests | config | deps) return 0 ;;
    *) return 1 ;;
    esac
}

# surface_globs MODE — print the rg --glob include patterns for a surface mode,
# one per line. Used to restrict docs/tests/config/deps to their file families.
surface_globs() {
    case "$1" in
    docs)
        printf '%s\n' 'README*' 'CHANGELOG*' '*.md' '*.rst' '*.adoc' 'docs/**'
        ;;
    tests)
        printf '%s\n' 'tests/**' '__tests__/**' '*.test.*' '*.spec.*' '*Test.php'
        ;;
    config)
        printf '%s\n' '.env*' 'config/**' '*.yaml' '*.yml' '*.json' '*.toml' \
            '*.ini' '*.nix' 'docker-compose*'
        ;;
    deps)
        printf '%s\n' 'composer.json' 'composer.lock' 'package.json' \
            'package-lock.json' 'pnpm-lock.yaml' 'yarn.lock' 'flake.nix' \
            'go.mod' 'Cargo.toml' 'pyproject.toml'
        ;;
    esac
}
