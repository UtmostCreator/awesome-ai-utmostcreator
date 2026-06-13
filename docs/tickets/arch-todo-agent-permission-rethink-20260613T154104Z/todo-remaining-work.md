# Remaining Work TODO — Agent Permission / Script Gateway

This replaces the stale root-level scratch TODO files. Keep follow-up work under this ticket so
future changes stay scoped and reviewable.

## P0 — Commit-ready follow-through

- [ ] Keep P2b scoped when merging: include only audited gateway permission, registry projection,
      agent-profile, tests, generated registry docs, and ticket documentation changes.
- [ ] Do not resurrect root scratch notes such as `todo-scripts-migration.md`,
      `todo-script-regestry-ref.md`, `agent-permission-rethink.md`, or `ai-search-todo.md`.
- [ ] If OpenCode config changes are used in a running session, restart OpenCode after merge so
      permission rules are reloaded.

## P1 — Phase 3 registry projection

- [ ] Finish / review `plan-phase3-registry-projection.md` as the durable plan for registry
      projection work.
- [ ] Complete the registry export/check flow so `docs/ai/script-registry.json` is generated from
      the PHP registry instead of hand-synced.
- [ ] Keep schema tightening tied to generated output, not manual JSON enrichment.
- [ ] Add or keep parity tests proving PHP registry entries and generated JSON cannot drift.

## P2 — Permission drift hardening

- [ ] Add a registry ↔ OpenCode permission drift test after P2b, so `tool:list*`,
      `tool:describe*`, and `tool:run *` cannot silently disappear from template/rendered config.
- [ ] Keep `bash "*"` at `ask` until a separate release-reviewed slice proves all agent flows have
      migrated to native reads and gateway commands.
- [ ] Do not weaken destructive, mutating, secret, or external-directory guardrails.

## P3 — Documentation cleanup

- [ ] Move any future broad README/security/support ideas into their own scoped ticket before
      implementation.
- [ ] Keep root-level scratch TODO markdown out of the repository; use `docs/tickets/**` for
      durable plans.
- [ ] Re-run docs/install validators after generated registry or adapter-surface changes.

## Verification to run before the next merge

```bash
php tools/ai/validate-ai-config.php
php tools/ai/validate-install-surface.php --strict
bash scripts/ai/ai-doc-check.sh --check
composer test
```
