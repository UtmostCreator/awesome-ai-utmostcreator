# Architecture Plan — Add-Primitive Pipeline (Spec + Scaffold + Reachability) and Generalized Fleet

- Ticket: none
- Source: user request for a contributor-first extensibility on-ramp, grounded via 3 read-only
  researchers → repository-reviewer `CONFIRM-WITH-ADJUSTMENTS` verdict (every cited claim verified
  true) → architect design (`scratchpad/architect-design-spec.md`, six resolutions + 9 slices +
  4-plan split)
- Generated: 2026-07-09
- Plan file: docs/tickets/IDEAS/plan-add-primitive-pipeline-and-fleet.md
- Type: durable-pipeline slice group (S6, S7, S8) — third of a deliberately-split 4-plan set. This is
  the durable centerpiece: the add-a-primitive pipeline. **S6 precedes S7; S7 depends on S1–S6; S8
  depends on S7.** Companion plans: `plan-primitive-quickwins-globify-gotcha-renderall.md` (S1–S3),
  `plan-agent-docs-generation-and-composed-migration.md` (S4–S5, which S7 depends on), and
  `plan-extensibility-onramp-docs.md` (S9 documents this pipeline).
- Risk: high (S7 is the biggest design gap — the missing spec→template→render+wire stage; S8
  productizes the fleet as a Workflow and generalizes the critic rubric). S6 is low-risk test hardening.
  No byte-parity gate or composed permission model is weakened or forked; the existing agent-creator
  fleet is GENERALIZED, not replaced by a parallel system.

## Context

The single biggest gap is that **no spec→template→render→wire stage exists**. `validate-agent-spec.php`
validates an AgentSpec JSON, but nothing consumes a spec to EMIT a primitive file, and no
scaffold/new-* script exists (`ls tools/ai | grep -iE 'scaffold|create|new'` → none). The existing
5-agent creator fleet (supervisor → creator → static-validator → semantic-verifier → runtime-guardian →
human gate) plus `agent-critic` is agent-only, but its skeleton generalizes: intake → spec → static →
semantic → (reachability replaces guardian for declarative primitives) → critic. Declarative primitives
(command/skill/gotcha) need a wiring/reachability verifier, not runtime guardrails — reachability IS
correctness for declarative primitives. `validate-agent-spec.php` today has only a file-existence
assertion (`InstallerSafetyTest.php:2569`), so it must gain functional tests BEFORE a discriminated
`primitive_type` schema depends on it.

## Problem

1. **`validate-agent-spec.php` is untested functionally.** Only a file-existence assertion exists. The
   discriminated `primitive_type` schema (S7) will depend on it, so its behavior must be pinned by
   functional tests first, or the whole pipeline rests on unverified validation.
2. **No render+wire stage — the biggest gap.** Nothing consumes a spec to emit a primitive file, author
   the required registration (compositions entry for agents), regenerate the catalog and adapters, and
   drift-check. A scaffolded agent with **no compositions.php entry gets NO composed permission block,
   no error, AND passes drift `--check`** (verified `generate-agent-permissions.php:9,57`,
   `generate-claude-settings.php:162` iterate only `aiPermissionAgentCompositions()`). Scaffold MUST
   author a compositions entry or HARD-FAIL for any agent primitive.
3. **The fleet + critic are agent-only.** They must be generalized to any primitive type via a
   discriminated `primitive_type` spec, a primitive-neutral reachability verifier, and a generalized
   critic rubric — without forking into a parallel system alongside the agent-creator fleet.

## Target Outcome

- `validate-agent-spec.php` has functional tests pinning its behavior before anything depends on it.
- A `scaffold-primitive.php` CLI + `primitive-scaffold/` library exists that: validates a discriminated
  `primitive_type` spec → stamps a canonical stub with valid OpenCode frontmatter → registers only where
  required (agents: a `compositions.php` entry; other types: nothing, catalog auto-scan + glob writers
  handle pickup) → `generate-ai-catalog.php --write` → `render-all.php --write` → drift `--check`.
  Scaffold output is byte-identical to a fresh `render-all.php --write`.
- A `validate-primitive-reachability.php --check` asserts each primitive type's load path resolves
  (reachability = correctness for declarative primitives, replacing runtime guardrails).
- An `add-primitive` Workflow orchestrates the generalized fleet (intake → spec → static → semantic →
  reachability/wire → critic), with the agent-creator fleet becoming the `primitive_type==agent`
  specialization and a generalized `agent-critic` rubric giving the final word for any primitive.

## In Scope

- **S6 — validate-agent-spec.php functional tests (Low, before S7).**
  - Add functional tests for `validate-agent-spec.php` (today only a file-existence assertion at
    `InstallerSafetyTest.php:2569`) BEFORE the discriminated schema depends on it (per AC-8).
- **S7 — scaffold-primitive.php + discriminated spec + reachability verifier (High, deps S1–S6).**
  - Discriminated `primitive_type` on ONE JSON schema: thin sub-objects for
    command/workflow/skill/capability/gotcha; a rich sub-object for agent.
  - New `tools/ai/scaffold-primitive.php` CLI + `tools/ai/install/primitive-scaffold/` library
    (dispatch by type). Flow: validate spec → stamp canonical stub (valid OpenCode frontmatter) →
    register only where required (agents: `compositions.php` entry via a compose profile from the spec;
    others: nothing — catalog auto-scan + glob writers handle pickup) → `generate-ai-catalog.php
    --write` → `render-all.php --write` → drift `--check`.
  - **compositions guarantee (Resolution 1)**: scaffold emits the agent template AND appends a starter
    `compositions.php` entry keyed on the new agent id (compose profile from the spec); PLUS a new
    `validate-agent-composition-coverage.php --check` asserts every agent id has a compositions entry
    (hard CI fail — drift can't catch a missing block, a coverage assertion can). Both scaffold-authors
    AND coverage-verifier. (Migration completion + warn→enforce flip is owned by
    `plan-agent-docs-generation-and-composed-migration.md` S5; this plan BUILDS the verifier.)
  - New `tools/ai/validate-primitive-reachability.php --check` asserts each primitive type's load path
    resolves (reachability = correctness for declarative primitives; replaces runtime guardrails).
  - `validate-primitive-spec.php` generalized from `validate-agent-spec.php` (the static gate for the
    discriminated schema).
- **S8 — add-primitive Workflow + generalized fleet + generalized critic rubric (High, deps S7).**
  - New Workflow doc `templates/workflows/add-primitive.md` (auto-renders to skill + command x3
    providers) orchestrating agents and holding NO logic. Graph: intake (`repository-researcher`: gather
    intent, existing patterns, `>=75%` overlap check) → spec (`primitive-creator`: emit discriminated
    `primitive_type` spec JSON) → static (`validate-primitive-spec.php`) → semantic
    (`primitive-semantic-verifier`) → wire (`scaffold-primitive.php --write`) → reachability
    (`validate-primitive-reachability.php --check`) → gate (generalized `agent-critic` rubric, final
    word; reject loops to spec, approve → done drift-clean).
  - Generalize the existing fleet: `supervisor→creator→static→semantic→guardian` maps to
    `intake→spec→static→semantic→(reachability replaces guardian for declarative)→critic`. The
    agent-creator fleet becomes the `primitive_type==agent` specialization, NOT a parallel system.
  - Generalized `agent-critic` rubric for any primitive: schema-fit / reachability / duplication /
    parity. Fleet/critic edits go through the canonical template + `render-all`, NOT through
    `architect.md` / `architecture-plan-writer.md`.

## Out Of Scope (Things To Avoid)

- **No `packages/**` edits in the planning phase** (NAC-1). `scaffold-primitive.php` is the write path;
  human / `implementer` / `refactorer` run it under interactive gating at implementation time.
- **Do not weaken, bypass, or fork the composed permission model or byte-parity `--check` gates**
  (NAC-2). The scaffold reuses the existing gates; reachability is a PEER of, not a replacement for, the
  parity mechanism.
- **Do not create a second renderer or pipeline** (NAC-3). The generalized fleet extends the mandated
  canonical→registry→renderer→output→drift-check shape; the agent-creator fleet becomes a specialization,
  not a fork.
- **No structured Claude handoff field** (NAC-4) — the add-primitive Workflow's agent sequence stays
  prose for Claude (per AC-13).
- **No installed-kit authoring surfaces** (NAC-5) — the pipeline authors canonical source only.
- **Do not modify `architect.md` or `architecture-plan-writer.md`** (NAC-6). Fleet/critic changes go
  through canonical templates + `render-all`.
- **DO NOT create a new `packages/**`-writing maintainer agent now (Resolution 6 — DEFERRED).** See the
  deferred-decision follow-up note below. Keep this plan bounded to S6/S7/S8.
- Do NOT pull the quick wins (S1–S3), agent-docs generation / composed migration (S4–S5), or the on-ramp
  docs (S9) into this plan — those belong to the other three plans.

## Affected Paths

- `tests/php/` (S6 — functional tests for `validate-agent-spec.php`, replacing the bare
  `InstallerSafetyTest.php:2569` existence assertion; S7/S8 — new pipeline tests).
- `tools/ai/scaffold-primitive.php` (S7 — NEW CLI).
- `tools/ai/install/primitive-scaffold/` (S7 — NEW library, dispatch by `primitive_type`).
- `tools/ai/validate-primitive-spec.php` (S7 — NEW, generalized from `validate-agent-spec.php`).
- `tools/ai/validate-primitive-reachability.php` (S7 — NEW `--check` reachability verifier).
- `tools/ai/validate-agent-composition-coverage.php` (S7 — NEW `--check` coverage verifier; warn→enforce
  flip owned by S5 in `plan-agent-docs-generation-and-composed-migration.md`).
- `tools/ai/install/permission-layers/compositions.php` (S7 — scaffold appends starter entries; write
  deferred to `scaffold-primitive.php --write` under interactive gating).
- `packages/ai-universal-rules/templates/workflows/add-primitive.md` (S8 — NEW Workflow; write deferred
  under the `packages/**` approval gate).
- Canonical templates for `primitive-creator` / `primitive-semantic-verifier` agents and the generalized
  `agent-critic` rubric (S8 — via canonical template + `render-all`, NOT `architect`/`plan-writer`
  files).
- Reused: `render-all.php` (S3), `generate-ai-catalog.php`, `generate-agent-permissions.php`,
  `aiCompareOrWrite()`.

## Contracts And Boundaries

- **Dependency chain**: S6 precedes S7 (spec validator must be tested before the discriminated schema
  depends on it). S7 depends on S1–S6 — it needs glob-ified skills/capabilities + gotcha prefix (S1/S2),
  `render-all.php` + `.opencode` third-dest (S3), agent-docs generation + `lifecycle`/`risk` frontmatter
  (S4), and the composed-model migration posture (S5). S8 depends on S7.
- **compositions guarantee (Resolution 1)**: scaffold-authors AND coverage-verifier. Scaffold appends a
  starter `compositions.php` entry for any agent primitive; the new coverage verifier hard-fails if any
  agent id lacks one. This plan BUILDS the verifier; `plan-agent-docs-generation-and-composed-migration.md`
  S5 completes the migration and flips it from `--warn` to enforce.
- **Reachability is a PEER, not a fork (Resolution 5)**: `validate-primitive-reachability.php` asserts
  each type's load path resolves; it is the declarative-primitive analogue of runtime guardrails and is
  designed as a peer of the CI content-parity gate in
  `plan-provider-content-parity-validator.md`, not a competing mechanism (see
  `plan-extensibility-onramp-docs.md` S9 for the reconciliation write-up).
- **Scaffold parity**: `scaffold-primitive.php --write` output must be byte-identical to a fresh
  `render-all.php --write` (per AC-10).
- **Generalize, do not fork**: the agent-creator fleet becomes the `primitive_type==agent`
  specialization; there is no parallel pipeline (NAC-3). Fleet/critic edits flow through canonical
  template + `render-all`.
- **Prose handoffs**: the add-primitive Workflow's agent sequence uses prose recommendations for Claude;
  no structured handoff field (NAC-4 / AC-13).

## Todo Plan

Priority-grouped, unchecked.

- [ ] P0: S6 — Add functional tests for `validate-agent-spec.php` (replace the file-existence-only
      assertion at `InstallerSafetyTest.php:2569`) BEFORE the discriminated schema depends on it.
- [ ] P0: S7 — Design the discriminated `primitive_type` JSON schema on ONE schema (thin sub-objects for
      command/workflow/skill/capability/gotcha; rich sub-object for agent).
- [ ] P0: S7 — Build `tools/ai/validate-primitive-spec.php` generalized from `validate-agent-spec.php`
      (static gate for the discriminated schema).
- [ ] P0: S7 — Build `tools/ai/scaffold-primitive.php` CLI + `tools/ai/install/primitive-scaffold/`
      library (dispatch by type): validate spec → stamp canonical stub → register only where required →
      `generate-ai-catalog --write` → `render-all --write` → drift `--check`.
- [ ] P0: S7 — Scaffold appends a starter `compositions.php` entry for agent primitives (compose profile
      from the spec) OR hard-fails; build `validate-agent-composition-coverage.php --check` asserting
      every agent id has a compositions entry (hard CI fail).
- [ ] P1: S7 — Build `tools/ai/validate-primitive-reachability.php --check` asserting each type's load
      path resolves (reachability = correctness for declarative primitives).
- [ ] P1: S8 — Author `templates/workflows/add-primitive.md` Workflow (auto-renders skill+command x3
      providers) orchestrating the graph: intake → spec → static → semantic → wire → reachability → gate.
      Holds NO logic.
- [ ] P1: S8 — Generalize the fleet: map `supervisor→creator→static→semantic→guardian` to
      `intake→spec→static→semantic→(reachability replaces guardian for declarative)→critic`; make the
      agent-creator fleet the `primitive_type==agent` specialization (no parallel system).
- [ ] P2: S8 — Generalize the `agent-critic` rubric for any primitive (schema-fit / reachability /
      duplication / parity), edits via canonical template + `render-all`, not `architect`/`plan-writer`.
- [ ] P2: S7/S8 — Confirm scaffold parity: `scaffold-primitive.php --write` output byte-identical to a
      fresh `render-all.php --write`.

## Acceptance Criteria

Each AC names its Type / what it Proves / how it is Verified.

- [ ] AC-01 (S6, explicit — AC-8): Type=test hardening. Proves `validate-agent-spec.php` behavior is
      pinned before the discriminated schema depends on it. Verified by new functional tests exercising
      valid/invalid spec cases (beyond the `InstallerSafetyTest.php:2569` existence assertion) passing.
- [ ] AC-02 (S7, explicit — AC-1): Type=compositions guarantee. Proves scaffold authors a
      `compositions.php` entry OR hard-fails for any agent primitive (no silent-missing block). Verified
      by a test scaffolding an agent spec and asserting either a starter `compositions.php` entry is
      appended or the CLI exits non-zero.
- [ ] AC-03 (S7, explicit — AC-5): Type=schema + stage + verifier. Proves the discriminated
      `primitive_type` schema shape is decided, the spec→template→render+wire stage is located, and a
      primitive-neutral reachability verifier exists. Verified by `validate-primitive-spec.php` accepting
      each `primitive_type`, `scaffold-primitive.php` emitting the stub, and
      `validate-primitive-reachability.php --check` passing for each type.
- [ ] AC-04 (S7, explicit — AC-10): Type=byte-parity + reachability. Proves every primitive names a load
      path, passes drift `--check`, and scaffold output is byte-identical to regen. Verified by
      `validate-primitive-reachability.php --check` green and `scaffold-primitive.php --write` output ==
      fresh `render-all.php --write` output for each type.
- [ ] AC-05 (S7, coverage verifier): Type=coverage assertion. Proves a missing compositions block is
      caught (drift can't catch it). Verified by `validate-agent-composition-coverage.php --check`
      failing when an agent id has no compositions entry and passing when all do.
- [ ] AC-06 (S8, explicit — AC-2 restated for the pipeline): Type=three-provider render+wire. Proves the
      pipeline covers all three provider paths incl. the `.opencode` installer route. Verified by
      `scaffold-primitive.php --write` (which calls `render-all.php`) producing `.claude`, `.github`, and
      `.opencode` agent-tree outputs and a green `--check`.
- [ ] AC-07 (S8, explicit — AC-13): Type=prose-handoff guard. Proves Claude handoffs stay prose. Verified
      by the rendered `add-primitive` Workflow / agent bodies for `.claude` carrying no structured
      handoff field, only prose recommendations.
- [ ] AC-08 (negative — NAC-3): Type=no-fork assertion. Proves the fleet is generalized, not forked.
      Verified by review that the agent-creator fleet is the `primitive_type==agent` specialization and
      no second renderer/pipeline was introduced.
- [ ] AC-09 (negative — NAC-1/NAC-6): Type=scope guard. Proves no `packages/**` edit in planning and no
      change to `architect.md`/`architecture-plan-writer.md`. Verified by this plan producing no
      `packages/**` diff and no edit to those two agent files.

## Verification Plan

Each step names the exact gate or test that proves an AC.

- New functional tests for `validate-agent-spec.php` — proves AC-01.
- New tests for `validate-primitive-spec.php` — proves AC-03 (each `primitive_type` accepted/rejected).
- New tests for `validate-primitive-reachability.php` — proves AC-03/AC-04 (each type's load path
  resolves).
- New tests for `validate-agent-composition-coverage.php` — proves AC-05 (missing block caught).
- `php tools/ai/validate-primitive-reachability.php --check` — proves AC-04.
- Scaffold-parity test: `scaffold-primitive.php --write` output byte-identical to fresh
  `render-all.php --write` — proves AC-04, AC-06.
- `php tools/ai/render-all.php --check` (from S3) — proves AC-06 (three provider trees clean after wire).
- `php tools/ai/generate-ai-catalog.php --check` and `generate-agent-permissions.php --check` — prove the
  scaffold's regen steps leave the catalog and composed permissions clean (NAC-2).
- Rendered `.claude` Workflow/agent body inspection — proves AC-07 (no structured handoff field).
- `tests/php` full-install-validation (`full-install-validation.php`) — proves the pipeline installs
  cleanly end-to-end.

## Risks And Rollback

- **High (S7)**: `scaffold-primitive.php` is the biggest design gap (the missing render+wire stage);
  primary risk is a scaffolded agent silently missing a composed permission block. Mitigated by the
  scaffold-authors-a-compositions-entry-OR-hard-fails rule (AC-02) plus the coverage verifier (AC-05).
  Rollback: `scaffold-primitive.php` and its verifiers are new tools — removing them restores the
  manual (registry-touch) authoring path.
- **High (S8)**: generalizing the fleet + critic risks forking into a parallel system; mitigated by the
  generalize-not-fork boundary (agent-creator becomes the `agent` specialization) and the no-fork
  assertion (AC-08). Rollback: keep the agent-creator fleet as-is and defer the `add-primitive` Workflow.
- **Low (S6)**: additive test hardening only. Rollback: remove the new tests.
- **Dependency risk**: S7 depends on S1–S6 landing; S8 depends on S7. If S3/S4/S5 stall, S7 is blocked.
  Documented as explicit sequencing.
- **Unknown / DEFERRED (non-blocking)**: the dedicated `packages/**`-writing maintainer agent — see the
  follow-up note below. Not resolved here.

## Deferred Decision Follow-Up (Resolution 6 — Maintainer Agent) — NOT A SLICE

Per Resolution 6, **do NOT create a new `packages/**`-writing maintainer agent now.**
`scaffold-primitive.php` (S7) is the write path, run by a human / `implementer` / `refactorer` under
interactive gating. A dedicated maintainer agent's `packages/**` grant collides with the active
edit-deny posture and is therefore **deferred, explicit-approval-gated, and routed to `workflow-auditor`
first** for the permission-model ruling. This is marked `unknown` / deferred and is out of the first
plan set — it is recorded here as a follow-up note, not a slice, and must not be resolved or implemented
by this plan. `config-maintainer` explicitly DENIES `packages/**`; `workflow-auditor` / `infra-auditor`
/ `repository-reviewer` are read-only; `implementer` / `refactorer` are the generic fallback that can
write `packages/**` under gating today.

## Handoff Notes

- **Recommended next step**: `implementer means implementer agent handoff` to build S6 first (test
  hardening), then S7 (the discriminated spec + `scaffold-primitive.php` + reachability verifier) once
  S1–S6 have landed, then S8 (the `add-primitive` Workflow + generalized fleet/critic).
- S7 is blocked on S1–S6 (quick wins + agent-docs generation + composed-model posture). Do not start S7
  before those land; do not start S8 before S7.
- The composed-model migration completion and the `validate-agent-composition-coverage.php`
  warn→enforce flip are owned by `plan-agent-docs-generation-and-composed-migration.md` S5 — this plan
  BUILDS the verifier; that plan flips it.
- The deferred maintainer-agent decision routes to `workflow-auditor means workflow-auditor agent
  handoff` if/when it is revisited — but that is out of scope for this plan.
- Claude handoffs stay prose per `docs/ai/handoff-contract.md`; no structured handoff field (NAC-4).
