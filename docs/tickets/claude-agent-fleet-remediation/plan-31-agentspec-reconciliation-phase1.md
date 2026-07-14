# Architecture Plan — AgentSpec Reconciliation Phase 1

- Ticket: claude-agent-fleet-remediation (branch `fix/opencode-agent-body-parity`)
- Source: Architect design pass operationalizing the P0 gate of `docs/tickets/claude-agent-fleet-remediation/plan-3-agent-fleet-production-roadmap.md` (line 87 P0 item + line 156 Handoff Notes)
- Generated: 2026-07-10
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-31-agentspec-reconciliation-phase1.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-31-agentspec-reconciliation-phase1.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-31-agentspec-reconciliation-phase1.md`). See "Archive On Completion" below for the exact steps.

## Context

This plan is Phase 1 of the AgentSpec/permission remediation roadmap defined in
`plan-3-agent-fleet-production-roadmap.md`. It operationalizes that roadmap's P0
architect gate (line 87 P0 item; line 156 Handoff Notes) into implementation-ready
subtasks.

An architect reconciliation pass produced a load-bearing finding that changes the
shape of Phase 1 from what plan-3 originally proposed. The controlling facts, artifact
classification, conflict resolutions, and human-decision gates are recorded verbatim
below and MUST NOT be re-derived or widened by the implementer.

## Problem

plan-3 originally proposed a net-new contracts/spec layer
(`packages/ai-contracts/` + `packages/ai-universal-rules/specs/**`) as the source of
truth for agent specs and permissions. The reconciliation pass found this conflicts
with the already-shipped composition engine and with a locked architectural decision.

### Core reconciliation finding (record verbatim)

Phase 1 must be an **EXTENSION of the existing composition engine**
(`tools/ai/install/permission-layers/` — `compose.php` / `compositions.php` /
`agent-spec.php` / `render-adapters.php`), **NOT** the net-new
`packages/ai-contracts/` + `packages/ai-universal-rules/specs/**` layer that plan-3
originally proposed.

The composed per-agent model (`aiPermissionComposeFromSpec` over
`aiInstallerAgentProfiles()` / `aiPermissionAgentCompositions()`) already **IS** the
single source of truth; building a second one violates the 2026-06-13 locked decision
(`arch-todo-agent-permission-rethink-20260613T154104Z/plan.md`) and the `>=75%` reuse
rule.

## Target Outcome / Scope

Deliver Phase 1 as four bounded slices that **extend** the existing composition engine,
land the implementer permission-profile pilot, and add two thin validation CLIs
projecting existing invariants — with no net-new SoT layer and no second registry.

Phase 1 is complete when: the human D1 decision has been ratified, the implementer
pilot ships a byte-identical OpenCode permission block, the two validation CLIs are wired
into CI, the already-delivered drift/header coverage is documented as satisfied, and
`composer test` is green.

## In Scope

- PH1-S1: Land the implementer permission-profile pilot (additive named projection over
  the composed model; implementer-only; byte-identical shipped OpenCode block).
- PH1-S2: Add `tools/ai/validate-agents.php` (one agent -> one spec) as a thin wrapper.
- PH1-S3: Add `tools/ai/validate-permissions.php` projecting existing hard-deny /
  edit-surface invariants into a CLI.
- PH1-S4: Document the generated-header / drift coverage ALREADY delivered by plan-28
  (assert-already-satisfied; no new tool).
- Recording the human-decision gates (D1–D4) and blocking implementation on D1.

## Out Of Scope (Things To Avoid)

- Creating `packages/ai-contracts/` (schema home is already `schemas/ai/`).
- Creating `packages/ai-universal-rules/specs/**` YAML SoT layer.
- Building `permission-profile.schema.json` (or any file) as a second enforceable registry.
- Portable skills under a new `.agents/skills/` root (skills already live at
  `templates/skills/`, `.claude/skills/`, `.opencode/skills/`).
- Re-deriving the OpenCode template-strip renderer — that remains the job of the still-OPEN
  ticket `arch-todo-opencode-render-permission-from-composition-20260706-030000`.
- Repeating the halted wildcard-collapse from
  `arch-todo-implementer-permission-line-reduction-pilot-20260706-233000`.
- Touching `render-agent-permissions.php`.
- Phase 2 (policy-gate consistency: reconcile `command-policy.tiers.yaml` sh-hook vs
  composed hard-deny; AC-08/09/10/11) — SEPARATE later plan.
- Phase 3 (GENUINELY-NEW: `eval-agents.php` + `tests/agent-evals/**` for AC-12/13,
  context-pack budget wiring AC-14, `score-handoffs.php` AC-15, orchestrator scope-gate
  AC-16/17) — SEPARATE later plan.
- Beginning ANY Phase 1 implementation before human D1 is resolved.

## Affected Paths

PH1-S1:

- `tools/ai/install/permission-layers/compositions.php` [annotation]
- `templates/core/agents/implementer.md`
- `.opencode/agents/implementer.md` [regenerated]
- `docs/ai/adapter-contract.md`
- +1 doc

PH1-S2:

- `tools/ai/validate-agents.php` [new]
- `tests/php/ValidateAgentsTest.php` [new]
- `.github/workflows/validate-ai-surface.yml` [CI wire]

PH1-S3:

- `tools/ai/validate-permissions.php` [new]
- `tests/php/ValidatePermissionsTest.php` [new]
- `.github/workflows/validate-ai-surface.yml` [CI wire]

PH1-S4:

- `docs/ai/source-of-truth.md` [spot-check]
- `docs/ai/generated-artifacts.md`

## Contracts And Boundaries

The composed per-agent model (`aiPermissionComposeFromSpec` over
`aiInstallerAgentProfiles()` / `aiPermissionAgentCompositions()`) is the single source of
truth. Phase 1 adds only **additive named projections** over it and **thin CLIs** that
project existing invariants.

### Artifact classification

| Class | Artifact | Existing home / reuse note |
| --- | --- | --- |
| ALREADY-DELIVERED | agent.schema.json | `schemas/ai/agent-spec.schema.json` |
| ALREADY-DELIVERED | handoff.schema.json | `schemas/ai/ai-handoff.schema.json` |
| ALREADY-DELIVERED | evidence.schema.json | `schemas/ai/evidence-event.schema.json` |
| ALREADY-DELIVERED | render-agents.php | `tools/ai/render-adapters.php` (via plan-28) |
| ALREADY-DELIVERED | validate-generated-drift.php | `render-adapters.php --check` + `AdapterRenderDriftTest` + `validate-generated-artifacts.php` |
| ALREADY-DELIVERED | policy-gate.php | `tools/ai/compile-command-policy.php` -> `command-policy.compiled.sh`, deny>ask>allow |
| ALREADY-DELIVERED | /context-pack token budget | `tools/ai/build-context-pack.php` + `validate-context-budgets.php` |
| ALREADY-DELIVERED | /secret-scan | `tools/ai/secret-scan.php` |
| EXTEND-EXISTING | validate-agents.php | extend `validate-agent-spec.php` + plan-28 composition-coverage guard-test |
| EXTEND-EXISTING | validate-permissions.php | project existing `aiPermissionAssertNoHardDenyWeakening` + edit-surface deny checks into a CLI |
| EXTEND-EXISTING | the 7 permission profiles | extend `aiPermissionProfileLayerNames` readonly/verify/impl + implementer-pilot `permission_profile` projection |
| EXTEND-EXISTING | workflow commands | extend existing |
| EXTEND-EXISTING | structured Final Output contract | extend existing |
| EXTEND-EXISTING | agent-fleet-assessor read-only upgrade | extend existing |
| GENUINELY-NEW | eval-agents.php + tests/agent-evals/** | defer to Phase 3 |
| GENUINELY-NEW | score-handoffs.php | defer to Phase 3 |
| CONFLICTS-WITH-LOCK | permission-profile.schema.json as a second registry | forbidden |
| CONFLICTS-WITH-LOCK | packages/ai-contracts/ new dir | forbidden (schema home is already `schemas/ai/`) |
| CONFLICTS-WITH-LOCK | packages/ai-universal-rules/specs/** YAML SoT layer | forbidden |
| CONFLICTS-WITH-LOCK | portable skills under new `.agents/skills/` root | forbidden (skills already live at `templates/skills/`, `.claude/skills/`, `.opencode/skills/`) |

### Conflict resolutions (record both verbatim)

- **Conflict (1):** DO NOT build `permission-profile.schema.json` as an enforceable second
  registry. Permission profiles must be additive named projections over the composed
  model, exactly as the implementer pilot designed. If a schema is wanted, add a
  DESCRIPTIVE one to the EXISTING `schemas/ai/` home that validates the projection
  vocabulary (names -> existing model keys), never a parallel rule store.
  `packages/ai-contracts/` must not be created.

- **Conflict (2):** `arch-todo-opencode-render-permission-from-composition-20260706-030000`
  is still OPEN (verified: `templates/core/agents/implementer.md` still carries a full baked
  `permission:` block, lines 22-40+; that ticket's Slice 3 has not landed) and remains the
  correct home for the OpenCode template-strip renderer direction. plan-31 defers OpenCode
  template-strip to that ticket; do NOT re-derive it and do NOT repeat the halted
  wildcard-collapse from
  `arch-todo-implementer-permission-line-reduction-pilot-20260706-233000`.

## Todo Plan

- [ ] P0: **BLOCKING GATE** — Do NOT begin any Phase 1 implementation until human decision
  D1 (below) is ratified. D1 gates all downstream implementation.
- [ ] P0: PH1-S1 — Land the implementer permission-profile pilot
  (`arch-todo-implementer-permission-profile-pilot-20260706-215934`, currently all
  unchecked, ~85-90% reuse, byte-identical shipped OpenCode block, implementer-only).
  Files: `tools/ai/install/permission-layers/compositions.php` [annotation],
  `templates/core/agents/implementer.md`, `.opencode/agents/implementer.md` [regen],
  `docs/ai/adapter-contract.md`, +1 doc.
  Verify: `php tools/ai/generate-agent-permissions.php --check` (byte-identical) and
  `php tools/ai/render-adapters.php --check`. Maps AC-05, partial AC-06/AC-07.
- [ ] P0: PH1-S3 — Add `tools/ai/validate-permissions.php` projecting existing
  `aiPermissionAssertNoHardDenyWeakening` + edit-surface deny checks into a CLI.
  Files: `tools/ai/validate-permissions.php`, `tests/php/ValidatePermissionsTest.php`,
  CI wire in `.github/workflows/validate-ai-surface.yml`. Maps AC-06, AC-07.
- [ ] P1: PH1-S2 — Add `tools/ai/validate-agents.php` (one agent -> one spec) as a thin
  wrapper over `validate-agent-spec.php` + plan-28's composition-coverage guard-test
  pattern. Files: `tools/ai/validate-agents.php`, `tests/php/ValidateAgentsTest.php`,
  CI wire in `.github/workflows/validate-ai-surface.yml`. Maps AC-02.
- [ ] P1: PH1-S4 — Document the generated-header / drift coverage ALREADY delivered by
  plan-28 (assert-already-satisfied, no new tool). Files: `docs/ai/source-of-truth.md`
  spot-check, `docs/ai/generated-artifacts.md`. Maps AC-01, AC-03, AC-04.

## Acceptance Criteria

- [ ] AC-01: plan-28 generated-header coverage documented as already satisfied
  (maps plan-3 AC-01; via PH1-S4).
- [ ] AC-02: `tools/ai/validate-agents.php` enforces one agent -> one spec and is CI-wired
  (maps plan-3 AC-02; via PH1-S2).
- [ ] AC-03: drift coverage documented as already delivered by plan-28
  (maps plan-3 AC-03; via PH1-S4).
- [ ] AC-04: generated-artifact validation documented as already satisfied
  (maps plan-3 AC-04; via PH1-S4).
- [ ] AC-05: implementer permission-profile pilot landed as an additive named projection
  (maps plan-3 AC-05; via PH1-S1).
- [ ] AC-06: `tools/ai/validate-permissions.php` projects hard-deny invariants into a
  CI-wired CLI (maps plan-3 AC-06; via PH1-S3, partial via PH1-S1).
- [ ] AC-07: edit-surface deny checks projected into the validation CLI
  (maps plan-3 AC-07; via PH1-S3, partial via PH1-S1).
- [ ] AC-D1: Human decision D1 resolved before ANY implementation begins.
- [ ] AC-BYTE: PH1-S1 shipped OpenCode block is byte-identical
  (`php tools/ai/generate-agent-permissions.php --check` exits 0).
- [ ] AC-NO-CONTRACTS: No `packages/ai-contracts/` or `packages/ai-universal-rules/specs/**`
  is created.
- [ ] AC-NO-REGISTRY: No second registry is introduced.
- [ ] AC-RENDER-UNTOUCHED: `render-agent-permissions.php` is untouched.
- [ ] AC-GREEN: Full `composer test` is green.

## Verification Plan

- AC-05 / AC-BYTE / AC-06 / AC-07 (PH1-S1): `php tools/ai/generate-agent-permissions.php --check`
  exits 0 (byte-identical shipped OpenCode block) AND `php tools/ai/render-adapters.php --check`
  passes.
- AC-02 (PH1-S2): `php tools/ai/validate-agents.php` exits 0; `ValidateAgentsTest` passes;
  CI job in `validate-ai-surface.yml` runs it.
- AC-06 / AC-07 (PH1-S3): `php tools/ai/validate-permissions.php` exits 0;
  `ValidatePermissionsTest` passes; CI job runs it.
- AC-01 / AC-03 / AC-04 (PH1-S4): inspection of `docs/ai/source-of-truth.md` and
  `docs/ai/generated-artifacts.md` confirming the plan-28-delivered coverage is documented
  as satisfied.
- AC-NO-CONTRACTS / AC-NO-REGISTRY / AC-RENDER-UNTOUCHED: `git status --short` / diff review
  confirms no forbidden dirs, no second registry, and `render-agent-permissions.php` unchanged.
- AC-GREEN: `composer test` (or `composer test:fast`) green.
- AC-D1: human ratification of D1 recorded before the first implementation edit.

## Risks And Rollback

- **HIGH risk** (security-posture: generated enforced permission floor) -> **reviewer +
  release-auditor mandatory** before merge.
- Pre-existing working-tree drift: plan-28 notes ~20 files may make the first
  `render-adapters.php --check` fail; reconcile with one `--write` atomically.
- **Rollback:** PH1-S1 is a clean `git revert` (byte-identical shipped block,
  implementer-only).
- **Hard gate:** Do NOT begin implementation until D1 is resolved by a human.

### HUMAN-DECISION items (record all four; D1 gates all downstream implementation)

- **D1 (architecturally load-bearing, GATES ALL IMPLEMENTATION):** Introduce net-new
  `packages/ai-universal-rules/specs/**` YAML SoT layer, OR extend the existing
  composition engine as the SoT? Architect recommendation: **extend existing engine**.
  plan-3 proposed `specs/**`, so a human must ratify killing that net-new layer. This
  decision gates whether `render-agents.php` is even needed (already delivered as
  `render-adapters.php` if we extend).
- **D2:** Are `skill.schema.json` / `command.schema.json` genuinely needed, or is
  `validate-ai-catalog.php` sufficient?
- **D3:** Confirm the implementer pilot as the Phase 1 pilot vs plan-3's implied big-bang
  foundation. Recommendation: **pilot-first**.
- **D4:** Approve routing every permission/floor-touching phase through reviewer +
  release-auditor (HIGH risk).

## Handoff Notes

Recommended next step after persistence and D1 resolution: hand PH1-S1 to the
**implementer**, routed through **reviewer + release-auditor** before merge. Do NOT begin
any Phase 1 implementation until D1 is resolved.

Phase 2 (policy-gate consistency: reconcile `command-policy.tiers.yaml` sh-hook vs composed
hard-deny; AC-08/09/10/11) and Phase 3 (GENUINELY-NEW: `eval-agents.php` +
`tests/agent-evals/**` for AC-12/13, context-pack budget wiring AC-14, `score-handoffs.php`
AC-15, orchestrator scope-gate AC-16/17) are SEPARATE later plans, out of scope for plan-31.

## Archive On Completion

When every `## Todo Plan` item AND every `## Acceptance Criteria` item above is checked
`- [x]`, archive this plan so active and finished plans stay separated:

1. `mkdir -p docs/tickets/claude-agent-fleet-remediation/archive`
2. Write the full plan contents to
   `docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-31-agentspec-reconciliation-phase1.md`
   (apply the `DONE-` prefix).
3. Replace this original file with a one-line tombstone pointing to the archived copy, e.g.
   `Archived: ./archive/DONE-plan-31-agentspec-reconciliation-phase1.md (all Todo items and Acceptance Criteria complete on {timestamp}).`

Only archive when every `- [ ]` in both `## Todo Plan` and `## Acceptance Criteria` is now
`- [x]`. If any item is still unchecked, leave the plan in place.
