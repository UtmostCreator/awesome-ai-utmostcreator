---
applyTo: '**'
description: 'Mandatory task-context loading before planning, editing, or reviewing'
---

# Context Gate

Before any agent answers, plans, edits, or reviews, it must establish task context from one of:

- `docs/ai/generated/task-context/latest.md` (optional, ephemeral generator output read only if present)
- read-only discovery via `scripts/ai/ai-search.sh` (`changed`, then `staged`, then `tracked`) plus `git status --short` and `git diff`

If no task context exists, the agent may only perform read-only research.

Write durable plans and task context to the committed `docs/tickets/` location, never under the gitignored `docs/ai/generated/`.

## Minimum Task Context Must Contain

- user task
- affected files
- affected symbols
- entrypoints
- owners
- tests
- risk level
- generated artifacts
- approval boundaries
- target or platform impact
- verification commands

## Scope And Ticket Discipline

These rules apply to every agent and AI surface, on every task.

### Establish Scope Source

Establish the task scope in this order, stopping at the first that gives enough context:

1. the user's task description
2. the current working change: `git status --short` and `git diff` (or `scripts/ai/ai-diff-context.sh` for richer context)
3. if the branch name matches a ticket id (`[A-Z]+-[0-9]+`, e.g. `DEV-1234`), look up that id in `docs/tickets/` first, then any other configured ticket paths
4. if no ticket text was provided and none is found, ask the user for the ticket description
5. if scope or ownership is still unclear, ask one clarifying question and stay read-only until answered

### Flag Out-Of-Scope Changes

- Flag any change that looks outside the stated task or ticket scope, whether it is a change you would make or a change already present in the working tree. State the file, why it looks out of scope, and pause for confirmation before keeping or extending it.

### Sweep For Stale Or Out-Of-Scope Data

- When making a new change, review data and code already present in the touched area. If existing content now falls outside scope or contradicts the change, suggest removing it. Do not delete it silently; deletion stays approval-gated.

## Forbidden Behavior

- Do not implement from memory.
- Do not scan the whole repository when a task context compiler exists.
- Do not edit before affected paths and verification surface are known.
- Do not ignore generated-output drift.
- Do not proceed past unclear scope or a missing ticket without asking.
- Do not silently widen scope or keep out-of-scope changes without flagging them.
