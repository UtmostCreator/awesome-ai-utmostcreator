---
applyTo: 'tools/ai/**,scripts/ai/**,docs/ai/script-registry.md,docs/ai/script-registry.json,docs/ai/tools/**,policies/copilot/policy.yaml,.github/hooks/tool-policy.json'
description: 'AI tooling contract, registry alignment, and hook-policy consistency'
---

# AI Tooling Rules

- Prefer existing repository scripts over ad-hoc shell commands.
- Before adding a new AI tool, compare with existing tools and avoid duplicates when overlap is >=75%.
- Keep script/tool behavior deterministic.
- Preserve machine-readable output keys and exit semantics unless contract changes are approved.
- Keep script registry aligned across PHP source and docs JSON/MD.
- Keep hook policy aligned across policy, scripts, and hook config.

## Preview-file rule

Use `scripts/ai/preview-file.sh` instead of raw `cat` when inspecting files.

Required safe pattern:

```bash
AI_OUTPUT=json bash scripts/ai/preview-file.sh <path> --around <line> --context 30
```

Do not expand to whole-file reads unless the file is small and relevant.
