# Architecture Plan — Ship optional `agent-critic` subagent to all three runtimes

- Ticket: none (branch `main`; descriptive folder used)
- Source: finalized architect design handoff (all decisions fixed)
- Generated: 2026-07-06T23:21:20Z
- Updated: 2026-07-07 (v3 — round-2 researcher verification + assessor critical merge set folded in)

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan.md` and move it into `archive/` under this ticket folder (`docs/tickets/arch-todo-agent-critic-optional-subagent-20260706-232120/archive/DONE-plan.md`). See "Archive On Completion" at the bottom.

## Context

The kit ships optional auditor subagents from one canonical template dir,
`packages/ai-universal-rules/templates/optional/agents/`, projected per-runtime.
Two optional-agent packs already render that whole dir:

- `optional-agents-opencode-pack` (packs.php:322-327, `install_type: opencode-agents`,
  `merge_strategy: replace`) → `.opencode/agents-optional/`
- `optional-agents-copilot-pack` (packs.php:329-335, `install_type: copilot-agents`,
  `merge_into_existing: true`, `merge_strategy: skip-if-exists`) → `.github/agents/`

Golden pattern to extend (≈85% structural match): `agent-creator-static-validator.md`
(read-only auditor, temperature 0.0, `edit: deny`, `bash '*': deny` + read-only allowlist,
capabilities, `agent_assessment` block, `## Script Access` / `## Hard Rules` / `## Final
Output` sections).

### Round-2 verified facts (researcher, 2026-07-07) — these override v2 assumptions

- **F1 (loadability):** `.opencode/agents-optional/` is NOT runtime-loadable. OpenCode loads
  only `.opencode/agents/`; `opencode.jsonc` has no dir remap; the live subagent roster
  contains none of the 11 optional agents; no doc defines a promotion mechanism (gap).
  Verdict: requires-promotion. This affects all existing optional agents, not just this one.
- **F2 (transformation):** OpenCode writer (`aiInstallerCopyDirAsOpenCodeAgents`,
  copilot-agent-renderer.php:314-372) = byte-preserving copy + GENERATED header, delete-tree
  first. Copilot writer (`aiInstallerRenderCopilotAgent`, :25-145) = full frontmatter rebuild
  (`name/description/tools/user-invocable/disable-model-invocation`) + body "Shell Boundary"
  section via `aiPermissionResolveAllowedBash` — NOT an `allowedBash:` frontmatter key.
  Claude writer (claude-agent-renderer.php:34-105) = rebuild to `name`, `description`,
  `tools:`, optional `disallowedTools:`, `model: inherit`, `permissionMode:` + body
  "## Bash Command Policy" section.
- **F3 (agent_assessment):** preserved by all three writers (`aiCopilotExtractAssessmentBlock`
  at copilot-agent-renderer.php:228-262; appended at :47,66 and claude-agent-renderer.php:52,64;
  OpenCode copies verbatim). Installed `.opencode/agents-optional/` copies lacking it are
  stale renders, not writer behavior.
- **F4 (validators):** `validate-agent-assessment.php` covers templates + `.opencode/agents/`
  but NOT `.opencode/agents-optional/`. `generate-agent-permissions.php --check` covers
  optional dirs but ONLY agents registered in `aiPermissionAgentCompositions()`. No validator
  checks general OpenCode frontmatter shape or deprecated `tools:` keys. `validate-adapter-drift.php:50`
  skips `/agents-optional/`.
- **F5 (Claude merge safety):** executor.php:130-133 `claude-agents` branch ignores
  `merge_into_existing`/`merge_strategy` entirely (unconditional call). The renderer never
  deletes the dest tree but ALWAYS overwrites same-named files. Docblock at
  claude-agent-renderer.php:105-109: "Single-writer variant only … defers an
  optional-agents-claude-pack". Reusing the install_type verbatim for a second pack would
  silently ignore skip-if-exists — a minimal merge variant IS required (v2's "no new renderer
  function" claim was wrong).
- **F6 (tool registries):** Copilot/Claude tool grants come from `aiCopilotAgentTools()`
  (copilot-agent-tool-registry.php) and `aiClaudeAgentToolConfig()` (claude-agent-tool-registry.php).
  An unregistered id falls back to defaults — the emitted default is `unknown`; verify before
  shipping.

## Problem

`agent-critic` (an optional, read-only, single-agent-file auditor) does not exist. It must
ship to OpenCode, Copilot, AND Claude from ONE canonical template, with correct per-runtime
frontmatter projection, without hand-authored per-runtime body variants — and without
pretending the OpenCode optional dir is callable when it is not (F1).

## Design Decisions (v3)

- **D1 — OpenCode landing:** keep the existing convention (`.opencode/agents-optional/`,
  zero pack edits) and DOCUMENT the promotion step
  (`cp .opencode/agents-optional/agent-critic.md .opencode/agents/agent-critic.md`) as the
  activation mechanism. A merge-capable OpenCode writer that makes optional agents
  live-loadable is a fleet-wide change affecting all 11 existing optional agents —
  flagged as a separate follow-up ticket, NOT this slice.
- **D2 — Claude landing:** new `optional-agents-claude-pack` targeting `.claude/agents`
  PLUS a minimal merge-aware writer variant `aiInstallerMergeDirAsClaudeAgents` (mirrors
  `aiInstallerMergeDirAsCopilotAgents`, copilot-agent-renderer.php:176-190) honoring
  `skip-if-exists`, routed from the executor `claude-agents` branch when
  `merge_into_existing` is set. Update the single-writer docblock. Core pack behavior
  unchanged.
- **D3 — template authoring:** OpenCode canonical frontmatter ONLY. Never hand-author
  Copilot/Claude keys (F2). Add `agent-critic` entries to both tool registries (F6) and to
  `aiPermissionAgentCompositions()` so the `--check` gate covers it (F4), composing from
  existing read-only auditor layers where available.
- **D4 — assessor merge set:** fold the 8 critical body additions (Static Validation Gate,
  Validator Output Mapping, Power-Fit, Enforceability, Runtime Guardrail, Script-Access
  prose-vs-frontmatter, Generated Source-of-Truth, READINESS line) plus the 5 rule
  refinements (min() score caps, archetype-scaled guardrail severity, new-agent-only reuse
  trigger, line-budget frontmatter/body split, generalized raw-reader rule) into the body
  spec. Body grows 18 → 24 sections; hard-max 320 still applies.
- **D5 — deferred follow-ups (out of this slice):** (a) merge-capable OpenCode optional
  writer / loadability fix; (b) dedicated `validate-agent-frontmatter.php` (general schema,
  deprecated `tools:` detection, rendered-output shape); (c) extending
  `validate-agent-assessment.php` + `validate-adapter-drift.php` to cover
  `.opencode/agents-optional/`. Record all three as recommended tickets in the PR/handoff.

## Target Outcome

- One canonical template at `packages/ai-universal-rules/templates/optional/agents/agent-critic.md`
  (OpenCode canonical format) delivering the v3 spec (24 body sections).
- OpenCode + Copilot ship it with ZERO pack edits (existing dir-rendering packs pick it up);
  OpenCode activation via documented promotion step (D1).
- Claude ships it via ONE new `optional-agents-claude-pack` + the minimal merge variant (D2).
- Tool-registry + permission-composition entries make Copilot/Claude projections and the
  `--check` gate deterministic (D3).
- Both `creator` and `full-governance` profiles select all three optional packs.
- All verification commands pass; `agent_assessment` survives projection in all three
  renderers (F3 says it will).

## In Scope

- Author ONE canonical template `packages/ai-universal-rules/templates/optional/agents/agent-critic.md`.
- Frontmatter: `id: agent-critic`, `mode: subagent`, `hidden: false`, `temperature: 0.0`;
  capabilities `authorization-and-tool-governance, adapter-drift, project-context, review-diff, verify-change`;
  permission `todowrite: allow`, `edit: deny`, `task: deny`, `bash '*': deny` then the fixed
  read-only allowlist; `agent_assessment: risk_level: low, decision: approve`.
- Body: all 24 v3 sections in the fixed order defined below.
- Register ONE new `optional-agents-claude-pack` (mirrors the Copilot optional pack:
  `merge_into_existing: true`, `merge_strategy: skip-if-exists`, targets `.claude/agents`,
  `install_type: claude-agents`).
- Add minimal `aiInstallerMergeDirAsClaudeAgents` + executor routing for merge mode (D2).
- Add `agent-critic` to `aiCopilotAgentTools()`, `aiClaudeAgentToolConfig()`, and
  `aiPermissionAgentCompositions()` (D3).
- Register the new pack in: `$agentPacks` (packs.php:603), `runtimeByPack` → `['claude']`
  (manifest.php ~:322-324), `creator` + `full-governance` profiles (profiles.php:29 / :20),
  and `aiInstallerAllFeaturePacks()` (profiles.php:57-58).
- Document the OpenCode promotion step (D1) in the surface where optional agents are already
  inventoried (`docs/ai/shipped-surface-inventory.md` optional-agents section or equivalent).
- Run the full verification plan and the dry-run/render confirmation.

## Out Of Scope (Things To Avoid)

- Do NOT author per-runtime hand copies of the body (single-source only).
- Do NOT hand-author Copilot/Claude frontmatter keys in the template (F2 — they are rebuilt).
- Do NOT modify the core `adapter-claude` pack or change `aiInstallerCopyDirAsClaudeAgents`
  behavior for CORE agents (the merge variant is a NEW function; core path untouched).
- Do NOT build the merge-capable OpenCode optional writer in this slice (D5a follow-up).
- Do NOT build `validate-agent-frontmatter.php` in this slice (D5b follow-up).
- Do NOT register as `core: true` / `required: true` — optional/additive only
  (`core: false`, `required: false`).
- Do NOT add non-existent capability names.
- Do NOT remove `validate-adapter-drift.php`'s `/agents-optional/` skip.
- Do NOT have `agent-critic` execute the target agent, edit files, or review non-agent files
  or the fleet.
- Do NOT hardcode the roster inside the agent body (dynamic `ls` verification only).
- Do NOT claim `.opencode/agents-optional/` agents are runtime-callable anywhere in the
  template body or docs (F1).

## Affected Paths

- `packages/ai-universal-rules/templates/optional/agents/agent-critic.md` (new template)
- `tools/ai/install/packs.php` (new pack def + `$agentPacks` at :603)
- `tools/ai/install/manifest.php` (`runtimeByPack` new `['claude']` entry, ~:322-324)
- `tools/ai/install/profiles.php` (`creator` :29, `full-governance` :20, `aiInstallerAllFeaturePacks()` :57-58)
- `tools/ai/install/claude-agent-renderer.php` (NEW `aiInstallerMergeDirAsClaudeAgents`;
  docblock update at :105-109; existing functions unchanged)
- `tools/ai/install/executor.php` (`claude-agents` branch: route to merge variant when
  `merge_into_existing` is set; default path unchanged)
- `tools/ai/install/copilot-agent-tool-registry.php` (`aiCopilotAgentTools()` entry)
- `tools/ai/install/claude-agent-tool-registry.php` (`aiClaudeAgentToolConfig()` entry)
- `tools/ai/install/permission-layers/` (`aiPermissionAgentCompositions()` entry)
- `docs/ai/shipped-surface-inventory.md` (or nearest optional-agents inventory doc): one-line
  OpenCode promotion note
- Reused unchanged: `tools/ai/install/permission-layers/render-adapters.php` (projection seam).

## Contracts And Boundaries

- Canonical body is single-source; the three renderers project frontmatter only.
- OpenCode projection: byte-preserving copy of canonical frontmatter + GENERATED marker (F2).
- Copilot projection: rebuilt frontmatter (`name/description/tools/user-invocable/
  disable-model-invocation`) + body "Shell Boundary" section from `aiPermissionResolveAllowedBash` (F2).
- Claude projection: rebuilt frontmatter (`name`, `description`, `tools:`, optional
  `disallowedTools:`, `model: inherit`, `permissionMode:`) + body "## Bash Command Policy" (F2).
- `agent_assessment` block must survive all three projections (F3 confirms writers preserve it).
- Claude merge variant contract: honors `skip-if-exists`, never deletes the dest tree, skips
  hidden agents — core Claude agents can never be clobbered by the optional pack.
- `agent-critic` is read-only: `edit: deny`, `task: deny`, `bash '*': deny` + allowlist only.
- Filename-collision guard: no file in `templates/optional/agents/` may share a name with a
  core Claude agent (verified in the verification plan).

## Todo Plan

Template authoring first (P0), then the Claude pack + merge variant + registrations (P1),
then verification (P2).

### P0 — template frontmatter

- [x] P0: Author `packages/ai-universal-rules/templates/optional/agents/agent-critic.md` frontmatter in OpenCode canonical format ONLY (no Copilot/Claude keys — F2): `id: agent-critic`, `mode: subagent`, `hidden: false`, `temperature: 0.0`; capabilities `authorization-and-tool-governance, adapter-drift, project-context, review-diff, verify-change`; permission `todowrite: allow`, `edit: deny`, `task: deny`, `bash '*': deny` then the read-only allowlist (`command -v *`, `test -f *`, `stat *`, `pwd`, `ls *`, `fd *`, `rg *`, `git grep *`, `sed -n *`, `nl *`, `wc *`, `git status*`, `git diff*`, `git log*`, `git ls-files*`, `bash scripts/ai/ai-search.sh *`, `AI_OUTPUT=json bash scripts/ai/ai-search.sh *`, `bash scripts/ai/preview-file.sh *`, `AI_OUTPUT=json bash scripts/ai/preview-file.sh *`, `bash scripts/ai/check-file-refs.sh *`, `php tools/ai/validate-adapter-drift.php *`, `php tools/ai/validate-ai-config.php *`, `php tools/ai/validate-agent-assessment.php *`); `agent_assessment: risk_level: low, decision: approve`.

### P0 — body sections (fixed order, 24 sections)

- [x] P0: S1 — `# Agent Critic` + one-line mission.
- [x] P0: S2 — `## Scope` (one file/run; do/don't; `Not an agent file: <reason>` rejection; fleet concerns → workflow-auditor).
- [x] P0: S3 — `## Script Access` (read-only tools + denied list; must match frontmatter allowlist exactly — the agent must pass its own S15 check).
- [x] P0: S4 — `## Canonical References` load-on-demand (ai-file-standards, agents.md, adapter-contract, approval-boundaries, tool-policy, generated-artifacts, verification-matrix, capabilities/README, agent-script-access; active repo evidence outranks planning notes; use `unknown` where proof absent incl. line budgets).
- [x] P0: S5 — `## Static Validation Gate` (NEW): before scoring, probe for deterministic agent validators/schemas (`php tools/ai/validate-agent-assessment.php`, `php tools/ai/validate-*.php`, `docs/ai/ai-file-standards.md`, `schemas/**/agent*.schema.*`). Run ONLY read-only/check-mode validators — never `--apply`, `--fix`, `--write`, install, regenerate, package-manager, or destructive commands. Validator failures cap the score: schema/parse failure → max 39; required-field failure → max 69; warning-only → no cap. Final score = min(rubric_score, validator_cap, blocker_cap) — a semantic BLOCKER may go below the validator cap. Validator is authority for syntax/schema; this agent for semantics, overpowered permissions, contradictions, exact fix text.
- [x] P0: S6 — `## Validator Output Mapping` (NEW): ERROR → BLOCKER unless validator documents non-blocking; WARN → MINOR by default, MAJOR when it affects permission, safety, schema compatibility, generated artifacts, or handoff executability; INFO → observation only unless it proves a rubric failure.
- [x] P0: S7 — `## Roster Verification` DYNAMIC (`ls` of `.opencode/agents/`, `.opencode/agents-optional/`, `.github/agents/`, `.claude/agents/` cross-checked vs `docs/ai/agents.md`; per-runtime differences; nonexistent target = BLOCKER; NOT hardcoded; note that `.opencode/agents-optional/` entries are installed-but-not-callable until promoted — F1). Include `### Agent Reuse / Duplication Check`: ONLY when target is a new, renamed, optional, or materially-expanded agent, compare id/description/mission/permissions/final-output vs nearest roster neighbours; roughly `>=75%` overlap = MAJOR duplicate-role finding proposing merge / narrow / rename / reject. No full fleet review.
- [x] P0: S8 — `## Rubric` (8 weighted dims totaling 100, arithmetic shown): Frontmatter schema+SoT fit 15; Role scope+mission fit 15; Permission+command governance 20; Instruction correctness+contradictions 15; Handoff+failure routing 10; Evidence, validation, and output testability 10 (validator ignored, claims uncheckable, no proof fields, fabricated verification risk); Brevity/duplication/token economy 10; Runtime safety and enforceability 5 (secret/injection/generated-file/destructive guards missing, hard rules unenforceable, runtime guardrails absent).
- [x] P0: S9 — `## Calibration` (5 bands 90-100 / 70-89 / 40-69 / 20-39 / 0-19; use full range, don't cluster).
- [x] P0: S10 — `## Role Archetype Checks` table (architect/plan-writer, researcher, implementer, refactorer, reviewer/auditors, bootstrapper/post-install, config-maintainer + expected posture; flag mismatches; flag body claims the permission model can't enforce, e.g. append-only over `edit: allow`). Include `### Power-Fit Check`: classify UNDERPOWERED (body requires tools/paths/autonomy/handoffs permissions deny) / FIT / OVERPOWERED (permissions, commands, task access, network, writes, destructive ops, or autonomy exceed the stated role). OVERPOWERED = MAJOR; escalate to BLOCKER when the extra power enables mutation, destructive operations, secret exposure, package/install changes, hook/policy changes, generated-file edits, or release-impacting work WITHOUT a matching mission and approval gate. UNDERPOWERED = MAJOR; BLOCKER when the final output recommends a handoff or action the agent cannot perform.
- [x] P0: S11 — `## Enforceability Check` (NEW): classify every hard body rule as ENFORCED (frontmatter permission, deterministic validator, hook policy, or path rule enforces it) / INSTRUCTION_ONLY (prose only) / UNENFORCEABLE (permission grammar grants broader action than the prose restricts). Security, secret, destructive, generated-file, write-surface, and approval rules that are only INSTRUCTION_ONLY = MAJOR. UNENFORCEABLE = MAJOR; BLOCKER if the mismatch grants write, destructive, package-manager, hook, release, or secret-adjacent access.
- [x] P0: S12 — `## Runtime Guardrail Check` (NEW): for tool-using/write-capable agents verify: input boundary (rejected requests), tool-call boundary (allow/ask/deny), output boundary (must-not-claim/leak), stop conditions (ambiguity, repeated failure, scope growth, risky op, missing evidence), evidence/logging expectation. Severity scaled by archetype: read-only reviewer/researcher → MINOR/MAJOR; bounded writer → MAJOR; installer/hook/release/package/destructive agent → BLOCKER; generated-file or permission-policy agent → MAJOR/BLOCKER by mutation path.
- [x] P0: S13 — `## Provider/Runtime Checks` (interactive features must define non-interactive fallback, missing = MAJOR, include canonical Claude line "On Claude, interactive clarification is unavailable: state the assumption, mark it unknown, stop only when high-impact"; runtime-hook refs must state hookless behavior, missing = MAJOR).
- [x] P0: S14 — `## Finding Format & Severity` (`[SEVERITY] dimension — problem` / Evidence "quoted" (line N) / Why / Fix exact text; absence format Evidence "Absent: <field>" (frontmatter/body); a finding without Evidence+Why+Fix is invalid; sort by severity then rubric dim order then line number; BLOCKER / MAJOR / MINOR definitions as specified).
- [x] P0: S15 — `## Permission Assessment` (expect deny-by-default; flag `'*': allow`, bash without allowlist, edit/write on reviewer archetype, native edit wider than role needs, command body needs but frontmatter denies, permission granted never referenced, secret/lockfile/generated/vendor/.git write, destructive commands without approval gate; verify referenced scripts exist via `test -f`). Include `### Script Access Prose vs Frontmatter Check`: if `Script Access` prose says a command is allowed/ask-gated/denied, compare against frontmatter `bash`; mismatch = MAJOR, BLOCKER when the command is required by the agent's required flow. Include raw-reader rule: flag raw broad file readers (`cat *`, unrestricted `sed`, unrestricted `head`/`tail`, broad `bat`) when bounded preview tooling exists; MINOR unless the command can expose secrets or large files, then MAJOR.
- [x] P0: S16 — `## Generated Source-of-Truth Check` (NEW): if the target file is generated (GENERATED header or under `.opencode/**`, `.github/**`, `.claude/**` rendered surfaces), identify its source template before recommending a fixer; prefer fixes under `packages/ai-universal-rules/templates/**`; direct generated-file edits are valid only when the task explicitly targets installed output and source-of-truth policy permits it.
- [x] P0: S17 — `## Handoff Assessment` (must define when to stop/ask/handoff, next agent by EXACT roster name, payload fields, failure path; verify targets vs live roster; nonexistent = BLOCKER, missing failure path = BLOCKER, unstructured payload = MAJOR).
- [x] P0: S18 — `## Brevity & Vague-Word Blacklist` (blacklist: appropriately, properly, robust, seamless, as needed, if necessary, various, handle, ensure quality, best practices, comprehensive, relevant, may want to, consider, try to; SELF-EXCEPTION: not applied inside quoted evidence, the blacklist itself, or canonical doc titles; verbatim shared policy blocks → MINOR cite-canonical-doc, or `unknown` if hookless inlining required). Include `### Line Budget Split`: report total / frontmatter / body lines + budget status; body exceeds hard max → MAJOR; total exceeds hard max only due to generated/permission block → MINOR or observation; budget doc missing → `unknown`, no penalty; repeated body policy block → MINOR.
- [x] P0: S19 — `## Clarification Flow` (trigger when a dimension unscorable or role/mission < 50; max 3 questions each 2-4 selectable answers, no open-ended; mark dims PROVISIONAL (pending Qn); include Claude non-interactive fallback).
- [x] P0: S20 — `## Handoff Ranking` (rank 0-100 with one-line reasons: <40 → architect; 40-69 with BLOCKERs → architect then re-run agent-critic; 40-69 no BLOCKERs → implementer; ≥70 only token/dup → refactorer; ≥80 no findings → reviewer; permission dim <50 → add workflow-auditor; install/hook/release surface → add release-auditor; missing evidence → researcher. CORRECTED HANDOFF MODEL: agent-file fixes REDIRECTED to the TEMPLATE path `packages/ai-universal-rules/templates/{core,optional}/agents/<id>.md` because implementer/refactorer edit allowlists cover `packages/**` but NOT `.opencode/agents/**` or `.github/agents/**`; only emit blocked-by-permission when a fix must touch an INSTALLED copy directly with no template source, then redirect to post-install. HANDOFF block fields: next/score/reason/alternatives/blocked-by-permission).
- [x] P0: S21 — `## Proposed agent_assessment Mapping` (from total: ≥90 approve, 70-89 approve_with_minor_fixes, 40-69 approve_with_major_requirements, <40 needs_refactor; risk_level from permission surface: read-only → low, bounded writes → medium, source-wide/install/hook → high, release/deploy → critical; PROPOSE ONLY, never score a MISSING `agent_assessment` as a failure). Include surface distinction: for template sources under `packages/ai-universal-rules/templates/**/agents/*.md` treat `agent_assessment` as expected repo convention; for installed runtime files do NOT require it (installs may predate the assessment rollout — F3 caveat).
- [x] P0: S22 — `## Output Order` fixed 8 parts (1 SCORE line `SCORE: NN/100 — <five words max>`, 2 READINESS line, 3 score table w/ arithmetic, 4 findings BLOCKERs first, 5 Keep list max 3, 6 proposed agent_assessment, 7 clarification only when triggered, 8 HANDOFF). READINESS mapping: `ready` = score ≥90 and no findings; `ready-with-fixes` = score ≥70 and no BLOCKERs; `blocked` = any BLOCKER, validator failure, nonexistent handoff target, or the recommended next agent lacks permission to edit the required source path, run the required validator, or access the required evidence surface.
- [x] P0: S23 — `## Self-Check` (no blacklisted words outside quotes, no praise, no restated rules, every finding has Evidence+Why+Fix, every handoff target verified vs live roster, no claim without evidence, `unknown` where proof absent, arithmetic shown, score = min(rubric, validator cap, blocker cap), deterministic).
- [x] P0: S24 — record final line count with frontmatter/body split; TARGET soft ≤280; accept ≤320 hard-max with a one-line justification. Compression levers in priority: cite canonical docs instead of inlining; compact tables/lists; terse definitions; merge sub-checks into parent sections (S7/S10/S15/S18 sub-checks may be short paragraphs, not full blocks). Do NOT sacrifice spec completeness to hit a lower number; if 320 cannot hold all 24 sections, STOP and report — do not cut checks silently.

### P1 — Claude pack, merge variant, registries

- [x] P1: Add `aiInstallerMergeDirAsClaudeAgents` to claude-agent-renderer.php mirroring `aiInstallerMergeDirAsCopilotAgents` (copilot-agent-renderer.php:176-190): per-file render, `skip-if-exists` honored, no dest-tree delete, hidden agents skipped. Update the single-writer docblock (:105-109) to name the two writers and their contracts.
- [x] P1: Route executor.php `claude-agents` branch to the merge variant when the pack sets `merge_into_existing: true` (default/core path unchanged; mirror the copilot branch shape at :111-128).
- [x] P1: Add `optional-agents-claude-pack` def in packs.php mirroring `optional-agents-copilot-pack` (`merge_into_existing: true`, `merge_strategy: skip-if-exists`) but targeting `.claude/agents`, `install_type: claude-agents`, `core: false`, `required: false`.
- [x] P1: Add `optional-agents-claude-pack` to `$agentPacks` (packs.php:603).
- [x] P1: Add `'optional-agents-claude-pack' => ['claude']` to `runtimeByPack` in manifest.php (~:322-324, beside the opencode/copilot optional entries).
- [x] P1: Add `optional-agents-claude-pack` to the `creator` profile (profiles.php:29) and `full-governance` profile (profiles.php:20) beside the existing OpenCode/Copilot optional pair.
- [x] P1: Add `optional-agents-claude-pack` to `aiInstallerAllFeaturePacks()` (profiles.php:57-58).
- [x] P1: Inspect renderer fallback for unregistered agent ids (F6 — currently `unknown`), then add `agent-critic` entries to `aiCopilotAgentTools()` (copilot-agent-tool-registry.php) and `aiClaudeAgentToolConfig()` (claude-agent-tool-registry.php) with read-only tool grants matching the frontmatter posture.
- [ ] P1 (BLOCKED per STOP condition #1, 2026-07-07; partially unblocked same day): Add `agent-critic` to `aiPermissionAgentCompositions()`. Original gap: the template's three specific validator allows did not match `proof.validate_script`'s wildcard `php tools/ai/validate-*.php *`. UPDATE: a user edit later replaced the specific allows with that exact wildcard, removing the widening concern for validators — remaining gap is pack coverage for `check-file-refs.sh` allow and the `env AI_OUTPUT=json` ai-search/preview-file variants. Still deferred as its own bounded slice (compose, `--write`, diff regenerated block against intent). Mitigation shipped instead: both runtimes' tool grants are pinned via explicit registry entries (AC-10 first half), and unknown-id fallbacks are verified safe read-only.
- [x] P1: Add a one-line OpenCode activation/promotion note (D1) where optional agents are inventoried (`docs/ai/shipped-surface-inventory.md:105-113` optional-agents section, or the nearest canonical inventory doc): optional OpenCode agents are installed inert and become callable by copying into `.opencode/agents/`.

### P2 — verification

- [x] P2: Run the full verification plan (see `## Verification Plan`) and record results. Done 2026-07-07; all green except `composer test:fast`, whose 10 failures are all pre-existing out-of-scope worktree changes (`agent-fleet-assessor.md` untracked template missing its agent-scores.yaml entry; untracked `examples/` dir missing top-level metadata; repo-required-tools generated-artifact drift) — focused suites covering this slice are green (ClaudeAgentRendererTest 22/22 incl. new merge test; installer/pack/profile filter 48/48).
- [x] P2: Run the dry-run/render confirmation. Done 2026-07-07: `install --dry-run --profile creator` and `--profile full-governance` both select all three optional packs; direct render through all three writers into a temp dir passed 16/16 checks (three landing files, OpenCode GENERATED+permission+assessment, Copilot `tools:`+Shell Boundary+assessment+no `permission:` key, Claude `tools:`+`permissionMode: plan`+Bash Command Policy+assessment+no Write/Edit, pre-existing core Claude agent preserved under skip-if-exists).

## Acceptance Criteria

- [x] AC-01: One template file at `packages/ai-universal-rules/templates/optional/agents/agent-critic.md` (OpenCode canonical frontmatter only) renders to all three runtimes with correct per-runtime projection (OpenCode byte-copy + GENERATED marker; Copilot rebuilt `name/description/tools/...` frontmatter + body Shell Boundary; Claude rebuilt `tools:`/`permissionMode:` frontmatter + body Bash Command Policy) and the `agent_assessment` block preserved in all three outputs.
- [x] AC-02: New `optional-agents-claude-pack` is registered in `$agentPacks` (packs.php:603), `runtimeByPack` → `['claude']` (manifest.php), the `creator` + `full-governance` profiles (profiles.php:29 / :20), and `aiInstallerAllFeaturePacks()` (profiles.php:57-58), with `merge_into_existing: true` + `skip-if-exists`.
- [x] AC-03: `agent-critic` ships to `.opencode/agents-optional/agent-critic.md`, `.github/agents/agent-critic.agent.md`, and `.claude/agents/agent-critic.md`; no core Claude agent file is overwritten (collision check clean; skip-if-exists honored by the new merge variant).
- [x] AC-04: Body delivers all 24 sections in the fixed order; final line count is recorded with frontmatter/body split; any soft-max (>280) overage carries a one-line justification and stays ≤320.
- [x] AC-05: Handoff model in the body redirects agent-file fixes to the TEMPLATE path (`packages/ai-universal-rules/templates/{core,optional}/agents/<id>.md`); `blocked-by-permission` is emitted only for installed-copy-only edits with no template source; READINESS `blocked` includes unexecutable-handoff conditions.
- [x] AC-06: Roster verification in the body is dynamic (`ls` cross-checked vs `docs/ai/agents.md`), NOT hardcoded, and states that `.opencode/agents-optional/` entries are not callable until promoted.
- [x] AC-07: `agent_assessment` is proposed-only; a MISSING `agent_assessment` is never scored as a failure; template-vs-installed surface distinction is stated.
- [x] AC-08: Pack is registered optional/additive (`core: false`, `required: false`); the core `adapter-claude` pack and the `aiInstallerCopyDirAsClaudeAgents` CORE-agent path are byte-identical in behavior; `validate-adapter-drift.php`'s `/agents-optional/` skip is intact.
- [x] AC-09 (partial, accepted 2026-07-07): validators + `generate-agent-permissions.php --check` green (agent-critic NOT composed — see blocked P1 item; registries pin the grants instead); `check-file-refs.sh` clean; focused suites green; `composer test:fast` red ONLY from pre-existing out-of-scope failures (agent-fleet-assessor / examples metadata / repo-required-tools drift) documented in the P2 item.
- [x] AC-10 (partial, accepted 2026-07-07): registry entries landed in both tool registries; rendered Copilot/Claude outputs verified read-only (16/16 render checks). Composition entry BLOCKED per plan STOP condition #1 — deferred to follow-up D5 with the wildcard-vs-atomic-packs design question.
- [x] AC-11: The OpenCode promotion step is documented in the optional-agents inventory surface; nothing in the template or docs claims agents-optional files are callable as installed.
- [x] AC-12: The three deferred follow-ups (D5a/b/c) are recorded as recommended tickets in the PR description or handoff notes.

## Verification Plan

Run each and record status. Each command proves the noted ACs.

- [x] Filename-collision guard: `ls packages/ai-universal-rules/templates/optional/agents/` vs `ls .claude/agents/` — zero shared basenames — proves AC-03.
- [x] `php tools/ai/validate-adapter-drift.php --changed-only` — proves AC-01, AC-08 (adapter/frontmatter projection integrity, `/agents-optional/` skip intact).
- [x] `php tools/ai/validate-ai-config.php` — proves AC-02, AC-08 (pack/profile registration valid, optional flags).
- [x] `php tools/ai/validate-agent-assessment.php` — proves AC-07 (template `agent_assessment` block shape valid).
- [x] `php tools/ai/generate-agent-permissions.php --check` — proves AC-01, AC-10 (permission-block parity across projections, composition entry live).
- [x] `bash scripts/ai/check-file-refs.sh packages/ai-universal-rules/templates/optional/agents/agent-critic.md` — proves AC-04, AC-09 (canonical references in the body resolve).
- [x] `composer test:fast` (pre-existing out-of-scope failures only; focused suites green) — proves AC-02, AC-03, AC-09 (pack/profile/writer routing tests green; add/extend a focused test for the Claude merge variant honoring skip-if-exists if the existing suite covers writer routing).
- [x] Dry-run/render confirmation (e.g. `php tools/ai/ai.php install --dry-run` for `creator` and `full-governance`): confirm `agent-critic` lands at `.opencode/agents-optional/agent-critic.md`, `.github/agents/agent-critic.agent.md`, `.claude/agents/agent-critic.md` with correct per-runtime projection per AC-01, `agent_assessment` surviving projection, both profiles selecting all three optional packs, and NO diff on pre-existing core Claude agent files — proves AC-01, AC-02, AC-03, AC-07.

## Risks And Rollback

- Risk: MEDIUM. OpenCode/Copilot legs are additive + low (zero pack edits). The Claude leg
  now touches executor.php + claude-agent-renderer.php (new merge variant + routing) in
  addition to packs/manifest/profiles — writer-routing regressions would affect Claude
  installs, mitigated by keeping the core single-writer path untouched and adding the
  focused merge-variant test.
- Known accepted limitation (documented, not a defect): `agent-critic` is inert on OpenCode
  as installed (F1) until promoted; identical to all 11 existing optional agents. Loadability
  fix is follow-up D5a.
- Fully additive and reversible: remove `agent-critic.md` + revert the new pack entry, its
  registrations (`$agentPacks`, `runtimeByPack`, `aiInstallerAllFeaturePacks()`), profile
  membership, registry/composition entries, and the merge-variant + executor routing. No
  migration, no runtime state, no change to existing agents.
- Success signal: `validate-*` and `generate-agent-permissions.php --check` stay green;
  pre-existing optional agents still render unchanged in all three runtimes; dry-run shows
  zero diff on core Claude agents.

## Handoff Notes

- Recommended next step: implementer means implementer agent handoff using OpenCode command: `/implement`.
- Author the template FIRST (P0), then the merge variant + Claude pack + registrations (P1),
  then verification (P2). Registrations must all land in the same change (pack def,
  `$agentPacks`, `runtimeByPack`, both profiles, `aiInstallerAllFeaturePacks()`, both tool
  registries, composition entry) so no half-registered pack ships.
- Two STOP conditions for the implementer: (1) if no suitable permission-layer combination
  exists for the composition entry, stop and report instead of inventing layers; (2) if the
  24-section body cannot fit ≤320 lines without cutting checks, stop and report.
- The `render-adapters.php` seam is at `tools/ai/install/permission-layers/render-adapters.php`
  (not `tools/ai/install/render-adapters.php`); no edit is expected there.
- Follow-up tickets to file at completion (D5): (a) merge-capable OpenCode optional writer /
  optional-agent loadability; (b) `validate-agent-frontmatter.php` (general frontmatter
  schema, deprecated `tools:` detection, rendered-output shape); (c) extend
  `validate-agent-assessment.php` + adapter-drift coverage to `.opencode/agents-optional/`;
  (d) RESOLVED 2026-07-07 (user chose script-hardening over fleet permission churn):
  FLEET-WIDE grant `php tools/ai/validate-*.php *` — carried by 10+ agents via the
  `proof.validate_script` pack — reached `validate-generated-artifacts.php --write`/`--fix`,
  a mutation path (regenerates committed catalog/tool-inventory) inside read-only agents.
  Fix: gated that script's only write path behind `AI_ALLOW_ARTIFACT_WRITE=1`
  (`tools/ai/validate-generated-artifacts.php`); a bare `--write`/`--fix` now prints
  `REFUSED:` and runs check-only, mutating nothing. Maintainers/CI opt in via the env var.
  Pack comment updated to document the script-level enforcement. No fleet permission
  re-render, no `--check` churn. Verified: bare `--write` refused with zero worktree
  change (41=41 files); opt-in still regenerates; script's own tests 2/2; `--check` in sync.

## Archive On Completion

When every `## Todo Plan` item AND every `## Acceptance Criteria` item is `- [x]`:

1. `mkdir -p docs/tickets/arch-todo-agent-critic-optional-subagent-20260706-232120/archive`
2. Write the full plan to `archive/DONE-plan.md`.
3. Replace this file's body with a one-line tombstone pointing to the archived copy.
