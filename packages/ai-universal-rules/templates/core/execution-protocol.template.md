# Execution Protocol

Use this as the canonical operating contract for non-trivial AI-assisted planning, editing, review, and verification.

## Prime Directive

Prefer the smallest safe change that is easy for humans to read, maintain, and modify.

## Required Sequence

1. classify task mode
2. inspect `git status --short` and relevant `git diff`
3. confirm approval boundaries for protected actions
4. declare intended scope and apply minimal patch
5. inspect final diff
6. run focused verification and report evidence honestly

## Verification Statuses

- `verified`
- `partially-verified`
- `not-verified`
- `failed-verification`

## Related Docs

- `docs/ai/source-of-truth.md`
- `docs/ai/approval-boundaries.md`
- `docs/ai/verification-matrix.md`
- `docs/ai/failure-handling.md`
- `docs/ai/session-reentry.md`

## Reference Integrity

When changing a function, method, config key, or any named symbol:

- All references must be updated in the same change.
- Task scope boundaries do not exempt call sites of the changed symbol.
- Search for usages before changing shared interfaces.
- If the change propagates widely, report the affected surface and ask before proceeding.

## Test-First Ordering

For bug fixes:

1. verify existing tests pass without your changes
2. write a failing regression test that reproduces the bug
3. apply the minimal fix
4. verify all affected tests pass

For test additions alongside code changes:

1. stash implementation changes
2. confirm existing tests you plan to modify pass unchanged
3. apply test changes
4. confirm expected pass or fail state

Ask before changing implementation code if existing tests have not been confirmed passing first.
