#!/usr/bin/env bash
set -euo pipefail

msg_file="${1:-}"
if [[ -z "$msg_file" || ! -f "$msg_file" ]]; then
    echo "[hook] commit-msg file missing"
    exit 1
fi

msg="$(head -n 1 "$msg_file")"

if [[ "$msg" =~ ^(feat|fix|docs|style|refactor|perf|test|build|ci|chore|revert)(\(.+\))?:\ .+ ]]; then
    exit 0
fi

echo "[hook] commit message should follow: type(scope): summary"
echo "[hook] got: $msg"
exit 1
