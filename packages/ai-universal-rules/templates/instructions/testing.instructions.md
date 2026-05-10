---
applyTo: '<TEST_PATH_GLOB>'
description: 'Testing, verification ladder, regression coverage, and deterministic proof rules'
---

# Testing Rules

Apply these rules to test files, test fixtures, test utilities, CI checks, and implementation tasks that require verification.

## Required Verification Ladder

Use the smallest sufficient proof first, then widen only when risk requires it.

1. Focused proof
   - one test file
   - one targeted command
   - one failing or passing reproduction
2. Affected-layer proof
   - related package or module tests
   - relevant frontend, backend, or API layer
3. Broader repository proof
   - full test suite or major affected suite
4. Build or smoke proof
   - build, lint, typecheck, route smoke, installer dry-run
5. Release-safety proof
   - only for medium or high-risk changes

## Baseline Command

- Use `<PRIMARY_TEST_COMMAND>` as the baseline test command unless a narrower command is more appropriate.
- Prefer deterministic local commands over broad slow commands when proving a small change.
- If no reliable command exists, state that clearly and provide the closest available proof.

## Regression Rules

- Add regression coverage when fixing bugs.
- Reproduce the bug first when practical.
- The regression test should fail before the fix when feasible.
- Do not weaken assertions to make a change pass.
- Do not delete coverage unless the behavior is intentionally removed and approved.

## Test Design

- Prefer the lowest test level that proves the behavior.
- Test behavior, contract, and edge cases before implementation details.
- Keep tests deterministic where practical.
- Avoid time, network, random, filesystem, and environment dependency unless isolated.
- Use existing fixtures, factories, and helpers before creating new ones.
- Do not duplicate large fixtures when a minimal fixture proves the case.

## AI-Specific Verification

For AI tooling, agents, instructions, generated docs, or installers, verify:

- schema validity
- generated-output drift
- dry-run or install transaction safety
- rollback or backup behavior
- adapter parity where relevant
- evidence log output

Likely commands:

- `php tools/ai/validate-ai-config.php`
- `php tools/ai/validate-ai-catalog.php`
- `php tools/ai/generate-ai-catalog.php --check`
- `php tools/ai/verify-full-install.php`
- `php tools/ai/ai.php install --profile <profile> --dry-run`

## Failure Handling

- If tests fail unexpectedly, stop and report:
  - command
  - failure summary
  - suspected cause
  - whether failure is related to the change
- Do not continue broad edits after unexplained failures.
- Do not claim verification passed without command output or clear evidence.

## Output Requirement

For every implementation or review, include:

- commands run
- result: pass / fail / not run
- reason for selected test level
- remaining verification gaps
