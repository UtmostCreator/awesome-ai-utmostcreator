---
name: prd-and-tasks
description: Use to turn a raw or ambiguous feature idea into a confirmed PRD-style brief and a staged, parent-tasks-first implementation plan
argument-hint: 'Describe the feature or problem in plain language; note any known constraints'
---

## What I Do

I turn a raw feature idea into a clarified PRD-style brief, then persist a staged plan that shows parent tasks first and pauses for confirmation before expanding into subtasks. I do not restate the clarification contract or the plan file format — I reuse them.

## When To Use Me

- when a feature request arrives as a plain-language idea rather than a scoped ticket
- when acceptance criteria are not yet clear enough to plan from directly
- when a multi-project or multi-phase feature needs splitting before implementation starts

## Do Not Use Me For

- bounded single-step changes with an obvious owner and approach (use the implementer agent directly)
- work where the design is already approved and scoped (use `architecture-plan`/`plan-slice` directly)

## Workflow

1. Apply `docs/ai/capabilities/clarification-and-handoff/CAPABILITY.md`: ask at most one clarifying question when acceptance criteria, scope, or a destructive/security/schema impact cannot be inferred; otherwise state the stop-or-assume branch explicitly.
2. When multiple projects or areas are implied, split into one brief per project, starting with the most logical one first — never combine unrelated projects into one brief.
3. Write a short PRD-style brief: problem, goal, in-scope, explicit non-goals, and a first-pass acceptance-criteria list. Keep each implied task as close as possible to a single unit of work verifiable with the user or by focused test coverage.
4. Hand off to the architect agent for boundaries and risk (or state the design yourself when it is trivial).
5. Request `architecture-plan-writer` in **parent-tasks-only mode**: write just the Todo Plan's P0/P1/P2 one-line parent tasks, then pause.
6. Pause for explicit user confirmation on the parent-task list before any subtask expansion.
7. Once confirmed, request `architecture-plan-writer` in **expand mode** on the same plan file to fill the remaining required sections and expand each parent task into implementation-ready subtasks.

## Read Alongside

- `docs/ai/capabilities/clarification-and-handoff/CAPABILITY.md`
- `docs/ai/handoff-contract.md`
- `templates/core/agents/architecture-plan-writer.md` (two-phase mode)

## Output

- clarified PRD-style brief (problem, goal, scope, non-goals, draft acceptance criteria)
- a parent-tasks-only plan file awaiting confirmation
- a confirmed, expanded plan file with full detail once approved
- recommended next step

## Gotchas

- do not skip the parent-tasks pause even when the idea seems obvious
- do not restate the clarification or handoff contract here — reference it
- do not combine multiple projects into one brief; split first
