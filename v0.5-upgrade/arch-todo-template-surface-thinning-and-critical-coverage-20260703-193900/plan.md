# Architecture Plan — Template Surface Thinning And Critical Coverage

- Ticket: none
- Source: task description
- Generated: 20260703-193900
- Plan folder: v0.5-upgrade/arch-todo-template-surface-thinning-and-critical-coverage-20260703-193900/

## Context

This repository ships AI workflow surfaces from package templates under `packages/ai-universal-rules/templates/**`. The current generated `.github/` outputs are not the design source for this task; the package templates are.

Evidence gathered for this plan:

- Copilot baseline source is `packages/ai-universal-rules/templates/core/copilot-instructions.template.md`.
- Root baseline source is `packages/ai-universal-rules/templates/core/AGENTS.template.md`.
- OpenCode shipped baseline is `packages/ai-universal-rules/templates/core/opencode.json`.
- Capability entrypoint source is `packages/ai-universal-rules/templates/capabilities/README.md`.
- Project-context source is `packages/ai-universal-rules/templates/core/project-context.template.md`.
- Project-owned starter docs include `packages/ai-universal-rules/templates/core/project/README.md` and `packages/ai-universal-rules/templates/core/project/project-interaction.md`.
- Installed-surface coverage is documented in `docs/ai/installed-files.md`.
- Adapter and ownership rules are documented in `docs/ai/adapter-contract.md`, `docs/ai/source-of-truth.md`, and `docs/ai/validation.md`.
- The OpenCode baseline currently loads a broad always-on instruction set through `packages/ai-universal-rules/templates/core/opencode.json`.
- The Copilot template is currently denser and more repetitive than ECC’s simpler Copilot baseline, even though this repository has the stronger overall architecture.
- The Karpathy-style reference repo demonstrates a high-value pattern: one compact behavioral baseline repeated consistently across surfaces with very low adoption friction.
- That same repo uses strong example-driven teaching (`bad` vs `good`) to make behavioral expectations memorable, even though its architecture is much flatter and less reusable than this repository’s.

The main opportunity is not to copy ECC’s flat instruction style. The real opportunity is to keep this repository’s stronger layered architecture while making the shipped template surfaces thinner, more deterministic, and easier to maintain.

## Problem

The package-layer shipped instruction system is correct in direction but too heavy in its current form.

This creates six maintenance and reliability risks:

1. always-on baseline files are denser than they need to be
2. critical guidance is repeated across layers instead of being clearly guaranteed once and referenced deterministically elsewhere
3. OpenCode’s always-on instruction bundle is broad enough to increase maintenance and token cost
4. critical guidance could be accidentally lost during thinning because there is no explicit critical-topic coverage matrix by surface
5. support docs can become effectively dead if they are installed but not guaranteed to be loaded or referenced by commands, prompts, skills, or agents
6. structure differences across Copilot, OpenCode, and Claude are necessary, but semantic equivalence of critical coverage is not yet made explicit enough

## Target Outcome

The shipped package templates should become thinner and easier to maintain without losing any critical semantics.

After this plan is completed:

- critical topics are explicitly mapped to guaranteed-load surfaces for Copilot, OpenCode, and Claude
- thinner adapters route to canonical workflow content without losing security, approval, scope, or verification rules
- structure may differ by runtime, but semantic coverage remains intentionally equivalent
- dead or weakly referenced instruction files are identified and removed, consolidated, or made reachable deterministically
- workflow-critical primitives such as clarification-before-action, stop conditions on ambiguity, and handoff-payload preservation are explicitly included in coverage review rather than assumed to survive indirectly
- a compact behavioral baseline is packaged from one shared source and rendered consistently across the relevant shipped surfaces
- a short tradeoff note makes the shipped system’s bias toward caution, evidence, and narrow scope explicit to adopters
- example-driven anti-pattern guidance exists for the most common LLM failure modes so downstream users can internalize the behavior model quickly
- OpenCode’s always-on baseline is smaller and more intentional
- Copilot’s shipped baseline is easier to skim and maintain while preserving mandatory routing and shell-policy constraints
- project-context and capability entrypoint templates provide better starter guidance so downstream repos need less compensating documentation

## In Scope

- Package-template sources under `packages/ai-universal-rules/templates/**` that define shipped instruction surfaces
- Coverage guarantees for critical baseline topics across Copilot, OpenCode, and Claude
- Thinning and rerouting of runtime adapter baselines
- Reachability and dead-file review for installed docs and support files
- Validation and review strategy to ensure no critical semantic loss during splitting or thinning
- Clarification of which content must always load vs which content may remain optional or on-demand

## Out Of Scope (Things To Avoid)

- [ ] Do not redesign the full agent system.
- [ ] Do not edit generated `.github/` output as the source of truth.
- [ ] Do not force identical file structure across Copilot, OpenCode, and Claude.
- [ ] Do not remove critical topics from always-on or deterministic-load paths.
- [ ] Do not move critical semantics only into optional docs, weak references, or generated artifacts.
- [ ] Do not combine template-surface restructuring with unrelated installer or validator rewrites unless strictly required.
- [ ] Do not touch pre-existing unrelated working-tree changes in `AGENTS.md`, `CLAUDE.md`, or `.claude/`.

## Affected Paths

Primary template surfaces:

- `packages/ai-universal-rules/templates/core/copilot-instructions.template.md`
- `packages/ai-universal-rules/templates/core/AGENTS.template.md`
- `packages/ai-universal-rules/templates/core/opencode.json`
- `packages/ai-universal-rules/templates/capabilities/README.md`
- `packages/ai-universal-rules/templates/core/project-context.template.md`
- `packages/ai-universal-rules/templates/core/project/README.md`
- `packages/ai-universal-rules/templates/core/project/project-interaction.md`
- `packages/ai-universal-rules/templates/core/workflow.template.md`

Canonical review and install-contract surfaces:

- `docs/ai/adapter-contract.md`
- `docs/ai/source-of-truth.md`
- `docs/ai/installed-files.md`
- `docs/ai/validation.md`
- `docs/ai/ai-file-standards.md`
- `docs/ai/generated-artifacts.md`
- `docs/ai/approval-boundaries.md`
- `docs/ai/failure-handling.md`
- `docs/ai/integration-matrix.md`
- `docs/ai/agent-ops-checklist.md`

Template-adjacent validation or generation surfaces that may need follow-up only if required:

- `tools/ai/validate-install-surface.php`
- `tools/ai/validate-adapter-drift.php`
- `tools/ai/validate-ai-config.php`
- `tools/ai/validate-ai-catalog.php`
- `tools/ai/generate-ai-catalog.php`
- `tools/ai/generate-ai-file-standards.php`

## Contracts And Boundaries

- Canonical portable workflow meaning remains owned by the canonical docs and capability model, not by any one runtime adapter.
- Runtime structure may differ by surface if the critical semantics remain guaranteed and reviewable.
- Critical topics must either be always loaded or deterministically reachable from an always-loaded surface.
- Installed support docs must not become dead files: every kept support doc must be either always loaded, explicitly referenced, or provably reachable through the intended surface workflow.
- Template-backed surfaces must be edited at the template source, not in generated outputs.
- Thinning is allowed only with coverage review and drift validation.

## Todo Plan

- [ ] P0: Chunk 1 — define the critical-topic coverage matrix for all supported runtime surfaces. Required topics: security, approval boundaries, task-context gate, source-of-truth, verification honesty, escalation, runtime limitations, and shell-policy boundaries. Likely edits: a new canonical matrix doc under `docs/ai/` or an extension to `docs/ai/integration-matrix.md`, plus references from template-layer docs if needed. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-ai-config.php`, `php tools/ai/validate-ai-catalog.php`.
- [ ] P0: Chunk 1A — expand the critical-topic coverage matrix to include workflow-critical primitives that must not be lost during thinning: clarification-before-action, stop-or-assume rules, preserved handoff payload, acceptance-criteria capture, and verification-expectation capture. Likely edits: the same coverage-matrix surface from Chunk 1 plus any template-maintainer guidance that defines the review checklist. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-ai-config.php`, `php tools/ai/validate-ai-catalog.php`.
- [ ] P0: Chunk 2 — classify every package-template instruction surface as one of: always-on critical, deterministic-load routing, optional support, or generated/install-only reference. Likely edits: `packages/ai-universal-rules/templates/core/copilot-instructions.template.md`, `packages/ai-universal-rules/templates/core/AGENTS.template.md`, `packages/ai-universal-rules/templates/core/opencode.json`, `docs/ai/installed-files.md`, and `docs/ai/adapter-contract.md`. Completion checks: `php tools/ai/validate-install-surface.php --strict`, `php tools/ai/validate-ai-config.php`, `bash scripts/ai/ai-doc-check.sh --check`.
- [ ] P0: Chunk 2A — define one shared-source compact behavioral baseline for shipped runtime surfaces, covering: ask instead of guessing, simplicity over speculative abstraction, surgical changes, and goal-driven verification. Likely edits: a shared snippet or template source under `packages/ai-universal-rules/templates/shared/**` or `templates/snippets/**`, plus references from `core/AGENTS.template.md`, `core/copilot-instructions.template.md`, and any other relevant runtime template. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-install-surface.php --strict`, `php tools/ai/validate-ai-config.php`.
- [ ] P0: Chunk 3 — thin the Copilot baseline template so it keeps only guaranteed-load critical routing and runtime-specific caveats while routing deeper procedure back to canonical docs. Likely edits: `packages/ai-universal-rules/templates/core/copilot-instructions.template.md`, with any required companion updates in `packages/ai-universal-rules/templates/core/AGENTS.template.md` and canonical routing docs. Completion checks: `php tools/ai/validate-adapter-drift.php --fail-on-warn`, `php tools/ai/validate-install-surface.php --strict`, `bash scripts/ai/ai-doc-check.sh --check`.
- [ ] P0: Chunk 3A — add a short, invariant tradeoff note to the shipped behavioral baseline so adopters understand that the system biases toward caution, clarity, and evidence over speculative speed on non-trivial tasks. Likely edits: the shared behavioral baseline source plus the minimal runtime templates that surface it. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-install-surface.php --strict`.
- [ ] P0: Chunk 4 — review and reduce the always-on OpenCode instruction bundle to the minimum stable set that still guarantees critical baseline coverage. Likely edits: `packages/ai-universal-rules/templates/core/opencode.json`, plus canonical references if some currently always-on docs must become deterministic-load docs instead. Completion checks: `php tools/ai/validate-install-surface.php --strict`, `php tools/ai/validate-adapter-drift.php --fail-on-warn`, `php tools/ai/validate-ai-config.php`, `bash scripts/ai/ai-doc-check.sh --check`.
- [ ] P0: Chunk 5 — strengthen `packages/ai-universal-rules/templates/capabilities/README.md` so it acts as an operational capability router rather than a generic description. Likely edits: `packages/ai-universal-rules/templates/capabilities/README.md`, possibly `packages/ai-universal-rules/templates/core/workflow.template.md` if cross-links need tightening. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-ai-catalog.php`.
- [ ] P1: Chunk 6 — strengthen `packages/ai-universal-rules/templates/core/project-context.template.md` so downstream projects get better defaults for required-soon fields and fewer ambiguous placeholders. Likely edits: `packages/ai-universal-rules/templates/core/project-context.template.md`, possibly `packages/ai-universal-rules/templates/core/project/README.md` if user-owned follow-up guidance must be clarified. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-ai-config.php`, `php tools/ai/validate-ai-catalog.php`.
- [ ] P1: Chunk 7 — improve project-owned starter docs so users know what to fill in and how those files interact with generated or kit-managed surfaces. Likely edits: `packages/ai-universal-rules/templates/core/project/README.md` and `packages/ai-universal-rules/templates/core/project/project-interaction.md`. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-ai-catalog.php`.
- [ ] P1: Chunk 7A — add a small example-driven teaching surface for the most common AI failure modes the shipped system is trying to prevent: hidden assumptions, overcomplication, drive-by changes, and weak success criteria. Likely edits: a new or expanded examples/reference doc under `packages/ai-universal-rules/templates/docs/**` or a closely related canonical doc, with references from the compact behavioral baseline or maintainer docs where appropriate. Completion checks: `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/validate-ai-catalog.php`.
- [ ] P1: Chunk 8 — add a deterministic reachability review for support docs that may become thinner or more split. Likely edits: `docs/ai/installed-files.md`, `docs/ai/adapter-contract.md`, `docs/ai/validation.md`, and any control-matrix doc created earlier. Completion checks: `php tools/ai/validate-install-surface.php --strict`, `php tools/ai/validate-adapter-drift.php --fail-on-warn`, `bash scripts/ai/ai-doc-check.sh --check`.
- [ ] P1: Chunk 9 — add a dead-file and dead-instruction review model so installed but weakly referenced files can be caught and either removed or re-routed intentionally. Likely edits: `docs/ai/validation.md`, `docs/ai/installed-files.md`, and template-maintainer guidance. Completion checks: `php tools/ai/validate-install-surface.php --strict`, `php tools/ai/validate-ai-config.php`, `bash scripts/ai/ai-doc-check.sh --check`.
- [ ] P1: Chunk 10 — define how semantic parity is reviewed across Copilot, OpenCode, and Claude when file structures diverge. Likely edits: `docs/ai/integration-matrix.md`, `docs/ai/adapter-contract.md`, and perhaps a new review checklist doc under `docs/ai/`. Completion checks: `php tools/ai/validate-adapter-drift.php --fail-on-warn`, `php tools/ai/validate-install-surface.php --strict`, `bash scripts/ai/ai-doc-check.sh --check`.
- [ ] P2: Chunk 11 — review whether the OpenCode bash permission allowlist should remain hand-authored or move toward generated or tier-derived policy from a canonical manifest. Likely edits: template-adjacent policy and documentation surfaces only unless implementation is explicitly approved later. Completion checks: `php tools/ai/validate-command-policy.php`, `php tools/ai/validate-install-surface.php --strict`, `bash scripts/ai/ai-doc-check.sh --check`.
- [ ] P2: Chunk 12 — document the package-template maintenance loop for future surface thinning: what to review before splitting, what to prove after splitting, and how to confirm critical topics were not lost. Likely edits: `docs/ai/validation.md`, `docs/ai/adapter-contract.md`, `docs/ai/agent-ops-checklist.md`, and maintainership guidance. Completion checks: `php tools/ai/validate-install-surface.php --strict`, `php tools/ai/validate-ai-config.php`, `php tools/ai/validate-ai-catalog.php`, `bash scripts/ai/ai-doc-check.sh --check`, `bash scripts/ai/ai-verify.sh .`.

## Acceptance Criteria

- [ ] AC-01: There is an explicit matrix showing where each critical topic is guaranteed to load on Copilot, OpenCode, and Claude.
- [ ] AC-01A: The same matrix explicitly covers workflow-critical primitives such as clarification, stop conditions, handoff payload preservation, acceptance-criteria capture, and verification-expectation capture.
- [ ] AC-02: Thinner Copilot and OpenCode baselines preserve all critical semantics while becoming easier to skim and maintain.
- [ ] AC-02A: A compact behavioral baseline exists in one shared source and is propagated consistently across the relevant shipped runtime surfaces.
- [ ] AC-03: Critical topics are never left only in optional, generated, or weakly referenced files.
- [ ] AC-04: Structure is allowed to differ by runtime, but semantic coverage remains reviewable and intentionally equivalent.
- [ ] AC-05: Capability and project-context starter templates provide stronger downstream guidance and reduce compensating explanation in installed repos.
- [ ] AC-05A: The shipped package exposes a short, explicit tradeoff note and example-driven anti-pattern guidance that make the behavior model easier for adopters to internalize quickly.
- [ ] AC-06: Support docs that remain installed are either always loaded, deterministically reachable, or intentionally classified as optional.
- [ ] AC-07: The review model can catch dead instructions, dead markdown files, and accidental semantic loss during template thinning.
- [ ] AC-08: The final package-template architecture is easier to maintain without increasing always-on instruction sprawl.

## Verification Plan

- Inspect every changed template against `docs/ai/ai-file-standards.md` for role fit and line-budget discipline.
- Run adapter and install-surface validators after each runtime-surface change.
- Perform a critical-topic coverage review after each thinning slice to confirm no required topic lost guaranteed-load coverage.
- Perform a reachability review for any support doc moved into thinner or more split structure.
- Use validator and install-surface evidence from `docs/ai/validation.md` to prove the shipped package still installs coherent surfaces.
- Run broader repository verification only after the final template workstream if any generator, catalog, or install-surface contract changes are touched.

## Risks And Rollback

Risk level: medium-high.

Main risks:

- Critical topics may currently be duplicated because guaranteed-load coverage is implicit; thinning before coverage mapping could lose required semantics.
- OpenCode’s current broad always-on bundle may hide support docs that would otherwise become weakly reachable.
- Copilot may require a slightly thicker always-on surface than other runtimes because deterministic loading options differ.
- Surface-specific structure changes may pass file-level validation while still weakening practical topic reachability if review remains too shallow.

Rollback posture:

- Do coverage-matrix work first and do not thin baselines before it exists.
- Keep runtime-surface changes in separate slices so a weakened baseline can be reverted without rolling back the whole workstream.
- If a thinning slice causes ambiguous coverage or drift warnings, restore the thicker baseline for that runtime and revisit the reachability model before proceeding.

## Handoff Notes

Recommended implementation order:

1. Build the critical-topic coverage matrix first.
2. Classify surfaces into always-on, deterministic-load, optional, and generated/reference roles.
3. Thin Copilot baseline only after critical-topic replacement paths are proven.
4. Reduce OpenCode always-on instructions only after the same coverage proof exists.
5. Strengthen capability and project-context starter templates.
6. Add reachability and dead-file review rules.
7. Document the long-term package-template maintenance loop last.

Recommended next stage:

implementer means implementer agent handoff using OpenCode command: /implement
