# Architecture Plan — Repository Clarity And Surface Consolidation

- Ticket: none
- Source: task description
- Generated: 20260703-192142
- Plan folder: v0.5-upgrade/arch-todo-repo-clarity-and-surface-consolidation-20260703-192142/

## Context

This repository is already architected around a strong canonical-doc model: canonical workflow and policy belong in `docs/ai/`, while runtime adapters should stay thin. The main weakness is no longer missing structure. The main weakness is the cost of navigating and maintaining the current structure across a large number of docs, instruction files, adapters, skills, and agents.

Current evidence gathered for this plan:

- `docs/ai/` contains 143 markdown files.
- `.github/instructions/` contains 22 instruction files.
- `.github/agents/` contains 23 Copilot agent files.
- `.opencode/agents/` contains 14 OpenCode agent files.
- `.github/skills/` contains 19 skills.
- `docs/ai/capabilities/` contains 16 capability roots.
- `docs/ai/project-context.md` still contains several high-value `unknown` placeholders.
- `docs/ai/capabilities/README.md` is too thin to act as an operational capability-discovery index.
- `README.md` explains the kit well but still routes users into a large documentation tree without one compact “start here / edit here / generated here” control surface.
- `docs/ai/maintainer-guide.md` currently describes `.github/`, `docs/`, and `scripts/` as untracked folders created by installer or self-install, which is confusing in the context of this source repository because those surfaces are present and actively maintained here.
- `docs/ai/source-of-truth.md` explains canonical-vs-generated ownership well, but it does not yet front-load the operational distinction between this source kit, an installed target project, and a self-installed development state.
- The repository has strong validators, but maintainers still need to infer which validator applies to which type of documentation, adapter, or template-backed change.

The repository should borrow ECC’s better first-read discoverability, but should not borrow ECC’s duplication, parity overclaiming, or surface-specific policy sprawl.

## Problem

The repository’s architecture is sound, but its control surfaces are too numerous and too distributed for maintainers and users to navigate quickly.

This creates four maintenance costs:

1. users do not get a compact answer to where they should start reading
2. maintainers do not get a compact answer to which file owns which rule or render surface
3. placeholder or incomplete canonical docs force adapters and surrounding documents to compensate
4. the number of instruction and adapter surfaces increases drift risk even with validators present

This also creates three additional clarity costs:

5. maintainers can confuse source-kit state with installed-target state and edit the wrong layer
6. maintainer-facing docs can describe repository surfaces in ways that are accurate for installed targets but misleading for the source repo itself
7. contributors do not yet get a short change-type to owner-file to validator routing path

## Target Outcome

The repository should become easier to understand, edit, and maintain without weakening its current canonical-doc architecture.

After this plan is completed:

- users can find one short control document that explains what to read first and where to edit
- maintainers can distinguish canonical docs, template sources, generated files, and runtime adapters quickly
- maintainers can distinguish source-kit behavior from installed-target behavior without guessing
- capability discovery is explicit and task-oriented
- high-value `unknown` fields in canonical context are reduced or eliminated
- surface differences across Copilot, OpenCode, and Claude are documented clearly without implying false parity
- validator routing is explicit enough that a maintainer can choose the right check for a change without reconstructing the validation model from multiple docs
- instruction and adapter surfaces are thinner, less repetitive, and easier to validate

## In Scope

- Documentation architecture for AI workflow surfaces in this repository
- Canonical routing and discoverability for workflow docs, capabilities, and adapter ownership
- Reduction of ambiguity across `README.md`, `docs/ai/`, and runtime adapter guidance
- Clarification of runtime surface differences for Copilot, OpenCode, and Claude
- Clarification of source-kit vs installed-target vs self-installed development-state language in maintainer-facing docs
- Consolidation planning for instruction and adapter surfaces where duplication or fragmentation is unnecessary
- Maintainer routing for change ownership and validator selection
- Validation implications for adapter drift, install surface integrity, and AI config consistency

## Out Of Scope (Things To Avoid)

- [ ] Do not redesign the entire agent system from scratch.
- [ ] Do not add new runtime surfaces or remove supported runtime surfaces.
- [ ] Do not expand the number of always-on instruction files as a primary fix.
- [ ] Do not copy ECC’s duplicated cross-surface policy style.
- [ ] Do not make generated files the source of truth.
- [ ] Do not implement installer, validator, or runtime behavior changes unless they are directly required by the documentation or adapter-ownership correction.
- [ ] Do not edit pre-existing unrelated working-tree changes in `AGENTS.md`, `CLAUDE.md`, or `.claude/` without explicit follow-up scope.

## Affected Paths

Primary canonical and routing surfaces:

- `README.md`
- `docs/ai/project-context.md`
- `docs/ai/workflow.md`
- `docs/ai/agents.md`
- `docs/ai/AGENTS-MANIFEST.md`
- `docs/ai/capabilities/README.md`
- `docs/ai/adapter-contract.md`
- `docs/ai/source-of-truth.md`
- `docs/ai/validation.md`

Potentially affected adapter or render-source surfaces in later phases:

- `.github/copilot-instructions.md`
- `.github/instructions/**`
- `.github/agents/**`
- `.opencode/agents/**`
- `AGENTS.md`
- `CLAUDE.md`
- `packages/ai-universal-rules/templates/**`

Verification and ownership surfaces:

- `docs/ai/ai-file-standards.md`
- `docs/ai/execution-protocol.md`
- `tools/ai/validate-adapter-drift.php`
- `tools/ai/validate-install-surface.php`
- `tools/ai/validate-ai-config.php`

## Contracts And Boundaries

- Canonical workflow and policy remain in `docs/ai/`.
- Runtime adapters remain lower authority than canonical docs.
- Generated artifacts remain non-canonical unless explicitly documented otherwise.
- Template-backed runtime surfaces must be changed at the template source when applicable, not only at the rendered output.
- Any phase that touches runtime adapters must remain aligned with `docs/ai/adapter-contract.md` and `docs/ai/ai-file-standards.md`.
- Any phase that touches generated or template-managed surfaces must verify ownership first using `docs/ai/source-of-truth.md`.
- This plan is documentation- and workflow-architecture-first; code mutation is not the first lever.

## Todo Plan

- [ ] P0: Chunk 1 — create the canonical control entrypoint in `docs/ai/` and define it as the one short “start here / edit here / generated here” document. Likely edits: new control doc under `docs/ai/`, `README.md`, `docs/ai/capabilities/README.md`. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-ai-config.php`, `php tools/ai/validate-ai-catalog.php`.
- [ ] P0: Chunk 2 — make source-kit versus installed-target versus self-installed development-state language explicit. Likely edits: `docs/ai/maintainer-guide.md`, `docs/ai/source-of-truth.md`, `README.md`, and the new control doc if needed. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-install-surface.php --strict`, `php tools/ai/validate-ai-config.php`.
- [ ] P0: Chunk 3 — add maintainer task routing so common change types map to the correct owner file or template source and the next validation step. Likely edits: `docs/ai/maintainer-guide.md`, `docs/ai/adapter-contract.md`, `docs/ai/validation.md`, and the new control doc if needed. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-install-surface.php --strict`, `php tools/ai/validate-ai-config.php`.
- [ ] P0: Chunk 4 — audit `docs/ai/project-context.md` and replace the highest-value `unknown` placeholders that directly affect understanding, verification, ownership, or safe editing. Likely edits: `docs/ai/project-context.md`, with supporting updates to the control doc or maintainer docs if wording depends on it. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-ai-config.php`, `php tools/ai/validate-ai-catalog.php`.
- [ ] P0: Chunk 5 — define one concise runtime surface matrix covering Copilot, OpenCode, and Claude so users can understand shipped differences without reading multiple adapter files. Likely edits: `docs/ai/agents.md`, `docs/ai/AGENTS-MANIFEST.md`, `docs/ai/adapter-contract.md`, or a dedicated matrix doc under `docs/ai/`. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-adapter-drift.php --fail-on-warn`, `php tools/ai/validate-install-surface.php --strict`.
- [ ] P1: Chunk 6 — review canonical routing overlap across `docs/ai/agents.md`, `docs/ai/AGENTS-MANIFEST.md`, and `docs/ai/workflow.md`, then tighten responsibilities so each document has a clearer single job. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-adapter-drift.php --changed-only --fail-on-warn`.
- [ ] P1: Chunk 7 — separate user-facing navigation docs from maintainer-facing internals so the documentation tree is easier to browse and reason about. Likely edits: `README.md`, `docs/ai/non-technical-overview.md`, `docs/ai/maintainer-guide.md`, the new control doc, and possibly a contributor quickstart doc. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-ai-catalog.php`.
- [ ] P1: Chunk 8 — add a validator-routing matrix that maps common documentation, adapter, template, and install-surface changes to the correct validator or verification sequence. Likely edits: `docs/ai/validation.md`, `docs/ai/maintainer-guide.md`, and the new control doc. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-install-surface.php --strict`, `php tools/ai/validate-ai-config.php`, `php tools/ai/validate-ai-catalog.php`.
- [ ] P1: Chunk 9 — review docs that talk about install-time surfaces and ensure they say explicitly whether they describe the source repo, a target repo, or both. Likely edits: `README.md`, `docs/ai/maintainer-guide.md`, `docs/ai/source-of-truth.md`, `readme-install.md`, and any touched doc from earlier chunks. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-install-surface.php --strict`.
- [ ] P1: Chunk 10 — identify which `.github/instructions/*.instructions.md` files are truly needed for deterministic loading and which can be consolidated or reduced without losing safety or scope control. Start with an audit note before consolidation. Likely edits: `docs/ai/adapter-contract.md`, `docs/ai/ai-file-standards.md`, affected `.github/instructions/*.instructions.md`, and template sources if a kept surface is template-backed. Completion checks: `php tools/ai/validate-adapter-drift.php --fail-on-warn`, `php tools/ai/validate-install-surface.php --strict`, `bash scripts/ai/ai-doc-check.sh --check`.
- [ ] P1: Chunk 11 — review adapter surfaces for repeated policy text and replace duplicated procedure with short routing back to canonical docs where safe. Likely edits: `.github/copilot-instructions.md`, `AGENTS.md`, `CLAUDE.md`, `.opencode/**`, and corresponding template sources under `packages/ai-universal-rules/templates/**` when applicable. Completion checks: `php tools/ai/validate-adapter-drift.php --fail-on-warn`, `php tools/ai/validate-install-surface.php --strict`, `php tools/ai/validate-ai-config.php`, `bash scripts/ai/ai-doc-check.sh --check`.
- [ ] P1: Chunk 12 — confirm that all adapter-facing claims about runtime differences, ownership, and safe command usage match the canonical docs and do not imply unsupported parity. Likely edits: `docs/ai/adapter-contract.md`, `docs/ai/agents.md`, `docs/ai/AGENTS-MANIFEST.md`, `.github/copilot-instructions.md`, `AGENTS.md`, `CLAUDE.md`, and related templates if required. Completion checks: `php tools/ai/validate-adapter-drift.php --fail-on-warn`, `php tools/ai/validate-install-surface.php --strict`, `bash scripts/ai/ai-doc-check.sh --check`.
- [ ] P2: Chunk 13 — review whether `AGENTS.md`, `CLAUDE.md`, and `.github/copilot-instructions.md` can be shortened further after canonical-doc and surface-matrix improvements land. Likely edits: template sources under `packages/ai-universal-rules/templates/**` first, then rendered surfaces if needed for this source repo. Completion checks: `php tools/ai/validate-adapter-drift.php --fail-on-warn`, `php tools/ai/validate-install-surface.php --strict`, `bash scripts/ai/ai-doc-check.sh --check`.
- [ ] P2: Chunk 14 — review whether additional generated or maintained index surfaces are needed to keep inventory and surface differences accurate over time. Likely edits: `docs/ai/validation.md`, `docs/ai/maintainer-guide.md`, generator or template documentation, and only the minimum necessary generated-surface guidance. Completion checks: `php tools/ai/validate-install-surface.php --strict`, `php tools/ai/validate-ai-catalog.php`, `bash scripts/ai/ai-doc-check.sh --check`.
- [ ] P2: Chunk 15 — document a stable maintenance loop for future changes: which canonical docs to update first, which template or adapter surfaces follow, and which validators must pass before merge. Likely edits: `docs/ai/maintainer-guide.md`, `docs/ai/source-of-truth.md`, `docs/ai/validation.md`, and the control doc. Completion checks: `php tools/ai/validate-install-surface.php --strict`, `php tools/ai/validate-ai-config.php`, `php tools/ai/validate-ai-catalog.php`, `bash scripts/ai/ai-doc-check.sh --check`, `bash scripts/ai/ai-verify.sh .`.
- [ ] P2: Chunk 16 — add a short maintainer or contributor quickstart layer between `README.md` and `docs/ai/maintainer-guide.md` if the strengthened routing still leaves too much onboarding distance for contributors. Likely edits: `README.md`, a new contributor quickstart doc under `docs/ai/`, and `docs/ai/maintainer-guide.md`. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-ai-catalog.php`.

## Acceptance Criteria

- [ ] AC-01: A maintainer can find one canonical “start here / edit here / generated here” document in `docs/ai/` and use it to identify the correct owner file for common workflow changes.
- [ ] AC-02: `README.md` routes users to the correct canonical workflow entrypoints without requiring them to infer ownership from multiple adapter files.
- [ ] AC-03: `docs/ai/capabilities/README.md` provides explicit task-to-capability routing for the main workflow categories used by this repository.
- [ ] AC-04: High-value `unknown` placeholders in `docs/ai/project-context.md` that affect ownership, verification, or runtime understanding are reduced or replaced with verified content.
- [ ] AC-05: A reader can understand the practical difference between Copilot, OpenCode, and Claude support from one maintained surface rather than piecing it together manually.
- [ ] AC-06: Canonical docs, adapter docs, and generated or template-backed surfaces have clearer role separation with less duplicated policy text.
- [ ] AC-07: Any instruction or adapter consolidation preserves the existing safety posture and passes the relevant repository validators.
- [ ] AC-08: The final structure makes the repo easier to maintain and modify without expanding the number of always-on guidance files.
- [ ] AC-09: A maintainer can tell whether a document is describing the source kit, an installed target project, or a self-installed development state without guessing.
- [ ] AC-10: A contributor can map a common change type to the correct owner file or template source and the right validator through one short lookup path.
- [ ] AC-11: Maintainer-facing docs do not describe currently tracked source-repo surfaces as if they only exist as untracked installer outputs.

## Verification Plan

- Inspect changed docs against `docs/ai/source-of-truth.md` to confirm canonical-vs-adapter ownership is preserved.
- Run focused documentation and adapter checks after each implementation slice, starting with adapter drift validation.
- Run the validator set relevant to changed surfaces, using `docs/ai/validation.md` as the verification reference.
- Use the planned validator-routing matrix to prove that common change types have an explicit and non-ambiguous verification path.
- Review changed files for duplication against `docs/ai/ai-file-standards.md` line-budget and role rules.
- Review the final diff for routing clarity, reduced ambiguity, and absence of generated-file-first edits.

## Risks And Rollback

Risk level: medium.

Main risks:

- Canonical-doc tightening may expose real missing project facts rather than simple wording issues.
- Source-kit vs installed-target clarification may reveal other docs that silently assume only one repository state and require follow-up updates.
- Instruction consolidation may accidentally remove a surface that exists for deterministic loading or validation coverage.
- Template-backed adapter updates may drift if only rendered surfaces are changed.
- User-facing simplification may overcompress important distinctions if routing is made too shallow.

Rollback posture:

- Keep changes in bounded slices, starting with canonical docs only.
- Do not consolidate instruction or adapter files until the new canonical control surfaces are in place and validated.
- If a later adapter-consolidation slice causes validator or surface-regression issues, roll back that slice while preserving the new canonical docs and navigation improvements.

## Handoff Notes

Recommended implementation order:

1. Execute Chunks 1-3 to establish the canonical control entrypoint, source-versus-target clarity, and maintainer task routing.
2. Execute Chunks 4-5 to complete the highest-value canonical context and add the runtime surface matrix.
3. Execute Chunks 6-9 to tighten routing roles, separate audiences more clearly, and add validator routing.
4. Execute Chunks 10-12 only after the canonical docs above are stable and validated.
5. Execute Chunks 13-16 last, using the earlier slices as the basis for adapter thinning, maintenance-loop codification, and contributor quickstart decisions.

Recommended next stage:

implementer means implementer agent handoff using OpenCode command: /implement
