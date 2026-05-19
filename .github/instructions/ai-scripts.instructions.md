---
applyTo: 'scripts/ai/**,docs/ai/script-registry.md,docs/ai/script-registry.json,docs/ai/script-registry.schema.json,docs/ai/scripts-reference.md,docs/ai/tools/actions/use-ai-script.md'
description: 'AI script registry consistency and risk-based execution rules'
---

# AI Scripts Rules

- Keep `docs/ai/script-registry.json` canonical and machine-readable.
- Keep `docs/ai/script-registry.md` and `docs/ai/scripts-reference.md` aligned with registry entries.
- Use `docs/ai/script-registry.schema.json` for registry contract shape.
- Prefer read-only wrappers before broad or mutating scripts.
- Require explicit approval before mutating scripts.
