# Review Diff Capability

## Purpose

Review a change set from the diff first, then expand only as needed to assess correctness, regression risk, policy fit, and missing verification.

## Trigger When

- reviewing a proposed implementation
- checking whether a slice is safe to merge
- validating that risk and verification depth match the change

## Do Not Trigger When

- there is no diff or proposed change set
- the request is purely about architecture with no implementation yet

## Required Inputs

- current diff
- project review priorities
- project risk model
- relevant verification evidence if already available

## Read Next

- `gotchas.md` for review anti-patterns
- `checklist.md` for ordered review depth
- `examples.md` for output shape

## Workflow

1. Start from the diff.
2. Check whether risk classification fits the actual change.
3. Inspect unchanged files only when needed to verify a concern.
4. Require duplicate-logic screening before passing review.
5. If overlap is roughly `>=75%`, flag potential reuse or replacement instead of allowing silent duplication.
6. Prioritize correctness, contracts, regressions, and missing tests.
7. Escalate review depth for `medium` and `high` risk changes.

## Verification Expectations

- Review is not a substitute for executed verification.
- Findings should distinguish likely issues from confirmed failures.
- Review should start from the diff and expand only when a concern justifies it.

## Output Contract

- verdict
- findings with severity and location
- risk assessment
- duplicate-logic screening result (`pass` | `issue` | `not-applicable`) with evidence
- recommended next step

## Related Capabilities

- `project-context`
- `verify-change`
