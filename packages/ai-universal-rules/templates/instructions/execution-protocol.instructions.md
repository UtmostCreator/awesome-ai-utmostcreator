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
