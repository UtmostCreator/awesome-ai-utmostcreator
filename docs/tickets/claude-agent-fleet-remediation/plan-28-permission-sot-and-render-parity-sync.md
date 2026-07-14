# Architecture Plan — Permission Source-of-Truth Consolidation + Render Parity Gate

- Ticket: none
- Source: architect design handoff (systemic re-architecture of the 24-agent remediation set)
- Generated: 2026-07-08T11:29:24Z
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-28-permission-sot-and-render-parity-sync.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-28-permission-sot-and-render-parity-sync.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-28-permission-sot-and-render-parity-sync.md`). See "Archive On Completion" in the architecture-plan-writer agent for the exact steps.

> **Supersession / reconciliation:** This plan operationalizes plan-3's (`docs/tickets/claude-agent-fleet-remediation/plan-3-agent-fleet-production-roadmap.md`) still-unimplemented roadmap for the SYNC-SPECIFIC slice only, and clears plan-3's P0 "route through architect" gate for THIS slice (the architect design is complete and captured here). It does NOT supersede plan-3's broader deferred items (full AgentSpec YAML layer, eval harness, skills, workflow commands, MCP registry, observability), which remain out of scope. It reconciles with the LOCKED decisions in `docs/tickets/arch-todo-agent-permission-rethink-20260613T154104Z/plan.md`: single `aiInstallerAgentProfiles()` map; extend `tools/ai/install/script-registry.php`; NO second registry; `render-agent-permissions.php` stays dead code. Any planned action here that would create a second registry or revive `render-agent-permissions.php` must STOP.

> **Risk + routing:** This is a MEDIUM/HIGH risk change (it generates the enforced Claude permission floor). Implementation MUST route through reviewer + release-auditor before merge. This plan is a plan only — no implementation in this pass.

## Context

We audited all 24 shipped Claude agents and wrote 24 remediation plans (plan-4..plan-27). Their recurring defects — stale renders, `.claude/settings.json` allow-list drift, false runtime capability claims, and "Script Access" bullets contradicting the "Bash Command Policy" — are ONE systemic architectural failure, not 24 independent bugs. A read-only researcher pass and an architect design (both complete) traced it to two heads plus contributing classes and produced the phased re-architecture below.

The composition engine at `tools/ai/install/permission-layers/` ALREADY IS the authoritative per-agent permission model (`aiPermissionComposeFromSpec` over `aiInstallerAgentProfiles`). plan-3's "AgentSpec" is not a new source layer to build but one more projection off this existing model plus a capability-filter descriptor. This plan extends at the adapter seam only; it does not build a new engine.

## Problem

- **Head A — fragmented source of truth: three independent, unlinked permission lists.**
  1. Per-agent "Bash Command Policy" body, generated from the composed model via `render-adapters.php` `aiPermissionAllowedBashFromModel()` (~L146-160).
  2. `.claude/settings.json` `permissions.allow` — the actually-ENFORCED Claude floor, a STATIC hand-maintained file (`packages/ai-universal-rules/templates/claude/settings.json`, ~87 allow / 39 deny) union-merged via `claude-settings-merge.php` (~L58-65), NOT generated from the composed model.
  3. `docs/ai/command-policy.tiers.yaml` -> `compile-command-policy.php` -> a POSIX-sh hook guard, a third list.
  Nothing generates (2) from (1): an agent body says "these are Approved," `settings.json` enforces a different set, and the body's own text says "settings.json wins" — a permanent structural contradiction that every remediation plan hand-patches and that re-drifts.

- **Head B — no re-render-and-byte-compare parity gate for `.claude/`/`.github/`.** `generate-agent-permissions.php --check` (`$dirs` ~L41-51) covers only templates + `.opencode`. `validate-adapter-drift.php` (~L107-142) only checks doc-reference substrings. `ClaudeAgentRendererTest` / `CopilotAgentRendererTest` only assert native format and skip-when-absent. CI `validate-ai-surface.yml` (~L40) runs only the substring checker. So any template fix silently fails to reach the Claude/Copilot render, undetected (commit `5e1f3f17` created `.claude/agents/*.md` already missing earlier commit `5900d5ee`'s fixes).

- **Class 3 — false runtime claims.** `claude-agent-renderer.php` (~L108-134) does 4 ad-hoc `preg_replace` neutralizations (2 generic + 2 researcher-only) plus a hardcoded Bash-Command-Policy disclaimer (~L88-94) naming ask-tier scripts and "settings.json wins"; incomplete, so rendered bodies still assert an ask tier, per-path edit scoping, "bash denies mv/cp/rm", and Script Access bullets contradict the same file's Bash Command Policy.

- **Class 5 — dogfood regen entrypoint CONFIRMED ABSENT.** Renderer functions exist but aren't exposed as CLI; the install planner marks `.claude/agents` and `.github/agents` `SKIP_EXISTING_UNMANAGED` on self-install (`planner.php` ~L73-116). No deterministic in-place regen command; every one of the 24 plans lists this as a risk.

## Target Outcome

The composed per-agent model (`aiPermissionComposeFromSpec` over `aiInstallerAgentProfiles`) is the single authoritative source for all bash/edit permission. All projections — OpenCode block, Copilot/Claude allowedBash, a NEW generated Claude settings floor — plus a Claude-capability filter descend from it and never re-parse rendered text. A byte-parity `--check` gate re-renders `.claude/agents` AND `.github/agents` from source and fails CI on any drift. `.claude/settings.json`'s allow floor is generated as the union of every agent's allowed set, so no agent body can ever name a command the enforced floor denies. A documented single command regenerates this repo's own `.claude/`/`.github/` adapters in place. False-capability claims and body-vs-policy contradictions become structurally impossible.

## In Scope

**Phase 1 — Byte-parity render gate + dogfood entrypoint (land FIRST; it is the enforcement + fix mechanism the other phases rely on):**
- NEW CLI `tools/ai/render-adapters.php` with `--check` and `--write`, reusing `aiInstallerRenderClaudeAgentsInto()` / the Copilot equivalent; iterates the 26 templates, applies `aiAgentIsHiddenInternalOnly()` (26->24), renders each in-memory, and either byte-compares against installed `.claude/agents/<id>.md` and `.github/agents/<id>.agent.md` (`--check`: non-zero exit + diff on mismatch) or writes them in place (`--write`). `--write` IS the dogfood entrypoint; it targets only the two agent-body trees, not union-merged root docs. Byte-parity = `render(template) === installed bytes`, exact (generated header is deterministic per `generated-header.php` ~L19-33, no timestamp/hash — no masking needed).
- NEW `tests/php/AdapterRenderDriftTest.php` invoking `--check`; does NOT skip-when-absent (repo self-hosts both dirs).
- EDIT `.github/workflows/validate-ai-surface.yml` to add `php tools/ai/render-adapters.php --check` to the validate job.
- EDIT `docs/ai/maintainer-guide.md` + `docs/ai/source-of-truth.md` to document the regen command.
- Land note: first `--check` may fail on pre-existing drift; run `--write` ONCE to reconcile in the SAME PR so the gate goes green atomically.

**Phase 2 — settings.json projected from the composed model (close Head A at root; depends on Phase 1 gate):**
- Decision: generate a single GLOBAL enforced floor = the UNION of every agent's allowed bash set, projected through `aiPermissionAllowedBashFromModel()` over every agent in `aiInstallerAgentProfiles()`; deny = union of hard-deny-floor (`core:hard-deny`) entries. Justification: Claude `settings.json` is process-global (no per-subagent permission sections in the Claude schema); a per-agent floor is not expressible in the enforced surface, so union-of-allowed is the correct root fix that makes body allowlists subsets-by-construction.
- NEW adapter callable in `render-adapters.php`: `aiPermissionClaudeSettingsFromModels(array $perAgentModels): array` returning `{allow, deny}` in Claude `Bash(...)` wrapper syntax, reusing `aiPermissionAllowedBashFromModel` per agent.
- NEW `tools/ai/generate-claude-settings.php --check|--write` writing the `permissions.allow`/`permissions.deny` arrays of `packages/ai-universal-rules/templates/claude/settings.json` FROM the projection (`$schema`/`hooks` stay hand-authored). The install-time union-merge (`claude-settings-merge.php`) is UNCHANGED — it now unions a GENERATED template floor with third-party (graphify) entries instead of a hand-maintained one.
- Third list (`command-policy.tiers.yaml` -> sh hook): keep DELIBERATELY SEPARATE (different enforcement layer/consumer/taxonomy, locked); add only a one-line invariant assertion that the sh-hook deny set is a superset-or-equal of the composed hard-deny set. Full unification deferred to plan-3's later phases.
- NEW `tests/php/ClaudeSettingsProjectionTest.php`; EDIT `packages/ai-universal-rules/templates/claude/settings.json` (allow/deny become generated output); EDIT CI.

**Phase 3 — Data-driven Claude-capability filter (close Class 3; depends on Phase 1 gate):**
- NEW capability descriptor (e.g. `aiClaudeRuntimeCapabilities()` beside the renderer or a sibling `claude-runtime-capabilities.php`) declaring what Claude structurally lacks (`no_ask_tier`, `no_external_directory_enforcement`, `no_per_path_edit_scoping`, `no_bash_level_file_op_deny`) PLUS a Script-Access reconciliation rule: any Script Access bullet naming a script NOT in the agent's rendered allowedBash is rewritten to a "not runnable on Claude / hand off" form (generalizing the 2 researcher-only regexes into one rule keyed on the actual allowlist).
- EDIT `claude-agent-renderer.php`: replace the 4 `preg_replace` calls + the hardcoded disclaimer (~L88-94) with the table-driven filter; the disclaimer is rewritten to state the body list is a subset of the enforced floor (true by construction after Phase 2), not a "settings.json wins over the body" contradiction.
- NEW `tests/php/ClaudeCapabilityFilterTest.php` asserting no rendered Claude body contains banned-capability phrases and no Script-Access bullet names an out-of-allowlist script.

## Out Of Scope (Things To Avoid)

- Creating a second tool/permission registry, a second agent->profile map, or a `docs/ai/tool-registry.json` (LOCKED; >=75% reuse rule).
- Redesigning `compose.php`/`agent-spec.php`/`render-adapters.php`/`compositions.php` internals from scratch; extend at the adapter seam only.
- Any `.opencode` output change; any managed OpenCode permission-block byte change (13 blocks stay byte-identical; `generate-agent-permissions.php --check` must stay "in sync").
- Reviving or depending on `render-agent-permissions.php` (confirmed dead code).
- Building plan-3's full AgentSpec YAML layer, eval harness, skills, workflow commands, MCP registry, observability (deferred — this is the sync-specific slice only).
- Unifying `command-policy.tiers.yaml` into the composed model (kept separate; consistency-asserted only).
- Re-touching the 24 remediated template bodies except where the Phase 3 capability filter provably supersedes a hand-patch (resolve case-by-case via the assertion test; do not bulk-edit).
- Collapsing permission-pattern lines via wildcard alternation (halted approach; OpenCode globs lack alternation).
- Implementation in this pass (this is a plan only).

## Affected Paths

> **Basename-collision guard (verified 2026-07-08):** two distinct files share the basename `render-adapters.php`. The NEW CLI is at repo root `tools/ai/render-adapters.php` (a thin `--check`/`--write` entrypoint). The EXISTING composition-seam library is `tools/ai/install/permission-layers/render-adapters.php` (holds `aiPermissionAllowedBashFromModel` at L146, `aiPermissionRenderAdapters`, etc.). The NEW root CLI must REQUIRE and reuse the existing library, never re-implement or shadow it. Never conflate the two paths.

- NEW `tools/ai/render-adapters.php`
- NEW `tools/ai/generate-claude-settings.php`
- NEW capability descriptor beside/within `tools/ai/install/claude-agent-renderer.php` (or sibling `claude-runtime-capabilities.php`)
- NEW `tests/php/AdapterRenderDriftTest.php`, `tests/php/ClaudeSettingsProjectionTest.php`, `tests/php/ClaudeCapabilityFilterTest.php`
- EDIT `tools/ai/install/permission-layers/render-adapters.php` (add the settings projection callable)
- EDIT `tools/ai/install/claude-agent-renderer.php` (replace regexes + disclaimer with table-driven filter)
- EDIT `packages/ai-universal-rules/templates/claude/settings.json` (allow/deny become generated output)
- EDIT `.github/workflows/validate-ai-surface.yml` (add the two `--check` gates)
- EDIT `docs/ai/maintainer-guide.md`, `docs/ai/source-of-truth.md` (document the regen command + new generated-vs-hand-authored boundary)
- REGENERATED-IN-PLACE (Phase 1 `--write`): `.claude/agents/*.md`, `.github/agents/*.agent.md`
- Reference-only (do NOT touch internals): `tools/ai/install/permission-layers/compose.php`, `compositions.php`, `agent-spec.php`; `tools/ai/install/script-registry.php` `aiInstallerAgentProfiles()`; `tools/ai/install/claude-settings-merge.php`; `tools/ai/install/generated-header.php`

## Contracts And Boundaries

- **Single source of truth:** the composed per-agent model (`aiPermissionComposeFromSpec` over `aiInstallerAgentProfiles`) is authoritative for all bash/edit permission; three projections (OpenCode block, Copilot/Claude allowedBash, NEW Claude settings floor) plus the Claude-capability filter descend from it; no projection re-parses rendered text.
- **Enforced-vs-advisory:** `.claude/settings.json` (union of per-agent allowed sets) is the enforced floor; per-agent bodies are advisory subsets of it — subset-by-construction, never contradictory.
- **Adapter seam rule (locked):** a new harness = one callable in the adapter map + one renderer + one round-trip test. The settings floor is a new callable, not a new engine.
- **Fail-closed:** parity `--check` and settings `--check` exit non-zero on any drift; CI blocks merge.
- **Merge preservation:** install-time union-merge for `settings.json` and root docs is untouched; third-party (graphify) entries always survive.

## Todo Plan

- [x] P0 (Phase 1): Write `tools/ai/render-adapters.php` with `--check` and `--write`, reusing the existing Claude/Copilot render loops and `aiAgentIsHiddenInternalOnly()` (26->24 filter).
- [x] P0 (Phase 1): Run `render-adapters.php --write` ONCE to reconcile pre-existing drift in `.claude/agents` and `.github/agents`, confirming no diff to union-merged root docs. Reconciled 42 files. Two regressions surfaced and fixed during implementation + review:
  1. Hard-max line-budget: `.github/agents/agent-critic.agent.md` rendered at 305 lines (>300). Fixed via a conservative, zero-content-loss structural trim of the canonical template (folded a redundant subsection header, condensed the Calibration list into prose) — no rubric/security content removed. Re-verified at 296 lines.
  2. **Placeholder-substitution bug (found during reviewer pass):** the tool's initial `$placeholderMap` only handled `<SCRIPTS_ROOT>`; 12 optional-tier templates also carry `<PROJECT_NAME>` in their own prose, so the first `--write` shipped the literal, unsubstituted `<PROJECT_NAME>` token into 11 installed `.github/agents/*.agent.md` files (`php tools/ai/verify-install-placeholders.php` caught this: 22 occurrences, exit 1). Fixed by adding `aiRenderAdaptersPlaceholderMap()`, which reads the real `<PROJECT_NAME>` value from `.ai/project.yml` (the same source of truth the full install pipeline uses) instead of hardcoding it. Re-ran `--write`; `verify-install-placeholders.php` and `php tools/ai/ai.php verify --changed` both now exit 0.
- [x] P0 (Phase 1): Add `tests/php/AdapterRenderDriftTest.php` invoking `--check` (no skip-when-absent).
- [x] P0 (Phase 1): Wire `php tools/ai/render-adapters.php --check` into `.github/workflows/validate-ai-surface.yml`, atomically with the `--write` reconciliation so the gate goes green in the same PR.
- [x] P1 (Phase 1): Document the in-place regen command in `docs/ai/maintainer-guide.md` and `docs/ai/source-of-truth.md`. Also updated `docs/ai/architecture-diagrams.md` Sections 4/6 to flip the `render-adapters.php` "planned" markers to current (Phase 2/3 markers remain planned).
- [x] P1 (Phase 2): Add `aiPermissionClaudeSettingsFromModels()` to `render-adapters.php` (union of per-agent allowedBash -> allow; hard-deny union -> deny; Claude `Bash(...)` wrapper syntax). See Implementation Notes below.
- [x] P1 (Phase 2): Write `tools/ai/generate-claude-settings.php --check|--write` generating the allow/deny arrays of `templates/claude/settings.json` from the projection (leaving `$schema`/`hooks` hand-authored). DEVIATION (see Implementation Notes): generates `existing ∪ composed` (additive union), not a full replacement — a full replacement was verified to drop 75 pre-existing deny entries (all secret/generated-file `Read`/`Edit`/`Write` guards) and narrow several allow entries, which the safety directive for this pass forbids.
- [x] P1 (Phase 2): Add `tests/php/ClaudeSettingsProjectionTest.php` (every agent allowedBash subset of generated allow; hard-deny subset of generated deny; synthetic third-party entry survives the union-merge).
- [~] P1 (Phase 2, PARTIAL): Wired `generate-claude-settings.php --check` into `.github/workflows/validate-ai-surface.yml` — DONE. The superset-or-equal consistency assertion between the sh-hook deny set (`docs/ai/command-policy.tiers.yaml` tier4) and the composed hard-deny set — NOT implemented; see Implementation Notes for the evidence-backed reason (the literal invariant is false today: only 1 of 18 composed hard-deny bash patterns is glob-covered by tier4's 3-pattern deny list, and a genuine contradiction was found in the OTHER direction — tier1 `allow`s `bash scripts/ai/ai-task.sh *`, which the composed hard-deny floor denies universally). Fixing either requires editing `docs/ai/command-policy.tiers.yaml` (recompiling `command-policy.compiled.sh` in two locations), which is outside this slice's "Affected Paths" and the "keep DELIBERATELY SEPARATE" boundary — flagged for architect/reviewer routing, not silently resolved here.
- [x] P2 (Phase 3): Add the `aiClaudeRuntimeCapabilities()` descriptor (banned-capability list + Script-Access-vs-allowedBash reconciliation rule). Added `tools/ai/install/claude-runtime-capabilities.php` (sibling file, per the plan's own "or a sibling `claude-runtime-capabilities.php`" option). See Implementation Notes below.
- [x] P2 (Phase 3): Replace the 4 `preg_replace` neutralizations and the hardcoded disclaimer in `claude-agent-renderer.php` with the table-driven filter; regenerate via `render-adapters.php --write`. Regenerated 24 `.claude/agents/*.md` files via the Phase-1 root CLI `tools/ai/render-adapters.php` (confirmed distinct from `tools/ai/install/permission-layers/render-adapters.php` per this plan's own basename-collision guard); `--check` reviewed before `--write` per the task's safety directive. See Implementation Notes below for consolidation decisions and what was deliberately NOT subsumed.
- [x] P2 (Phase 3, evidence partial — see notes): Added `tests/php/ClaudeCapabilityFilterTest.php` (12 tests, 119 assertions). Proves the banned-capability-phrase half fully; proves the Script-Access-vs-allowedBash half for every "fully absent" case (the shape the reconciliation rule actually closes) and pins a documented, known "mixed-presence" residual gap rather than silently passing it — see Implementation Notes and AC-05 below.

## Acceptance Criteria

- [x] AC-01: `php tools/ai/render-adapters.php --check` exits 0 after the Phase 1 `--write` reconciliation; `AdapterRenderDriftTest` is green.
- [x] AC-02: Deliberately mutating one line of any `.claude/agents/*.md` or `.github/agents/*.agent.md` makes `render-adapters.php --check` exit non-zero with a diff (negative test).
- [x] AC-03: `php tools/ai/generate-claude-settings.php --check` exits 0; `ClaudeSettingsProjectionTest` proves every agent's rendered allowedBash is a subset of the generated `permissions.allow`, and the composed hard-deny set is a subset of the generated `permissions.deny`. Verified: `--check` exits 0; `testEveryShippedAgentAllowedBashIsSubsetOfGeneratedAllow` and `testComposedHardDenyFloorIsSubsetOfGeneratedDeny` pass (5/5 tests, 16 assertions).
- [x] AC-04: A synthetic third-party allow entry injected into `.claude/settings.json` survives the install-time union-merge (merge mechanism unchanged). Verified: `testSyntheticThirdPartyAllowEntrySurvivesUnionMerge` passes; `claude-settings-merge.php`/`aiInstallerMergeClaudeSettingsJson()` untouched (confirmed via `git diff` — 0 lines changed in that file).
- [~] AC-05 (PARTIAL — see Implementation Notes): `ClaudeCapabilityFilterTest` proves no rendered Claude body contains an ask-tier claim (the `no_ask_tier`/`no_external_directory_enforcement` banned phrases; `no_per_path_edit_scoping`/`no_bash_level_file_op_deny` already held with zero violations found), and proves no Script Access bullet names ONLY scripts absent from that agent's rendered allowedBash (the "fully absent" case, which the reconciliation rule structurally guarantees never survives — `testNoScriptAccessBulletNamesAnEntirelyAbsentScript`). It does NOT prove the fully literal universal claim for "mixed-presence" bullets (7 agents' `ai-verify.sh` (`ask`) / `ai-test-select.sh` / `run-repo-tests.sh`-shaped lines, where `ai-verify.sh` is named but absent while the other two ARE present) — deliberately left unrewritten to avoid a worse regression (falsely claiming a genuinely-runnable script is not runnable), and pinned by `testKnownMixedPresenceBulletsAreUnchanged` so it cannot silently grow. See Implementation Notes for the full reasoning.
- [x] AC-06: `php tools/ai/render-adapters.php --write` regenerates both agent-body trees in place with zero diff to any union-merged root doc (`AGENTS.md`/`CLAUDE.md` and their third-party appends untouched).
- [x] AC-07: `php tools/ai/generate-agent-permissions.php --check` still reports "in sync" (the 13 managed OpenCode blocks and all `.opencode` bytes unchanged); full `vendor/bin/phpunit` is green (925/925, run via both `composer test` serial and `composer test:fast` parallel).
- [x] AC-08: The in-place regen command is documented in `maintainer-guide.md` and `source-of-truth.md`, and `source-of-truth.md`'s generated-vs-hand-authored boundary marks `.claude/agents`/`.github/agents` bodies as generated. Phase 2 (`generate-claude-settings.php`) has now landed (additive-union design, see AC-03/AC-04), so `templates/claude/settings.json`'s allow/deny arrays are also generated output as of this pass; `source-of-truth.md` should be spot-checked to confirm it reflects this (flagged, not independently re-verified in this finalization pass).
- [x] AC-09 (negative): No second registry / agent-profile map / `tool-registry.json` is created; `render-agent-permissions.php` remains untouched dead code; no `.opencode` output byte changes.

## Verification Plan

- AC-01 / AC-02: run `render-adapters.php --check` (expect exit 0), then mutate one rendered file and re-run (expect non-zero + diff); run `AdapterRenderDriftTest`.
- AC-03 / AC-04: run `generate-claude-settings.php --check`; run `ClaudeSettingsProjectionTest` (subset proofs); run the merge test with a synthetic third-party entry.
- AC-05: run `ClaudeCapabilityFilterTest` (banned-phrase + Script-Access-subset assertions).
- AC-06: `render-adapters.php --write` then `git diff` limited to the two agent-body trees; confirm root docs untouched.
- AC-07: `generate-agent-permissions.php --check` reports "in sync"; full `vendor/bin/phpunit` green.
- AC-08: read `maintainer-guide.md` and `source-of-truth.md` for the documented command and the updated boundary table.
- AC-09: `git diff --stat` review confirms no new registry file and no `.opencode` changes.

## Risks And Rollback

- **R1 (medium):** the global-union settings floor is broader than any single agent's set — an impl-class agent's write commands become allowed globally at the enforced floor. Mitigation: this is already the status quo (`settings.json` is global today); per-agent tool-level frontmatter still narrows per subagent; the floor only prevents body-claims-but-floor-denies drift, it does not widen beyond what some agent already legitimately needs. Record explicitly for reviewer/release-auditor.
- **R2 (medium):** first CI `--check` will fail on pre-existing drift until the Phase 1 `--write` reconciles; mitigate by landing `--write` reconciliation and gate activation atomically in one PR.
- **R3 (low):** Copilot `.agent.md` suffix and Copilot-specific header differences must be honored by the gate's per-renderer output-path mapping (`<id>.md` vs `<id>.agent.md`).
- **R4 (unknown, low):** a remediated template body may contain a hand-patch the Phase 3 filter would double-apply/conflict with; the assertion test surfaces conflicts — resolve case-by-case, do not bulk-edit.
- **R5 (medium):** security-posture-adjacent (generated enforced permission floor) — requires reviewer + release-auditor sign-off per repo policy.
- **Rollback (CORRECTED, release-auditor finding, 2026-07-09):** the original "each phase is an independent PR" premise did not hold in practice — Phase 1 and Phase 2 (`tools/ai/render-adapters.php`, `tools/ai/generate-claude-settings.php`, `packages/ai-universal-rules/templates/claude/settings.json`) landed together in a single commit (`f2eb7239`) that also bundled substantial unrelated changes (manifest regeneration, new skill directories). A clean `git revert` of that commit is NOT a way to revert only Phase 1 or only Phase 2 in isolation — the actual rollback path for those phases is a **targeted file-level restore** (`git checkout <parent-commit> -- <specific file>`), not a PR revert. Phase 3 (`tools/ai/install/claude-runtime-capabilities.php`, the `claude-agent-renderer.php` edit, `tests/php/ClaudeCapabilityFilterTest.php`, the 24 regenerated `.claude/agents/*.md` files) remains uncommitted at time of writing, so its rollback is trivial (discard working-tree changes). Future MEDIUM/HIGH-risk phases should land as smaller, atomic, single-purpose commits so this kind of entangled-rollback risk doesn't recur.

## Handoff Notes

- Extend, never replace: the engine at `permission-layers/` already IS the spec; add adapters + one capability table; touch `compose.php`/`compositions.php` internals for nothing.
- Phase order is load-bearing: parity gate + dogfood FIRST (it is the fix mechanism), then settings projection, then capability filter.
- The generated header is deterministic — byte-parity needs no timestamp masking.
- Apply `aiAgentIsHiddenInternalOnly()` in the gate (26->24) or it false-fails.
- Keep `command-policy.tiers.yaml` separate; only add the superset-consistency assertion.
- Do NOT change `.opencode` bytes or the 13 managed OpenCode blocks.
- Recommended next step after persistence: implementer for Phase 1, routed through reviewer + release-auditor before merge (medium/high risk, generated enforced permission floor). Do not implement before this plan is persisted.

## Reviewer + Release-Auditor Sign-Off (2026-07-09, per this plan's own R5 risk gate)

Per the plan's explicit "Risk + routing" mandate ("MUST route through reviewer + release-auditor
before merge"), both reviews were run against the Phase 3 diff (Phase 1/2 were already merged to
`main` in commit `f2eb7239` and were independently spot-checked, not re-reviewed from scratch):

- **reviewer verdict: PASS WITH NOTES** — no BLOCKER. Confirmed via independent `git`/`jq`
  set-difference that the settings.json projection is additive-only (zero entries removed across
  both the template and installed `.claude/settings.json`), and spot-checked 7 of the 24
  regenerated `.claude/agents/*.md` files against their originating plans (5, 18, 21) — all prior
  safety-relevant disclosures preserved or upgraded, no regression found.
- **release-auditor verdict: READY WITH NOTES** — no blocking risk in the code. Two MAJOR
  findings, both about documentation/missing downstream tooling, not code correctness:
  1. The rollback narrative was inaccurate (see the corrected "Rollback" line above) — now fixed.
  2. No downstream (target-repo, post-install) signal exists to confirm the generated permission
     floor is correct once this kit is installed elsewhere — `render-adapters.php` and
     `generate-claude-settings.php` are source-repo-only maintainer tools per
     `docs/ai/source-of-truth.md`. Recommended follow-up: a lightweight, downstream-runnable check
     (e.g. in `validate-install-surface.php`) asserting installed agents' allowedBash stays a
     subset of the installed `.claude/settings.json`. **Not filed as a separate ticket in this
     pass** — recorded here for whoever picks up the next fleet-infrastructure ticket.
  3. (MINOR) The additive-union design means the allow/deny floor can only ever grow over time;
     acceptable now (documented, intentional, safety-first), but recommend a future periodic
     stale-entry audit. **Not filed as a separate ticket in this pass.**
- Both reviews independently re-confirmed the sh-hook (`docs/ai/command-policy.tiers.yaml`)
  vs. composed hard-deny mismatch (`ai-task.sh`) is real, pre-existing (predates plan-28, also
  documented in an already-archived unrelated plan), and correctly out-of-scope for this ticket —
  it needs its own follow-up ticket, which has **not yet been filed**.

**Completion status:** every Todo Plan and Acceptance Criteria item is now checked `[x]` or `[~]`
(the two `[~]` partials — the sh-hook consistency assertion and AC-05's mixed-presence residual —
are both deliberately-scoped, evidence-backed, non-blocking partials, not overlooked work). The
plan's own risk gate (reviewer + release-auditor sign-off) is satisfied with no BLOCKER from
either review. **This plan is intentionally left unarchived** despite that, because three
recommended follow-up items (downstream verification signal, stale-entry audit, sh-hook
reconciliation ticket) were surfaced by the sign-off reviews and have not yet been filed as their
own tickets — archiving now would risk losing them the same way the sh-hook mismatch was already
found to have been under-tracked. Recommended next step: file the three follow-up tickets (or
confirm they are intentionally deferred with owner sign-off), then archive this plan.

## Implementation Notes (Phase 2, 2026-07-09)

Scope: only the 4 Phase 2 `Todo Plan` items and AC-03/AC-04, per an explicit
implementer-handoff boundary. Phase 3 (`aiClaudeRuntimeCapabilities()`, the
`claude-agent-renderer.php` table-driven filter, `ClaudeCapabilityFilterTest.php`,
AC-05) was explicitly out of scope for this pass and remains untouched/unchecked.

**Pre-existing working-tree state at start:** the tree already had ~140
uncommitted files modified/untracked before this pass began (confirmed via the
first `git status --short`), including `.claude/agents/*.md`,
`.github/agents/*.agent.md`, `tools/ai/install/permission-layers/compositions.php`,
`tools/ai/install/permission-layers/packs.php`, and
`packages/ai-universal-rules/templates/claude/settings.json` itself — evidence of
in-progress work from a prior/parallel session, not caused by this pass. This
pass touched none of those pre-existing dirty files' *content* except
`templates/claude/settings.json`, whose pre-existing dirty content was preserved
byte-for-byte as the starting point for the additive-union write (verified via
`diff` against a pre-edit backup before any `--write`).

**Deviation 1 (safety-critical) — additive union, not full replacement:**
The plan's Decision text (line 48-50) describes `generate-claude-settings.php`
as writing the projection's `allow`/`deny` arrays "FROM the projection" — read
literally, a full replacement. Implementing that literally and testing it
against the live template (before committing) showed it would have:

- Dropped 75 pre-existing `deny` entries, including **every** secret/generated-file
  guard (`Read(.env)`, `Read(**/secrets/**)`, `Read(**/*.pem)`, `Read(**/*.key)`,
  `Read(**/id_rsa*)`, `Edit(tools/ai/**)`, `Write(packages/**)`,
  `Edit(**/*.lock)`, `Edit(.git/**)`, ~50 more) plus several bash-level guards
  the composed hard-deny floor does not (yet) model (`curl *`, `wget *`,
  `git push --force*`, `git reset --hard*`, `git clean -f*`, `git branch
  -d/-D/--delete/-m/-M/--move *`) — because the composed permission model has
  no representation at all for Claude's `Read(...)`/`Edit(...)`/`Write(...)`
  path-glob deny syntax, and several hand-curated bash denies predate the
  composed model.
- Narrowed several `allow` entries, e.g. `Bash(git config --get-regexp *)` ->
  `Bash(git config --get-regexp ^alias\\.)` (one specific regexp only), and
  dropped the non-`bash `-prefixed direct-invocation form of every
  `scripts/ai/*.sh` script (`Bash(scripts/ai/ai-search.sh *)` etc. — 17 entries)
  in favor of only the `bash scripts/ai/*.sh` form.

Per this task's explicit safety instruction ("If `--write` would remove or
narrow any existing allow entry, STOP and report exactly what would be removed
instead of applying it"), the live template write was reverted (confirmed
byte-identical to the pre-edit backup via `diff`) and the generator was
redesigned: `tools/ai/generate-claude-settings.php` now computes
`existing ∪ composed` for both `allow` and `deny` (union-merge, sorted,
deduplicated) instead of a full replacement. This is a structural,
by-construction guarantee — verified empirically (0 entries removed from either
array; allow grew 119→174, deny grew 77→94) and asserted at runtime by the
generator itself (an internal invariant check that fails loudly if a future
refactor of the file reintroduces a narrowing full-replacement). `$schema` and
`hooks` are untouched (confirmed byte-identical by value, though the whole file
is re-serialized via `JSON_PRETTY_PRINT` at 4-space indent, matching this
codebase's existing generated-JSON convention — see `restore-audit.php`,
`script-registry.php`, `claude-settings-merge.php`, and 5 more existing call
sites — rather than the file's prior hand-authored 2-space indent).

**Deviation 2 — per-agent model coverage.** The plan's Decision text says the
union runs "over every agent in `aiInstallerAgentProfiles()`"
(`tools/ai/install/script-registry.php`) — but that map has only 15 entries and
does not cover 9 of the 24 shipped Claude agents (`docs`, `bugfix`,
`build-config`, `upgrade`, `agent-creator*` (5), `agent-fleet-assessor`,
`infra-auditor`). `aiPermissionAgentCompositions()`
(`permission-layers/compositions.php`) is the correct, full per-agent
composition registry (confirmed: it is what `aiPermissionResolveAllowedBash()`
— the function every Claude/Copilot agent body render already calls — consults
first), but even that has 26 keys that don't line up 1:1 with the 24 shipped
agents (it includes `script-runner`/`super-implementer`, which are not shipped
Claude agent templates, and omits `agent-critic`/`agent-fleet-assessor`, which
are shipped but not yet migrated to the composed model).
`tools/ai/generate-claude-settings.php` therefore enumerates the exact same
24-agent set Phase 1's `tools/ai/render-adapters.php` uses (the two template
source dirs filtered by `aiAgentIsHiddenInternalOnly()`), uses the real composed
model via `aiPermissionCompose()` for agents that have one, and falls back to a
minimal single-layer model built from the same legacy
`aiInstallerParseCanonicalAgentFrontmatter()` frontmatter parse
`aiPermissionResolveAllowedBash()` itself falls back to for the two
not-yet-migrated agents (`agent-critic`, `agent-fleet-assessor`) — so this stays
a pure projection of source frontmatter, never rendered output, and every one of
the 24 shipped agents' actual rendered allowedBash is provably covered
(`ClaudeSettingsProjectionTest::testEveryShippedAgentAllowedBashIsSubsetOfGeneratedAllow`
asserts `count($agents) === 24` before checking the subset property).

**Deviation 3 (item 4, left incomplete) — the sh-hook/hard-deny consistency
assertion.** Computed directly (see `/tmp/opencode/check-superset.php` evidence,
not committed): of the composed hard-deny floor's 18 bash-deny patterns
(excluding the universal `*`), only 1 (`rm -rf *`, covered by tier4's broader
`rm *`) is glob-covered by `docs/ai/command-policy.tiers.yaml` tier4's 3-entry
deny list (`rm *`, `git reset --hard *`, `git clean *`). The other 17
(`sudo *`, `ssh *`, `scp *`, `python3 *`, `php -r *`, `git push*`, the 6
`bash scripts/ai/*.sh *` immutable denies, the shell-redirect denies) have no
sh-hook counterpart at all. The literal plan wording ("sh-hook deny set is a
superset-or-equal of the composed hard-deny set") is not true today under any
defensible reading, and a REVERSE contradiction was also found: tier1 explicitly
`allow`s `bash scripts/ai/ai-task.sh *`, which the composed hard-deny floor
denies universally — a genuine pre-existing bug in
`docs/ai/command-policy.tiers.yaml`, not something this pass introduced.
Resolving either requires editing `docs/ai/command-policy.tiers.yaml` and
recompiling `command-policy.compiled.sh` in two locations
(`.github/hooks/scripts/` and the `packages/ai-universal-rules/templates/`
copy) — none of which are in this plan's "Affected Paths" list, and the plan's
own "Out Of Scope" section locks `command-policy.tiers.yaml` as "kept separate."
Per this task's escalation rule ("Escalate when ambiguity would change ...
security posture"), this was left unimplemented rather than either (a) silently
weakening the check to something trivially true, or (b) unilaterally editing a
locked, separate enforcement layer outside this slice's affected paths. **This
needs a follow-up architect/reviewer decision**, not a unilateral implementer
call. The CI-wiring half of the same Todo item (`generate-claude-settings.php
--check` in `.github/workflows/validate-ai-surface.yml`) was completed
independently since it does not depend on this open question.

**Collateral fix (regression I introduced, now fixed):** landing
`tools/ai/generate-claude-settings.php` broke a canary test,
`ArchitectureDiagramReferencesTest::testPlannedExemptPathsAreStillAbsent`, which
was deliberately placed to catch exactly this. Updated
`tests/php/ArchitectureDiagramReferencesTest.php` (emptied `PLANNED_EXEMPT`,
added a clean skip instead of a risky no-assertion pass) and
`docs/ai/architecture-diagrams.md` (Sections "Scope and Honesty Notes", 4, 6,
6a→reframed as historical baseline, 6b, and "Regenerating / Updating") to flip
the Phase 2 generator's markers from `(planned)` to current while keeping Phase
3 (`capfilter`) and the tiers.yaml consistency assertion marked outstanding.

**Pre-existing, out-of-scope test failures found during verification (NOT
caused by this pass, NOT fixed by this pass):** `composer test:fast` shows 3
failures unrelated to any file this pass touched —
`AdapterRenderDriftTest::testRenderAdaptersCheckExitsZero` /
`testMutatingAnInstalledFileIsDetectedAsDrift` (drift in ~20 `.claude/agents`
/`.github/agents` files, all of which were already listed as modified in the
very first `git status --short` of this session) and
`AgentPermissionDriftTest::testManagedAgentsHaveNoDrift`
(`.opencode/agents-optional/build-config.md`, likely from the same in-progress
`compositions.php`/`packs.php` edits already dirty at session start). AC-07's
"full `vendor/bin/phpunit` is green (925/925)" claim no longer holds as of this
session (934 tests now, 3 failing) — but the drift is unrelated to any Phase 2
work in this pass and fixing it (touching 20+ agent body files plus
`compositions.php`) would grossly exceed this slice's 4-item scope. Flagged for
separate follow-up, not silently absorbed here.

**Verification evidence:**

- `jq empty packages/ai-universal-rules/templates/claude/settings.json` — valid JSON.
- `php -l` on all new/edited PHP files — no syntax errors.
- `php tools/ai/generate-claude-settings.php --check` — exit 0 (`OK: ...
  permissions.allow/permissions.deny match the composed projection`).
- `vendor/bin/phpunit tests/php/ClaudeSettingsProjectionTest.php` — 5/5 passed,
  16 assertions.
- `vendor/bin/phpunit tests/php/ClaudeSettingsMergeTest.php` — 9/9 passed
  (unchanged; `claude-settings-merge.php` itself was not edited).
- `vendor/bin/phpunit tests/php/ArchitectureDiagramReferencesTest.php` — 3/3
  passed (1 clean skip, was previously 1 failure caused by this pass).
- `composer test:fast` (934 tests) — 3 pre-existing, out-of-scope failures only
  (see above); zero new failures caused by this pass, confirmed by diffing the
  failure set before and after the `ArchitectureDiagramReferencesTest` fix.
- `actionlint .github/workflows/validate-ai-surface.yml` — clean (no output).
- `yq eval '.jobs.validate.steps[].name' .github/workflows/validate-ai-surface.yml`
  — confirms the new "Check generated Claude settings floor" step is present.
- Structured diff of `templates/claude/settings.json` before/after `--write`
  (`array_diff` both directions on `permissions.allow`/`permissions.deny`) —
  0 entries removed from either array; allow 119→174, deny 77→94; `$schema` and
  `hooks` byte-identical by decoded value.
- Not run: `composer test` (full serial suite) — `composer test:fast` (parallel)
  was used instead per the verification ladder's "start narrow" guidance and the
  60s/90s budget split in `docs/ai/execution-protocol.md`; the parallel run
  already covers every test file including the new one.

## Implementation Notes (Phase 3, 2026-07-09)

Scope: only the 3 Phase 3 `Todo Plan` items and AC-05, per an explicit
implementer-handoff boundary. No Task-tool subagent-dispatch capability was
available in this session, so no item required deferring to an "agent-critic" or
other subagent persona — none of the 3 Todo items or AC-05 needed one.

**Pre-existing working-tree state at start:** consistent with the Phase 2 notes
above, the tree already had ~176 uncommitted files modified/untracked before
this pass began (confirmed via the first `git status --short` of this session),
including two untracked skill directories (`.claude/skills/ai-scripts/`,
`.claude/skills/ai-search/`) that make `packages/ai-universal-rules/catalog.json`
and `docs/ai/catalog.md` drift against `generate-ai-catalog.php --check` — see
"Pre-existing, out-of-scope test failures" below. This pass did not create or
touch either untracked skill directory.

**Design decision — descriptor location:** used the plan's own explicitly
offered alternative ("or a sibling `claude-runtime-capabilities.php`") rather
than inlining the descriptor into `claude-agent-renderer.php` itself, since the
renderer file was already large (459 lines pre-edit) and the descriptor is
conceptually a data table, not renderer logic.

**Consolidation decisions (what was subsumed vs. kept as an exception):**

1. **Subsumed, lossless:** the `external_directory` neutralization chain
   (3 `preg_replace` calls, previously inline at ~L166-191) and the "Full
   per-script ... is in frontmatter" sentence (1 `str_replace`, previously
   ~L199-203) moved into `aiClaudeRuntimeCapabilities()`'s `rules` tables for
   `no_external_directory_enforcement` and `no_ask_tier` respectively, applied
   via one `aiClaudeApplyRuntimeCapabilityFilters()` call. These three
   `external_directory` rules are order-dependent (rule 2's output text is rule
   3's match target) and are preserved in the same order inside one capability's
   `rules` array — verified byte-identical output via the pre-`--write` diff
   preview (`/tmp/opencode/full-diff.txt`, not committed): zero unexpected
   diff on any agent for this class of text.
2. **Subsumed, equivalent-or-better, with EXPANDED reach:** the `task` (`ask`)
   delegation rewrite (previously a `!in_array('Agent', $tools, true)`-gated
   inline `preg_replace`) moved into the same capability table as a
   `condition`-gated rule (`$ctx['tools']`), byte-identical behavior confirmed
   by the pre-existing `ClaudeAgentRendererTest::testReviewerScriptAccessDoesNotDescribeUnreachableTaskDelegation`
   and `testArchitectOutputKeepsAgentToolAndIsUnaffectedByTaskRewrite` passing
   unchanged.
3. **Subsumed, equivalent-or-better, with EXPANDED reach:** researcher's
   plan-5 `pack-context.sh` (`ask`) regex was DELETED and replaced by the new
   generalized `aiClaudeReconcileScriptAccessBullets()` rule (the plan's
   explicit "generalizing the 2 researcher-only regexes into one rule keyed on
   the actual allowlist" instruction — only 1 of the 2 referenced regexes
   subsumes cleanly; see item 5 below for the other). Verified equivalent output
   via `ClaudeAgentRendererTest::testResearcherScriptAccessDoesNotFramePackContextAsRunnable`
   passing unchanged (same two key substrings asserted). The generalized rule
   ALSO now reaches 4 previously-unfixed agents with the identical
   `repomix/`pack-context.sh` (`ask`)` bullet shape: architect, post-install,
   repository-researcher, infra-auditor — confirmed via the `--check`/`--write`
   diff preview, a genuine new fix, not merely a refactor.
4. **Subsumed, equivalent-or-better, with EXPANDED reach (new coverage beyond
   the plan's 2 named agents):** docs' plan-18 `ai-edit.sh`/`ai-rollback.sh`/
   `session-checkpoint.sh` bullet shape (2 `(`ask`)` markers on one line) is now
   also reconciled by the SAME generalized rule for `implementer.md` and
   `config-maintainer.md` (previously unfixed — those two templates carry the
   3 scripts as 2 SEPARATE single-marker bullets rather than one compound line,
   so they were reachable once the rule was generalized) and for
   `refactorer.md`/`bugfix.md`/`build-config.md`/`upgrade.md` (compound
   2-marker single-line shape, reconciled once the rule was widened from an
   initial 1-marker-only design to "any marker count, full-line rewrite" — see
   item 6). Confirmed via the diff preview: 0 lines removed, only accurate
   "not runnable" disclosures added or corrected.
5. **KEPT AS EXCEPTION (not subsumed):** docs' plan-18 override itself (the
   `if ($agentId === 'docs')` block) was deliberately LEFT IN PLACE, running
   BEFORE the generic reconciliation call, specifically because it carries a
   "stop and report `needs-scope-approval`" instruction the generic rule's
   fallback text ("note the gap in this agent's Final Output instead") does not
   capture — a stronger, protocol-specific safety instruction unique to docs'
   Edit-tool-based mission. The generic rule only matches lines still containing
   the literal `` (`ask`) `` marker, so once docs' override rewrites its line,
   the generic pass naturally skips it (verified: no diff on that specific line
   in the `--write` output).
6. **KEPT AS EXCEPTIONS (not touched at all, not subsumable within this
   slice's scope):** the 5 "Bash Command Policy footer" per-agent overrides
   (release-auditor plan-16, workflow-auditor plan-17, agent-fleet-assessor
   plan-20, config-maintainer plan-24, agent-creator-runtime-guardian plan-25 —
   all rewriting the "Other listed commands (`rm`, `mv`, `cp`, `chmod`, plain
   `git push`/`git reset`) are prose-discouraged and interactively gated, not
   hard-blocked" sentence) were left completely untouched. This is a DIFFERENT
   defect shape than the two named in the plan's Phase 3 scope
   (`no_ask_tier`/`no_external_directory_enforcement` phrase claims and the
   Script-Access-vs-allowedBash bullet contradiction) — it concerns whether
   `rm`/`mv`/`cp`/`chmod`/plain `git push`/`git reset` are "listed" for THIS
   agent at all, which requires interpreting each agent's own mission framing
   (5 hand-tuned, non-identical replacement texts — release-auditor's differs
   materially from workflow-auditor's in tone and framing). A fully-dynamic,
   data-driven version of this rule (computed from whether those literal verbs
   appear in each agent's own `$allowedBash`) was considered and would likely
   ALSO fix the same latent contradiction for several other agents that
   currently render the generic, arguably-inaccurate sentence unmodified — but
   building and verifying that safely was judged out of this slice's explicit 3-
   item scope and the "Replace the 4 `preg_replace` neutralizations and the
   hardcoded Bash-Command-Policy disclaimer" instruction, which named the
   TRAILING disclaimer paragraph (rewritten — see below), not this "Other
   listed commands" sentence. Flagged here as a good candidate for a FUTURE,
   separately-scoped Phase 3b, not silently attempted in this pass. release-
   auditor's separate Script-Access cross-reference fix (plan-16 finding #3,
   "this agent's Script Access list names below" -> "...above") and researcher's
   Write/Edit-capability Hard Rules fix (plan-5, keyed on `disallowedTools`
   rather than allowedBash) were likewise left untouched — different defect
   shapes entirely, not Script-Access-vs-allowedBash bullet contradictions.
7. **Hardcoded disclaimer rewrite (the plan's second named target):** the
   trailing "Hard enforcement ... if this list and `.claude/settings.json`
   disagree, `.claude/settings.json` wins" paragraph (previously ~L150-153,
   present for every one of the 24 agents with a non-empty `allowedBash`) was
   rewritten to state the approved-scripts list is a SUBSET of
   `.claude/settings.json`'s floor "by construction" — literally true as of
   Phase 2's `generate-claude-settings.php`/`ClaudeSettingsProjectionTest`
   (`testEveryShippedAgentAllowedBashIsSubsetOfGeneratedAllow`). This is a
   universal, agent-agnostic change (all 24 agents), matching the plan's
   Phase 3 bullet text verbatim: "the disclaimer is rewritten to state the body
   list is a subset of the enforced floor ..., not a `settings.json wins over
   the body` contradiction."

**Reconciliation-rule design evolution (documented for the next implementer):**
the Script-Access reconciliation rule was iteratively widened during this pass
from an initial "exactly 1 `` (`ask`) `` marker only" guard (to avoid ambiguous
multi-clause lines) to "any marker count, full-line rewrite when ALL named
scripts are absent" once empirical verification (rendering every agent and
diffing) showed the simpler, more general form was SAFE for every real
template: the only remaining un-rewritten `` (`ask`) `` occurrences after this
change are 7 lines where at least one named script (`ai-test-select.sh`,
`run-repo-tests.sh`, or `ai-diff-context.sh`) genuinely IS in that agent's own
`allowedBash` — a "mixed-presence" case the rule correctly declines to touch
(rewriting the whole line would falsely claim a runnable script is not
runnable). This mixed-presence residual is pinned by
`ClaudeCapabilityFilterTest::testKnownMixedPresenceBulletsAreUnchanged` rather
than silently left unproven — see AC-05 above.

**Safety review before `--write` (per this task's CRITICAL SAFETY directive):**
ran `php tools/ai/render-adapters.php --check` first (24 files flagged), then
generated a full unified diff of every changed agent (rendered-in-memory vs.
installed bytes, via a throwaway script at `/tmp/opencode/render-diff-preview.php`,
not committed) and read all 559 diff lines before writing anything. Every
changed line was one of: (a) the universal disclaimer subset-by-construction
rewrite, (b) a Script-Access bullet gaining an accurate "not runnable on Claude
Code" disclosure it previously lacked, or (c) researcher's bullet losing its
old bespoke wording in favor of the new generic wording (equivalent facts, see
item 3 above). No line removed or weakened a hard-block, deny, secret-read, or
mutation-framing disclosure; no line touched `.github/agents/*.agent.md` or any
union-merged root doc (confirmed via `git diff --stat .github/agents AGENTS.md
CLAUDE.md` — 0 files). Only after this review was `--write` run.

**Verification evidence:**

- `php -l` on `tools/ai/install/claude-agent-renderer.php`,
  `tools/ai/install/claude-runtime-capabilities.php`, and
  `tests/php/ClaudeCapabilityFilterTest.php` — no syntax errors.
- `php tools/ai/render-adapters.php --check` (before `--write`) — 24 files
  flagged as drift, all in `.claude/agents/`, none in `.github/agents/`.
- Manual diff review of all 24 changed files (see "Safety review" above) —
  confirmed purely additive/corrective, no safety regression.
- `php tools/ai/render-adapters.php --write` — `OK: rewrote 24 rendered agent
  file(s)`, all under `.claude/agents/`.
- `php tools/ai/render-adapters.php --check` (after `--write`) — `OK: .claude/agents
  and .github/agents are byte-parity with the canonical templates` (AC-01/AC-06).
- `git diff --stat .claude/agents .github/agents AGENTS.md CLAUDE.md` — 24 files
  changed, all under `.claude/agents/`; 0 files under `.github/agents/`,
  `AGENTS.md`, or `CLAUDE.md` (confirms no union-merged root doc or Copilot
  surface was touched).
- `vendor/bin/phpunit tests/php/ClaudeCapabilityFilterTest.php` — 12/12 passed,
  119 assertions.
- `vendor/bin/phpunit tests/php/ClaudeAgentRendererTest.php
  tests/php/AdapterRenderDriftTest.php tests/php/ClaudeCapabilityFilterTest.php`
  — 41/41 passed, 334 assertions (includes the one pre-existing test updated —
  `testArchitectOutputHasBashCommandPolicySection` — to match the new
  subset-by-construction disclaimer wording instead of the removed "settings.json
  wins" phrase).
- `php tools/ai/generate-claude-settings.php --check` — still exits 0 (Phase 2's
  projection is unaffected by Phase 3's body-text-only changes).
- `php tools/ai/generate-agent-permissions.php --check` — `OK: managed agent
  permission blocks in sync` (AC-07's OpenCode-parity half unaffected).
- `composer test:fast` (946 tests, 12 workers) — 4 failures, ALL pre-existing
  and unrelated to this pass: `CliToolsTest::testGenerateCatalogCheckModeExitsZero`,
  `testGenerateCatalogCheckModeDoesNotWriteFiles`,
  `testValidateGeneratedArtifactsExitsZero`, and
  `GeneratedHeaderTest::testValidateGeneratedArtifactsPasses` — all four fail
  because `packages/ai-universal-rules/catalog.json`/`docs/ai/catalog.md` are
  out of sync with two UNTRACKED skill directories
  (`.claude/skills/ai-scripts/`, `.claude/skills/ai-search/`) that were already
  present (as `??` in `git status --short`) before this pass began; confirmed via
  `git diff packages/ai-universal-rules/catalog.json docs/ai/catalog.md` — the
  only diff content is the `claude-skill` count and two new catalog entries for
  those pre-existing untracked directories, neither of which this pass touched.
  Note this differs from the 3 failures Phase 2's notes recorded (those were
  `AdapterRenderDriftTest`/`AgentPermissionDriftTest` drift in ~20 already-dirty
  agent files) — this pass's own `--write` run RESOLVED the `AdapterRenderDriftTest`
  drift Phase 2 flagged (all 24 `.claude/agents/*.md` are now byte-parity), so
  neither of Phase 2's originally-flagged failures reproduces here; the 4
  failures seen now are a distinct, also-pre-existing, catalog-only issue.
- Not run: `composer test` (full serial suite) — `composer test:fast` used
  instead, consistent with Phase 2's documented rationale.

**Archive status: NOT ARCHIVED.** This plan's own "Risk + routing" section
(top of file) states: "This is a MEDIUM/HIGH risk change (it generates the
enforced Claude permission floor). Implementation MUST route through reviewer +
release-auditor before merge." That risk gate is unchanged by finishing Phase
3's 3 Todo items — Phase 3 edits the same permission-adjacent rendered surface
(24 `.claude/agents/*.md` bodies) as Phases 1-2. Per this task's explicit
instruction, this plan is left UNARCHIVED at its current path even though every
Phase 1-3 Todo/AC item is now checked or explicitly marked partial with reasons
(AC-05, AC-08's settings.json-generated half already noted complete since
Phase 2 landed) — a human/reviewer/release-auditor sign-off is the actual
completion gate for this plan, not Todo/AC checkbox state. A follow-up session
with `reviewer` + `release-auditor` access should review this diff (24
`.claude/agents/*.md` files, `tools/ai/install/claude-agent-renderer.php`,
`tools/ai/install/claude-runtime-capabilities.php` (NEW),
`tests/php/ClaudeCapabilityFilterTest.php` (NEW),
`tests/php/ClaudeAgentRendererTest.php`) before this plan is archived or merged.
