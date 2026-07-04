# Capabilities

Capabilities are reusable workflow procedures. Load only the capability relevant to
the current task.

## Task-To-Capability Router

| Task signal | Capability |
|---|---|
| unfamiliar area, multiple active paths, or choosing verification depth before editing | `project-context` |
| behavior changed and needs the smallest relevant proof | `verify-change` |
| reviewing a diff for correctness, regression risk, or merge safety | `review-diff` |
| a bug report or regression needs a minimal fix with a proving test | `bug-regression` |
| a request is ambiguous, or work is handing off to another agent or human | `clarification-and-handoff` |
| a change is `medium`/`high` risk and needs rollout/rollback posture | `release-safety` |
| a dependency, library, framework, or runtime is being upgraded | `dependency-upgrade` |
| a non-trivial edit needs scope control and dirty-worktree protection | `evidence-first-execution` |
| the human is learning a skill and a full solution should not be handed over by default | `mentor-mode` |

If more than one row matches, compose in this order: `project-context` first to
ground the task, then the task-specific capability, then `verify-change` before
closing out.

## Adding A New Capability

Each capability is one folder containing a canonical procedure file plus optional
support files for a checklist, gotchas, examples, and reference links (see
`docs/ai/ai-file-standards.md` for the naming convention). Keep the entry file
trigger-oriented; add support files rather than bloating it. Before adding a new
capability, check this router for `>=75%` overlap with an existing one.
