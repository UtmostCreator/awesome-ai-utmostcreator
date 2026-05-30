# Human Review Rules

Use these triggers when agent outcomes need explicit human validation.

## Always Require Human Review

- high-risk mutating actions
- policy or approval-gate changes
- non-reproducible results in medium/high-risk workflows
- changes affecting credentials, auth, billing, or external side effects

## Require Human Review Unless Exempted

- prompt or policy changes that affect tool choice
- changes to failure taxonomy mappings
- changes to regression or golden-task expectations

## Review Record Expectations

- what was reviewed
- risk level
- approval or rejection decision
- required follow-up actions

## Minimal Decision Record

```text
risk: high
scope: approval gate behavior
decision: approved
reviewer: human-maintainer
notes: golden_auth_001 passes; no unapproved high-risk path observed
```
