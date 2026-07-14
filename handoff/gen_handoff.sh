#!/usr/bin/env bash
# Validate the agent-handoff model and (re)generate the Mermaid diagrams.
#
#   ./handoff/gen_handoff.sh            # validate + render handoff/generated/*.mmd
#   ./handoff/gen_handoff.sh --check    # validate only
#
# gen_handoff.py needs only PyYAML. If the python3 on PATH lacks it, this
# wrapper falls back to nix-shell, mirroring the repo's other generators.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GEN="${SCRIPT_DIR}/gen_handoff.py"
MODEL="${SCRIPT_DIR}/agent-handoff.yaml"
OUT="${SCRIPT_DIR}/generated"

ARGS=("${MODEL}" --out "${OUT}" "$@")

if python3 -c 'import yaml' >/dev/null 2>&1; then
  exec python3 "${GEN}" "${ARGS[@]}"
elif command -v nix-shell >/dev/null 2>&1; then
  exec nix-shell -p "python3.withPackages(ps: [ ps.pyyaml ])" \
    --run "python3 '${GEN}' ${ARGS[*]}"
else
  echo "ERROR: need python3 with pyyaml, or nix-shell on PATH." >&2
  exit 2
fi
