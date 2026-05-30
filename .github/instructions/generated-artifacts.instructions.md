---
applyTo: "docs/ai/generated/**,schemas/ai/**,tools/ai/**,scripts/ai/**,docs/ai/script-registry.md,docs/ai/script-registry.json"
description: "Generated artifact drift routing, source-first regeneration policy, and verbose output control"
---

# Generated Artifacts

- Treat generated outputs as non-canonical unless explicitly marked otherwise.
- Change source/generator/schema first, then regenerate outputs.
- Do not manually edit generated output to mask drift.

## Essential vs Ephemeral

Essential (consumed by other tools, do not delete between related commands):

- docs/ai/generated/preflight.json
- docs/ai/generated/package-verify.json
- docs/ai/generated/install.json
- docs/ai/generated/verify.json
- docs/ai/generated/adapter-plan.json
- docs/ai/generated/advisor.json
- docs/ai/generated/advisor-context.md
- docs/ai/generated/advisor-prompt.md
- docs/ai/generated/advisor-drift.md
- docs/ai/generated/advisor-secret-findings.json
- docs/ai/generated/install-manifest.json
- docs/ai/generated/install-instructions.json
- docs/ai/generated/repo-structure.json
- docs/ai/generated/artifacts.json

Ephemeral (informational only, safe to delete, regenerated on next run):

- docs/ai/generated/analysis-\*.json
- docs/ai/generated/workspace-\*.json
- docs/ai/generated/decisions-\*.json
- docs/ai/generated/git-\*.json
- docs/ai/generated/next-\*.json

## Markdown Duplicates

Markdown duplicate files in docs/ai/generated/ are not written by default.
They are JSON wrapped in a markdown code block; no tool reads them.
To generate them for manual inspection, set the env var before the command:

```bash
AI_ARTIFACTS_VERBOSE=1 php tools/ai/ai.php <command>
```

## Clean Up

All files in docs/ai/generated/ are safe to delete and regenerate.

Regenerate only what you need next.

For full policy, see `docs/ai/generated-artifacts.md`.

## Canonical References

Prefer canonical repository docs over this adapter file:

- `docs/ai/project-context.md`
- `docs/ai/workflow.md`
- `docs/ai/AI-GUARDRAILS.md`
- `docs/ai/generated-artifacts.md`
