# Review Diff Examples

## Example Verdict

PASS WITH NOTES

- Severity: major
- Location: `app/Services/PricingService.php` coupon application path
- Category: correctness
- Issue: coupon amount can still be applied twice if the same cart is recalculated after hydration
- Fix direction: guard on applied-discount state or normalize recalculation entrypoint
