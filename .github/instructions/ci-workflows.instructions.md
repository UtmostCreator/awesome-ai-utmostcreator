---
applyTo: ".github/workflows/**/*.yml,.github/workflows/**/*.yaml"
description: "CI workflow safety, permissions, and trigger-scope rules"
---

# CI Workflow Rules

- Treat workflow edits as high risk.
- Preserve least-privilege permissions.
- Avoid broad trigger expansion unless explicitly required.
- Do not add deploy/publish/release behavior without approval.
- Do not expose secrets in logs.
