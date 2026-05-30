---
description: Verify OpenCode script-first AI wiring
agent: repository-reviewer
---

Run the narrow wiring checks first:

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh doctor
php tools/ai/validate-ai-config.php
php tools/ai/validate-ai-catalog.php
php tools/ai/generate-ai-catalog.php --check
```

Checklist:

- `opencode.jsonc` keeps `permission.*` ask-by-default and denies raw destructive commands.
- Safe read-only wrappers (`ai-search`, `preview-file`, `query-usage`, `ai-diff-context`, `git-forensics`, `ai-doc-check`, `ai-test-select`, `ai-task`, `ai-structured`) are allowlisted.
- Mutating or broad wrappers (`ai-verify`, `pack-context`, repomix context scripts, `ai-edit`, `ai-rollback`, `install-mandatory-tools`) require ask.
- Researcher/reviewer agents deny edits and start with changed/staged/tracked evidence.
- File reading uses `preview-file`; usage discovery uses `query-usage`; verification uses `ai-verify`.

## Canonical References

Load only what is relevant: `docs/ai/project-context.md`, `docs/ai/workflow.md`, `docs/ai/AI-GUARDRAILS.md`.
