# Architecture Plan — spec-kit comparison: accepted high/good-value changes (3-phase, 9-slice program)

- Ticket: none
- Source: architect design handoff (3-researcher spec-kit comparison program)
- Generated: 20260704-223159
- Plan folder: docs/tickets/arch-todo-speckit-comparison-adoption-20260704-223159/

## Context

Evidence summary (from the architect handoff):

- Derived from a 3-researcher comparison of `/home/utmostcreator/Projects/spec-kit` (github/spec-kit @ 0.12.5.dev0) vs this repo (HEAD `aa01690`).
- Key evidence: `.ai/project.yml` lines 6–11 contain literal `unknown` for `primaryLanguage`, `primaryRuntime`, `primaryVerifyCommand`, `primaryBuildCommand`, `primaryTestCommand` while `composer.json` defines `test`, `test:fast`, `test:profile`, `test:slow`; `validate-context-budgets` currently FAILs (17 WARN + `.opencode/skills/graphify/SKILL.md` 669 lines > 350 hard max); `docs/ai/adapter-contract.md` admits `validate-adapter-drift.php` does not do full content parity; `v0.5-upgrade/` and `v0.6-plan/` sit at repo root violating the docs/tickets/-only plan boundary; spec-kit's `templates/commands/analyze.md` (254 lines, 6 read-only passes, severity table), `tasks-template.md` (T-IDs, `[P]` markers, independent-test lines), `clarify.md` (ambiguity taxonomy, durable answer encoding) have no counterpart here; 8 capabilities exist installed without template sources (orphan pattern not to be repeated).
- Pre-existing dirty file `scripts/ai/internal/pre-tool-use/10-helpers.sh` (+17/-3) is OUT OF SCOPE: no slice may include it in a commit.

## Problem

The repo fails its own truth and hygiene gates (literal `unknown` project-context values that are provable from `composer.json`, a failing context-budget validator, root-level plan folders outside the `docs/tickets/` boundary), lacks spec-kit's proven pipeline discipline (requirement/task IDs, durable spec artifact, strengthened clarify gate), has no cross-artifact analyze capability, and cannot detect template-vs-render content drift. This program adopts the accepted high/good-value spec-kit findings by extending existing surfaces — never by adding a parallel pipeline.

## Target Outcome

All three phases land in order: Phase A restores truth and hygiene (project context resolved, context budgets PASS, plan folders relocated); Phase B adds ID discipline, durable spec.md persistence, and a stronger clarify gate to the existing pipeline; Phase C ships a read-only analyze-plan capability and full adapter content-parity validation. Program-level success signal: `bash scripts/ai/ai-doc-check.sh drift` PASS, `php tools/ai/validate-adapter-drift.php` PASS, `php tools/ai/ai.php placeholders --fail` PASS (or documented intentional), `composer test` PASS.

## In Scope

Program structure: 3 phases, 9 slices, execute in order.

- Phase A — Truth & hygiene: S1 resolve knowable unknowns in `.ai/project.yml` + re-render; S2 pass own context-budget validator; S3 relocate root plan folders into `docs/tickets/`.
- Phase B — Pipeline discipline (extend existing pipeline): S4 FR-###/T-### ID discipline in architecture-plan-writer; S5 persist the PRD brief as a durable `spec.md` under `docs/tickets/<folder>/`; S6 strengthen the clarify gate with an ambiguity taxonomy, option-table format, and durable answer encoding.
- Phase C — Analysis & parity (depends on A/B): S7 new analyze-plan capability (cross-artifact consistency, severity-ranked findings); S8 adapter content-parity validation in `validate-adapter-drift.php` (chosen option: validator, NOT uncommitting renders); S9 (candidate, decision-gated) surface consolidation of duplicate verify launchers and near-duplicate agent pairs.

## Out Of Scope (Things To Avoid)

Program-wide non-goals:

- Do NOT add a parallel spec pipeline (prd-and-tasks/architect/plan-writer already cover it at ~70–80% overlap).
- Do NOT add a constitution artifact/file.
- Do NOT uncommit rendered adapters or switch to install-time-only rendering in this program.
- Do NOT edit rendered files directly; all changes at `.ai/project.yml` or `packages/ai-universal-rules/templates/**` sources.
- Do NOT edit third-party graphify skill content (`.opencode/skills/graphify/SKILL.md` — allowlist it instead).
- Do NOT include `scripts/ai/internal/pre-tool-use/10-helpers.sh` in any commit (pre-existing dirty file, out of scope).
- Do NOT regress: permission gating, evidence discipline (`.ai-logs`, hooks), validators, script registry, clarification one-question budget.

## Affected Paths

- S1: `.ai/project.yml` (edit-here source); rendered surfaces refreshed via installer (`docs/ai/project-context.md`, `AGENTS.md` incl. out-of-band `## graphify` re-apply, others per render plan); user-owned `docs/ai/project/project-interaction.md` (sign-off gated).
- S2: `packages/ai-universal-rules/templates/core/agents/*` (refactorer 312>240, post-install 298, architect 290, reviewer 286, architecture-plan-writer 260, config-maintainer 243), Copilot agent templates (implementer 237, refactorer 263, researcher 234, architecture-plan-writer 231, post-install 221), `AGENTS.template.md` (196>180); overflow relocated to referenced capability files; validator allowlist entry for `.opencode/skills/graphify/SKILL.md`.
- S3: `v0.5-upgrade/` and `v0.6-plan/` -> under `docs/tickets/` (git mv, approval-gated); `docs/tickets/MASTER-INDEX.md`; reference sweep surfaces.
- S4/S5: architecture-plan-writer and prd-and-tasks skill/agent TEMPLATE sources + re-rendered `.opencode/` and `.github/` copies; `docs/ai/workflow.md` (stage chain).
- S6: `docs/ai/capabilities/clarification-and-handoff/` canonical + template source (create the template source if this is one of the 8 orphans).
- S7: new `docs/ai/capabilities/analyze-plan/` (CAPABILITY.md, checklist.md, gotchas.md, reference.md) WITH matching template source under `packages/ai-universal-rules/templates/capabilities/`; workflow template fan-out to `.opencode/commands` + `.github/prompts` + both SKILL.md trees; optional enabler in `AGENTS.template.md` (separate commit, user-approved).
- S8: `tools/ai/validate-adapter-drift.php`, `scripts/ai/ai-doc-check.sh` drift wiring, CI validate-ai-surface workflow, new PHPUnit tests.
- S9: `.opencode/commands/verify.md` (25 ln), `verify-change.md` (44), `verify-ai-wiring.md` (26); agent pairs researcher/repository-researcher, reviewer/repository-reviewer, implementer/super-implementer — decision-gated only.

## Contracts And Boundaries

- Edit-here surfaces are `.ai/project.yml` and `packages/ai-universal-rules/templates/**`; rendered copies flow only from the installer render pipeline. Installer `--apply` is approval-gated; `--dry-run` first, always.
- `AGENTS.md` carries an out-of-band `## graphify` section (per `docs/ai/adapter-contract.md` hazard note) that must be re-applied after any full re-render.
- New capabilities MUST ship canonical + template source + rendered copies in the same commit (no 9th orphan capability).
- analyze-plan is read-only: severity-ranked findings only; remediation is approval-gated, never auto-fix.
- File moves in S3 are pure renames (git mv); deletion anywhere in the program is approval-gated (S9 requires a recorded owner decision first).
- Third-party skill content (graphify) is untouchable; validator handles it via a documented allowlist.

## Todo Plan

Phase A — Truth & hygiene:

- [ ] P0: S1 (CRITICAL) — Resolve knowable unknowns in project context. Steps: (1) set in `.ai/project.yml`: `primaryLanguage=PHP`, `primaryRuntime=PHP` (version per `composer.json` require), `primaryVerifyCommand=composer test`, `primaryTestCommand=composer test` (fast variant `composer test:fast`), `primaryBuildCommand=none` (document explicitly), uncomment/fill `packageManager=composer`, `testCommand`, `lintCommand`/`formatCommand` only where provable from repo configs; (2) preview re-render with `php tools/ai/ai.php install * --dry-run`; (3) apply re-render (ask-gated `php tools/ai/ai.php install * --apply`); (4) re-apply out-of-band `## graphify` section to `AGENTS.md` per adapter-contract.md hazard note; (5) propose content for user-owned `docs/ai/project/project-interaction.md` stubs and get user sign-off before writing.
- [ ] P1: S2 (HIGH) — Pass own context-budget validator. Steps: trim/split the 11 over-budget agent files at their TEMPLATE sources (`packages/ai-universal-rules/templates/core/agents/*`: refactorer 312>240, post-install 298, architect 290, reviewer 286, architecture-plan-writer 260, config-maintainer 243; Copilot agents implementer 237, refactorer 263, researcher 234, architecture-plan-writer 231, post-install 221; `AGENTS.template.md` 196>180), moving overflow into referenced capability files; add a documented validator allowlist entry for third-party `.opencode/skills/graphify/SKILL.md` (669 lines) instead of editing third-party content; re-render.
- [ ] P1: S3 (HIGH) — Relocate root plan folders into docs/tickets/. Steps: `git mv v0.5-upgrade/` and `v0.6-plan/` under `docs/tickets/` (approval-gated move; ask user before executing); sweep references with `bash scripts/ai/check-file-refs.sh` and `rg` for path strings; update `docs/tickets/MASTER-INDEX.md`.

Phase B — Pipeline discipline (extend existing pipeline; DO NOT add a parallel spec pipeline — >=75% overlap rule):

- [ ] P1: S4 (HIGH) — Requirement/task ID discipline in architecture-plan-writer. Steps: extend the architecture-plan-writer skill/agent TEMPLATE sources (and re-render to `.opencode/` and `.github/` copies) with: FR-### requirement keys, T-### task IDs, `[P]` parallel markers, per-story "Independent Test" line, checkpoint gates. Additive only; existing plans remain valid.
- [ ] P1: S5 (HIGH) — Persist the PRD brief as a durable spec artifact. Steps: update prd-and-tasks skill (template source + renders) so the confirmed PRD brief is written as `spec.md` into the same `docs/tickets/<folder>/` as the eventual `plan.md`; document the explicit stage chain prd-and-tasks -> architect -> architecture-plan-writer -> implementer in the skill docs and `docs/ai/workflow.md`.
- [ ] P1: S6 (HIGH) — Strengthen clarify gate. Steps: extend `docs/ai/capabilities/clarification-and-handoff` (canonical + template source if one exists; if the capability is one of the 8 orphans, create its template source as part of this slice) with: ambiguity-scan taxonomy (functional scope, data model, NFRs, integrations, edge cases, terminology, completion signals), option-table question format (A/B/C options), and a rule that accepted clarification answers are encoded into the ticket spec/plan file.

Phase C — Analysis & parity (depends on Phases A/B):

- [ ] P0: S7 (CRITICAL) — New analyze-plan capability (cross-artifact consistency). Steps: create canonical `docs/ai/capabilities/analyze-plan/` (CAPABILITY.md, checklist.md, gotchas.md, reference.md) WITH matching template source under `packages/ai-universal-rules/templates/capabilities/` (must not become a 9th orphan capability); add workflow template so it fans out to `.opencode/commands` + `.github/prompts` + both SKILL.md trees via the normal renderer; behavior: read-only passes over a `docs/tickets/<folder>/` artifact set detecting (a) AC/FR with no covering T-task, (b) T-task with no AC/FR, (c) unresolved placeholders, (d) terminology drift across spec/plan, (e) conflicts with AGENTS.md policy; output severity-ranked findings table (CRITICAL/HIGH/MEDIUM/LOW); remediation approval-gated, never auto-fix. Optional enabler (separate commit, user-approved): short numbered "Non-Negotiable Principles" block in `AGENTS.template.md` that analyze-plan treats as auto-CRITICAL on conflict; explicitly NOT a separate constitution file. Requires reviewer.
- [ ] P0: S8 (CRITICAL) — Adapter content-parity validation (chosen option: validator, NOT uncommitting renders). Steps: extend `tools/ai/validate-adapter-drift.php` with full content parity: re-render (or hash-compare) template sources vs committed copies for agents, skills, commands, prompts, capabilities; documented allowlist mechanism for intentional out-of-band sections (`## graphify` in AGENTS.md, third-party skills); wire into `ai-doc-check.sh drift` and CI validate-ai-surface workflow; add PHPUnit coverage for pass, drift-detected, and allowlisted cases. If real drift is found in committed renders, fix forward at template sources in follow-up commits inside this slice's scope. Requires reviewer + release-auditor.
- [ ] P2: S9 (CANDIDATE, decision-gated) — Surface consolidation. Steps: (1) decision checkpoint with owner: present the 3 verify launchers (`.opencode/commands/verify.md` 25 ln, `verify-change.md` 44, `verify-ai-wiring.md` 26) and near-duplicate agent pairs (researcher/repository-researcher, reviewer/repository-reviewer, implementer/super-implementer) with whatever usage evidence exists (currently unknown); (2) only on explicit approval: merge/remove at template sources, re-render, sweep references with `check-file-refs.sh`.

## Acceptance Criteria

S1:

- [ ] AC-01 (explicit): `docs/ai/project-context.md` no longer states unknown for language/runtime/verify/build/test.
- [ ] AC-02 (evidence-backed): `php tools/ai/ai.php placeholders --fail` passes or the remaining placeholders are documented intentional.
- [ ] AC-03 (negative): `AGENTS.md` still ends with the `## graphify` section after re-render.
- [ ] AC-04 (negative): no rendered file edited directly; all value changes flow from `.ai/project.yml`.

S2:

- [ ] AC-05 (explicit): `bash scripts/ai/ai-doc-check.sh drift` reports validate-context-budgets PASS.
- [ ] AC-06 (negative): no policy semantics lost — trimmed content relocated to referenced capability/docs files, not deleted.
- [ ] AC-07 (negative): graphify SKILL.md content untouched.

S3:

- [ ] AC-08 (explicit): no plan folders exist at repo root.
- [ ] AC-09 (evidence-backed): `check-file-refs.sh` reports no broken references to moved paths.
- [ ] AC-10 (negative): file contents unmodified by the move (pure rename).

S4:

- [ ] AC-11 (explicit): plan-writer output contract requires FR/T IDs and `[P]` markers.
- [ ] AC-12 (negative): existing AC-NN convention preserved.
- [ ] AC-13 (negative): no new file surfaces added beyond the existing skill/agent/template set.

S5:

- [ ] AC-14 (explicit): prd-and-tasks contract includes persisting `spec.md` under `docs/tickets/`.
- [ ] AC-15 (inferred): `spec.md` carries FR-### IDs from S4 so coverage mapping is possible.
- [ ] AC-16 (negative): no second pipeline created; only existing skills extended.

S6:

- [ ] AC-17 (explicit): capability includes the taxonomy and option-table format.
- [ ] AC-18 (explicit): capability requires durable encoding of answers into ticket artifacts.
- [ ] AC-19 (negative): one-question-per-pause budget and stop-or-assume branch unchanged.

S7:

- [ ] AC-20 (explicit): analyze-plan exists as capability + rendered command/prompt/skill via templates.
- [ ] AC-21 (explicit): running it on an ID-disciplined ticket folder yields a coverage table keyed on FR/T IDs.
- [ ] AC-22 (negative): capability is read-only, no mutation instructions.
- [ ] AC-23 (evidence-backed): template source and rendered copies ship in the same commit and pass adapter-drift validation.

S8:

- [ ] AC-24 (explicit): parity mismatch between a template source and its committed render fails the validator.
- [ ] AC-25 (explicit): allowlisted out-of-band content does not fail.
- [ ] AC-26 (evidence-backed): new PHPUnit tests cover the three cases and `composer test` passes.
- [ ] AC-27 (negative): existing validator checks keep their current behavior.
- [ ] AC-28 (negative): install-time-only rendering (spec-kit model) is NOT implemented — recorded as a deferred future decision.

S9:

- [ ] AC-29 (explicit): an owner decision is recorded in the ticket before any removal.
- [ ] AC-30 (evidence-backed): after any merge, `check-file-refs.sh` passes and adapter-drift validation passes.
- [ ] AC-31 (negative): no deletion without recorded approval.

## Verification Plan

Per slice, narrow-first:

- S1: `git diff` of rendered files matches dry-run preview; `php tools/ai/ai.php placeholders --fail`; `composer test:fast`.
- S2: `bash scripts/ai/ai-doc-check.sh drift`; `php tools/ai/validate-adapter-drift.php`; `composer test:fast` if validator PHP touched.
- S3: `check-file-refs.sh`; `git log --follow` spot check.
- S4: `php tools/ai/validate-adapter-drift.php`; manual render diff review.
- S5: adapter-drift validator; docs reference check.
- S6: adapter-drift validator; `ai-doc-check.sh drift` stays PASS.
- S7: `validate-adapter-drift.php`; `ai-doc-check.sh drift`; manual dry-run of the command against an existing arch-todo folder.
- S8: `composer test:fast` then `composer test`; `ai-doc-check.sh drift`; CI run green.
- S9: `check-file-refs.sh` and adapter-drift validation after any approved merge.

Program-level final check (verification ladder): `bash scripts/ai/ai-doc-check.sh drift` PASS, `php tools/ai/validate-adapter-drift.php` PASS, `php tools/ai/ai.php placeholders --fail` PASS (or documented intentional), `composer test` PASS.

## Risks And Rollback

- S1: medium (re-render blast radius). Rollback: git revert of the render commit.
- S2: low-medium. Rollback: revert commit.
- S3: low. Rollback: git revert (rename-only).
- S4: low.
- S5: low-medium (touches installer templates -> re-render).
- S6: low.
- S7: medium (new surface across 4 render targets). Requires reviewer.
- S8: medium (may surface latent drift; touches CI gate). Requires reviewer + release-auditor. Rollback: disable the new check behind its flag/allowlist and revert.
- S9: low-medium. Deletion approval-gated.

## Handoff Notes

- Ordering and dependencies: S1 -> S2 -> S3 (independent of each other but do Phase A first) -> S4 -> S5 -> S6 -> S7 (needs S4/S5 IDs) -> S8 (any time after S2; benefits from S7 surfaces existing) -> S9 (last, decision-gated).
- Approval gates to request per slice: installer `--apply` (S1, S2, S4, S5, S7, S9 renders), `git mv` of root folders (S3), user sign-off on `project-interaction.md` content (S1), optional "Non-Negotiable Principles" enabler commit (S7), owner decision before any S9 removal, CI-gate change review (S8).
- Pre-existing dirty working-tree files (including `scripts/ai/internal/pre-tool-use/10-helpers.sh` and the architecture-plan-writer agent/template edits) are OUT OF SCOPE for every slice commit — stage only intended files.
- Recommended next step: implementer means implementer agent handoff using OpenCode command: /implement — start with S1 only.
