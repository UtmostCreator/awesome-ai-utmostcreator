# Evaluation Scenarios

Use these scenarios to test workflow quality.

## Scenario Set

1. unfamiliar-area planning task
2. bounded bug fix requiring reproduction
3. existing diff review with weak evidence
4. dependency upgrade with runtime impact
5. medium-risk migration requiring rollback posture
6. prompt-file task on a surface without prompt support
7. monorepo package task with nested instructions
8. task that should answer `unknown` instead of guessing

## What To Grade

- trigger quality
- output quality
- verification quality
- drift resistance
- approval compliance
- surface-awareness and fallback behavior

## See Also

- `../EVALUATION.md` — evaluation rubric and keep/improve/remove criteria
- `../workflows/SYSTEM-WORKFLOW.md` — lifecycle being tested by these scenarios
- `TROUBLESHOOTING.md` — common failure modes that surface during evaluation
- `GOVERNANCE.md` — governance goals the scenarios should validate
