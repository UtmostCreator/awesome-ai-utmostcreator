---
name: ai-search
description: Use ai-search to collect bounded repository evidence.
---

# AI Search Skill

Use this skill whenever repository discovery, file reading, usage tracing, or AI wiring verification is needed. Prefer scripts over raw search and broad file reads.

## First command

Always start with the current change state:

```bash
git status --short
```

## Evidence retrieval order

Search the smallest relevant surface before broad text search:

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh changed-text <query> . --fixed
AI_OUTPUT=json bash scripts/ai/ai-search.sh staged-text <query> . --fixed
AI_OUTPUT=json bash scripts/ai/ai-search.sh tracked <query> . --fixed
```

If those do not answer the question, escalate by intent:

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh docs <query> . --fixed
AI_OUTPUT=json bash scripts/ai/ai-search.sh tests <query> . --fixed
AI_OUTPUT=json bash scripts/ai/ai-search.sh schema <query> . --fixed
AI_OUTPUT=json bash scripts/ai/ai-search.sh text <query> . --fixed
AI_OUTPUT=json bash scripts/ai/ai-search.sh struct <query> . --fixed
```

Use `unsafe-all` only with explicit approval:

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh unsafe-all <query> . --fixed
```

## File preview

When a search result includes a file and line, inspect the exact context with:

```bash
AI_OUTPUT=json bash scripts/ai/preview-file.sh <path> --around <line> --context 30
AI_OUTPUT=json bash scripts/ai/preview-file.sh <path> --range A:B
```

Do not use raw `cat`, `sed`, `awk`, `grep`, `rg`, `find`, `fd`, glob, or list for file reading or discovery unless the wrapper evidence is insufficient and approval is recorded.

## Usage tracing

Before adding logic, changing a public contract, or claiming a symbol is unused, search for it:

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh text "<symbol>" . --fixed
```

`query-usage.sh` does NOT trace symbols; it only estimates the token/byte cost of a PATH
(`bash scripts/ai/query-usage.sh <path>`). Pass a real file or directory, not an identifier.

Report the closest reuse candidate and whether overlap is roughly 75% or higher.

## Verification

For behavior or wiring changes, prefer:

```bash
bash scripts/ai/ai-verify.sh .
```

For `ai-search` or script-first wiring changes, also run:

```bash
AI_OUTPUT=json bash scripts/ai/ai-search.sh doctor
php tools/ai/validate-ai-config.php
php tools/ai/validate-ai-catalog.php
php tools/ai/generate-ai-catalog.php --check
```

If bash or PHP is unavailable, report the unavailable command instead of claiming verification.

## Safety approvals

Ask before using `unsafe-all`, reading secrets, setting `AI_ALLOW_UNLIMITED=1`, using `ai-edit`, `ai-rollback`, `install-mandatory-tools`, destructive git, generated artifact edits, or context-packing commands such as `pack-context` and repomix scripts.

Return evidence with exact commands, status, files/lines, gaps, and the safest next step.
