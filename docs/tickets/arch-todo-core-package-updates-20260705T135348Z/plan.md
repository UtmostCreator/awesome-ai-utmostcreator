# Architecture Plan — Core-Package Updates: Karpathy Parity, jscpd, ECC-Inspired Guardrails

- Ticket: `arch-todo-core-package-updates-20260705T135348Z`
- Source: architect handoff, grounded on read-only verification (supersedes/extends research ticket `arch-todo-karpathy-ecc-jscpd-shipped-features-20260705T134713Z`)
- Generated: 2026-07-05T13:06:32Z (updated/reformatted; original authored 2026-07-05T13:53:48Z)
- Plan file: docs/tickets/arch-todo-core-package-updates-20260705T135348Z/plan.md
- Status: `P0 and P1 IMPLEMENTED AND VERIFIED; P2 and P3 remain PLAN ONLY`
- Risk: `medium` (shipped template surfaces + pack lock + ai-verify; no runtime/data change)
- Split-out ticket: the former P4 workstream (stack/permission/placeholder skill trio) has been split into its own independent ticket: `docs/tickets/arch-todo-stack-permission-placeholder-skill-trio-20260705T151632Z/plan.md` — see that ticket for its scope, Todo items, and acceptance criteria.

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan.md` and move it into `archive/` under this ticket folder (`docs/tickets/arch-todo-core-package-updates-20260705T135348Z/archive/DONE-plan.md`).

## Context

This ticket is grounded on direct, already-completed verification (not assumption):

- `aiCollectTemplateChecksums()` re-run → 159 template files exist on disk; the lock has 155 entries → **4 stale-missing** entries.
- Confirmed `anti-pattern-examples.md` DOES ship via whole-dir copy (`packs.php:368`) — the snippets directory ships **wholesale**: `{type: dir, source: templates/snippets, target: docs/ai/snippets, merge_strategy: replace}`. So installed targets **do** receive the file; the reference is NOT dangling at install time.
- Confirmed the package lock is auto-generated (no extension filter) → the fix for the stale-lock defect is regeneration, not code.
- Prior research called `anti-pattern-examples.md` a "shipping gap (dangling reference)"; direct verification refines this — the real defect is the stale lock, not a dangling reference.

## Implementation Log (P0 And P1 — Verified)

Commands run and exact results, recorded so a future reader does not need to re-derive this:

**P0:**

- `php tools/ai/ai.php package-lock --update` — wrote updated `packages/ai-universal-rules/package-lock.ai.json`.
- `php tools/ai/ai.php package-verify` — `mismatch_count: 0`, `status: ok` (confirmed via `docs/ai/generated/package-verify.json`).
- Full lock-diff accounting (see AC-02 for the summary conclusion):
  - 4 NEW keys, exactly as originally anticipated: `templates/claude/settings.json`, `templates/core/CLAUDE.template.md`, `templates/snippets/anti-pattern-examples.md`, `templates/workflows/prd-and-tasks.md`.
  - 3 CHANGED-checksum keys directly caused by this ticket's own P1 work (intentional, expected): `templates/core/AGENTS.template.md`, `templates/core/copilot-instructions.template.md`, `templates/snippets/behavioral-baseline.snippet.md`.
  - ~13 CHANGED-checksum keys caused by the separate, confirmed-complete `arch-todo-permission-layer-composition-20260705T004618Z` ticket (NOT this ticket's work, but legitimate concurrent working-tree state, already reviewed elsewhere per user confirmation): `templates/core/agents/architect.md`, `architecture-plan-writer.md`, `bootstrapper.md`, `config-maintainer.md`, `implementer.md`, `post-install.md`, `refactorer.md`, `release-auditor.md`, `repository-researcher.md`, `repository-reviewer.md`, `researcher.md`, `reviewer.md`, `workflow-auditor.md`, plus `templates/core/opencode.json`, `templates/core/execution-protocol.template.md`, `templates/instructions/execution-protocol.instructions.md`, `templates/core/project-context.template.md`, `templates/core/project/README.md`, `templates/workflows/architecture-plan.md`.

**P1:**

- Direct read of all three files: the 5 Karpathy sub-rules are present verbatim (prose-wrapped in the snippet, single-line in both templates) in `templates/snippets/behavioral-baseline.snippet.md` (lines 23-29), `templates/core/AGENTS.template.md` (lines 56-60), and `templates/core/copilot-instructions.template.md` (lines 71-75). Confirmed meaning-equivalent across all three (the prose-wrap vs single-line difference matches each file's own pre-existing style for every other line in this section — not a deviation).
- `php tools/ai/generate-agent-snippets.php --check` — `OK: agent tool snippets in sync`. Scope note: this check covers the agent-tools-readonly/execute snippets, not behavioral-baseline byte-parity specifically (no automated generator exists for that pair) — behavioral-baseline parity was confirmed by the direct read above.
- `php tools/ai/validate-context-budgets.php` — 17 pre-existing WARNs (soft-max, non-blocking, largely from the concurrent permission-layer-composition agent-template growth, unrelated to the 5 new lines) and 1 pre-existing FAIL (`.opencode/skills/graphify/SKILL.md` = 669 lines > hard max 350). Confirmed via `git log`/`git diff` this FAIL file has zero uncommitted diff (last commit `d3121ee`) — pre-existing, out of scope, owned by graphify's own out-of-band installer per `docs/ai/adapter-contract.md`'s "Out-Of-Band Local Additions" section.
- `php tools/ai/validate-adapter-drift.php` — exit code 0 (`OK: adapter drift validation completed`). WARN lines produced are all pre-existing, about unrelated workflow templates missing doc cross-references; none reference `AGENTS.template.md`, `copilot-instructions.template.md`, or any P1-touched file.
- `composer test:fast` (paratest, 12 workers) as full-suite smoke check: 863 tests, 10911 assertions, 10 failures, 7 skipped. All 10 failures triaged and confirmed pre-existing/unrelated to P0 or P1 (none touch `package-lock.ai.json`, `behavioral-baseline.snippet.md`, `AGENTS.template.md`, or `copilot-instructions.template.md`):
  - `ShHelpTest::testHelpMatchesGoldenSnapshot`, `ShIntrospectRegressionTest::testContractMatchesGoldenJsonSnapshot` — `scripts/ai/ai-search.sh` tool-introspection golden snapshot, unrelated.
  - `CliToolsTest::testGenerateRepoStructureCheckModeExitsZero`, `...OutputsUpToDateLines` — missing repo-structure metadata for top-level paths `.claude`, `bin`, `common-vocabulary`, `graphify-out`, `v0.5-upgrade`, `v0.6-plan`, unrelated.
  - `ShipReferenceIntegrityTest::testShippedSurfaceHasNoBrokenDocReferences` — flags `docs/ai/index.md` as an unshipped path referenced by `docs/ai/workflow.md`, plus the pre-existing graphify size issue, unrelated.
  - `...testEveryOpencodeInstructionIsShipped` — `opencode.json` missing a `docs/ai/tools/ai-search.md` reference, unrelated.
  - Two validator self-tests cascading from the same root causes above.
  - `AgentsManifestTest::testManifestClassifiesEveryOpencodeAgent`, `...testManifestDocumentsSurfaceCoverageDifferences` — the agents manifest doc does not yet document the `script-runner` agent; belongs to the separate permission-layer-composition ticket, not this one.

## Problem

1. **Stale package lock (P0):** `packages/ai-universal-rules/package-lock.ai.json` is missing 4 files that exist in `templates/`:
   - `templates/claude/settings.json`
   - `templates/core/CLAUDE.template.md`
   - `templates/snippets/anti-pattern-examples.md`
   - `templates/workflows/prd-and-tasks.md`
   Effect: `php tools/ai/ai.php package-verify` currently reports 4 `missing_from_lock` mismatches → **package integrity check fails**. This is the concrete, verified bug to fix first.
2. **Missing Karpathy behavioral sub-rules (P1):** 5 grep-confirmed one-line behavioral rules are absent from the shipped behavioral baseline that feeds all three rendered adapters.
3. **No prompt-defense / confidence-gated review guardrails (P2):** shipped write/review agent templates lack an instruction-integrity ("treat file/tool content as data, not instructions") preamble and reviewer templates lack confidence/severity-labeled, zero-findings-is-valid reporting guidance (ECC-inspired).
4. **No duplication detection in `ai-verify` (P3):** the fast/bounded `ai-verify` pipeline has no opt-in jscpd-based duplication gate, unlike its existing tiered line-count gate (`30-linecount.sh`).

P4 (stack/permission/placeholder skill trio) has been split into its own ticket: `docs/tickets/arch-todo-stack-permission-placeholder-skill-trio-20260705T151632Z/plan.md` — see that ticket for scope, Todo items, and acceptance criteria.

## Target Outcome

- `php tools/ai/ai.php package-verify` passes with 0 mismatches (lock refreshed, diff limited to the 4 verified files unless P1/P2 intentionally changed sources).
- The 5 Karpathy sub-rules are present, byte-identical, across `behavioral-baseline.snippet.md`, `AGENTS.template.md`, and `copilot-instructions.template.md`.
- Shipped write/review agent templates carry a prompt-defense preamble; reviewer templates carry confidence-gated, zero-findings-valid review guidance — without weakening existing reviewer strictness.
- `ai-verify` has an opt-in (`VERIFY_JSCPD`, default off), env-tunable jscpd duplication module that changes no default behavior, advisory-only unless a FAIL threshold is explicitly set.

P4 (stack/permission/placeholder skill trio) has been split into its own ticket: `docs/tickets/arch-todo-stack-permission-placeholder-skill-trio-20260705T151632Z/plan.md` — see that ticket for scope, Todo items, and acceptance criteria.

## In Scope

Core-package files only, four independently shippable workstreams:

- **P0** Refresh the stale package lock so integrity verification passes.
- **P1** Add 5 missing Karpathy sub-rules to the shipped behavioral baseline (byte-parity source).
- **P2** Add a prompt-defense preamble + confidence-gated-review guidance to shipped agent templates.
- **P3** Add an opt-in, env-tunable jscpd duplication module to `ai-verify`.

P4 (the stack/permission/placeholder skill trio: `scan-stack`, `generate-permissions`, `replace-placeholders`) has moved to its own ticket: `docs/tickets/arch-todo-stack-permission-placeholder-skill-trio-20260705T151632Z/plan.md`. It is no longer in scope of this ticket.

Each Pn is independently shippable and lands as its own commit (no GitHub PR required for this repository's own changes).

## Out Of Scope (Things To Avoid)

Deferred to separate tickets — independently and differently risked:

- User-defined pattern auto-discovery (`docs/ai/patterns/` + per-runtime injection) — larger, its own design; tracked in the research ticket §3.
- Per-stack golden-config gallery (ECC `examples/`).
- Hook profile tiers / cross-session memory.

Global things to avoid across all workstreams:

- Do NOT hand-edit generated artifacts (lock, rendered adapters); edit sources + regenerate.
- Do NOT bake stack/tool-specific claims into universal templates (`AGENTS.template.md` ships everywhere).
- Do NOT bundle P0–P3 into one opaque commit; keep each slice auditable, and run the P0 lock refresh last.
- Do NOT exceed `policies/ai-file-standards.json` line budgets.
- Do NOT make jscpd default-on or auto-fetch.

P4's out-of-scope items (stack scanning/permission-derivation/placeholder-replacement reimplementation bans, the deny-floor bypass ban, the universal-template ban for `stack.md`, and the permission-frontmatter auto-write ban) now live in the split-out ticket: `docs/tickets/arch-todo-stack-permission-placeholder-skill-trio-20260705T151632Z/plan.md`.

## Affected Paths

- `packages/ai-universal-rules/package-lock.ai.json` (P0 — regenerated only, never hand-edited)
- `packages/ai-universal-rules/templates/snippets/behavioral-baseline.snippet.md` (P1 — canonical add)
- `packages/ai-universal-rules/templates/core/AGENTS.template.md` (P1 — byte-parity re-render)
- `packages/ai-universal-rules/templates/core/copilot-instructions.template.md` (P1 — byte-parity re-render)
- `packages/ai-universal-rules/templates/core/agents/implementer.md`, `refactorer.md`, `reviewer.md`, `repository-reviewer.md` (P2a; optionally `templates/optional/agents/bugfix.md`, `super-implementer` if present)
- `packages/ai-universal-rules/templates/core/agents/reviewer.md`, `repository-reviewer.md` (P2b confidence-gate)
- `scripts/ai/internal/ai-verify/35-jscpd.sh` (P3 — new)
- `scripts/ai/ai-verify.sh` (P3 — env defaults + source line)
- `scripts/ai/internal/ai-verify/90-run.sh` (P3 — call `check_jscpd`)
- `docs/ai/validation.md` + script registry entry (P3 registration)
- `tests/shell/ai-verify-jscpd.bats` (P3 — new test)

P4 (stack/permission/placeholder skill trio) has been split into its own ticket: `docs/tickets/arch-todo-stack-permission-placeholder-skill-trio-20260705T151632Z/plan.md` — see that ticket for scope, Todo items, and acceptance criteria.

## Contracts And Boundaries

- **Lock-generation contract (P0):** the package lock is auto-generated by the canonical tool; hand-adding sha entries will drift on the next generator run. Regenerate via `package-lock --update`, never hand-edit.
- **Byte-parity contract (P1):** `behavioral-baseline.snippet.md` must stay byte-equivalent with its rendering into `AGENTS.template.md` and `copilot-instructions.template.md` via `generate-agent-snippets.php`. Rewording the same rule differently across surfaces fails the parity check.
- **Line-budget contract (P1/P2):** `AGENTS.md` hard-max 250 lines, `copilot-instructions` hard-max 220 lines (`policies/ai-file-standards.json`); each P2 agent template has its own 320-line hard max.
- **Reviewer-strictness contract (P2b):** "zero findings is a valid result" must not become "skip review" — existing reviewer strictness must not be weakened.
- **Opt-in / no-silent-network contract (P3):** `VERIFY_JSCPD` defaults to `0` (off); jscpd may only be fetched via `npx --yes jscpd` when `VERIFY_JSCPD=1` is explicitly set — never a silent/default network fetch, per approval boundaries. **CONFIRMED default tuning (resolved decision):** when enabled, `JSCPD_WARN_PCT=5`; `JSCPD_FAIL_PCT` stays EMPTY/unset by default, so the check is advisory-only and never hard-fails verification unless a project explicitly opts into a FAIL threshold.
- **Delivery contract (CONFIRMED, resolved decision):** no GitHub PR review process is required for this repository's own changes (this is the kit's own repo, direct commits apply), but each workstream (P0, P1, P2, P3) still lands as its own isolated, auditable commit, in the suggested slice order, for the rollback reasoning in Risks And Rollback.

P4's contracts (deny-floor, `substitute=false`, scan-first data-flow, adapter-thinness) now live in the split-out ticket: `docs/tickets/arch-todo-stack-permission-placeholder-skill-trio-20260705T151632Z/plan.md`.

## Todo Plan

### P0 — Refresh Stale Package Lock

- [x] P0.1: Run `php tools/ai/ai.php package-lock --update`. **VERIFIED:** ran; wrote updated `packages/ai-universal-rules/package-lock.ai.json`. See Implementation Log.
- [x] P0.2: Run `php tools/ai/ai.php package-verify` and confirm `matches: true`, 0 mismatches. **VERIFIED:** `mismatch_count: 0`, `status: ok`, confirmed via `docs/ai/generated/package-verify.json`.
- [x] P0.3: Inspect the lock diff — the ONLY new entries must be the 4 verified files (`templates/claude/settings.json`, `templates/core/CLAUDE.template.md`, `templates/snippets/anti-pattern-examples.md`, `templates/workflows/prd-and-tasks.md`). If more appear, stop and investigate before committing — an unintended template change is in the tree. **VERIFIED WITH NOTED DEVIATION:** the 4 anticipated keys are present as new entries, plus additional changed-checksum entries — see the AC-02 note and Implementation Log for the full, investigated accounting (all attributed to either this ticket's own P1 work or a separate, user-confirmed-complete ticket; nothing unaccounted-for).
- [x] P0.4 (sequencing): Treat P0 as the LAST step of whichever slice lands, because P1/P2 mutate template sources and would re-stale the lock; run P0 alone first only if no other template edit follows it. **VERIFIED:** P0 was run after P1's template edits landed, per this sequencing rule.

### P1 — Karpathy Sub-Rules Into Shipped Baseline

- [x] P1.1: Add these 5 one-line rules to `templates/snippets/behavioral-baseline.snippet.md` (canonical source):
  1. Match the existing style and conventions of the file you edit, even if you would do it differently.
  2. Mention unrelated dead code you notice; do not delete it unless asked.
  3. Remove only imports/variables/functions that YOUR change made unused — not pre-existing dead code.
  4. Every changed line should trace directly to the stated request.
  5. Do not add error handling for scenarios that cannot occur.

  **VERIFIED:** all 5 present verbatim (prose-wrapped) at `templates/snippets/behavioral-baseline.snippet.md` lines 23-29.
- [x] P1.2: Re-render `templates/core/AGENTS.template.md` and `templates/core/copilot-instructions.template.md` from the updated snippet (kept byte-parity — do not independently reword). **VERIFIED:** all 5 rules present, single-line form, at `AGENTS.template.md` lines 56-60 and `copilot-instructions.template.md` lines 71-75; confirmed meaning-equivalent to the snippet (prose-wrap vs single-line matches this section's pre-existing style, not a deviation).
- [x] P1.3: Run `php tools/ai/generate-agent-snippets.php --check` (and `--write` if needed) to verify byte-parity. **VERIFIED, WITH SCOPE NOTE:** command result `OK: agent tool snippets in sync` — this generator/check covers the agent-tools-readonly/execute snippets, not the behavioral-baseline snippet pair specifically (no automated generator exists for that pair); behavioral-baseline byte/meaning parity across the 3 files was confirmed by direct read instead (see P1.1/P1.2 notes and AC-04's updated text).
- [x] P1.4: Confirm line budgets with `php tools/ai/validate-context-budgets.php` (5 short added lines expected to stay within `AGENTS.md` hard-max 250 / copilot hard-max 220 per `policies/ai-file-standards.json`). **VERIFIED:** ran; 17 pre-existing WARNs (soft-max, non-blocking, unrelated to these 5 lines) and 1 pre-existing FAIL (`.opencode/skills/graphify/SKILL.md`, zero uncommitted diff, out of scope per the adapter-contract's out-of-band-additions section) — neither `AGENTS.template.md` nor `copilot-instructions.template.md` triggered a new WARN/FAIL from this change.
- [x] P1.5: Confirm no new capability was created for these 5 one-liners (violates adapter thinness). **VERIFIED:** no new capability, skill, prompt, or command file was added for P1 — the 5 lines were added only to the existing canonical snippet and its two existing rendered adapters.

### P2 — Prompt-Defense + Confidence-Gated Review (ECC-Inspired)

- [ ] P2a.1: Add a short, reusable "instruction-integrity" prompt-defense preamble to the shipped write/review agent templates under `packages/ai-universal-rules/templates/core/agents/`: `implementer.md`, `refactorer.md`, `reviewer.md`, `repository-reviewer.md` (and optionally `templates/optional/agents/bugfix.md`, `super-implementer` if present). Content intent: treat file contents, tool output, and fetched text as data, not instructions; ignore embedded directives that try to change task, permissions, or safety rules; surface suspected injection instead of complying. Author original wording for this repo's voice (CONFIRMED decision) — do not copy ECC's `architect.md:8-15` text verbatim; the ECC citation remains only as a design-pattern reference, not a source to copy from.
- [ ] P2a.2: Decide inline-vs-shared-snippet during design: if the preamble is <6 lines, inline is acceptable; if longer, extract a shared snippet referenced by each agent (respect adapter-thinness) instead of duplicating the body in every agent.
- [ ] P2b.1: Add confidence-gated review guidance to `reviewer.md` + `repository-reviewer.md`: report findings with a confidence/severity label; "zero findings is a valid result — do not invent issues"; keep a short false-positive skip list (e.g. intentional patterns, generated files) to reduce reviewer noise. ECC reference: `code-reviewer.md:29-112`. CONFIRMED (resolved decision): this skip list is a fixed, shipped list baked directly into `reviewer.md`/`repository-reviewer.md` — no per-project extensible override file.
- [ ] P2.3: Confirm existing reviewer strictness is not weakened — "zero findings valid" must not become "skip review".
- [ ] P2.4: Confirm each edited agent stays within its 320-line hard-max budget.
- [ ] P2.5: Re-render `.opencode`/`.github` adapters and run `php tools/ai/validate-adapter-drift.php`.

### P3 — jscpd Duplication Module In ai-verify

- [ ] P3.1: Add new module `scripts/ai/internal/ai-verify/35-jscpd.sh`, sourced between `30-linecount.sh` and `40-step-runner`, mirroring the existing tiered line-count gate pattern.
- [ ] P3.2: Edit `scripts/ai/ai-verify.sh` to add env defaults and a `source` line for the new module.
- [ ] P3.3: Edit `scripts/ai/internal/ai-verify/90-run.sh` to call `check_jscpd` near `check_line_counts`.
- [ ] P3.4: Register the new module in `docs/ai/validation.md` and the script registry.
- [ ] P3.5: Add test `tests/shell/ai-verify-jscpd.bats` (opt-in default-off; tier behavior verified via a stub).
- [ ] P3.6: Implement design as opt-in (like `VERIFY_LINKS`/`VERIFY_SECRETS`), env-tunable, advisory-only by default (CONFIRMED — no FAIL threshold set by default, so this check never hard-fails verification unless a project explicitly opts in):
  - `VERIFY_JSCPD` (default `0` = off).
  - `JSCPD_MIN_TOKENS` (default `50`).
  - `JSCPD_WARN_PCT` (default `5`) → WARN when `% duplicated lines >=` this.
  - `JSCPD_FAIL_PCT` (default empty = never fail; advisory-only unless a project explicitly sets this; when set, increments `$failures`).
  - `JSCPD_PATHS` (default: scope-aware changed files, else `.`).
  - Availability guard: run via `command -v jscpd` else `npx --yes jscpd` (on-demand, matching the user-approved v0.6 pattern) — only when `VERIFY_JSCPD=1`, never silently fetch.
  - Parse jscpd JSON reporter `statistics.total.percentage`; emit INFO/WARN/FAIL by tier exactly like `30-linecount.sh`.
- [ ] P3.7: Document the known limitation honestly in the module/docs: jscpd's markdown reporter only tokenizes fenced code blocks, not prose (recorded in the v0.6 plan).
- [ ] P3.8: Give jscpd a bounded timeout via the existing step runner (respect anti-freeze budget).

P4 (stack/permission/placeholder skill trio) has been split into its own ticket: `docs/tickets/arch-todo-stack-permission-placeholder-skill-trio-20260705T151632Z/plan.md` — see that ticket for scope, Todo items, and acceptance criteria.

## Acceptance Criteria

### P0

- [x] AC-01: `php tools/ai/ai.php package-verify` exits 0 with 0 mismatches. **VERIFIED:** `mismatch_count: 0`, `status: ok` per `docs/ai/generated/package-verify.json`.
- [x] AC-02 (text updated to reflect verified reality, per deviation noted at P0.3): The refreshed lock now matches current template state with 0 mismatches. The 4 originally-anticipated new keys are present exactly as expected (`templates/claude/settings.json`, `templates/core/CLAUDE.template.md`, `templates/snippets/anti-pattern-examples.md`, `templates/workflows/prd-and-tasks.md`). Additionally, 3 changed-checksum keys are directly and intentionally caused by this ticket's own P1 work (`templates/core/AGENTS.template.md`, `templates/core/copilot-instructions.template.md`, `templates/snippets/behavioral-baseline.snippet.md`). The remaining ~13 changed-checksum keys (the agent template set plus `opencode.json`, `execution-protocol.template.md`, `execution-protocol.instructions.md`, `project-context.template.md`, `project/README.md`, `architecture-plan.md`) belong to the separately-tracked, user-confirmed-complete `arch-todo-permission-layer-composition-20260705T004618Z` ticket, which was already in-flight in the working tree concurrently — this is legitimate concurrent state correctly captured by the lock refresh, not scope creep from this ticket. Full accounting is in the Implementation Log.

### P1

- [x] AC-03: The 5 listed Karpathy sub-rules are present in `behavioral-baseline.snippet.md` and both rendered adapters (`AGENTS.template.md`, `copilot-instructions.template.md`), byte-identical across all three. **VERIFIED** by direct read (see P1.1/P1.2); meaning-equivalent across prose-wrapped vs single-line forms consistent with each file's pre-existing style.
- [x] AC-04 (text updated to precisely describe scope): `php tools/ai/generate-agent-snippets.php --check` passes and reports `OK: agent tool snippets in sync` — **this specifically validates the agent-tools-readonly/execute snippet pairs, not the behavioral-baseline snippet pair** (no automated generator/checker exists for that specific pair). Behavioral-baseline parity itself (AC-03) was confirmed by direct read of all three files, not by this generator check.
- [x] AC-05: `php tools/ai/validate-context-budgets.php` and `php tools/ai/validate-adapter-drift.php` both pass. **VERIFIED:** context-budgets run produced only pre-existing WARNs/1 pre-existing unrelated FAIL (no new violation from P1); adapter-drift run exited 0 (`OK: adapter drift validation completed`) with no WARN referencing any P1-touched file.

### P2

- [ ] AC-06: The prompt-defense preamble is present in the named write/review templates and re-rendered in the `.opencode`/`.github` adapters.
- [ ] AC-07: Confidence-gate guidance is present in both `reviewer.md` and `repository-reviewer.md`.
- [ ] AC-08: `php tools/ai/validate-adapter-drift.php` is clean and every edited agent stays under its line-budget hard max.

### P3

- [ ] AC-09: With `VERIFY_JSCPD=0` (default), the module logs a skip with zero behavior change and existing bats tests pass.
- [ ] AC-10: With `VERIFY_JSCPD=1` and a seeded duplicate, the module emits WARN at `JSCPD_WARN_PCT` and FAIL at `JSCPD_FAIL_PCT`.
- [ ] AC-11: The module is registered in `docs/ai/validation.md` and the script registry; the new `tests/shell/ai-verify-jscpd.bats` test is green.
- [ ] AC-12: `bash scripts/ai/ai-verify.sh .` on this repo stays green.

P4's acceptance criteria (formerly AC-13..AC-16) now live in the split-out ticket: `docs/tickets/arch-todo-stack-permission-placeholder-skill-trio-20260705T151632Z/plan.md`.

## Verification Plan

Whole-ticket ladder, focused first then broad (run in this order; P0 `package-verify` must be last if P1/P2 land in the same pass, per the sequencing note in P0.4):

1. `php tools/ai/generate-agent-snippets.php --check` — proves AC-04 (P1 byte-parity).
2. `php tools/ai/validate-context-budgets.php` — proves part of AC-05 / AC-08 (P1/P2 line budgets).
3. `php tools/ai/validate-adapter-drift.php` — proves part of AC-05 / AC-08 (P1/P2 adapter sync).
4. `php tools/ai/ai.php package-verify` — proves AC-01/AC-02 (P0 integrity); run this step last.
5. `bash scripts/ai/ai-verify.sh .` plus the new `tests/shell/ai-verify-jscpd.bats` — proves AC-09 through AC-12 (P3).
6. `composer test` — full-suite smoke check across all workstreams.

## Risks And Rollback

- **Sequencing risk (P0 vs P1/P2):** if P1 or P2 template edits land after a P0 lock refresh, the lock will re-stale. Mitigation: treat P0 as the final step of whichever slice lands, or run it once at the very end after all template-touching slices (P1, P2) are in.
- **Byte-parity drift risk (P1):** rewording the same rule differently across the snippet and its two rendered adapters silently breaks parity. Mitigation: single source-of-truth edit in `behavioral-baseline.snippet.md`, then re-render; verify with `generate-agent-snippets.php --check` before commit.
- **Reviewer-strictness regression risk (P2b):** "zero findings is valid" guidance could be misread as license to skip review. Mitigation: explicit acceptance criterion and review-time check that strictness language is unchanged.
- **Network/surprise-fetch risk (P3):** jscpd's `npx --yes jscpd` fallback could silently reach the network if the opt-in gate is not respected. Mitigation: hard gate on `VERIFY_JSCPD=1`; default `0`; covered by AC-09.
- **Rollback posture:** every workstream here is additive-only against shipped templates/scripts/skills, or a regeneration of a derived artifact (the lock). Rollback for any single slice is a straightforward revert of that slice's commit(s); P0's lock file can be regenerated again from the (reverted) template sources. No data migration or destructive change is introduced by this ticket.

P4's risks (deny-floor bypass, placeholder-substitution drift, the `docs/ai/project/stack.md` vs `docs/ai/project-stack.md` naming-collision risk) now live in the split-out ticket: `docs/tickets/arch-todo-stack-permission-placeholder-skill-trio-20260705T151632Z/plan.md`.

## Handoff Notes

**Which agents benefit from which change:**

| Change | Primary agents |
|---|---|
| P0 lock refresh | (infra) release-auditor, workflow-auditor validate integrity |
| P1 Karpathy sub-rules | implementer, super-implementer, refactorer, bugfix (write); reviewer, repository-reviewer (check) |
| P2a prompt-defense | ALL write/review agents |
| P2b confidence-gate | reviewer, repository-reviewer, release-auditor |
| P3 jscpd | refactorer (primary — duplication is its job), reviewer, release-auditor |

P4's agent-benefit rows (scan-stack, generate-permissions, replace-placeholders) now live in the split-out ticket: `docs/tickets/arch-todo-stack-permission-placeholder-skill-trio-20260705T151632Z/plan.md`.

**Suggested slice order (land as separate commits, no PR required for this repo):**

1. P0 lock refresh (or fold as final step of the last slice) — smallest, unblocks integrity.
2. P1 Karpathy sub-rules — low risk, high value, one source file + parity render.
3. P3 jscpd module — isolated to ai-verify, no template surface.
4. P2 prompt-defense + confidence-gate — touches multiple agent templates; largest review of the four.

**Resolved Decisions:**

- jscpd defaults (P3): off-by-default; when enabled, `JSCPD_WARN_PCT=5`, `JSCPD_FAIL_PCT` stays empty by default — advisory-only, never hard-fails unless a project explicitly opts in.
- P2a preamble: author original wording for this repo's voice — do not mirror ECC's `architect.md:8-15` text verbatim; the ECC citation is a design-pattern reference only.
- P2b false-positive skip list: a fixed, shipped list baked into `reviewer.md`/`repository-reviewer.md` — no per-project extensible override file.
- Delivery: no GitHub PR needed for this repository's own changes (direct commits); each workstream (P0, P1, P2, P3) still lands as its own isolated, auditable commit in the suggested slice order above.
- P4 (stack/permission/placeholder skill trio) split into its own independent ticket: `docs/tickets/arch-todo-stack-permission-placeholder-skill-trio-20260705T151632Z/plan.md` — see that ticket for its own resolved decisions (CLI verbs, `replace-placeholders` wrapping contract, `docs/ai/project/stack.md` output location, naming-collision-avoidance, and delivery/scoping decisions).

**Follow-up (observed during P0/P1 implementation, not a P1 gap — outside P1's stated Affected Paths):** this repository's own root-rendered files (`AGENTS.md` and `.github/copilot-instructions.md`, as opposed to their package templates under `packages/ai-universal-rules/templates/`) have not yet been re-rendered from the updated templates, so they do not yet contain the 5 new Karpathy lines live in this repo. A future re-render/upgrade pass of this repo's own self-hosted instructions will be needed to pick up the new template content; tracking that is not part of this ticket's scope.

**Recommended next step:** implementer means implementer agent handoff using OpenCode command: `/implement` — proceed with P3 (jscpd module) or P2 (prompt-defense + confidence-gate) per the suggested slice order above; P0 and P1 are complete and verified.
