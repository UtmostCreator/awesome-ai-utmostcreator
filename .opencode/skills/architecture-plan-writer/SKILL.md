---
name: architecture-plan-writer
description: Use when an architect design or a bounded task/ticket must be persisted as a Todo architecture plan markdown file under docs/tickets, strictly scoped to the task with steps, things-to-avoid, and acceptance criteria
argument-hint: 'Provide the architect design or the scoped task/ticket and an optional docs/tickets folder'
---

# Architecture Plan Writer

Persist exactly one bounded architecture plan as a Todo markdown file. This skill adapts the
`architecture-plan-writer` agent: it writes the plan; it does not design or implement.

## What I Do

I turn a completed architect design (or an explicitly scoped task/ticket) into a single plan file
with ordered Todo steps, an explicit things-to-avoid list, and observable acceptance criteria.

## Where I Write

Only markdown under `docs/tickets/`. Default output is one folder per current git branch, with one
file per plan inside it:

```text
docs/tickets/{branch-name}/plan-{n}-{short-desc}.md
```

When one invocation covers multiple tickets or tasks, I write one file per ticket in the same
branch folder, numbering sequentially (`plan-1-...`, `plan-2-...`, `plan-3-...`) — never hardcoded
to `1`.

The user may name a different folder under `docs/tickets/`. A folder outside `docs/tickets/` is a
stop condition — ask first, never write there.

## When To Use Me

- after the architect agent finishes a design and needs it documented as a Todo plan
- when a bounded task or ticket needs a persisted, scope-locked plan before implementation

## Do Not Use Me For

- designing the architecture (use the architect agent)
- implementing the plan (use the implementer agent)
- editing any file outside `docs/tickets/`

## Workflow

1. read the architect handoff or the scoped task/ticket; detect the current git branch and any ticket id
2. derive `{branch-name}` (sanitized current branch), `{short-desc}`, and the next unused `{n}` for each ticket/task
3. if this updates an existing plan file, switch to dedup-first update mode instead of creating a new file (see Gotchas)
4. resolve the target folder `docs/tickets/{branch-name}/`; create it only if it is under `docs/tickets/`
5. write `plan-{n}-{short-desc}.md` with the required sections, including the top completion instruction
6. re-read the file and confirm scope and format

## Required Sections

Context, Problem, Target Outcome, In Scope, Out Of Scope (Things To Avoid), Affected Paths,
Contracts And Boundaries, Todo Plan (`- [ ]` only, grouped P0/P1/P2), Acceptance Criteria
(`- [ ] AC-NN:` observable + testable), Verification Plan, Risks And Rollback, Handoff Notes.

## Gotchas

- never pre-check Todo items or acceptance criteria
- never widen scope beyond the task/ticket; record adjacent ideas only under things-to-avoid
- never bypass the write boundary with redirection, `tee`, `cp`, or interpreters
- when updating an existing plan, read it first and skip items that already exist (checked or
  unchecked) — never re-add a duplicate
- if the computed update produces no actual change, report "no changes needed" instead of
  writing; if the same update repeats right after that, stop instead of retrying (loop guard)
- on completion (every Todo item and AC checked), rename the file to `DONE-plan-{n}-{short-desc}.md`
  and move it into `archive/` under the branch folder
- use `unknown` when the design does not prove a claim

## Update/Expand Loop Guard

Applies to Update Mode and Expand mode alike (mirrors the `architecture-plan-writer` agent):

- read the current file first and prefer bounded replacement (swap content between one
  `## Heading` and the next `## `) over re-emitting or appending the full plan — only emit the
  full document when creating a brand-new plan file
- after each edit, re-read and verify the intended change actually landed
- one attempt = one write/edit operation against the same target plan file in the same run
- stop after 3 failed, blocked, or non-landing attempts on the same target file and report the
  exact blocker; do not retry with rephrased approaches
- never re-append a second full copy of the plan to recover from a blocked/failed edit

## Archive Partial-State Handling

Before writing an archive copy, check state and never guess or recreate blindly: archive exists,
original not yet tombstoned -> tombstone the original only; archive missing, original still
active -> write the archive copy, then tombstone the original; archive exists and original
already tombstoned -> stop, already archived (no-op); archive missing but original already
tombstoned -> stop and report inconsistent state.

## Repo-Local Exception Note

This file is repo-local with no template source under `packages/ai-universal-rules/templates/**`
(constraint-#1 exception, user-approved). It is edited directly, not regenerated, and must be kept
aligned by hand with `packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md`
whenever that template's guard wording changes. See `docs/ai/adapter-contract.md` ("Out-Of-Band
Local Additions") for the broader class of repo-local, non-kit-managed content this belongs to.
