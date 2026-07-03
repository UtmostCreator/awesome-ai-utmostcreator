# Architecture Plan — Clarification PRD And Handoff Architecture

- Ticket: none
- Source: task description
- Generated: 20260703-195541
- Plan folder: v0.5-upgrade/arch-todo-clarification-prd-and-handoff-architecture-20260703-195541/

## Context

This repository already has a strong canonical-doc and template-driven architecture, but it does not yet treat clarification, PRD generation, task-list generation, and handoff semantics as one reusable workflow family.

Evidence gathered for this plan:

- The external reference repo `.github/skills` set uses useful clarify-first patterns in `create-prd`, `prd-task-generation`, and `generate-tasks`, including asking clarifying questions and pausing before expanding tasks.
- Those external skills rely on prose rules rather than a strongly defined canonical handoff or clarification contract.
- No explicit handoff metadata or deterministic handoff schema was found in the reviewed external skill surfaces.
- This repository already has a canonical handoff contract in `docs/ai/handoff-contract.md`, but the package-layer workflow design does not yet appear to expose clarification-and-handoff as a first-class reusable capability family.
- Package-layer workflow and adapter sources currently live under `packages/ai-universal-rules/templates/**`, including `core/`, `capabilities/`, `skills/`, `commands/`, and `workflows/`.
- The shipped package already has strong adapter, validation, and source-of-truth rules, but it lacks a canonical workflow primitive for: ask a few clarifying questions, stop when still unclear, preserve answers, and hand off to the next surface or stage safely.

The opportunity is to borrow the best interaction pattern from the external repo while implementing it in this repository’s stronger architecture: canonical capability + surface-specific wrappers + explicit handoff payload + review and dead-surface protection.

## Problem

Clarification and handoff behavior are currently under-modeled as reusable architecture.

This creates seven risks:

1. ambiguous user requests may be handled inconsistently across skills, prompts, agents, and surfaces
2. PRD or task-generation flows may ask useful questions, but without a canonical contract those rules can drift or be omitted on some surfaces
3. surface-specific wrappers may duplicate clarification logic instead of routing into a shared workflow primitive
4. handoff payloads may omit assumptions, acceptance criteria, unresolved questions, or verification expectations
5. one runtime may support richer question-asking or handoff mechanics than another, but the repository lacks an explicit fallback model for preserving equivalent meaning
6. clarification or handoff support docs can become dead or weakly referenced if they are not wired into installed surfaces deterministically
7. review cannot reliably prove that the same critical clarification or handoff semantics are preserved after splitting or thinning files

## Target Outcome

The repository should gain a canonical clarification, PRD, task-breakdown, and handoff workflow architecture that works across Copilot, OpenCode, and Claude with surface-specific structure but consistent semantics.

After this plan is completed:

- clarification-before-action rules are canonical, reusable, and consistently routed
- PRD and task-generation workflows share the same ambiguity, stop, and assumption rules
- handoffs preserve the minimum required payload for downstream agents or humans
- each runtime has an explicit model for how clarification and handoff happen on that surface
- thinner wrappers can defer to canonical workflow logic without losing question-asking or handoff quality
- question-asking behavior is intentionally bounded so agents do not loop or over-interrogate users
- multiple plausible interpretations are surfaced instead of being picked silently when ambiguity matters
- workflows can explicitly push back when a simpler path is better than the user’s implied or overcomplicated framing
- parent-task-first planning and explicit pause or confirmation checkpoints are available where the workflow benefits from staged approval
- planning outputs preserve execution-ready sections such as likely files and verification commands where appropriate
- dead clarification or handoff docs can be detected by review and validation

## In Scope

- Canonical workflow architecture for clarification, PRD creation, task-list generation, and stage handoff
- Surface-specific wrapper strategy for Copilot, OpenCode, and Claude
- Definition of minimum preserved handoff payload
- Rules for when to ask, when to stop, when to assume, and when to escalate
- Rules for surfacing multiple plausible interpretations when they materially affect execution or scope
- Rules for pushing back when a simpler approach better satisfies the goal than a more complex implied path
- Bounded clarification behavior including maximum question count and explicit stop-or-assume branches
- Review and reachability model for clarification and handoff surfaces
- Package-template sources that would own or route this behavior

## Out Of Scope (Things To Avoid)

- [ ] Do not redesign all agents or all capabilities at once.
- [ ] Do not implement generated `.github/` output changes as the design source.
- [ ] Do not assume all runtimes support the same handoff or questioning mechanism.
- [ ] Do not move critical clarification or handoff semantics only into optional or weakly referenced files.
- [ ] Do not add broad always-on instruction sprawl as the primary fix.
- [ ] Do not touch unrelated existing working-tree changes in `AGENTS.md`, `CLAUDE.md`, or `.claude/`.

## Affected Paths

Primary package-template surfaces:

- `packages/ai-universal-rules/templates/capabilities/**`
- `packages/ai-universal-rules/templates/skills/**`
- `packages/ai-universal-rules/templates/commands/**`
- `packages/ai-universal-rules/templates/workflows/**`
- `packages/ai-universal-rules/templates/core/copilot-instructions.template.md`
- `packages/ai-universal-rules/templates/core/AGENTS.template.md`
- `packages/ai-universal-rules/templates/core/opencode.json`

Canonical review and contract surfaces:

- `docs/ai/handoff-contract.md`
- `docs/ai/adapter-contract.md`
- `docs/ai/validation.md`
- `docs/ai/ai-file-standards.md`
- `docs/ai/source-of-truth.md`
- `docs/ai/installed-files.md`
- `docs/ai/integration-matrix.md`
- `docs/ai/agent-ops-checklist.md`

Potential follow-up validator or generator surfaces if required:

- `tools/ai/validate-install-surface.php`
- `tools/ai/validate-adapter-drift.php`
- `tools/ai/validate-ai-config.php`
- `tools/ai/validate-ai-catalog.php`
- `tools/ai/validate-agent-spec.php`

## Contracts And Boundaries

- Clarification and handoff semantics should be canonical reusable workflow behavior, not surface-specific prose only.
- Runtimes may implement different wrappers for question-asking and handoff, but they must preserve equivalent critical meaning.
- Critical clarification semantics must include: when to ask, how much to ask, when to stop, when to make assumptions, and how to surface unknowns.
- Critical clarification semantics must include: when to ask, how much to ask, when to stop, when to make assumptions, how to surface unknowns, and when to present multiple plausible interpretations instead of choosing silently.
- Critical handoff semantics must include: scope, clarified constraints, assumptions, acceptance criteria, likely files, verification expectation, unresolved questions, recommended next step, and whether a downstream confirmation checkpoint is required.
- Support docs for PRD, task generation, and handoff must not become dead files; they must be installed and reachable intentionally.

## Todo Plan

- [ ] P0: Chunk 1 — define the canonical clarification contract: when to ask questions, maximum question count, when to stop, when to proceed with assumptions, and how to surface unknowns explicitly. Likely edits: a new canonical capability or workflow doc under `packages/ai-universal-rules/templates/capabilities/**` or `templates/workflows/**`, with supporting references in `docs/ai/handoff-contract.md` and `docs/ai/ai-file-standards.md` if needed. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-ai-config.php`, `php tools/ai/validate-ai-catalog.php`.
- [ ] P0: Chunk 1A — extend the clarification contract so it requires the workflow to surface multiple plausible interpretations when ambiguity materially affects scope, implementation path, or acceptance criteria, instead of picking one silently. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-ai-config.php`, `php tools/ai/validate-ai-catalog.php`.
- [ ] P0: Chunk 1B — define when a workflow should push back and recommend a simpler path rather than proceeding with an overcomplicated implied request. Likely edits: the same clarification contract surface plus any related behavioral or workflow guidance. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-ai-config.php`, `php tools/ai/validate-ai-catalog.php`.
- [ ] P0: Chunk 2 — define the minimum preserved handoff payload for PRD, planning, task-generation, and implementation transitions, including required sections for likely files, verification commands, assumptions, acceptance criteria, unresolved questions, and recommended next step. Likely edits: `docs/ai/handoff-contract.md`, package-template workflow or capability sources, and any companion routing docs that must reference the payload. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-ai-config.php`, `php tools/ai/validate-ai-catalog.php`.
- [ ] P0: Chunk 3 — design the canonical PRD and task-generation workflow family so clarifying-question rules, stop conditions, and staged approval checkpoints are shared rather than repeated. Likely edits: new or updated template workflow/capability files under `packages/ai-universal-rules/templates/capabilities/**`, `templates/workflows/**`, `templates/skills/**`, or `templates/commands/**`. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-ai-catalog.php`, `php tools/ai/validate-install-surface.php --strict`.
- [ ] P0: Chunk 3A — define the two-phase planning pattern for workflows that benefit from staged confirmation: generate parent tasks or high-level plan first, pause for confirmation when appropriate, then expand into subtasks or implementation detail. Likely edits: the same PRD/task workflow family plus handoff or wrapper guidance that marks when a pause is required versus optional. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-ai-catalog.php`, `php tools/ai/validate-install-surface.php --strict`.
- [ ] P0: Chunk 4 — map surface-specific wrappers for Copilot, OpenCode, and Claude so each runtime has a clear way to ask clarifying questions and hand off work without losing semantic equivalence. Likely edits: `packages/ai-universal-rules/templates/core/copilot-instructions.template.md`, `packages/ai-universal-rules/templates/core/AGENTS.template.md`, `packages/ai-universal-rules/templates/core/opencode.json`, and package-level workflow or wrapper templates. Completion checks: `php tools/ai/validate-adapter-drift.php --fail-on-warn`, `php tools/ai/validate-install-surface.php --strict`, `bash scripts/ai/ai-doc-check.sh --check`.
- [ ] P1: Chunk 4A — decide where runtime-specific structured handoff metadata is appropriate and where prose-only fallback is required, so Copilot/OpenCode/Claude can preserve the same meaning even when their handoff mechanisms differ. Likely edits: package-template wrapper docs, `docs/ai/integration-matrix.md`, and `docs/ai/adapter-contract.md`. Completion checks: `php tools/ai/validate-adapter-drift.php --fail-on-warn`, `php tools/ai/validate-install-surface.php --strict`, `bash scripts/ai/ai-doc-check.sh --check`.
- [ ] P1: Chunk 5 — define fallback behavior for runtimes that cannot support the same structured handoff metadata or interactive question flow. Likely edits: `docs/ai/integration-matrix.md`, `docs/ai/adapter-contract.md`, and package-template wrapper docs. Completion checks: `php tools/ai/validate-adapter-drift.php --fail-on-warn`, `php tools/ai/validate-install-surface.php --strict`, `bash scripts/ai/ai-doc-check.sh --check`.
- [ ] P1: Chunk 6 — add dead-surface and reachability review for clarification, PRD, task, and handoff docs so they cannot remain installed but operationally unused. Likely edits: `docs/ai/validation.md`, `docs/ai/installed-files.md`, `docs/ai/adapter-contract.md`, and template-maintenance guidance. Completion checks: `php tools/ai/validate-install-surface.php --strict`, `php tools/ai/validate-ai-config.php`, `bash scripts/ai/ai-doc-check.sh --check`.
- [ ] P1: Chunk 7 — decide which clarification or handoff semantics must be always-on and which can be deterministic-load support content. Likely edits: package-template baseline surfaces plus the canonical clarification/handoff docs. Completion checks: `php tools/ai/validate-install-surface.php --strict`, `php tools/ai/validate-adapter-drift.php --fail-on-warn`, `bash scripts/ai/ai-doc-check.sh --check`.
- [ ] P2: Chunk 8 — add review guidance that compares pre-thinning and post-thinning semantic coverage for clarification and handoff behavior. Likely edits: `docs/ai/validation.md`, `docs/ai/agent-ops-checklist.md`, and maintainer-facing template guidance. Completion checks: `php tools/ai/validate-install-surface.php --strict`, `php tools/ai/validate-ai-config.php`, `php tools/ai/validate-ai-catalog.php`, `bash scripts/ai/ai-doc-check.sh --check`.
- [ ] P2: Chunk 9 — document the long-term maintenance loop for clarification, PRD, task, and handoff workflow assets so future splits or surface-specific refinements do not reintroduce drift. Likely edits: `docs/ai/handoff-contract.md`, `docs/ai/validation.md`, `docs/ai/adapter-contract.md`, and template-maintenance docs. Completion checks: `php tools/ai/validate-install-surface.php --strict`, `php tools/ai/validate-ai-config.php`, `php tools/ai/validate-ai-catalog.php`, `bash scripts/ai/ai-doc-check.sh --check`, `bash scripts/ai/ai-verify.sh .`.

## Acceptance Criteria

- [ ] AC-01: Clarification rules are canonical and define when to ask, when to stop, and when to assume.
- [ ] AC-01A: Clarification rules include an explicit maximum question count and a clear stop-or-assume branch so agents do not drift into repeated questioning.
- [ ] AC-01B: Clarification rules require the workflow to surface multiple plausible interpretations where material ambiguity exists, instead of choosing one silently.
- [ ] AC-02: PRD and task-generation workflows reuse the same clarification semantics instead of duplicating them loosely.
- [ ] AC-02A: PRD and task-generation workflows can support a parent-task-first or high-level-plan-first checkpoint where appropriate before expanding into detailed subtasks.
- [ ] AC-02B: PRD and task-generation workflows can recommend a simpler path and push back on overcomplicated framing when that better serves the user’s goal.
- [ ] AC-03: The minimum preserved handoff payload is explicit and sufficient for downstream humans or agents to continue without guessing.
- [ ] AC-03A: Planning and task-generation handoffs include likely files and verification commands whenever the workflow is mature enough to produce them responsibly.
- [ ] AC-04: Copilot, OpenCode, and Claude may differ structurally, but their clarification and handoff semantics remain reviewably equivalent.
- [ ] AC-05: Critical clarification or handoff semantics are never left only in optional, generated, or weakly referenced files.
- [ ] AC-06: Review and validation guidance can detect dead clarification, dead handoff, or dead PRD/task support docs.
- [ ] AC-07: The final workflow family improves agent understanding of user requests without broadening always-on instruction sprawl unnecessarily.

## Verification Plan

- Inspect changed template and canonical files against `docs/ai/ai-file-standards.md` for role fit and duplication avoidance.
- Run install-surface and adapter-drift validators after any runtime-surface wrapper change.
- Review the clarification contract and handoff payload against `docs/ai/handoff-contract.md` to ensure required fields are preserved.
- Perform a reachability review for any support doc or wrapper introduced or split.
- Run broader repository verification only after the final workflow-family slice if validator or install-surface contracts are touched.

## Risks And Rollback

Risk level: medium-high.

Main risks:

- Clarification behavior may be easy to describe but hard to preserve consistently across different runtimes unless the contract is explicit.
- A surface with weaker question-asking or handoff support may require a slightly thicker wrapper than ideal.
- Splitting PRD/task/handoff workflow docs without a reachability review could create dead support docs.
- Trying to solve Copilot, OpenCode, and Claude handoff behavior with one identical structure would create false parity and weaken practical usability.

Rollback posture:

- Define the canonical clarification and handoff contracts before changing wrappers.
- Keep runtime-specific wrapper changes in separate slices so a weakened surface can be reverted independently.
- If a split creates ambiguous reachability or dead-surface concerns, restore the thicker routing surface until the fallback model is proven.

## Handoff Notes

Recommended implementation order:

1. Define the canonical clarification contract.
2. Define the minimum preserved handoff payload.
3. Build the PRD/task workflow family on top of that contract.
4. Map runtime-specific wrappers and fallbacks.
5. Add dead-surface and semantic-coverage review rules.
6. Document the long-term maintenance loop last.

Recommended next stage:

implementer means implementer agent handoff using OpenCode command: /implement
