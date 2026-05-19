---
applyTo: 'AGENTS.md,CLAUDE.md,README.md,docs/ai/**,.github/copilot-instructions.md,.github/agents/**,.github/instructions/**,.github/prompts/**,.github/skills/**,.opencode/**,scripts/ai/**,tools/ai/**'
description: 'AI workflow file roles, line budgets, duplication rules, and adapter boundaries'
---

# AI File Standards

- Follow `docs/ai/ai-file-standards.md` before adding or expanding AI workflow files.
- Keep each file single-purpose: instructions set stable rules, prompts and commands launch tasks, agents define role/tool boundaries, skills adapt capabilities, and capabilities own reusable procedure.
- Do not duplicate full capability bodies in prompts, commands, skills, agents, or always-on instructions.
- Keep provider-specific syntax in provider adapters; keep portable workflow logic in `docs/ai/capabilities/`.
- Prefer links to canonical docs over copied policy text, except for short critical routing needed by a runtime.
- Respect the hard line limits in `docs/ai/ai-file-standards.md`; split or reference support files instead of expanding beyond them.
- Treat generated outputs as non-canonical unless `docs/ai/generated-artifacts.md` or the generator contract says otherwise.
- OpenCode agents should use `permission`, not deprecated `tools`.
- Copilot `.instructions.md` files should include `applyTo` when deterministic loading matters.
