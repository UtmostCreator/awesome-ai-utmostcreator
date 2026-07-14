# Architecture Plan — Complete the OpenCode Lifecycle Model

- Status: Todo
- Type: architecture plan (documentation / model extension)
- Owner: utmostcreator
- Created: 2026-07-10
- Target model: `___ARCHITECTURE_2.0/opencode_architecture/lifecycle-model/lifecycle/model/opencode.lifecycle.yaml`
- Upstream verified against: `sst/opencode` @ `dev`, commit `9976269ab1accfc9f9dc98a4a688c516934de422`
  (local clone `/home/utmostcreator/Projects/opencode`)

## Completeness Ranking (as requested)

**Current diagram completeness: 60 / 100.**

Reconciled from two independent parallel research passes:

- `researcher` scored **62/100**
- `repository-researcher` scored **58/100**

Both converge on the same conclusion: the 16 existing machines model the **cognitive /
per-turn request-processing spine** with exceptional depth and real provenance
(startup → config precedence → discovery → skill/instruction/plugin loading → routing →
context + system-prompt assembly → agent loop → subagent isolation → compaction/prune →
truncate → permission). That is the hardest half and it is modeled well.

What is systematically absent is the **stateful infrastructure + I/O half** the runtime
runs on. Roughly 16 of an estimated ~35–37 real top-level lifecycle blocks are modeled
(≈43% by raw count), but the modeled ones carry the highest traffic, so centrality-weighted
practical coverage lands at ~60%.

### Score rationale table

| Dimension | Coverage | Note |
|---|---|---|
| Request/turn cognitive path | ~95% | startup, config, discovery, context, prompt, loop, permission all modeled |
| Session/state substrate | ~5% | no session entity, run-state, storage, snapshot, revert, summary, share |
| Provider/model + auth bootstrap | ~5% | "5. Active agent / model" is a black box; no provider/key resolution |
| Connection/transport layer | 0% | no MCP connection statechart, no event bus / SSE, no server bootstrap |
| Cross-cutting failure control | ~10% | retry modeled inside loop; no abort/cancellation, no error taxonomy |
| Support services | 0% | no LSP, no formatter, no shell-exec, no tool registry |

## Scope

Add **new top-level lifecycle machines** (breadth) to the single-source-of-truth YAML.
**Do NOT** deepen any of the 16 existing machines — the task is explicitly to find and add
**missing blocks**, not more detail inside existing ones.

In scope:

- Append new `machines:` entries (each with `provenance:` file/symbol/lines, `nodes`, `edges`).
- Add `cross_links:` wiring the new machines to existing ones.
- Regenerate diagrams via the model's own generator (`scripts/gen_mermaid.py`).

Out of scope:

- Editing internals of the 16 existing machines.
- Any change to upstream OpenCode source (read-only research only).
- Implementation code in this repo.

## Confirmed Missing Machines (evidence-first, upstream-cited)

Provenance re-verified live: `session/session.ts`, `storage/storage.ts`, `provider/provider.ts`,
`mcp/index.ts`, `snapshot/index.ts`, `session/run-state.ts`, `bus/global.ts`, `tool/registry.ts`
all exist; `session/session.ts` `Interface` at line 415 with `create`/`fork`/`setArchived`/`remove`
confirmed.

### Tier 1 — Highest priority (the loop has no host / the model node is a black box)

| # | Proposed machine id | Kind | Why it is a distinct missing stage | Provenance (file · symbol · lines) |
|---|---|---|---|---|
| M1 | `session_lifecycle` | statechart | agent_loop is a *turn*; there is no machine for the session *entity* (create→fork→archive→remove) | `session/session.ts` · `Interface` create/fork/setArchived/remove · **415–476**, `create` **669**, `fork` **693**, `remove` **608** |
| M2 | `provider_resolution` | pipeline | node "5. Active agent / model" assumes a resolved model; load providers→build catalog→pick model is unmodeled | `provider/provider.ts` · `getModel/getSmallModel/list` · **1146–1168**, `closest` **1849**, `defaultModel` **1930**, errors **1092–1131** |
| M3 | `auth_key_resolution` | precedence | provider key precedence (env→apiKey→oauth→config) + OAuth refresh has its own ordered policy | `provider/provider.ts` · **612,745,871–899**; `provider/auth.ts` · **90–105,211–216**; `auth/index.ts` · **14–35,58–73** |
| M4 | `storage_persistence` | pipeline | every artifact persisted via keyed JSON store + versioned migrations; nothing models durability | `storage/storage.ts` · `Interface` remove/read/update/write/list · **53–61**, `parseMigration` **76**, `file()` key→path **63** |

### Tier 2 — Connection / transport / interrupt (nothing delivers output; no cancel path)

| # | Proposed machine id | Kind | Why distinct | Provenance |
|---|---|---|---|---|
| M5 | `mcp_connection` | statechart | discovery inventories MCP servers; the connect→list→call→auth→disconnect→fail statechart is unmodeled | `mcp/index.ts` · `Interface` connect/disconnect/status · **164–200**, transports **218–384**, `MCPFailed` **65** |
| M6 | `event_bus_sse` | dataflow | entire runtime is event-driven; GlobalBus→bridge→per-instance SSE stream surfaces all state | `bus/global.ts` · `GlobalBusEmitter.emit` · **11–22**; `server/routes/instance/httpapi/handlers/event.ts` · **25–40,70–78** |
| M7 | `abort_cancellation` | statechart | AbortSignal woven through prompt→llm→tools + run-state busy guard; loop shows only happy path | `session/run-state.ts` · `assertNotBusy/cancel` · **11–27,71–88**; `session/prompt.ts` · **815–827**; `session/llm.ts` abort · **51,362–366** |
| M8 | `snapshot_git` | statechart | git-shadow checkpoint engine (init→track→patch→restore/revert→prune) underpins file undo | `snapshot/index.ts` · `Interface` init/track/restore/revert · **36–45**, `track` **318–347**, `revert` **408–520**, gc prune 7d **300–318** |

### Tier 3 — Support services & secondary session ops (lower priority, still real blocks)

| # | Proposed machine id | Kind | Provenance |
|---|---|---|---|
| M9 | `tool_registry` | pipeline | `tool/registry.ts` · `Interface` tools/all/ids/named · **72–84**, per-agent filter **287–303**, `fromPlugin` **120** |
| M10 | `session_run_state` | decision | `session/run-state.ts` · `assertNotBusy`→`SessionBusyError` · **11–27,146** (may fold into M7) |
| M11 | `session_revert` + `session_summary` | pipeline | `session/revert.ts` · **20–26**; `session/summary.ts` · `summarize` **66–72,102–129** |
| M12 | `share_lifecycle` | statechart | `share/session.ts` · **9–15**; `share/share-next.ts` · `sync` **124**, `api` **85** |
| M13 | `shell_execution` | statechart | `tool/shell.ts` · commands/expand/preview/tail · **123–293,392** |
| M14 | `server_bootstrap` | statechart | `server/server.ts` · `HttpRouter.serve` **101**, dispose **11**; `server/mdns.ts` publish **6,36** |
| M15 | `cli_run_modes` | decision→pipeline | `cli/cmd/{serve,tui,run,acp}.ts`; `cli/cmd/run/runtime.queue.ts` · **32–186** |
| M16 | `lsp_lifecycle` + `format_lifecycle` | statechart / pipeline | `lsp/lsp.ts` · **119–136**; `format/index.ts` · **21–27**, formatter registry **18–396** |
| M17 | `error_taxonomy` | inventory | ~85 `NamedError.create`/`TaggedErrorClass` across ~15 modules; render as annotation, not flow |

## Already Covered — Do NOT Duplicate

- Compaction/prune/overflow → machine `compaction_prune`.
- Truncation → machine `truncate`.
- Retry/backoff → inside `agent_loop`.
- Plugin load/init/dispose → `plugin_lifecycle` (but plugin-contributed **tool** registration is the M9 gap).
- Subagent spawn → `subagent_isolation` (M-background-jobs is the primitive it rides; fold, don't duplicate).
- Message history → partially in `context_assembly.HIST`; message *streaming/persistence* belongs to M1/M6.
- Question/ask interactive tail → part of `permission_engine`; a minor extension, not a new stage.
- Todo/task-state, cost/usage accounting → data side-channels of the loop, not stages.

## Recommended Implementation Order

1. **M2 provider_resolution** — unblocks the model's biggest black box (the "model" node).
2. **M1 session_lifecycle** — gives the agent loop a host entity.
3. **M3 auth_key_resolution** — precedence machine mirroring existing `config_precedence` style.
4. **M4 storage_persistence** — durability substrate.
5. **M5 mcp_connection** → **M6 event_bus_sse** → **M7 abort_cancellation** → **M8 snapshot_git**.
6. Tier 3 (M9–M17) as capacity allows; consider folding M10 into M7 and pairing M16 as support-services.

## Things To Avoid

- Do NOT hand-edit generated `.mmd` diagrams — they are build artifacts (`gen_mermaid.py`).
- Do NOT add detail nodes inside the 16 existing machines; this plan is breadth-only.
- Do NOT invent nodes without `provenance:` file/symbol/lines — every node must cite upstream code.
- Do NOT assume line numbers are stable: the clone tree may drift from pinned commit `9976269…`;
  re-verify symbol lines at authoring time (existence high-confidence, exact lines medium).
- Watch scope: this roughly doubles machine count. Confirm with the model owner before adding all
  tiers in one pass; prefer landing Tier 1 first as a bounded slice.
- Resolve two scoping judgments before authoring: (a) does M8 `server_bootstrap` / `cli_run_modes`
  belong here or as a branch on existing `startup`? (b) is `event_bus_sse` a runtime-lifecycle
  concern or a separate transport model?

## Acceptance Criteria

- [x] Each new machine added to `opencode.lifecycle.yaml` has a `provenance:` block with real
      upstream `file` + `symbol` + `lines`, re-verified against the clone.
- [x] New `cross_links:` connect each new machine to the existing spine
      (e.g. `provider_resolution → agent_loop.AG`, `session_lifecycle → agent_loop`;
      `event_bus_sse ← agent_loop.FINAL` landed with Tier 2 — see "Tier 2 Implementation Note").
- [x] No internal node added to any of the 16 original pre-existing machines (Tier 1 and Tier 2
      additions are new top-level machines only).
- [x] `scripts/gen_mermaid.py --check` passes; diagrams regenerated (not hand-edited).
- [x] `meta.upstream.verified_at` bumped to `2026-07-11` after the Tier 2 re-verification pass
      (commit unchanged: `9976269...`, no drift).
- [x] Completeness re-ranked after Tier 1 lands (see "Tier 1 Implementation Note" below;
      landed at ~72/100, short of the ≥80 aspirational target because Tier 2's transport/
      connection dimension is still 0% — see note for why).
- [x] Completeness re-ranked again after Tier 2 lands (see "Tier 2 Implementation Note" below;
      landed at ~84/100, now past the ≥80 aspirational target).

## Tier 1 Implementation Note (2026-07-10)

Landed M2 `provider_resolution`, M1 `session_lifecycle`, M3 `auth_key_resolution`,
M4 `storage_persistence` — in that order, per "Recommended Implementation Order" above.

- Machines: 16 → 20. Cross-links: 35 → 40 (5 new: `provider_resolution.PR_RESOLVED →
  agent_loop.AG`, `session_lifecycle.SL_ACTIVE → agent_loop.OVERFLOW`,
  `subagent_isolation.SA_SPAWN → session_lifecycle.SL_CREATE`,
  `auth_key_resolution.AK5 → provider_resolution.PR_LIST`,
  `storage_persistence.ST_DONE → session_lifecycle.SL_ACTIVE`).
- Provenance re-verified live this session against the pinned clone
  (`/home/utmostcreator/Projects/opencode` @ `9976269...`): `session/session.ts` Interface
  415-474, `create` 669-691, `fork` 693-734, `remove` 608-629, `setArchived` 759-761;
  `provider/provider.ts` Interface 1146-1157, errors 1092-1141, `list` 1654, `getModel` 1794,
  `closest` 1849, `getSmallModel` 1861, `defaultModel` 1930; `auth/index.ts` Info union 14-35,
  `all()` env-content override 58-67; `provider/auth.ts` `authorize`/`callback` 163-221;
  `provider/provider.ts` per-provider precedence examples (gitlab 611-613, snowflake-cortex
  860-899); `storage/storage.ts` Interface 53-59, `file()` 63-65, `parseMigration` 76,
  `MIGRATIONS[]` 81-211, migration-marker read + sequential apply 224-241, per-key
  `RcMap`/`TxReentrantLock` 218-221.
- Verification: `nix-shell -p "python3.withPackages(ps: [ps.pyyaml ps.jsonschema])" --run
  "python3 gen_mermaid.py --check"` → `model ok: 20 machines, 40 cross-links`. Diagrams
  regenerated (not hand-edited) via the same tool without `--check`; `provider_resolution.mmd`
  spot-checked for correct Mermaid output.
- Re-ranked completeness: **~72/100** (up from 60/100), not the ≥80 aspirational target,
  because Tier 1 only closes the *session/provider/auth/storage* substrate gap. The
  "Connection/transport layer" dimension (M5 `mcp_connection`, M6 `event_bus_sse`) is
  still 0% and "Cross-cutting failure control" (M7 `abort_cancellation`) is still ~10% —
  both remain fully open as Tier 2. Session/state substrate moved from ~5% to an estimated
  ~50-55% (CRUD/fork/archive/remove modeled; run-state busy-guard, share/summary/revert are
  Tier 2/3 gaps). Provider/model + auth bootstrap moved from ~5% to an estimated ~65-70%
  (catalog/lookup/fuzzy-match/defaults + per-provider key precedence modeled; OAuth
  device-flow UI and remote/well-known config tie-in to `config_precedence.C7` are not).
- Tier 2 (M5-M8) and Tier 3 (M9-M17) remain open follow-up work, out of scope for this slice.

### Post-Review Fix (2026-07-11)

A `reviewer` handoff pass found Tier 1 content-clean (PASS, no blocking findings) but flagged two
provenance citations in `auth_key_resolution` as off by 1-2 lines from the exact upstream symbol
boundary: `auth/index.ts all()` cited as `58-66` (actual close `67`) and `provider/auth.ts`
`authorize`/`callback` cited as `163-219` (actual `callback` close `221`). Both corrected in the
yaml (`provenance:` block + the `AK2` item note) and mirrored above. Re-verified live against the
pinned clone (`awk '{print NR": "$0}' ... | sed -n` on both files) before editing. Re-ran
`gen_mermaid.py --check` → `model ok: 20 machines, 40 cross-links` (unchanged), then regenerated
all 21 `.mmd` files (including `combined.mmd`) via the same tool without `--check` since the
corrected provenance text is rendered into `auth_key_resolution.mmd`'s comment header. No other
reviewer finding required a fix (the remaining review notes were informational, not defects).

## Tier 2 Implementation Note (2026-07-11)

Landed M5 `mcp_connection`, M6 `event_bus_sse`, M7 `abort_cancellation`, M8 `snapshot_git` — in
that order, per "Recommended Implementation Order" above. Resolves the plan's open scoping
judgment (b): `event_bus_sse` is modeled here as a runtime-lifecycle concern (turn/event output
delivery), not spun out as a separate transport model, since it directly answers "how does a
turn's output actually reach the caller" for the existing spine (`agent_loop.FINAL`,
`permission_engine.PENDING`). Scoping judgment (a) (`server_bootstrap`/`cli_run_modes`) remains
open and out of scope for this slice — it is Tier 3 (M14/M15), not part of Tier 2.

- Machines: 20 → 24. Cross-links: 40 → 49 (9 new): `discovery.MCP → mcp_connection.MC_CONFIG`,
  `mcp_connection.MC_CALL → truncate.TR_CAP`, `agent_loop.REQUEST → mcp_connection.MC_CALL`,
  `agent_loop.FINAL → event_bus_sse.EB_EMIT`, `permission_engine.PENDING → event_bus_sse.EB_EMIT`,
  `session_lifecycle.SL_ACTIVE → abort_cancellation.AC_BUSY`,
  `abort_cancellation.AC_CASCADE → subagent_isolation.SA_SPAWN`,
  `session_lifecycle.SL_ACTIVE → snapshot_git.SG_TRACK`,
  `permission_engine.EXEC → snapshot_git.SG_TRACK`.
- Provenance re-verified live this session against the pinned clone
  (`/home/utmostcreator/Projects/opencode` @ `9976269...`, confirmed via `git rev-parse HEAD`, no
  drift): `mcp/index.ts` Interface 164-198, `status`/`connect`/`disconnect` 591-659,
  `connectTransport`/`connectRemote` 218-370, `startAuth`/`authenticate`/`finishAuth` 806-942,
  `Failed` (MCPFailed) 65-67; `bus/global.ts` `GlobalBusEmitter.emit` 11-20 (whole file, 22
  lines); `server/routes/instance/httpapi/handlers/event.ts` `eventResponse` 25-87, disposed
  listener + heartbeat 42-66; `session/run-state.ts` Interface 11-25, `assertNotBusy`/`cancel`
  71-86, `cancelBackgroundJobs` 111-143; `session/prompt.ts` AbortController on the read-tool
  example 815-827; `session/llm.ts` `StreamRequest.abort` 50-52, scoped `AbortController`
  acquire/release 357-368; `snapshot/index.ts` Interface 36-45, `track` 318-347, `patch` 349-380,
  `restore` 382-406, `revert` 408-522, `cleanup` (gc/prune) 300-316, `prune` constant line 23.
  Every citation resolved to the exact symbol at the exact line — no drift found from the
  pinned commit.
- Verification: `nix-shell -p "python3.withPackages(ps: [ps.pyyaml ps.jsonschema])" --run
  "python3 gen_mermaid.py --check"` → `model ok: 24 machines, 49 cross-links`. Diagrams
  regenerated (not hand-edited) via the same tool without `--check` (25 `.mmd` files written,
  including `combined.mmd`); `mcp_connection.mmd` spot-checked for correct Mermaid output
  (valid `flowchart TD`, correctly HTML-escaped `-&gt;` in note text, all 9 nodes/edges present).
- `git status --short` reconfirmed the diff stays scoped to `___ARCHITECTURE_2.0/` and this
  ticket folder; the pre-existing 66 unrelated dirty tracked files in the repo were left
  untouched.
- Re-ranked completeness: **~84/100** (up from ~72/100), now past the ≥80 aspirational target.
  "Connection/transport layer" moved from 0% to an estimated ~70-75% (MCP connect/auth/call/
  disconnect statechart and the GlobalBus→SSE delivery path are now modeled; MCP resource/prompt
  subscription edge cases and multi-instance event fan-out are not). "Cross-cutting failure
  control" moved from ~10% to an estimated ~55-60% (busy-guard, AbortSignal plumbing, and
  cascade-cancel of background/subagent jobs are modeled; a dedicated error-taxonomy view
  (M17) and a unified retry-vs-abort precedence diagram are still open, Tier 3). Session/state
  substrate gains a checkpoint dimension via `snapshot_git` (git-shadow init/track/patch/
  restore/revert/gc), though `session_revert`/`session_summary` (M11) and `share_lifecycle`
  (M12) remain Tier 3 gaps.
- Tier 3 (M9-M17) remains open follow-up work, out of scope for this slice. Given completeness
  is now past the plan's own ≥80 target, Tier 3 should be re-scoped as its own explicitly
  requested slice rather than assumed as an automatic continuation.

## Verification

- `python ___ARCHITECTURE_2.0/opencode_architecture/lifecycle-model/lifecycle/model/scripts/gen_mermaid.py --check`
  (path per the model header, lines 3–4; confirm generator location at authoring time).
- Manual provenance re-check: for each new machine, `Read`/`preview-file.sh` the cited upstream
  file at the cited lines and confirm the symbol still resolves.

## Provenance of This Plan

- Two parallel read-only research passes (`researcher`, `repository-researcher`) over
  `/home/utmostcreator/Projects/opencode`.
- Live re-verification in this session: 8 cited upstream files confirmed present;
  `session/session.ts` `Interface` (create/fork/setArchived/remove) confirmed at lines 415–476.
- No files in the upstream clone were modified (read-only research).
