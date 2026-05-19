# AI Guardrails

Use this file as the cross-tool control layer for common failure modes.

## Default Rules

- Do not fill gaps in the spec silently. Stop and surface the unknown.
- For multi-step, ambiguous, or architecture-affecting tasks, plan first.
- Require proof, not claims, for completed work.
- Keep context lean and prefer fresh sessions for unrelated tasks.
- Gate destructive or high-impact actions behind explicit approval.
- Treat environment and setup issues as first-class debugging targets.
- Prefer direct quotes, file references, or command output over invented certainty.
- Keep durable repo memory in `AGENTS.md`, `CLAUDE.md`, or project-context files, not in session-only summaries.

## High-Risk Actions

Require approval and rollback notes before:

- deleting or moving many files
- schema or migration changes
- dependency or runtime upgrades with broad impact
- auth, permission, billing, or production config changes
- secrets or environment edits

## Evidence Standard

- Bug fixes should prefer a failing reproduction first when practical.
- Verification reports must name the command or check that produced the claim.
- Summaries must separate executed verification from recommended next checks.

## Drift Signals

Pause and narrow scope when you see:

- unrelated file edits
- repeated reinvestigation of already rejected ideas
- large exploratory output for a narrow task
- generic confidence without concrete evidence
