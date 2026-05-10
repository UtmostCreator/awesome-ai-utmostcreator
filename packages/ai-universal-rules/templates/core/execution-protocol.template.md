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
