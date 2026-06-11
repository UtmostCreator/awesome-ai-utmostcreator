---
description: Collect script-first repository evidence using ai-search
agent: repository-researcher
---

Use this command to answer the user's evidence request without broad raw search.

## Mandatory sequence

```bash
git status --short
AI_OUTPUT=json bash scripts/ai/ai-search.sh changed "$ARGUMENTS" . --fixed
AI_OUTPUT=json bash scripts/ai/ai-search.sh staged "$ARGUMENTS" . --fixed
AI_OUTPUT=json bash scripts/ai/ai-search.sh tracked "$ARGUMENTS" . --fixed
```

If changed/staged/tracked evidence is insufficient, escalate narrowly in this order:

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh docs "$ARGUMENTS" . --fixed
AI_OUTPUT=json bash scripts/ai/ai-search.sh tests "$ARGUMENTS" . --fixed
AI_OUTPUT=json bash scripts/ai/ai-search.sh schema "$ARGUMENTS" . --fixed
```

Preview any cited file or line with:

```bash
AI_OUTPUT=json bash scripts/ai/preview-file.sh <path> --around <line> --context 30
```

For usage, impact, or duplication questions, search with `AI_OUTPUT=json bash scripts/ai/ai-search.sh text "<symbol>" . --fixed` (and `git grep`). Use `bash scripts/ai/query-usage.sh <path>` only to estimate the token/byte cost of a file or directory; it is not a symbol search.

## Return fields

- `status`: found, partial, or unknown
- `queries_run`: exact commands or modes used
- `evidence`: file, line, and short finding
- `gaps`: what the evidence does not prove
- `next_step`: safest follow-up
