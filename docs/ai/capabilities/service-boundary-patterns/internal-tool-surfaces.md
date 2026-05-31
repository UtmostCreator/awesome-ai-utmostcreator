# Internal Tool Surfaces

Use this guide when changing scripts, validators, installers, command policies,
or AI runtime adapters.

## Tool Boundary Rules

- Prefer registered scripts and documented tool wrappers over ad hoc commands.
- Preserve existing command paths unless you also provide compatibility shims.
- Keep permission policies and runtime adapter files aligned with the command
  paths they allow.
- Treat installer packs, manifests, catalogs, and validation workflows as one
  contract surface.
- Do not widen tool permissions while doing unrelated maintenance.

## Adapter Surface Rules

- Canonical source -> provider registry -> provider renderer -> provider output
  -> validation/drift check.
- Keep OpenCode, Copilot, and Claude differences in adapters or renderers.
- Do not duplicate long procedure bodies across agents, skills, prompts, and
  commands.
- Move durable workflow detail into capabilities and keep runtime launchers
  thin.

## Verification

Run the narrowest validator that covers the touched surface first, then
escalate only if needed:

- config or policy change: `php tools/ai/validate-ai-config.php`
- catalog or package resource change: `php tools/ai/validate-ai-catalog.php`
- install pack or template change: `php tools/ai/validate-install-surface.php --strict`
- generated catalog change: `php tools/ai/generate-ai-catalog.php --check`
