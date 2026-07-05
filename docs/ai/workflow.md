# Workflow

See `docs/ai/index.md` for a curated, annotated entry point into `docs/ai/**`.

## Default Task Flow

For non-trivial work, follow `docs/ai/execution-protocol.md`.

1. load task context or perform read-only grounding when ownership is unclear
2. route through `project-context` when multiple active paths could own the change
3. use `plan-slice` or a planning agent for multi-step or risky work
4. check approval boundaries before mutation
5. implement the bounded slice
6. verify with the smallest sufficient proof first
7. review the diff in a fresh context
8. use `release-safety` when risk is `medium` or `high`

## Entry Point Examples

- bounded bug fix -> `bug-regression`
- existing diff review -> `review-diff`, then `release-safety` when risk justifies it
- behavior-following docs update -> `docs-sync`

## Verification Ladder

1. focused proof
2. affected-layer verification
3. broader repository verification
4. build or smoke proof when relevant
5. release-safety proof for medium or high-risk work
