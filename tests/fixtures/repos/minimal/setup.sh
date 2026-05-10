#!/usr/bin/env bash
# Creates a minimal git repo in a temporary directory for shell test fixtures.
# Usage: setup.sh [<target-dir>]
# Prints the repo path to stdout. Exits 0 on success.
set -euo pipefail

TARGET="${1:-$(mktemp -d)}"

# Also create a variant with a space in the name to catch quoting failures.
SPACED_TARGET="${2:-}"

mkdir -p "$TARGET"
cd "$TARGET"
git init --quiet
git config user.email "test@example.com"
git config user.name "Test User"
touch README.md
git add README.md
git commit --quiet -m "Initial commit"

if [[ -n "$SPACED_TARGET" ]]; then
    mkdir -p "$SPACED_TARGET"
    cd "$SPACED_TARGET"
    git init --quiet
    git config user.email "test@example.com"
    git config user.name "Test User"
    touch README.md
    git add README.md
    git commit --quiet -m "Initial commit"
fi

echo "$TARGET"
