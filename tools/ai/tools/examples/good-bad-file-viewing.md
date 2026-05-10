# Good / Bad: File Viewing

## Best (repository wrapper)

```bash
# Safe bounded preview — line numbers, max-lines, truncation protection
bash scripts/ai/preview-file.sh app/Services/Checkout/CheckoutService.php --lines 200

# Preview around a specific line
bash scripts/ai/preview-file.sh app/Services/Checkout/CheckoutService.php --around 120 --context 30

# Exact range
bash scripts/ai/preview-file.sh app/Services/Checkout/CheckoutService.php --range 100:160

# JSON envelope for pipelines
AI_OUTPUT=json bash scripts/ai/preview-file.sh app/Services/Checkout/CheckoutService.php --lines 50
```

## Good

```bash
bat -n app/Services/Checkout/CheckoutService.php
bat --line-range 100:160 app/Services/Checkout/CheckoutService.php
tail -100 storage/logs/laravel.log
```

## Bad

```bash
cat app/Services/Checkout/CheckoutService.php
cat storage/logs/laravel.log
tail -f storage/logs/*.log
```

Why bad:

- huge output hides relevant evidence
- no line numbers
- harder for agents to cite/reason
