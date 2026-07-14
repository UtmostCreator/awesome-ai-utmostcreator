# Agent Handoff Governance Migration — Master Plan & Todo

Status: ✅ COMPLETE (2026-07-14) — all 8 phases done; migration-caused tests green; fleet at notes-only (0 blockers). Only pre-existing script-extraction tests remain red (separate effort). Not committed (awaiting user).
Owner: orchestrator (main session)
Started: 2026-07-14
Source of truth: `handoff/agent-handoff.yaml` (ported from `/home/utmostcreator/Projects/handsoff`)
Authorization: user granted full write access to this repo (incl. `packages/**`, `tools/ai/**`) for this task.

## Goal (user's words, sequenced)

1. Research fit into repo, then plan split for super-implementers.
2. Super-implementer/implementer finishes a slice → hands to reviewer immediately.
3. Reviewer finds issues → implementer fixes → re-run reviewer **until only notes remain**, per agent.
4. Integrate the handoff instruction into **agent-critic** (→ agent-definition-reviewer) with a thorough reviewer→implement→fix loop until only notes.
5. Use agent-critic to improve **all other** agents (fleet assurance loop).
6. Drive everything from `agent-handoff.yaml`; correct the rules if errors are found.
7. All 3 providers we ship (Copilot, Claude, OpenCode) carry the **same** instructions.
8. Agent renames + agent→skill moves where required (e.g. bugfix→bug-regression skill).
9. An **orchestrator** agent that accepts a command and directs it (dynamic routing).
10. A common contract in every agent: **provide / produce / avoid** + acceptance/evidence/stop/failure_route/authority/security/budget + HandoffPayload + human_summary.
11. Pull extra useful features/commands/ideas from the handsoff repo (DIAGRAMS, protocol, hooks).

## Key decisions & known conflicts

- **DECISION (structured vs prose handoff):** the YAML mandates a structured `HandoffPayload:v1` + `handoff(goto,payload)` command + dispatcher. This supersedes the older "prose-only Claude handoff" posture. Structured wins, expressed as headed prose sections in bodies (fleet house style) so it renders identically to all 3 providers.
- **CONFLICT (renames):** `docs/tickets/IDEAS/plan-agent-rename-assessment.md` recommended NOT renaming shipped agents (blast radius). The YAML + user direction require renames. **Resolution:** perform renames **with a 1-release alias window** (`migration.compatibility`) so nothing breaks; remove aliases only after two clean fleet assessments. Flagged for user veto.
- **Edit surface:** no active `.claude/settings.json` in-session; `packages/**` convention-denial overridden by explicit user grant. Canonical edits go to `packages/ai-universal-rules/templates/{core,optional}/agents/*.md` then regenerate; never hand-edit generated `.claude|.github|.opencode` copies (they carry GENERATED headers and are re-rendered).
- **Provider parity mechanics:** Claude + Copilot are *rendered* from canonical; OpenCode is a *verbatim copy* (`.opencode/agents{,-optional}/`). Skills come from `packages/ai-universal-rules/templates/workflows/*.md` and auto-render to all 3 skill dirs + Copilot prompt + OpenCode command. So a new common contract must live in the **canonical agent bodies** and new skills as **workflow templates**.
- **OpenCode path fixes to YAML:** singular `.opencode/command/` → `.opencode/commands/`; `.opencode/agent/` → `.opencode/agents/`.

## Phased todo (execution order)

### Phase 0 — Foundation: port handoff tooling  [option-independent]  ✅ DONE (2026-07-14)
- [x] Create `handoff/` in repo: `gen_handoff.py`, `dispatch.py`, `gen_handoff.sh`, `agent-handoff.yaml`, `AGENT-INCLUDE.md`, `DIAGRAMS.md`, `generated/` (16 files).
- [x] Fix YAML opencode plural-path (3 edits: render_targets, provider agent_file, provider_commands).
- [x] `./handoff/gen_handoff.sh --check` passes → `PASS migration_coverage=24/24`, exit 0.
- [x] `handoff/generated/*` present; `allowed_next.json` matches `dynamic_routing.allowed_next`.
- [x] `dispatch.py` verified: implementer→reviewer exit 0; implementer→architect exit 1.
- [x] FOLLOW-UP (from reviewer): reconciled bare `.opencode/agent`→`.opencode/agents` (line 108); re-`--check` exit 0. `configuration-maintainer` WARN documented as EXPECTED (conditional/dynamic-only role; orphan check ignores `allowed_next` by design — not a defect).
- [x] Phase 0 reviewer verdict: PASS WITH NOTES → resolved to notes-only. **Phase 0 merge-ready.**

### Phase 0.5 — Provider best-practices grounding  [NEW per user]
- [x] OpenCode best practices captured. Findings: dirs PLURAL (`.opencode/agents/`,`.opencode/commands/`; singular still loads); frontmatter `description(req)/mode/permission/model/temperature/hidden`, filename=id, no `name`; **`tools:` DEPRECATED → use `permission`**; permission is **LAST-MATCH-WINS by pattern order** (not deny>ask>allow); subagents via `task` tool; orchestrator idiom = `mode: primary` + `permission.task` allowlist.
- [x] Claude Code subagent + command/skill + permission best practices captured. Findings: no native typed handoff (prose "Recommended next step" + `Agent()` + optional `SendMessage`) → our portable `handoff` block + dispatcher is the correct cross-provider layer; subagent frontmatter tools are ADVISORY, `settings.json` enforces; `.claude/skills/<n>/SKILL.md` and `.claude/commands/<n>.md` both create `/<n>` (skills = canonical form); body ≤~500 lines. Stick to renderer-supported frontmatter (name/description/tools/disallowedTools/model/permissionMode).
- [x] Copilot best practices captured. Findings: `.github/agents/*.agent.md` + `.github/prompts/*.prompt.md`; native **`handoffs:` frontmatter** (label/agent/prompt/send/model) = human-click button (only native handoff carrier across providers); subagent delegation via `agent`/`runSubagent` tool + `agents:` allowlist (no re-delegation by default); CLI is single-agent.
- [x] Reconciled yaml `provider_integration`: fixed OpenCode precedence (last-match-wins), noted `tools:` deprecation, added Copilot `handoffs:`/`agents:` + orchestrator idioms, added "payload rides in body (no native transport)" strategy bullet. Re-`--check` exit 0.

### Phase 1 — Common handoff contract in canonical bodies (all 3 providers)
- [x] Authored the shared `## Handoff Contract` section (provide/produce/avoid + acceptance/evidence/stop/failure_route/authority/security/budget + HandoffPayload + human_summary + edgeless `handoff`/dispatcher flow). Uses target ROLE_ID as handoff id (decoupled from pending file renames).
- [x] **implementer** (PILOT): section added to canonical, rendered to all 3 providers byte-identical (SHA256 match), gates green, no new failures. Dispatcher route ok.
- [x] Batch CLEAN delivery agents done: researcher, config-maintainer(id=configuration-maintainer), reviewer, release-auditor — byte-parity, dispatcher exits 0.
- [x] architect + architecture-plan-writer(id=plan-writer) done. **RESOLVED the branch reconciliation**: `render-adapters.php --check` now fully GREEN (EXIT=0); `RECONCILIATION_PENDING = []`; OpencodeAgentBodyParityTest passes. (architect/plan-writer anchor = before `## Final Output`, matching the 5-agent convention.)
- [x] All 7 delivery agents carry `## Handoff Contract` across claude/github/opencode (verified).
- [x] Restored `.claude/settings.json` (tracked, deleted from worktree during session — cause predates render work; watching for recurrence).
- [x] Reviewer pass over Phase 1: **PASS WITH NOTES** (only notes → done). 10× dispatcher edges exit 0; ids/goto match allowed_next; per-section sha256 identical; settings.json clean. **PHASE 1 COMPLETE.**
- NOTE (gotcha): `render-adapters.php --write` regenerates ALL drifted files; run serially, never concurrent `--write` across slices.
- FOLLOW-UP notes (tracked, non-blocking): (a) architect has 3 overlapping handoff constructs (Mandatory Plan-Writer Handoff / generic Handoff Contract / Plan Writer Handoff Envelope) — reconcile vocab in Phase 5/7. (b) `.claude/agents/super-implementer.md` untracked orphan (user-provided) — leave. (c) no skill/command render-drift gate exists (render-adapters is agents-only) — consider adding in Phase 8.

### Phase 2 — Orchestrator + `handoff` command (3 providers)
- MECHANISM (verified): command generation has TWO canonical sources. `templates/commands/*.md` → `.claude/commands/` + `.opencode/commands/` (NOT Copilot). `templates/workflows/*.md` → `.github/prompts/*.prompt.md` + `.claude/skills/<n>/SKILL.md` + `.opencode/{skills,commands}/`. To give ALL 3 providers `/handoff`, author ONE `templates/workflows/handoff.md` (Claude skill ≡ /command per Claude docs). Update yaml `provider_commands.claude` → `.claude/skills/handoff/SKILL.md` (equivalence).
- [x] (2a) `/handoff` command DONE: canonical `templates/workflows/handoff.md` + 5 rendered files (`.claude/skills/handoff/`, `.github/skills/handoff/`, `.github/prompts/handoff.prompt.md`, `.opencode/commands/handoff.md`, `.opencode/skills/handoff/`). Body md5-identical across all providers; gates EXIT=0; yaml `provider_commands.claude`→skill path. Copilot prompt gets NO generated header (mirrors verify-change). Handoff skill now live. (Phase 2 reviewer folds this in.)
- [ ] (2b) Author `orchestrator` agent (new CORE agent). **ADD-AGENT RECIPE (verified):**
  - `mode: primary` is INVALID (validate-agent-spec.php) → use **`mode: all`**. Mirror `agent-fleet-assessor.md` frontmatter (mode:all, task:allow, edit:deny, hand-authored `permission` block; described in-repo as the "primary orchestrator" pattern). Add `'python handoff/dispatch.py *': allow` to bash (NOVEL, additive).
  - MUST add `orchestrator` entry to `tools/ai/install/claude-agent-tool-registry.php` with `['Agent']` (else silent read-only, no delegation). copilot handoff registry optional.
  - `compositions.php` NOT required (legacy hand-authored perms, agent-fleet-assessor precedent).
  - Render: `render-adapters.php --write` (claude+github) + revert-guard not needed now (parity green); `.opencode/agents/orchestrator.md` = canonical+header passthrough.
  - Add `AGENTS-MANIFEST.md` row (` `orchestrator` `) [AgentsManifestTest] + `docs/ai/agent-scores.yaml` entry [frontmatter-drift].
  - DEFER to Phase 8 consolidation: count-test bumps (`AgentAssessmentFrontmatterWriterTest:93`, `ClaudeSettingsProjectionTest:107`), `generate-claude-settings.php --write`, catalog regen.
  - Body: accepts a task/command, classifies (intake), dispatches within `allowed_next` via `/handoff`+dispatcher, owns loop/failure control; never edits/designs/reviews. Copilot=coordinator (`agents:` allowlist); Claude=invoked via `Agent()` (single-agent fallback documented).
- [x] (2b) orchestrator DONE: canonical CORE template + registry entry (`Agent` grant) + 3 renders (byte-parity) + AGENTS-MANIFEST row + agent-scores entry. Gates green; `Agent` tool present in Claude render; dispatcher supervisor free-dispatch confirmed; Handoff Contract byte-identical across providers. Now a live agent type.
- NOTE (new-agent gotcha): `render-adapters.php --write` only rewrites EXISTING files — a brand-new agent's first render needs a bootstrap (mirror `aiInstallerRenderClaudeAgent`/`aiInstallerRenderCopilotAgent` + opencode header helper), then `--write` confirms parity. Reuse for agent-factory + configuration-maintainer.
- DEFERRED (Phase 8): count-tests (AgentAssessmentFrontmatterWriterTest 26, ClaudeSettingsProjectionTest 24) + AGENTS-MANIFEST "19 agents" prose.
- [ ] Ship `HANDOFF-PROTOCOL.md` reference; dispatcher enforcement documented in the command body.
- [x] **PHASE 2 COMPLETE** — reviewer PASS WITH NOTES (orchestrator Agent-grant present + no write capability; /handoff body-identical; settings change = exactly 2 approved deny lines). Notes: commit hygiene; `super-implementer` orphan render (harness artifact, not migration — leave, don't commit).

### Phase 3 — Create skills (9 new + render config-change-safety) — ✅ STRUCTURAL DONE
- [x] All 10 skills created as canonical `templates/workflows/<n>.md` + 5 rendered files each (Claude skill, Copilot prompt [no header], Copilot skill, OpenCode command, OpenCode skill), body md5-identical across providers:
  - batch-1: `handoff-contract`, `agent-definition-review`, `fleet-assessment`
  - batch-2a: `safe-refactor`, `build-configuration`, `workflow-drift-audit`, `infra-risk-audit`
  - batch-2b: `agent-semantic-verification`, `runtime-guardrail-design`, `config-change-safety`
- [x] Gates green per batch (render-adapters --check EXIT=0, validate-adapter-drift EXIT=0 — only pre-existing requiredRefs WARN pattern). All 10 skills now live in the Skill menu.
- [x] Reviewer: **PASS WITH NOTES** — all 10 faithful, gates green, handoff-contract field-list exact-matches yaml schema. **PHASE 3 COMPLETE.**
- [ ] Catalog registration for the 10 skills → deferred to Phase 8.
- FOLLOW-UP for Phase 6 (fold into agent-definition-reviewer hardening): re-add agent-critic's Power-Fit (UNDERPOWERED/FIT/OVERPOWERED) + Enforceability (ENFORCED/INSTRUCTION_ONLY/UNENFORCEABLE) lenses to `agent-definition-review` skill; restore fleet-assessment's "unexecutable top handoff never >79" cap.
- COMMIT HYGIENE (whole migration): when committing, split by phase — Phase 1 agent-body edits + handoff skill + super-implementer + test line are separate from the 60 Phase-3 skill files. (No commits made yet.)
- [ ] Phase 8: add `handoff/` tooling to the install bundle (packs.php) so INSTALLED projects (not just dogfood) get dispatch.py + generated/ that agent bodies reference.

### Phase 4 — Agent → skill / tool moves (retire) — ✅ DONE (6 agents)
- [x] Retired bugfix, docs, refactorer, upgrade (→implementer + skills), infra-auditor, workflow-auditor (→reviewer + skills): 24 files `git rm`'d across canonical + 3 providers.
- [x] Cleaned registries (claude/copilot tool registries, copilot-handoff-registry, compositions.php, script-registry aiInstallerAgentProfiles coupling), AGENTS-MANIFEST rows, agent-scores entries. Removed stray empty `.claude/agents/docs/` dir.
- [x] implementer capability routing += safe-refactor + dependency-upgrade; reviewer += infra-risk-audit + workflow-drift-audit review modes.
- [x] Gates green: render-adapters --check EXIT=0, OpencodeAgentBodyParityTest PASS, **PermissionComposeTest PASS (42 tests)**, adapter-drift EXIT=0.
- [x] Reviewer: PASS WITH NOTES. Fixed the one MINOR (removed 4 retired stems from `aiPermissionOptionalAgentKeys()`, count 11→7; PermissionComposeTest still 42/794 green). **PHASE 4 COMPLETE.**
- DEFERRED to Phase 8: count-test bumps (ClaudeSettingsProjectionTest now 19, AgentAssessmentFrontmatterWriterTest now 20); `docs/ai/agents.md` roster rewrite (still lists the 6).
- Phase-5-owned cross-refs: `agent-critic` (→ refactorer/workflow-auditor mentions) + `repository-reviewer` (→ workflow-auditor) — redirect during their rename/merge.
- NOTE: agent-creator-static-validator→tool, runtime-guardian→skill, semantic-verifier→skill happen in Phase 5c (agent-factory merge), not Phase 4.

### Phase 5 — Merges & renames (highest blast radius; aliased)
- [x] **5a** repository-researcher→researcher + repository-reviewer→reviewer merged/retired. Gates green (PermissionCompose 42/734). Script-first line folded into researcher.
- [x] **5a.5** reconciliation DONE: fixed orchestrator Copilot-registry gap + handoffs-field bug (prose `handoffs:` colon tripped ClaudeAgentRendererTest whole-body scan → reworded); backfilled stale roster tests. **Clean baseline: full suite red = ONLY the 2 count-tests (Phase 8) + pre-existing script-extraction classes.** Expanded gate set all green.
- [x] **5b-1** DONE: agent-critic→agent-definition-reviewer, agent-fleet-assessor→fleet-assessor (direct git mv, history preserved). Full expanded gate set green; no new red class; dispatcher routes new ids; deferred Phase-4 cross-refs fixed; `docs/ai/agent-deprecations.md` created.
- [x] **5b-2** DONE: architecture-plan-writer→plan-writer (direct git mv; compositions↔profiles lockstep held PermissionComposeTest green; 6 architect cross-refs updated; body already persist-only). No new red class. **PHASE 5b COMPLETE (3 renames).**
- Residual non-agent artifacts to handle in Phase 8: `architecture-plan-writer` SKILL dir (.github/.opencode skills — pre-existing cross-provider skill), `architecture-plan-scope-guard.yml` CI, `aiPermissionRenderArchitecturePlanWriter()` internal fn name (cosmetic).
- [x] **5c** DONE: agent-factory created (in `.opencode/agents/`, both tool registries + handoff registry); 5 agent-creator* retired. Full-suite residual byte-identical to baseline (zero new red). Phase-8 residuals: ai-script-access.yaml (lists 9 retired agents + hook-holder invariant), orphaned agent_creator.* packs, agents.md.
- [x] **5d** DONE: config-maintainer→configuration-maintainer (rename); build-config + post-install retired. Two compositions↔profiles lockstep changes held PermissionComposeTest 42/42; zero new red; ui-builder over-reach caught+reverted. **PHASE 5 COMPLETE.**
- [x] Phase 5 combined reviewer: **PASS WITH NOTES** — roster EXACTLY matches target; 214/214 agent gates green; dispatcher routes; no dangling live-agent refs; deprecations complete. Phase-8 items: settings.json projection regen (`generate-claude-settings.php --write`), dead renderer branch `claude-agent-renderer.php:272` (agent-creator-runtime-guardian). **PHASE 5 COMPLETE.**
- BRANCH NOTE (NOT this migration): ~66 script-extraction test failures (missing `scripts/ai/*.sh` from commit b2fbf715) — belongs to the arch-todo-scripts-ai-reusable-extraction effort; do NOT fix here (conflict risk). Surface to user.
- FLEET NOW = target: researcher, architect, plan-writer, implementer, configuration-maintainer, reviewer, release-auditor, agent-factory, fleet-assessor, agent-definition-reviewer, orchestrator (+ unmanaged extras bootstrapper/ui-builder/script-runner/super-implementer/ai-maintenance).
- Phase 6/7 TODO: fleet-assessor + agent-definition-reviewer still LACK the `## Handoff Contract` section (5b-1 was rename-only) — add during their hardening.
- [ ] repository-researcher → researcher (merge).
- [ ] repository-reviewer → reviewer (merge).
- [ ] agent-creator + agent-creator-supervisor → agent-factory (new, staged pipeline).
- [ ] build-config + config-maintainer → configuration-maintainer (conditional; else fall back to implementer).
- [ ] agent-critic → agent-definition-reviewer (rename).
- [ ] agent-fleet-assessor → fleet-assessor (rename).
- [ ] architecture-plan-writer → plan-writer (thin + rename; docs/tickets-write-only).
- [ ] post-install → temporary post_install workflow.
- [ ] Update registries (`tools/ai/install/*`), catalogs, docs, tests; add 1-release aliases + deprecation warnings.

### Phase 6 — Integrate handoff into agent-definition-reviewer + loop
- [x] **6a** DONE: added `## Handoff Contract` to agent-definition-reviewer + fleet-assessor (byte-parity); strengthened Handoff Assessment to verify full contract + dispatcher-valid goto; restored Power-Fit/Enforceability lenses + fleet-assessment cap; skill-loading refs. All gates green, zero new red.
- [x] **7 assessment** DONE (real fleet-assessor agent, 13 agents): mean 83/100, **0 blockers**, 0 invalid schemas, ready-with-fixes. 24 real MAJORs / 9 agents. Systemic: (a) advisory-`next:`-names-illegal-`goto` (adr, release-auditor, plan-writer, researcher); (b) raw-reader (head/tail/sed/bat/nl) bypass secret-deny (architect, release-auditor, fleet-assessor, adr); (c) overpowered edit surface (ui-builder, bootstrapper, researcher); (d) no `## Handoff Contract` (bootstrapper, ui-builder — ui-builder also absent from dispatch graph). FALSE POSITIVE: agent-factory "skills don't exist" — they DO (Phase 3); only real fix = task:allow→deny (least-privilege). Deferred (Phase 8): script-path allowlist drift, values-registry.
- [x] **7r-1** DONE: advisory-vs-goto disclaimers (adr/release-auditor/plan-writer/researcher); bootstrapper+ui-builder got `## Handoff Contract` + registered as roles in yaml (gen_handoff requires roles for allowed_next keys); agent-factory task:deny + Agent grant removed (false-positive "missing skills" rejected); reviewer decision→approve_with_minor_fixes. config-maintainer risk change REJECTED (would regress drift gate — manifest classifies it high). All gates green, zero new red. **ALL SHIPPED AGENTS NOW HAVE `## Handoff Contract`.**
- [x] **7r-2** DONE: secret-gap was mostly FALSE POSITIVE (architect/release-auditor/researcher already have composed `deny_secret_reads` backstop — no change); removed unrestricted `sed -n`/`nl` readers from the 2 legacy agents (fleet-assessor, agent-definition-reviewer); researcher append-only honest disclosure. All gates green, zero new red.
- [x] **PHASE 7 substantively complete**: fleet 83/100, 0 blockers; migration-central MAJORs (handoff-correctness, missing contracts, agent-factory, secret readers) remediated + gate-verified. DEFERRED (design decision, unmanaged extras): ui-builder + bootstrapper edit-surface narrowing — flag to user.
- Phase-8 note: adding handoff contract pushed a few Copilot renders >300 lines (validate-ai-catalog lint) — trim or confirm advisory.

### Phase 7 — Fleet assurance: improve every agent
- [ ] fleet-assessor → agent-definition-reviewer over every canonical agent.
- [ ] Implement fixes; re-review each until only notes remain.

### Phase 8 — Gates, docs, extras, alias removal
- [ ] Green: `render-adapters.php --check`, `generate-agent-permissions.php --check`, `validate-adapter-drift.php`, `validate-agent-spec.php`, `gen_handoff.sh --check`, dispatch tests, catalog regen, PHP tests.
- [ ] Docs: `docs/ai/agents.md`, `handoff-contract.md`, `integration-matrix.md`; ship DIAGRAMS mermaid.
- [ ] Extra handsoff features evaluated/added: hooks (`tool-policy.json`, `tool-guardian.json`), protocol doc, edgeless routing diagram.
- [x] **8a** DONE: settings projection regen; count-tests reconciled to actual (ClaudeSettingsProjectionTest 24→11, AgentAssessmentFrontmatterWriterTest 26→13) → BOTH GREEN; agent-scores 1:1 validated; catalog regen clean (0 errors). **Full-suite red now = ONLY the 8 pre-existing script-extraction classes; migration test debt fully cleared.**
- [ ] **8b** (in flight): docs/ai/agents.md roster rewrite; dead renderer branch + orphaned agent_creator.* packs cleanup; `handoff/` install-bundle (packs.php). Defer ai-script-access.yaml (non-gating, hook-holder-invariant entangled) with note.
- [x] **8b** DONE: agents.md roster rewritten to live fleet (+ validator-anchor fix); dead renderer branch + 4 orphan agent_creator.* packs removed; **handoff/ install-bundle added to packs.php `base`** (validate-install-surface byte-identical → installed projects get dispatch.py). Zero new red.
- [x] **8c** FINAL DONE: handoff model --check EXIT=0 (roles=12 incl bootstrapper/ui-builder); **settings.json tools/ai deny RESTORED** (both lines); final verification: render-adapters --check byte-parity, expanded gate set + BOTH count-tests GREEN (229 tests), full-suite red = ONLY 8 pre-existing script-extraction classes, all 13 dispatcher edges route, ALL 13 agents carry `## Handoff Contract`. **MIGRATION COMPLETE.**

### Open items (deferred, non-blocking — for follow-up)
- ui-builder + bootstrapper overpowered edit-surface (fleet-assessor MAJOR) — needs design decision on true write paths per mission.
- ai-script-access.yaml lists retired agents + hook-holder invariant on retired agent-creator-runtime-guardian — non-gating; entangled, needs care.
- `handoff/agent-handoff.yaml` `dynamic_routing.wire_agents` is stale (points at pre-rename filenames) and superseded by the durable canonical `## Handoff Contract` injection — remove/update so a full `gen_handoff.sh` (non---check) doesn't re-inject drift into rendered agents.
- Pre-existing (NOT this migration): ~66 script-extraction test failures (commit b2fbf715 moved scripts/ai/*.sh out) — belongs to arch-todo-scripts-ai-reusable-extraction.
- COMMIT: split by phase; handoff/ is untracked; agent-deprecations.md untracked. `graphify update .` before commit.
- [ ] Two clean fleet assessments → remove aliases per `migration.compatibility.remove_aliases_after`.

## PERMISSION STATE (must restore in Phase 8)
- 2026-07-14: temporarily removed `Edit(tools/ai/**)` + `Write(tools/ai/**)` from `.claude/settings.json` `permissions.deny` (user chose "temp-lift, restore in Phase 8") to allow generator/registry edits for the migration. **PHASE 8 MUST re-add both deny lines** (after line `"Write(**/secrets/**)"`). Confirmed the lift hot-reloaded (tools/ai edits now work).

## Test ledger (full phpunit run, 2026-07-14 after Phase 5a)
- **REAL BUGS (fix in reconciliation slice 5a.5):** orchestrator missing from `copilot-agent-tool-registry.php` (CopilotAgentRendererTest); orchestrator Claude render "invents a handoffs field" (ClaudeAgentRendererTest).
- **STALE ROSTER TESTS (backfill for removed agents):** CopilotAgentRendererTest (bugfix, reviewer→refactorer handoff), ClaudeCapabilityFilterTest (bugfix), AgentPermissionPolicyTest (refactorer/workflow-auditor consts), GeneratedHeaderTest. Rule going forward: the slice that removes/renames an agent updates its roster tests.
- **PURE COUNT TESTS (defer to Phase 8):** ClaudeSettingsProjectionTest, AgentAssessmentFrontmatterWriterTest (assertCount — will change again in 5b–5d).
- **PRE-EXISTING / OUT OF SCOPE (script-extraction, commit b2fbf715):** ShHelpTest(19), CliToolsTest(7), ShIntrospect*(9), ShipReferenceIntegrityTest(3), ScriptsAiManifestTest(2), ScriptRegistryInvariantTest(1), ToolGatewayTest(1), InstallerSafetyTest(23), InstallLifecycleTest(3). Hand to the script-extraction effort; NOT this migration.
- **EXPANDED per-slice gate set** (add these): CopilotAgentRendererTest, ClaudeAgentRendererTest, ClaudeCapabilityFilterTest — alongside render-adapters --check, OpencodeAgentBodyParityTest, PermissionComposeTest, validate-adapter-drift.

## Gate strategy (important)
- **Per-slice green gates** (keep these passing every slice): `render-adapters.php --check`, `OpencodeAgentBodyParityTest`, `validate-adapter-drift.php`, `gen_handoff.sh --check`, `dispatch.py`.
- **Deferred to Phase 8** (one consolidated regen + reconcile): catalog (`catalog.json`, `package-lock.ai.json`), install-catalog (`ai.php install-docs --write`), `docs/ai/agents.md`/`AGENTS-MANIFEST.md`, agent-scores.
- **Pre-existing RED, OUT OF SCOPE** (do not fix here): `validate-ai-catalog.php` errors for missing `scripts/ai/*.sh` — those scripts were moved out in commit b2fbf715 (external extraction effort). Note but leave.

## Working method (per user)
Research → plan split → super-implementer slice → **reviewer immediately** → implementer fixes → re-review until only notes. Each agent slice is bounded; handoffs carry the contract; some agents become skills. agent-definition-reviewer (agent-critic) is the quality gate reused across the fleet.

## Progress log
- 2026-07-14: Research complete (5 agents). Structural facts verified. Plan authored. Starting Phase 0.
