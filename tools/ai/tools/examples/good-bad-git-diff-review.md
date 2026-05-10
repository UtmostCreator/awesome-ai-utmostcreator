# Good / Bad: Git Diff Review

## Best (repository wrapper)

```bash
# Investigate who/when/why a line changed
bash scripts/ai/git-forensics.sh app/Services/Checkout/CheckoutService.php

# Build AI-ready context from current diff
bash scripts/ai/ai-diff-context.sh since HEAD~3
```

## Good

```bash
git status --short
git diff --stat
git diff --check
git diff
```

Human review:

```bash
git diff | delta
difft old.php new.php
```

## Bad

```bash
git diff
# then final answer without git diff --check
```

Why bad:

- misses whitespace errors and conflict markers
- no change-scope summary
