# Architecture Plan — Extensibility On-Ramp Docs and Contract Sections

- Ticket: none
- Source: user request for a contributor-first extensibility on-ramp, grounded via 3 read-only
  researchers → repository-reviewer `CONFIRM-WITH-ADJUSTMENTS` verdict (every cited claim verified
  true) → architect design (`scratchpad/architect-design-spec.md`, six resolutions + 9 slices +
  4-plan split)
- Generated: 2026-07-09
- Plan file: docs/tickets/IDEAS/plan-extensibility-onramp-docs.md
- Type: documentation slice (S9) — fourth and final of a deliberately-split 4-plan set. Documents the
  work delivered by S1–S8 (the other three plans) and reconciles two companion wiring plans. **Depends
  on S1–S8** for accurate content, though the contract-section skeletons can be drafted in parallel.
  Companion plans: `plan-primitive-quickwins-globify-gotcha-renderall.md` (S1–S3),
  `plan-agent-docs-generation-and-composed-migration.md` (S4–S5),
  `plan-add-primitive-pipeline-and-fleet.md` (S6–S8), plus the two provider-wiring companions this plan
  reconciles: `plan-provider-wiring-reconciliation.md` (DONE) and
  `plan-provider-content-parity-validator.md`.
- Risk: low (documentation-only; extends existing canonical docs, adds one on-ramp doc). No code,
  template, byte-parity gate, or composed permission model is touched.

## Context

The other three plans deliver the extensibility machinery: glob-ified skills/capabilities + a gotcha
primitive + `render-all.php` (S1–S3), `agents.md`/`AGENTS-MANIFEST.md` generation + `lifecycle`/`risk`
frontmatter + composed-model migration (S4–S5), and the durable add-a-primitive pipeline — discriminated
spec, `scaffold-primitive.php`, reachability verifier, and the generalized fleet/critic Workflow
(S6–S8). None of that is discoverable to a contributor until the canonical docs describe "to add
primitive X, do A → B → C" and the adapter/contract docs record the new artifacts and boundaries. Two
companion plans already touch this space: `plan-provider-wiring-reconciliation.md` (DONE) added a
Primitive Wiring Matrix to `integration-matrix.md`, and `plan-provider-content-parity-validator.md`
designs a durable CI parity gate — both must be reconciled, not duplicated or forked.

## Problem

1. **No contributor on-ramp doc.** There is no single "how to add a command / workflow / skill /
   capability / agent / gotcha" walkthrough, so the S1–S8 machinery is undiscoverable.
2. **Contract docs do not yet record the new surfaces and boundaries.** `adapter-contract.md`,
   `integration-matrix.md`, `handoff-contract.md`, `generated-artifacts.md`, and `source-of-truth.md`
   predate the new gotcha prefix, the `.opencode` third render route, the `lifecycle`/`risk` frontmatter
   SoT, the generated `agents.md`/`AGENTS-MANIFEST.md`, and the add-primitive agent sequence.
3. **Two companion plans risk duplication/fork if not reconciled.** S4's `lifecycle`/`risk` fields
   EXTEND the DONE Primitive Wiring Matrix rather than duplicate it, and S7's reachability verifier is
   the same family as the content-parity CI gate — it must be designed as a PEER, not a fork.

## Target Outcome

- One on-ramp doc that walks a contributor through adding each of the six primitive types (A → B → C
  per type), naming the scaffold path, the load path, and the `--check` gate that proves it.
- Contract sections added/extended in `adapter-contract.md`, `integration-matrix.md`,
  `handoff-contract.md`, `generated-artifacts.md`, and `source-of-truth.md` recording the new artifacts,
  routes, and boundaries from S1–S8.
- Explicit reconciliation of the two companion plans: the DONE Primitive Wiring Matrix is EXTENDED (not
  duplicated) by S4's `lifecycle`/`risk` fields, and the content-parity CI gate and S7's reachability
  verifier are documented as PEERS in one family, not competing mechanisms.

## In Scope

- **S9 — on-ramp doc + contract sections (Low, documents S1–S8).**
  - **On-ramp doc**: a new "to add primitive X, do A → B → C" walkthrough covering all six primitive
    types (command, workflow, skill, capability, agent, gotcha), each naming its scaffold invocation
    (`scaffold-primitive.php --type ...`), its load path, and the `--check` gate that proves reachability.
  - **`adapter-contract.md`**: add a "Primitive Wiring & Scaffold" section plus a `.opencode`
    third-route note (record that `render-all.php`/`render-adapters.php` now owns the `.opencode/agents`
    destination alongside the installer route).
  - **`integration-matrix.md`**: EXTEND the existing Primitive Wiring Matrix (added by the DONE
    `plan-provider-wiring-reconciliation.md`) with the `lifecycle`/`risk` fields and the
    generated-artifact status of `agents.md` / `AGENTS-MANIFEST.md`. Do NOT create a second matrix.
  - **`handoff-contract.md`**: document the `add-primitive` agent sequence (intake → spec → static →
    semantic → wire → reachability → critic) with the prose-only handoff note (no structured field).
  - **`generated-artifacts.md`**: record the newly generated artifacts — `agents.md`,
    `AGENTS-MANIFEST.md`, and `templates/gotchas/` entries.
  - **`source-of-truth.md`**: record that the `lifecycle`/`risk` frontmatter is the SoT for the doc
    tables.
  - **Companion-plan reconciliation** (must be explicit in the docs):
    - `plan-provider-wiring-reconciliation.md` (DONE): its Primitive Wiring Matrix is EXTENDED by S4's
      `lifecycle`/`risk` fields, NOT duplicated. The on-ramp/contract docs reference that matrix and add
      columns to it rather than starting a parallel table.
    - `plan-provider-content-parity-validator.md`: its CI parity gate is the SAME FAMILY as S7's
      `validate-primitive-reachability.php`. Document reachability as a PEER of the content-parity gate
      (both verify a primitive's output is present/correct on each provider), NOT a fork — one family,
      two complementary checks.

## Out Of Scope (Things To Avoid)

- **No `packages/**` edits** (NAC-1). This is a docs slice; any `packages/**`-adjacent write is deferred
  and gated.
- **Do not duplicate the DONE Primitive Wiring Matrix** — EXTEND it (per the `>=75%` reuse rule and this
  repo's explicit rejection precedent for competing taxonomies). No second matrix, no second registry.
- **Do not design reachability as a fork of the content-parity gate** (NAC-2/NAC-3) — document it as a
  PEER in one family; do not describe a second parallel parity mechanism.
- **No structured Claude handoff field** (NAC-4) — the `handoff-contract.md` addition documents the
  add-primitive sequence as prose-only for Claude.
- **No installed-kit authoring surfaces** (NAC-5) — the on-ramp doc is contributor-facing (extending
  canonical source) only.
- **Do not modify `architect.md` or `architecture-plan-writer.md`** (NAC-6).
- Do NOT implement, re-design, or re-scope S1–S8 machinery here — this plan only documents what those
  plans deliver. Do NOT restructure the existing tables in the contract docs beyond the minimal
  additions named above.

## Affected Paths

- NEW on-ramp doc (exact location TBD at implementation time — likely `docs/ai/` alongside the other
  contract docs; recommend a name like `docs/ai/extensibility-on-ramp.md`).
- `docs/ai/adapter-contract.md` (add "Primitive Wiring & Scaffold" section + `.opencode` third-route
  note).
- `docs/ai/integration-matrix.md` (EXTEND the existing Primitive Wiring Matrix with `lifecycle`/`risk`
  fields + generated-artifact status of `agents.md`/`AGENTS-MANIFEST.md`).
- `docs/ai/handoff-contract.md` (add the add-primitive agent sequence + prose-only note).
- `docs/ai/generated-artifacts.md` (record `agents.md`, `AGENTS-MANIFEST.md`, `templates/gotchas/`
  entries).
- `docs/ai/source-of-truth.md` (record `lifecycle`/`risk` frontmatter as SoT for the doc tables).
- Read/reference only: `docs/tickets/IDEAS/plan-provider-wiring-reconciliation.md` (DONE),
  `docs/tickets/IDEAS/plan-provider-content-parity-validator.md`, and the three companion plans in this
  set.

## Contracts And Boundaries

- **Documents S1–S8**: content accuracy depends on the machinery those plans deliver. The on-ramp doc
  and generated-artifact records should be finalized after S1–S8 land; contract-section skeletons may be
  drafted in parallel and filled in as each slice lands.
- **Extend, do not duplicate**: `integration-matrix.md`'s Primitive Wiring Matrix is owned by the DONE
  `plan-provider-wiring-reconciliation.md`; S9 adds columns/fields to it, never a second matrix. Reuse
  the file's existing table/heading conventions (Runtime Surface Matrix, Critical-Topic Coverage Matrix),
  per the `>=75%` reuse rule.
- **Reachability as a PEER of content-parity**: `plan-provider-content-parity-validator.md`'s CI gate and
  S7's `validate-primitive-reachability.php` are one family — both confirm a primitive's output is
  present/correct per provider. Document them as complementary peers; do not describe a fork or a second
  competing gate.
- **Prose handoffs**: the `handoff-contract.md` addition records the add-primitive sequence as prose-only
  for Claude (NAC-4 / AC-13); no structured handoff field.
- Docs-only boundary: no code, template, permission-layer, or byte-parity gate is edited by this plan.

## Todo Plan

Priority-grouped, unchecked.

- [ ] P0: S9 — Write the on-ramp doc: "to add primitive X, do A → B → C" for all six primitive types,
      each naming its `scaffold-primitive.php --type` invocation, load path, and reachability `--check`
      gate.
- [ ] P1: S9 — Add a "Primitive Wiring & Scaffold" section + `.opencode` third-route note to
      `docs/ai/adapter-contract.md`.
- [ ] P1: S9 — EXTEND the existing Primitive Wiring Matrix in `docs/ai/integration-matrix.md` with
      `lifecycle`/`risk` fields and the generated-artifact status of `agents.md`/`AGENTS-MANIFEST.md`
      (extend the DONE `plan-provider-wiring-reconciliation.md` matrix — do NOT create a second table).
- [ ] P1: S9 — Add the add-primitive agent sequence (intake → spec → static → semantic → wire →
      reachability → critic) + prose-only handoff note to `docs/ai/handoff-contract.md`.
- [ ] P1: S9 — Record `agents.md`, `AGENTS-MANIFEST.md`, and `templates/gotchas/` entries in
      `docs/ai/generated-artifacts.md`.
- [ ] P1: S9 — Record `lifecycle`/`risk` frontmatter as SoT for the doc tables in
      `docs/ai/source-of-truth.md`.
- [ ] P2: S9 — Reconcile companion plans in the docs: state that S4's `lifecycle`/`risk` fields EXTEND
      (not duplicate) the DONE Primitive Wiring Matrix, and that S7's reachability verifier is a PEER
      (not a fork) of `plan-provider-content-parity-validator.md`'s CI parity gate.

## Acceptance Criteria

Each AC names its Type / what it Proves / how it is Verified.

- [ ] AC-01 (S9, explicit): Type=coverage. Proves the on-ramp doc covers all six primitive types.
      Verified by the doc containing an A → B → C walkthrough for each of command, workflow, skill,
      capability, agent, gotcha, each naming its scaffold path, load path, and `--check` gate.
- [ ] AC-02 (S9, explicit — AC-9 reconcile clause): Type=reconciliation. Proves the two companion plans
      are reconciled, not duplicated. Verified by the docs explicitly stating (a) `lifecycle`/`risk`
      EXTEND the DONE Primitive Wiring Matrix and (b) reachability is a PEER of the content-parity gate,
      with references to both companion plans by filename.
- [ ] AC-03 (S9, extend-not-duplicate): Type=no-second-matrix guard. Proves `integration-matrix.md`
      gained fields on the existing matrix, not a new competing table. Verified by a diff review showing
      the Primitive Wiring Matrix was extended in place (no new parallel matrix section).
- [ ] AC-04 (S9, generated-artifact record): Type=artifact registration. Proves the new generated
      artifacts are recorded. Verified by `generated-artifacts.md` listing `agents.md`,
      `AGENTS-MANIFEST.md`, and `templates/gotchas/` entries.
- [ ] AC-05 (S9, SoT record): Type=SoT registration. Proves the frontmatter SoT is documented. Verified
      by `source-of-truth.md` naming `lifecycle`/`risk` as the SoT for the doc tables.
- [ ] AC-06 (S9, explicit — AC-13): Type=prose-handoff record. Proves the add-primitive sequence is
      documented as prose-only for Claude. Verified by the `handoff-contract.md` addition describing the
      sequence with a prose-only note and no structured handoff field.
- [ ] AC-07 (negative — NAC-1/NAC-6): Type=scope guard. Proves this docs slice touches no code/template
      and no `architect.md`/`architecture-plan-writer.md`. Verified by the diff showing only the named
      `docs/ai/` files (plus the new on-ramp doc) changed.

## Verification Plan

Each step names the exact gate or inspection surface that proves an AC.

- Manual review of the on-ramp doc against the six primitive types — proves AC-01 (every type has a
  walkthrough with scaffold path + load path + `--check` gate).
- Diff review of `docs/ai/integration-matrix.md` — proves AC-03 (matrix extended in place, no second
  table) and part of AC-02.
- Diff review of `docs/ai/adapter-contract.md`, `handoff-contract.md`, `generated-artifacts.md`,
  `source-of-truth.md` — proves AC-04, AC-05, AC-06 and the reconciliation clauses of AC-02.
- `php tools/ai/validate-adapter-drift.php` — confirms the doc edits introduce no drift warning against
  the contract docs' own reference rules.
- `php tools/ai/validate-ai-config.php --check` — confirms the doc additions do not break config
  validation.
- Diff review confirming only `docs/ai/` files changed — proves AC-07 (no code/template/agent-file
  edits).

## Risks And Rollback

- **Low**: documentation-only; the only writes are new/extended `docs/ai/` files.
- **Content-accuracy dependency**: the on-ramp doc and generated-artifact records depend on S1–S8 having
  landed; drafting them before the machinery lands risks documenting a moving target. Mitigated by
  finalizing content after each slice lands (skeletons in parallel, fill on landing).
- **Duplication risk**: extending vs. duplicating the DONE Primitive Wiring Matrix is the main content
  risk; mitigated by the extend-not-duplicate boundary and AC-03.
- **Rollback**: revert the `docs/ai/` section additions and remove the new on-ramp doc; no runtime,
  code, or CI impact.

## Handoff Notes

- **Recommended next step**: `docs means docs agent handoff` to land the on-ramp doc and the contract-doc
  sections once S1–S8 have landed (or to draft the contract-section skeletons in parallel).
- This plan documents S1–S8; finalize its content after those slices land for accuracy.
- It must be read alongside the two companion wiring plans: `plan-provider-wiring-reconciliation.md`
  (DONE — its Primitive Wiring Matrix is EXTENDED here, not duplicated) and
  `plan-provider-content-parity-validator.md` (its CI parity gate is a PEER of S7's reachability
  verifier, not a fork).
- Claude handoffs stay prose per `docs/ai/handoff-contract.md`; the add-primitive sequence is documented
  as prose-only, with no structured handoff field (NAC-4 / AC-13).
