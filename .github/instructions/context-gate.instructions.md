---
applyTo: '**'
description: 'Mandatory task-context loading before planning, editing, or reviewing'
---

# Context Gate

Before any agent answers, plans, edits, or reviews, it must load one of:

- `docs/ai/generated/task-context/latest.md`
- output from `php tools/ai/compile-task-context.php`
- output from `php tools/ai/impact.php`

If no task context exists, the agent may only perform read-only research.

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

## Forbidden Behavior

- Do not implement from memory.
- Do not scan the whole repository when a task context compiler exists.
- Do not edit before affected paths and verification surface are known.
- Do not ignore generated-output drift.
