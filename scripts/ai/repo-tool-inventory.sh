#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"

exec "$PHP_BIN" "$ROOT/tools/ai/repo-tool-inventory.php" "$@"