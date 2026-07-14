# Architecture Plan — Cross-Provider Content-Parity Validator (Agents/Commands/Skills/Workflows)

- Ticket: none
- Source: user request for "separate plan to create validation to ensure all providers receive the
  same agents content etc but with adapter for their own format"; grounded against
  `docs/ai/adapter-contract.md` ("Two Different 'Drift' Concepts" + known content-parity
  limitation), `tools/ai/validate-adapter-drift.php`, `tools/ai/generate-agent-permissions.php`,
  and `docs/tickets/claude-agent-fleet-remediation/plan-28-permission-sot-and-render-parity-sync.md`
- Generated: 2026-07-09
- Plan file: docs/tickets/IDEAS/plan-provider-content-parity-validator.md
- Type: new CI validator (durable, ongoing gate) — companion to
  `plan-provider-wiring-reconciliation.md` (one-time manual audit). **Deliberately split**: that
  plan finds today's gaps by hand; this plan builds the machine that keeps them from recurring.
- Risk: medium (a new CI gate that can fail builds; must not introduce false positives against the
  intentional asymmetries `integration-matrix.md` already documents)

## Context

`docs/ai/adapter-contract.md` already states the exact known gap this plan closes: "Adapter drift
... does not yet do full content parity against template sources; treat that as a known
limitation, not a guarantee." `tools/ai/validate-adapter-drift.php` currently checks for required
canonical references, oversize bodies, and non-agnostic literals — not semantic/byte content
parity. `plan-28` (in progress, not superseded by this plan) is independently building a byte-parity
gate, but scoped specifically to agent **bash/edit permission bodies** for `.claude/agents` +
`.github/agents` vs. canonical templates (its own Phase 1 `render-adapters.php --check`). It does
not cover: (a) OpenCode agents in the same byte-parity gate (though `generate-agent-permissions.php
--check` already covers OpenCode + templates per `plan-28`'s own Head B note — a *different*,
narrower existing check), (b) commands/skills/prompts derived from `templates/workflows` and
`templates/commands`, or (c) semantic (non-byte) parity for structurally-different surfaces like
Copilot instructions vs. OpenCode `AGENTS.md` vs. Claude `CLAUDE.md`.

## Problem

Three distinct parity problems exist today with three different correct solution shapes, and
conflating them risks either a validator that is too strict (false-fails on intentional runtime
differences) or too loose (misses real drift):

1. **Byte parity** (same content, mechanically rendered, must match exactly): agent bodies where a
   provider's renderer is a near-verbatim frontmatter transform of the same canonical source.
   `plan-28` already owns this for `.claude`/`.github` agents; this plan must extend the *same
   mechanism* (not a new one) to cover commands/skills/prompts rendered from `templates/workflows`
   and `templates/commands`, which currently have **no** re-render-and-compare gate at all.
2. **Semantic parity** (equivalent meaning, necessarily different structure): Copilot's `applyTo`-
   scoped instructions vs. OpenCode's always-on `AGENTS.md` vs. Claude's `CLAUDE.md` — these cannot
   be byte-compared (different files, different mechanisms) but should cover the same critical
   topics. `docs/ai/integration-matrix.md` already has a "Semantic-Parity Review Methodology"
   reference in `docs/ai/adapter-contract.md` ("compare topic-level coverage, not file structure")
   — this exists as *review guidance for humans*, not as an automated check.
3. **Intentional asymmetry** (must NOT be flagged as drift): Claude's coarser `settings.json`
   enforcement, no `task: ask` Claude equivalent, Claude receiving workflows as skills but not as
   `.claude/commands`, Copilot never receiving the 5 thin `templates/commands` files as prompts.
   A validator that does not know about these will produce persistent false positives that erode
   trust in the gate (the exact failure mode CI validators are supposed to prevent, not cause).

## Target Outcome

One additional, narrowly-scoped validator (or an extension of the existing
`tools/ai/render-adapters.php` `--check` mechanism `plan-28` is building, reused rather than
duplicated) that: re-renders every workflow/command template into its expected provider output and
byte-compares it (closing gap 1), and separately runs a topic-coverage check against
`docs/ai/integration-matrix.md`'s Critical-Topic Coverage Matrix table to confirm every `covered`
row still has evidence on every named runtime surface (closing gap 2, as an automatable subset of
the semantic-parity methodology — not full natural-language equivalence checking, which is out of
scope for a deterministic CI gate). Gap 3 is closed by an explicit, version-controlled exceptions
list sourced from `plan-provider-wiring-reconciliation.md`'s findings.

## In Scope

- **Byte-parity extension** (gap 1): extend `plan-28`'s `tools/ai/render-adapters.php --check`
  mechanism (once it lands — do not fork it) to also re-render every `templates/workflows/*.md` and
  `templates/commands/*.md` file into its expected `.opencode/commands`, `.github/prompts`,
  `{opencode,github,claude}/skills`, and `.claude/commands` outputs and byte-compare. If `plan-28`
  has not landed yet when this plan is implemented, this item is blocked on it (see Contracts And
  Boundaries) — do not build a second, parallel render-check CLI.
- **Topic-coverage check** (gap 2): a small script/test that parses
  `docs/ai/integration-matrix.md`'s "Critical-Topic Coverage Matrix" table and, for every row
  marked `covered`, confirms the named file/path in each of the Copilot/OpenCode/Claude columns
  still exists on disk (a forward file-existence check, matching the pattern already proven safe
  by `plan-29`'s `ArchitectureDiagramReferencesTest.php` — reuse that pattern, do not invent a new
  one). This does NOT attempt to verify the *prose content* matches in meaning (that stays a human
  review responsibility per the existing Semantic-Parity Review Methodology) — only that the cited
  evidence paths still exist, so the matrix cannot silently rot by referencing deleted files.
- **Exceptions list** (gap 3): a version-controlled list (e.g.
  `docs/ai/generated/provider-parity-exceptions.md` or a table inside
  `docs/ai/integration-matrix.md` itself, reusing its existing "Runtime limitation notes" pattern)
  enumerating every confirmed-intentional asymmetry from `plan-provider-wiring-reconciliation.md`'s
  findings, which the byte-parity check must skip rather than fail on.
- Wire the new check(s) into `.github/workflows/validate-ai-surface.yml` alongside `plan-28`'s
  `render-adapters.php --check`, once both exist.

## Out Of Scope (Things To Avoid)

- Building a second render/compare CLI parallel to `plan-28`'s `tools/ai/render-adapters.php` — one
  render-parity mechanism, extended, not two competing ones (locked, per this repo's own
  `>=75%` reuse rule and its explicit rejection precedent for competing taxonomies —
  `docs/tickets/MASTER-INDEX.md`'s "P6 REJECTED").
- Attempting full natural-language semantic-equivalence checking (e.g. "does this Copilot
  instruction actually mean the same thing as this OpenCode doc paragraph") — that is not
  deterministically checkable and stays a human/reviewer responsibility; this plan only
  automates the file-existence layer of that check.
- Re-scoping or re-designing `docs/ai/integration-matrix.md`'s existing tables — this plan reads
  and extends them minimally (one new exceptions section), it does not restructure them.
- Flagging any asymmetry already documented in `integration-matrix.md`'s "Runtime limitation notes"
  as drift — those are intentional by design and must be in the exceptions list from day one.
- Implementation before `plan-provider-wiring-reconciliation.md`'s findings exist (that plan's
  output is a required input to this plan's exceptions list — sequencing dependency, not a full
  blocking gate, since the byte-parity mechanism itself can still be designed in parallel).

## Affected Paths

- `tools/ai/render-adapters.php` (extend, once `plan-28` lands it — do not create a duplicate)
- NEW small script or PHPUnit test for the topic-coverage forward-existence check (exact location
  TBD at implementation time — likely `tests/php/IntegrationMatrixCoverageTest.php`, mirroring
  `ArchitectureDiagramReferencesTest.php`'s pattern)
- `docs/ai/integration-matrix.md` (new exceptions section, sourced from the wiring-reconciliation
  plan's findings)
- `.github/workflows/validate-ai-surface.yml` (wire the new check(s) into CI)

## Contracts And Boundaries

- **Sequencing dependency on `plan-28`**: the byte-parity extension (gap 1) reuses
  `tools/ai/render-adapters.php`, which `plan-28` is actively building (Phase 1). This plan's
  byte-parity item cannot land before `plan-28`'s Phase 1 lands; the topic-coverage check (gap 2)
  and the exceptions-list scaffold (gap 3) have no such dependency and can land independently/first.
- **Sequencing dependency on `plan-provider-wiring-reconciliation.md`**: the exceptions list must be
  sourced from that plan's findings table, not invented fresh — implement that plan first, or at
  minimum draft its findings before finalizing this plan's exceptions list.
- **No second registry**: reuse `docs/ai/script-registry.json`/`agent-script-access.md`'s existing
  registry pattern if any script-level registration is needed; do not create a parallel one (locked
  rule, reused from `plan-28`'s own explicit boundary).
- **False-positive discipline**: any new CI gate that fails on day one due to missing exceptions
  entries must be landed with `--check` in warn-only/report mode first, then flipped to
  failing once the exceptions list is confirmed complete (same two-step pattern `plan-28` itself
  uses: "first `--check` may fail on pre-existing drift; run `--write` ONCE to reconcile... so the
  gate goes green atomically").

## Todo Plan

- [ ] Confirm `plan-28`'s Phase 1 (`tools/ai/render-adapters.php --check`) status before starting
      the byte-parity extension; if not yet landed, start with gap 2 (topic-coverage check) and
      gap 3 (exceptions scaffold) instead.
- [ ] Draft the exceptions list from `plan-provider-wiring-reconciliation.md`'s findings (or a
      first-pass manual list if that plan has not landed yet, clearly marked provisional).
- [ ] Build the topic-coverage forward-existence check (gap 2), reusing the
      `ArchitectureDiagramReferencesTest.php` pattern.
- [ ] Extend `tools/ai/render-adapters.php --check` to cover workflow/command-derived surfaces
      (gap 1), once `plan-28` Phase 1 has landed.
- [ ] Wire both checks into `validate-ai-surface.yml` in warn-only mode first; confirm a clean run;
      then flip to failing.

## Acceptance Criteria

- AC-01: The topic-coverage check runs and confirms every `covered` row in
  `integration-matrix.md`'s Critical-Topic Coverage Matrix cites an existing file path on each
  named runtime.
- AC-02: The byte-parity extension (once unblocked by `plan-28`) confirms every workflow/command
  template's rendered output on every provider byte-matches expectations, or is explicitly listed
  in the exceptions section.
- AC-03: The exceptions list cites its source (`plan-provider-wiring-reconciliation.md`'s findings
  or this plan's own provisional list) for every entry — no unexplained exception.
- AC-04: CI (`validate-ai-surface.yml`) runs both checks; a clean run is demonstrated before any
  check is flipped from warn-only to failing.
- AC-05 (negative): No second render-compare CLI or second permission/tool registry is created.

## Verification Plan

- Run the new topic-coverage check against the current `integration-matrix.md` and confirm zero
  false positives (every cited path exists today).
- Run the byte-parity extension (once available) against the current rendered `.opencode`,
  `.github`, `.claude` surfaces and confirm it only flags entries not already in the exceptions
  list.
- `composer test:fast` to confirm no regression in the existing test suite.
- `php tools/ai/validate-adapter-drift.php` to confirm this plan's new checks do not conflict with
  the existing drift validator's scope.

## Risks And Rollback

- **Medium**: a new CI gate always risks false positives on first run; mitigated by the
  warn-only-then-flip sequencing already proven by `plan-28`'s own approach.
- **Medium (dependency risk)**: this plan is partially blocked on `plan-28` landing first for the
  byte-parity item; if `plan-28` stalls, only gaps 2/3 can proceed independently — documented above
  as an explicit todo-ordering note, not a hidden assumption.
- **Rollback**: disable the new CI job step(s); no runtime/production impact since this is a
  validation-only addition.

## Handoff Notes

- This plan and `plan-provider-wiring-reconciliation.md` must be read together: that plan supplies
  the facts (what is asymmetric and why), this plan supplies the mechanism (how to keep it that way
  on purpose and catch anything new).
- Recommended next step: `architect means architect agent handoff` to confirm the sequencing
  against `plan-28`'s actual landing timeline before implementation starts, then
  `architecture-plan-writer means architecture-plan-writer agent handoff` to refine/persist any
  adjustment, then `implementer means implementer agent handoff` for the unblocked gaps 2/3 first.
