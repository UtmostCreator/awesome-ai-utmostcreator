# Good / Bad: Research Sequence

## Best (repository wrapper)

```bash
# Start with changed files
AI_OUTPUT=json bash scripts/ai/ai-search.sh changed-text "KEYWORD" . --fixed

# Then staged files
AI_OUTPUT=json bash scripts/ai/ai-search.sh staged-text "KEYWORD" . --fixed

# Then tracked files
AI_OUTPUT=json bash scripts/ai/ai-search.sh tracked "KEYWORD" . --fixed

# Then broader text search
AI_OUTPUT=json bash scripts/ai/ai-search.sh text "KEYWORD" . --fixed

# Preview specific results
AI_OUTPUT=json bash scripts/ai/preview-file.sh path/to/file --around 42 --context 30
```

## Good

```bash
git status --short
git diff --stat
git log --oneline --decorate -10
rg --files | head -200
rg -n "KEYWORD"
bat -n path/to/file
jq '.scripts' package.json 2>/dev/null || true
```

## Bad

```bash
cat lots/of/files
grep -R "KEYWORD" .
start editing immediately
```

Why bad:

- reads too much
- no repo state awareness
- increases hallucination risk
