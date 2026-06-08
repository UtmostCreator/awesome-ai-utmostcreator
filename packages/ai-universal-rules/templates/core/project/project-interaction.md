# <PROJECT_NAME> — AI Interaction Guide

User-owned. Describe how you want AI agents to collaborate on this repository. The kit
installs this once and never overwrites it.

## Collaboration defaults

- Preferred change size: <e.g. smallest safe slice; pause beyond ~6 files>
- When to ask vs. proceed: <e.g. ask before schema, auth, billing, or dependency changes>
- Verification expectation: <e.g. run the focused test that proves the behavior>

## External project interaction map

List external repositories, sibling directories, generated-artifact sources, shared scripts,
upstream/downstream packages, or tool configuration layers that agents may need to inspect.

| External project/system | Direction | Local entrypoint | External path/reference | Contract |
|---|---:|---|---|---|
| <example: shared-configs> | inbound/outbound | <local file or command> | <../shared-configs> | <read-only contract summary> |

Read-only inspection of projects named here is allowed when relevant to the current task, subject to
the OpenCode `external_directory: ask` prompt and secret/sensitive-file rules. External edits require
separate explicit user approval naming the external path and intended change.

## Communication

- Explanation depth: <terse diffs / step-by-step rationale>
- What to always report: <failed commands, skipped checks, assumptions>

## Escalation

Escalate (stop and ask) when a change would affect:

- public interfaces or persistence shape
- security posture, secrets, or permissions
- rollout/rollback risk
- files outside this project unless the user explicitly authorized that external edit

## Out of scope

List anything the AI should not touch without explicit approval:

- <paths, systems, or workflows>
