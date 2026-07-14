---
name: bug-regression
description: Use when fixing a bug, adding a regression test, or proving a minimal fix with direct evidence
argument-hint: 'Describe the bug, expected behavior, and where it appears'
---

## What I Do

I drive a minimal bug-fix workflow: reproduce, localize, fix narrowly, verify, and report evidence.

## When To Use Me

- when a user reports broken behavior
- when a fix should start with a regression test or deterministic reproduction
- when the safest path is to keep the change tightly bounded

## Do Not Use Me For

- feature planning, broad refactors, or release-only review

## Read Alongside

- `docs/ai/capabilities/bug-regression/CAPABILITY.md`
- `docs/ai/capabilities/bug-regression/checklist.md`
- `docs/ai/capabilities/bug-regression/gotchas.md`
- `docs/ai/capabilities/bug-regression/examples.md`

## Workflow

1. identify the owning layer
2. add the smallest practical failing reproduction
3. apply the smallest safe fix
4. follow the verification ladder: focused proof first, affected layer tests second, broader repository verification third, build as a smoke check when relevant, release-safety review only when risk warrants it
5. report reproduction, root cause, fix, and evidence

## Modes

- **default** — the full workflow above: reproduce, localize, fix narrowly, verify, report.
- **`reproduce_only`** — stop after proving the failure, when the deliverable is a failing or
  proving regression test for a reported bug or edge case, without the fix (this replaces the
  former `regression-test` skill):
  1. identify the smallest useful proving surface
  2. add the narrowest practical test or deterministic reproduction
  3. confirm failure or proving behavior when practical
  4. stop before applying a fix unless the request also asks for it

  Do not widen into a broader refactor or feature task; do not weaken assertions to force a pass.

## Gotchas

- do not weaken assertions to force a pass
- do not skip the reproduction step when a focused check is practical
- do not report a recommendation as if it was verified
