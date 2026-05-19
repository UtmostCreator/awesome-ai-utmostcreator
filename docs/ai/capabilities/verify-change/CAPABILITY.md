# Verify Change Capability

## Purpose

Choose and run the smallest relevant verification flow for a change, then report evidence clearly.

## Trigger When

- behavior changed
- a bug fix claims to be resolved
- a review needs evidence
- a risky refactor or migration needs confidence

## Do Not Trigger When

- the task is purely exploratory and no behavior claim is being made
- no code or config changed and the request is informational only

## Required Inputs

- project test command
- project build command
- project verification command
- affected areas or likely owner

## Read Next

- `checklist.md` for verification order
- `gotchas.md` for weak-evidence traps
- `examples.md` for reporting format

## Workflow

1. Start with focused proof for the changed behavior.
2. Run affected layer or package tests next if the change crosses that boundary.
3. Escalate to broader repository verification only as needed.
4. Use the build as a smoke check when relevant, not as the first proof of behavior.
5. Add release-safety review only when the risk profile warrants it.
6. Report exactly what ran and what each result proves.

## Verification Expectations

- Build success is a smoke check unless the project defines otherwise.
- Do not claim behavior correctness from static analysis alone.
- Separate executed evidence from unrun recommendations.
- Do not skip straight to a broad build if a focused proof is available.
- If environment or setup failures block verification, report that separately from code correctness.

## Output Contract

- command selection rationale
- commands run
- results
- remaining verification not run

## Related Capabilities

- `project-context`
- `review-diff`
- `bug-regression`
