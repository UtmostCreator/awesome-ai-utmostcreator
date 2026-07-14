# Architecture Plan — Telemetry MODEL consistency: evaluation-pin reconciliation + doc-sync

- Ticket: `arch-todo-telemetry-evidence-pipeline-20260711-132347` (same dedicated ticket as plan-1..plan-4)
- Source: post-RSETTLED-backport model consistency scoping, 2026-07-11
- Generated: 2026-07-11
- Current branch: `fix/opencode-agent-body-parity`
- Plan file: `docs/tickets/arch-todo-telemetry-evidence-pipeline-20260711-132347/plan-5-model-consistency-eval-pin-reconciliation.md`

> **SCOPE — MODEL ONLY.** Covers the canonical model
> `___ARCHITECTURE_2.0/telemetry/model/telemetry.yaml`, its regenerated `.mmd`
> diagrams, and the `verified_against` meta note. It does NOT cover the sibling
> `../ai-monitoring` TypeScript/PostgreSQL implementation.

> **UNTRACKED TREE — NO COMMITS.** The entire `___ARCHITECTURE_2.0/` tree is
> untracked. Do not stage or commit it. The current branch is unrelated.

> **VERIFICATION GAP — READ FIRST.** This sandbox has no `python3` with
> `pyyaml`+`jsonschema` and no `nix-shell`, so `scripts/gen_mermaid.sh --check`
> and the regenerate step CANNOT run here. Every model edit below is a source
> edit; the paired `--check` + regenerate MUST run in an environment that
> provides those deps (or nix-shell) before the `.mmd` artifacts are current.

> **Completion instruction:** When every `## Todo Plan` and `## Acceptance
> Criteria` item is `[x]`, rename to
> `DONE-plan-5-model-consistency-eval-pin-reconciliation.md` and move into
> `archive/` under this ticket folder.

## Context

The RSETTLED node + `RSETTLE → RSETTLED` edge + re-pointed
`RSETTLED → SPROJ/PF/MANUAL/VIEW` cross-links were backported into
`model/telemetry.yaml` from the proven `ai-monitoring` shape. That backport
introduced a durable **immutable** settlement fact (`RSETTLED`) carrying
`accepted_set_digest` + `accepted_event_high_watermark` as the certificate of
exactly which accepted-event set was evaluated.

But the evaluation machine still pins evaluations to `run_state_digest`, a field
that lives on the **mutable, projected** `RSTATE` state index (rebuilt
idempotently by the outbox projector `STATEPROJ`). The model now carries two
competing pin concepts:

- `accepted_set_digest` on the **immutable** `RSETTLED` fact (line 121), also
  surfaced by the `RSETTLED → VIEW` cross-link label "pinned accepted-event set"
  (line 294) — the intended, durable pin.
- `run_state_digest` on the **mutable** `RSTATE` projection (line 119),
  referenced by `EREC` (line 219) and the `EREC → VIEW` edge label (line 226) as
  "pins the accepted-event set evaluated".

Pinning immutable evaluation records to a digest that lives on a rebuildable
projection is a live semantic contradiction: a reprojection can change
`run_state_digest` while the accepted-event set actually evaluated is fixed by
`accepted_set_digest`. The correct single source of the evaluation pin is the
immutable `RSETTLED` fact.

### Current sync state (do not needlessly regenerate)

Model ↔ diagram are **currently IN SYNC** (verified this session):

- `combined.mmd` node declarations match the model's 60 node ids.
- `RSETTLED` present in both (`combined.mmd` node L66; edges L74, L167-170) and
  in `run_settlement.mmd`.
- `EREC`'s `run_state_digest` text and the `EREC → VIEW` `run_state_digest` label
  appear identically in `evaluation.mmd` (L7, L13) and `combined.mmd` (L131, L137).
- All cross_links qualified refs resolve to a declared node id — no dangling
  edges, no orphan nodes, no reference to the removed `run_ledger` machine;
  `generated/run_ledger.mmd` is already gone.

Consequence: the `run_state_digest` contradiction is baked into BOTH the model
and the generated `.mmd`. Because they are in sync now, regenerate ONLY AFTER the
P0/P1/P2 model edits — not before, and not to "fix" a drift that does not exist.

## Confirmed model gaps (ranked)

### P0 — Evaluation pin points at the mutable projection digest, not the immutable settlement fact
**File/lines:** `model/telemetry.yaml` — `EREC` note L219; `EREC → VIEW` label L226
(context: `RSTATE` L119, `RSETTLED` L121, `RSETTLED → VIEW` cross-link L294).

**Change (canonical: `RSETTLED.accepted_set_digest` is the single pin):**
1. `EREC` note (L219): drop `run_state_digest`; replace with
   `source_run_id · run_epoch · accepted_set_digest (pins the immutable
   accepted-event set evaluated) · accepted_event_high_watermark`. Keep the other
   evaluator-provenance fields unchanged.
2. `EREC → VIEW` label (L226): `run_state_digest` → `accepted_set_digest`.
3. Leave `run_state_digest` ON `RSTATE` (L119) — a legitimate projection/rebuild
   checksum; only its reuse as the evaluation pin is removed.

**Why:** Immutable evaluation records must pin to an immutable fact.
`RSETTLED.accepted_set_digest` is that fact; `RSTATE.run_state_digest` is a
rebuildable projection digest. Aligns `EREC → VIEW` with the existing
`RSETTLED → VIEW` "pinned accepted-event set" cross-link (L294).

### P1 — EREC has no provenance tie to RSETTLED (missing wiring)
**File/lines:** `model/telemetry.yaml` — `cross_links`, add near L284-294.

**Change:** add
`- {from: run_settlement.RSETTLED, to: evaluation.EREC, label: "pins evaluated set (source_run_id · run_epoch · accepted_set_digest)"}`

**Why:** After P0, EREC's pin fields are exactly RSETTLED's primary key + digest.
The transitive path `RSETTLED → PF/MANUAL → EREC` proves *eligibility* only; this
direct edge encodes *which settled set each record pins* and makes it
diagram-visible. Closes candidate gaps #2 and #3 together.

### P2 — `verified_against` meta note is stale and cites a missing file
**File/lines:** `model/telemetry.yaml` — `meta.verified_against` L16; header comment L3-4, L12-15.

**Two defects, one region:**
- (a) The note describes the pre-RSETTLED state and never records the RSETTLED
  backport reconciliation.
- (b) **`___ARCHITECTURE_2.0/telemetry/architecture.md` DOES NOT EXIST** —
  verified: `ls` returns "No such file or directory" and a recursive
  `find … -iname architecture.md` returns nothing. Both L16 and the header
  comment L3-4 point at an absent file.

**Change:** append a 2026-07-11 line recording the RSETTLED backport
reconciliation (RSETTLED added; 9B/10 re-pointed at the immutable settlement fact;
eval pin moved `run_state_digest` → `accepted_set_digest`); and correct the
source claim (mark the transcription source `unknown / file not present`, or state
this YAML is now the sole source and the generator owns the diagram). Reconcile
header comment L3-4 with the absent-file reality.

**Why:** A `verified_against` that names a non-existent file and predates the
current model shape misrepresents provenance and sync status. This is doc-sync
WITHIN the model file — fully MODEL scope. (Candidate #5 assumed the file still
showed the ledger; the actual finding is stronger — the file is gone.)

## Non-gaps evaluated and EXCLUDED (with reason)

- **CINBOX writer / "registered" projection (candidate #4):** MODEL is complete —
  `CINBOX → RSTATE "project: registered"` (L254) present and correctly
  dotted-projection styled; `CAND → CINBOX` + `CINBOX → EGATE` wire it in.
  "No writer in the impl" is an **ai-monitoring CODE** gap, OUT OF SCOPE.
- **Dangling edges / orphans / unknown classes (candidate #6):** verified by hand
  — every cross_link qualified ref resolves to a declared node id; no orphans;
  every `class:`/`default_class:` references a key in `classes:` (L20-30). No gap.
- **Re-adding `run_ledger` / machine 7:** intentionally removed 2026-07-11; NOT a
  gap. Do not re-add.

## Out Of Scope (Things To Avoid)

- Do NOT touch `../ai-monitoring` — MODEL only.
- Do NOT hand-edit any `___ARCHITECTURE_2.0/telemetry/generated/*.mmd`; regenerate.
- Do NOT re-add the `run_ledger` machine or ledger cross-links.
- Do NOT remove `run_state_digest` from `RSTATE` (L119) — only its EREC reuse.
- Do NOT stage/commit `___ARCHITECTURE_2.0/` (untracked, unrelated branch).
- Do NOT regenerate `.mmd` BEFORE applying the model edits (already in sync).

## Affected Paths

- `___ARCHITECTURE_2.0/telemetry/model/telemetry.yaml` — EDIT: EREC note (L219),
  EREC→VIEW label (L226), one new `RSETTLED → EREC` cross_link (~L294), meta
  `verified_against` + header comment (L3-4, L16).
- `___ARCHITECTURE_2.0/telemetry/generated/evaluation.mmd` — REGENERATED.
- `___ARCHITECTURE_2.0/telemetry/generated/combined.mmd` — REGENERATED.
- `___ARCHITECTURE_2.0/telemetry/generated/run_settlement.mmd` — REGENERATED.
- `___ARCHITECTURE_2.0/telemetry/architecture.md` — INVESTIGATE (absent); P2 fixes
  the model's reference either way. Recreating it is a separate decision.

## Contracts And Boundaries

- Model is single source of truth; `.mmd` are generated. Edits go to the YAML.
- `RSETTLED` is immutable (one fact per `source_run_id`+`run_epoch`); "which set
  was evaluated" must reference it, not mutable `RSTATE`.
- `EREC` is immutable; its pin becomes the `RSETTLED` identity + digest.
- Generator: `scripts/gen_mermaid.sh` wraps
  `../opencode_architecture/lifecycle-model/lifecycle/scripts/gen_mermaid.py`,
  validated against `lifecycle.schema.json`; only writer of `generated/`.

## Todo Plan

### P0 — Fix the evaluation pin (model)
- [x] Edit `EREC` note (L219): drop `run_state_digest`; add
      `source_run_id · run_epoch · accepted_set_digest (pins the immutable
      accepted-event set evaluated) · accepted_event_high_watermark`. Done — now
      at L220 after the P1/P2 insertions shifted line numbers by +2.
- [x] Edit `EREC → VIEW` label (L226): `run_state_digest` → `accepted_set_digest`.
      Done — now at L227.

### P1 — Wire EREC to the immutable fact (model)
- [x] Add cross_link `run_settlement.RSETTLED → evaluation.EREC` with label
      `"pins evaluated set (source_run_id · run_epoch · accepted_set_digest)"`.
      Done — added at L296 as the 5th `RSETTLED →` cross-link.

### P2 — Reconcile meta / doc-sync (model)
- [x] Update `meta.verified_against` (L16, now L17): appended a 2026-07-11
      RSETTLED-backport + eval-pin-fix line; corrected the `architecture.md`
      reference to state it is absent from disk and the reference is historical.
- [x] Reconcile header comment L3-4 (now L3-5): rewritten to state
      `architecture.md` does not exist on disk (verified) and this YAML is the
      sole source of truth.

### P3 — Regenerate (REQUIRES python3+pyyaml+jsonschema OR nix-shell — NOT this sandbox)
- [x] `bash ___ARCHITECTURE_2.0/telemetry/scripts/gen_mermaid.sh --check` passes.
      **Run by the user outside the blocked sandbox** (2026-07-11): output
      `model ok: 11 machines, 39 cross-links` (39 = 38 prior + 1 new
      `RSETTLED → EREC` link; 11 machines unchanged — no new machine added).
- [x] Regenerate the `.mmd` from the model. **Done** — user ran
      `bash ./gen_mermaid.sh`; all 12 `.mmd` files rewrote successfully
      (`runtime_signals` through `combined`).
- [x] Confirm `evaluation.mmd` + `combined.mmd` show `accepted_set_digest` (not
      `run_state_digest`) on EREC + EREC→VIEW, and `combined.mmd` shows the new
      `RSETTLED → EREC` edge. **Verified by grep on the regenerated files:**
      `run_state_digest` now appears only on `RSTATE` in `combined.mmd` (zero
      hits in `evaluation.mmd`); `EREC` + `EREC→VIEW` in `evaluation.mmd` both
      use `accepted_set_digest`; `combined.mmd` L171 has
      `run_settlement__RSETTLED -->|"pins evaluated set (...)"| evaluation__EREC`.
      Model↔diagram are back IN SYNC.

## Acceptance Criteria

- [x] AC-01: `EREC` no longer references `run_state_digest`; pins via
      `source_run_id · run_epoch · accepted_set_digest`. `EREC → VIEW` label reads
      `(accepted_set_digest)`. **Verified in `model/telemetry.yaml`** (L220,
      L227): confirmed by grep — `run_state_digest` no longer appears on EREC or
      its edge label.
- [x] AC-02: A `run_settlement.RSETTLED → evaluation.EREC` cross_link exists with a
      pin-provenance label. **Verified** — L296, 5th `RSETTLED →` cross-link
      (grep count confirms 5, was 4).
- [x] AC-03 (negative): `run_state_digest` appears exactly once — on `RSTATE`
      (L119, now L120) — and NOWHERE in the evaluation machine. **Verified by
      grep**: one structural occurrence (RSTATE note, L120); the only other
      occurrence is the historical mention inside the `verified_against`
      changelog text (documenting the fix itself, not a live field reference).
- [x] AC-04: `meta.verified_against` records the RSETTLED backport and no longer
      asserts a present, ledger-showing `architecture.md`; header L3-4 matches
      on-disk state. **Verified** — L17 changelog + L1-7 header rewritten.
- [x] AC-05: `gen_mermaid.sh --check` passes in a capable env (machine count
      unchanged; cross-link count = previous + 1); regenerated `.mmd` reflect
      AC-01/AC-02. **Verified** — user ran `--check` (`model ok: 11 machines,
      39 cross-links`, exactly `38+1`) and the plain regenerate; grep-confirmed
      the regenerated `evaluation.mmd`/`combined.mmd` carry `accepted_set_digest`
      on EREC + the new `RSETTLED → EREC` edge, with `run_state_digest` confined
      to `RSTATE`.
- [x] AC-06 (negative): no `run_ledger` reintroduced; no `.mmd` hand-edited; no
      ai-monitoring file touched; `___ARCHITECTURE_2.0/` uncommitted. **Verified**
      — only `model/telemetry.yaml` and this plan file were edited; `git status`
      confirms `___ARCHITECTURE_2.0/` remains untracked (`??`).

## Verification Plan

- **In this sandbox (possible now):** hand ref-integrity re-check (node ids vs
  cross_link refs), `run_state_digest` occurrence count, absence of `run_ledger`
  refs. Confirm model↔diagram sync only AFTER regen in a capable env.
- **Blocked here (must run elsewhere):** `gen_mermaid.sh --check` + regenerate —
  require python3+pyyaml+jsonschema or nix-shell. Do not claim diagram parity
  until regen has run.

## Risks And Rollback

- **Risk — regen skipped:** a reader trusting the `.mmd` after a P0 model edit but
  before regen sees stale `run_state_digest`. Mitigation: P3 gate + AC-05; state
  sync status in handoff.
- **Risk — over-removal:** deleting `run_state_digest` from `RSTATE` too.
  Mitigation: AC-03 keeps it on `RSTATE`.
- **Rollback:** all edits are to an untracked YAML; revert by restoring prior
  lines. No data/schema/committed artifact touched.

## Handoff Notes

- Model↔diagram are currently in sync; regenerate ONLY after P0-P2 edits.
- Recommended next step: implementer agent applies P0-P2 YAML edits, then runs P3
  (`gen_mermaid.sh --check` + regenerate) in an env with
  python3+pyyaml+jsonschema or nix-shell, then reviewer agent via `/review-diff`
  (diffing the untracked working tree, not a commit).
