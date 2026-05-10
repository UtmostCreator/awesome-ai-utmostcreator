# Good / Bad: Edit Sequence

## Best (repository wrapper)

```bash
# Save session checkpoint before risky edits
bash scripts/ai/session-checkpoint.sh create "before refactor"

# Use bounded search-and-replace (requires approval)
bash scripts/ai/ai-edit.sh replace "old_func" "new_func" app/Services

# Rollback if something went wrong (requires approval)
bash scripts/ai/ai-rollback.sh restore latest
```

## Good

```bash
git status --short
rg -n "target"
bat -n path/to/file

# minimal edit

git diff --check
git diff --stat
git diff
php artisan test --filter=SpecificTest
```

## Bad

```bash
sed -i 's/target/replacement/g' $(find . -type f)
php artisan test
final answer
```

Why bad:

- broad edit
- no scope review
- no diff check
