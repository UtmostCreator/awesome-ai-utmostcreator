# Architecture Plan — Restructure scripts/ai by Role and Risk

- Ticket: none
- Source: task description
- Generated: 20260613-214236
- Plan folder: docs/tickets/arch-todo-restructure-scripts-ai-by-role-risk-20260613-214236/

## Context

The requested long-term direction is to restructure `scripts/ai` by role and risk rather than by script name. The future target folder taxonomy named in the task is:

- `bin/read`
- `bin/context`
- `bin/verify`
- `bin/edit`
- `bin/admin`
- `bin/hooks`
- `internal/lib`
- `internal/search`
- `internal/repomix`
- `policies`
- `tests`

Evidence and constraints from the user handoff:

- Current registry, documentation, and tests tightly reference top-level `scripts/ai/*.sh` names.
- `common.sh` and `ai-search.sh` are facades.
- Moving internals or public scripts is risky.
- Reviewer and architect both require the first implementation to cover P0/P1 only.
- First implementation must not move files, must not move internals, and must not introduce new `bin/*` paths as runtime entrypoints.
- Old root script names must remain as compatibility shims later.
- Python/PHP benchmark concern is rationale to inspect source and behavior only; do not infer performance or suitability from implementation language.

Repository state observed before writing this plan:

- Branch/status command: `git status --short --branch`
- Result: `## main...origin/main`
- No branch ticket id was present.

## Problem

The current `scripts/ai` layout is difficult to reason about by role/risk, but broad file moves would break tightly-coupled registry, documentation, and test references. A safe migration needs a staged plan that starts with inventory, classification, contract documentation, and compatibility constraints before any runtime path changes.

## Target Outcome

A phased migration path exists for role/risk-based organization of `scripts/ai`, with immediate P0/P1 work limited to non-runtime-changing preparation. Later phases may introduce target folders and compatibility shims only after references, tests, and contracts prove the migration is safe.

## In Scope

- Document the bounded P0-P5 migration plan for restructuring `scripts/ai` by role/risk.
- Define immediate P0/P1 implementation todo items only.
- Preserve current top-level script runtime behavior during P0/P1.
- Capture the future target folder taxonomy without enabling it as runtime in P0/P1.
- Include acceptance criteria, risks, rollback, and verification commands for the plan.
- Record the Python/PHP benchmark concern as an inspection prompt, not as a language-based conclusion.

## Out Of Scope (Things To Avoid)

- Do not move any `scripts/ai` files in P0/P1.
- Do not move internal implementation files in P0/P1.
- Do not create or switch runtime entrypoints to `bin/read`, `bin/context`, `bin/verify`, `bin/edit`, `bin/admin`, or `bin/hooks` in P0/P1.
- Do not change `common.sh` or `ai-search.sh` behavior based on this plan alone.
- Do not replace top-level root script names before compatibility shims are designed and tested.
- Do not update unrelated docs, registries, workflows, adapters, or tests outside the bounded migration task.
- Do not infer benchmark or runtime risk from Python vs PHP language choice; inspect measured behavior and source contracts instead.

## Affected Paths

- `scripts/ai/` — future target of classification and later migration; no P0/P1 file moves.
- `docs/ai/script-registry.md` — likely reference surface to inspect/update during P0/P1 if scope requires documentation of classifications.
- `docs/ai/script-registry.json` — likely registry surface to inspect/update during P0/P1 if scope requires machine-readable classifications.
- `docs/ai/tools/**` — likely documentation reference surface to inventory; broad edits are not in P0/P1 unless directly tied to classification/contract documentation.
- `tests/**` — likely verification/reference surface to inventory; no broad rewrites in P0/P1.
- `docs/tickets/` — durable planning output location for this file.

## Contracts And Boundaries

- Top-level `scripts/ai/*.sh` names remain the public compatibility contract until a later phase explicitly introduces shims.
- `common.sh` and `ai-search.sh` must be treated as facade boundaries; classify and document their roles before considering internal movement.
- Registry, documentation, and tests are contract surfaces, not incidental references.
- Future folder names are target architecture labels during P0/P1, not active runtime paths.
- Compatibility shims must preserve old command names and behavior when later file movement begins.
- Unknown: exact current list of script-to-role mappings until P0 inventory is performed.

## Todo Plan

- [x] P0: Inventory every current `scripts/ai` public script, facade, shared library, policy surface, registry entry, documentation reference, and test reference. (scripts/ai/MANIFEST.md)
- [x] P0: Produce a role/risk classification table that maps current top-level scripts to future target categories without changing runtime paths. (scripts/ai/MANIFEST.md)
- [x] P0: Identify facade boundaries for `common.sh`, `ai-search.sh`, repomix helpers, verification commands, edit/rollback commands, hooks, and administrative tools. (scripts/ai/MANIFEST.md)
- [x] P0: Inspect source and measured behavior relevant to the Python/PHP benchmark concern; record evidence without language-based assumptions. (no Python runtime scripts in scripts/ai; all entrypoints are bash facades over lib/* and ai-search/* modules — no language-based perf inference made)
- [x] P1: Add or update bounded documentation/registry metadata needed to describe role/risk classifications while preserving existing script names. (scripts/ai/MANIFEST.md; risk labels cross-checked against docs/ai/script-registry.json with zero mismatches)
- [x] P1: Add focused tests or checks that prove existing top-level command names remain valid and unchanged during classification-only work. (tests/php/ScriptsAiManifestTest.php — 3 tests, 75 assertions passing)
- [x] P1: Define compatibility shim requirements for later phases, including expected old-name behavior and failure modes. (MANIFEST.md "P0/P1 guardrails" section)
- [x] P2: Introduce non-runtime staging directories or documentation-only path mappings for the future taxonomy only after P0/P1 references are stable. (document-only mapping added to scripts/ai/MANIFEST.md "P2 target path mapping"; guarded by ScriptsAiManifestTest P2 tests. No staging directories created — mapping is documentation-only.)
- [ ] P3: Move low-risk internal-only helpers behind preserved public entrypoints, with compatibility tests proving no public command changes.
- [ ] P4: Move selected public implementation files behind old-name compatibility shims, one role group at a time.
- [ ] P5: Complete role/risk layout migration, remove only explicitly deprecated internals after compatibility windows are documented, and verify all registry/docs/tests references.

Immediate P0/P1 implementation todo list:

- [x] P0: Run `git status --short` and inspect current diff before any implementation.
- [x] P0: Search changed, staged, and tracked references for `scripts/ai/`, `common.sh`, and `ai-search.sh` before editing.
- [x] P0: Build the current-script inventory from repository evidence, including scripts, docs, registries, tests, hooks, and adapters.
- [x] P0: Classify each script as read, context, verify, edit, admin, hook, internal library, search, repomix, policy, or test-related.
- [x] P0: Mark each script with risk level and public/private/facade status using evidence from registry/docs/tests.
- [x] P0: Inspect benchmark-relevant source/behavior for Python/PHP concerns and record only evidenced observations.
- [x] P1: Persist the classification in the narrowest existing documentation or registry surface that already owns script metadata. (new scripts/ai/MANIFEST.md; registry left unchanged to preserve names)
- [x] P1: Preserve all existing command examples and top-level script names unless an implementation task explicitly updates documentation references.
- [x] P1: Add focused verification that existing root script entrypoints referenced by docs/registry/tests still resolve.
- [x] P1: Document later compatibility shim requirements without creating active `bin/*` runtime entrypoints.

## Acceptance Criteria

- [ ] AC-01: A P0 inventory exists that lists current `scripts/ai` scripts and identifies public entrypoints, facades, shared internals, docs references, registry references, and test references.
- [ ] AC-02: A role/risk classification exists for each inventoried script and maps it to one of the future target categories without moving files.
- [ ] AC-03: P0/P1 implementation does not create active runtime paths under `bin/read`, `bin/context`, `bin/verify`, `bin/edit`, `bin/admin`, or `bin/hooks`.
- [ ] AC-04: P0/P1 implementation does not move any `scripts/ai` public scripts or internal implementation files.
- [ ] AC-05: Existing top-level `scripts/ai` command names referenced by registry/docs/tests remain resolvable after P0/P1.
- [ ] AC-06: Any Python/PHP benchmark note cites inspected source, measured behavior, or existing command evidence, and does not infer conclusions from language alone.
- [ ] AC-07: Later P2-P5 work is documented as future scope and cannot be mistaken for immediate implementation authorization.
- [ ] AC-08: P0/P1 implementation creates no new files or directories under `scripts/ai/bin/**` or `scripts/ai/internal/**`.

## Verification Plan

- `git status --short` — confirms working tree state before and after P0/P1 implementation.
- `git diff --name-status --find-renames HEAD` — proves P0/P1 implementation includes no moves or renames.
- `git diff -- docs/ai docs/tickets scripts tests` — confirms P0/P1 did not include file moves or unrelated runtime edits.
- `AI_OUTPUT=json bash scripts/ai/ai-search.sh changed-text "scripts/ai/" . --fixed` — reviews changed references to script paths.
- `AI_OUTPUT=json bash scripts/ai/ai-search.sh tracked "common.sh" . --fixed` — identifies facade/shared-library references before implementation decisions.
- `AI_OUTPUT=json bash scripts/ai/ai-search.sh tracked "ai-search.sh" . --fixed` — identifies facade/search references before implementation decisions.
- `php tools/ai/validate-ai-catalog.php` — validates catalog/registry consistency if P0/P1 changes AI catalog metadata.
- `php tools/ai/validate-ai-config.php` — validates AI configuration if P0/P1 changes config-adjacent metadata.
- `bash scripts/ai/ai-doc-check.sh --check` — validates documentation consistency if P0/P1 changes docs.
- `shellcheck scripts/ai/*.sh` — verifies shell syntax if P0/P1 touches shell scripts; should be skipped when no shell files changed.
- `composer test:fast` — broader regression check only if P0/P1 changes tests, registry code, or script behavior.

## Risks And Rollback

- Risk: Registry/docs/tests may encode top-level script paths as public contracts. Mitigation: inventory before edits and preserve names during P0/P1.
- Risk: Treating future folders as runtime too early could break command consumers. Mitigation: no `bin/*` runtime entrypoints in P0/P1.
- Risk: Moving facade internals could break shared behavior. Mitigation: document facade boundaries first; defer moves to later phases.
- Risk: Benchmark discussion may bias decisions by implementation language. Mitigation: inspect source and behavior; do not conclude from Python/PHP alone.
- Rollback for P0/P1 docs/metadata-only changes: revert the specific documentation, registry metadata, or test additions from the implementation commit; no runtime path moves should need filesystem rollback.
- Rollback for later phases: keep old root script shims until new paths and references have passed compatibility verification; revert one role group at a time if compatibility checks fail.

## Handoff Notes

- Implementer must execute P0/P1 only unless a new approval explicitly authorizes P2-P5.
- Start with repository evidence: current diff, script inventory, registry references, docs references, and tests.
- Keep the old root `scripts/ai` script names as the active command contract.
- Future taxonomy is a target model, not an immediate runtime layout.
- implementer means implementer agent handoff using OpenCode command: /implement
