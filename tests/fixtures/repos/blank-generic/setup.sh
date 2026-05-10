#!/usr/bin/env bash
set -euo pipefail

TARGET="${1:-$(mktemp -d)}"

mkdir -p "$TARGET"
cd "$TARGET"
git init --quiet
git config user.email "test@example.com"
git config user.name "Test User"
printf '# Blank Generic Fixture\n' > README.md
git add README.md
git commit --quiet -m "Initial commit"

echo "$TARGET"
