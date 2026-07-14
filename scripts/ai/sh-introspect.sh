#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"

# `--introspect` (with no file argument) reports THIS wrapper's own contract,
# matching the universal --introspect convention. Any other args pass through to
# the introspector unchanged (e.g. `--all`, a FILE, `--format=...`).
if [[ "${1:-}" == "--introspect" && "$#" -eq 1 ]]; then
    exec env AI_OUTPUT=json "$PHP_BIN" "$ROOT/tools/ai/sh-introspect.php" "${BASH_SOURCE[0]}"
fi

exec "$PHP_BIN" "$ROOT/tools/ai/sh-introspect.php" "$@"
