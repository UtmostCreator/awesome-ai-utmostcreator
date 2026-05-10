# Agent Handoffs

Use staged agents to reduce context pollution and scope drift.

## Default Roles

- `researcher`: read-only discovery and repo grounding
- `planner` or `architect`: bounded slice, risk level, acceptance criteria
- `implementer`: code and config changes plus focused verification
- `reviewer`: fresh-context audit of diff, risk, and missing proof
- `release-auditor`: rollout, rollback, and observability review for risky work

## Handoff Contract

Each stage should pass only:

- task goal
- non-goals
- risk level
- active paths
- chosen capability or entry point
- acceptance criteria
- verification already run
- unresolved questions

Do not dump full session history into every handoff.

## Recommended Boundaries

- researcher: no edits
- planner: no edits unless planning artifacts are the output
- implementer: edits and narrow verification allowed
- reviewer: no edits
- release auditor: no edits unless explicitly asked to produce rollout docs

## When To Skip Stages

- trivial task with obvious owner: skip straight to implementer
- existing diff review: skip planner and start with reviewer
- architecture-only question: stop at researcher or planner
- local low-risk refactor: implementer then reviewer if needed

## See Also

- `SYSTEM-WORKFLOW.md` — end-to-end lifecycle and task classification
- `RISK-AND-APPROVALS.md` — risk levels and approval gates
- `TASK-ENTRYPOINTS.md` — when to use each mechanism
- `../foundations/COMPATIBILITY.md` — surface differences between runtimes
