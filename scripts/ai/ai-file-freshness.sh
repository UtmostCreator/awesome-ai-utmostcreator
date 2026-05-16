#!/usr/bin/env bash
set -euo pipefail
git status --short docs .github .opencode AGENTS.md 2>/dev/null || true
