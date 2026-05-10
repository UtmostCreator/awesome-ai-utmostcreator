# Governance

This package assumes AI instructions alone are not enough for production work.

## Governance Goals

- keep policy and procedure separate
- limit silent scope expansion
- require evidence for completion claims
- gate destructive or high-impact actions
- make uncertainty legal instead of encouraging guesses

## Required Controls

- exact task contract for non-trivial work
- acceptance criteria before implementation when ambiguity matters
- approval gates for destructive or sensitive changes
- verification evidence for behavior claims
- documented fallback when runtime features are unavailable
- do not rely on repeated permission prompts as the only safety control for high-risk work

## Human Ownership Rule

A human approver must be able to explain each changed section well enough to own the merge.

## Unknown Is Allowed

The workflow should prefer `unknown`, `not verified`, or `needs repo confirmation` over invented certainty.

## See Also

- `HOOKS-AND-ENFORCEMENT.md` — deterministic controls and minimum enforcement posture
- `../workflows/RISK-AND-APPROVALS.md` — risk levels, approval gates, and approval packet
- `../workflows/AGENT-HANDOFFS.md` — staged roles and handoff boundaries
- `../../templates/shared/verification/VERIFICATION-EVIDENCE.template.md` — evidence template
