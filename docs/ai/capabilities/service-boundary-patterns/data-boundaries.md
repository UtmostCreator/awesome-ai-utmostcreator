# Data Boundaries

Use this guide when a change touches state, fixtures, generated artifacts,
secrets, or data flow between tools.

## Rules

- Identify whether data is source-of-truth, generated, cached, fixture, or
  local evidence.
- Do not commit `docs/ai/generated/` outputs unless the generated-artifact
  policy changes.
- Do not read, print, summarize, or move secrets to prove behavior.
- Keep test fixtures deterministic and scoped to the behavior under test.
- For schema or manifest changes, update validators and focused tests in the
  same slice.
- For destructive data changes, use expand-contract and document rollback.

## Generated And Derived Data

- Prefer changing the generator or canonical input over editing generated
  output.
- If tracked catalogs are generated-but-committed, update the generator source
  and the tracked output together.
- After changing generated-output inputs, run the repository's catalog or
  artifact drift check.

## Evidence To Report

- source-of-truth data file
- derived files that changed or intentionally did not change
- validator or drift check used as proof
- remaining unknowns about data ownership
