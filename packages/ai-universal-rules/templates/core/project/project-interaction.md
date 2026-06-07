# <PROJECT_NAME> — AI Interaction Guide

User-owned. Describe how you want AI agents to collaborate on this repository. The kit
installs this once and never overwrites it.

## Collaboration defaults

- Preferred change size: <e.g. smallest safe slice; pause beyond ~6 files>
- When to ask vs. proceed: <e.g. ask before schema, auth, billing, or dependency changes>
- Verification expectation: <e.g. run the focused test that proves the behavior>

## Communication

- Explanation depth: <terse diffs / step-by-step rationale>
- What to always report: <failed commands, skipped checks, assumptions>

## Escalation

Escalate (stop and ask) when a change would affect:

- public interfaces or persistence shape
- security posture, secrets, or permissions
- rollout/rollback risk

## Out of scope

List anything the AI should not touch without explicit approval:

- <paths, systems, or workflows>
