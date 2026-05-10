# Good / Bad: AI Context Packing

## Best (repository wrapper)

```bash
# Pack changed-file context for AI
bash scripts/ai/ai-diff-context.sh since HEAD~3

# Pack specific area with safe defaults
bash scripts/ai/pack-context.sh app/Services/Checkout

# Plan what fits in token budget before packing
bash scripts/ai/repomix-scc-router.sh .

# Run repomix with repo-aware exclusions
bash scripts/ai/run-repomix-context.sh --include "app/Services/Checkout/**"

# Build context tree index for large repos
bash scripts/ai/repomix-context-tree.sh .
```

## Good

```bash
scc .
rg -n "CheckoutService|DirectPackageAccommodationService" app tests
repomix --include "app/Services/Checkout/**/*.php,tests/Feature/*Checkout*.php"
files-to-prompt docs/ai/project-context.md docs/ai/workflow.md
```

## Bad

```bash
repomix .
cat $(find . -type f)
```

Why bad:

- excessive noise
- includes irrelevant/generated/vendor content
- wastes token budget
