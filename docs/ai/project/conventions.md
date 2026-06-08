# awesome-ai-utmostcreator — Conventions

User-owned. Durable conventions for this repository. The kit installs this once and never
overwrites it. Keep entries concrete so the AI can follow and verify them.

## Code

- Language/runtime: PHP (tools and validators) plus Bash scripts under `scripts/ai/`.
- File placement: AI workflow source lives under `packages/ai-universal-rules/templates/`;
  installed surfaces live under `docs/ai/`, `.github/`, and `.opencode/`. PHP tooling lives
  under `tools/ai/`; shell wrappers live under `scripts/ai/`.
- Naming: keep canonical procedure in `docs/ai/capabilities/`; keep runtime adapters thin.

## Testing

- Test command: `composer test` (serial) or `composer test:fast` (paratest, 12 workers).
- Test location & style: PHPUnit tests in `tests/php/`; shell harnesses in `tests/scripts/ai/`.
- Fixture policy: keep tests deterministic; do not weaken assertions to make changes pass.

## Review

- Review priorities: correctness, regressions, configuration drift, adapter/template parity.
- Definition of done: focused tests green, references intact, docs updated, no orphaned files.

## Protected / generated

- Do not edit by hand: anything under `docs/ai/generated/`, files marked
  `GENERATED — DO NOT EDIT` or `Managed by ai-kit`, and generated catalog/manifest artifacts.
- Approval-gated: secrets, destructive changes, dependency installs, deployments, and
  generated-artifact rewrites (see `docs/ai/approval-boundaries.md`).
