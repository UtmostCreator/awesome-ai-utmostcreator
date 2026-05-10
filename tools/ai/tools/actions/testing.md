# Testing

Use focused tests first, then affected broader tests.

---

## Preferred Commands

PHP/Laravel:

```bash
php artisan test --filter=SpecificTest
php artisan test tests/Feature/SpecificTest.php
php artisan test 2>&1 | tail -80
```

JS/TS:

```bash
pnpm test -- --run
vitest --run
playwright test
```

Shell:

```bash
bats tests/*.bats
```

Find tests:

```bash
fd "Test\.php$" tests
rg -n "function test|it\(" tests
```

---

## Use When

- reproducing a bug
- verifying a fix
- narrowing failing scope
- checking regressions

---

## Avoid

Running full suite repeatedly before isolating the failure.

Example: [`../examples/good-bad-testing.md`](../examples/good-bad-testing.md)
