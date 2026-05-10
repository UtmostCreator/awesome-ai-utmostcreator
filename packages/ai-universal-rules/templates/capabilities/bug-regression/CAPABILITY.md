# Bug Regression Capability

## Purpose

Reproduce a bug with the smallest practical test or deterministic check, apply a minimal fix, and prove the regression is closed.

## Trigger When

- a user reports incorrect behavior
- a bug fix is requested
- a regression needs a stable reproduction before editing

## Do Not Trigger When

- the request is purely exploratory and no bug claim is being made
- the issue is actually a feature request or broad refactor request

## Required Inputs

- bug description
- expected behavior
- where the bug appears
- relevant project context and verification commands

## Read Next

- `gotchas.md` for common bug-fix mistakes
- `examples.md` for regression workflow examples
- `checklist.md` for minimal-fix sequence

## Workflow

1. Identify the owning layer or boundary.
2. Add the smallest practical failing regression test or deterministic reproduction.
3. Confirm the reproduction fails when practical.
4. Apply the smallest safe fix.
5. Follow the verification ladder: focused proof first, affected layer tests second, broader repository verification third, build as a smoke check when relevant, and release-safety review only when risk warrants it.
6. Report reproduction, root cause, fix, and evidence.

## Verification Expectations

- Prefer executable reproduction over narrative-only explanation when practical.
- Keep the fix bounded to the identified fault.
- If the bug cannot be reproduced deterministically, explain what blocks proof and what evidence is still available.

## Output Contract

- reproduction
- root cause
- minimal fix
- evidence

## Related Capabilities

- `project-context`
- `verify-change`
- `review-diff`
