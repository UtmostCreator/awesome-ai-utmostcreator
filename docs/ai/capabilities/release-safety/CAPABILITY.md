# Release Safety Capability

## Purpose

Assess rollout, rollback, observability, and compatibility posture for changes whose risk extends beyond local correctness.

## Trigger When

- a change is classified as `medium` or `high` risk
- a contract, migration, deployment path, or rollout strategy changed
- a reviewer or planner needs release-specific safeguards

## Do Not Trigger When

- the change is small, local, and has no meaningful rollout or compatibility implications
- the request is purely about local refactoring with no behavior or release impact

## Required Inputs

- risk level
- affected contracts or deployment surfaces
- rollback path if known
- observability or smoke-check expectations if known

## Read Next

- `checklist.md` for release questions
- `gotchas.md` for rollout anti-patterns
- `examples.md` for reporting format

## Workflow

1. Confirm why the change needs release-safety review.
2. Identify rollout, rollback, and compatibility concerns.
3. Check for a concrete post-release success signal.
4. Flag missing safeguards before implementation or merge.
5. Use an approval packet when the change is destructive, production-facing, or otherwise high impact.

## Verification Expectations

- Local tests do not replace rollout planning for risky changes.
- If rollback is impossible, that should be stated explicitly.

## Output Contract

- rollout considerations
- rollback posture
- observability or smoke-check signal
- unresolved release risks

## Related Capabilities

- `project-context`
- `review-diff`
- `verify-change`
