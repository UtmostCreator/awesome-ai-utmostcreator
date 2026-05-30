---
applyTo: "docs/ai/generated/**,schemas/ai/**,tools/ai/**,scripts/ai/**,docs/ai/script-registry.md,docs/ai/script-registry.json"
description: "Generated artifact drift routing, source-first regeneration policy, and verbose output control"
---

# Generated Artifacts

- Treat generated outputs as non-canonical unless explicitly marked otherwise.
- Change source/generator/schema first, then regenerate outputs.
- Do not manually edit generated output to mask drift.

## Essential vs Ephemeral

Essential (consumed by other tools — do not delete between related commands):

- `preflight.json`, `package-verify.json`, `install.json`, `verify.json`, `adapter-plan.json`, `advisor.json`
- `advisor-context.md`, `advisor-prompt.md`, `advisor-drift.md`
- `advisor-secret-findings.json`, `install-manifest.json`, `install-instructions.json`
- `repo-structure.json`, `artifacts.json`

Ephemeral (informational only — safe to delete, regenerated on next run):

- `analysis-*.json`, `workspace-*.json`, `decisions-*.json`, `git-*.json`, `next-*.json`

## Markdown Duplicates

`.md` files in `docs/ai/generated/` are **not** written by default.
They are JSON wrapped in a markdown code block — no tool reads them.
To generate them for manual inspection, set the env var before the command:

```bash
AI_ARTIFACTS_VERBOSE=1 php tools/ai/ai.php <command>
```

## Clean Up

All files in `docs/ai/generated/` are safe to delete:

```bash
rm -rf docs/ai/generated/*
```

Regenerate only what you need next.

For full policy, see `docs/ai/generated-artifacts.md`.

## Canonical References

Prefer canonical repository docs over this adapter file:

- `docs/ai/project-context.md`
- `docs/ai/workflow.md`
- `docs/ai/AI-GUARDRAILS.md`
- `docs/ai/generated-artifacts.md`
