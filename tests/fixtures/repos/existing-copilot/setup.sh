#!/usr/bin/env bash
set -euo pipefail

TARGET="${1:-$(mktemp -d)}"

mkdir -p "$TARGET/.github"
cd "$TARGET"
git init --quiet
git config user.email "test@example.com"
git config user.name "Test User"
printf '# Existing Copilot Fixture\n' > README.md
cat > .github/copilot-instructions.md <<'EOF'
# Existing Copilot Instructions

Keep this file unchanged unless force overwrite is requested.
EOF
git add README.md .github/copilot-instructions.md
git commit --quiet -m "Initial fixture with existing copilot instructions"

echo "$TARGET"
