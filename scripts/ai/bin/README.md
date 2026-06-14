# scripts/ai/bin — role/risk alias tree

This directory holds **generated delegating shims** that group the canonical AI
scripts by role and risk, so agents and humans can discover tools by intent.

## Contract

- Each `scripts/ai/bin/<role>/<name>.sh` is a thin shim that `exec`s the canonical
  implementation at `scripts/ai/<name>.sh`. The canonical file stays at the root;
  `bin/` is an alias tree, not the implementation's home.
- Shims are **unregistered** (not in `tools/ai/install/script-registry.php`); they ship
  via a single `scripts/ai/bin` packs `dir` entry, so the registry↔packs 1:1 invariant
  is unaffected.
- Do **not** hand-edit shims — they are derived artifacts. Edit the canonical root
  script instead.
- Root script names are the public/registered contract and must not change; `bin/`
  aliases never replace them.

## Roles

- `read/` — read-only discovery (search, preview, usage).
- `context/` — context packing, diff/PR context, repomix routing.
- `verify/` — tests, checks, validation, freshness, reference integrity.
- `edit/` — guarded edit/task execution.
- `admin/` — install coverage, inventory, bundle, all-in-one orchestration.
- `hooks/` — pre/post tool-use policy and evidence hooks.

See `scripts/ai/MANIFEST.md` for the full role/risk inventory.
