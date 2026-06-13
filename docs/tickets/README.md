# Tickets

Drop per-ticket context here so agents can resolve scope from a branch name.

## Convention

- One file per ticket id, named after the id: `DEV-1234.md`.
- The id matches the pattern `[A-Z]+-[0-9]+` (for example `DEV-1234`, `JIRA-42`, `ABC-7`).
- When a branch name contains a ticket id, agents look the id up here first to establish task scope. See `.github/instructions/context-gate.instructions.md`.
- Durable AI-generated plans live here too, in per-plan folders: `docs/tickets/arch-todo-{slug}-{timestamp}/plan.md`. This keeps generated plans committed instead of in the gitignored `docs/ai/generated/`.

If no ticket file exists for a referenced id, the agent asks for the ticket description instead of guessing scope.

## Suggested ticket file shape

```md
# DEV-1234 — short title

## Goal

What outcome this ticket delivers.

## Scope

- in scope: ...
- out of scope: ...

## Acceptance criteria

- ...
```
