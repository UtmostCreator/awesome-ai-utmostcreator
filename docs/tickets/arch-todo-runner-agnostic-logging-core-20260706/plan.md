# Architecture Plan — Runner-Agnostic AI Event Logging Core

- Ticket: none (self-directed refactor)
- Source: architect design handoff (runner-agnostic logging core)
- Generated: 2026-07-06
- Branch: main
- Plan file: docs/tickets/arch-todo-runner-agnostic-logging-core-20260706/plan.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan.md` and move it into `archive/` under this ticket folder (`docs/tickets/arch-todo-runner-agnostic-logging-core-20260706/archive/DONE-plan.md`). See "Archive On Completion" below for the exact steps. Note: this ticket ships **Slice 1 only as fully specified and ready to implement**; Slices 2-5 are ordered scope stubs and must be expanded into their own detail (or their own plans) before implementation — do not archive on Slice 1 completion alone unless the ticket is deliberately scoped to Slice 1.

## Context

The repository's AI event logging currently treats specific runners (Claude Code /
OpenCode / Copilot / generic-shell) as concepts baked into the core emitter path.
The logging libraries live under `scripts/ai/internal/lib/**` and are shipped
**verbatim** as repo-local sources (not templated): `packs.php` ships the
`scripts/ai/internal` tree plus `common.sh` with `merge_strategy: replace`, and the
schema ships `skip-if-exists`. Editing happens directly on the real files —
`scripts/ai/internal/lib/**`, `tools/ai/**`, `schemas/ai/**` — with no parallel
template copies, and rendered `.opencode/`, `.github/`, `CLAUDE.md`/`AGENTS.md`
adapters stay untouched.

Current logging maturity is assessed at 45/100; the target for this refactor is
88-93/100.

## Problem

Runner identity is entangled with the core emitter instead of being an **input
adapter** concern. The core (`30-logging.sh`) should never branch on which runner
produced an event. Instead, all runners should share **one additively-versioned
event schema**, and the core should reuse the existing redaction, token, exec-guard
watchdog, and session machinery rather than duplicating it. Additional concrete
defects to fix along the way:

- The durable sink `tools/ai/agent-log.php` is referenced at `30-logging.sh:56` but
  **does not exist** (the call is already guarded by an `-f` existence check).
- Standalone-script events carry `"unknown"` trace/session ~79% of the time because
  IDs are not lazily seeded in the emitter.
- `post-tool-use.sh` emits `event_version "1.1"` inline via its own builder — a real
  divergence from the shared emitter path.

## Verified Evidence

Evidence gathered by direct file inspection at plan time:

- **Core emitter + missing sink**: `scripts/ai/internal/lib/30-logging.sh:56` calls
  `php "$repo_root/tools/ai/agent-log.php" ...` guarded by `-f` (line 56); no
  `tools/ai/agent-log.php` file exists on disk. Confirms "BUILD durable sink".
- **Core header contract forbids scope creep**: `30-logging.sh:6-8` explicitly states
  "No session-ID generation, approval policy, or command execution." Confirms the
  non-goal against moving that machinery into `30-logging.sh`.
- **Status-derivation lives in core**: `event_execution_status()`
  (`30-logging.sh:18-26`) maps event-type suffix to `success|error|timeout|unknown`;
  the plan adds a `payload._status` override *before* this, additively.
- **Reuse targets exist**: `redact_json_payload()` (`10-json.sh:24`),
  `estimate_tokens_string()` (`80-tokens.sh:12`), `agent_session_init()`
  (`40-session.sh:12`) all confirmed present.
- **Exec-guard actual path (fidelity correction)**: `run_guarded()` lives at
  `scripts/ai/internal/lib/exec-guard/40-run-guarded.sh:26` — **not** a flat
  `scripts/ai/internal/lib/40-run-guarded.sh`. The design's degrade-pattern citation
  is real (`exec-guard/40-run-guarded.sh:65` warns and falls back to a plain wrapper
  when no writable temp dir exists), which is the mirror pattern for the flock
  degrade. See Risks Or Unknowns for the file-path reconciliation this implies for
  Slice 3.
- **Schema shape**: `schemas/ai/evidence-event.schema.json` has `event_version` as
  `{ "type": "string", "minLength": 1 }` (lines 19-22) and `event_version` in
  `required[]` (line 7); root `additionalProperties: false` at line 285. Confirms the
  schema does **not** gate the value `"3.0"` — only the test asserts it — and that new
  top-level sections must be added to the root allowed keys.

## Design Decisions

1. **Runner is an input adapter, not a core concept.** Core reads `AI_RUNNER_NAME` /
   `AI_RUNNER_VENDOR` / `AI_RUNNER_ADAPTER_VERSION` from env with `"shell"`/`null`
   defaults; adapters *write* those env vars. Core **never** branches on runner.
   Boundary: core reads env, adapters write env.
2. **Additive schema versioning.** Bump `event_version` to `"3.0"` and **add** new
   flat top-level sections to the existing schema (do **not** re-nest v2 fields). New
   sections: `ids{event_id,span_id,parent_span_id,sequence}`,
   `runner{name,vendor,adapter_version}`, `context{hashes[],preview}`,
   `patch{artifact_path,sha256,files_changed}`. Each gets its own
   `additionalProperties:false`; each is added to root allowed keys; **none** are
   added to `required` so old-shape events still validate.
3. **Reuse existing machinery, no duplication.** Redaction reuses
   `redact_json_payload()`/`redact_sensitive_text()`; tokens reuse
   `estimate_tokens_string()`/`estimate_tokens()`; session/trace IDs reuse and
   lazy-seed `agent_session_init()`; span timing reuses exec-guard timing. No new
   redaction regex, token, ID, or span machinery.
4. **Extend, do not fork, the command wrapper.** Span IDs are threaded into
   `run_guarded()`'s existing `guard.start`/`guard.done`/`guard.killed` emits (which
   already carry `label`+`elapsed_s`+`exit_code`); no parallel wrapper.
5. **Follow the existing numeric lib split.** `30-logging.sh` stays a thin
   emitter+append+rotation. New thin adapters: `31-log-redaction.sh` (wires
   `redact_json_payload` into `log_redact_payload()`, loads after `10-json`),
   `32-log-spans.sh` (`event_id`/`span_id`/`sequence` + span helpers),
   `33-log-tools.sh` (tool-call/context/patch payload builders, later). Every new lib
   gets a `scripts/ai/MANIFEST.md` entry in the same slice.
6. **One emit code path.** The (later) `ai-log emit` CLI is the single boundary
   adapters shell out to; it sets `AI_RUNNER_*` env then calls the same emitter path
   as `log_json` — no divergent builder.
7. **No external UUID dependency.** `event_id`/`sequence` reuse the
   `$RANDOM`/`date`/`$$` composition style already used for `SESSION_ID`
   (`40-session.sh:17`); `sequence` is a process-scoped incrementing counter var.

## Non-Goals (Things To Avoid)

- Do **not** build the SQLite/durable sink, the `ai-log` CLI, or `tools/ai/adapters/**`
  before the core + schema land (deferred to Slice 5).
- Do **not** change the signature of `log_json(event, payload, caller)` or affect its
  15+ callers — additive changes only.
- Do **not** log inline prompts, diffs, or command output; store hashes + redacted
  previews only. Diffs/patches go to artifact files referenced by path + hash.
- Do **not** hand-edit rendered adapters (`.opencode/**`, `.github/**`, `AGENTS.md`,
  `CLAUDE.md`).
- Do **not** add new redaction/token/ID/span machinery that duplicates
  `10-json` / `80-tokens` / `40-session` / exec-guard.
- Do **not** move session-ID generation, approval policy, or command execution into
  `30-logging.sh` (header contract `30-logging.sh:6-8` forbids it).
- Do **not** reconcile `post-tool-use.sh` in Slice 1 (defer to Slice 4).
- Do **not** re-nest existing v2 schema fields when adding v3 sections.
- Do **not** create parallel template copies of the logging scripts — edit the real
  verbatim-shipped files directly.

## Affected Paths

Slice 1 (fully specified) touches at most 6 files:

- `scripts/ai/internal/lib/30-logging.sh` (edit)
- `scripts/ai/internal/lib/31-log-redaction.sh` (new)
- `schemas/ai/evidence-event.schema.json` (edit — additive sections)
- `scripts/ai/MANIFEST.md` (edit — add `31-log-redaction.sh` entry)
- `tests/scripts/ai/test-common.sh` (edit — version bump + new assertions)
- `tests/shell/logging-core.bats` (new, or extend an existing bats file)

Later-slice paths (not in scope until their slice): `40-session.sh` (Slice 2),
`scripts/ai/internal/lib/32-log-spans.sh` + `exec-guard/40-run-guarded.sh` (Slice 3),
`scripts/ai/internal/**/post-tool-use.sh` (Slice 4), `tools/ai/agent-log.php`,
`tools/ai/adapters/**`, `scripts/ai/internal/lib/33-log-tools.sh`, `ai-log` CLI
(Slice 5).

## Contracts And Boundaries

- **`log_json(event, payload, caller)` signature is frozen.** All additions are
  internal/additive; 15+ callers stay untouched.
- **`payload._status` override contract:** an explicit `_status` in the payload wins
  over the suffix-derived `event_execution_status()` result.
- **Runner env boundary:** core reads `AI_RUNNER_NAME` (`claude-code|opencode|copilot|
  shell`), `AI_RUNNER_VENDOR` (`anthropic|sst|github|null`),
  `AI_RUNNER_ADAPTER_VERSION`, defaulting to `"shell"`/`null`; adapters set them.
- **Schema additivity:** new sections are optional (not in `required`); old-shape and
  new-shape events both validate. `event_version` value is gated only by the test, not
  the schema.
- **Test-coupled invariants (must change in the same slice as the code):**
  - `test-common.sh` asserts `event_version == "2.0"` (design cites line 465) — update
    to `"3.0"` in the same slice as the version bump.
  - `ScriptsAiManifestTest.php` (design cites lines 44-62) requires every
    `internal/lib/*.sh` to appear in `scripts/ai/MANIFEST.md` — add the
    `31-log-redaction.sh` entry in the same slice as the new lib file.
  - Schema root `additionalProperties: false` (line 285) — new sections must be added
    to root allowed keys.

## Todo Plan

Use unchecked Markdown tasks only, grouped by priority. Slice 1 is fully specified;
Slices 2-5 are ordered scope stubs to be expanded before implementation.

### Slice 1 — Core emitter hardening (LOW risk, <=6 files) — fully specified

- [ ] P0: In `30-logging.sh`, wrap `append_log_entry` (and log rotation) in an
  `flock`-guarded atomic append + locked rotation. Feature-detect `flock`; when
  absent, fall back to plain append and `log_warn` (mirror the exec-guard degrade
  pattern at `exec-guard/40-run-guarded.sh:65`).
- [ ] P0: In `30-logging.sh`, cache per-process git metadata (`git_root`, branch,
  commit) as compute-once, process-scoped vars instead of recomputing per event.
- [ ] P0: In `30-logging.sh`, add `ids.event_id` and `ids.sequence` plus a top-level
  `severity` field to emitted events. `event_id` reuses the `$RANDOM`/`date`/`$$`
  composition style from `SESSION_ID` (`40-session.sh:17`); `sequence` is a
  process-scoped incrementing counter var. No external UUID dependency.
- [ ] P0: In `30-logging.sh`, read a `payload._status` override *before* calling
  `event_execution_status()` so an explicit `_status` in the payload wins over the
  suffix-derived status.
- [ ] P0: In `30-logging.sh`, call a new `log_redact_payload()` on the emitted payload
  (wire the redaction hook) and bump `event_version` to `"3.0"`.
- [ ] P0: Create `scripts/ai/internal/lib/31-log-redaction.sh` — a thin
  `log_redact_payload()` that delegates to `redact_json_payload()` (`10-json.sh:24`).
  No new redaction regex. Ensure it loads after `10-json.sh` and before `30-logging`
  consumes it.
- [ ] P0: In `schemas/ai/evidence-event.schema.json`, add an `ids` object
  (`event_id`, `sequence` int, `span_id`/`parent_span_id` nullable) and a top-level
  `severity` enum (`debug|info|warn|error|null`); add both to the root allowed keys;
  add neither to `required[]`. Keep each new object `additionalProperties:false`.
- [ ] P0: In `scripts/ai/MANIFEST.md`, add the `31-log-redaction.sh` entry (same slice
  as the new lib, to satisfy `ScriptsAiManifestTest`).
- [ ] P0: In `tests/scripts/ai/test-common.sh`, update the version assertion from
  `"2.0"` to `"3.0"`; add tests for `event_id` presence + uniqueness, `sequence`
  monotonicity per process, `_status` override precedence, and redaction-applied.
- [ ] P0: Add `tests/shell/logging-core.bats` (or extend an existing bats file) with
  regression coverage for `flock` atomic append and locked rotation.
- [ ] P0: Verify Slice 1: run `bash tests/scripts/ai/test-common.sh` first, then
  `composer test` (use `composer test:fast` for the parallel first pass).

### Slice 2 — Lazy session/trace auto-seed + runner section (LOW-MED risk)

- [ ] P1: (Expand before implementing) Lazily auto-seed session/trace IDs in the
  emitter to fix the ~79% `"unknown"` rate, reusing `agent_session_init()`
  (`40-session.sh:12`) — no new ID logic.
- [ ] P1: (Expand before implementing) Add the `runner{name,vendor,adapter_version}`
  schema section populated from `AI_RUNNER_*` env with `"shell"`/`null` defaults; core
  must not branch on runner.

### Slice 3 — Span integration into run_guarded (MED risk)

- [ ] P1: (Expand before implementing) Thread `span_id`/`parent_span_id`/latency into
  the existing `run_guarded()` emits at `exec-guard/40-run-guarded.sh:26` (note the
  actual nested path); reuse exec-guard timing; add `32-log-spans.sh` (+ MANIFEST
  entry). No duplicate wrapper.

### Slice 4 — Reconcile post-tool-use.sh (MED risk, needs reviewer, touches shipped hook)

- [ ] P2: (Expand before implementing) Move `post-tool-use.sh` onto the shared emitter
  path and emit `"3.0"`, killing the inline `"1.1"` builder divergence. Keep bats +
  `test-post-tool-use.sh` green with no field regressions.

### Slice 5 — Durable sink + ai-log CLI + adapters (HIGH risk, needs release-auditor)

- [ ] P2: (Expand before implementing) Build `tools/ai/agent-log.php` durable sink,
  the `ai-log` CLI (`emit`/`query`/`report`), `tools/ai/adapters/**`, and
  context/patch/tool-call payload builders (`33-log-tools.sh`). Define a
  rollback/disable path. Store diffs as artifacts (path + sha256), never inline.

## Acceptance Criteria

Slice 1 ACs are observable and testable now; later-slice ACs are recorded for
completeness and become checkable when their slice is expanded and implemented.

### Slice 1

- [ ] AC-01: Each emitted event carries a unique `ids.event_id` (uniqueness asserted
  across a batch of emitted events in `test-common.sh`).
- [ ] AC-02: `ids.sequence` is monotonically increasing per process (asserted by
  emitting multiple events and checking strictly increasing sequence).
- [ ] AC-03: A `payload._status` value overrides the suffix-derived
  `event_execution_status()` result in the emitted event.
- [ ] AC-04: Redaction is applied to the emitted payload via `log_redact_payload()`
  (asserted by feeding a redactable token and confirming it is masked in output).
- [ ] AC-05: `flock` serializes concurrent appends; when `flock` is absent the code
  degrades to plain append and emits a warning (both paths covered in
  `logging-core.bats`).
- [ ] AC-06: `event_version == "3.0"` in emitted events and in the updated
  `test-common.sh` assertion.
- [ ] AC-07: `scripts/ai/MANIFEST.md` lists `31-log-redaction.sh`, so
  `ScriptsAiManifestTest` passes.
- [ ] AC-08: `bash tests/scripts/ai/test-common.sh` and `composer test` both pass
  (all suites green).

### Slice 2

- [ ] AC-09: Standalone-script events carry real trace/session IDs (no `"unknown"`);
  the `runner` section is populated from env with `"shell"`/`null` defaults; no runner
  branching exists in core.

### Slice 3

- [ ] AC-10: Guarded commands emit paired span start/finish events with `span_id` +
  latency, reusing exec-guard timing; no duplicate wrapper is introduced.

### Slice 4

- [ ] AC-11: `post-tool-use.sh` emits `"3.0"` via the shared path; bats +
  `test-post-tool-use.sh` are green; no field regressions.

### Slice 5

- [ ] AC-12: The sink persists events and `ai-log query`/`report` round-trips
  correctly; diffs are stored as artifacts (path + sha256), never inline; a
  rollback/disable path is defined.

## Verification Plan

- **Slice 1 (proves AC-01..AC-08):** `bash tests/scripts/ai/test-common.sh` (new
  `event_id`/`sequence`/`_status`/redaction assertions + `"3.0"` version assertion),
  then the new/extended `tests/shell/logging-core.bats` (flock append + rotation +
  degrade), then `composer test` / `composer test:fast` for the full suite including
  `ScriptsAiManifestTest`.
- **Slice 2:** emit events from a standalone script path and assert non-`"unknown"`
  trace/session plus env-derived `runner` fields.
- **Slice 3:** run a guarded command and assert paired span start/finish with
  `span_id` + latency.
- **Slice 4:** `test-post-tool-use.sh` + bats; assert `"3.0"` and no field regressions.
- **Slice 5:** `ai-log` round-trip test (emit → query → report) + artifact-path/sha256
  assertion; verify rollback/disable path.

## Risks And Rollback

- **flock portability (MED):** macOS lacks `flock`. Mitigation: feature-detect and
  degrade to plain append + warn, mirroring the verified exec-guard degrade at
  `exec-guard/40-run-guarded.sh:65`.
- **Downstream schema consumers (UNKNOWN):** install targets carry a `skip-if-exists`
  schema copy; changes are additive and non-breaking (no new `required` keys), so
  old-shape events keep validating. Impact on already-installed downstream copies is
  not proven from this repo — treat as unknown until a consumer is inspected.
- **`post-tool-use.sh` `"1.1"` vs `"3.0"` coexistence (MED):** both validate because
  `event_version` is not value-gated by the schema and is required only as a non-empty
  string; the divergence persists until Slice 4 reconciles it.
- **Cached git metadata staleness (LOW):** a mid-process branch switch would make the
  process-scoped cached branch/commit stale; acceptable given per-event recompute cost
  and the low likelihood of mid-process branch switches.
- **File-path fidelity (planning risk):** the design refers to `run_guarded()` as
  `40-run-guarded.sh` and to `agent_session_init()` as `40-session.sh`; the real
  exec-guard path is `scripts/ai/internal/lib/exec-guard/40-run-guarded.sh` (nested),
  while `40-session.sh` is a flat lib. Slice 3 must use the nested path; the MANIFEST
  assertion applies to `internal/lib/*.sh` (flat) files — confirm whether the nested
  `exec-guard/` tree is covered by the same manifest rule before adding entries there.

**Rollback (Slice 1):** revert the 6 touched files. `31-log-redaction.sh` is new, so
rollback is a delete. `log_json`'s signature is unchanged, so its 15+ callers are
unaffected by a revert.

## Handoff Notes

- Implement **Slice 1 only** from this plan; it is fully specified and bounded to
  <=6 files. Slices 2-5 are ordered stubs — expand each (or split into its own plan)
  and confirm scope before implementing.
- Two test-coupled invariants must move in the same commit as the code: the
  `event_version` assertion in `test-common.sh` and the `MANIFEST.md` entry for
  `31-log-redaction.sh`.
- Confirm the manifest-coverage question in Risks (flat `internal/lib/*.sh` vs the
  nested `exec-guard/` tree) before Slice 3.
- Recommended next step: hand off to the implementer agent using the OpenCode command
  `/implement`, scoped to Slice 1.

## Archive On Completion

When every `## Todo Plan` item **and** every `## Acceptance Criteria` item above is
checked `[x]`, archive this plan so active and finished plans stay separated:

```text
docs/tickets/arch-todo-runner-agnostic-logging-core-20260706/plan.md
  -> docs/tickets/arch-todo-runner-agnostic-logging-core-20260706/archive/DONE-plan.md
```

Steps: (1) `mkdir -p` the `archive/` folder, (2) write the full plan to
`archive/DONE-plan.md`, (3) replace this file with a one-line tombstone pointing to the
archived copy. Do not archive while any Todo item or Acceptance Criterion is still
unchecked. If this ticket is deliberately scoped to Slice 1 only, treat the Slice 1
Todo + Slice 1 AC set as the completion gate and note the deferred slices in the
tombstone.
