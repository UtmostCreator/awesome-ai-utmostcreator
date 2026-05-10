# Composition Recipes

Use this guide to map a request type to a small capability sequence.

The default rule is: choose the shortest safe sequence and avoid loading unnecessary roles.

## Default Dev Flow

- unclear area -> `researcher` or `project-context`
- multi-step or risky change -> `architect` then `implementer`
- bounded implementation -> `implementer`
- existing diff review -> `reviewer`
- `medium` or `high` risk -> add `release-auditor`
- structure-only cleanup -> optional `refactorer`

## Capability Recipes

### Unfamiliar Area

- primary sequence: `project-context`
- expected output: likely owners, affected paths, risks, and verification surface

### Bounded Bug Fix

- primary sequence: `project-context` -> `bug-regression` -> `verify-change`
- optional add-ons: `review-diff`
- expected output: reproduction, minimal fix, and direct evidence

### Bounded Feature

- primary sequence: `project-context` -> `verify-change`
- optional add-ons: `review-diff`, `release-safety`
- expected output: affected owner, bounded implementation slice, proportional verification

### Review Existing Change

- primary sequence: `project-context` -> `review-diff`
- optional add-ons: `verify-change`
- expected output: findings, risk assessment, and missing verification

### Dependency Upgrade

- primary sequence: `project-context` -> `dependency-upgrade` -> `verify-change`
- optional add-ons: `review-diff`, `release-safety`
- expected output: compatibility risk, verification evidence, rollout notes when needed

### Risky Change Or Rollout-Sensitive Change

- primary sequence: `project-context` -> `review-diff` -> `verify-change` -> `release-safety`
- expected output: release posture, rollback path, success signal, unresolved risk

## Keep Choice Clear

For normal code development, recommend only these agents by default:

- `researcher`
- `architect`
- `implementer`
- `reviewer`

Optional agents:

- `release-auditor`
- `refactorer`

Do not add specialist agents until the core choice model is already obvious to the team.

## See Also

- `workflows/SYSTEM-WORKFLOW.md` — end-to-end lifecycle and task classification table
- `workflows/TASK-ENTRYPOINTS.md` — when to use each mechanism
- `workflows/AGENT-HANDOFFS.md` — staged agent handoff contract
- `foundations/CAPABILITY-MODEL.md` — capability-first reusable workflow model
- `PROJECT-EXAMPLES.md` — scenario-based examples using these recipes
