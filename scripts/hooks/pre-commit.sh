#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[hook] pre-commit checks"

staged_files="$(git diff --cached --name-only --diff-filter=ACM || true)"

is_ai_kit_source_repo() {
    [[ -d packages/ai-universal-rules/templates \
        && -f packages/ai-universal-rules/package-lock.ai.json \
        && -f tools/ai/ai.php \
        && -f tools/ai/generate-ai-catalog.php ]]
}

needs_ai_kit_generation() {
    [[ -n "$staged_files" ]] || return 1

    while IFS= read -r file; do
        [[ -n "$file" ]] || continue
        case "$file" in
            packages/ai-universal-rules/templates/*|\
            packages/ai-universal-rules/docs/*|\
            packages/ai-universal-rules/catalog.json|\
            packages/ai-universal-rules/manifest.json|\
            packages/ai-universal-rules/manifest.yml|\
            docs/ai/capabilities/*|\
            docs/ai/snippets/*|\
            tools/ai/ai_catalog_lib.php|\
            tools/ai/install/packs.php|\
            tools/ai/install/profiles.php)
                return 0
                ;;
        esac
    done <<<"$staged_files"

    return 1
}

stage_if_present() {
    local path
    for path in "$@"; do
        if [[ -e "$path" ]]; then
            git add "$path"
        fi
    done
}

if [[ -n "$staged_files" ]]; then
    while IFS= read -r file; do
        [[ -n "$file" ]] || continue
        if git show ":$file" | rg -n '^(<<<<<<<|=======|>>>>>>>)' >/dev/null 2>&1; then
            echo "[hook] merge conflict markers found in staged blob: $file"
            exit 1
        fi
    done <<<"$staged_files"
fi

if command -v php >/dev/null 2>&1; then
    if is_ai_kit_source_repo && needs_ai_kit_generation; then
        echo "[hook] refreshing AI-kit source generated artifacts"
        php tools/ai/ai.php package-lock --update
        php tools/ai/generate-ai-catalog.php
        stage_if_present \
            packages/ai-universal-rules/package-lock.ai.json \
            .ai/package-lock.ai.json \
            docs/ai/catalog.md \
            .ai/catalog.json \
            packages/ai-universal-rules/catalog.json \
            llms.txt
        php tools/ai/ai.php package-verify
        php tools/ai/generate-ai-catalog.php --check
    fi

    changed_php="$(git diff --cached --name-only --diff-filter=ACM | rg '\.php$' || true)"
    if [[ -n "$changed_php" ]]; then
        while IFS= read -r file; do
            [[ -n "$file" ]] || continue
            tmp_file="$(mktemp "${TMPDIR:-/tmp}/app-configs-php-lint.XXXXXX.php")"
            git show ":$file" >"$tmp_file"
            php -l "$tmp_file" >/dev/null
            rm -f "$tmp_file"
        done <<<"$changed_php"
    fi
else
    echo "[hook] php binary not found (skip lint)"
fi

if command -v gitleaks >/dev/null 2>&1; then
    gitleaks protect --staged --redact --verbose
elif command -v trufflehog >/dev/null 2>&1; then
    trufflehog git file://. --since-commit HEAD --results=verified,unknown --fail
else
    echo "[hook] neither gitleaks nor trufflehog found (skip secret scan)"
fi

if [[ -n "$staged_files" ]]; then
    echo "[hook] running full repo health check"
    bash scripts/repo-health-check.sh staged
fi

echo "[hook] pre-commit passed"
