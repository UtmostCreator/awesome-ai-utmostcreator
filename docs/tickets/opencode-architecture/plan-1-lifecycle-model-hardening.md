# Architecture Plan — Lifecycle Model Hardening (opencode prototype)

- Ticket: none
- Source: architect design handoff
- Generated: 2026-07-10
- Plan file: docs/tickets/opencode-architecture/plan-1-lifecycle-model-hardening.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-1-lifecycle-model-hardening.md` and move it into `archive/` under this branch folder (`docs/tickets/opencode-architecture/archive/DONE-plan-1-lifecycle-model-hardening.md`). See "Archive On Completion" in the architecture-plan-writer contract for the exact steps.

## Prominent Scope Caveat (READ FIRST)

- **Two distinct surfaces.** This plan *document* is written to the TRACKED
  `docs/tickets/` location. The plan's IMPLEMENTATION surface is entirely the
  UNTRACKED directory `___ARCHITECTURE_2.0/opencode_architecture/lifecycle-model/lifecycle/`.
  These are not the same place; do not conflate them.
- The implementation target lives at the UNTRACKED path
  `___ARCHITECTURE_2.0/opencode_architecture/lifecycle-model/lifecycle/`, outside
  the repo's declared active paths.
- Per AGENTS.md "Prototype Lane" it is an exploration lane: code may live here but
  must not be merged to production paths; promotion requires respecification as a
  normal bounded slice passing the standard workflow.
- This plan hardens the prototype **in place** and does **NOT** authorize promotion.
  All work stays inside the untracked directory.
- The repo's `composer test` does **NOT** cover this Python + Node toolchain.

## Context

Verified current state under `.../lifecycle/`:

- `model/opencode.lifecycle.yaml` — 264 lines, source of truth.
- `model/lifecycle.schema.json` — 112 lines, JSON Schema draft 2020-12,
  `additionalProperties: false` on root/meta/machine.
- `scripts/gen_mermaid.py` — 206 lines, validate + generate.
- `scripts/check_mermaid.mjs` — parses generated `.mmd` with Mermaid 11, reads
  `../generated`.
- `generated/*.mmd` — 10 build artifacts.
- `README.md`.

## Problem

The prototype lifecycle model has self-contained correctness gaps (combined-diagram
node ID collisions, unguarded duplicate YAML keys, an unimplemented documented exit
code, flat/unstructured upstream provenance) plus missing schema/semantic invariants.
Deeper enrichments (presentation/model separation, real statechart semantics,
generated-artifact freshness/CI gating) exist but carry higher risk and, in some cases,
depend on verifying real upstream `sst/opencode` behavior or would touch production
paths.

## Target Outcome

Harden the prototype in place across ordered phases: land P0/P1 self-contained
correctness, then P2 schema/semantic constraints, and stop before the deferred P3
enrichments. All edits remain inside the untracked `lifecycle/` directory. No
repo-tracked file is modified by P0–P2.

## In Scope

- P0/P1 self-contained correctness fixes inside `.../lifecycle/`:
  - P0-1 qualified IDs for the combined diagram (combined.mmd only).
  - P1-2 reject duplicate YAML mapping keys.
  - P1-3 implement documented exit code 2.
  - P1-4 structured upstream provenance + `schema_version` (YAML + schema coupled).
- P2 schema/semantic constraints inside `.../lifecycle/`:
  - P2-5 per-kind invariants (schema + `semantic_errors`).
  - P2-6 `minItems: 1` on collections.
- Verification via the Python + Node toolchain inside the untracked directory only.

## Out Of Scope (Things To Avoid)

1. Do **NOT** edit any repo-tracked file in P0–P2; the entire surface is the untracked
   directory (P3-9 CI wiring is deferred for this reason).
2. Do **NOT** promote the prototype to production or add production CI referencing it
   without separate respecification (AGENTS.md Prototype Lane).
3. Do **NOT** add a new YAML field without the paired schema property (meta line 10 and
   machine line 88 are `additionalProperties: false`).
4. Do **NOT** change `render_machine` or the 9 per-machine `.mmd` files in P0; the
   collision fix is combined-diagram-only.
5. Do **NOT** enforce global-unique node IDs; the fix is per-machine namespacing
   (`machine__node`).
6. Do **NOT** fix P0-1 node emission without also fixing cross-link resolution AND
   intra-subgraph edges — all three use the same prefix.
7. Do **NOT** fabricate a commit SHA, retry cap, or any statechart semantic — structure
   only; real values are unknown until verified.
8. Do **NOT** introduce CUE or rejected runtime-semantic encodings
   (capability→EXEC, MCP destinations, permission approval outcomes, plugin retry rules).
9. Do **NOT** map exit codes wrong — parse/IO → 2, schema/semantic → 1, ok → 0.
10. Do **NOT** bundle P3 into P0/P2; each phase is one bounded outcome.

## Affected Paths (all UNTRACKED, inside `.../lifecycle/`)

- `scripts/gen_mermaid.py` (P0-1, P1-2, P1-3, P2-5; P3 later)
- `model/opencode.lifecycle.yaml` (P1-4; P3 later)
- `model/lifecycle.schema.json` (P1-4, P2-5, P2-6; P3 later)
- `generated/combined.mmd` (regenerated by P0-1; other 9 stay byte-identical)
- P3-only (deferred, do not touch now): `views/mermaid.yaml` (new), `.github/` (BLOCKED)

## Contracts And Boundaries

- Exit-code contract: parse/IO failure → `2`; schema/semantic failure → `1`; success → `0`.
- Schema is closed: `additionalProperties: false` on root/meta/machine — every new YAML
  field needs a paired schema property in the same change.
- Namespacing contract: combined diagram uses per-machine prefix `{machine_id}__`;
  per-machine diagrams and their 9 `.mmd` outputs are unaffected.
- Prototype-lane boundary: no repo-tracked file may change in P0–P2; no production CI.
- Split convention: JSON Schema owns shape constraints; `semantic_errors` owns relational
  checks (P2-5 respects this existing split).

## Todo Plan

Grouped by priority. Land P0/P1 as one phase-slice, then P2. Stop before P3.

### P0/P1 — Self-contained correctness (LOW risk)

- [x] P0-1: Qualified IDs for combined diagram. In `scripts/gen_mermaid.py`
  `render_combined` (lines 138-161): emit subgraph nodes with prefix `{machine_id}__`
  (reuse the `node_line` prefix param, 85-98); stop stripping machine in cross-link
  resolution (152-159) — resolve `mid.nid` → `mid__nid`; intra-subgraph edges (149-150)
  use the same prefix. Per-machine namespacing, NOT global-unique-id enforcement.
  `render_machine` (117-135) UNCHANGED; the other 9 `.mmd` files stay byte-identical;
  only `combined.mmd` changes.
- [x] P1-2: Reject duplicate YAML mapping keys. In `gen_mermaid.py` load site (174):
  custom `SafeLoader` subclass overriding `construct_mapping` to raise `ConstructorError`
  on duplicate keys. Coupled with P1-3: the dup-key error routes to exit 2.
- [x] P1-3: Implement documented exit code 2. In `gen_mermaid.py` main I/O block
  (174-175), docstring line 9: wrap model read + `yaml.load` + schema read + `json.loads`
  in `try/except OSError/YAMLError/JSONDecodeError` → one-line stderr diagnostic +
  `return 2`. Exit 1 stays for schema/semantic failures (182-186). No docstring change
  needed.
- [x] P1-4: Structured upstream provenance + `schema_version`. In
  `model/opencode.lifecycle.yaml` meta (6-10) AND `model/lifecycle.schema.json` meta
  properties (12-17): replace flat `verified_against` (line 9) with a structured
  `upstream` object (`repo`, `ref`, `commit` full SHA, `verified_at` ISO date) + add
  `schema_version`. COUPLED: meta is `additionalProperties: false` (line 10) so the
  schema must add the new properties (`commit` pattern `^[0-9a-f]{7,40}$`, `verified_at`
  `format: date`). DO NOT fabricate a SHA — leave a marked placeholder/unknown until
  verified against `sst/opencode`.

### P2 — Schema/semantic constraints (LOW risk)

- [x] P2-5: Per-kind invariants. In `gen_mermaid.py` `semantic_errors` (27-75) +
  `lifecycle.schema.json` `allOf` (100-109). Schema: `precedence` forbids nodes/edges +
  requires `items` `minItems: 2`; `inventory` forbids edges; `pipeline`/`statechart`/
  `decision`/`dataflow` require nodes `minItems`. `semantic_errors`: relational checks
  are awkward for JSON Schema — prefer schema for shape, `semantic_errors` for relational
  (matches the existing split).
- [x] P2-6: `minItems: 1` on collections. In `lifecycle.schema.json`: add `minItems: 1`
  to node collection arrays and machine-level `nodes`/`items`/`edges` where empty is
  meaningless. `machines` already has `minItems: 1` (line 25) — do not duplicate.

### P3 — DEFERRED enrichments (MEDIUM risk — DO NOT implement in this slice)

- [ ] P3-7 (DEFERRED): Presentation/model separation. New `views/mermaid.yaml`
  (node-type → shape/class); `gen_mermaid.py` `node_line` (85-98); YAML nodes drop
  inline shape/class; schema node def (52-63) optionalizes shape+class. High churn
  (regenerates all artifacts) — isolate.
- [ ] P3-8 (DEFERRED, GATED): Real statechart semantics. Files:
  `opencode.lifecycle.yaml` `agent_loop` (158-199) & `permission_engine`; schema
  node/machine defs (add `initial`/`transitions`/`event`/`guard`/`action`,
  `additionalProperties: false` constraint applies); `semantic_errors` kind-specific
  checks (single initial, terminal states, bounded retry, reachability). Motivating
  defect: `agent_loop` has `COMPACT→OVERFLOW` (184) and `RETRY→AG` (187) loops with no
  exhaustion path. GATE: verify actual upstream retry/exhaustion behavior in
  `sst/opencode` (`session/retry.ts` 35-65, `session/prompt.ts`) before encoding; if
  unverifiable, stop and report unknown.
- [ ] P3-9 (DEFERRED, PROTOTYPE-LANE-BLOCKED): Generated-artifact freshness + CI gate.
  Files `gen_mermaid.py` `--check`/new check subcommand (189-190); `check_mermaid.mjs`
  (9-12) as gate. Add a check mode that regenerates to a temp dir and diffs committed
  `generated/`, failing on drift. CI wiring is PROTOTYPE-LANE-BLOCKED (touches
  repo-tracked `.github/`, pulls the prototype into production) — document the manual
  gate command only until promotion.

## Acceptance Criteria

Per-phase, each observable and testable.

> **Implementation status (2026-07-10):** P0-1, P1-2, P1-3, P1-4, P2-5, P2-6 code is
> APPLIED and statically verified (edits read back; P2 constraints confirmed not to
> reject the current valid model by inspection). Runtime-proof ACs (AC-01–AC-08) were
> **NOT executed** in the implementing session because `python3 *` was policy-blocked;
> they remain unchecked pending a run of the Python + Node toolchain. Structural/negative
> ACs verified by inspection are checked below. (Policy has since been updated to grant
> `python3 *` = ask to write/edit agents, so the runtime proof can be run next session.)

### P0/P1

- [ ] AC-01: `combined.mmd` regenerates with `machine__node` IDs and resolving
  cross-links.
- [ ] AC-02: The other 9 `.mmd` files are byte-identical after regeneration.
- [ ] AC-03: A model with a duplicate YAML mapping key exits with code `2`.
- [ ] AC-04: Unreadable/malformed input exits with code `2` and a one-line stderr
  diagnostic.
- [ ] AC-05: The new structured `upstream` + `schema_version` passes
  `python3 scripts/gen_mermaid.py --check`.
- [ ] AC-06: An unknown extra meta key still fails schema validation (exit `1`).

### P2

- [ ] AC-07: Minimal invalid models each fail with the intended message —
  `precedence` with edges, `inventory` with edges, single-item `precedence`, and empty
  nodes.
- [ ] AC-08: The real model still passes `python3 scripts/gen_mermaid.py --check`.

### P3 (only when ungated — not part of this slice)

- [ ] AC-09: Statechart checks flag the unbounded `agent_loop` loops ONLY after upstream
  behavior is verified.
- [ ] AC-10: The freshness check fails on injected drift.

### Negative Acceptance Criteria

- [x] AC-N1: Per-machine `.mmd` outputs are unchanged in P0. (`render_machine` untouched;
  only `render_combined` changed. Byte-identity to be reconfirmed on regeneration.)
- [x] AC-N2: No rejected runtime-semantics are encoded. (No capability→EXEC / MCP /
  approval-outcome / plugin-retry semantics added.)
- [x] AC-N3: No CUE is introduced.
- [x] AC-N4: No repo-tracked file is modified by P0–P2. (`git status` confirms only the
  untracked `___ARCHITECTURE_2.0/` tree and this plan file changed.)

## Verification Plan (Python + Node; NOT covered by `composer test`)

- Per-phase focused proof: `python3 scripts/gen_mermaid.py --check`
- End-of-phase full proof: `python3 scripts/gen_mermaid.py` then
  `node scripts/check_mermaid.mjs`
- Phase-specific proofs as listed in the Acceptance Criteria above.
- Report Python and Node exit codes honestly for each run.

AC → proof mapping:

- AC-01/AC-02/AC-N1: run full regenerate, then diff `generated/` — only `combined.mmd`
  changes; the 9 per-machine `.mmd` stay byte-identical.
- AC-03/AC-04/AC-06: run `gen_mermaid.py` against crafted invalid inputs and assert the
  exact exit code (`2` for parse/IO and dup-key; `1` for schema).
- AC-05/AC-08: `python3 scripts/gen_mermaid.py --check` on the real model returns `0`.
- AC-07: run against each minimal invalid model and assert the intended message.
- AC-09/AC-10: deferred; only when P3 is ungated.

Anti-freeze budgets:

- `--check` ≤ 30s
- full regenerate ≤ 60s
- `check_mermaid.mjs` ≤ 90s
- On overrun: kill and bisect by single `.mmd`.

## Risks And Rollback

- P0/P1: LOW — self-contained, deterministic; only `combined.mmd` regenerates. Rollback:
  revert the untracked-file edits; regenerate to restore artifacts.
- P2: LOW — additive constraints. Rollback: remove the added schema/semantic constraints.
- P3: MEDIUM and DEFERRED — P3-7 high churn (regenerates all artifacts); P3-8
  behavior-encoding gated on upstream verification (stop and report unknown if
  unverifiable); P3-9 CI touches production and is blocked on promotion.
- Success signal: the two-command proof passes with the expected exit codes after each
  phase, and the byte-identical / exit-code assertions above hold.

## Handoff Notes

- Implementer works exclusively inside the untracked `lifecycle/` directory.
- Land P0/P1 as one phase-slice, then P2; stop before P3.
- P1-2 + P1-3 are coupled (dup-key error must route to exit 2).
- P1-4 YAML + schema are coupled; use a placeholder SHA (do not fabricate a real one).
- Run the two-command proof after each phase and report Python + Node exit codes
  honestly.
- Do not touch repo-tracked files in P0–P2; do not promote the prototype.
- Recommended next step: hand off to the implementer means implementer agent handoff
  using OpenCode command: /implement
