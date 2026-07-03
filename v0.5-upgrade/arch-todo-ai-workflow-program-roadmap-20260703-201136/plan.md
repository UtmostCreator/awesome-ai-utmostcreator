# Architecture Plan — AI Workflow Program Roadmap

- Ticket: none
- Source: task description
- Generated: 20260703-201136
- Plan folder: v0.5-upgrade/arch-todo-ai-workflow-program-roadmap-20260703-201136/

## Context

This repository now has three bounded architecture plans that address different parts of the same AI workflow system:

- `v0.5-upgrade/arch-todo-repo-clarity-and-surface-consolidation-20260703-192142/plan.md`
- `v0.5-upgrade/arch-todo-template-surface-thinning-and-critical-coverage-20260703-193900/plan.md`
- `v0.5-upgrade/arch-todo-clarification-prd-and-handoff-architecture-20260703-195541/plan.md`

Those plans are individually coherent, but they now need one program-level execution order so maintainers know what to do first, what depends on what, and how to avoid rework or semantic drift.

Current evidence shows three distinct problem classes:

- repo-level navigation, ownership clarity, and maintainer routing
- package-template surface sprawl, coverage guarantees, and dead-file risk
- missing canonical workflow primitives for clarification, PRD generation, task generation, and handoff

The highest-value architectural need now is not more planning depth. It is one dependency-aware roadmap that sequences the existing plans so critical guidance is preserved while clarity and maintainability improve.

## Problem

Without a program-level roadmap, the repository risks implementing the right ideas in the wrong order.

That creates five program risks:

1. adapter or template thinning could happen before canonical routing and ownership are clear
2. clarification and handoff workflows could be designed before the runtime coverage and reachability model is explicit
3. maintainers could touch generated or lower-authority surfaces before fixing source-of-truth navigation
4. validation and review improvements could land too late to protect earlier structural changes
5. the three plans could drift apart and duplicate work instead of forming one coherent architecture program

## Target Outcome

The repository should have one bounded program roadmap that sequences the existing architecture tracks in the safest and highest-leverage order.

After this roadmap is completed:

- maintainers know which plan to execute first and why
- each architecture track has explicit dependencies and safe entry criteria
- critical topics are protected before thinning or splitting starts
- clarification and handoff workflow work is introduced at the correct point instead of too early or too late
- validation, reachability, and drift checks are staged early enough to protect later work

## In Scope

- Program-level ordering across the three existing architecture plans
- Dependencies between clarity, template thinning, and clarification/handoff work
- Definition of safe entry and exit criteria for each program phase
- Program-level validation posture and review gates
- Identification of what must be finished before later phases begin

## Out Of Scope (Things To Avoid)

- [ ] Do not duplicate the full contents of the three existing plans.
- [ ] Do not expand into implementation details beyond what is necessary to order the program.
- [ ] Do not introduce new runtime surfaces or new major workstreams beyond the three already planned.
- [ ] Do not rewrite existing plan scopes unless a direct contradiction is discovered.
- [ ] Do not edit unrelated working-tree changes in `AGENTS.md`, `CLAUDE.md`, or `.claude/`.

## Affected Paths

Primary roadmap inputs:

- `v0.5-upgrade/arch-todo-repo-clarity-and-surface-consolidation-20260703-192142/plan.md`
- `v0.5-upgrade/arch-todo-template-surface-thinning-and-critical-coverage-20260703-193900/plan.md`
- `v0.5-upgrade/arch-todo-clarification-prd-and-handoff-architecture-20260703-195541/plan.md`

Program-governing canonical surfaces:

- `docs/ai/source-of-truth.md`
- `docs/ai/adapter-contract.md`
- `docs/ai/validation.md`
- `docs/ai/ai-file-standards.md`
- `docs/ai/handoff-contract.md`
- `docs/ai/installed-files.md`

## Contracts And Boundaries

- Track A owns repo clarity, source-vs-target clarity, maintainer routing, and capability discoverability.
- Track B owns shipped package-template surface thinning, critical-topic coverage, reachability, and dead-file prevention.
- Track C owns canonical clarification, PRD, task-generation, staged checkpoint, and handoff workflow semantics.
- Track A must establish human and ownership clarity before Track B performs meaningful thinning.
- Track B must establish coverage and reachability discipline before Track C’s richer workflow family is propagated broadly across runtime surfaces.
- Track C may define canonical clarification and handoff contracts early, but runtime-specific wrapper rollout must respect Track B’s coverage model.

## Todo Plan

- [ ] P0: Phase 1 — execute the early slices of Track A first so the repository gains a clear canonical control entrypoint, source-kit versus installed-target clarity, and maintainer routing before any broad template or adapter thinning begins.
- [ ] P0: Phase 1 exit gate — do not start structural thinning until maintainers can identify the correct owner file, source layer, and validator path through one short lookup flow.
- [ ] P0: Phase 2 — start the early slices of Track B immediately after Phase 1 by defining the critical-topic coverage matrix and classifying package-template surfaces into always-on, deterministic-load, optional, and generated/reference roles.
- [ ] P0: Phase 2A — include the compact behavioral baseline and its cross-surface shared-source strategy in the earliest Track B coverage work, because it is now a cross-cutting prerequisite for shipped surface simplification.
- [ ] P0: Phase 2 exit gate — do not thin Copilot or OpenCode baselines until critical-topic coverage and workflow-primitive coverage are explicit enough to review.
- [ ] P0: Phase 3 — start the canonical contract work from Track C once Phase 2 coverage modeling exists: define the clarification contract first, then the minimum preserved handoff payload, then the shared PRD/task workflow family.
- [ ] P0: Phase 3A — include multiple-interpretation handling, simpler-path pushback, and staged approval checkpoints in the early Track C canonical contract work so later wrappers inherit those semantics instead of recreating them ad hoc.
- [ ] P0: Phase 3 exit gate — do not roll out runtime-specific clarification or handoff wrappers until the canonical contract and preserved payload are explicit and reviewed.
- [ ] P1: Phase 4 — return to Track B runtime-surface thinning once Track C has defined the clarification and handoff primitives that must also survive surface-specific packaging.
- [ ] P1: Phase 4 exit gate — any runtime-surface thinning must prove both critical-topic coverage and clarification/handoff workflow-primitive coverage after the change.
- [ ] P1: Phase 5 — complete the remaining Track A clarity and maintainer-facing routing work so docs, matrices, and validator guidance reflect the new surface structure and workflow family accurately.
- [ ] P1: Phase 6 — complete the remaining Track C runtime-specific wrappers, fallback rules, staged checkpoints, and dead-surface protections only after Track B’s reachability and coverage discipline is in place.
- [ ] P2: Phase 7 — finish long-term maintenance-loop documentation across Tracks A, B, and C so future work follows one stable sequence: change canonical source, verify coverage/reachability, then propagate surface-specific structure.
- [ ] P2: Phase 8 — run final program review to confirm that the three tracks now reinforce each other instead of overlapping ambiguously.

## Acceptance Criteria

- [ ] AC-01: The three existing architecture plans are ordered into a dependency-aware program sequence with clear rationale.
- [ ] AC-02: Track A is explicitly recognized as the first prerequisite for broad maintainability work.
- [ ] AC-03: Track B cannot thin runtime surfaces until coverage and reachability gates are in place.
- [ ] AC-04: Track C defines canonical clarification and handoff semantics before runtime-specific wrappers are expanded.
- [ ] AC-04A: The roadmap treats the compact behavioral baseline and the clarification/pushback semantics as early prerequisites rather than late polish.
- [ ] AC-05: The roadmap defines explicit stop gates that prevent later phases from weakening critical guidance or creating dead surfaces.
- [ ] AC-06: Maintainers can follow the roadmap without guessing which plan to open first or what must already be true before proceeding.

## Verification Plan

- Review the roadmap against the three existing plan files to confirm no dependency contradiction was introduced.
- Check the ordering against `docs/ai/source-of-truth.md`, `docs/ai/adapter-contract.md`, and `docs/ai/validation.md` to ensure earlier phases really establish prerequisites for later ones.
- Confirm the roadmap does not widen scope beyond the three existing plans.
- Use this roadmap only as a program-level guide; all implementation verification remains owned by the underlying plans.

## Risks And Rollback

Risk level: medium.

Main risks:

- A program roadmap can become too abstract if it does not preserve the concrete exit gates from each plan.
- Track boundaries may still need refinement once implementation starts and deeper overlap is discovered.
- If Track B or Track C reveals hidden prerequisites not captured here, the roadmap will need a bounded update.

Rollback posture:

- If the roadmap sequence proves wrong during implementation, update this roadmap in place rather than broadening one of the underlying plans silently.
- Keep implementation scoped to the underlying bounded plans even if the roadmap is later revised.

## Handoff Notes

Recommended execution order:

1. `arch-todo-repo-clarity-and-surface-consolidation-20260703-192142`
2. Early coverage modeling from `arch-todo-template-surface-thinning-and-critical-coverage-20260703-193900`
3. Early canonical contract work from `arch-todo-clarification-prd-and-handoff-architecture-20260703-195541`
4. Return to template thinning
5. Return to runtime-specific clarification and handoff wrappers
6. Finish maintenance-loop and final review work across all three tracks

Recommended next stage:

implementer means implementer agent handoff using OpenCode command: /implement
