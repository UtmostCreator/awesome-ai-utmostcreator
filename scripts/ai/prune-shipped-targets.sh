#!/usr/bin/env bash
# prune-shipped-targets.sh (thin loader)
#
# Read .ai-install-manifest.json and operate on the kit-author's local copies
# of files installed from packages/ai-universal-rules/templates/**. The 40
# entries where the manifest's `.value.source != .key` are duplicates of the
# shipped template; deleting the local copies prevents agents from editing
# the wrong side of the source/installed boundary.
#
# Modes (mutually exclusive): --list (default), --dry-run, --apply.
# Restore after --apply: `bash install-ai-kit.sh .` (or the php installer).
#
# The implementation lives in numbered, load-ordered modules under
# scripts/ai/internal/prune-shipped-targets/; this root file stays the public,
# registered entrypoint and its behavior is byte-for-byte identical to the
# previous monolithic version. Deletion is isolated to 60-apply.sh.
#
# See docs/ai/capabilities/evidence-first-execution/examples.md for the
# documented workflow.

set -euo pipefail
set -E

# Early --introspect / --help guard: when invoked with --introspect or --help/-h
# as the FIRST argument, emit this script's machine-readable JSON contract or its
# human-readable contract (static parse via sh-introspect) and exit before running
# any logic. The target script is parsed as text, never executed.
if [[ "${1:-}" == "--introspect" || "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    _ai_self_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    _ai_self_tool="$_ai_self_dir/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_self_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        if [[ "${1:-}" == "--introspect" ]]; then
            exec env AI_OUTPUT=json "${PHP_BIN:-php}" "$_ai_self_tool" "${BASH_SOURCE[0]}"
        fi
        exec "${PHP_BIN:-php}" "$_ai_self_tool" --format=help "${BASH_SOURCE[0]}"
    fi
fi

# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

SCRIPT_VERSION="1"
SCRIPT_NAME="prune-shipped-targets"

_ai_prune_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/internal/prune-shipped-targets"
# shellcheck source=scripts/ai/internal/prune-shipped-targets/10-rules.sh
source "$_ai_prune_dir/10-rules.sh"
# shellcheck source=scripts/ai/internal/prune-shipped-targets/60-apply.sh
source "$_ai_prune_dir/60-apply.sh"
# shellcheck source=scripts/ai/internal/prune-shipped-targets/90-run.sh
source "$_ai_prune_dir/90-run.sh"

prune_run "$@"
