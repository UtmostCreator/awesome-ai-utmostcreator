#!/usr/bin/env bash
set -euo pipefail

TARGET="${1:-$(mktemp -d)}"

mkdir -p "$TARGET/.opencode/agents"
cd "$TARGET"
git init --quiet
git config user.email "test@example.com"
git config user.name "Test User"
printf '# Existing OpenCode Fixture\n' > README.md
cat > .opencode/agents/custom.md <<'EOF'
---
name: custom
mode: subagent
tools: ["read", "bash"]
hidden: false
---

Custom existing agent in fixture.
EOF
git add README.md .opencode/agents/custom.md
git commit --quiet -m "Initial fixture with existing opencode agent"

echo "$TARGET"
