# scripts/ai/internal — private implementation modules

This directory holds the **private implementation** behind the canonical
`scripts/ai/*.sh` scripts. Nothing here is a public entrypoint.

## Contract

- Modules are loaded only through their facades (e.g. `scripts/ai/common.sh`,
  `scripts/ai/ai-search.sh`); do **not** source them directly from outside the kit.
- These files are NOT in the script registry and are NOT public contract; they may be
  refactored freely as long as the canonical root scripts keep their behavior.
- The canonical root script name remains the public/registered contract; internals
  live here so the root stays a thin, stable facade.

## Layout

- `lib/` — shared sourced shell helpers (env, json, paths, policy, secrets, snapshot).
- `search/` — ai-search backend modules (parse, scope, guards, backends, dispatch).
- `config/` — shared exclude-dir lists consumed by report/search scripts.
- `ai-edit/`, `ai-verify/`, `ai-diff-context/`, `pre-tool-use/`,
  `prune-shipped-targets/`, `repomix-context-tree/`, `repomix-scc-router/` — per-script
  module splits for the larger entrypoints.

See `scripts/ai/MANIFEST.md` for the full inventory and `scripts/ai/bin/README.md` for
the role/risk alias tree.
