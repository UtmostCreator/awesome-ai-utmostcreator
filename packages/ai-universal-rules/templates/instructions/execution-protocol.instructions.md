---
applyTo: "**"
description: "Evidence-first task execution, dirty-worktree protection, scope control, and final verification reporting."
---

# Execution Protocol Instructions

For non-trivial work, follow `docs/ai/execution-protocol.md`.

Before edits:

- classify task mode
- run or request `git status --short`
- inspect relevant diffs
- protect pre-existing user changes
- declare intended scope
- avoid broad rewrites

For code changes, final output must include:

- changed files
- verification classification
- checks run
- rollback path (for medium/high risk)
- remaining risks

## Reference Integrity

When changing a function, method, config key, or any symbol used by other code:

- Find and update all call sites and references — not just the declaration.
- Task scope does not exempt call sites of the symbol you changed.
- If the change propagates widely, report the surface and ask before proceeding.

## Test-First Ordering

For bug fixes: verify existing tests pass first, write a failing regression test, apply the fix, re-verify.
For test additions alongside code changes: stash implementation, confirm existing tests pass, apply test changes, confirm expected state.
Ask before changing implementation code if existing tests have not been confirmed passing.
