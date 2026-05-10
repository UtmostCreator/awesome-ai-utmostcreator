# Verify Change Examples

## Example Report

- Selected verification: focused feature test for checkout coupon flow
- Why: directly exercises the changed pricing behavior
- Commands run: `php artisan test --filter=CheckoutCouponTest`
- Result: passing
- Not run: full suite and production smoke flow

## Example Escalation

If a change touches shared API contracts and web rendering:

- run the narrow API test first
- then run the affected frontend package tests
- then run the broader build only if package boundaries or bundling changed
