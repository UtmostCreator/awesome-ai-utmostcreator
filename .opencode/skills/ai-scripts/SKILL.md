---
name: ai-scripts
description: Use registered scripts with risk-based approvals and evidence.
---

# AI Scripts Skill

Use this skill when selecting or running repository scripts from `scripts/ai/`.

## Selection order

1. Confirm script is listed in `docs/ai/script-registry.json`.
2. Confirm doc parity in `docs/ai/script-registry.md` and `docs/ai/scripts-reference.md`.
3. Prefer the narrowest read-only wrapper that answers the request.

## Preferred wrappers

- Discovery/evidence: `ai-search`, `preview-file`, `query-usage`
- Change-aware context: `ai-diff-context`, `git-forensics`
- Wiring checks: `ai-doc-check`, `ai-search doctor`
- Verification: `ai-verify`

## Approval gate

Ask before mutating or high-impact wrappers, including `ai-edit`, `ai-rollback`, `install-mandatory-tools`, and broad context packers.

## Evidence commands

```bash
git status --short
AI_OUTPUT=json bash scripts/ai/ai-search.sh changed <query> . --fixed
AI_OUTPUT=json bash scripts/ai/ai-search.sh staged <query> . --fixed
AI_OUTPUT=json bash scripts/ai/ai-search.sh tracked <query> . --fixed
```

Use `preview-file` for line-level context:

```bash
AI_OUTPUT=json bash scripts/ai/preview-file.sh <path> --around <line> --context 30
```

If runtime lacks `bash` or `php`, record the command as not run and keep claims bounded.
