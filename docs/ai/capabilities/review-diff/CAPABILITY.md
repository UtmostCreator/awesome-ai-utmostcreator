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
2. Establish review scope and branch context before judging risk.
3. For branch or PR review, resolve the merge base and prefer `BASE...HEAD` diff views over two-dot ranges.
4. Run cheap diff inventory first: stat, name-status, diff check, directory distribution, and function-context patch views.
5. Check whether risk classification fits the actual change.
6. Inspect unchanged files only when needed to verify a concern.
7. Require duplicate-logic screening before passing review.
8. If overlap is roughly `>=75%`, flag potential reuse or replacement instead of allowing silent duplication.
9. Prioritize correctness, contracts, regressions, and missing tests.
10. Escalate review depth for `medium` and `high` risk changes.

## Verification Expectations

- Review is not a substitute for executed verification.
- Findings should distinguish likely issues from confirmed failures.
- Review should start from the diff and expand only when a concern justifies it.
- Branch/PR findings should cite the base used for review, or report it as `unknown`.

## Output Contract

- verdict
- findings with severity and location
- risk assessment
- duplicate-logic screening result (`pass` | `issue` | `not-applicable`) with evidence
- recommended next step

## Related Capabilities

- `project-context`
- `verify-change`
