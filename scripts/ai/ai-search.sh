#!/usr/bin/env bash
set -euo pipefail

# ai-search.sh — unified repository search entrypoint (thin facade).
#
# All logic lives in ordered modules under scripts/ai/ai-search/, sourced here
# in load order. The entrypoint only: records its own path (so modules resolve
# common.sh and the introspector relative to scripts/ai/), runs the bootstrap
# guards, loads the modules, and calls ai_search_main.
#
# Module map (load order):
#   00-bootstrap.sh        early --help/--introspect guards; source common.sh
#   10-contract.sh         usage() + help summary + envelope contract comments
#   20-state.sh            defaults, warning state, init_run_state()
#   25-modes.sh            mode taxonomy (is_*_mode, surface_globs)
#   40-output-json.sh      emit_json/fail/to_json_array/canonical_root/validate
#   45-results-rg.sh       rg-json + line-to-result transforms
#   50-results-context.sh  context enrichment + max-bytes trimming
#   30-parse-flags.sh      flag parser
#   35-parse-positionals.sh legacy aliases + query/root interpretation
#   55-scope-args.sh       case/pattern/scope args, ignore + global gitignore
#   60-guards.sh           tool/git-root guards
#   65-backend-files.sh    changed-files/staged-files/files
#   70-backend-text.sh     text/docs/tests/config/deps/tracked/changed/staged-text
#   75-backend-git.sh      diff/history
#   80-backend-curated.sh  todo/unsafe-patterns
#   85-backend-ast.sh      struct/symbols/class
#   90-doctor.sh           doctor
#   95-dispatch.sh         dispatch + output assembly + ai_search_main
#
# Load-order constraint: 40-output-json.sh defines emit_json() before fail();
# the parser (30) depends on validate_non_negative_int()/fail() (40); dispatch
# (95) depends on every prior module.

# Entrypoint path, exported so modules resolve common.sh and sibling tools
# relative to scripts/ai/ rather than scripts/ai/ai-search/.
AI_SEARCH_ENTRYPOINT="${BASH_SOURCE[0]}"
export AI_SEARCH_ENTRYPOINT

_search_dir="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)/ai-search"

# shellcheck source=scripts/ai/ai-search/00-bootstrap.sh
source "$_search_dir/00-bootstrap.sh"
# shellcheck source=scripts/ai/ai-search/10-contract.sh
source "$_search_dir/10-contract.sh"
# shellcheck source=scripts/ai/ai-search/20-state.sh
source "$_search_dir/20-state.sh"
# shellcheck source=scripts/ai/ai-search/25-modes.sh
source "$_search_dir/25-modes.sh"
# shellcheck source=scripts/ai/ai-search/40-output-json.sh
source "$_search_dir/40-output-json.sh"
# shellcheck source=scripts/ai/ai-search/45-results-rg.sh
source "$_search_dir/45-results-rg.sh"
# shellcheck source=scripts/ai/ai-search/50-results-context.sh
source "$_search_dir/50-results-context.sh"
# shellcheck source=scripts/ai/ai-search/30-parse-flags.sh
source "$_search_dir/30-parse-flags.sh"
# shellcheck source=scripts/ai/ai-search/35-parse-positionals.sh
source "$_search_dir/35-parse-positionals.sh"
# shellcheck source=scripts/ai/ai-search/55-scope-args.sh
source "$_search_dir/55-scope-args.sh"
# shellcheck source=scripts/ai/ai-search/60-guards.sh
source "$_search_dir/60-guards.sh"
# shellcheck source=scripts/ai/ai-search/65-backend-files.sh
source "$_search_dir/65-backend-files.sh"
# shellcheck source=scripts/ai/ai-search/70-backend-text.sh
source "$_search_dir/70-backend-text.sh"
# shellcheck source=scripts/ai/ai-search/75-backend-git.sh
source "$_search_dir/75-backend-git.sh"
# shellcheck source=scripts/ai/ai-search/80-backend-curated.sh
source "$_search_dir/80-backend-curated.sh"
# shellcheck source=scripts/ai/ai-search/85-backend-ast.sh
source "$_search_dir/85-backend-ast.sh"
# shellcheck source=scripts/ai/ai-search/90-doctor.sh
source "$_search_dir/90-doctor.sh"
# shellcheck source=scripts/ai/ai-search/95-dispatch.sh
source "$_search_dir/95-dispatch.sh"

ai_search_main "$@"
