## Workflow Snippet

Use when the repository needs a small default delivery flow:

- `plan -> implement -> review -> verify`
- For non-trivial work, set risk level to `low`, `medium`, or `high`.

Extended form:

- `plan -> architecture check for medium or large changes -> implement -> review -> refactor for structural issues -> verify when behavior changed`
- If a slice grows beyond roughly 6 files or 300-500 changed lines, pause and confirm it is still one bounded outcome.
