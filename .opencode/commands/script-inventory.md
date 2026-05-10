---
description: Build AI script inventory with risk and parity checks
---

Create a script inventory for `$ARGUMENTS` using repository-approved wrappers.

1. Run `git status --short`.
2. Confirm scripts in `docs/ai/script-registry.json`.
3. Cross-check parity against `docs/ai/script-registry.md` and `docs/ai/scripts-reference.md`.
4. Return risk class and required tools for each relevant script.

Preferred command patterns:

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh tracked "scripts/ai/" . --fixed
AI_OUTPUT=json bash scripts/ai/ai-search.sh tracked "docs/ai/script-registry" . --fixed
```
