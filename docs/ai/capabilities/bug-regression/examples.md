# Bug Regression Examples

## Example: Laravel Checkout Bug

- Reproduction: add a feature test for applying the same coupon twice through cart recalculation
- Root cause: pricing service applies discount on hydrated totals instead of normalized subtotal
- Fix: guard duplicate application in pricing service
- Verification: focused feature test, then targeted related pricing tests

## Example: Monorepo API Regression

- Reproduction: contract test for missing field in API response
- Root cause: serializer omitted field after schema rename
- Fix: restore backward-compatible field mapping
- Verification: API package test first, then frontend consumer test if the client relies on the old field
