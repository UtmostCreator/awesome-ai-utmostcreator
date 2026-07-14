# Architecture Plan — Lifecycle Model Lifecycle/Model Follow-Ups (opencode prototype)

- Ticket: none
- Source: research/implementation run findings (verified upstream `sst/opencode` @9976269)
- Generated: 2026-07-10
- Plan file: docs/tickets/opencode-architecture/plan-2-lifecycle-model-followups.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-2-lifecycle-model-followups.md` and move it into `archive/` under this branch folder (`docs/tickets/opencode-architecture/archive/DONE-plan-2-lifecycle-model-followups.md`). See "Archive On Completion" in the architecture-plan-writer contract for the exact steps.

## Prominent Scope Caveat (READ FIRST)

- **This is a follow-up to `plan-1-lifecycle-model-hardening.md`.** Plan-1 covered
  P0/P1/P2 (DONE, code applied, static-verified) and deferred P3-7/P3-8/P3-9. This
  plan-2 captures NEW verified upstream research findings to FEED those deferred P3
  items and adds new ones. See plan-1 for the prior slice and its acceptance criteria.
- **Two distinct surfaces.** This plan *document* is written to the TRACKED
  `docs/tickets/` location. The plan's IMPLEMENTATION surface is entirely the
  UNTRACKED directory
  `___ARCHITECTURE_2.0/opencode_architecture/lifecycle-model/lifecycle/`
  (model YAML + schema + `gen_mermaid.py`). These are not the same place; do not
  conflate them. Do **NOT** touch the untracked prototype directory as part of
  *writing this plan* — encoding happens in a later implementer slice.
- Per AGENTS.md "Prototype Lane" the implementation target is an exploration lane:
  code may live there but must not be merged to production paths; promotion requires
  respecification as a normal bounded slice passing the standard workflow.
- **Upstream provenance for all Section 2/3/4 evidence:** local clone
  `github.com:anomalyco/opencode.git` (canonical slug `sst/opencode`), branch `dev`,
  commit `9976269ab1accfc9f9dc98a4a688c516934de422`. All `file:line` evidence below is
  under `packages/` at that commit.
- **Verification limitation:** `python3 *` runtime verification of the model was
  policy-blocked in the implementing sessions. The plan-1 P0/P1/P2 edits AND the new
  provenance edits recorded in Section 1 are **STATIC-verified only**. The
  `generated/*.mmd` artifacts are **stale** pending a regeneration run
  (`python3 scripts/gen_mermaid.py`). See Section 5.
- The repo's `composer test` does **NOT** cover this Python + Node toolchain.

## Context

Verified upstream research (two runs) against `sst/opencode` @9976269 produced:

1. A set of MODEL EDITS already applied to
   `___ARCHITECTURE_2.0/opencode_architecture/lifecycle-model/lifecycle/model/opencode.lifecycle.yaml`
   (272 → 295 lines), static-verified, runtime-unverified (Section 1 — record as DONE).
2. A broad EVIDENCE CATALOG of upstream behavior across six areas: user-supplied file
   loading, context/truncation, subagent context passing, prompts/skills at runtime,
   behavior-vs-context-size, and limitations/policies/permissions (Section 2).
3. Three MODEL CORRECTIONS where the current model is wrong (Section 3).
4. A prioritized list of NEW/EXTENDING follow-up TODO items (Section 4) that feed the
   deferred P3 items in plan-1.
5. Outstanding verification (Section 5) that is blocked until `python3` is permitted.

This plan-2 persists that research so an architect can scope the encoding and an
implementer can encode it once `python3` verification is available.

## Problem

Plan-1's deferred P3 items (P3-7 precedence/presentation, P3-8 statechart/compaction
semantics, P3-9 freshness/CI) were gated on verifying real upstream `sst/opencode`
behavior. That verification has now been done (@9976269), producing concrete
`file:line` evidence, three model corrections, and new subsystem findings (truncate,
plugin-load policy, compaction/prune, subagent isolation). Without persisting these,
the evidence is lost and the deferred items cannot be safely encoded.

## Target Outcome

One durable, evidence-backed plan that: (a) records the already-applied Section 1 model
edits as DONE with confidence-tagged evidence; (b) catalogs the Section 2/3 research
findings and corrections; (c) lists the prioritized Section 4 follow-up TODO items
(unchecked — these are future encoding work); and (d) records the Section 5 outstanding
verification. No prototype file is changed by *this plan document*.

## In Scope

- Persisting research findings and follow-up TODO items as a plan document only.
- Recording the already-applied Section 1 provenance/model edits as DONE with evidence.
- Cataloging the Section 2 evidence and Section 3 corrections for future encoding.
- Listing the prioritized Section 4 follow-ups (all unchecked) that extend plan-1's
  deferred P3-7/P3-8/P3-9 or add NEW subsystem nodes.
- Recording the Section 5 outstanding `python3`-gated verification as pending.

## Out Of Scope (Things To Avoid)

1. Do **NOT** touch the untracked prototype directory
   (`___ARCHITECTURE_2.0/opencode_architecture/lifecycle-model/lifecycle/`) while
   writing this plan; encoding is a later implementer slice.
2. Do **NOT** edit any repo-tracked file except optionally a trivial cross-reference
   from plan-1 (this plan does not require any plan-1 edit).
3. Do **NOT** introduce CUE or any rejected runtime-semantic encoding.
4. Do **NOT** add a new YAML field without the paired schema property — meta and
   machine defs are `additionalProperties: false`; every new field needs a paired
   schema property in the same change.
5. Do **NOT** invent semantics. Keep all Section 5 unknowns as `unknown`
   (mergeConfigConcatArrays/mergeDeep scalar-conflict semantics; task-result truncation
   on the specific return path vs generic wrapper; `containsPath` impl details; whether
   references content is ever loaded beyond listing; `--ignore-scripts` absence
   confirmed only by grep). Do not fabricate resolutions.
6. Do **NOT** delete the "policy statements" node — **rename/remap** it. It IS the
   permission ruleset (positional findLast/default-ask), not a separate system
   (Section 3, correction 1/2).
7. Do **NOT** encode a "capabilities" loader — OpenCode has none; "capabilities" is an
   OUR-kit-docs concept only (Section 3, correction 3, provable negative).
8. Do **NOT** promote the prototype to production or add production CI referencing it
   without separate respecification (AGENTS.md Prototype Lane).
9. Do **NOT** re-open plan-1's P0/P1/P2 — they are DONE; this plan only feeds the
   deferred P3 items and adds new ones.

## Affected Paths

- `docs/tickets/opencode-architecture/plan-2-lifecycle-model-followups.md` (this file — the only write).
- Implementation surface for the Section 4 follow-ups (LATER slice, do not touch now,
  all UNTRACKED under `.../lifecycle/`):
  - `model/opencode.lifecycle.yaml`
  - `model/lifecycle.schema.json`
  - `scripts/gen_mermaid.py`
  - `generated/*.mmd` (regenerated only)

## Contracts And Boundaries

- Schema is closed: `additionalProperties: false` on root/meta/machine — every new
  field needs a paired schema property in the same change (inherited from plan-1).
- Split convention: JSON Schema owns shape constraints; `semantic_errors` owns
  relational checks.
- Prototype-lane boundary: no repo-tracked file may change to encode these items; no
  production CI.
- Evidence contract: every finding below carries `file:line` under `packages/` at
  `sst/opencode`@9976269 plus a confidence percentage. Confidence is the researcher's
  strength-of-evidence estimate, not a guarantee.
- Positional-precedence contract: permission resolution is `findLast`/default-ask,
  NOT a fixed `deny > ask > allow` constant (Section 3).

## Section 1 — Already-Applied Model Edits (DONE, static-verified, runtime-unverified)

Applied to `.../lifecycle/model/opencode.lifecycle.yaml` (272 → 295 lines). Recorded
here as DONE with evidence; runtime proof is pending Section 5.

- [x] S1-1 (98%): `meta.upstream` set to real commit
  `9976269ab1accfc9f9dc98a4a688c516934de422`, `ref: dev`, canonical repo `sst/opencode`,
  with clone-provenance comment.
- [x] S1-2 (95%): Provenance line-ranges corrected @9976269 —
  `config_precedence` loadInstanceState `config.ts [314,596]`;
  `agent_loop` runLoop `prompt.ts [1081,1341]` + maxSteps/MAX_STEPS `[1178,1281]` +
  `processor.ts` retry driver `[660,676]` (tri-state 713-715) + `retry.ts` policy
  `[176,199]` + delay `[35,66]` + `compaction.ts` terminal `[404,412]`;
  `permission_engine` `permission.ts` evaluateInput..assert `[155,218]` + reply
  `[220,286]` + `schema/permission.ts` Reply/Effect enums `[40,54]`;
  `plugin_lifecycle` path `packages/opencode/specs/tui-plugins.md` + added
  `specs/v2/catalog-config-plugin-lifecycle.md`.
- [x] S1-3 (95%): `MAXSTEPS` node note — soft/advisory `MAX_STEPS_PROMPT` injection on
  `isLastStep` (`step >= agent.steps`, default `Infinity`); tools still passed
  (`prompt.ts:1285`).
- [x] S1-4 (96%): NEW terminal node `CTX_OVERFLOW_ERR` (class `danger`) + edge
  `COMPACT -> CTX_OVERFLOW_ERR` "still overflows" — `ContextOverflowError`,
  `finish=error`, stop, never retried (`retry.ts:70`). Kept `COMPACT -> OVERFLOW`
  success path.
- [x] S1-5 (95%): `RETRY` node note — unbounded attempts for retryable errors
  (429/5xx/rate-limit); only backoff delay capped (`RETRY_MAX_DELAY`); ends only when
  non-retryable.
- [x] S1-6 (95%): `permission_engine` outcomes corrected — reply set is exactly
  `once` / `always` (+save persists allow-rule) / `reject` (+feedback = correction);
  NO timeout, NO distinct approve-for-session; `cancel` = scope teardown
  (`DeclinedError`) + same-session cascade-reject, NOT a reply value.

## Section 2 — Research Findings Evidence Catalog (by area)

Each entry: finding — `file:line` — (confidence%). Record only; encoding is Section 4.

### A. User-supplied file loading

**A.1 Skills**

- discoverSkills roots+order: `skill/index.ts:184-227` (98).
- External dirs are `.claude` and `.agents`, NOT `.opencode`: `skill/index.ts:21-22` (98).
- Globs: external `skills/**/SKILL.md`; opencode `{skill,skills}/**/SKILL.md`;
  config-path `**/SKILL.md`: `skill/index.ts:23-25` (98).
- Scan order: global external (`~/.claude/skills`, `~/.agents/skills`) → project-upward
  external → config `.opencode` dirs → `skills.paths` → `skills.urls`:
  `skill/index.ts:186-227` (96). `.claude` before `.agents`; claude disableable:
  `skill/index.ts:187-188` (95).
- Duplicate skill name → warn then overwrite (last-scanned wins):
  `skill/index.ts:125-139` (95). Built-in customize-opencode registered before disk so
  user override wins: `skill/index.ts:276-284` (97). Body = markdown after frontmatter,
  stored eagerly: `skill/index.ts:134-139` (95).
- Remote skills from URL `index.json`, cached `Global.Path.cache/skills`, versioned
  atomic swap: `skill/discovery.ts:49-132` (92). Disable flags
  `OPENCODE_DISABLE_EXTERNAL_SKILLS` / `OPENCODE_DISABLE_CLAUDE_CODE(_SKILLS)`:
  `effect/runtime-flags.ts:21,27-30` (97).

**A.2 Instructions (AGENTS.md/CLAUDE.md)**

- Global files: `<global.config>/AGENTS.md` then `~/.claude/CLAUDE.md`:
  `session/instruction.ts:60-63` (97). Project filenames order: `AGENTS.md`,
  `CLAUDE.md`, `CONTEXT.md` (deprecated): `session/instruction.ts:64-68` (97).
- FIRST global match wins (break): `session/instruction.ts:115-120` (96). FIRST
  project-level match wins, does NOT stack every ancestor:
  `session/instruction.ts:122-133` (97). `config.instructions` (glob/`~/` + http/https
  URLs) added AFTER the AGENTS.md pair: `session/instruction.ts:135-169` (95). Remote
  fetch 5s timeout, fail → empty: `session/instruction.ts:95-103` (94).
- Aggregate string `"Instructions from: <path>\n<content>"`:
  `session/instruction.ts:165-168` (96). read tool walks upward, attaches nearby
  AGENTS.md/CLAUDE.md once per message as `<system-reminder>`:
  `session/instruction.ts:179-221` + `tool/read.ts:355-357` (95). Loaded paths tracked
  via read `metadata.loaded`: `session/instruction.ts:17-32` + `tool/read.ts:365` (94).
  `OPENCODE_DISABLE_PROJECT_CONFIG` → global only:
  `session/instruction.ts:81-88,123` (93).

**A.3 Config precedence**

- Merge order remote/global → `OPENCODE_CONFIG` → project `opencode.json(c)` →
  `.opencode` dir configs → `OPENCODE_CONFIG_CONTENT` → active-org remote → managed dir
  → macOS managed prefs override everything: `config/config.ts:356-534`
  (92, later-wins linear merge).
- Project config files returned farthest-first (`.toReversed()`), nearest-to-cwd merges
  last = highest priority: `config/paths.ts:10-21` + `fs-util.ts:161-175` (95). Config
  dirs deduped list: `config/paths.ts:23-41` (95). merge = `mergeConfigConcatArrays`
  (arrays concat): `config/config.ts:351-354` (88). `tools:{}` lowered to permission
  allow/deny; `OPENCODE_PERMISSION` env merged over: `config/config.ts:545-564` (90).

**A.4 Commands/Agents/Modes/Plugins/References**

- Commands `{command,commands}/**/*.md`, invalid = throw: `config/command.ts:13-37` (95).
- Agents `{agent,agents}/**/*.md`; modes `{mode,modes}/*.md`: `config/agent.ts:11-58` (95).
- Merged per `.opencode` dir via `mergeDeep` later-wins: `config/config.ts:459-465` (90).
- References experimental (`experimentalReferences`), listed not loaded:
  `session/system.ts:62-95` + `runtime-flags.ts:42` (85).
- `fs-util.up()` nearest-first is the ordering primitive behind ALL precedence:
  `core/src/fs-util.ts:161-175` (97).

### B. Context / truncation / line limits

- Tool-output cap `MAX_LINES=2000`, `MAX_BYTES=51200`: `tool/truncate.ts:15-16` (98).
  Configurable `tool_output.max_lines`/`max_bytes`: `tool/truncate.ts:80-81` (97). On
  truncate: full text to disk, preview+hint, head default/tail optional:
  `tool/truncate.ts:85-141` (97). Hint changes if agent has task tool (delegate to
  explore): `tool/truncate.ts:129-131` (95). Files retained 7 days, cleaned hourly:
  `tool/truncate.ts:13,54-66,143-148` (92).
- Read tool `DEFAULT_READ_LIMIT=2000` lines, `MAX_LINE_LENGTH=2000` chars,
  `MAX_BYTES=50KB`, `SAMPLE_BYTES=4096`: `tool/read.ts:13-18` (98).
  "Output capped at 50 KB" / "Showing lines X-Y of N": `tool/read.ts:344-350` (96). Bash
  tail truncation: `tool/shell.ts:225-236,569-590` (93).
- NO hard line cap on skill/instruction loading (whole content loaded):
  `skill/index.ts:134-139` + `session/instruction.ts:91-93` (90).
- `usable = limit.input ? max(0, limit.input - reserved) : max(0, context - maxOutput)`;
  `reserved = compaction.reserved ?? min(20000, maxOutput)`: `session/overflow.ts:8-20`
  (97). Overflow trigger `count >= usable`; disabled if `compaction.auto === false` or
  `context === 0`: `session/overflow.ts:22-33` (97). Token count `tokens.total` or
  `sum(input + output + cache.read + cache.write)`: `session/overflow.ts:31-32` (96).
  `COMPACTION_BUFFER=20000`: `session/overflow.ts:8` (98).
- KNOWN BUG `limit.input` headroom asymmetry: usable subtracts no output headroom →
  compaction fires too late: `session/overflow.ts:17-19` +
  `compaction.test.ts:442-476` (96); ~30K more usage allowed:
  `compaction.test.ts:499-514` (95); in-tree issues #10634, #8089, #11086, #12621;
  PRs #6875, #12924: `compaction.test.ts:450-451` (90).

### C. Agent → subagent context passing

**Down:**

- Fresh child session `sessions.create({parentID})`, NOT parent session:
  `tool/task.ts:142-158` (96).
- Child prompt = only `params.prompt`, NO parent history: `tool/task.ts:186-198` (92).
- Inherits ONLY `external_directory` + deny rules:
  `agent/subagent-permissions.ts:14-26` (97).
- Own agent def (prompt/model/tools) via `agent.get(subagent_type)`:
  `tool/task.ts:116-119,167` (96).
- Model = subagent's else parent assistant model: `tool/task.ts:167-170` (95).
- Default-deny `todowrite`+`task` unless subagent ruleset permits:
  `tool/task.ts:129-141` + `subagent-permissions.ts:18-25` (96).
  `experimental.primary_tools` also denied: `tool/task.ts:136-140` (90).
- `task_id` resumes same child: `tool/task.ts:121-123` (94). Child system prompt
  assembled independently: `prompt.ts:1257-1269` (88).

**Up:**

- Result = last text part only: `tool/task.ts:199` (97).
- Wrapped `<task id state><task_result>...</task_result></task>` as tool output:
  `tool/task.ts:64-79,316-320` (96).
- Not length-bounded at return but tool-output goes through Truncate 50KB/2000:
  `tool/task.ts:316-320` + `tool/tool.ts:131-142` (85).
- Background subagents inject synthetic user message on done: `tool/task.ts:202-240` (90).
- command-subtasks get synthetic "Summarize... and continue":
  `prompt.ts:430-448` (92).

### D. Prompts & skills at runtime

- System prompt section order: env → instructions → mcp → skills (+optional
  structured-output): `prompt.ts:1257-1271` (97).
- Base provider prompt by model id: `system.ts:27-42` (96), prepended only if agent has
  no custom prompt: `llm/request.ts:60` (88). env block: model id, cwd, worktree, vcs,
  platform, date, `<available_references>`: `system.ts:60-95` (95).
- Skills exposed as prompt text (`<available_skills>` verbose) AND skill tool (terse
  desc) deliberately: `system.ts:98-110` + `skill/index.ts:321-346` (96). Omitted if
  skill permission disabled: `system.ts:99` (95). `available(agent)` filters
  `permission !== deny`; loading requires `ctx.ask({permission:skill, patterns:[name]})`:
  `skill/index.ts:310-315` + `tool/skill.ts:27-32` (96). Skill tool loads trimmed
  content + base dir + sampled file list (ripgrep limit 10): `tool/skill.ts:34-66` (95),
  wrapped `<skill_content>`/`<skill_files>`: `tool/skill.ts:45-61` (95). MCP
  instructions `<mcp_instructions>` permission-filtered: `system.ts:112-128` (90).

### E. Behaviour vs context size

- Auto-compaction: lastFinished not summary AND isOverflow → compaction turn:
  `prompt.ts:1161-1168` (96). Provider overflow `result==="compact"` → compaction with
  overflow flag: `prompt.ts:1319-1329` (94). Prune runs after every turn (forked):
  `prompt.ts:1338` + `compaction.ts:243-287` (96).
- Prune constants `PRUNE_MINIMUM=20000`, `PRUNE_PROTECT=40000` (keeps newest 40K tokens
  tool output): `compaction.ts:28-29,269-278` (97). Prune PROTECTS skill tool output
  (`PRUNE_PROTECTED_TOOLS=["skill"]`): `compaction.ts:31,267` (98). Skips last 2 user
  turns, stops at first summary: `compaction.ts:258-268` (95). Only if
  `prunable > PRUNE_MINIMUM`: `compaction.ts:278-286` (95). Disabled by
  `compaction.prune:false` / `OPENCODE_DISABLE_PRUNE`: `compaction.ts:245` +
  `config.ts:582-584` (95).
- Compaction tail: `tail_turns` default 2; `preserve_recent_tokens` = config or
  `min(8000, max(2000, floor(usable*0.25)))`: `compaction.ts:32-34,80-85,193` (96). Tool
  output stripped to `TOOL_OUTPUT_MAX_CHARS=2000`, media stripped for summary:
  `compaction.ts:30,351-354` (96). Overflow compaction replays prior user turn/drops
  media: `compaction.ts:310-326,404-504` (90). Autocompact disabled
  `OPENCODE_DISABLE_AUTOCOMPACT`: `config.ts:579-581` (95). Plugin hooks can inject
  context/override compaction prompt/veto autocontinue: `compaction.ts:342-348,451-472`
  (88).

### F. Limitations / policies / permissions

- Rule resolution `findLast` across flattened rulesets = last matching wins, default
  ask: `permission/index.ts:28-38` (98). ask loop: any deny short-circuits
  `DeniedError`; allow continues; else prompt: `permission/index.ts:67-107` (97).
  Effective precedence is POSITIONAL last-wins, NOT fixed `deny>ask>allow` constant:
  `permission/index.ts:28-38,72-84` (90). `fromConfig` flattens config map, `~/` `$HOME`
  expanded: `permission/index.ts:178-198` (95). `disabled()`/`visibleTools()` hide
  `*-deny` tools; edit/write/apply_patch → edit, mcp reads → read:
  `permission/index.ts:204-219` (95). "always" replies persist as session approved
  allow-rules, auto-resolve pending: `permission/index.ts:143-166` (92).
- `external_directory`: any target outside instance path triggers
  `ask({permission:external_directory, patterns:[glob]})`:
  `tool/external-directory.ts:15-45` (97). `containsPath(target, ctx)` decides boundary,
  win32-normalized: `tool/external-directory.ts:24-33` (93). Approvals inherited by
  subagents: `subagent-permissions.ts:21-22` (95). read tool asserts external dir before
  read: `tool/read.ts:9` (88).
- Plugin: npm compat-gated `checkPluginCompatibility`, file plugins skip gate:
  `plugin/loader.ts:123-131` (95). Retry-once ONLY for file plugins retryable pre-import
  failure; npm/dynamic-import permanent (Bun caches failed):
  `plugin/loader.ts:203-229` (94). Deprecated plugins silently ignored:
  `plugin/loader.ts:160-161` (93). Pure mode `OPENCODE_PURE` skips external plugins:
  `plugin/tui/runtime.ts:1089-1090` + `runtime-flags.ts:18` + `index.ts:70` (92).
  `OPENCODE_DISABLE_DEFAULT_PLUGINS`: `runtime-flags.ts:19` (90). No `--ignore-scripts`
  in opencode plugin npm install path (only core test + publish):
  `core/test/npm-config.test.ts:28` + `opencode/script/publish.ts:42-48`
  (80, negative finding).

## Section 3 — Model Corrections (things our model gets WRONG)

- [ ] C1: Permission model is POSITIONAL `findLast`/default-ask, NOT a fixed
  `deny > ask > allow` constant. Correct the model. `permission/index.ts:28-38`.
- [ ] C2: There is NO separate "policy statements" system — it IS the permission
  ruleset. Any "policy statements" node must be **renamed/remapped, not deleted and not
  extended** into a separate system.
- [ ] C3: OpenCode has NO "capabilities" loader — "capabilities" is a concept in OUR kit
  docs only; no OpenCode file loads a `capabilities/` tree (provable negative from the
  discovery paths in A.1–A.4). Do not encode a capabilities loader.

## Todo Plan

All follow-ups are UNCHECKED (future encoding work in a later implementer slice). Each
tag = `[strength, confidence%]` and names the plan-1 item it extends (or NEW). Encoding
is gated on Section 5 `python3` verification.

### HIGH priority

- [ ] TODO-1 [HIGH, 97] Model the precedence primitive `fs-util.up()` nearest-first;
  wire skills/instructions/config to it. Extends plan-1 P3-7.
  (`core/src/fs-util.ts:161-175`, `config/paths.ts:10-41`)
- [ ] TODO-2 [HIGH, 97] Encode system-prompt section order
  env → instructions → mcp → skills as an ordered assembly node. Extends plan-1 P3-8.
  (`prompt.ts:1257-1271`)
- [ ] TODO-3 [HIGH, 96] Add subagent-isolation machine: fresh session, inherit
  `external_directory`+deny only, default-deny `todowrite`/`task`, return-last-text.
  Extends plan-1 P3-9. (`tool/task.ts:116-199`, `subagent-permissions.ts`)
- [ ] TODO-4 [HIGH, 97] Add compaction+prune state machine with named constants +
  skill-protection + tail-preservation edges. Extends plan-1 P3-8.
  (`session/overflow.ts`, `session/compaction.ts`)
- [ ] TODO-5 [HIGH, 98] Add Truncate subsystem node (2000 lines / 50KB, config keys
  `tool_output.max_lines`/`max_bytes`, task-aware hint). NEW.
  (`tool/truncate.ts`, `tool/read.ts:13-17`)

### MEDIUM priority

- [ ] TODO-6 [MED, 96] Correct permission model to positional `findLast`/default-ask;
  **rename** (do not delete) any "policy statements" node. Extends plan-1 P3-9.
  (`permission/index.ts:28-38`)
- [ ] TODO-7 [MED, 96] Skill discovery order + duplicate-overwrite + built-in override
  node. Extends plan-1 P3-7. (`skill/index.ts:184-284`)
- [ ] TODO-8 [MED, 96] Instruction first-match-wins (global/project/`config.instructions`)
  node + read-tool upward attach as `<system-reminder>`. Extends plan-1 P3-7.
  (`session/instruction.ts:115-221`)
- [ ] TODO-9 [MED, 90] Plugin load policy edges (retry-once / pure-mode / compat-gate).
  NEW. (`plugin/loader.ts`, `plugin/tui/runtime.ts:1089`)

### LOW priority

- [ ] TODO-10 [LOW, 95] Risk node: `limit.input` headroom asymmetry bug with issue refs.
  NEW. (`session/overflow.ts:17-19`, `compaction.test.ts:442-514`)

## Acceptance Criteria

Each observable and testable. AC-01/AC-02 gate re-verifying Section 1; AC-03..AC-12 gate
the Section 4 follow-ups; each is checked only when its encoding lands AND passes the
`python3`/`node` toolchain.

- [ ] AC-01: `python3 scripts/gen_mermaid.py --check` exits `0` against the current model
  with the Section 1 provenance edits applied (revalidates the 272 → 295 edits).
- [ ] AC-02: `python3 scripts/gen_mermaid.py` regenerates `generated/*.mmd` and
  `node scripts/check_mermaid.mjs` parses them (closes the "stale generated" state and
  plan-1 AC-01..AC-08).
- [ ] AC-03: The model contains a node/relationship representing `fs-util.up()`
  nearest-first precedence, referenced by skills/instructions/config (TODO-1), and
  `--check` still exits `0`.
- [ ] AC-04: An ordered system-prompt assembly node encodes
  env → instructions → mcp → skills in that order (TODO-2).
- [ ] AC-05: A subagent-isolation machine encodes fresh session + inherit
  external_directory/deny only + default-deny todowrite/task + return-last-text (TODO-3).
- [ ] AC-06: A compaction+prune machine encodes the named constants
  (`PRUNE_MINIMUM=20000`, `PRUNE_PROTECT=40000`, `COMPACTION_BUFFER=20000`) with
  skill-protection and tail-preservation edges (TODO-4).
- [ ] AC-07: A Truncate subsystem node encodes 2000 lines / 50KB and the
  `tool_output.max_lines`/`max_bytes` config keys (TODO-5).
- [ ] AC-08: The permission node is positional `findLast`/default-ask AND any "policy
  statements" node is renamed (not deleted, not a separate system) (TODO-6, C1/C2).
- [ ] AC-09: A skill-discovery node encodes scan order + duplicate-overwrite +
  built-in-override (TODO-7).
- [ ] AC-10: An instruction node encodes first-match-wins across global/project/
  `config.instructions` + read-tool upward `<system-reminder>` attach (TODO-8).
- [ ] AC-11: Plugin-load policy edges encode retry-once / pure-mode / compat-gate
  (TODO-9).
- [ ] AC-12: A risk node encodes the `limit.input` headroom asymmetry bug with issue
  refs (TODO-10).

### Negative Acceptance Criteria

- [ ] AC-N1: No "capabilities" loader is encoded (C3 provable negative).
- [ ] AC-N2: The "policy statements" node is renamed, never deleted (C2).
- [ ] AC-N3: No CUE and no repo-tracked file changed to encode any follow-up.
- [ ] AC-N4: No new YAML field lacks its paired schema property
  (`additionalProperties: false`).
- [ ] AC-N5: No Section 5 unknown was invented/resolved without upstream evidence.

## Section 5 — Outstanding Verification (PENDING — `python3`-gated)

Run once `python3` is permitted:

`python3 scripts/gen_mermaid.py --check`

`python3 scripts/gen_mermaid.py`

`node scripts/check_mermaid.mjs`

- `--check` (expect exit `0`) revalidates the Section 1 edits.
- The regenerate run refreshes the stale `generated/*.mmd` (currently stale).
- `check_mermaid.mjs` parses the regenerated artifacts.
- Together these close plan-1 AC-01..AC-08 and validate the Section 1 edits.

### Unknowns (do NOT invent — keep as `unknown`)

- `mergeConfigConcatArrays` / `mergeDeep` scalar-conflict semantics
  (`config/config.ts:351-354`).
- Whether task-result text is truncated on the specific return path vs only the generic
  wrapper (`tool/task.ts:316-320` vs `tool/tool.ts:131-142`).
- `containsPath` implementation details.
- Whether references content is ever loaded beyond listing.
- Absence of `--ignore-scripts` confirmed only by grep (`plugin/shared.ts` not read).

## Verification Plan

- Section 1 re-verification: `python3 scripts/gen_mermaid.py --check` → exit `0`
  (AC-01).
- Regeneration proof: `python3 scripts/gen_mermaid.py` then
  `node scripts/check_mermaid.mjs` → both exit `0` (AC-02).
- Per-follow-up proof (AC-03..AC-12): after each TODO's encoding lands, run
  `python3 scripts/gen_mermaid.py --check` (exit `0` = model still valid) and, where the
  follow-up changes emitted diagrams, a full regenerate + `check_mermaid.mjs`.
- Negative ACs verified by inspection of the model/schema diff (no capabilities loader,
  policy node renamed not deleted, no CUE, no repo-tracked change, paired schema
  properties present, unknowns untouched).
- Anti-freeze budgets (inherited from plan-1): `--check` ≤ 30s; full regenerate ≤ 60s;
  `check_mermaid.mjs` ≤ 90s. On overrun: kill and bisect by single `.mmd`.
- Report Python and Node exit codes honestly for each run.

## Risks And Rollback

- This plan document: LOW — persists research only; no prototype file touched by writing
  it. Rollback: delete `plan-2-lifecycle-model-followups.md`.
- Section 1 edits (already applied): LOW but RUNTIME-UNVERIFIED — static-verified only;
  `generated/*.mmd` stale. Risk: a `--check` failure would surface only when `python3`
  runs. Rollback: revert the untracked-file provenance edits and regenerate.
- Section 4 follow-ups (future slice): P3-8/P3-9-class MEDIUM — statechart/compaction
  semantics and subagent isolation carry higher churn; each is gated on Section 5
  verification and must respect the paired-schema and rename-not-delete constraints.
- Success signal: `--check` exits `0` after each encoding, regenerated artifacts parse,
  and the negative ACs hold by inspection.

## Handoff Notes

- This plan is a research + follow-up catalog; it authorizes no prototype edits by
  itself. Encoding is a separate implementer slice, gated on Section 5 `python3`
  verification.
- Cross-reference: this extends `plan-1-lifecycle-model-hardening.md` — Section 1 records
  edits beyond plan-1's DONE P0/P1/P2; Section 4 feeds plan-1's deferred
  P3-7/P3-8/P3-9 and adds NEW subsystem nodes (Truncate, plugin-load, compaction/prune,
  subagent isolation).
- Keep every Section 5 unknown as `unknown`; do not fabricate resolutions.
- Rename (do not delete) the "policy statements" node; encode no "capabilities" loader.
- Recommended next step: architect to scope the Section 4 encoding into bounded slices
  respecting the paired-schema and rename-not-delete constraints, THEN implementer to
  encode once `python3` verification is available — implementer means implementer agent
  handoff using OpenCode command: /implement
