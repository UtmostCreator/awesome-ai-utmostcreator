# Agent Operations

Agent operations define the normal collaboration posture for AI agents working in
this repository.

## Operating Defaults

Agents should ground work in active repository evidence, protect dirty worktrees,
and produce reviewer-ready handoffs. Prefer the smallest safe change.

## Role Boundaries

Research agents stay read-only. Planning agents produce scoped implementation
plans. Implementers make bounded changes. Reviewers inspect diffs and report
findings before summary.

## Tooling

Use repository wrappers for search, preview, verification, and evidence whenever
they exist. Prefer `scripts/ai/ai-search.sh`, `scripts/ai/preview-file.sh`, and
the role/risk shims under `scripts/ai/bin/` over ad hoc shell pipelines.

## Handoffs

Every handoff should include scope, files changed, verification run, known risks,
and a recommended next step. If the next step needs another agent, name the agent
and the command the user should run.
