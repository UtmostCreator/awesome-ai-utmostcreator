# Architecture Plan — Remaining Rework Across Four Todos (Post scripts/ai Migration)

- Ticket: none
- Source: architect design handoff (remaining-work scoping of todo-agents-rework.md, todo-agents-script-rework.md, todo-introspect-improvement.md, todo-scripts-refactor.md)
- Generated: 20260613-231930
- Plan folder: docs/tickets/arch-todo-remaining-rework-20260613-231930/

## Context

A scripts/ai migration has ALREADY been applied and is DONE (do not redo):

- `scripts/ai/lib/*` and `scripts/ai/ai-search/*` were RENAMED to
  `scripts/ai/internal/{lib,search}/`.
- 41 additive delegating shims live under
  `scripts/ai/bin/{read,context,verify,edit,admin,hooks}/`.
- `scripts/ai/MANIFEST.md` and `tests/php/ScriptsAiManifestTest.php` were added.
- Migration strategy (frozen): the root script `scripts/ai/<name>.sh` is the
  canonical, registered contract; `bin/<role>/` entries are additive UNREGISTERED
  delegating aliases; implementation internals live under `internal/`. Root names
  must NOT change.

A reviewer classified done-vs-remaining across four source todos
(`todo-agents-rework.md`, `todo-agents-script-rework.md`,
`todo-introspect-improvement.md`, `todo-scripts-refactor.md`); an architect then
scoped the REMAINING work into seven phases. This plan persists that scoping.
Phase 1 is fully specified as the implementable first slice; Phases 2–7 are
ordered and bounded but intentionally under-specified pending their own architect
pass.

Repository state observed before writing this plan:

- Branch/status: `git status --short` on `main`; the migration changes above are
  present in the working tree (renames + `scripts/ai/bin/**` additions +
  `MANIFEST.md` + `ScriptsAiManifestTest.php`).

Verified current state of the Phase 1 surface (`tools/ai/sh-introspect/`):

- `tools/ai/sh-introspect/` has 18 numbered modules plus the `sh-introspect.php`
  loader (confirmed by directory listing).
- `10-cli.php` `--format` accepts ONLY `json` and `help`, in both the bare
  `--format VALUE` form (lines 21–33) and the `--format=VALUE` form (lines 72–85);
  any other value calls `shIntrospectFail()` with
  `"unknown --format value: {value} (expected json or help)"` (lines 32 and 84).
- Default branch (neither json nor help, and `AI_OUTPUT` != json) runs the verbose
  text renderer `shIntrospectRenderText()` (line 150). `--format=help` ->
  `shIntrospectRenderHelpSummary()`. So the CURRENT DEFAULT = verbose text.
- All STDOUT/file writes funnel through `shIntrospectEmitReport()` (defined at
  line 208), whose STDOUT sink is the literal `fwrite(STDOUT, $report)` at line 211
  — a single attach point for a pager.
- No pager logic exists: a content search for `pager|isatty|less -R` under
  `tools/ai/sh-introspect/` returned zero matches.
- CI/TTY detection precedent exists elsewhere: `tools/ai/commands/helpers.php` uses
  `getenv('CI')` + `stream_isatty(STDIN)`; `secret-scan.php` uses `getenv('CI')` /
  `GITHUB_ACTIONS`. Reuse this idiom.

## Problem

The remaining rework spans agent inventory/governance and further script
decomposition, but it is gated by one dominant binding constraint:
`sh-introspect --format=help` (the compact summary) is consumed by ~70 callers —
every `scripts/ai/*.sh --help` / `--introspect` path, all 41 `bin/` shims,
`common.sh:63`, and the universal help convention. The `bin` shim contract diffs
`--introspect` output. ANY byte change to existing `json` / `help` / default-text
output breaks this fleet and existing tests. Byte-identical output preservation is
therefore the dominant constraint across all phases, and the work must be staged so
that each phase is independently verifiable and revertible without disturbing the
frozen migration contract.

## Target Outcome

The seven remaining phases are documented as an ordered, scope-locked migration
path. Phase 1 (sh-introspect `--format` aliases + smart pager) is implementable
immediately as an additive, byte-identical-preserving slice. Phases 2–7 are
recorded with correct ordering, risk level, conflict-resolution constraints, and an
explicit note that each requires its own architect pass before implementation.

## In Scope

- Document all seven remaining phases with risk levels and ordering.
- Fully specify Phase 1: `sh-introspect` `--format full`/`--format summary` aliases
  (additive, default unchanged) plus a smart, fully gated pager.
- Carry the verbatim negative acceptance criteria and conflict resolutions (C1–C9).
- Define Phase 1 acceptance criteria, verification plan, risks, and rollback.
- Record the open question about `.github/agent-permissions/*.yaml` for Phase 6b.
- Provide bounded, intentionally under-specified scope notes for Phases 2–7.

## Out Of Scope (Things To Avoid)

Repository-wide things-to-avoid (carry verbatim across all phases):

- MUST NOT change root script names or relocate root implementations (migration
  freeze, C1).
- MUST NOT alter byte output of `--format=json`, `--format=help`, the default
  verbose text, or `--introspect` for any non-interactive invocation.
- MUST NOT page when STDOUT is not a TTY, when `AI_OUTPUT=json` / `--format=json`,
  when `CI` / `GITHUB_ACTIONS` is set, or when `--output PATH` is used.
- MUST NOT introduce numbered lifecycle folders (`00-help` .. `99-danger`) — C4.
- MUST NOT create a second source of truth for agent permissions without
  retiring/deriving from the inline `.opencode/agents/*.md` source — C8.
- MUST NOT edit `ai-verify.sh` for both the split (Phase 6a) and P10 enforcement
  (Phase 6b) in overlapping uncoordinated changes — C9.

Phase 1 things-to-avoid (specific):

- Do not change the default format (default stays full / verbose text).
- Do not touch the rendering logic in `70-render-text.php` or `75-render-help.php`.
- Do not page in any non-TTY / JSON / CI / `--output` path.
- Do not make a missing `less` fatal.
- Do not alter the `--introspect` JSON that the `bin` shims diff.

Implementation-deferral boundary:

- Do not implement Phases 2–7 from this plan. Each requires its own architect pass.
  Adjacent ideas surfaced during Phase 1 must be recorded here, not implemented.

## Affected Paths

Phase 1 (the only fully-specified slice):

- `tools/ai/sh-introspect/10-cli.php` — flag parsing for new aliases and pager
  flags; pager attach at `shIntrospectEmitReport()` (line 208) STDOUT sink.
- `tools/ai/sh-introspect/12-pager.php` — OPTIONAL new small helper, loader-ordered
  before `10-cli.php`, holding the gated pager decision + invocation.
- `tools/ai/sh-introspect.php` — docblock update to list the new `--format` aliases
  and pager flags.
- `docs/tickets/` — durable planning output location for this file.

Later phases (target surfaces; inspected/edited only under their own architect pass):

- `.opencode/agents/*.md`, `.github/agents/*.md` — Phases 2, 3 (inventory + frontmatter).
- `scripts/ai/MANIFEST.md` — Phase 2 mirroring precedent.
- `.github/ai-script-access.yaml` — Phase 4 (root-keyed tiers).
- `.github/agent-permissions/*.yaml` — Phase 6b (generated/derived only; open question).
- `scripts/ai/pre-tool-use.sh`, `scripts/ai/internal/lib/60-exec-guard.sh` — Phase 5.
- `scripts/ai/ai-verify.sh` (+ `internal/ai-verify/`) — Phases 6a, 6b.
- `scripts/ai/ai-edit.sh`, `scripts/ai/repomix-scc-router.sh`,
  `scripts/ai/repomix-context-tree.sh`, `scripts/ai/ai-diff-context.sh`,
  `scripts/ai/prune-shipped-targets.sh` (+ matching `internal/<name>/`) — Phase 7.

## Contracts And Boundaries

- `sh-introspect --format=help` compact summary is consumed by ~70 callers; its
  byte output is a hard contract. The same applies to `--format=json`, the default
  verbose text, and `--introspect`.
- `bin/<role>/<name>.sh` shims diff `--introspect` against the root impl; the
  `--introspect` JSON must stay byte-identical.
- Root script names and root implementation locations are frozen (C1).

Conflict resolutions (carry verbatim):

- C1: split scripts keep the root path as a THIN LOADER; internals ->
  `internal/<name>/`; the `bin` shim still delegates to the root. Root names never
  change.
- C2: fix the stale `lib/60-exec-guard.sh` reference -> `internal/lib/60-exec-guard.sh`.
- C4: keep role folders (`read`/`context`/`verify`/`edit`/`admin`/`hooks`); drop
  numbered lifecycle folders.
- C8: inline `.opencode/agents/*.md` permissions are canonical; any
  `.github/agent-permissions/*.yaml` must be generated/derived, not parallel
  hand-authored.
- C9: sequence the `ai-verify` split (6a) fully before P10 enforcement (6b); no
  interleaving.

## Todo Plan

Phases are ordered P1 -> P2 -> P3 -> P4 -> P5 -> P6a -> P6b -> P7. Only Phase 1 is
implementable from this plan; every later phase requires its own architect pass.

### Phase 1 — sh-introspect `--format` aliases + smart pager (FIRST SLICE, LOW risk, FULLY SPECIFIED)

Phase 1a — format aliases (file: `tools/ai/sh-introspect/10-cli.php` only):

- [x] P0: In the bare `--format VALUE` branch, add `summary` -> `helpSummary = true`
      and `full` -> no-op (the default already renders verbose text via the `else`
      branch), so an explicit `forceFull` flag was unnecessary.
- [x] P0: In the `--format=VALUE` branch, add the same `summary` -> `helpSummary`
      and `full` -> no-op mappings.
- [x] P0: Kept `json` and `help` mappings unchanged; DEFAULT stays full (verbose
      text); `summary` is NOT the default.
- [x] P0: Updated the error string in BOTH branches to
      `"expected json, help, summary, or full"`.
- [x] P0: Updated `shIntrospectUsage()` and the `sh-introspect.php` docblock to list
      the new `--format summary` / `--format full` aliases (and the pager flags).

Phase 1b — smart pager (files: `tools/ai/sh-introspect/10-cli.php` for flag parsing
+ `shIntrospectEmitReport()`; new `tools/ai/sh-introspect/12-pager.php` helper
loader-ordered before `10-cli.php`):

- [x] P0: Parsed two new flags in `10-cli.php`: `--pager` (force on, still subject to
      the TTY guard) and `--no-pager` (force off), above the unknown-`--` catch-all.
- [x] P0: Pager activates ONLY when ALL true (in `shIntrospectShouldPage`): STDOUT is
      a TTY; not JSON; not `--output PATH`; not CI (`CI`/`GITHUB_ACTIONS` == "true");
      not `--no-pager`. `--pager` overrides only the default-off heuristic.
- [x] P0: Pager command is `less -R -F -X`, honoring `AI_PAGER` / `PAGER`; an absent
      OR unresolvable env pager falls back to direct STDOUT — never fatal.
- [x] P0: Byte-identical output preserved: the pager runs via `proc_open` ONLY in the
      gated interactive branch (passed as a `bool $page` arg to
      `shIntrospectEmitReport`); the non-interactive branch stays the literal
      `fwrite(STDOUT, $report)`. The `--all` index path threads the same gate.

Implementation notes (deviations from the spec, all evidence-verified):

- A `bool $page` parameter was added to `shIntrospectEmitReport()` (default `false`)
  and the pager decision is computed at the call sites via `shIntrospectShouldPage()`.
  This threads the gate through the 3 emit call sites (`10-cli.php` single-file and
  `15-index.php` lines 28/47 for `--all`) without changing the literal STDOUT sink.
- `--all` text output is now pageable on an interactive TTY (consistent with single-
  file text); its JSON path is never paged.
- AC-11 hardened: an env `AI_PAGER`/`PAGER` whose binary does not resolve falls back
  to STDOUT (via `shIntrospectFirstWord` + `command -v` probe) so a broken pager can
  never swallow output.

### Phase 2 — agent inventory doc (LOW) — DONE

- [x] P1: Produced `docs/ai/AGENTS-MANIFEST.md` mirroring `scripts/ai/MANIFEST.md`
      for `.opencode/agents/*.md` (19) + `.github/agents/*.agent.md` (22), with the
      lifecycle/mutating/gate/risk classification and a "Surface coverage
      differences" section. Coverage test `tests/php/AgentsManifestTest.php`
      (3 tests, 51 assertions) asserts every agent on both surfaces is classified.

### Phase 3 — agent governance metadata — SPLIT into 3a (DONE) + 3b (DEFERRED)

- [x] P1 (3a): Canonical governance metadata (two-score-style lifecycle/mutating/
      gate/risk per agent) captured in the non-generated `docs/ai/AGENTS-MANIFEST.md`.
      Decision: do NOT hand-edit the GENERATED `.opencode/agents/*.md` /
      `.github/agents/*.agent.md` (DO-NOT-EDIT, rendered from
      `packages/.../templates/**` via the approval-gated installer). The manifest is
      the durable, schema-safe form.
- [ ] P1 (3b, DEFERRED/backlog): add lifecycle + `agent_score` to the canonical
      TEMPLATE schema and the renderer, then re-render through the approved installer
      pipeline. Approval-gated (installer run); not done in this pass.

### Phase 4 — script access tiers (MEDIUM) — DONE

- [x] P1: Added `.github/ai-script-access.yaml` keyed on ROOT script names with
      tiers T0–T5 derived from `scripts/ai/MANIFEST.md`; documented that `bin/`
      aliases inherit the root tier and internal modules are never exposed. Contract
      test `tests/php/AiScriptAccessManifestTest.php` (3 tests) asserts every root
      script is tiered exactly once, dangerous scripts only in T5, and only the
      runtime guardian may use the tool-use hooks.

### Phase 5 — split pre-tool-use.sh + 60-exec-guard.sh (MED-HIGH) — DONE

- [x] P2: Split `pre-tool-use.sh` (447L) -> 40-line root loader +
      `internal/pre-tool-use/{10-helpers,20-decide}.sh`. Split
      `internal/lib/60-exec-guard.sh` (345L) -> thin loader +
      `internal/lib/exec-guard/{10-run-timeout,20-cpu-sampling,30-kill-tree,40-run-guarded}.sh`
      (C2 path corrected to `internal/lib/`). Behavior preserved: `--help`
      byte-identical, risk preserved (pre-tool-use high), shellcheck clean,
      `test-pre-tool-use` 30/30, `test-common`/`test-common-source` pass, hang-kill
      watchdog verified (rc=124 in 3s).

### Phase 6a — split ai-verify.sh (HIGH) — DONE

- [x] P2: Split `ai-verify.sh` (718L) -> 82-line root loader +
      `internal/ai-verify/{10-scope,20-shipped-filters,30-linecount,40-step-runner,90-run}.sh`.
      `--help` byte-identical, risk preserved (high), shellcheck clean,
      `test-ai-verify` 24/24. Updated the test helper `load_scoping_functions` to
      source the relocated helper modules (was sed-extracting from the monolith).

### Phase 6b — P10 consistency checks (HIGH) — DONE (without dual-source perms)

- [x] P2: Added `tools/ai/validate-script-access.php` enforcing the manifest
      invariants (every root script tiered once; dangerous scripts only in T5;
      internal modules never exposed; access-manifest agents exist in
      AGENTS-MANIFEST; only runtime guardian may use tool hooks). Wired into
      `scripts/ai/ai-doc-check.sh` and documented in `docs/ai/validation.md`.
      Negative-tested (catches injected violations). C8 honored: inline
      `.opencode/agents` permissions stay canonical; NO `.github/agent-permissions/*.yaml`
      dual source was created. C9 honored: this landed after 6a verified.

### Phase 7 — split remaining large scripts (HIGH) — DONE

- [x] P2: Split all five via the C1 thin-loader pattern + `internal/<name>/`, each
      verified behavior-preserving (`--help` byte-identical, introspect risk
      preserved, shellcheck clean, shell test suite green):
  - `prune-shipped-targets.sh` (399L) -> 53-line loader +
    `internal/prune-shipped-targets/{10-rules,60-apply,90-run}.sh` (delete isolated
    in `60-apply.sh`, AC8); risk critical preserved; `test-prune-shipped-targets` 5/5.
  - `ai-edit.sh` (817L) -> 117-line loader (keeps curated usage) +
    `internal/ai-edit/{10-helpers,30-parse,40-plan-apply,90-main}.sh`; risk high
    preserved; `test-ai-edit` 18/18.
  - `ai-diff-context.sh` (662L) -> 76-line loader +
    `internal/ai-diff-context/{10-helpers,40-commands,90-main}.sh`; help identical;
    `test-ai-diff-context` 5/5.
  - `repomix-scc-router.sh` (967L) -> 85-line loader +
    `internal/repomix-scc-router/{10-helpers,40-analysis-pack,90-main}.sh`; risk
    critical preserved (after fixing the dir-var to a direct `dirname BASH_SOURCE`
    form so sh-introspect inlines modules); `test-repomix-scc-router` 9/9. Test
    helper repointed at the helpers module.
  - `repomix-context-tree.sh` (733L) -> 76-line loader +
    `internal/repomix-context-tree/{10-helpers,40-build-pack,90-main}.sh`; risk
    critical preserved; `test-repomix-context-tree` 4/4. Fixed an in-module
    `SCRIPT_DIR=$(dirname BASH_SOURCE)` that pointed at the module dir — re-anchored
    to the root `COMMON_DIR` so the sibling `repomix-scc-router.sh` resolves and
    `analyze` runs end-to-end.

## Acceptance Criteria

Phase 1 acceptance criteria (observable, testable):

- [x] AC-01: `--format full FILE` and the no-flag `FILE` produce byte-identical output. (golden diff OK; `testFormatFullEqualsDefaultText`)
- [x] AC-02: `--format summary FILE` and `--format help FILE` produce byte-identical output. (golden diff OK; `testFormatSummaryEqualsHelp`)
- [x] AC-03: `--format=json` and `AI_OUTPUT=json` output is unchanged byte-for-byte. (golden diff OK; `testJsonOutputNeverPagedAndUnchanged`)
- [x] AC-04: A piped (non-TTY) invocation does NOT page and is byte-identical to current. (all golden diffs run piped; OK)
- [x] AC-05: `CI=true` does NOT page. (`CI=true` golden diff OK)
- [x] AC-06: `--output PATH` produces the same bytes and does NOT page. (golden diff OK, incl. `--pager --output`)
- [x] AC-07: `--no-pager` on a TTY disables paging; `--pager` forces paging on a TTY only. (PTY test via `script`, both exit 0 no hang; `testPagerFlagsAreByteIdenticalOnNonTty`)
- [x] AC-08: `--format x` (unknown) errors with `"expected json, help, summary, or full"`. (both forms; `testUnknownFormatErrorListsAllValidNames`)
- [x] AC-09: `bash scripts/ai/bin/read/sh-introspect.sh --introspect` is byte-identical to pre-change. (golden diff OK)
- [x] AC-10: `bash scripts/ai/sh-introspect.sh --help` is unchanged. (root shim `--introspect` exit 0; ShHelpTest delegate tests pass)
- [x] AC-11: A missing `less` falls back to STDOUT and exits 0 (never fatal). (broken `AI_PAGER` emits `FALLBACK-OK` to STDOUT; resolve returns null)

Cross-phase acceptance criteria (apply to every later phase when it is implemented):

- [x] AC-12: No root script name is renamed and no root implementation is relocated (C1). (Phase 1 edited only `tools/ai/sh-introspect/*` + a test; no scripts/ai script moved)
- [x] AC-13: No byte change to `--format=json`, `--format=help`, default verbose text,
      or `--introspect` for any non-interactive invocation. (all golden diffs byte-identical)
- [x] AC-14: No numbered lifecycle folders are introduced (C4). (Phase 1 added no folders)
- [ ] AC-15: Agent-permission changes keep inline `.opencode/agents/*.md` canonical;
      any `.github/agent-permissions/*.yaml` is generated/derived, not hand-authored (C8).
- [ ] AC-16: `ai-verify.sh` split (6a) lands and verifies fully before P10
      enforcement (6b); the two are not interleaved (C9).
- [ ] AC-17: Each later phase is implemented only after its own architect pass produces
      a fully specified plan.

## Verification Plan

Phase 1 verification (narrow -> broad; each step names the AC it proves):

- Golden-output diff of all five format/sink combos vs captured baselines — proves
  AC-01, AC-02, AC-03, AC-04, AC-06.
  - `--format full FILE` vs no-flag `FILE` (AC-01)
  - `--format summary FILE` vs `--format help FILE` (AC-02)
  - `--format=json FILE` and `AI_OUTPUT=json ... FILE` vs baseline (AC-03)
  - piped/non-TTY run vs baseline (AC-04)
  - `--output PATH` written bytes vs baseline (AC-06)
- `php tools/ai/sh-introspect.php --help` — proves usage/docblock updated and the
  `--help` text policy is intact (supports AC-10).
- `CI=true` invocation on a TTY shows no paging — proves AC-05.
- `--no-pager` (TTY) and `--pager` (TTY only) behavior checks — prove AC-07.
- Unknown-format invocation emits `"expected json, help, summary, or full"` — proves AC-08.
- Spot `--introspect` byte-diff on 2–3 `bin` shims (including
  `scripts/ai/bin/read/sh-introspect.sh`) vs root — proves AC-09.
- `bash scripts/ai/sh-introspect.sh --help` vs pre-change capture — proves AC-10.
- Temporarily-unavailable `less` (e.g. `PAGER`/`AI_PAGER` pointing at a missing
  binary) falls back to STDOUT, exit 0 — proves AC-11.
- Run `tests/php/ScriptsAiManifestTest.php` (focused) — guards the migration contract.
- `composer test:fast` — broader regression check (run last).

Notes on running commands: prefer narrow checks first and stop at the first failing
gate. Apply the anti-freeze budgets from `docs/ai/execution-protocol.md`
(read-only discovery 30s; focused unit 60s; `composer test:fast` 90s). For PHP tools
using `proc_open`, prefer file-descriptor capture over pipes to avoid pipe-buffer
deadlocks (pager work touches `proc_open`/`popen`).

Later-phase verification (each phase defines its own under its architect pass):
golden-output diffs for behavior-preserving splits, hook tests for Phase 5, and a
coverage test for Phase 2. Do not run later-phase verification as part of Phase 1.

## Risks And Rollback

- Risk: pager routing accidentally changes output bytes. Mitigation: keep the report
  STRING identical; only the gated interactive branch changes the sink; the
  non-interactive branch stays the literal `fwrite(STDOUT, $report)`. Proven by the
  golden-output diff (AC-01..AC-06).
- Risk: pager triggers in a non-interactive context (CI, pipe, JSON, `--output`).
  Mitigation: the all-true guard (TTY AND !json AND !output AND !CI AND !no-pager);
  `--pager` cannot override these guards. Proven by AC-04, AC-05, AC-06, AC-07.
- Risk: a missing `less` breaks runs. Mitigation: fall back to STDOUT and never fail;
  proven by AC-11.
- Risk: alias parsing accidentally changes the default or the error string.
  Mitigation: default stays full; explicit `forceFull` flag; updated error string
  asserted by AC-08; AC-01 confirms the default is unchanged.
- Risk: `--introspect` JSON drift breaks the 41 `bin` shims and tests. Mitigation:
  Phase 1 touches only `10-cli.php` flag parsing + the emit sink, not the renderers;
  AC-09 spot-diffs shims; `ScriptsAiManifestTest` + `composer test:fast` regress.
- Rollback (Phase 1): the change is additive and confined to `10-cli.php`
  (plus an optional `12-pager.php` and a docblock line); revert that commit to
  restore prior behavior with no consumer changes.
- Rollback (later phases): each split lands as one revertible slice; root names and
  the public contract are preserved, so reverting a slice restores prior behavior.

## Handoff Notes

- Implement Phase 1 ONLY from this plan. It is the bounded, byte-identical-preserving
  first slice (LOW risk).
- Do NOT implement Phases 2–7 from this plan; each is intentionally under-specified
  and requires its own architect pass before implementation. Follow the ordering
  P1 -> P2 -> P3 -> P4 -> P5 -> P6a -> P6b -> P7.
- Honor every verbatim negative acceptance criterion and conflict resolution
  (C1, C2, C4, C8, C9) on every phase.
- OPEN QUESTION (record + confirm before Phase 6b): is the
  `.github/agent-permissions/*.yaml` surface actually desired, or should agent
  permissions stay inline-only? Flag for user confirmation before starting Phase 6b.
- implementer means implementer agent handoff using OpenCode command: /implement
