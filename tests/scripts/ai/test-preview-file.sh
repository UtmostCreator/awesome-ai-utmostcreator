#!/usr/bin/env bash
set -euo pipefail

BASH_BIN="${BASH_BIN:-/opt/homebrew/bin/bash}"
repo_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../../.." && pwd)"
script="$repo_root/scripts/ai/preview-file.sh"

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

mkdir -p "$tmp/app" "$tmp/node_modules/pkg" "$tmp/.git"

cat > "$tmp/app/UserService.php" <<'PHP'
<?php
class UserService {
    public function login() {
        return true;
    }

    public function logout() {
        return true;
    }
}
PHP

# Help must work without a file.
"$BASH_BIN" "$script" --help >/dev/null

# Default/plain preview.
"$BASH_BIN" "$script" "$tmp/app/UserService.php" --lines 3 | grep -q 'UserService'

# Range preview.
"$BASH_BIN" "$script" "$tmp/app/UserService.php" --range 3:5 | grep -q 'login'

# Around preview.
"$BASH_BIN" "$script" "$tmp/app/UserService.php" --around 7 --context 1 | grep -q 'logout'

# JSON envelope.
AI_OUTPUT=json "$BASH_BIN" "$script" "$tmp/app/UserService.php" --lines 4 \
    | jq -e '
        .schema == "1"
        and .status == "ok"
        and .tool == "preview-file"
        and .path != ""
        and (.content | contains("UserService"))
        and (.warnings | type == "array")
        and (.errors | type == "array")
    ' >/dev/null

# Dry run.
AI_OUTPUT=json "$BASH_BIN" "$script" "$tmp/app/UserService.php" --around 4 --dry-run \
    | jq -e '.status == "dry_run" and .content == ""' >/dev/null

# Invalid line count must fail.
if "$BASH_BIN" "$script" "$tmp/app/UserService.php" --lines abc >/dev/null 2>&1; then
    echo "expected invalid --lines to fail" >&2
    exit 1
fi

# Invalid range must fail.
if "$BASH_BIN" "$script" "$tmp/app/UserService.php" --range 10:2 >/dev/null 2>&1; then
    echo "expected invalid --range to fail" >&2
    exit 1
fi

# Missing file must produce JSON error envelope.
missing_json="$(AI_OUTPUT=json "$BASH_BIN" "$script" "$tmp/app/Missing.php" 2>/dev/null || true)"
printf '%s' "$missing_json" | jq -e '.status == "error" and (.errors | length == 1)' >/dev/null

# Binary-looking file blocked by default.
printf '\000\001\002' > "$tmp/app/blob.bin"

if "$BASH_BIN" "$script" "$tmp/app/blob.bin" >/dev/null 2>&1; then
    echo "expected binary file to be blocked" >&2
    exit 1
fi

binary_json="$(AI_OUTPUT=json "$BASH_BIN" "$script" "$tmp/app/blob.bin" 2>/dev/null || true)"
printf '%s' "$binary_json" | jq -e '.status == "error" and (.errors[0] | contains("binary"))' >/dev/null

# Max bytes should block oversized files.
python3 - "$tmp/app/large.txt" <<'PY'
import sys
with open(sys.argv[1], "w", encoding="utf-8") as f:
    f.write("A" * 5000)
PY

if "$BASH_BIN" "$script" "$tmp/app/large.txt" --max-bytes 100 >/dev/null 2>&1; then
    echo "expected max-bytes block" >&2
    exit 1
fi

# Long line truncation.
"$BASH_BIN" "$script" "$tmp/app/large.txt" --force --max-bytes 10K --max-columns 20 --lines 1 \
    | grep -q 'truncated'

# Generated/vendor path warning.
AI_OUTPUT=json "$BASH_BIN" "$script" "$tmp/node_modules/pkg/index.js" --force 2>/dev/null \
    | jq -e '.status == "error" or (.warnings | type == "array")' >/dev/null || true

# .git internals blocked unless forced.
echo "secretish" > "$tmp/.git/config"

if "$BASH_BIN" "$script" "$tmp/.git/config" >/dev/null 2>&1; then
    echo "expected .git internals to be blocked" >&2
    exit 1
fi

echo "preview-file tests passed"
