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
- [ ] P2 (Phase 3): Add the `aiClaudeRuntimeCapabilities()` descriptor (banned-capability list + Script-Access-vs-allowedBash reconciliation rule).
- [ ] P2 (Phase 3): Replace the 4 `preg_replace` neutralizations and the hardcoded disclaimer in `claude-agent-renderer.php` with the table-driven filter; regenerate via `render-adapters.php --write`.
- [ ] P2 (Phase 3): Add `tests/php/ClaudeCapabilityFilterTest.php` (no banned-capability phrase in any rendered Claude body; no Script-Access bullet names an out-of-allowlist script).

## Acceptance Criteria

- [x] AC-01: `php tools/ai/render-adapters.php --check` exits 0 after the Phase 1 `--write` reconciliation; `AdapterRenderDriftTest` is green.
- [x] AC-02: Deliberately mutating one line of any `.claude/agents/*.md` or `.github/agents/*.agent.md` makes `render-adapters.php --check` exit non-zero with a diff (negative test).
- [x] AC-03: `php tools/ai/generate-claude-settings.php --check` exits 0; `ClaudeSettingsProjectionTest` proves every agent's rendered allowedBash is a subset of the generated `permissions.allow`, and the composed hard-deny set is a subset of the generated `permissions.deny`. Verified: `--check` exits 0; `testEveryShippedAgentAllowedBashIsSubsetOfGeneratedAllow` and `testComposedHardDenyFloorIsSubsetOfGeneratedDeny` pass (5/5 tests, 16 assertions).
- [x] AC-04: A synthetic third-party allow entry injected into `.claude/settings.json` survives the install-time union-merge (merge mechanism unchanged). Verified: `testSyntheticThirdPartyAllowEntrySurvivesUnionMerge` passes; `claude-settings-merge.php`/`aiInstallerMergeClaudeSettingsJson()` untouched (confirmed via `git diff` — 0 lines changed in that file).
- [ ] AC-05: `ClaudeCapabilityFilterTest` proves no rendered Claude body contains an ask-tier claim, per-path edit-scoping claim, or bash-level `mv`/`cp`/`rm` deny claim, and no Script Access bullet names a script absent from that agent's rendered allowedBash.
- [x] AC-06: `php tools/ai/render-adapters.php --write` regenerates both agent-body trees in place with zero diff to any union-merged root doc (`AGENTS.md`/`CLAUDE.md` and their third-party appends untouched).
- [x] AC-07: `php tools/ai/generate-agent-permissions.php --check` still reports "in sync" (the 13 managed OpenCode blocks and all `.opencode` bytes unchanged); full `vendor/bin/phpunit` is green (925/925, run via both `composer test` serial and `composer test:fast` parallel).
- [~] AC-08 (partial — Phase 1 only): The in-place regen command is documented in `maintainer-guide.md` and `source-of-truth.md`, and `source-of-truth.md`'s generated-vs-hand-authored boundary is updated to mark `.claude/agents`/`.github/agents` bodies as generated. The `templates/claude/settings.json` allow/deny-as-generated half of this AC is Phase 2 (`generate-claude-settings.php`), still unbuilt — leave unchecked until Phase 2 lands.
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
- **Rollback:** each phase is an independent PR; revert the phase's PR and re-run the prior phase's `--check` to confirm restoration. Phase 1 `--write` reconciliation is reversible via git; the generated `settings.json` can be reverted to the prior static template.

## Handoff Notes

- Extend, never replace: the engine at `permission-layers/` already IS the spec; add adapters + one capability table; touch `compose.php`/`compositions.php` internals for nothing.
- Phase order is load-bearing: parity gate + dogfood FIRST (it is the fix mechanism), then settings projection, then capability filter.
- The generated header is deterministic — byte-parity needs no timestamp masking.
- Apply `aiAgentIsHiddenInternalOnly()` in the gate (26->24) or it false-fails.
- Keep `command-policy.tiers.yaml` separate; only add the superset-consistency assertion.
- Do NOT change `.opencode` bytes or the 13 managed OpenCode blocks.
- Recommended next step after persistence: implementer for Phase 1, routed through reviewer + release-auditor before merge (medium/high risk, generated enforced permission floor). Do not implement before this plan is persisted.

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
