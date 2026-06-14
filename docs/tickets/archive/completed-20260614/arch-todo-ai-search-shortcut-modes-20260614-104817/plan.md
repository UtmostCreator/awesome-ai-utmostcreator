# Architecture Plan — ai-search Shortcut Modes (DEFERRED)

- Ticket: none
- Source: decomposition of `docs/tickets/arch-todo-repo-cleanup-shipping-surface-20260614-101701/plan.md`, with binding user decisions
- Generated: 20260614-104817
- Plan folder: docs/tickets/arch-todo-ai-search-shortcut-modes-20260614-104817/
- Status: **DEFERRED** — Todo (unchecked); backlog plan, written now but not scheduled for the current pass
- Decomposition role: backlog plan (Plan E)
- Rank: item 100
- Dependency: **Plan A committed**
- Review depth: standard

## Context

`tests/scripts/ai/test-ai-search.sh` already contains RED tests for new ai-search shortcut modes — `function`, `method`, `interface`, `enum`, `route`, `config-key`. These are additive, read-only convenience modes layered over the existing `struct`/`text`/`tracked` backends. The work is well-understood but deferred behind the baseline + the higher-priority A–D program.

## Problem

ai-search has no first-class shortcuts for common symbol/structure lookups (function/method/interface/enum/route/config-key), forcing callers to hand-write structural or text patterns. RED tests assert the desired behavior; the modes are not yet implemented.

## Target Outcome

ai-search exposes the new read-only shortcut modes, the previously RED tests in `tests/scripts/ai/test-ai-search.sh` pass, and existing modes/contracts remain unchanged.

## In Scope

- 100: Add ai-search shortcut modes `function`, `method`, `interface`, `enum`, `route`, `config-key` (additive, read-only) so the existing RED tests pass.

## Out Of Scope (Things To Avoid)

- External-root / `--allow-outside-root` access (Plan F, item 105).
- Changing existing mode behavior, output contract, or default exclusions.
- Any write/mutation capability (these modes are read-only).
- Widening to modes not covered by the existing RED tests.

## Affected Paths

- `scripts/ai/internal/search/**` (the relocated ai-search modules; e.g. the `25-modes.sh` mode registry and the relevant backend modules).
- `scripts/ai/ai-search.sh` facade if mode dispatch/help needs updating.
- `tests/scripts/ai/test-ai-search.sh` (already RED — turned green).
- `docs/ai/tools/ai-search.md` mode list (kept in sync).

## Contracts And Boundaries

- New modes are additive and read-only; they must not alter existing modes or the JSON envelope contract (`schema`, `status`, `mode`, `matches`, `results`, `warnings`, `errors`, `limits`, `meta`).
- The RED tests in `tests/scripts/ai/test-ai-search.sh` define the acceptance contract.
- Default exclusions (`vendor`, `node_modules`, `dist`, `build`, `coverage`, `.git`) still apply.
- Depends on Plan A so the modes live in the relocated `internal/search/` tree.

## Todo Plan

- [ ] 100a: Confirm the RED tests in `tests/scripts/ai/test-ai-search.sh` and enumerate the exact expected mode names and outputs.
- [ ] 100b: Implement the shortcut modes (`function`, `method`, `interface`, `enum`, `route`, `config-key`) over existing backends in `internal/search/`.
- [ ] 100c: Wire the new modes into mode dispatch and `--introspect`/help output.
- [ ] 100d: Update `docs/ai/tools/ai-search.md` mode list.

## Acceptance Criteria

- [ ] AC-01: The previously RED tests in `tests/scripts/ai/test-ai-search.sh` pass.
- [ ] AC-02: Each new mode is read-only and appears in `--introspect`/help output.
- [ ] AC-03: Existing modes and the JSON envelope contract are unchanged (no regression).
- [ ] AC-04: `docs/ai/tools/ai-search.md` lists the new modes; `composer test:fast` passes.

## Verification Plan

- `bash tests/scripts/ai/test-ai-search.sh` — proves the RED tests now pass (AC-01).
- `bash scripts/ai/ai-search.sh --introspect` (or `AI_OUTPUT=json ...`) — confirms new modes are registered (AC-02).
- `AI_OUTPUT=json bash scripts/ai/ai-search.sh function <symbol> . --fixed` (and one per new mode) — confirms read-only behavior and envelope shape (AC-02, AC-03).
- `composer test:fast` — regression smoke (AC-04).

## Risks And Rollback

- Risk: a new mode unintentionally changes shared dispatch and breaks existing modes. Mitigation: additive registry entries; run full ai-search test suite + `composer test:fast`.
- Risk: doc mode list drifts from implementation. Mitigation: update `ai-search.md` in the same change; doc-check.
- Rollback: revert the additive commit; modes are isolated registry additions.

## Handoff Notes

- DEFERRED: schedule after Plan A and the A–D program; standard review depth.
- The RED tests are the contract — implement to them, do not rewrite them to pass.
- Keep modes read-only and additive; no envelope or existing-mode changes.
- implementer means implementer agent handoff using OpenCode command: /implement
