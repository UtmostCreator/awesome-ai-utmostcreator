# OpenCode Under the Hood — Authoring Reference for Better Agents/Skills/Commands/Permissions

- Status: Reference (research output)
- Created: 2026-07-10
- Purpose: How OpenCode actually loads/unloads files, resolves precedence, hands off to
  subagents, and assembles turns — read through the lens of **authoring better primitives**
  (agents, subagents, skills, capabilities, instructions, commands, tools, custom tools,
  policies, references, permissions, rules, priorities).
- Upstream: `sst/opencode` clone `/home/utmostcreator/Projects/opencode`, verified live at
  commit `9976269` (line numbers read live; existence high-confidence).
- Companion: `plan.md` (diagram-completeness plan) in this same ticket folder.
- Method: read-only `repository-researcher` pass + live re-verification of 5 authoring-critical
  claims in this session (`task.ts:199`, `subagent-permissions.ts:14-27`,
  `permission/index.ts:28-38`, `skill/index.ts:322`, `instruction.ts:122-133`).

---

## TL;DR — Top 10 Authoring Rules (each code-cited)

1. **Anchor every permission block with a `"*"` rule; put specific rules LAST.** Resolution is
   `findLast` positional-wins with default `ask` when nothing matches — an unanchored ruleset
   silently degrades to prompting. `permission/index.ts·evaluate·28-38` (verified).
2. **To HIDE a tool from an agent, deny it with a bare `"*"` pattern** (`{"bash":"deny"}`).
   A scoped deny still leaves the tool visible (it just blocks specific calls).
   `permission/index.ts·disabled·204-214`.
3. **Grant subagents `task`/`todowrite` explicitly or they cannot chain or track work** — both
   are force-denied by default unless the subagent's own permission already lists them.
   `tool/task.ts·129-141`, `agent/subagent-permissions.ts·14-27` (verified).
4. **A subagent's ONLY handoff to the parent is its LAST text block.** Tool calls, reasoning,
   and file-write narration do not cross back. End every subagent with a complete standalone
   summary. `tool/task.ts·runTask·199` (verified), `renderOutput·64-79`.
5. **Subagents inherit only the parent's `deny` + `external_directory` rules — not allows, not
   history, not system prompt.** Parents can tighten, never loosen. Write self-contained
   subagent prompts + their own permission block. `agent/subagent-permissions.ts·14-27` (verified).
6. **Every skill needs a `description`, and that description is the ONLY thing always in
   context.** Write it as a precise "Use ONLY when…" trigger; the body is pay-on-load.
   `skill/index.ts·fmt·322` (verified), `tool/skill.ts·12-67`.
7. **Skill and agent names are last-write-wins across scan roots.** Unique names avoid silent
   shadowing; reuse a name deliberately to override (project `.opencode` beats global/`.claude`,
   and can override the built-in `customize-opencode`). `skill/index.ts·add·125-140,276-283`.
8. **`AGENTS.md` beats `CLAUDE.md` and lands HIGH in the prompt; nested `AGENTS.md` inject
   lazily on read.** Put durable rules in root `AGENTS.md`/`instructions[]`; put subsystem rules
   in nested `AGENTS.md`. `session/instruction.ts·122-133,179-221` (verified),
   assembly `session/prompt.ts·1264-1269`.
9. **`steps` is an advisory stop-PROMPT, not a hard limit.** Use `permission: deny` for real
   boundaries. `session/prompt.ts·1178-1281`, `core/session/runner/max-steps.ts·1-16`.
10. **Only `instructions[]` merges across config files; nearest project config and MDM/env
    override everything else.** Use `instructions[]` to stack files; never assume a project
    scalar survives on an MDM / `OPENCODE_PERMISSION` deploy target.
    `config/config.ts·mergeConfigConcatArrays·45-51`, `loadInstanceState·314-596`.

---

## A. File Loading / Unloading

### A1 — Config precedence (later wins; `mergeDeep`)
`config/config.ts·loadInstanceState·314-596`. Order: (1) remote well-known → (2) global dir
`config.json`→`opencode.json`→`opencode.jsonc` → (3) `OPENCODE_CONFIG` file → (4) project files
cwd→worktree **reversed so nearest wins last** → (5) `.opencode/` dirs + auto-discovered
command/agent/mode/plugin → (6) `OPENCODE_CONFIG_CONTENT` → (7) active-org remote → (8) managed
dir → (9) **macOS MDM (overrides everything)** → (10) `OPENCODE_PERMISSION` env JSON.
**Authoring:** your project files sit above global only for nearest-cwd; MDM/env clobber you.

### A2 — Only `instructions[]` concatenates
`config/config.ts·mergeConfigConcatArrays·45-51` union-dedupes `instructions`; everything else
overwrites. **Authoring:** stack instruction files via `instructions[]`; other arrays replace.

### A3 — Agents load from `.opencode/agent(s)/**/*.md`
`config/agent.ts·load·11-32` (frontmatter→config, body→`prompt`); `loadMode·34-58` forces
`mode:"primary"`. Same-named agent in later dir deep-merges (nearest wins). **Bad agent frontmatter
is silently skipped** — typos hide.

### A4 — Commands load from `{command,commands}/**/*.md`
`config/command.ts·load·13-38`; **a malformed command hard-fails startup** (`InvalidError` 31-36),
unlike agents. **Authoring:** keep command frontmatter strictly valid.

### A5 — Skill scan order + last-write-wins
`skill/index.ts·discoverSkills·173-233`: (1) `~/.claude/skills` then `~/.agents/skills` → (2)
project `.claude`/`.agents` `skills/**/SKILL.md` cwd→worktree → (3) config `.opencode`
`{skill,skills}/**/SKILL.md` → (4) `skills.paths[]` → (5) `skills.urls[]`. Duplicate name →
**last-scanned overwrites** (only a warning, `add·125-140`). Built-in `customize-opencode`
registered before disk so a disk skill of the same name overrides it (276-283).

### A6 — Instruction first-match-wins
`session/instruction.ts·systemPaths·110-153`: global first of `$config/AGENTS.md` then
`~/.claude/CLAUDE.md` (`break`); project iterates `["AGENTS.md","CLAUDE.md","CONTEXT.md"]` and the
**first filename that matches anywhere breaks the loop** (verified 122-133). All ancestor copies of
the matched filename attach; a different filename never loads once one matched.

### A7 — read-tool lazy upward attach (once per message)
`session/instruction.ts·resolve·179-221` walks up from any file the `read` tool opens and attaches
the nearest not-yet-claimed `AGENTS.md`/`CLAUDE.md` as `<system-reminder>` (`read.ts·300,355-356`),
once per assistant message (`s.claims` map). **Authoring:** nested `AGENTS.md` = lazy subsystem
context that fires only on read.

### A8 — Custom tools / plugins / MCP roots
Custom tools: `registry.ts·178-192` scans `{tool,tools}/*.{js,ts}` (`default` export → namespace
name; other exports → `namespace_export`). Plugins: `config/plugin.ts·load·18-30`
`{plugin,plugins}/*.{ts,js}`, deduped by identity. **Authoring:** file basename becomes the tool
namespace; relative plugin specs resolve against the declaring config file.

### A9 — What gets UNLOADED / not-loaded
- **Skill off** → `Permission.evaluate("skill", name, agent.permission)==="deny"` filters it
  (`skill/index.ts·available·310-315`); whole `<available_skills>` vanishes if `skill` is `deny *`
  (`system.ts·99`).
- **Compaction prune** → old completed tool outputs marked `compacted` after 2 turns
  (`compaction.ts·prune·243-287`) — **but `skill` output is prune-protected**
  (`PRUNE_PROTECTED_TOOLS=["skill"]` 31,267).
- **Truncate** → big tool outputs offloaded to disk (machine `truncate`).
- **Dedupe** → same-name skill/agent overwrite.
- `agent.disable:true` removes the agent (`agent.ts·268-270`).
- Env gates: `OPENCODE_DISABLE_PROJECT_CONFIG`, `OPENCODE_DISABLE_EXTERNAL_SKILLS`,
  `OPENCODE_DISABLE_CLAUDE_CODE_SKILLS/PROMPT`.
**Authoring:** durable procedure survives compaction inside a **skill body**; ordinary custom-tool
outputs do NOT — restate critical facts.

### A10 — Frontmatter schemas (author to these)
- **Agent** `core/v1/config/agent.ts·AgentSchema·12-41`: `model, variant, temperature, top_p,
  prompt, tools(deprecated), disable, description, mode(subagent|primary|all), hidden, options,
  color, steps, maxSteps(deprecated), permission`. **Unknown keys silently fold into `options`**
  (`normalize·62-81`) — typos won't warn.
- **Command** `core/v1/config/command.ts·Info·5-12`: `template, description, agent, model, variant,
  subtask` — strict.
- **Skill** `skill/index.ts·isSkillFrontmatter·53-59`: requires `name`; optional `description`.
  **Only skills with `description` are shown** (`fmt·322`, verified).
- **Permission** `core/v1/config/permission.ts·InputObject·17-36`: per-key `allow|ask|deny` or
  `{pattern: action}`. Known keys: `read, edit, glob, grep, list, bash, task, external_directory,
  todowrite, question, webfetch, websearch, lsp, doom_loop, skill` + extras.

---

## B. Limitations & Preferences (precedence)

### B1 — Permission `findLast` positional last-wins (VERIFIED)
`permission/index.ts·evaluate·28-38`: flatten all rulesets, `findLast` wildcard match on
`(permission, pattern)`; **no match → default `{action:"ask"}`**. Config key order preserved
(`fromConfig·186-198`, `propertyOrder:"original"`). Merge = concatenation. **Authoring:** broad
rules first, exceptions last: `{"*":"deny","read":"allow"}`. Always anchor with `"*"`.

### B2 — Per-agent composition
`agent/agent.ts·119-294`: built-in agent = `Permission.merge(defaults, <agent-specific>, user)`;
**`user` (your config `permission`) wins** for built-ins. Custom agents append their own
`permission` last (293) → agent file has the last word for that agent. `defaults` set `"*":"allow"`,
so a locked-down agent must explicitly `{"*":"deny", ...re-allow}` (pattern of `explore` 198-212).

### B3 — Tool visibility per agent
Hidden only if resolved rule is exactly `pattern:"*"` + `action:"deny"` (`disabled·204-214`;
edit/write/patch collapse to `edit`; mcp-resource → `read`). Registry also filters by model
(`registry.ts·286-335`): `websearch` gated; `apply_patch` vs `edit`/`write` by model family;
code-mode `execute` replaces raw MCP when experimental. **Authoring:** deny with bare `"*"` to hide;
`edit/write/patch` share one `edit` key; don't force `apply_patch` on non-gpt models.

### B4 — Skill visibility per agent
`skill/index.ts·available·310-315`: skill shown unless `evaluate("skill", name, agent.permission)`
is `deny`. **Authoring:** gate individual skills per agent via
`permission:{skill:{"secret-skill":"deny"}}`.

---

## C. Handoff & Subagent Mechanics (most important for your goal)

### C1 — Invocation = separate child session
`tool/task.ts·81-345`: `task` asks permission `task`/`subagent_type`, resolves agent, creates a
child session `parentID=ctx.sessionID` (142-158). `task_id` **resumes** an existing child
(121-123). **Authoring:** subagent = separate session; pass `task_id` to continue, omit for fresh;
the returned `task_id` is your continuation handle.

### C2 — Inheritance is narrow (VERIFIED)
`agent/subagent-permissions.ts·14-27`: child inherits from parent **only** `external_directory`
rules and `action==="deny"` rules. System prompt/history NOT inherited (child runs its own agent
prompt on only `params.prompt`, `task.ts·186-200`). Model = `next.model ?? parent model` (167-170).
**Authoring:** parents can only TIGHTEN a child. Give each subagent a self-contained prompt + its
own permission.

### C3 — Default-deny `todowrite`/`task` + `experimental.primary_tools` (VERIFIED)
`task.ts·129-141` + `subagent-permissions.ts·18-25`: unless the subagent's own ruleset lists
`todowrite`/`task`, both are force-denied `"*"`; every `experimental.primary_tools` entry is denied
to subagents. **Authoring:** to build a subagent CHAIN you must explicitly grant `task:"allow"` in
that subagent's permission. Reserve orchestration tools for primaries via `primary_tools`.

### C4 — Return = last text part only (VERIFIED)
`task.ts·runTask·199`: `result.parts.findLast(p=>p.type==="text")?.text ?? ""`, wrapped
`<task id=... state="completed"><task_result>…</task_result></task>` (`renderOutput·64-79`).
**No structured metadata handoff** — purely prompt-conveyed text + `task_id`. **Authoring:** the
subagent's final text IS the whole payload; make it complete and self-sufficient.

### C5 — Parent resume
Foreground: `task.ts·303-333` blocks on completion, returns rendered result as the tool output; the
parent loop continues with it in context. Background (`experimentalBackgroundSubagents`): returns a
"running" notice, later `inject·202-229` posts a synthetic message re-prompting the parent.
**Authoring:** a background subagent's summary must stand alone as an interruption the parent can act
on; don't design the model to poll (mirror `BACKGROUND_STARTED` discipline).

---

## D. Turn / Process Mechanics

### D1 — System-prompt section order
`session/prompt.ts·1257-1269`: **env → instructions → mcp → skills**. Provider base prompt
prepended separately (`system.ts·provider·27-42`, by model id). **Authoring:** `AGENTS.md`/
`instructions` land HIGH (right after env); skills land LAST. For maximum attention, prefer
instructions over a skill body.

### D2 — Sections that appear/disappear
`skills` section drops entirely if `skill` is `deny*` or no described skills
(`system.ts·98-110`); `mcp` section drops if all MCP tools disabled for the agent (112-128).
**Authoring:** denying `skill`/MCP silently slims the prompt (good for a focused agent). If skills
"aren't showing," check the agent's `skill` permission and that each skill has a `description`.

### D3 — Step budget is advisory
`prompt.ts·1178-1281`: `maxSteps = agent.steps ?? Infinity`; last step appends `MAX_STEPS_PROMPT`
(text: "tools disabled, respond text only", `max-steps.ts·1-16`). Not a real tool lock.
**Authoring:** use `steps` to bound cost; use `permission: deny` for hard boundaries.

### D4 — Skills: verbose index vs pay-on-load body
System prompt lists `<available_skills>` name/description/location (`skill/index.ts·fmt·321-346`,
verified). Full body loads only when the `skill` tool is called (`tool/skill.ts·12-67`), emitting
`<skill_content>` + body + a **sampled** file list (ripgrep limit ~10, 36-43,54). **Authoring:** only
name+description are always in context — make the description a precise trigger; reference key files
explicitly in the body since the file list is sampled.

### D5 — Skill output prune-protected
`compaction.ts·31,267`. **Authoring:** loaded skill content persists through compaction — safe home
for procedure the agent must retain across a long session.

---

## How This Maps To YOUR Kit Primitives

| You author… | Governing mechanic | Do this |
|---|---|---|
| **Agent / subagent** | narrow inheritance (C2), default-deny task/todo (C3), last-text return (C4) | self-contained prompt + own permission; grant `task`/`todowrite` if it must chain; end with a complete summary |
| **Skill** | description-gated visibility (A10/D4), last-write-wins name (A5/A7), prune-protected (D5) | precise "Use ONLY when…" description; unique or deliberately-overriding name; put durable procedure in body |
| **Capability** | loaded like a skill body / referenced doc | keep canonical procedure; adapters reference it |
| **Instruction / rule** | AGENTS.md > CLAUDE.md first-match (A6), instructions[] stacks (A2), high prompt slot (D1) | root `AGENTS.md` for durable rules; nested `AGENTS.md` for lazy subsystem rules; `instructions[]` to stack extra files |
| **Command** | strict schema, hard-fails startup (A4) | keep frontmatter strictly valid |
| **Tool / custom tool** | namespace = file basename (A8), model-family filtering (B3), visibility via bare `"*"` deny (B2) | drop in `.opencode/tool/`; hide via `{"tool":"deny"}` |
| **Policy / permission** | findLast last-wins, default ask (B1), per-agent compose user-wins (B2) | anchor `"*"`, exceptions last, deterministic ordering |
| **Reference** | env block exposes `<available_references>` (system.ts env) | keep referenceable paths + descriptions accurate |
| **Priority / precedence** | config later-wins, nearest project wins, MDM/env clobber (A1) | never rely on a project scalar on managed deploy targets |

---

## Provenance / Confidence

- Full authoring pass by `repository-researcher` over `/home/utmostcreator/Projects/opencode`.
- 5 highest-leverage claims re-verified live this session (see Purpose block).
- Line numbers read against the live tree at `9976269`; existence high-confidence, exact lines
  medium (tree may drift from pinned commit). Re-check at authoring time.
- No upstream files modified (read-only research).
