# Good / Bad: Code Structure Search

## Best (repository wrapper)

```bash
# AST-aware structural search via ai-search facade
AI_LANG=php AI_OUTPUT=json bash scripts/ai/ai-search.sh struct 'new CheckoutService($A, $B)' app --fixed

# Find all usages of a symbol before changing it
bash scripts/ai/query-usage.sh CheckoutService
```

## Good

```bash
sg -p 'new CheckoutService($A, $B)' -l php tests
sg -p 'console.log($A)' -l ts src
semgrep -e 'eval($X)' --lang php app
```

## Bad

```bash
rg "new CheckoutService\("
rg "console\.log\("
sed -i 's/oldFunction/newFunction/g' src/**/*.ts
```

Why bad:

- regex can match comments/strings
- bulk text replacement can corrupt code
- AST search reduces false positives
