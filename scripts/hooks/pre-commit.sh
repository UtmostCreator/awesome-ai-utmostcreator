#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[hook] pre-commit checks"

staged_files="$(git diff --cached --name-only --diff-filter=ACM || true)"

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
