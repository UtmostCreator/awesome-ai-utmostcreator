# Validation

Run `php tools/ai/validate-install-surface.php --strict`, `php tools/ai/validate-ai-config.php`, and focused script/PHP checks after installer changes.

## Validator Set

All validators live under `tools/ai/` and exit non-zero on failure:

- `validate-install-surface.php` — install pack, profile, script, and adapter template/script-reference contracts (`--strict`).
- `validate-ai-config.php` — AI config integrity.
- `validate-ai-catalog.php` — catalog contents.
- `validate-catalog-drift.php` — committed `INSTALL-CATALOG.md` matches a fresh render of the pack registry.
- `validate-adapter-drift.php` — adapter surfaces carry required canonical references, are not oversized, and avoid non-agnostic literals (`--fail-on-warn`, `--changed-only`). See `docs/ai/adapter-contract.md`.
- `validate-generated-artifacts.php` — generated artifact shape and schema.
- `validate-schemas.php` — JSON Schema contracts (see `docs/ai/schema-ownership.md`).
- `validate-command-policy.php` — compiled command-policy parity.
- `validate-placeholders.php` — unresolved placeholder detection.
- `validate-stub-surfaces.php`, `validate-mentor-parity.php`, `validate-context-budgets.php`, `validate-agent-spec.php` — focused surface checks.

## Drift Terminology

- "Adapter drift" = adapter-vs-canonical/template consistency (`validate-adapter-drift.php`).
- "Advisor scorecard drift" = advisor score deltas vs. a baseline (`docs/ai/generated/advisor-drift.md`). These are unrelated.
