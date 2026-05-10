# Good / Bad: Text Search

## Best (repository wrapper)

```bash
# Unified search — bounded output, respects ignores, JSON mode available
AI_OUTPUT=json bash scripts/ai/ai-search.sh text "CheckoutService" tests --fixed

# Focused ripgrep wrapper — safe defaults, auto-excludes vendor/node_modules
bash scripts/ai/rg-code.sh "TODO|FIXME" .

# Search only tracked files (git-aware)
AI_OUTPUT=json bash scripts/ai/ai-search.sh tracked "Context Gate" . --fixed

# Search only changed files
AI_OUTPUT=json bash scripts/ai/ai-search.sh changed "refactor" . --fixed
```

## Good

```bash
rg -n "new CheckoutService" tests -g "*.php"
git grep -n "Context Gate"
rg -n "TODO|FIXME" --glob "!vendor" --glob "!node_modules"
```

## Bad

```bash
grep -R "new CheckoutService" .
grep -rn "TODO" . | grep -v vendor
```

Why bad:

- noisy
- slower
- easy to include ignored files
