# Good / Bad: Testing

## Best (repository wrapper)

```bash
# Run narrowest verification first, then escalate
bash scripts/ai/ai-verify.sh .

# Select focused tests for changed code
bash scripts/ai/ai-test-select.sh changed

# Select tests for a specific file
bash scripts/ai/ai-test-select.sh file app/Services/Checkout/CheckoutService.php
```

## Good

```bash
php artisan test --filter=CheckoutServiceTest
php artisan test tests/Feature/CheckoutServiceTest.php
php artisan test 2>&1 | tail -80
```

## Bad

```bash
php artisan test
php artisan test
php artisan test
```

before isolating the failing test.

Why bad:

- slow
- repetitive
- less diagnostic
