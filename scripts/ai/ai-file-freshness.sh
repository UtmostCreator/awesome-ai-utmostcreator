#!/usr/bin/env bash
set -euo pipefail
# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

git status --short docs .github .opencode AGENTS.md 2>/dev/null || true
