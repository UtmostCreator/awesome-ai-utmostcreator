# Remaining Work TODO — Agent Permission / Script Gateway

This replaces the stale root-level scratch TODO files. Keep follow-up work under this ticket so
future changes stay scoped and reviewable.

## Ongoing constraints (not tracked as completable TODOs)

These are evergreen guardrails/process guidance, not completable tasks. Reconciled
2026-07-05 (previously listed as dangling unchecked P0/P3 boxes).

- **Merge hygiene (was P0):** keep P2b scoped when merging (only audited gateway permission,
  registry projection, agent-profile, tests, generated registry docs, ticket docs); do not
  resurrect root scratch notes (`todo-scripts-migration.md`, `todo-script-regestry-ref.md`,
  `agent-permission-rethink.md`, `ai-search-todo.md`); restart OpenCode after merge if config
  changes were used in a running session so permission rules reload.
- **Process guidance (was P3):** move any future broad README/security/support ideas into their
  own scoped ticket before implementation; keep root-level scratch TODO markdown out of the
  repository (use `docs/tickets/**` for durable plans); re-run docs/install validators after
  generated registry or adapter-surface changes.

## P1 — Phase 3 registry projection

- [x] Finish / review `plan-phase3-registry-projection.md` as the durable plan for registry
      projection work. DONE — `archive/plan-phase3-registry-projection.md` exists with a DONE marker.
- [x] Complete the registry export/check flow so `docs/ai/script-registry.json` is generated from
      the PHP registry instead of hand-synced. DONE — proven by `tests/php/RegistryProjectionTest.php`.
- [x] Keep schema tightening tied to generated output, not manual JSON enrichment. DONE — proven
      by `tests/php/RegistryProjectionTest.php`.
- [x] Add or keep parity tests proving PHP registry entries and generated JSON cannot drift. DONE —
      `tests/php/RegistryProjectionTest.php`.

## P2 — Permission drift hardening

- [ ] SUPERSEDED — see `docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/plan.md`.
      Decision 5 (generate compact inline frontmatter at install time) was formally un-deferred by
      `docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/` at user request on
      2026-07-05. The phase-2 P2c registry ↔ permission drift test is absorbed into that ticket's
      slice 6; keep future work cross-linked there instead of duplicating it here.
- [ ] SUPERSEDED — see `docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/plan.md`.
      Add a registry ↔ OpenCode permission drift test after P2b, so `tool:list*`,
      `tool:describe*`, and `tool:run *` cannot silently disappear from template/rendered config.
      (Absorbed as `PermissionComposeTest::testEveryComposedScriptReferenceIsRegistered()` and
      `AgentPermissionDriftTest.php`.)
- [ ] Keep `bash "*"` at `ask` until a separate release-reviewed slice proves all agent flows have
      migrated to native reads and gateway commands.
- [ ] Do not weaken destructive, mutating, secret, or external-directory guardrails.

## Release audit outcome (2026-06-13, commits f5d0255..e30cec1)

Release-auditor verdict: **SHIP-WITH-CONDITIONS** (risk: medium-high — permission-floor change).
Code quality high; fail-closed gateway logic verified live; template/rendered parity holds;
registry projection breaks no consumer. Blockers are posture/process, not defects.

Ran clean (exit 0 unless noted): `registry:export --check` (0, in sync); `tool:run ai-edit`
(2, blocked/approval_required); `tool:run nope-not-a-tool` (1, unknown_id);
`validate-ai-config.php` (0); `validate-install-surface.php` (0);
`phpunit --filter 'ToolGatewayTest|RegistryProjectionTest'` (25 tests, 424 assertions, OK).

### Blocking before merge — RESOLVED by Phase 4 (see plan-phase4-apply-gate-hardening.md)

- [x] **`--apply` no-prompt mutation lane closed (Fix B).** The gateway now refuses to execute
      an approval-required tool via `--apply` unless a human sets `AI_GATEWAY_ALLOW_APPLY=1`
      (`install_extras.php` Fix B block). Agent bash contexts do not set it, so the no-prompt lane
      is closed regardless of OpenCode tokenizer behavior. The approver no longer has to *accept*
      the risk — it is engineered out. Returns `mutating_requires_apply` (exit 2).
- [x] **Defense-in-depth prompt (Fix A).** Added `tool:run * --apply*: ask` after the allow in
      both config files (last-match-wins). Drift test enforces it.
- [x] **OQ-1 live confirm (now NON-blocking).** Optionally verify in a real OpenCode session that
      the Fix A `* --apply*` ask rule fires under the live tokenizer. No longer a merge blocker
      because Fix B is the load-bearing guard. DONE — `runbook-p2b-gateway-wiring.md` documents
      the live confirmation and a recorded release-auditor conditional sign-off (2026-06-13).

### Done (non-blocking follow-up)

- [x] **CI drift gate wired.** Added `php tools/ai/ai.php registry:export --check` to
      `.github/workflows/validate-ai-surface.yml` so the generated `script-registry.json` cannot
      silently drift from the canonical PHP registry. actionlint clean; check passes locally.

### Recommended (not yet done, optional)

- [ ] Strengthen observability: ensure `post-tool-use.sh` captures every `tool:run --apply`
      into the append-only `.ai-logs/` root (current `docs/ai/generated/tool-run.json` is
      last-write-wins and gitignored), so mutation invocations are reconstructable.

## Verification to run before the next merge

```bash
php tools/ai/validate-ai-config.php
php tools/ai/validate-install-surface.php --strict
php tools/ai/ai.php registry:export --check
bash scripts/ai/ai-doc-check.sh --check
composer test
```
