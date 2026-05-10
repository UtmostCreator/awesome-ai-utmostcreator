# Good / Bad: Formatting and Linting

## Best (repository wrapper)

```bash
# Documentation quality check — markdownlint, link validation
bash scripts/ai/ai-doc-check.sh --check docs/ai

# Focused verification for any change type
bash scripts/ai/ai-verify.sh .
```

## Good

```bash
vendor/bin/pint --test
prettier --check .
shellcheck scripts/*.sh
shfmt -d scripts/*.sh
```

For targeted write:

```bash
prettier --write path/to/file.ts
vendor/bin/pint app/Services/Foo.php
```

## Bad

```bash
prettier --write .
vendor/bin/pint
```

without confirming scope.

Why bad:

- large unrelated formatting diffs
- hides actual logic changes
