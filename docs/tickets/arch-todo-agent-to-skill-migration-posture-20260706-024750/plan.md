# Architecture Plan — Agent-to-Skill Migration Posture (DECISION RECORD ONLY)

- Ticket: none
- Source: architect design handoff (Project 5 of 5)
- Generated: 20260706-024750
- Plan folder: docs/tickets/arch-todo-agent-to-skill-migration-posture-20260706-024750/
- Sequence: **Project 5 (LAST)** in a five-plan effort. Execution order across the effort is 1 -> 2 -> 3 -> 4 -> 5. This plan is last because it is decision-gated with NO mechanical work.
- Risk: HIGH
- Mode: **DECISION RECORD ONLY (needs-user-confirmation).** Recommendation: **MIGRATE NONE.**

## Global Constraints

- Edit ONLY shipped template sources under `packages/ai-universal-rules/templates/**` and installer/generator PHP under `tools/ai/install/**`. `.claude/`, `.opencode/`, `.github/`, `AGENTS.md`, `CLAUDE.md` are GENERATED — never hand-edit; fix the template/generator so a re-install regenerates them.
- No constraint-#1 exceptions apply to this plan (it is a decision record with no mechanical file changes).
- Logging is OUT OF SCOPE. Do not touch `docs/tickets/arch-todo-runner-agnostic-logging-core-20260706/**` or any dirty logging file.
- MUST-NOT-TOUCH dirty in-flight files (on main): README.md, docs/ai/script-registry.json, docs/ai/script-registry.md, docs/ai/scripts-reference.md, docs/ai/verification-matrix.md, install-ai-kit.sh, schemas/ai/evidence-event.schema.json, scripts/ai/MANIFEST.md, scripts/ai/ai-verify.sh, scripts/ai/common.sh, scripts/ai/internal/ai-verify/90-run.sh, scripts/ai/internal/lib/30-logging.sh, tests/scripts/ai/test-common.sh, tools/ai/install/script-registry.php, tools/ai/validate-ai-config.php, tools/ai/validate-install-surface.php (dirty), plus untracked logging additions.

## Context

A proposal to migrate some read-only advisory/report agents into skills. This plan evaluates the posture and records a decision; it performs no mechanical migration.

## Problem

- Should any of the read-only advisory/report agents (architect, reviewer, repository-reviewer, researcher, repository-researcher, release-auditor, workflow-auditor, infra-auditor, agent-creator-\* validators) be migrated from agents to skills?

## Target Outcome

- A recorded decision with rationale, marked needs-user-confirmation, that unblocks (or defers) any future migration work as a separately respecified bounded slice.

## In Scope

- Recording the recommendation (migrate none in this effort) and its rationale.
- Noting the weak candidate (agent-creator-\* validators) without acting on it.
- Noting the H stale-data-reporting overlap finding (extend existing sweep, do not duplicate) as note-only.

## Out Of Scope (Things To Avoid)

- Migrating ANY agent without explicit user sign-off.
- Duplicating the touched-scope stale-sweep pattern already in `AGENTS.md` (extend the existing pattern instead).
- Any mechanical code, template, or generator change in this plan.
- Any logging files or the dirty must-not-touch list.

## Affected Paths

- None. This plan is a decision record; no source, template, or generator file is changed.

## Contracts And Boundaries

- Agent vs skill contract (basis for the decision): a skill loads INTO the caller's context (inherits its tools/permissions, pollutes context); an agent runs in a clean sub-session with its own read-only permission floor.
- For review/research/audit work, fresh-context isolation + tool-boundary + staged handoff is the core value; migrating to a skill defeats that purpose.
- The H stale-data-reporting pattern overlaps >=75% with the existing touched-scope stale-sweep in `AGENTS.md`; per the reuse rule, extend the existing sweep guidance rather than add a new pattern.

## Decision Record

- **Candidates considered:** architect, reviewer, repository-reviewer, researcher, repository-researcher, release-auditor, workflow-auditor, infra-auditor, agent-creator-\* validators (all read-only advisory/report agents).
- **Recommendation: keep ALL as agents.** A skill loads into the caller's context (inherits tools/permissions, pollutes context); an agent runs in a clean sub-session with its own read-only permission floor. For review/research/audit, fresh-context isolation + tool-boundary + staged handoff is the core value — migrating defeats the purpose.
- **Only weak candidate:** agent-creator-\* validators (deterministic in-sequence steps), but the benefit is ambiguous and the supervisor/sub-agent split may be intentional for tool isolation.
- **NET: migrate none in this effort;** defer to a separate bounded slice if the user later wants it.
- **H stale-data reporting (note-only, LOW):** a "report if stale data detected" pattern overlaps >=75% with the existing touched-scope stale-sweep in `AGENTS.md` — recommend EXTENDING the existing sweep guidance, NOT adding a new pattern. Note only; no change here.

## Todo Plan

- [x] P0-1: Record the decision (migrate none) and its rationale in this plan; mark needs-user-confirmation. (No mechanical work.)
- [x] P0-2: Note the H stale-data-reporting overlap finding (extend existing `AGENTS.md` sweep, do not duplicate) as note-only.

## Acceptance Criteria

- [x] AC-01: The decision (migrate none) and rationale are recorded in this plan.
- [x] AC-02: The plan is marked needs-user-confirmation with NO code, template, or generator change made. User confirmed via the "UPDATED ANSWER" / "Copy-paste sign-off" section below: "Decision: MIGRATE NONE in this effort." Confirmed by `git status` showing no source/template/generator change attributable to this plan.
- [x] AC-03: The stale-data-reporting finding is recorded as extend-existing (not duplicate), note-only.
- [ ] AC-04: N/A until/unless the user approves a specific migration (then a new plan/slice is created). No migration approved in this effort.

## Verification Plan

- AC-01 / AC-02 / AC-03: inspect this plan file; confirm `git status` shows no source/template/generator change attributable to this plan (only the plan file itself under `docs/tickets/`).
- AC-04: N/A until/unless the user approves a specific migration (then a new plan/slice is created).

## Risks And Rollback

- Risk: acting on the migration without sign-off would defeat fresh-context isolation and tool boundaries (HIGH). Mitigation: this plan is decision-only and gated behind explicit user confirmation.
- Risk: duplicating the stale-sweep pattern. Mitigation: recorded as extend-existing, note-only.
- Rollback: none needed — no mechanical change is made by this plan.
- Success signal: decision recorded; repository unchanged except for this plan file.

## Handoff Notes

- Recommended next step: obtain explicit user sign-off on the "migrate none" recommendation. If the user later approves a specific migration, respecify it as a separate bounded slice and hand off to the implementer agent using OpenCode command: /implement (implementer means implementer agent handoff).
- Do NOT migrate any agent, and do NOT duplicate the stale-sweep pattern, without explicit sign-off.

## UPDATED ANSWER:

## Decision

**Approve `MIGRATE NONE` for this effort.**

This is the safer architecture posture.

## Assessment

| Area                       |           Decision |  Score |
| -------------------------- | -----------------: | -----: |
| Fresh-context isolation    |     Keep as agents | 95/100 |
| Permission boundary safety |     Keep as agents | 95/100 |
| Context pollution risk     |       Avoid skills | 90/100 |
| Migration benefit          |                Low | 20/100 |
| Overall safety             | Keep all as agents | 94/100 |

## Rationale

Agents are the correct abstraction for:

- `architect`
- `reviewer`
- `repository-reviewer`
- `researcher`
- `repository-researcher`
- `release-auditor`
- `workflow-auditor`
- `infra-auditor`
- `agent-creator-* validators`

Reason: these roles need **isolation**, not convenience. A skill would load into the caller context, inherit the caller’s tool surface, and increase context contamination. For audit/review/research flows, that weakens the main value of the sub-agent model.

## Weak candidate

`agent-creator-* validators` are the only plausible future migration candidate because they may behave like deterministic checklist steps.

But I would still defer them because the current supervisor/sub-agent split may intentionally preserve:

- clean validation context
- restricted tool boundary
- staged reporting
- reproducible handoff

## Stale-data reporting note

Correct posture:

> Extend the existing touched-scope stale-sweep pattern in `AGENTS.md`; do not introduce a duplicate pattern.

## Copy-paste sign-off

```md
User sign-off: approve the decision record as written.

Decision: MIGRATE NONE in this effort.

Do not migrate any advisory, review, research, audit, or agent-creator validator agent to skills. Treat any future migration as a separate bounded slice requiring a new plan, explicit scope, affected paths, acceptance criteria, and verification.

For stale-data reporting, extend the existing touched-scope stale-sweep guidance only if a later bounded slice is approved. Do not duplicate the pattern.
```

Final risk rating: **Low if kept as decision-only; High if implemented mechanically without a separate slice.**
