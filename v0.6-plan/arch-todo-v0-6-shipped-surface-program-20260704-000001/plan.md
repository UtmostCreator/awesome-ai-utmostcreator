# Architecture Plan — v0.6 Shipped-Surface Program (Merged)

- Ticket: none
- Source: merge of four v0.5-upgrade plans plus graph re-analysis (graphify-out/graph.json)
- Generated: 20260704-000001
- Plan folder: v0.6-plan/arch-todo-v0-6-shipped-surface-program-20260704-000001/

## Merged Inputs

| Track | Source plan | Owns |
|---|---|---|
| A | `v0.5-upgrade/arch-todo-repo-clarity-and-surface-consolidation-20260703-192142/plan.md` | repo clarity, ownership routing, capability discoverability |
| B | `v0.5-upgrade/arch-todo-template-surface-thinning-and-critical-coverage-20260703-193900/plan.md` | template thinning, critical-topic coverage, dead-file prevention |
| C | `v0.5-upgrade/arch-todo-clarification-prd-and-handoff-architecture-20260703-195541/plan.md` | clarification, PRD, task-generation, handoff semantics |
| D | `v0.5-upgrade/arch-todo-ai-workflow-program-roadmap-20260703-201136/plan.md` | program ordering (superseded by this plan) |
| F | field notes `/home/utmostcreator/Projects/copy-paste/ai/*.md` (external, read-only, user-named) | real installed-target failure reports and quality rules |
| L | tool-usage log `/home/utmostcreator/Projects/copy-paste/ai/ai-logs/.ai-logs/tool-usage.jsonl` (external, read-only, user-named; 862 events, 2026-05-12..2026-07-03, target `headless-cms`) | observed runtime failures of shipped scripts, hooks, and evidence pipeline |

This plan supersedes Track D's ordering. The four v0.5 plans remain the detailed
implementation references; this plan owns the sequence, the shipped-files
constraint, the conflict resolutions, and the added missing points.

## Binding Constraint (User-Set)

All design and implementation work targets **files we ship**:

- `packages/ai-universal-rules/templates/**` (template sources)
- shipped canonical docs listed in `docs/ai/installed-files.md` (verified: includes
  `docs/ai/handoff-contract.md`, `docs/ai/integration-matrix.md`, `docs/ai/workflow.md`,
  `docs/ai/adapter-contract.md`, `docs/ai/capabilities/**`, and more)

Rendered runtime outputs (`.opencode/**`, `.github/**`, root `AGENTS.md`, `CLAUDE.md`,
`.github/copilot-instructions.md`) are **never edited directly** in this program. They
follow from template sources via the installer/render pipeline. Any v0.5 chunk that
targeted a rendered surface is rerouted to its template source in this plan.

## Context — Graph Re-Analysis Evidence

Evidence gathered from `graphify-out/graph.json` (3,717 nodes, 5,664 links,
47 hyperedges) and direct file verification. All claims below are query-backed,
not aspirational:

- 139 shipped template files spread across ~42 graph communities — Track B's
  "surface sprawl" is structurally confirmed.
- `packages/ai-universal-rules/templates/core/opencode.json` has **zero graph edges**
  and always-loads **13 instruction docs**. It is the least-governed shipped surface
  and the highest-value thinning target.
- A shared-source include mechanism **already exists**: `templates/snippets/` holds 5
  snippets (workflow, approvals, verification, agent-tools-execute, agent-tools-readonly).
  Overlap with Track B's proposed "compact behavioral baseline shared source" is ~80%.
  Per the >=75% reuse rule, the baseline extends this pattern — no new `templates/shared/**` tree.
- Template-to-canonical routing already works where it exists: 31 graph edges from
  templates into `docs/ai/capabilities/**` and `docs/ai/tools/**`. Thinning is low-risk
  where these edges exist and high-risk where they do not (opencode.json, instructions/).
- `templates/workflows/` has 17 entries (strong pattern); `templates/skills/` has only 2
  and `templates/commands/` only 4 (weak patterns). Track C's new family lands as
  workflow templates first; skills/commands wrappers are greenfield and go last.
- 22 instruction templates ship from `templates/instructions/` — Track A's instruction
  consolidation happens there, not in rendered `.github/instructions/**`.
- **No validator-to-template edges exist in the graph.** The only drift protection is
  the validator suite (`validate-install-surface.php`, `validate-adapter-drift.php`).
  Every slice must run it; nothing else will catch semantic loss.
- Line budgets (measured): `AGENTS.template.md` 195 lines (soft max 180 already exceeded),
  `copilot-instructions.template.md` 140, `capabilities/README.md` 33,
  `docs/ai/handoff-contract.md` 24. Thinning slices must be net-negative on
  always-on line count.

## Context — Field Evidence From Installed Targets (Track F)

Notes from a real installed target (external `copy-paste/ai` notes, read-only) report
shipped-feature failures that none of the v0.5 plans address:

- F-1 hook reliability: the shipped Copilot pre-tool-use hook
  (`.github/hooks/tool-policy.json` in the target) **errored and denied a plain
  read-only `git status`**. A hook error must not hard-deny benign read-only commands.
  Shipped sources: `templates/github/hooks/scripts/tool-guardian.sh`, `tool-guardian.ps1`,
  `command-policy.compiled.sh`.
- F-2 rewrite loops: agents "infinitely attempt to rewrite md files to fix
  non-existing issues" — loop-guard / stop-after-N-attempts semantics are not
  guaranteed-load on all runtimes.
- F-3 permission/duty mismatch: agents are blocked from moving or editing files their
  role requires; the shipped permission templates and agent duty declarations are not
  validated against each other.
- F-4 blocked-edit duplication: a write agent whose edit is silently blocked re-appends
  content, producing duplicates. No shipped blocked-edit fallback protocol exists
  (verify write success; never re-append; stop and report).
- F-5 agent-vs-skill misfit: some shipped agents are "defeated" by their boundary and
  should be packaged as skills instead.
- F-6 context-economy leak: in the target, `graphify-out/graph.json` consumed 90%
  (207k tokens) of a repomix pack. Shipped ignore surfaces do not exclude graph and
  pack artifacts by default.
- F-7 desired shipped conventions: write-agent code-quality guardrails (max 3 returns
  per method, max 2 nested ifs, complexity < 15, params < 5, LOC > 500 -> split and
  flag for refactor, readability first without hurting performance, always show
  remaining work) and a test-grouping rule (new tests join an appropriate existing
  group or start a new one).
- F-8 multi-scope graphify maintenance: installed targets use per-scope graphs merged
  into one root graph (`update` -> `merge-graphs` -> `cluster-only`); doc/config scopes
  need semantic re-extraction. No shipped guidance covers this.

Updated field notes (re-read 2026-07-04) add:

- F-9 validator portability and target-completeness failures: checks refuse unbounded
  execution because `timeout` is unavailable on the target (macOS); `validate-ai-catalog.php`
  fails in installed targets because it requires `packages/ai-universal-rules/manifest.json`,
  a source-repo-only file that never ships; `validate-ai-config.php` floods reports with
  pre-existing missing-file noise unrelated to the current change; docs reference a
  "generated impact helper" that does not exist in the target.
- F-10 workflow ergonomics requests: plans must be split per project starting with the
  most logical one, and every P0/P1/P2 task should be as close as possible to a single
  unit of work verifiable with the user or by test coverage; review/research agents
  must state the goal up front (what to review or research, and why); default handoff
  chains: architect hands off straight to plan-writer, implementer hands off to
  reviewer; runtimes that support handoff metadata (VS Code `handoffs:` frontmatter
  with label/agent/prompt/send) should get it in shipped agent templates.
- F-11 agent tool gaps: the reviewer needs PR read access; the researcher failed on a
  large-file range read (`bat --paging=never -n -r 900:1320 <repomix XML>`) — shipped
  guidance should route large or generated files through `preview-file.sh --range`
  instead of raw pagers; stack-specific context commands (for Laravel targets:
  `php artisan config:show database.connections.mysql`, `php artisan about
  --only=environment`) belong in the project-stack template, not tribal knowledge.
- F-12 stale-evidence reporting pattern: agents should report degraded evidence
  sources explicitly, in the shape "Ran X; it warned about Y (for example, graph on an
  old node-ID scheme), so it was used for orientation but not treated as authoritative
  proof."
- F-13 test discipline confirmed field-critical: never change code until existing
  tests are verified passing unchanged; start bug fixes from a failing regression
  test; stash-validate-then-add when modifying tests; and reference integrity — scope
  boundaries never exempt call sites of a changed symbol. These exist in this repo's
  execution protocol but must be guaranteed-load on every runtime and survive thinning.

Large files in the same notes folder (`cms docs folder.md`, `repomix.md`) are command
output dumps; they served only as evidence for F-6 and as proof the kit runs in real
targets. No CMS-specific content is adopted.

## Context — Log Evidence From Installed Targets (Track L)

The target's `.ai-logs/tool-usage.jsonl` (862 events over ~7 weeks) proves six
additional shipped failure classes. All counts are from `jq` aggregation:

- L-1 permanent-red verification: 42 `verify.failed` vs zero recorded verify passes;
  the same failure count (`failures:4`, 31 times) persisted across 6+ weeks.
  `doc-check.failed` 33 vs `doc-check.passed` 8, failing continuously 2026-06-18 to
  2026-07-01. Shipped verification has no failure-triage or baseline concept, so
  installed targets live in permanent red and the signal becomes noise.
- L-2 docs/script contract drift: `ai-doc-check.sh` rejected the documented flag with
  `unknown mode: --check` (3 events). Shipped instructions and shipped script versions
  disagree in the target; nothing validates that documented commands actually parse.
- L-3 retry-without-change: `mode and target required` usage errors occur in identical
  back-to-back pairs (2026-06-01 twice within 3s, 2026-07-03 twice within 2s) — the
  agent retried the exact failed command unchanged. Wrapper usage errors also do not
  print corrective usage.
- L-4 evidence pipeline is decorative: every `details.raw` value is malformed JSON
  (unbalanced `}}`/`}}}`); `authorization.decision` is `unknown`, `execution.status`
  `unknown`, `exit_code` null, and `failure.category` null in effectively all events;
  several emitters log `trace_id`/`session_id` as `unknown`. Downstream parsing and
  correlation are impossible.
- L-5 guard lifecycle leak and actor blur: 324 `guard.start` vs 307 `guard.done`
  (17 guards never completed) and 631 guard events attribute `actor.id` as `common`
  instead of the real tool.
- L-6 context-pack pipeline fails on target paths: `no context bundles generated in
  .../storage/tmp/.repomix-context/tree-context/bundles`, `no files to pack`, and
  `context tree generation failed` — path defaults do not survive installed-target
  layouts.
- L-7 ai-verify scope leak (user-reported): `ai-verify.sh` fails most of the time
  because it scans folders it should never care about — `.ai-backups` and similar
  backup/artifact folders. Scoping is param-dependent and non-deterministic: with some
  parameters it runs correctly, with others it attempts to check 100% of files in the
  repository including backups and useless files. This is a major driver of the L-1
  permanent-red state.
- L-8 stuck-after-execution (user-reported): `ai-verify.sh`, test scripts, and other
  shipped scripts sometimes hang after their work completes and the user must press
  CTRL+C to get the shell back. Likely causes to audit: lingering child processes,
  unclosed pipes/file descriptors held by emitters, or a final `wait`/`read` in the
  evidence-writer path (interacts with L-4/L-5 emitter fixes).

## Context — External Gap-List Review (Track G)

An external tooling-gap analysis was triaged against this kit. Adopted items map to
phases below; rejected items are recorded so scope stays honest:

- Adopted: portable agent policy compilation (validates and strengthens Phase 5.7);
  local-first observability (validates Track O / Phase 7); provenance fields for
  evidence lines (Phase 7.5); mechanical duplication measurement with jscpd/CPD/MinHash
  (Phase 5.1); rules-eval harness for instruction regression (Phase 8.1); optional
  mutation-score verification rung (Phase 8.2); task-scoped context routing recipe on
  existing primitives (Phase 8.3); symbol-level doc drift extension (Phase 6.8 note).
- Rejected as out of kit scope: semantic/behavioral diff tooling (product-scale; the
  review-diff capability already prioritizes behavior and contracts), cross-language
  contract checking (target-project concern, for example Laravel to Nuxt), and
  SonarQube-style server dashboards (against the local-first posture).
- Verified locally: the repo has no references to the abandoned phpcpd; existing
  primitives `repomix-scc-router.sh` and `query-usage.sh` already cover ranked
  selection and token budgeting.

## Problem

Four individually coherent plans overlap, partially contradict the shipped-files
constraint, and lack graph-verified sequencing. Executing them as written risks:

1. editing rendered surfaces that the installer will overwrite (Track A chunks 10-13)
2. duplicating work across layers (Track A chunk 4 vs Track B chunk 6 are ~75%+ identical)
3. thinning ungoverned surfaces before coverage guarantees exist
4. building greenfield clarification wrappers (skills/commands) before reachability
   rules exist, creating dead files
5. relying on implicit drift protection that the graph proves does not exist

## Target Outcome

One dependency-aware program that merges Tracks A, B, C into shipped-surface slices:

- critical topics are mapped to guaranteed-load surfaces before any thinning
- one compact behavioral baseline ships from `templates/snippets/` to all runtimes
- clarification and handoff contracts are canonical in shipped docs before wrappers exist
- `templates/core/opencode.json` and `copilot-instructions.template.md` become thinner
  with proven coverage replacement
- the PRD/task/clarification workflow family lands in the proven `templates/workflows/` pattern
- rendered outputs in this repo are refreshed by render, never hand-edited
- every slice is gated by the validator suite because it is the only drift protection

## In Scope

- `packages/ai-universal-rules/templates/**` (all subtrees)
- Shipped canonical docs from `docs/ai/installed-files.md`
- Program sequencing, conflict resolution, and coverage/reachability gates
- Validator-gated verification per slice

## Out Of Scope (Things To Avoid)

- [ ] Do not edit `.opencode/**`, `.github/**`, root `AGENTS.md`, `CLAUDE.md`, or any
      rendered output directly; reroute to template sources.
- [ ] Do not redesign the agent system, installer, or validator internals unless a
      slice strictly requires it (then split it out and get approval).
- [ ] Do not create a new `templates/shared/**` tree for the behavioral baseline;
      extend `templates/snippets/`.
- [ ] Do not build Track C skills/commands wrappers before Phase 0 reachability rules exist.
- [ ] Do not force identical file structure across Copilot, OpenCode, and Claude;
      require semantic equivalence, not structural parity.
- [ ] Do not move critical semantics into optional, generated, or weakly referenced files.
- [ ] Do not touch pre-existing unrelated working-tree changes: `AGENTS.md`,
      `scripts/ai/internal/search/95-dispatch.sh`, `tests/scripts/ai/test-ai-search.sh`,
      untracked `.opencode/` additions. They belong to another task.
- [ ] Do not duplicate the full contents of the v0.5 plans; reference them.

## Affected Paths

Shipped template surfaces (primary):

- `packages/ai-universal-rules/templates/core/AGENTS.template.md`
- `packages/ai-universal-rules/templates/core/copilot-instructions.template.md`
- `packages/ai-universal-rules/templates/core/opencode.json`
- `packages/ai-universal-rules/templates/core/project-context.template.md`
- `packages/ai-universal-rules/templates/core/project/README.md`
- `packages/ai-universal-rules/templates/core/project/project-interaction.md`
- `packages/ai-universal-rules/templates/core/workflow.template.md`
- `packages/ai-universal-rules/templates/snippets/**` (baseline snippet added here)
- `packages/ai-universal-rules/templates/capabilities/README.md` and capability roots
- `packages/ai-universal-rules/templates/workflows/**` (Track C family lands here)
- `packages/ai-universal-rules/templates/instructions/**` (consolidation source layer;
  `testing.instructions.md` for the test-grouping rule)
- `packages/ai-universal-rules/templates/skills/**`, `templates/commands/**` (last, gated)
- `packages/ai-universal-rules/templates/github/hooks/scripts/**` (hook error-path fix)
- `packages/ai-universal-rules/templates/core/agents/**`, `templates/optional/agents/**`
  (duty-vs-permission parity, blocked-edit fallback)
- `packages/ai-universal-rules/templates/core/project/conventions.md` (write-agent
  quality conventions)
- shipped ignore surfaces: `.repomixignore` guidance, `templates/core/opencode.json`
  watcher ignore, search default excludes
- shipped script surfaces used by installed targets (Track L): `scripts/ai/post-tool-use.sh`,
  `scripts/ai/common.sh` emitter functions, `scripts/ai/ai-doc-check.sh`,
  `scripts/ai/ai-verify.sh`, pack pipeline scripts (`run-repomix-context.sh`,
  `repomix-context-tree.sh`), plus their install/packaging path

Shipped canonical docs (verified in `docs/ai/installed-files.md`):

- `docs/ai/integration-matrix.md` (coverage matrix home)
- `docs/ai/handoff-contract.md` (handoff payload contract)
- `docs/ai/adapter-contract.md`, `docs/ai/source-of-truth.md`, `docs/ai/validation.md`
- `docs/ai/ai-file-standards.md`, `docs/ai/installed-files.md`
- `docs/ai/workflow.md`, `docs/ai/capabilities/README.md`

Verification surfaces (run, not edited, unless a gate demands it):

- `tools/ai/validate-install-surface.php`
- `tools/ai/validate-adapter-drift.php`
- `tools/ai/validate-ai-config.php`, `tools/ai/validate-ai-catalog.php`
- `tools/ai/validate-command-policy.php`
- `scripts/ai/ai-doc-check.sh`

## Contracts And Boundaries

- Canonical meaning lives in shipped `docs/ai/**` docs and capability sources;
  runtime templates stay thin and route back.
- Critical topics must be always-loaded or deterministically reachable from an
  always-loaded surface, per the Phase 0 matrix.
- Template sources are the only edit layer; rendered copies in this repo follow via
  render and are verified by drift validation, not hand-synced.
- Runtimes may differ structurally; semantic coverage must remain reviewably equivalent.
- Thinning slices must be net-negative on always-on lines and must prove coverage
  replacement before removal.
- Every slice ends with the validator gate (below); a failing gate blocks the next slice.

Per-slice validator gate:

```bash
php tools/ai/validate-install-surface.php
php tools/ai/validate-adapter-drift.php --fail-on-warn
php tools/ai/validate-ai-config.php
php tools/ai/validate-ai-catalog.php
bash scripts/ai/ai-doc-check.sh --check
```

## Todo Plan

Phase 0 — guard rails before any content change (merges B-1, B-1A, B-2, A-1, A-3):

- [x] P0: Phase 0.1 — build the critical-topic coverage matrix in shipped
      `docs/ai/integration-matrix.md` (or a referenced companion doc). Required topics:
      security, approval boundaries, task-context gate, source-of-truth, verification
      honesty, escalation, runtime limitations, shell-policy boundaries, plus
      workflow-critical primitives: clarification-before-action, stop-or-assume rules,
      preserved handoff payload, acceptance-criteria capture, verification-expectation
      capture, loop-guard / stop-after-N-failed-attempts (F-2), blocked-edit
      fallback (F-4), test-first ordering, and reference integrity (F-13). Map each
      topic to its guaranteed-load surface per runtime (Copilot, OpenCode, Claude).
- [x] P0: Phase 0.2 — classify all 139 shipped template surfaces as always-on critical,
      deterministic-load routing, optional support, or generated/install-only reference.
      Record the classification in `docs/ai/installed-files.md` companion guidance or
      the matrix doc. Include reachability rules that define when an installed file
      counts as dead.
- [x] P0: Phase 0.3 — add maintainer routing: change-type to template-source to
      validator lookup, in `docs/ai/validation.md` and/or `docs/ai/maintainer-guide.md`
      (source-repo-only doc; mark it as such per Track A chunk 2 wording rules).
- [x] P0: Phase 0.4 — establish the "start here / edit here / generated here" control
      entrypoint and make source-kit vs installed-target vs self-installed state
      explicit (A-1, A-2, A-9). Fix `docs/ai/maintainer-guide.md` claims that describe
      tracked source-repo surfaces as untracked installer outputs; every doc touching
      install-time surfaces must state which repository state it describes.
- [x] P0: Phase 0 exit gate — no thinning or new-family work starts until the matrix,
      classification, routing, and state-clarity docs exist and pass the validator gate.

Phase 1 — compact behavioral baseline (merges B-2A, B-3A; location resolved by graph):

- [x] P0: Phase 1.1 — add `templates/snippets/behavioral-baseline.snippet.md` covering:
      ask instead of guessing, simplicity over speculative abstraction, surgical
      changes, goal-driven verification, the explicit tradeoff note (bias toward
      caution, clarity, evidence over speculative speed), and the field-driven loop
      rules (F-2, F-4): verify an edit landed before continuing, never re-apply or
      re-append after a blocked or failed edit, stop and report after N (default 3)
      failed fix attempts on the same file.
- [x] P0: Phase 1.2 — wire the snippet into `core/AGENTS.template.md` and
      `core/copilot-instructions.template.md` with net-zero-or-negative line impact on
      each (AGENTS.template is already over soft max at 195 lines; remove or route out
      at least as much as is added).
- [x] P0: Phase 1 exit gate — validator gate passes; snippet renders into both surfaces;
      matrix updated to show the baseline's guaranteed-load path per runtime.

Phase 2 — canonical clarification and handoff contracts (merges C-1, C-1A, C-1B, C-2):

- [x] P0: Phase 2.1 — extend shipped `docs/ai/handoff-contract.md` (currently 24 lines)
      with the minimum preserved handoff payload: scope, clarified constraints,
      assumptions, acceptance criteria, likely files, verification expectation,
      unresolved questions, recommended next step, downstream-confirmation flag, and
      an explicit remaining-work section (F-7: "show remaining work if any" — a handoff
      or completion report without it is incomplete).
- [x] P0: Phase 2.2 — define the canonical clarification contract as a shipped
      capability or workflow source under `templates/capabilities/**` or
      `templates/workflows/**`: when to ask, maximum question count, stop-or-assume
      branch, surfacing unknowns, presenting multiple plausible interpretations when
      ambiguity is material, and simpler-path pushback against overcomplicated framing.
- [x] P0: Phase 2 exit gate — contracts exist canonically and are referenced from the
      matrix; no runtime-specific wrappers yet.

**Review-fix reconciliation (`docs/tickets/arch-todo-v0-6-review-fix-20260704-000002/plan.md`):**
A reviewer FAILed this Phase 0–2 slice after initial completion: rendered adapters
(`AGENTS.md`, `.github/copilot-instructions.md`) had not been regenerated from the
updated templates, the clarification-and-handoff capability was referenced by its
template-source path instead of its installed path, Phase 0.1 overstated
clarification/stop-or-assume as "covered", Phase 0.2's per-file classification was
missing, and package-lock/catalog surfaces had drifted. All five findings are now
fixed (rendered adapters contain `## Behavioral Baseline` via a user-approved manual
reconciliation since no installer command could regenerate them without an
unacceptable blast radius on unrelated owned-modified files; the capability is
installed at `docs/ai/capabilities/clarification-and-handoff/CAPABILITY.md`; matrix
status is now truthfully `partial`; `docs/ai/shipped-surface-inventory.md` carries the
per-file classification; `package-verify`/`validate-ai-catalog`/`generate-ai-catalog
--check` are clean). The Phase 0.1/0.2/Phase 1 exit/Phase 2 exit checkboxes above are
re-affirmed on this evidence, not merely left checked from the original pass.

Phase 3 — thinning, one runtime per slice (merges B-4, B-3, A-5; order set by graph):

- [x] P1: Phase 3.1 — reduce `templates/core/opencode.json` always-on instructions
      (currently 13 docs) to the minimum set that the Phase 0 matrix proves sufficient;
      demote the rest to deterministic-load routing. This is first because the graph
      shows it is the zero-edge, ungoverned surface. Done: `instructions[]` reduced
      13 -> 8 (`AGENTS.md`, `project-context.md`, `project/project-interaction.md`,
      `workflow.md`, `execution-protocol.md`, `ai-file-standards.md`,
      `approval-boundaries.md`, `adapter-contract.md`); no critical-topic matrix row
      cited the 5 removed docs (`tools/ai-search.md`, `tools/tool-map.md`,
      `tools/actions/search-evidence.md`, `tools/actions/preview-file.md`,
      `generated-artifacts.md`) as a coverage owner, so removal is coverage-neutral.
      Reachability closed by naming `docs/ai/tools/tool-map.md` and
      `docs/ai/generated-artifacts.md` by path from the always-on `AGENTS.md`/
      `AGENTS.template.md` ("Read This First" + tool-routing rule); `tool-map.md`'s own
      "See Also" section already named the other 3 tool docs. Rendered `opencode.jsonc`
      and `AGENTS.md` hand-edited to match the templates byte-for-byte (approved;
      installer render can't regenerate them without an unrelated blast radius, same as
      the Phase 0-2 review-fix). `docs/ai/integration-matrix.md` and
      `docs/ai/shipped-surface-inventory.md` updated to reflect the new classification;
      the matrix's `generated-artifacts.template.md` row — previously misclassified as
      always-on alongside 4 always-on-critical docs — is now correctly its own
      deterministic-load row. Verified: `validate-ai-config.php` and
      `validate-ai-catalog.php` clean; `validate-adapter-drift.php --fail-on-warn`
      exit 0 (pre-existing unrelated warnings only); `validate-install-surface.php` and
      `ai-doc-check.sh --check` show only pre-existing, untouched-by-this-slice
      failures (`bugfix.agent.md`/`build-config.agent.md`/`docs.agent.md`/
      `upgrade.agent.md` placeholder errors, `.opencode/skills/graphify/SKILL.md` over
      hard max, `docs/ai/repo-required-tools.md` drift from the pre-existing
      out-of-scope `95-dispatch.sh`/`test-ai-search.sh` changes) — confirmed via `git
      diff --stat` showing zero diff on each flagged file before this slice began.
- [x] P1: Phase 3.2 — thin `core/copilot-instructions.template.md` to guaranteed-load
      critical routing plus runtime-specific caveats, routing procedure back to shipped
      canonical docs. Copilot may stay slightly thicker than other runtimes where
      deterministic loading is weaker; record that in the matrix. Done: 139 -> 117
      lines. Removed 3 "Working Style" bullets fully redundant with `applyTo: "**"`
      instruction files that load deterministically regardless of which path is
      touched — `.github/instructions/copilot-script-enforcement.instructions.md`
      (script-registry wrapper preference) and
      `.github/instructions/ai-file-standards.instructions.md` (keep-this-file-focused,
      since its `applyTo` covers `.github/copilot-instructions.md` itself); the removed
      bullet's unique `docs/ai/script-registry.json` reference (not present in any `**`
      instructions file) was preserved by folding it into "Copilot-Specific: Shell
      Script Enforcement" instead of being dropped — caught by a `validate-ai-config.php`
      regression during this slice and fixed before completion. Collapsed
      "Evidence-First Execution" (13 -> 1 lines) and "Core Workflow" (12 -> 1 lines)
      from verbatim restatements of `docs/ai/execution-protocol.md` +
      `.github/instructions/execution-protocol.instructions.md` and `docs/ai/workflow.md`
      into routing sentences. Left "Quality Bar" release/migration/prototype bullets and
      all matrix-cited sections (Behavioral Baseline, Hard Stops, Limits,
      `<APPROVAL_REQUIRED_CHANGES>`) untouched — no `**`-scoped duplicate exists for
      that content, so removing it would have been a real coverage loss, not
      redundancy. `docs/ai/integration-matrix.md` records the thinning rationale.
      Rendered `.github/copilot-instructions.md` hand-edited to match byte-for-byte
      (same approved approach as Phase 0-2 and Phase 3.1); full-file diff against the
      template shows only expected `<PLACEHOLDER>` resolution. Verified:
      `validate-ai-config.php` and `validate-ai-catalog.php` clean;
      `validate-adapter-drift.php --fail-on-warn` output is byte-identical before/after
      this slice (diffed via `git stash` scoped to the 3 touched files — confirmed zero
      new findings, same pre-existing warnings only); `validate-install-surface.php` and
      `ai-doc-check.sh --check` show only the same pre-existing failures already
      recorded in Phase 3.1 (`.opencode/skills/graphify/SKILL.md` over hard max,
      `bugfix.agent.md`/`build-config.agent.md`/`docs.agent.md`/`upgrade.agent.md`
      placeholder errors, agent/root soft-max warnings) — none touch
      `copilot-instructions.md` or its template.
- [x] P1: Phase 3.3 — add the runtime surface matrix (one maintained shipped doc
      describing practical Copilot/OpenCode/Claude differences without implying parity),
      including the explicit semantic-parity review methodology (B-10): how a reviewer
      compares pre-thinning and post-thinning topic coverage when file structures
      diverge across runtimes. Done: the existing "Guaranteed-Load Surfaces Per
      Runtime" table/notes in `docs/ai/integration-matrix.md` is now explicitly framed
      as the maintained runtime surface matrix (A-5); a new "Semantic-Parity Review
      Methodology (B-10)" section documents the 7-step reviewer procedure (identify
      touched matrix rows, confirm load-path class holds per runtime, diff
      covered/partial/gap status, require explicit not silent asymmetry per
      `adapter-contract.md`'s no-false-parity rule (A-12), compare by topic content
      when file structures diverge, run the validator gate, record the outcome in the
      matrix). `docs/ai/adapter-contract.md` Contract Rules cross-references the new
      section so the existing "document the fallback instead of implying parity" rule
      has a concrete review procedure attached. No template source exists for either
      doc (both are shipped canonical docs consumed directly, not rendered), so no
      render/hand-sync step was needed. Also kept and verified the pre-existing
      uncommitted working-tree correction to these same two files (CLAUDE.md has no
      template/pack-registry entry and is hand-maintained directly, not rendered;
      `.opencode/opencode.json` vs root `opencode.jsonc` distinction) — confirmed
      accurate against `tools/ai/install/packs.php` (no CLAUDE.md entry) and the
      `.opencode/opencode.json` file contents (graphify plugin registration only)
      before building on it, per user approval. Verified: `validate-install-surface.php`
      and `validate-install-surface.php --strict` both exit 1, but only on the same
      pre-existing `.opencode/skills/graphify/SKILL.md` hard-max ERROR and soft-max
      WARNs already recorded in Phase 3.1/3.2 (none reference the 2 files touched
      here); `validate-adapter-drift.php --fail-on-warn` exits 1 on pre-existing
      `should reference docs/ai/AI-GUARDRAILS.md`-class warnings, confirmed
      byte-identical before/after this slice via a `git stash` scoped to the touched
      files (zero new findings); `validate-ai-config.php` and `validate-ai-catalog.php`
      exit 0 clean; `ai-doc-check.sh --check` exits 1 with output identical before/after
      this slice (only a timing string differs) — same pre-existing failures already
      recorded in Phase 3.1/3.2, unrelated to the 2 files touched here. A reviewer pass
      on this slice caught this note originally mis-stating those pre-existing exit
      codes as "clean"/"exit 0" and caught a self-contradiction this slice introduced
      at `docs/ai/maintainer-guide.md:92` (claimed `CLAUDE.md` was a `templates/core/`
      master copy, contradicting the CLAUDE.md-has-no-template fix at line 26 of the
      same file); both are now corrected.
- [x] P1: Phase 3 exit gate — each thinning slice proves critical-topic AND
      clarification/handoff-primitive coverage after the change; a failed proof
      restores the thicker baseline for that runtime only. Done: applied the Phase
      3.3 Semantic-Parity Review Methodology to Phases 3.1/3.2 together. Step 1
      (identify touched rows): the OpenCode `instructions[]` removals (Phase 3.1) and
      Copilot baseline removals (Phase 3.2) do not name any of the 17 rows in the
      Critical-Topic Coverage Matrix as their coverage owner — reconfirmed by re-
      reading every row's Copilot/OpenCode cell against the removed content. Step 2
      (load-path class holds): reconfirmed live — `.github/instructions/copilot-script-enforcement.instructions.md`
      and `.github/instructions/execution-protocol.instructions.md` still carry
      `applyTo: '**'`; `.github/instructions/ai-file-standards.instructions.md` still
      explicitly lists `.github/copilot-instructions.md` in its `applyTo`; `AGENTS.md`
      still names `docs/ai/tools/tool-map.md` and `docs/ai/generated-artifacts.md` by
      path, and `docs/ai/tools/tool-map.md`'s own "See Also" still names the other 3
      removed tool docs (`ai-search.md`, `actions/preview-file.md`,
      `actions/search-evidence.md`) — reachability chain intact. Step 3 (status diff):
      no row's `Status` cell regressed; the only `partial` rows
      (Clarification-before-action, Stop-or-assume rules) were already `partial`
      before Phase 3 and are unrelated to the Phase 3.1/3.2 removals, not a new gap.
      Step 4 (explicit asymmetry): Claude's advisory-only shell-policy note and
      Copilot's intentionally-thicker baseline are both still stated in the matrix,
      not silently dropped. Steps 5-6: no file-structure divergence introduced new
      ambiguity; validator gate re-run clean/pre-existing-only (below). Step 7:
      recorded here. Verified: `validate-install-surface.php` exit 1 (same pre-existing
      graphify-skill hard-max ERROR only); `validate-adapter-drift.php --fail-on-warn`
      exit 1, byte-identical to the Phase 3.3 post-fix run (`diff` exit 0 — zero new
      findings); `validate-ai-config.php` and `validate-ai-catalog.php` exit 0 clean.

Phase 4 — PRD/task workflow family (merges C-3, C-3A, C-4, C-4A, C-5):

- [x] P1: Phase 4.1 — add the PRD and task-generation workflow family as new entries in
      `templates/workflows/**`, reusing the clarification contract from Phase 2 rather
      than restating it. Include the two-phase pattern: parent tasks or high-level plan
      first, pause for confirmation where appropriate, then expand. Encode the F-10
      splitting rules: multi-project plans split per project starting with the most
      logical one, and each generated task is as close as possible to one unit of work
      verifiable with the user or by focused test coverage. Done: reuse check found
      ~70-80% overlap with the existing `architecture-plan` / `plan-slice` /
      `architecture-plan-writer` planning chain (same bounded-plan-under-docs/tickets
      shape); per the `>=75%` rule, flagged this to the user before building, who chose
      the thinnest non-duplicative option: one new
      `templates/workflows/prd-and-tasks.md` (49 lines) that runs the
      `clarification-and-handoff` capability on a raw idea, encodes the F-10
      per-project-split and single-verifiable-unit rules, then explicitly hands off to
      `architecture-plan-writer` for persistence — it does not restate the plan file
      format. The two genuinely-missing primitives (raw-idea clarification front-end;
      the parent-tasks-first pause-then-expand mechanic) were added as a compact
      "Two-Phase Mode" section (23 lines) on `architecture-plan-writer.md` instead of
      reinventing task-writing: parent-tasks-only mode writes only the Todo Plan's
      P0/P1/P2 one-liners plus a `## Status` pause note, expand mode later fills the
      remaining sections in place on the same file. `templates/workflows/**` is
      registered as a whole directory in `tools/ai/install/packs.php` (not per-file),
      so the new workflow needed no packs.php change; its 4 rendered projections
      (`.github/prompts/prd-and-tasks.prompt.md`, `.github/skills/prd-and-tasks/SKILL.md`,
      `.opencode/skills/prd-and-tasks/SKILL.md`, `.opencode/commands/prd-and-tasks.md`)
      were hand-authored to match the template byte-for-byte (verified via `diff`,
      same approved approach as every prior phase in this program) since this repo is
      self-installed and full installer re-render still carries the same unrelated-
      owned-file blast radius risk documented in the Phase 0-2 review-fix, regardless
      of git-clean status. The `architecture-plan-writer` two-phase-mode addition was
      likewise hand-synced into `.github/agents/architecture-plan-writer.agent.md` and
      `.opencode/agents/architecture-plan-writer.md` — confirmed via `diff` that only
      the pre-existing frontmatter/Enforcement-Boundary rendering differences remain,
      the new body section is byte-identical. This slice does not touch
      `templates/skills/**`/`templates/commands/**` (that stays gated by Phase 4.3);
      `templates/workflows/**` already has an established, reachable render path used
      by all 17 prior entries, so no new reachability question was raised. User-
      approved generator run: `php tools/ai/generate-ai-catalog.php` (no `--check`) to
      regenerate `packages/ai-universal-rules/catalog.json`, `docs/ai/catalog.md`, and
      `packages/ai-universal-rules/docs/BROWSE.md`, which had drifted from the new
      file (confirmed via `ai-doc-check.sh --check` before/after); the regen also
      incidentally fixed a pre-existing missing `graphify` opencode-skill catalog row
      as a side effect of the same full-scan generator, not a manual edit. Verified:
      `validate-ai-config.php`, `validate-ai-catalog.php`, `validate-catalog-drift.php`
      all exit 0 clean; `validate-install-surface.php` exit 1 on the same pre-existing
      graphify-skill hard-max ERROR plus 3 new soft-max WARNs from the two-phase-mode
      addition (`architecture-plan-writer.agent.md` 231/220, `.md` OpenCode variant
      260/240, template 259/240 — expected and acceptable, since Phase 4 is additive
      and not bound by Phase 3's thinning net-negative-line rule);
      `validate-adapter-drift.php --fail-on-warn` exit 1 with only the expected new
      warnings (the new workflow file lacks references to
      `project-context.md`/`workflow.md`/`AI-GUARDRAILS.md`, the same pre-existing
      warning class every other workflow file already carries — confirmed via `diff`
      against the pre-slice baseline, no unexpected findings); `ai-doc-check.sh --check`
      generated-artifacts section fully clean after the regen.
- [x] P1: Phase 4.2 — map runtime wrappers and fallback rules: where structured handoff
      metadata is possible, where prose-only fallback applies; record in
      `docs/ai/integration-matrix.md` and `docs/ai/adapter-contract.md`. Done: added
      a "Handoff Mechanism Per Runtime" table to `docs/ai/integration-matrix.md`,
      grounded in a verified external fetch of VS Code's Custom Agents documentation
      (code.visualstudio.com/docs/copilot/customization/custom-agents, fetched
      2026-07-04): Copilot's `.github/agents/*.agent.md` supports structured
      `handoffs:` frontmatter (`label`/`agent`/`prompt`/`send`/`model`) rendering a
      clickable handoff button; OpenCode and Claude have no equivalent field (grep-
      confirmed no `handoffs:` key exists in any shipped `.opencode/agents/**` file
      or in VS Code's own documented Claude sub-agent frontmatter fields). Recorded
      the fallback rule (Chunk 5 / C-5): the prose "Recommended next step" sentence
      in `docs/ai/handoff-contract.md` is the mandatory baseline on every runtime;
      structured metadata is additive only, never a replacement — this is the
      false-parity guard for a future Phase 6.15 that actually adds `handoffs:` to
      specific agent pairs. Cross-referenced from `docs/ai/adapter-contract.md`
      Contract Rules. No shipped agent template emits `handoffs:` yet in this slice
      (design/mapping only, per the phase's own scope). Fixed one validator
      regression caught during this slice: an initial draft referenced `.claude/agents`
      as a backtick path, which `validate-ai-config.php` correctly flagged as a
      broken path reference since this kit ships no such folder; reworded to
      describe the format without asserting a non-existent shipped path. Verified:
      `validate-ai-config.php` and `validate-ai-catalog.php` exit 0 clean;
      `validate-adapter-drift.php --fail-on-warn` and `validate-install-surface.php`
      byte-identical to the pre-slice baseline (`diff` exit 0 on both — zero new
      findings).
- [x] P2: Phase 4.3 — only after reachability rules from Phase 0 exist: add skills or
      commands wrappers under `templates/skills/**` / `templates/commands/**` where a
      runtime genuinely needs them. Each new wrapper must name its guaranteed
      reachability path or it is not added. Done (evaluated, none added): the
      Phase 4.1 `prd-and-tasks` capability already gets full multi-runtime
      reachability from the established `templates/workflows/**` mechanism (renders
      to `.github/prompts/*.prompt.md`, `.github/skills/*/SKILL.md`,
      `.opencode/skills/*/SKILL.md`, `.opencode/commands/*.md` — the same 4-surface
      projection used by all 18 prior workflow entries, confirmed in
      `docs/ai/shipped-surface-inventory.md`'s workflows/** row). No runtime lacks a
      reachable entry point for it, so no standalone `templates/skills/**`/
      `templates/commands/**` wrapper is genuinely needed; adding one would violate
      this phase's own reachability-first gate by creating a duplicate route to the
      same capability. `templates/skills/**` and `templates/commands/**` stay at
      their existing 2/4-file counts.
- [x] P1: Phase 4 exit gate — no wrapper duplicates contract text; each new file has a
      reachability entry; validator gate passes. Done: `prd-and-tasks.md` references
      (not restates) `clarification-and-handoff` and `architecture-plan-writer`; its
      reachability entry is recorded in `docs/ai/shipped-surface-inventory.md`'s
      workflows/** row (Phase 4.3 confirmed no additional wrapper is needed or
      added). Full validator gate re-run at exit: `validate-ai-config.php`,
      `validate-ai-catalog.php`, `validate-catalog-drift.php` exit 0 clean;
      `validate-install-surface.php` and `validate-adapter-drift.php --fail-on-warn`
      show only the same pre-existing findings plus the Phase 4.1-disclosed new
      soft-max/missing-reference warnings, confirmed unchanged since Phase 4.2's
      `diff` checks.

Phase 5 — consolidation and maintenance loop (merges rerouted A-6, A-7, A-10..16, A-15, B-8, B-9, B-11, B-12, C-6..9):

- [x] P2: Phase 5.1 — audit the 22 `templates/instructions/*.instructions.md` sources;
      consolidate or reduce at the template layer only, with an audit note before any
      removal and coverage proof after. Use mechanical duplication evidence for this audit and for every thinning slice:
      `jscpd` with JSON reporter and a threshold gate for verbatim code/markdown
      duplication across `templates/**` and shipped `docs/ai/**`, PMD CPD as an
      optional differential sweep, and a MinHash/heading-chunk pass for paraphrased
      instruction prose. This mechanizes the repository's >=75% overlap rule
      (currently agent judgment) and produces before/after duplication percentages as
      thinning proof. Do not use the abandoned phpcpd. Done: `jscpd` was not
      installed; user approved `npx --yes jscpd` (on-demand fetch, no persistent
      install, confirmed version 5.0.11). Ran it against all 22 instruction
      templates (`--min-lines 3 --min-tokens 10`, JSON + console reporters):
      **0 exact clones, 0.00% duplicated lines**. Note on tool scope, reported
      honestly: jscpd's markdown format only tokenizes fenced code blocks (5 of the
      22 files contain a bash fence; the other 17 produced zero comparable
      "sources"), so this result is real evidence against verbatim *code-block*
      duplication, not a full prose-duplication measurement — PMD CPD would hit the
      same code-fence-only limitation for markdown, and no MinHash/heading-chunk
      tool exists in this environment, so the paraphrased-prose sweep was done as a
      manual qualitative read-through instead (documented as a gap, not silently
      skipped). Qualitative findings: applyTo globs and rule content across the 22
      files are legitimately distinct (registry rules vs. search-tool contract vs.
      general tool preference vs. per-stack instruction sets); the closest
      candidates (`ai-scripts.instructions.md`'s registry-doc-alignment bullet vs.
      `ai-tooling.instructions.md`'s registry-PHP-source-alignment bullet) address
      different alignment axes (docs-to-registry vs. PHP-source-to-registry), not a
      genuine `>=75%` duplicate, so no merge was made. One structural observation
      recorded for future reference, not acted on in this slice: 8 of the 22 files
      carry `applyTo: '**'` (`approval-boundaries`, `base`, `context-gate`,
      `copilot-script-enforcement`, `execution-protocol`, `security`, `targets`,
      `tools` — all always-on for Copilot per the Phase 3 runtime matrix) totaling
      ~408 lines always-on; re-auditing that cluster is a candidate for a future
      bounded Phase-3-style thinning slice, not this audit phase. Conclusion: no
      unsafe or `>=75%`-triggering duplication found; no consolidation was safely
      actionable beyond what Phase 3 already completed.
- [x] P2: Phase 5.2 — strengthen `templates/capabilities/README.md` (33 lines) into an
      operational task-to-capability router; strengthen
      `core/project-context.template.md` and `core/project/**` starter docs (this single
      slice replaces both Track A chunk 4 and Track B chunk 6 — deduplicated). Done:
      research clarified the router target: `packages/ai-universal-rules/templates/capabilities/README.md`
      (34 lines) is a source-repo-only contributor doc referenced only from
      `packages/ai-universal-rules/docs/CAPABILITY-MODEL.md` (never installed —
      confirmed absent from `tools/ai/install/packs.php`); the actual shipped,
      agent-read router is `docs/ai/capabilities/README.md` (self-sourced in
      `packs.php`, `source == target`, only 3 lines). Strengthened that file
      (3 -> 29 lines): a 9-row task-signal-to-capability table covering all 9
      shipped capabities, a composition-order rule (`project-context` first,
      task-specific capability, `verify-change` last), and a "when to add a new
      capability" note citing the `>=75%` overlap rule. Left
      `templates/capabilities/README.md` untouched — its contributor-facing content
      is accurate and serves a different audience; forcing it into router shape
      would duplicate the real router, not strengthen anything. Cross-referenced
      the router from `templates/core/project-context.template.md` §11 (and hand-
      synced the rendered `docs/ai/project-context.md`, verified via `diff` —
      placeholder differences only) and from `templates/core/project/README.md`
      (hand-synced this repo's already-materialized `docs/ai/project/README.md`
      too, even though its `skip-if-exists` merge strategy means the installer
      itself would never re-render it). Fixed one validator regression during the
      slice: an early draft of the router's "Adding A New Capability" section used
      backtick-quoted bare filenames (`CAPABILITY.md`, `checklist.md`, etc.) that
      `validate-ai-config.php` correctly flagged as broken path references (same
      class of finding as the Phase 4.2 `.claude/agents` fix); reworded to describe
      the file roles without asserting unresolvable literal paths. User-approved
      generator run (repeat of the Phase 4.1 pattern): `php tools/ai/generate-ai-catalog.php`
      to resync `catalog.json`/`docs/ai/catalog.md` after the router's description
      text changed. Verified: `validate-ai-config.php`, `validate-ai-catalog.php`
      exit 0 clean; `validate-adapter-drift.php --fail-on-warn` and
      `validate-install-surface.php` byte-identical to the Phase 4.2 baseline
      (`diff` exit 0, zero new findings); `ai-doc-check.sh --check`
      generated-artifacts section clean after the regen (only the expected
      phantom-stub-surfaces file-count increase from Phase 4.1's new files).
- [x] P2: Phase 5.3 — add example-driven anti-pattern guidance (hidden assumptions,
      overcomplication, drive-by changes, weak success criteria) as a shipped examples
      surface referenced from the behavioral baseline. Done: added
      `packages/ai-universal-rules/templates/snippets/anti-pattern-examples.md`
      (56 lines, 4 examples matching each Behavioral Baseline rule) — placed in
      `templates/snippets/` because it is already whole-directory registered in
      `tools/ai/install/packs.php` (target `docs/ai/snippets/`), so it ships with
      zero new pack-registration or generated-`docs/ai/installed-files.md` changes
      (that file carries a `GENERATED — DO NOT EDIT` header written only by the
      full-install manifest step, which this program has consistently avoided
      running). Added one pointer line to the Behavioral Baseline bullet list in
      the canonical snippet source, both templates (`AGENTS.template.md`,
      `copilot-instructions.template.md`), and both rendered outputs (`AGENTS.md`,
      `.github/copilot-instructions.md`) — verified via `diff` that only expected
      placeholder/out-of-band-graphify differences remain against the templates.
      Also fixed a pre-existing drift found while touching this folder:
      `docs/ai/snippets/behavioral-baseline.snippet.md` was missing from this
      repo's rendered `docs/ai/snippets/` mirror since Phase 1.1 added the source
      file (`required: false` in its pack entry let this slip past validation
      silently); synced it now, byte-identical via `diff`. Classified the new file
      in `docs/ai/shipped-surface-inventory.md` as `deterministic-load`, not
      `generated-or-install-only` like its snippet siblings, because it is the
      only file in that folder actually named by path from an always-on surface
      (the Behavioral Baseline itself), making it reachable at task time, not just
      an install-time reference copy. Verified: `validate-ai-config.php`,
      `validate-ai-catalog.php` exit 0 clean; `validate-adapter-drift.php
      --fail-on-warn` byte-identical to the Phase 5.2 baseline (zero new
      findings); `validate-install-surface.php` shows only the same pre-existing
      graphify hard-max error plus the expected +1 line-count on two already-over-
      soft-max warnings (`AGENTS.md`, `AGENTS.template.md`); `ai-doc-check.sh
      --check` generated-artifacts section stayed clean with no regeneration
      needed (the catalog generator does not itemize raw snippet files).
- [x] P2: Phase 5.4 — document the permanent maintenance loop in shipped
      `docs/ai/validation.md` / `docs/ai/adapter-contract.md`: change template source,
      re-prove coverage and reachability, re-render, run validator gate. Include the
      dead-file review model so weakly referenced installed files are caught. Done:
      added a "Permanent Maintenance Loop" section to `docs/ai/validation.md`
      (5-step cycle: change source, re-prove coverage via the Phase 3.3 Semantic-
      Parity Review Methodology, re-prove reachability, re-render/hand-sync, run the
      gate) and a "Dead-File Review Model" subsection operationalizing
      `docs/ai/integration-matrix.md`'s existing Reachability Rules: search by exact
      path before treating a file as dead (a maintainer-doc reference still counts,
      using this program's own `templates/capabilities/README.md` finding from
      Phase 5.2 as the worked example), record the kept-or-removed reasoning in the
      same slice, and treat `graphify update .` edge presence as secondary signal
      only (ties into Phase 5.5). Placed in `docs/ai/validation.md` (not
      `docs/ai/adapter-contract.md`, which already covers adapter-specific drift
      rules and is closer to its `ai-file-standards.md` line budget); cross-linked
      from there instead of duplicated. Verified: `validate-ai-config.php`,
      `validate-ai-catalog.php` exit 0 clean; `validate-adapter-drift.php
      --fail-on-warn` and `validate-install-surface.php` byte-identical to the
      Phase 5.3 baseline (zero new findings).
- [ ] P2: Phase 5.5 — run `graphify update .` after each merged phase so the knowledge
      graph tracks the restructured surfaces, and use edge presence as a secondary
      reachability signal in reviews. Add shipped guidance for multi-scope installed
      targets (F-8): incremental `graphify update <scope> --no-cluster`, then
      `merge-graphs`, then `cluster-only`; note that doc/config scopes need semantic
      re-extraction with a backend key.
- [ ] P2: Phase 5.6 — tighten routing-role overlap across the shipped docs
      `docs/ai/agents.md`, `docs/ai/AGENTS-MANIFEST.md` (ship-status: unknown — verify
      first), and `docs/ai/workflow.md` so each has one clear job (A-6); separate
      user-facing navigation from maintainer-facing internals in the touched docs (A-7).
- [ ] P1: Phase 5.7 — portable policy compilation (B-11, strengthened by external
      gap-list validation and by field evidence F-1/L-2 that hand-authored per-runtime
      policy drifts): define one canonical policy source (the existing
      `docs/ai/command-policy.tiers.yaml` plus the script-access manifest are the
      proto-version) and a compile step that renders it to each runtime's format —
      OpenCode `permission` blocks, Copilot hook policy, Claude settings — with drift
      detection between compiled outputs and the source. Decision-and-design in this
      program; compiler implementation needs separate approval. Check:
      `php tools/ai/validate-command-policy.php`.
- [ ] P2: Phase 5.8 — decide whether additional maintained index surfaces are needed to
      keep shipped-surface inventory and runtime differences accurate over time (A-14);
      add a contributor quickstart layer only if routing gaps remain after Phases 0 and
      5.6, and only for shipped docs (A-16 — README-level routing in this source repo
      is out of program scope under the shipped-files constraint).
- [ ] P2: Phase 5 exit gate — final program review confirms the three tracks reinforce
      each other; `bash scripts/ai/ai-verify.sh .` as the closing broad check.

Phase 6 — shipped-enforcement reliability, field-failure remediation (Track F; can run
in parallel with Phases 0-2 because it touches hook scripts and conventions, not the
instruction-thinning surfaces):

- [ ] P0: Phase 6.1 — fix the hook error path (F-1): shipped pre-tool-use hook sources
      (`templates/github/hooks/scripts/tool-guardian.sh`, `tool-guardian.ps1`,
      `command-policy.compiled.sh`) must never hard-deny read-only commands because the
      hook itself errored. Define the contract: on internal hook error, allow read-only
      command classes, deny mutating classes, and emit a clear remediation message.
      Add a regression test for the errored-hook path. Checks:
      `shellcheck` on touched scripts, focused hook test, per-slice validator gate.
- [ ] P0: Phase 6.2 — agent duty vs permission parity (F-3, F-5): audit every shipped
      agent template (`templates/core/agents/**`, `templates/optional/agents/**`)
      against the permissions it ships with; fix templates where a declared duty
      (for example move or edit files) is blocked by its own boundary; convert agents
      to skills where the boundary defeats the agent's purpose. Record the
      duty-vs-permission check in the Phase 0 classification so future agents cannot
      ship with this mismatch.
- [ ] P0: Phase 6.3 — blocked-edit fallback protocol (F-4): add to the behavioral
      baseline and the relevant agent templates: verify write success after every edit,
      never re-append content after a blocked or failed edit, stop and report with the
      exact blocked path and permission rule after repeated failure. This also encodes
      the anti-rewrite-loop rule (F-2) as an enforceable stop condition rather than
      advice.
- [ ] P1: Phase 6.4 — ship write-agent code-quality conventions (F-7) in
      `templates/core/project/conventions.md` (user-owned starter) and reference them
      from write-tier agent templates: max 3 returns per method, max 2 levels of
      nested conditionals, cyclomatic complexity below 15, max 5 parameters, files
      growing past ~500 LOC are split into new classes and flagged for refactor,
      readability first — add lines when it improves clarity but never at the cost of
      performance, and always report remaining work.
- [ ] P1: Phase 6.5 — ship the test-grouping rule (F-7) in
      `templates/instructions/testing.instructions.md`: new tests join the appropriate
      existing group or suite; create a new group only when none fits.
- [ ] P1: Phase 6.6 — context-economy defaults (F-6): shipped ignore surfaces must
      exclude graph and pack artifacts by default — add `graphify-out/**` and
      `.repomix-context/**` (plus existing `.ai-backups/**`, `.ai-logs/**`) to the
      shipped `.repomixignore` guidance, `templates/core/opencode.json` watcher ignore,
      and search-tool default excludes, so a 200k-token graph artifact can never
      dominate a context pack in installed targets.
- [ ] P0: Phase 6.7 — evidence-writer integrity (L-4, L-5): fix
      `scripts/ai/post-tool-use.sh` (and `common.sh` emitters) so `details.raw` is
      valid JSON, `execution.status`, `exit_code`, `authorization.decision`, and
      `failure.category` are populated, `trace_id`/`session_id` are never `unknown`
      when a session exists, `actor.id` names the real tool instead of `common`, and
      `guard.done` is guaranteed via trap even on crash. Add a log-schema validation
      test that parses every emitted line. Checks: focused shell test on emitters,
      `shellcheck`, per-slice validator gate.
- [ ] P0: Phase 6.8 — documented-command contract check (L-2): add a validation step
      that every command and flag documented in shipped docs, instructions, and
      capability files actually parses against the shipped script versions (start with
      `ai-doc-check.sh --check`, `ai-search.sh` modes, `ai-verify.sh`). Wire it into
      install verify so docs/script version skew is caught at install time, not in
      production use. P2 follow-up within the same contract: extend from command flags to symbol-level
      references, flagging instruction files that reference renamed or removed
      functions, scripts, or config keys.
- [ ] P1: Phase 6.9 — verification actionability and persistent-red handling (L-1):
      shipped verify and doc-check output must name each failing check with a
      remediation command, not just a failure count; post-install setup must end green
      or emit an explicit triage list; shipped workflow guidance must treat an
      identical failure count repeating across sessions as an escalation trigger, not
      ambient noise.
- [ ] P1: Phase 6.10 — usage-error UX and retry discipline (L-3): wrappers print
      corrective usage on argument errors; the behavioral baseline gains the rule
      "never retry an identical failed command unchanged — change arguments, mode, or
      approach, or stop and report" (generalizes the F-2 loop guard from edits to
      commands).
- [ ] P1: Phase 6.11 — context-pack path robustness (L-6): make the shipped pack
      pipeline resolve its output/bundle directories from repository-relative defaults
      that survive installed-target layouts (the target failed under `storage/tmp/`),
      and fail with a path-diagnosis message instead of `no context bundles generated`.
- [ ] P0: Phase 6.12 — ai-verify scope contract (L-7): define one canonical exclusion
      list that `ai-verify.sh` (and every scanning script) must honor regardless of
      parameters — at minimum `.ai-backups/**`, `.ai-logs/**`, `.git/**`, `vendor/**`,
      `node_modules/**`, `graphify-out/**`, `.repomix-context/**`, `docs/ai/generated/**`
      — sourced from one shared config, not per-script copies. Make scoping
      deterministic: identical params must always scan the identical file set. Add a
      `--list-targets` (dry-run) mode that prints exactly what would be scanned so
      users can debug scope before running. Regression test: run with the params the
      target used and assert backup folders are never visited.
- [ ] P0: Phase 6.13 — no-hang-after-completion contract (L-8): audit `ai-verify.sh`,
      the shell test harness, and wrapper/emitter chain for lingering children, open
      pipe FDs, and trailing `wait`/`read` calls; every shipped script must exit
      cleanly when its work is done. Add a no-hang regression test that runs each
      shipped entry script under `timeout` and asserts natural exit (exit code
      not 124). Coordinate with Phase 6.7 since the evidence-writer FD handling is a
      prime suspect.
- [ ] P0: Phase 6.14 — validator portability and target-awareness (F-9): shipped
      scripts must not require GNU `timeout` — detect and fall back to `gtimeout` or a
      portable guard, and degrade to a warning (never a hard refusal) when no bound is
      available; `validate-ai-catalog.php` must skip or scope checks that depend on
      source-repo-only files (`packages/ai-universal-rules/manifest.json`) when run in
      an installed target; `validate-ai-config.php` must separate pre-existing missing
      surfaces (report the class once, with remediation) from findings caused by the
      current change; no shipped doc may reference a helper that does not ship
      (enforced by the Phase 6.8 documented-command contract check).
- [ ] P1: Phase 6.15 — agent ergonomics and tool-routing fixes (F-10, F-11, F-12):
      grant the reviewer agent PR read access (`gh-pr-context.sh` tier) in shipped
      templates; route large/generated file reads through `preview-file.sh --range`
      instead of raw pagers in researcher guidance; add default handoff chains to
      shipped agent templates (architect -> plan-writer, implementer -> reviewer) and
      emit runtime handoff metadata (VS Code `handoffs:` frontmatter) where the runtime
      supports it, prose fallback elsewhere (respecting Phase 4.2's fallback model);
      require review/research launchers to state the goal up front; add the
      stale-evidence reporting shape (F-12) to shipped evidence rules; move
      stack-specific context commands (for example the Laravel artisan pair) into
      `templates/core/project-stack.template.md`.
- [ ] P0: Phase 6 exit gate — the errored-hook regression test passes; no shipped
      agent declares a duty its own permissions block; ignore defaults verified by
      inspecting a fresh pack listing; every emitted evidence line parses as valid
      JSON with populated status fields; every documented command parses against its
      shipped script; `--list-targets` proves backup/artifact folders are never
      scanned; the no-hang test passes for every shipped entry script.

### Debug And Helper Steps (User-Facing, Ship With The Fixes)

Until Phases 6.12 and 6.13 land, and afterwards as diagnosis guidance, ship these
steps in the troubleshooting surface (and use them to reproduce during implementation):

Reproduce and diagnose a hang (L-8) — run any suspect script under a timeout and
capture whether it exits naturally:

```bash
timeout 60 bash scripts/ai/ai-verify.sh . ; echo "exit=$?"
```

`exit=124` means the script hung after (or during) its work. Then find what it left
behind:

```bash
ps -ef | rg "ai-verify|repomix|scc|node" | rg -v rg
```

Kill leftovers and note the parent script in the report.

Diagnose scope leak (L-7) — before trusting a red verify, check what it actually
scanned. Until `--list-targets` exists, compare the failure list against the
exclusion set: any finding whose path starts with `.ai-backups/`, `.ai-logs/`,
`graphify-out/`, `.repomix-context/`, `vendor/`, `node_modules/`, or
`docs/ai/generated/` is a scope bug, not a real failure — report it as L-7, do not
"fix" the flagged file.

Confirm param-dependent behavior: run the same script twice with identical params and
diff the reported file counts; a difference is a determinism bug (L-7) worth attaching
to the report.

Workaround for stuck sessions: CTRL+C is currently required; after CTRL+C, re-run the
narrowest variant of the check (single file or single test) to still get usable
evidence, and record `Not run: <full command> — hangs after completion (L-8)`.

Phase 7 — shipped observability program (Track O; builds on Phase 6.7's evidence-writer
fixes; user-specified three-layer setup):

- [ ] P1: Phase 7.1 — ship an OpenCode audit plugin template (optional pack under
      `packages/ai-universal-rules/templates/**`, installed to `.opencode/plugins/`):
      hooks `tool.execute.before`, `tool.execute.after`, and the event stream
      (`command.executed`, `file.edited`, `permission.asked`, `permission.replied`,
      `session.created`, `session.error`, `session.idle`, `session.diff`,
      `todo.updated`), writing NDJSON to an env-configurable dir
      (`OPENCODE_AUDIT_DIR`, default `~/.local/share/opencode/audit/actions.ndjson`)
      with built-in secret redaction (bearer tokens, API keys, GitHub tokens, AWS keys,
      secret-named object keys). The user-provided `audit-log.js` is the reference
      implementation. Every emitted line must satisfy the same valid-JSON contract as
      Phase 6.7.
- [ ] P1: Phase 7.2 — ship native OpenCode observability guidance as a shipped doc:
      `opencode stats [--days N] [--models N] [--project]` for token/cost,
      `opencode session list --format json`, `opencode export <id> [--sanitize]` for
      session evidence (prefer `--sanitize` when sharing), `opencode db path` and the
      `~/.local/share/opencode/log|project` paths for raw logs. Include the cost
      limitation honestly: cost attaches to the LLM turn around a command, not to the
      command itself — correlation shape: LLM turn -> tool calls -> bash command ->
      file edits -> next turn cost.
- [ ] P1: Phase 7.3 — unify the two evidence pipelines: the OpenCode audit NDJSON and
      the kit's `.ai-logs/tool-usage.jsonl` (Copilot/hook side) must share field
      naming (timestamp, actor, tool, status, exit code, decision) so one `jq` query
      set works across both, and both are documented as feeding the `.ai-logs/`
      evidence root. Extend the Copilot-side writer (Phase 6.7) with what the log
      analysis proved missing: real per-event status, exit codes, decisions, and
      session correlation — this is what makes the existing 862-event log style
      actually diagnosable.
- [ ] P2: Phase 7.4 — optional dashboard guidance (documented, not default-installed):
      MLflow OpenCode plugin (`@mlflow/opencode` in `opencode.json` `plugin` list with
      `MLFLOW_TRACKING_URI`/`MLFLOW_EXPERIMENT_ID`) for tracing and token/cost
      dashboards, and `ccusage opencode` (`daily`/`session`/`monthly --json`) for local
      usage reporting — both marked experimental/external, approval-gated installs.
- [ ] P2: Phase 7.5 — provenance fields (Track G): extend the unified evidence
      contract with provenance so future forensics can answer "was this the agent or
      me, and what context did it have": session id, model id, kit/rules version (for
      example a hash of AGENTS.md plus the installed kit version), and task/prompt
      reference on every mutating tool event. Also add per-task token spend and
      tool-call error-rate fields where the runtime exposes them, so the local JSONL
      answers cost and reliability queries with jq alone.
- [ ] P1: Phase 7 exit gate — audit plugin lines parse as valid JSON and redact
      planted fake secrets in a test; the unified field contract is documented; native
      command guidance verified against the installed OpenCode version (flags checked
      per Phase 6.8 contract check, since CLI flags drift too).

Phase 8 — deterministic verification extensions (Track G; exploratory, each item is
decision-and-design only and needs separate approval before implementation):

- [ ] P2: Phase 8.1 — rules-eval harness ("phpunit for AGENTS.md"): a fixed task
      suite run against shipped instruction surfaces before and after an edit, so
      instruction regressions are caught mechanically instead of by review alone. The
      deterministic subset already exists as validators (coverage, reachability,
      drift, command contract); the behavioral subset (scenario prompts with expected
      routing/refusal/escalation outcomes) is new. Design it git-native and
      deterministic-where-possible; anything model-dependent is marked as such.
- [ ] P2: Phase 8.2 — mutation-score verification rung: document an optional top rung
      of the verification ladder for high-risk changes — mutation testing (Infection
      for PHP) as proof that "tests pass" means something. Heavy and approval-gated;
      never a default gate.
- [ ] P2: Phase 8.3 — task-scoped context routing recipe: unify the existing
      primitives (`repomix-scc-router.sh` ranked selection, `query-usage.sh` token
      budgeting, graphify edge queries) into one documented workflow that emits a
      minimal sufficient file set for a named task under a hard token budget —
      shipped as guidance on existing tools, not a new tool.

## Missing Points Added (Not In Any v0.5 Plan)

1. Shipped-files constraint made binding: all rendered-surface chunks rerouted to
   template sources (resolves Track A chunks 10-13 conflict).
2. Graph-verified ordering: opencode.json thinning promoted to first thinning slice
   because it has zero graph edges and the broadest always-on load.
3. Behavioral baseline location resolved to existing `templates/snippets/` (>=75% reuse
   rule), removing Track B's shared/-vs-snippets/ ambiguity.
4. Track A chunk 4 and Track B chunk 6 deduplicated into one slice (Phase 5.2).
5. Validator gate made mandatory per slice, because the graph proves there are no
   structural validator-to-template edges — validators are the only drift protection.
6. Dead-file risk ranking for Track C wrappers: workflows (17-entry pattern) first,
   skills (2) and commands (4) last and reachability-gated.
7. Net-negative line rule for thinning slices, grounded in measured budgets
   (AGENTS.template.md already exceeds its 180-line soft max).
8. `docs/ai/**` ship-status resolved via `docs/ai/installed-files.md`: the coverage
   matrix and handoff contract live in shipped canonical docs, not source-repo-only docs.
9. Pre-existing worktree changes explicitly excluded and named.
10. Graph refresh (`graphify update .`) added to the maintenance loop as a secondary
    reachability signal.
11. Full chunk-level traceability appendix (below) so coverage of the v0.5 plans is
    auditable instead of asserted.
12. Track F field-failure remediation (Phase 6): hook error-path contract, agent
    duty-vs-permission parity, blocked-edit fallback, anti-rewrite-loop stop condition
    — all sourced from real installed-target failure reports, absent from every v0.5 plan.
13. Shipped write-agent code-quality conventions and test-grouping rule (F-7) in the
    user-owned conventions and testing instruction templates.
14. Context-economy ignore defaults for graph/pack artifacts (F-6), preventing
    200k-token artifacts from dominating packs in installed targets.
15. Remaining-work section added to the minimum handoff payload (F-7).
16. Multi-scope graphify maintenance guidance for installed targets (F-8).
17. Track L log-driven remediation (Phase 6.7-6.13): evidence-writer JSON integrity,
    documented-command contract validation, persistent-red verification triage,
    retry-without-change discipline, pack-path robustness, deterministic verify
    scoping with a shared exclusion list, and a no-hang-after-completion contract —
    proven by 862 logged events plus direct user reports from a real installed
    target; absent from every v0.5 plan.
18. User-facing debug and helper steps shipped alongside the fixes (timeout-based
    hang reproduction, scope-leak triage, determinism check, CTRL+C workaround with
    honest `Not run:` reporting).
19. Track F additions from the re-read notes (F-9..F-13): validator portability
    (`timeout` fallback), target-aware validation (no source-repo-only file
    requirements), noise-separated config validation, plan-splitting and
    single-verifiable-unit task rules, default handoff chains with runtime handoff
    metadata, reviewer PR access, large-file read routing, stale-evidence reporting
    shape, and guaranteed-load test-first plus reference-integrity rules.
20. Track O shipped observability program (Phase 7): OpenCode audit plugin template
    with redaction, native stats/export guidance with honest cost semantics, unified
    evidence field contract across OpenCode NDJSON and `.ai-logs/tool-usage.jsonl`,
    and optional MLflow/ccusage dashboard guidance.
21. Track G adopted items: mechanical duplication gates for thinning proof
    (jscpd/CPD/MinHash), portable policy compilation direction for Phase 5.7,
    provenance and cost fields in the evidence contract, rules-eval harness,
    optional mutation-score rung, and a task-scoped context routing recipe —
    with explicit rejections recorded (semantic diff, cross-language contracts,
    server dashboards) to prevent scope creep.

## Traceability Appendix — v0.5 Chunk To v0.6 Step

Track A (repo clarity):

| Chunk | v0.6 step |
|---|---|
| A-1 control entrypoint | Phase 0.3 + 0.4 |
| A-2 source-vs-target state language | Phase 0.4 |
| A-3 maintainer task routing | Phase 0.3 |
| A-4 project-context unknowns | Phase 5.2 (deduplicated with B-6) |
| A-5 runtime surface matrix | Phase 3.3 |
| A-6 routing-role overlap | Phase 5.6 |
| A-7 audience separation | Phase 5.6 |
| A-8 validator-routing matrix | Phase 0.3 |
| A-9 install-surface doc state labeling | Phase 0.4 |
| A-10 instructions consolidation audit | Phase 5.1 (rerouted to `templates/instructions/`) |
| A-11 duplicated adapter policy text | Phase 3.1–3.2 + 5.1 |
| A-12 adapter claims vs canonical, no false parity | Phase 3.3 |
| A-13 shorten baselines post-improvement | Phase 1.2 + 3.2 (net-negative line rule) |
| A-14 maintained index surfaces | Phase 5.8 |
| A-15 maintenance loop | Phase 5.4 |
| A-16 contributor quickstart | Phase 5.8 (conditional; README part out of scope) |

Track B (template thinning):

| Chunk | v0.6 step |
|---|---|
| B-1 / B-1A coverage matrix + primitives | Phase 0.1 |
| B-2 surface classification | Phase 0.2 |
| B-2A behavioral baseline shared source | Phase 1.1 (location resolved: `templates/snippets/`) |
| B-3 thin Copilot baseline | Phase 3.2 |
| B-3A tradeoff note | Phase 1.1 |
| B-4 reduce OpenCode always-on bundle | Phase 3.1 |
| B-5 capabilities README router | Phase 5.2 |
| B-6 project-context template defaults | Phase 5.2 (deduplicated with A-4) |
| B-7 project starter docs | Phase 5.2 |
| B-7A anti-pattern examples | Phase 5.3 |
| B-8 reachability review | Phase 0.2 + 5.4 |
| B-9 dead-file review model | Phase 0.2 + 5.4 |
| B-10 semantic parity review method | Phase 3.3 |
| B-11 allowlist hand-authored vs generated | Phase 5.7 |
| B-12 maintenance loop | Phase 5.4 |

Track C (clarification/PRD/handoff):

| Chunk | v0.6 step |
|---|---|
| C-1 / C-1A / C-1B clarification contract | Phase 2.2 |
| C-2 minimum handoff payload | Phase 2.1 |
| C-3 / C-3A PRD/task family + two-phase pattern | Phase 4.1 |
| C-4 / C-4A runtime wrappers + metadata vs prose | Phase 4.2 |
| C-5 fallback behavior | Phase 4.2 |
| C-6 dead-surface reachability | Phase 4.3 + 5.4 |
| C-7 always-on vs deterministic-load decision | Phase 0.1 matrix + Phase 3 gates |
| C-8 pre/post thinning coverage comparison | Phase 3 exit gate + Phase 3.3 methodology |
| C-9 maintenance loop | Phase 5.4 |

Track D (roadmap): superseded in full; its exit gates are preserved as the Phase 0–5
exit gates, and its final program review is Phase 5's exit gate. Ordering deliberately
changed (documented in Context) — opencode.json thinning promoted on graph evidence.

Track F (field notes, external `copy-paste/ai`):

| Note | v0.6 step |
|---|---|
| F-1 hook errored, denied read-only command | Phase 6.1 |
| F-2 infinite md-rewrite loops | Phase 0.1 matrix + Phase 1.1 baseline + Phase 6.3 |
| F-3 broken agent move/edit permissions | Phase 6.2 |
| F-4 duplicate content on blocked edit | Phase 1.1 + Phase 6.3 |
| F-5 agents that should be skills | Phase 6.2 |
| F-6 graph artifact dominated context pack | Phase 6.6 |
| F-7 write-agent quality rules + remaining work + test grouping | Phase 2.1 + 6.4 + 6.5 |
| F-8 multi-scope graphify maintenance | Phase 5.5 |

Track L (tool-usage log, external `copy-paste/ai/ai-logs`):

| Log finding | v0.6 step |
|---|---|
| L-1 permanent-red verify/doc-check (42 fails, weeks-long identical counts) | Phase 6.9 |
| L-2 `unknown mode: --check` docs/script drift | Phase 6.8 |
| L-3 identical-retry usage errors (`mode and target required` pairs) | Phase 6.10 |
| L-4 malformed `details.raw`, unpopulated status/decision/exit-code fields | Phase 6.7 |
| L-5 guard start/done imbalance (324/307), `actor.id=common` blur | Phase 6.7 |
| L-6 pack pipeline fails on target paths (`no context bundles generated`) | Phase 6.11 |
| L-7 ai-verify scans `.ai-backups`/useless folders; param-dependent scope | Phase 6.12 + debug steps |
| L-8 scripts hang after completion, require CTRL+C | Phase 6.13 + debug steps |

Track F re-read additions and Track O (observability):

| Finding | v0.6 step |
|---|---|
| F-9 timeout unavailable; manifest-dependent catalog validator; config noise; missing helper | Phase 6.14 (+6.8 for doc-referenced helpers) |
| F-10 plan splitting, single-unit tasks, goal statements, handoff chains | Phase 4.1 + 6.15 |
| F-11 reviewer PR access; large-file read routing; stack DB commands | Phase 6.15 |
| F-12 stale-evidence reporting shape | Phase 6.15 |
| F-13 test-first + reference integrity guaranteed-load | Phase 0.1 matrix |
| O-1 three-layer OpenCode observability (stats/plugin/dashboards) | Phase 7.1, 7.2, 7.4 |
| O-2 extend Copilot-side `.ai-logs` into diagnosable, unified pipeline | Phase 6.7 + 7.3 |
| G duplication measurement (jscpd/CPD/MinHash) | Phase 5.1 |
| G portable policy compilation | Phase 5.7 |
| G provenance + cost fields | Phase 7.5 |
| G rules-eval harness | Phase 8.1 |
| G mutation-score rung | Phase 8.2 |
| G task-scoped context routing | Phase 8.3 |

## Acceptance Criteria

- [ ] AC-01: A coverage matrix in a shipped doc maps every critical topic and
      workflow-critical primitive to a guaranteed-load surface per runtime.
- [ ] AC-02: All 139 shipped template surfaces carry an explicit role classification
      with reachability rules that can flag dead files.
- [ ] AC-03: One behavioral-baseline snippet ships from `templates/snippets/` and
      renders into AGENTS and Copilot baselines with net-negative always-on line impact.
- [ ] AC-04: Clarification and handoff contracts are canonical in shipped docs before
      any runtime wrapper exists, and wrappers reference rather than restate them.
- [ ] AC-05: `templates/core/opencode.json` always-on set is reduced with proven
      coverage replacement; Copilot baseline is thinner with the same proof.
- [ ] AC-06: The PRD/task workflow family exists in `templates/workflows/**` with
      staged-confirmation support and runtime fallback rules recorded in shipped docs.
- [ ] AC-07: No rendered surface (`.opencode/**`, `.github/**`, root adapters) was
      hand-edited; drift validation passes at every slice boundary.
- [ ] AC-08: Instruction-template consolidation preserves safety posture and passes
      the validator gate.
- [ ] AC-09: The maintenance loop (source -> coverage proof -> render -> validate) is
      documented in shipped docs and includes the dead-file review model.
- [ ] AC-10: Each executed slice traces back to a named chunk in one of the four v0.5
      plans, a named field note (F-1..F-8), or a named missing point in this plan —
      no silent scope growth.
- [ ] AC-11: An errored pre-tool-use hook can no longer deny benign read-only commands;
      the error-path contract is tested and shipped.
- [ ] AC-12: No shipped agent template declares a duty that its own shipped permission
      boundary blocks; agents whose purpose is defeated by their boundary are
      repackaged as skills.
- [ ] AC-13: Loop-guard and blocked-edit fallback rules are guaranteed-load on every
      supported runtime, so rewrite loops and duplicate-on-blocked-edit failures are
      policy violations rather than unspecified behavior.
- [ ] AC-14: Shipped ignore defaults keep graph and pack artifacts out of context
      packs, search results, and watchers in installed targets.
- [ ] AC-15: Every line the shipped evidence writer emits parses as valid JSON with
      populated status, exit-code, decision, and real actor fields; guard start/done
      pairs balance even on crash.
- [ ] AC-16: Every command documented in shipped docs parses against the shipped
      script versions, enforced at install verify (no `unknown mode: --check` class
      drift can ship).
- [ ] AC-17: Shipped verification names failing checks with remediation commands, and
      a persistent identical failure across sessions is an explicit escalation
      trigger; post-install ends green or with a triage list.
- [ ] AC-18: The retry-without-change rule is guaranteed-load: an identical failed
      command is never re-run unchanged.
- [ ] AC-19: `ai-verify.sh` and every scanning script honor one shared exclusion list
      deterministically; `--list-targets` exists; backup and artifact folders are
      provably never scanned regardless of parameters.
- [ ] AC-20: Every shipped entry script exits cleanly after completing its work — the
      timeout-based no-hang regression test passes; no shipped script requires CTRL+C
      as normal operation.
- [ ] AC-21: Shipped validators run correctly in installed targets: no GNU-`timeout`
      hard dependency, no source-repo-only file requirements, and pre-existing missing
      surfaces are reported as a separate class from current-change findings.
- [ ] AC-22: Shipped agent templates carry default handoff chains (architect ->
      plan-writer, implementer -> reviewer), runtime handoff metadata where supported,
      reviewer PR read access, and large-file reads routed through `preview-file.sh`.
- [ ] AC-23: A shipped OpenCode audit plugin template exists, redacts secrets, emits
      valid NDJSON, and shares one documented field contract with
      `.ai-logs/tool-usage.jsonl` so both pipelines answer the same `jq` queries.
- [ ] AC-24: Observability guidance states cost semantics honestly (per LLM turn, not
      per command) and marks MLflow/ccusage as optional, experimental, approval-gated.

## Verification Plan

- Run the per-slice validator gate after every slice (commands listed above).
- After each thinning slice, perform a coverage re-review against the Phase 0 matrix
  and record which surface now guarantees each affected topic.
- After each phase, run `graphify update .` and check that new/moved shipped files have
  inbound reference edges (secondary dead-file signal).
- `bash scripts/ai/ai-verify.sh .` only at program close or after validator/generator
  contract changes.
- Line budgets checked against `docs/ai/ai-file-standards.md` for every touched template.

## Risks And Rollback

Risk level: medium-high (inherited from Tracks B and C).

Main risks:

- Coverage may currently rely on duplication; thinning before the matrix is complete
  could silently drop guaranteed-load coverage (mitigated by Phase 0 exit gate).
- The installer/render pipeline relationship between template sources and this repo's
  rendered copies is validator-enforced only; a render step missed after a template
  edit would leave this repo internally inconsistent (mitigated by drift validation in
  the per-slice gate).
- Track C wrappers may pressure scope toward greenfield skills/commands; reachability
  gating must hold (mitigated by Phase 4.3 gate).
- Copilot may need a thicker baseline than OpenCode/Claude; forcing symmetry would
  create false parity (explicitly allowed asymmetry, recorded in the matrix).

Rollback posture:

- One runtime surface per slice; a failed coverage proof restores the thicker baseline
  for that runtime only, without rolling back the program.
- Phase 0 artifacts (matrix, classification, routing) are additive and never need
  rollback; they are the safety floor for everything later.
- If sequencing proves wrong during implementation, update this plan in place; do not
  silently widen any underlying v0.5 plan.

## Handoff Notes

Recommended execution order: Phase 0 -> 1 -> 2 -> 3 -> 4 -> 5, with exit gates as hard
stops. Each phase is implementable as one or more bounded slices (<=6 files each) by
the implementer agent, using the corresponding v0.5 plan chunk text for detail.

First bounded slice: Phase 0.1 coverage matrix in `docs/ai/integration-matrix.md`
(or companion), verified by the per-slice validator gate.

Recommended next stage:

implementer means implementer agent handoff using OpenCode command: /implement
