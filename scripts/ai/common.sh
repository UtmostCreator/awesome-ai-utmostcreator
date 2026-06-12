#!/usr/bin/env bash
# Shared compatibility facade for repository AI tooling scripts.
#
# This file is a thin facade. All logic lives in ordered modules under
# scripts/ai/lib/, sourced here in 00->90 order. Each module is idempotent
# (unique source guard) so re-sourcing common.sh is safe. Dependent scripts
# source ONLY this file; do not source lib modules directly.
#
# Module map:
#   00-env.sh        env defaults, color vars
#   05-core.sh       logging primitives, command/version probes, generic utils
#   10-json.sh       JSON, redaction, envelope helpers
#   20-paths.sh      path/repo validation, tool discovery
#   30-logging.sh    structured event logging
#   40-session.sh    agent session init
#   50-policy.sh     command classification and approval policy
#   60-exec-guard.sh timeout and hang/freeze guards
#   70-secrets.sh    secret scanning
#   80-tokens.sh     token estimation and previews
#   90-snapshot.sh   snapshot create/apply (rollback mechanism)

set -euo pipefail

# Universal --introspect guard. When a script that sources this file is invoked
# with `--introspect` as its FIRST argument, emit that script's machine-readable
# JSON contract (via the static introspector) and exit, WITHOUT running any of
# the script's own logic. The target script is parsed statically, never executed.
#
# This gives every common.sh-sourcing script a uniform `--introspect` surface.
# Scripts that need to handle `--introspect` earlier (e.g. before sourcing, like
# ai-search.sh) still can; this guard only runs when reached. It is a no-op when
# the first argument is anything other than `--introspect`.
if [[ "${1:-}" == "--introspect" ]]; then
    _ai_introspect_here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    # Target = the script that sourced common.sh; when common.sh is run directly
    # (no sourcer), introspect common.sh itself.
    _ai_introspect_target="${BASH_SOURCE[1]:-${BASH_SOURCE[0]}}"
    _ai_introspect_tool="$_ai_introspect_here/../../tools/ai/sh-introspect.php"
    if [[ -n "$_ai_introspect_target" && -f "$_ai_introspect_tool" ]] \
        && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        exec env AI_OUTPUT=json "${PHP_BIN:-php}" \
            "$_ai_introspect_tool" "$_ai_introspect_target"
    fi
fi

_AI_COMMON_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
_AI_COMMON_LIB_DIR="${_AI_COMMON_DIR}/lib"

# shellcheck source=scripts/ai/lib/00-env.sh
source "${_AI_COMMON_LIB_DIR}/00-env.sh"
# shellcheck source=scripts/ai/lib/05-core.sh
source "${_AI_COMMON_LIB_DIR}/05-core.sh"
# shellcheck source=scripts/ai/lib/10-json.sh
source "${_AI_COMMON_LIB_DIR}/10-json.sh"
# shellcheck source=scripts/ai/lib/20-paths.sh
source "${_AI_COMMON_LIB_DIR}/20-paths.sh"
# shellcheck source=scripts/ai/lib/30-logging.sh
source "${_AI_COMMON_LIB_DIR}/30-logging.sh"
# shellcheck source=scripts/ai/lib/40-session.sh
source "${_AI_COMMON_LIB_DIR}/40-session.sh"
# shellcheck source=scripts/ai/lib/50-policy.sh
source "${_AI_COMMON_LIB_DIR}/50-policy.sh"
# shellcheck source=scripts/ai/lib/60-exec-guard.sh
source "${_AI_COMMON_LIB_DIR}/60-exec-guard.sh"
# shellcheck source=scripts/ai/lib/70-secrets.sh
source "${_AI_COMMON_LIB_DIR}/70-secrets.sh"
# shellcheck source=scripts/ai/lib/80-tokens.sh
source "${_AI_COMMON_LIB_DIR}/80-tokens.sh"
# shellcheck source=scripts/ai/lib/90-snapshot.sh
source "${_AI_COMMON_LIB_DIR}/90-snapshot.sh"
