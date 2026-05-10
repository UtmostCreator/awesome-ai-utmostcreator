# Project Context Capability

## Purpose

Provide durable repository context that other capabilities, agents, and prompts can rely on before planning, implementing, reviewing, or verifying changes.

## Trigger When

- the task touches an unfamiliar area
- the repository has multiple active paths or stacks
- verification or ownership choices depend on project structure
- another capability needs repository facts before acting

## Do Not Trigger When

- the request is a purely general programming question with no repository context
- the task is trivial and the relevant file owner is already obvious from local context

## Required Inputs

- project summary
- active and inactive paths
- primary entrypoints
- main verification commands
- approval boundaries

## Read Next

- `reference.md` for durable facts
- `gotchas.md` for common misreads
- `examples.md` for how other capabilities should use project context

## Workflow

1. Read durable repository facts.
2. Identify active paths and likely owners.
3. Surface approval, verification, and risk boundaries that matter for the task.
4. Hand off to implementation, review, or verification workflows with that context.

## Verification Expectations

- Do not infer stack details that are not in the context file.
- Prefer active repository truth over stale planning notes.
- Surface unknowns explicitly instead of inventing repository facts.

## Output Contract

- affected areas
- risk-sensitive notes
- likely verification path
- approval or rollout constraints when relevant

## Related Capabilities

- `verify-change`
- `review-diff`
- `bug-regression`
- `release-safety`
