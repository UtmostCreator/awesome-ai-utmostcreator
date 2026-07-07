# Architecture Plan — Refactorer Agent Critic Fixes

- Ticket: none
- Source: task description
- Generated: 2026-07-07T17:21:34Z
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-1-refactorer-agent-critic-fixes.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-1-refactorer-agent-critic-fixes.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-1-refactorer-agent-critic-fixes.md`). See "Archive On Completion" below for the exact steps.

## Context

The refactorer agent has agent-critic blockers related to overly broad permission grants and a reported capability-route issue. Source-of-truth evidence cited by the task says shipped agent permission blocks and template permission blocks are generated from `tools/ai/install/permission-layers/` using `php tools/ai/generate-agent-permissions.php --write`, so generated adapter permission blocks are not the durable source for fixes.

## Problem

Rendered refactorer permissions currently allow broad command patterns that agent-critic flags as blockers: `git branch*` and `php tools/ai/validate-*.php *`. The critic also reported `authorization-and-tool-governance`, but task evidence says `docs/ai/capabilities/authorization-and-tool-governance/CAPABILITY.md` exists, so removal is not justified without stronger evidence.

## Target Outcome

Refactorer permission/capability assessment passes without blocker/high findings for broad `git branch*`, broad `php tools/ai/validate-*.php *`, or missing `authorization-and-tool-governance`, while durable permission source changes remain scoped to refactorer and affected generated refactorer surfaces.

## In Scope

- Refactorer-agent permission source changes under `tools/ai/install/permission-layers/**`.
- Required refactorer template/source updates, if permission generation requires them.
- Regeneration or checking of affected refactorer managed outputs only: `.opencode/agents/refactorer.md`, `packages/ai-universal-rules/templates/core/agents/refactorer.md`, `.github/agents/refactorer.agent.md`, and `.claude/agents/refactorer.md` when the existing workflow requires adapter renders.
- Preservation of already-present unrelated working-tree changes in affected refactorer surfaces.
- Validation of the existing `authorization-and-tool-governance` route by evidence before any action on that finding.

## Out Of Scope (Things To Avoid)

- Do not hand-edit generated adapter permission blocks as the durable fix.
- Do not delete or remove `authorization-and-tool-governance` unless a validator or direct file read proves a real absence or path issue.
- Do not globally narrow `git branch*` or `php tools/ai/validate-*.php *` for all agents without a separate fleet-level assessment.
- Do not add destructive git commands to refactorer permissions.
- Do not include wildcard validator grants that can match future mutation-capable validators.
- Do not include `validate-generated-artifacts.php --write` or `validate-generated-artifacts.php --fix` surfaces.
- Do not clean, delete, or otherwise modify untracked `graphify-out/**` or unrelated untracked scripts.
- Do not perform broad generated artifact rewrites or broad adapter rerenders beyond affected refactorer surfaces without approval.

## Affected Paths

- `tools/ai/install/permission-layers/compositions.php`
- `tools/ai/install/permission-layers/pack-sets.php`
- `tools/ai/install/permission-layers/**`
- `packages/ai-universal-rules/templates/core/agents/refactorer.md`
- `.opencode/agents/refactorer.md`
- `.github/agents/refactorer.agent.md`
- `.claude/agents/refactorer.md`
- `docs/ai/capabilities/authorization-and-tool-governance/CAPABILITY.md`

## Contracts And Boundaries

- Durable fixes must be made in permission-layer source and required template source, not only in rendered `.opencode/agents/refactorer.md`.
- `docs/ai/source-of-truth.md:58-62` is cited as the source-of-truth evidence for generated agent permission blocks.
- Preferred `git branch` fix is refactorer-scoped: stop inheriting broad branch permissions or otherwise render only safe read forms such as exact `git branch` and `git branch --list*`.
- Shared `core:git-read` changes are allowed only if tests/source evidence prove that shared layer is the intended global source to narrow.
- Preferred validator fix is refactorer-scoped: avoid `aiPermissionPackSetFullProof()` for refactorer if it forces `proof.validate_script`; list needed proof packs explicitly and grant only exact read-only validator commands.
- A reusable validator pack should be added only if evidence shows two or more agents will share it; otherwise prefer a documented refactorer-specific composition exception/source entry consistent with `tools/ai/install/permission-layers/pack-sets.php:5-10`.
- Generated/refreshed outputs must preserve unrelated user changes already present in affected refactorer files.

## Todo Plan

- [x] P0: Inspect current diff for affected permission-layer and refactorer adapter/template files to identify pre-existing user changes that must be preserved.
- [x] P0: Read the permission source around `refactorer` in `tools/ai/install/permission-layers/compositions.php` and the proof pack definitions in `tools/ai/install/permission-layers/pack-sets.php` before editing.
- [x] P0: Confirm current rendered `.opencode/agents/refactorer.md` contains the critic-blocked broad `git branch*` and `php tools/ai/validate-*.php *` permission grants before changing source.
- [x] P0: Change refactorer permission source so rendered refactorer no longer allows broad `git branch*`, while retaining safe read-only branch inspection forms such as exact `git branch` and `git branch --list*`.
- [x] P0: Change refactorer permission source so rendered refactorer no longer allows broad `php tools/ai/validate-*.php *`, while granting exact read-only validator commands required for critic and adapter validation.
- [x] P0: Include at minimum exact grants for `php tools/ai/validate-agent-assessment.php *`, `php tools/ai/validate-agent-assessment-values.php`, `php tools/ai/validate-adapter-drift.php *`, and `php tools/ai/validate-ai-config.php`.
- [x] P0: Add `php tools/ai/validate-script-access.php *` only if direct source evidence proves refactorer needs that validator.
- [x] P0: Preserve `authorization-and-tool-governance` unless a validator or direct read proves the capability path is absent or invalid.
- [x] P1: Regenerate only affected managed refactorer outputs from permission source using the existing permission generation workflow.
- [x] P1: Inspect generated changes to ensure unrelated user changes in `.github/agents/refactorer.agent.md`, `.claude/agents/refactorer.md`, `.opencode/agents/refactorer.md`, and `packages/ai-universal-rules/templates/core/agents/refactorer.md` were not clobbered.
- [x] P1: Run the permission parity/check and targeted validators listed in the verification plan.
- [x] P1: Rerun `agent-critic` against `.opencode/agents/refactorer.md` and capture the score and blocker/high findings.
- [x] P2: If exact validator grants need a reusable permission pack, add one only after confirming at least two agents share the need; otherwise keep the exception refactorer-specific and documented in source.

## Acceptance Criteria

- [x] AC-01: Rendered `.opencode/agents/refactorer.md` no longer contains `git branch*`: allow.
- [x] AC-02: Rendered `.opencode/agents/refactorer.md` no longer contains `php tools/ai/validate-*.php *`: allow.
- [x] AC-03: Refactorer retains safe read-only branch inspection permission, including exact `git branch` or a proven equivalent safe branch-list command.
- [x] AC-04: Refactorer retains exact validator grants for `php tools/ai/validate-agent-assessment.php *`, `php tools/ai/validate-agent-assessment-values.php`, `php tools/ai/validate-adapter-drift.php *`, and `php tools/ai/validate-ai-config.php`.
- [x] AC-05: Durable source changes are present under `tools/ai/install/permission-layers/**` and any required template source, not only in `.opencode/agents/refactorer.md`.
- [x] AC-06: `authorization-and-tool-governance` remains routed when `docs/ai/capabilities/authorization-and-tool-governance/CAPABILITY.md` exists and no validator proves a different path issue.
- [x] AC-07: No new destructive git command permission is allowed for refactorer.
- [x] AC-08: No wildcard validator command remains in refactorer permissions that can match future mutation-capable validators.
- [x] AC-09: Diff inspection shows unrelated pre-existing working-tree changes in affected refactorer files are preserved.
- [x] AC-10: No generated artifact rewrite or broad adapter rerender beyond affected refactorer surfaces occurs without approval.
- [x] AC-11: `agent-critic` run against `.opencode/agents/refactorer.md` reports score `>= 80` and no blocker/high finding for broad `git branch*`, broad `validate-*.php`, or missing `authorization-and-tool-governance`, unless new evidence proves a real capability issue.

## Verification Plan

- Inspect diff before and after implementation: `git diff -- tools/ai/install/permission-layers/ packages/ai-universal-rules/templates/core/agents/refactorer.md .opencode/agents/refactorer.md .github/agents/refactorer.agent.md .claude/agents/refactorer.md`.
- Run permission parity/check: `php tools/ai/generate-agent-permissions.php --check`.
- Run targeted validation: `php tools/ai/validate-agent-assessment.php .opencode/agents/refactorer.md`.
- Run targeted validation: `php tools/ai/validate-agent-assessment-values.php`.
- Run targeted validation: `php tools/ai/validate-adapter-drift.php .opencode/agents/refactorer.md`.
- Run targeted validation: `php tools/ai/validate-ai-config.php`.
- Rerun `agent-critic` against `.opencode/agents/refactorer.md`; acceptance target is score `>= 80` and no blocker/high finding for broad `git branch*`, broad `validate-*.php`, or missing `authorization-and-tool-governance` unless new evidence proves a real capability issue.

## Risks And Rollback

- Risk level: medium, because permission-layer source changes can affect generated agent permissions.
- Rollback: revert the permission-layer and template changes for this task, regenerate affected refactorer surfaces through the permission generation workflow, and rerun `php tools/ai/generate-agent-permissions.php --check` plus targeted validators.
- Success signal: clean permission check and `agent-critic` no longer reports blocker/high findings for the stated refactorer permission/capability assessment.
- Unknown: whether `php tools/ai/validate-script-access.php *` is needed by refactorer until direct source evidence is checked.

## Handoff Notes

- Keep implementation bounded to refactorer-agent permission/capability assessment.
- Avoid direct generated permission block edits as the durable fix; make source changes first, then regenerate affected outputs.
- Preserve untracked `graphify-out/**` and unrelated untracked scripts as pre-existing working-tree state.
