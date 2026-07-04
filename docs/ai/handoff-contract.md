# Handoff Contract

Handoffs let the next human or agent continue without guessing.

## Minimum Preserved Payload

Every handoff — between agents, between sessions, or in a completion report to a
human — must carry this minimum payload. Omitting a section is only acceptable when
it is explicitly not applicable, and that must be stated rather than silently dropped:

- **Scope**: what was and was not touched, in file or area terms.
- **Clarified constraints**: any constraint the user confirmed or corrected during the task.
- **Assumptions**: what was assumed because evidence was missing; mark with `unknown`.
- **Acceptance criteria**: what "done" means for this slice, as stated or inferred.
- **Likely files**: the files most likely to need follow-up or review.
- **Verification expectation**: what proof was expected versus what was actually run.
- **Unresolved questions**: open questions the next agent or human must resolve.
- **Recommended next step**: the single next action, naming an agent/command when relevant.
- **Downstream-confirmation flag**: whether downstream work (render, deploy, merge) still needs a human check.
- **Remaining work**: an explicit list of what is not yet done, even if empty (state "none").

## Required Content

Handoffs must include scope, changed files, verification run, remaining risks,
and recommended next step. Keep claims evidence-backed.

## Verification

List commands that were actually run separately from commands that are only
recommended. Include failures and skipped checks with reasons.

## Risks And Assumptions

State assumptions that affected the implementation. Use `unknown` where the
repository does not prove a claim. Identify any approval-gated work that was not
performed.

## Review Handoff

When a fresh review is recommended, say: `reviewer means reviewer agent handoff
using OpenCode command: /review-diff`.
