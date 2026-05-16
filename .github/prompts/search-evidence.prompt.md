---
description: Collect repository evidence using ai-search
---

Collect repository evidence for `$ARGUMENTS`.

1. Run `git status --short`.
2. Search changed, staged, then tracked evidence before broad modes.
3. Prefer JSON output.

Default commands:

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh changed "$ARGUMENTS" . --fixed
AI_OUTPUT=json bash scripts/ai/ai-search.sh staged "$ARGUMENTS" . --fixed
AI_OUTPUT=json bash scripts/ai/ai-search.sh tracked "$ARGUMENTS" . --fixed
```
