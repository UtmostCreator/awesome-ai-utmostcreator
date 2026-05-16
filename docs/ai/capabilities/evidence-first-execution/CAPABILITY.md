# Evidence-First Execution Capability

## Purpose

Run non-trivial changes with explicit scope control, dirty-worktree protection, and evidence-backed verification.

## When To Use

- source, config, docs, workflow, or policy edits
- diff review with correctness claims
- medium/high risk tasks needing approval boundaries

## Inputs Required

- user objective and boundaries
- current worktree/diff state
- affected paths and contracts
- narrowest verification command

## Execution Protocol

1. classify task mode
2. inspect `git status --short` and relevant `git diff`
3. confirm protected actions and approval posture
4. declare intended scope and smallest change
5. apply minimal patch
6. inspect final diff
7. run focused verification and report evidence

## Stop Conditions

- ownership or contract unclear
- protected action without approval
- scope grows beyond bounded slice

## Output Contract

- changed files
- verification command(s) and results
- verification status
- assumptions and remaining risks
