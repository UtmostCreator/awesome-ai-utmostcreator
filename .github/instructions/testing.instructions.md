---
applyTo: "**/*.test.*,**/*.spec.*,**/tests/**,**/test/**,**/__tests__/**"
description: "Testing, verification ladder, regression coverage, and deterministic proof rules"
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

- Use `composer test (composer test:fast for parallel)` as the baseline test command unless a narrower command is more appropriate.
- Prefer deterministic local commands over broad slow commands when proving a small change.
- If no reliable command exists, state that clearly and provide the closest available proof.

## Regression Rules

- Add regression coverage when fixing bugs.
- Reproduce the bug first when practical.
- The regression test must fail before the fix is applied.
- Do not weaken assertions to make a change pass.
- Do not delete coverage unless the behavior is intentionally removed and approved.

## Test-First Bug Fix Protocol

When fixing a bug, follow this exact order:

1. **Verify baseline**: run existing tests for the affected area and confirm they pass on the current code without any of your changes. If tests fail before your changes, stop and report.
2. **Write the regression test**: add a test that reproduces the bug. This test must fail on the current unfixed code.
3. **Apply the fix**: make the smallest code change that makes the regression test pass.
4. **Re-verify**: run the full affected test suite and confirm no regressions.

Ask before changing implementation code if existing tests have not been confirmed passing first.

## Test Addition Protocol

When adding or modifying tests alongside code changes:

1. **Stash your code changes** (`git stash` or equivalent isolation).
2. **Run the tests you plan to modify** and confirm they pass in their current form on the unchanged code.
3. **Unstash** and apply your new or modified tests on top.
4. **Confirm new tests fail** against the unfixed code (for bug fixes) or pass against the new behavior (for features).

This prevents conflating test changes with implementation changes.

## Reference Integrity

When changing a function signature, method contract, config key, class name, or any symbol used by other code:

- Find and update all call sites, references, imports, and usages — not just the declaration.
- Staying within the discussed task scope does not exempt call sites of the symbol you changed.
- If a rename or signature change propagates widely, report the affected surface and ask before proceeding.
- Use symbol-usage search before changing any shared interface.

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
