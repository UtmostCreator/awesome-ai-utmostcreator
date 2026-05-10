# Good / Bad: GitHub Actions

## Best (repository wrapper)

```bash
# Get structured PR context — description, diff summary, check status
bash scripts/ai/gh-pr-context.sh 42

# JSON output
AI_OUTPUT=json bash scripts/ai/gh-pr-context.sh 42
```

## Good

```bash
actionlint
yq '.on' .github/workflows/*.yml
yq '.jobs | keys' .github/workflows/*.yml
```

## Bad

```bash
grep -R "uses:" .github/workflows
grep -R "pull_request" .github/workflows
```

Why bad:

- YAML structure matters
- expressions can be invalid even when grep looks fine
