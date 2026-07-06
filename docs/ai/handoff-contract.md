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

## Audit-Grade Evidence

These fields raise a handoff from narrative to auditable. Include each one when it
applies; state `N/A` (with a one-line reason) instead of dropping it silently:

- **Change type**: one of `refactor`, `feature`, `fix`, `docs`, `test`, `infra`, `migration`.
- **Risk level**: `low`, `medium`, or `high` (drives reviewer attention and depth).
- **Named failing checks**: name each failing test or check exactly (not "some failures").
  For every failure, state whether it is pre-existing and why it is unrelated.
- **Baseline honesty**: if a pre-change baseline was captured, cite it; if not, write
  "no pre-change baseline captured — cannot prove these failures are pre-existing."
  Never pair "zero regressions" with unnamed failures.
- **Behavioural contract** (behaviour-preserving work): what must stay identical, plus
  `old location -> new location` for moved code and which surface is public vs internal-only.
- **Reviewer focus**: highest-risk areas in priority order, and the exact commands a
  reviewer should run to reproduce the verification.

## Risks And Assumptions

State assumptions that affected the implementation. Use `unknown` where the
repository does not prove a claim. Identify any approval-gated work that was not
performed.

## Review Handoff

When a fresh review is recommended, say: `reviewer means reviewer agent handoff
using OpenCode command: /review-diff`.
