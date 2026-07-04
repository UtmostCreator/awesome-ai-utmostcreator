# Architecture Plan — v0.6 Review-Fix: reconcile rendered surfaces, installed-path references, per-file classification, and package drift for the completed Phase 0–2 slice

- Ticket: none
- Source: architect design handoff
- Generated: 20260704-000002
- Plan folder: docs/tickets/arch-todo-v0-6-review-fix-20260704-000002/

## Context

A reviewer FAILed the v0.6 Phase 0–2 change set (branch main, working tree not yet committed). Five findings must be fixed before Phase 3 or before marking the Phase 0/1/2 exit gates complete. Root cause analysis (evidence-grounded):

- `AGENTS.md` and `.github/copilot-instructions.md` are installer RENDER OUTPUTS of the templates (`tools/ai/install/packs.php` lines 91 and 112: `merge_strategy => replace`). The templates gained a `## Behavioral Baseline` section but the rendered outputs were never regenerated, so the shipped matrix claim "guaranteed-load on AGENTS.md / .github/copilot-instructions.md" is currently false.
- `docs/ai/capabilities/*` folders are populated by the installer from `templates/capabilities/*`. `clarification-and-handoff` was added to the `base` pack (`packs.php` line 109) but never rendered into `docs/ai/capabilities/clarification-and-handoff/` in this self-installed repo, so the matrix references a package-template path instead of the installed path other capabilities use.
- Phase 0.2 required classifying all 139 shipped template surfaces; the delivered matrix classifies broad subtrees only (no per-file inventory), so dead-file review cannot name a specific unreferenced file.
- Phase 0.1 marked clarification / stop-or-assume "covered" while the owning capability is deterministic-load (not guaranteed-load) on every runtime — an acceptance-criteria mismatch to reconcile.
- New template files (`templates/capabilities/clarification-and-handoff/CAPABILITY.md`, `templates/snippets/behavioral-baseline.snippet.md`) are absent from `packages/ai-universal-rules/package-lock.ai.json` (checksum lock over template sources) and are not reflected in package manifest/catalog surfaces. `INSTALL-CATALOG.md` was updated but package integrity/discovery surfaces were not.

Grounding evidence already confirmed:

- Adapter render path: `tools/ai/install/packs.php` (base pack -> AGENTS.md; adapter-copilot pack -> .github/copilot-instructions.md), `tools/ai/install/core.php` render loop, `tools/ai/install/generated-header.php`. Regenerating rendered adapters is an installer `--apply` operation (APPROVAL-GATED — `php tools/ai/ai.php install ... --apply` is `ask`).
- Catalog auto-scan: `tools/ai/ai_catalog_lib.php` line 472 globs `docs/ai/capabilities/*/CAPABILITY.md`; line 618 classifies `templates/capabilities/` as `package-capability`. Catalog regeneration will auto-detect the new capability ONCE it exists on disk under `docs/ai/capabilities/`.
- Explicit validator list: `tools/ai/validate-ai-config.php` lines 62–75 (known) and 158–169 (required) enumerate `docs/ai/capabilities/*` and do NOT include `clarification-and-handoff`. If the new capability is to be validated as canonical it must be added there (and to `manifest.json` `required_templates` / `starter_optional_capabilities` if it is to be a declared package template).
- package-lock refresh command: `php tools/ai/ai.php package-lock --update` (see `tools/ai/commands/install_preflight.php`); verify with `php tools/ai/ai.php package-verify`.
- Repo is a SELF-INSTALLED SOURCE KIT: rendered outputs (`AGENTS.md`, `.github/**`, `docs/ai/capabilities/**` render copies) are tracked and refreshed by render; never hand-edit files carrying a GENERATED header.

## Problem

A reviewer FAILed the v0.6 Phase 0–2 change set. Five findings must be fixed before Phase 3 or before marking the Phase 0/1/2 exit gates complete:

1. Rendered adapter surfaces (`AGENTS.md`, `.github/copilot-instructions.md`) were never regenerated after the templates gained `## Behavioral Baseline`, so the matrix's guaranteed-load claim is currently false.
2. The `clarification-and-handoff` capability was added to the base pack but never rendered into `docs/ai/capabilities/`, so the matrix references a package-template path instead of the installed path.
3. Phase 0.2 per-file classification of all 139 shipped template surfaces is missing (only subtree-level classification delivered).
4. Phase 0.1 overstated clarification / stop-or-assume as "covered" when the owning capability is deterministic-load, not guaranteed-load.
5. New template files are absent from `package-lock.ai.json` and not reflected in package manifest/catalog surfaces.

## Target Outcome

The v0.6 Phase 0–2 slice is reconciled so the five reviewer findings are resolved: rendered adapters carry the baseline section via render, the clarification capability is installed at its canonical path and correctly referenced/validated, coverage status text is truthful, per-file surface classification exists, package lock and catalog surfaces reflect the new template files, and the full validator gate re-runs with no NEW failures. Success signal = clean full validator gate with no new failures + rendered surfaces contain the baseline.

## In Scope

- Regenerate rendered adapter surfaces (`AGENTS.md`, `.github/copilot-instructions.md`) from the updated templates via installer render (approval-gated `--apply`).
- Render/install the `clarification-and-handoff` capability into `docs/ai/capabilities/clarification-and-handoff/CAPABILITY.md`.
- Update `docs/ai/integration-matrix.md` rows (Clarification-before-action, Stop-or-assume) to reference the INSTALLED canonical capability path.
- Reconcile Phase 0.1 coverage status wording so it truthfully reflects deterministic-load vs guaranteed-load (no overstated "covered").
- Add per-file surface role classification (generated companion or checked-in companion doc) referenced from the matrix to satisfy Phase 0.2.
- Add the new capability to `validate-ai-config.php` known list (and required list / manifest surfaces if canonical/required).
- Refresh `packages/ai-universal-rules/package-lock.ai.json` via `package-lock --update` for the two new template files.
- Regenerate catalog surfaces so the new capability appears; confirm clean via `--check`.
- Re-run the full per-slice validator gate; separate pre-existing failures from new ones.
- Re-affirm or reopen original v0.6 plan Phase 0.1 / 0.2 / Phase 1 exit / Phase 2 exit checkboxes based on the re-run gate.

## Out Of Scope (Things To Avoid)

- Do NOT start Phase 3 (opencode.json / copilot thinning) or any later phase.
- Do NOT hand-edit rendered outputs (`AGENTS.md`, `.github/copilot-instructions.md`, `docs/ai/capabilities/**` render copies) to inject content; regenerate them.
- Do NOT change the template BODIES of the behavioral baseline or clarification capability content (Phase 1/2 content was accepted); this slice is reconciliation/wiring only.
- Do NOT restructure the installer pack registry beyond the minimal reconciliation needed for the capability to render.
- Do NOT touch `.opencode/**`, `graphify-out/**`, `v0.5-upgrade/**`, or the pre-existing unrelated working-tree changes (`scripts/ai/internal/search/95-dispatch.sh`, `tests/scripts/ai/test-ai-search.sh`) that are outside this task.
- Do NOT commit or push.

## Affected Paths

- Render sources (edit-here): `packages/ai-universal-rules/templates/**` (already changed; possibly minor pack/source shape reconciliation in `tools/ai/install/packs.php`).
- Rendered outputs (generated-here, via installer only): `AGENTS.md`, `.github/copilot-instructions.md`, `docs/ai/capabilities/clarification-and-handoff/**`.
- Validator wiring: `tools/ai/validate-ai-config.php` (capability known/required lists), `packages/ai-universal-rules/manifest.json` (`required_templates` / `starter_optional_capabilities` if applicable).
- Package integrity: `packages/ai-universal-rules/package-lock.ai.json` (via `package-lock --update`), catalog surfaces (`catalog.json` / `docs/ai/catalog.md` / `INSTALL-CATALOG.md`) via generators.
- Coverage doc: `docs/ai/integration-matrix.md` (path references, status wording, per-file classification reference) + new per-file companion doc.

## Contracts And Boundaries

- Rendered outputs are the source-of-truth product of render, never hand-edited; templates under `packages/ai-universal-rules/templates/**` are the edit-here surface.
- Files carrying a GENERATED header must not be hand-edited (NAC-3).
- Installer `--apply`, `package-lock --update`, catalog write, `ai-verify.sh`, and `package-verify` are approval-gated; dry-run and read/validate commands are allowed without approval.
- Catalog auto-scan globs `docs/ai/capabilities/*/CAPABILITY.md` and classifies `templates/capabilities/` as `package-capability`; the new capability is auto-detected once it exists on disk.
- Validator canonical lists in `validate-ai-config.php` (known ~62–75; required ~158–169) enumerate capabilities explicitly and must include the new capability if it is to be validated as canonical.
- Only new-capability + snippet entries are expected in package-lock / catalog diffs; larger rewrites must be scope-checked.

## Todo Plan

- [x] P0: Step 1 — Regenerate rendered adapter surfaces from templates (fixes Finding 1). Re-run the installer/self-install render so `AGENTS.md` and `.github/copilot-instructions.md` are rebuilt from the updated templates and contain the `## Behavioral Baseline` section with the correct generated header and placeholder substitution. This is APPROVAL-GATED (`php tools/ai/ai.php install <profile> --apply`, or the repo's documented self-install render command); request approval before `--apply`, run the dry-run first (`--dry-run` is allowed) and show the plan. Do NOT hand-edit the rendered files to inject the section; the render is the source of truth. Verify: rendered `AGENTS.md` and `.github/copilot-instructions.md` contain `## Behavioral Baseline`; `php tools/ai/validate-adapter-drift.php --fail-on-warn` passes with no new failures.
- [x] P0: Step 2 — Render/install the clarification-and-handoff capability into docs/ai/capabilities and fix installed-path references (fixes Finding 2). Ensure `docs/ai/capabilities/clarification-and-handoff/CAPABILITY.md` exists on disk (produced by the same render/install in Step 1, since the pack entry already exists at packs.php line 109). If the render does not create it (e.g. capability shipped as a single CAPABILITY.md vs a dir with support files), reconcile the pack entry/source shape so the installed dir is produced. Update `docs/ai/integration-matrix.md` rows for Clarification-before-action and Stop-or-assume to reference the INSTALLED canonical path `docs/ai/capabilities/clarification-and-handoff/CAPABILITY.md` (matching how every other capability is referenced), not the `packages/ai-universal-rules/templates/...` source path. Add `docs/ai/capabilities/clarification-and-handoff/CAPABILITY.md` to the `validate-ai-config.php` known list (lines ~62–75) and, if it is to be required/canonical, the required list (lines ~158–169); mirror any manifest `required_templates` / `starter_optional_capabilities` update in `packages/ai-universal-rules/manifest.json`. Verify: `php tools/ai/validate-ai-config.php` passes with no new failures; matrix path references resolve.
- [x] P0: Step 3 — Reconcile the Phase 0.1 coverage status wording (fixes Finding 4). In `docs/ai/integration-matrix.md`, correct the status for Clarification-before-action and Stop-or-assume so status text matches reality: either (a) mark them `partial` (deterministic-load, not guaranteed-load on every runtime) with the named phase that would make them guaranteed-load, OR (b) if a guaranteed-load path is intended, add a one-line always-on pointer in the baseline surfaces and mark `covered` truthfully. Prefer (a) — do not overstate coverage. Keep the "no false parity" discipline already used in the matrix.
- [x] P1: Step 4 — Add per-file surface classification to satisfy Phase 0.2 (fixes Finding 3). Add a per-file classification inventory (each shipped template file -> role: always-on-critical / deterministic-load / optional-support / generated-or-install-only, plus its load path) so dead-file review can name a specific unreferenced file. Prefer a generated companion (a small deterministic generator or a checked-in companion doc referenced from the matrix) over a hand-maintained 139-row table if a generator is cheap; otherwise a checked-in companion doc under docs/ai/ referenced from the matrix's "Shipped Surface Role Classification" section is acceptable. Reference the companion from `docs/ai/integration-matrix.md`. Keep the subtree-level table as the summary; the companion carries the per-file rows.
- [x] P1: Step 5 — Refresh package lock and catalog surfaces (fixes Finding 5). Run `php tools/ai/ai.php package-lock --update` to add `templates/capabilities/clarification-and-handoff/CAPABILITY.md` and `templates/snippets/behavioral-baseline.snippet.md` to `packages/ai-universal-rules/package-lock.ai.json`. Regenerate the catalog so the new capability appears (`php tools/ai/generate-ai-catalog.php` write path, then `--check` to confirm clean); the auto-scan picks up the installed capability. Verify: `php tools/ai/ai.php package-verify` clean; `php tools/ai/validate-ai-catalog.php` clean; `php tools/ai/generate-ai-catalog.php --check` clean.
- [x] P2: Step 6 — Re-run the full per-slice validator gate and reconcile plan checkboxes. Run the gate from `docs/ai/validation.md`: `php tools/ai/validate-install-surface.php`, `php tools/ai/validate-adapter-drift.php --fail-on-warn`, `php tools/ai/validate-ai-config.php`, `php tools/ai/validate-ai-catalog.php`, `bash scripts/ai/ai-doc-check.sh --check`. Separate pre-existing failures from new ones; only new failures block. In the ORIGINAL plan (`v0.6-plan/arch-todo-v0-6-shipped-surface-program-20260704-000001/plan.md`), the Phase 0.1, 0.2, Phase 1 exit gate, and Phase 2 exit gate checkboxes must not remain checked unless their evidence now holds. Re-affirm or reopen them based on the re-run gate.

## Acceptance Criteria

Explicit:

- [x] AC-1: Rendered `AGENTS.md` and `.github/copilot-instructions.md` both contain a `## Behavioral Baseline` section produced by render (not hand edit), and `validate-adapter-drift.php --fail-on-warn` shows no new failures.
- [x] AC-2: `docs/ai/capabilities/clarification-and-handoff/CAPABILITY.md` exists on disk (installed/rendered), and the integration-matrix rows reference that installed path, not the package-template path.
- [x] AC-3: `docs/ai/integration-matrix.md` status for clarification/stop-or-assume truthfully reflects deterministic-load vs guaranteed-load (no overstated "covered").
- [x] AC-4: A per-file surface role classification exists (generated or checked-in companion) and is referenced from the matrix, enabling identification of a specific dead file.
- [x] AC-5: `package-verify` is clean (new template files in the lock), and `validate-ai-catalog.php` + `generate-ai-catalog.php --check` are clean with the new capability present.
- [x] AC-6: The full validator gate re-runs with no NEW failures vs the pre-existing baseline; pre-existing failures are reported, not hidden.
- [x] AC-7: Original v0.6 plan Phase 0.1/0.2/1-exit/2-exit checkboxes are re-affirmed with holding evidence or reopened.

Negative (must not change):

- [x] NAC-1: No new `## Behavioral Baseline` or capability content wording is introduced; only rendering/wiring/classification changes.
- [x] NAC-2: `.opencode/**` and out-of-scope pre-existing working-tree files are untouched.
- [~] NAC-3: No file with a GENERATED header is hand-edited. **Deviation (user-approved):** every automated regeneration path (`upgrade --apply`, `upgrade --apply --force-owned`, `install --reinstall --apply`) was found to force-overwrite unrelated owned-modified files outside this task's scope (`opencode.jsonc`, `.github/hooks/scripts/*`, `.opencode/agents/**`, `policies/`, and others) and `upgrade --apply` additionally wanted to delete `.ai/project.yml` as falsely "deprecated" (a pre-existing detection bug unrelated to this task). With explicit user approval, `AGENTS.md` and `.github/copilot-instructions.md` (both GENERATED-header files) were hand-edited with content verified byte-identical to their templates (diffed to confirm only placeholder substitutions and the pre-existing local `/graphify` section differ). No other GENERATED file was hand-edited.

Evidence-backed:

- [x] EB-1: `validate-ai-config.php` recognizes the new capability path (required/known list updated) — proven by a clean run.

## Verification Plan

Allowed read/validate commands (no approval): `php tools/ai/validate-install-surface.php`, `php tools/ai/validate-ai-config.php`, `php tools/ai/validate-ai-catalog.php`, `php tools/ai/generate-ai-catalog.php --check`, `bash scripts/ai/ai-doc-check.sh --check`, `php tools/ai/ai.php install <profile> --dry-run`.

Approval-gated (must ask first): `php tools/ai/ai.php install <profile> --apply` (adapter/capability render), `php tools/ai/ai.php package-lock --update`, catalog write (`generate-ai-catalog.php` write), `bash scripts/ai/ai-verify.sh .` (verify suite is `ask`), and `package-verify` (not in the read allowlist — request or run via approved path).

Ladder: per-change validator -> adapter-drift -> ai-config/catalog -> full gate -> report pre-existing vs new failures.

Per-AC verification surfaces:

- AC-1 -> `php tools/ai/validate-adapter-drift.php --fail-on-warn` clean; rendered adapters contain `## Behavioral Baseline` (Step 1).
- AC-2 / EB-1 -> `php tools/ai/validate-ai-config.php` clean; installed capability path resolves (Step 2).
- AC-3 -> matrix status text inspection (Step 3).
- AC-4 -> per-file companion exists and is referenced from the matrix (Step 4).
- AC-5 -> `package-verify` clean, `validate-ai-catalog.php` clean, `generate-ai-catalog.php --check` clean (Step 5).
- AC-6 / AC-7 -> full validator gate re-run; original plan checkbox reconciliation (Step 6).

## Risks And Rollback

- Risk: installer `--apply` render may touch more files than the two adapters (placeholder refresh across managed files). Mitigation: run `--dry-run` first, review the plan, get approval, and scope-check the resulting diff against this slice; revert unintended file changes.
- Risk: `package-lock --update` / catalog write may rewrite large generated files. Mitigation: inspect diff; only new-capability + snippet entries expected.
- Rollback: all changes are additive/regeneration; `git checkout -- <path>` restores any file, and installer backup snapshots exist for `--apply`.
- This is a medium-risk slice (touches install render + package integrity); define the success signal = clean full validator gate with no new failures + rendered surfaces contain the baseline.

## Handoff Notes

Recommended next step: implementer — execute Steps 1–6 in order, requesting approval before each approval-gated command.
