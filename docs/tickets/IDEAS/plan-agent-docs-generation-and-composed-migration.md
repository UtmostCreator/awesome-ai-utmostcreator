# Architecture Plan — Agent Docs Generation (agents.md + AGENTS-MANIFEST) + Composed-Model Migration

- Ticket: none
- Source: user request for a contributor-first extensibility on-ramp, grounded via 3 read-only
  researchers → repository-reviewer `CONFIRM-WITH-ADJUSTMENTS` verdict (every cited claim verified
  true) → architect design (`scratchpad/architect-design-spec.md`, six resolutions + 9 slices +
  4-plan split)
- Generated: 2026-07-09
- Plan file: docs/tickets/IDEAS/plan-agent-docs-generation-and-composed-migration.md
- Type: generator + migration slice group (S4, S5) — second of a deliberately-split 4-plan set.
  **Depends on S3** (`render-all.php` + `.opencode` third-dest) from
  `plan-primitive-quickwins-globify-gotcha-renderall.md`; S5 depends on S4 within this plan. Companion
  plans: `plan-add-primitive-pipeline-and-fleet.md` (S7 depends on S1–S6, i.e. on this plan's S4/S5),
  and `plan-extensibility-onramp-docs.md` (S9 documents these slices and reconciles the wiring
  companion plans).
- Risk: medium (S4 introduces two frontmatter fields and a new generator that must reconcile two
  existing hand-maintained inventories; S5 completes a composed-model migration whose cost is the
  main unknown). No byte-parity gate or composed model is weakened or forked; S5 *completes* the
  composed model rather than adding a parallel path.

## Context

Agent creation is the highest-friction primitive today: up to 7 manual registry touches + 4 regens.
Two of those touches are hand-maintained-yet-CI-drift-enforced doc inventories: `docs/ai/agents.md`
(routing table + validator-anchor comment, enforced at `validate-ai-config.php:335`) and
`docs/ai/AGENTS-MANIFEST.md` (parsed by `validate-agent-assessment-frontmatter-drift.php:96` and
`validate-script-access.php:40`). Meanwhile the composed permission model is **incomplete**: only some
agents use `aiPermissionAgentCompositions()`; others still ride raw frontmatter
(`generate-claude-settings.php:19-20`). This split is load-bearing for any single-frontmatter-field
doc-generation design — a generator projecting from frontmatter must know whether the composed model is
the single source of truth or one of two paths.

## Problem

1. **Two hand-maintained agent inventories drift independently.** `agents.md` and `AGENTS-MANIFEST.md`
   are both hand-maintained yet CI-enforced — the exact "hand-maintained-yet-enforced" trap. Generating
   only `agents.md` closes the trap by half; both must derive from one source of truth or the manifest
   still rots.
2. **No single frontmatter source of truth for the doc tables.** The routing/manifest tables encode
   lifecycle, risk, mutation, gate, and per-provider surface facts that are re-derived by hand today.
   Without two additive frontmatter fields (`lifecycle`, `risk`) the rest cannot be derived
   deterministically.
3. **The composed permission model is incomplete (load-bearing).** Some agents ride raw frontmatter.
   Until migration completes, a coverage assertion (from the pipeline plan) cannot safely enforce that
   every agent has a composed block — it can only warn. The migration posture must be decided and
   executed, not left ambiguous.

## Target Outcome

- Two additive agent frontmatter fields (`lifecycle`, `risk`) exist as the single source of truth for
  the doc tables; everything else is derived, not hand-typed.
- A `generate-agent-docs.php --check|--write` emits **both** `docs/ai/agents.md` **and**
  `docs/ai/AGENTS-MANIFEST.md` from one shared agent inventory, and the three existing validators become
  `--check` consumers instead of hand-maintenance enforcers.
- The composed permission model is the single path (composed-only): remaining raw-frontmatter agents are
  migrated, and the composition-coverage verifier (owned by the pipeline plan) can flip from `--warn` to
  enforce once migration completes.

## In Scope

- **S4 — agents.md + AGENTS-MANIFEST generator (Med, deps S3).**
  - Add exactly TWO additive agent frontmatter fields: `lifecycle:` (one of orchestration / discovery /
    planning / execution / validation / review / release / post-install / agent-factory / runtime-safety)
    and `risk:` (one of low / medium / high / critical).
  - Derive the rest instead of hand-typing: `Mutating` = `permission.edit != deny`; `Gate` = derived from
    `lifecycle` (add an explicit `gate: bool` field only where a counterexample exists);
    OpenCode/GitHub surface columns = mode/hidden + template dir + renderer rules; `Purpose` = first
    sentence of `description`; the `agents.md` description column = verbatim `description`.
  - New `tools/ai/generate-agent-docs.php --check|--write` emits BOTH `docs/ai/agents.md` and
    `docs/ai/AGENTS-MANIFEST.md` from one shared agent inventory (per AC-3).
  - Convert `validate-ai-config.php:332`, `validate-agent-assessment-frontmatter-drift.php:96`, and
    `validate-script-access.php:40` into `--check` consumers of the generated artifacts.
  - The generator does NOT replace provider tool registries or `compositions.php` — provider-specific
    grants stay; the docs become a projection only.
- **S5 — composed-model migration + coverage verifier warn→enforce (Med, deps S4).**
  - Posture is **COMPOSED-ONLY** (not dual-path): migrate the remaining raw-frontmatter agents
    (`generate-claude-settings.php:19-20`) onto `aiPermissionAgentCompositions()`.
  - The `validate-agent-composition-coverage.php --check` verifier (built in
    `plan-add-primitive-pipeline-and-fleet.md`) runs in `--warn` mode until this migration completes,
    then flips to enforce (hard CI fail). This plan owns completing the migration; the pipeline plan owns
    building the verifier.

## Out Of Scope (Things To Avoid)

- **No `packages/**` edits in the planning phase** (NAC-1). Implementation writes to canonical agent
  frontmatter and generated docs go through the explicit approval gate.
- **Do not weaken, bypass, or fork the composed permission model or byte-parity `--check` gates**
  (NAC-2). S5 *completes* the composed model; it does not add a second permission path. The generator
  does not replace tool registries/compositions.
- **Do not create a second renderer or pipeline** (NAC-3). `generate-agent-docs.php` projects from the
  existing agent inventory; the three validators become consumers of one generated artifact, not new
  parallel enforcers.
- **No structured Claude handoff field** (NAC-4) — the manifest/routing tables stay prose-compatible for
  Claude handoffs.
- **No installed-kit authoring surfaces** (NAC-5).
- **Do not modify `architect.md` or `architecture-plan-writer.md`** (NAC-6).
- Do NOT pull the glob-ify/gotcha/render-all quick wins (S1–S3), the discriminated primitive spec,
  scaffold-primitive, reachability verifier, the add-primitive workflow, or the on-ramp docs into this
  plan — those belong to the other three plans. Keep this plan bounded to S4/S5.

## Affected Paths

- `packages/ai-universal-rules/templates/{core,optional}/agents/*.md` (S4 — add `lifecycle:`/`risk:`
  frontmatter; write deferred to implementation under the `packages/**` approval gate).
- `docs/ai/agents.md` (S4 — becomes a GENERATED artifact).
- `docs/ai/AGENTS-MANIFEST.md` (S4 — becomes a GENERATED artifact from the same inventory).
- `tools/ai/generate-agent-docs.php` (S4 — NEW generator).
- `tools/ai/validate-ai-config.php:332` (S4 — convert to `--check` consumer).
- `tools/ai/validate-agent-assessment-frontmatter-drift.php:96` (S4 — convert to `--check` consumer).
- `tools/ai/validate-script-access.php:40` (S4 — convert to `--check` consumer).
- `tools/ai/install/permission-layers/compositions.php` (S5 — add composed entries for migrated agents).
- `tools/ai/generate-claude-settings.php:19-20` (S5 — remove raw-frontmatter path once migration
  completes).
- `docs/ai/source-of-truth.md` (S4 — record `lifecycle`/`risk` as SoT for the doc tables; the on-ramp
  docs plan S9 owns the fuller contract write-up).
- `tests/php/` (S4/S5 — byte-parity + coverage tests, see Verification Plan).

## Contracts And Boundaries

- **Dependency on S3**: `generate-agent-docs.php` and the composed-model regen rely on the single
  `render-all.php` wrapper (S3, `plan-primitive-quickwins-globify-gotcha-renderall.md`) so the three
  provider agent trees regenerate together. Do not start S4 before S3 lands.
- **Single source of truth**: `lifecycle`/`risk` frontmatter is the SoT for the doc tables
  (`docs/ai/source-of-truth.md`); the doc tables become a projection. The generator must NOT duplicate or
  replace provider tool registries or `compositions.php` grants.
- **Composed-only posture (Resolution 4)**: the chosen posture is composed-only, migrating remaining
  raw-frontmatter agents. **This posture is contestable** — if the composed-only ruling is contested,
  route that one decision to `workflow-auditor` before implementing S5. The fallback is a documented
  raw-frontmatter allowlist (dual-path), marked `unknown`, which is non-blocking for S1/S3/S4. This is
  recorded as an open decision, not resolved here.
- **Warn→enforce sequencing**: the coverage verifier (owned by the pipeline plan) stays `--warn` until
  this migration completes, then enforces — the same two-step reconciliation pattern used elsewhere in
  the repo (regen once, then flip the gate to failing atomically).

## Todo Plan

Priority-grouped, unchecked.

- [ ] P0: S4 — Add the two additive frontmatter fields `lifecycle:` and `risk:` to every canonical
      agent template (enumerated values only), under the `packages/**` approval gate.
- [ ] P0: S4 — Build `tools/ai/generate-agent-docs.php --check|--write` emitting BOTH `docs/ai/agents.md`
      and `docs/ai/AGENTS-MANIFEST.md` from one shared agent inventory.
- [ ] P1: S4 — Derive `Mutating` (`permission.edit != deny`), `Gate` (from `lifecycle`, `gate: bool`
      only on counterexamples), surface columns (mode/hidden + template dir + renderer rules),
      `Purpose` (first sentence of description), and verbatim description — no hand-typed table cells.
- [ ] P1: S4 — Convert `validate-ai-config.php:332`,
      `validate-agent-assessment-frontmatter-drift.php:96`, and `validate-script-access.php:40` into
      `--check` consumers of the generated docs.
- [ ] P1: S4 — Record `lifecycle`/`risk` as the doc-table SoT in `docs/ai/source-of-truth.md` (fuller
      contract write-up deferred to S9).
- [ ] P2: S5 — [DECISION GATE] If the composed-only posture (Resolution 4) is contested, route the
      composed-only-vs-dual-path ruling to `workflow-auditor` BEFORE migrating; otherwise proceed.
- [ ] P2: S5 — Migrate remaining raw-frontmatter agents (`generate-claude-settings.php:19-20`) onto
      `aiPermissionAgentCompositions()` composed entries in `compositions.php`.
- [ ] P2: S5 — Once migration completes, flip `validate-agent-composition-coverage.php` (built in the
      pipeline plan) from `--warn` to enforce; remove the raw-frontmatter path from
      `generate-claude-settings.php`.

## Acceptance Criteria

Each AC names its Type / what it Proves / how it is Verified.

- [ ] AC-01 (S4, explicit — AC-3): Type=single-SoT reconciliation. Proves `agents.md` AND
      `AGENTS-MANIFEST.md` are both generated/reconciled from one SoT. Verified by
      `generate-agent-docs.php --write` emitting both files and `generate-agent-docs.php --check` passing
      against the committed copies.
- [ ] AC-02 (S4, byte-parity): Type=byte-parity gate. Proves the generator output is deterministic.
      Verified by a new PHPUnit byte-parity test that regenerates both docs and asserts byte-identical
      output to the committed files.
- [ ] AC-03 (S4, inferred): Type=derivation correctness. Proves derived columns match their frontmatter
      sources. Verified by a test asserting `Mutating` == (`permission.edit != deny`), `Purpose` == first
      sentence of `description`, and description column == verbatim `description` for every agent.
- [ ] AC-04 (S4, consumer conversion): Type=validator role change. Proves the three validators now
      consume the generated artifacts. Verified by `validate-ai-config.php`,
      `validate-agent-assessment-frontmatter-drift.php`, and `validate-script-access.php` all passing in
      `--check` mode against the generated docs (no hand-maintenance path remaining).
- [ ] AC-05 (S5, explicit — AC-4): Type=posture statement + migration completeness. Proves the
      composed-model migration posture is stated (composed-only) and executed. Verified by every agent
      having a `compositions.php` entry and `generate-claude-settings.php` no longer branching on
      raw frontmatter (`:19-20`).
- [ ] AC-06 (S5, enforcement flip): Type=coverage enforcement. Proves the coverage verifier enforces
      once migration completes. Verified by `validate-agent-composition-coverage.php` passing in enforce
      mode with zero missing composed blocks.
- [ ] AC-07 (negative — NAC-2): Type=no-fork/no-weaken assertion. Proves the generator did not replace
      tool registries/compositions and S5 added no second permission path. Verified by review that
      provider tool registries and `compositions.php` grants are unchanged in shape and no dual-path
      permission branch remains.
- [ ] AC-08 (negative — NAC-1): Type=scope guard. Proves no `packages/**` edit happened in the planning
      phase. Verified by this plan producing no `packages/**` diff.

## Verification Plan

Each step names the exact gate or test that proves an AC.

- `php tools/ai/generate-agent-docs.php --check` — proves AC-01, AC-04 (both docs reconciled, validators
  consume).
- New `generate-agent-docs.php` byte-parity PHPUnit test — proves AC-02 (byte-identical regen).
- New derivation-correctness test — proves AC-03 (`Mutating`/`Purpose`/description derivations).
- `php tools/ai/validate-ai-config.php`, `validate-agent-assessment-frontmatter-drift.php`,
  `validate-script-access.php` in `--check` mode — proves AC-04.
- `php tools/ai/generate-agent-permissions.php --check` and `generate-claude-settings.php` — proves the
  composed model still renders cleanly after S5 (NAC-2).
- `php tools/ai/validate-agent-composition-coverage.php` in enforce mode — proves AC-05, AC-06.
- `php tools/ai/render-all.php --check` (from S3) — proves the three agent trees regenerate cleanly with
  the new frontmatter fields.
- `tests/php` full-install-validation (`full-install-validation.php`) — proves the slice group installs
  cleanly end-to-end.

## Risks And Rollback

- **Medium (S4)**: reconciling two hand-maintained inventories into one generator risks a first-run diff;
  mitigated by the regen-once-then-check pattern (run `--write` once, then the `--check` gate goes green
  atomically) and the byte-parity test (AC-02). Rollback: revert `generate-agent-docs.php` and restore
  the validators to their pre-generation enforcement mode.
- **Medium (S5)**: composed-only migration cost is the main unknown; the posture is contestable and is
  gated `warn→enforce` with a `workflow-auditor` decision gate before migrating. Fallback is a documented
  raw-frontmatter allowlist (dual-path), marked `unknown`, non-blocking for S1/S3/S4. Rollback: keep the
  verifier in `--warn` mode and retain the raw-frontmatter path until migration is complete.
- **Dependency risk**: S4 depends on S3 (`render-all.php`); if S3 stalls, S4 is blocked. S5 depends on
  S4. Documented as explicit sequencing, not a hidden assumption.
- **Unknown (non-blocking)**: composed-only-vs-dual-path is an open ruling routed to `workflow-auditor`
  if contested — recorded as `unknown`, not resolved here.

## Handoff Notes

- **Recommended next step**: `workflow-auditor means workflow-auditor agent handoff` FIRST, but only if
  the composed-only posture (Resolution 4) is contested — to rule on composed-only vs. dual-path before
  S5. If the posture is accepted, `implementer means implementer agent handoff` to build S4 (once S3 has
  landed), then S5.
- S4 is blocked on S3 (`render-all.php` + `.opencode` third-dest) from
  `plan-primitive-quickwins-globify-gotcha-renderall.md`; S5 is blocked on S4.
- This plan's S4/S5 are prerequisites for `plan-add-primitive-pipeline-and-fleet.md` (S7 depends on
  S1–S6). The coverage verifier itself is BUILT in that pipeline plan; this plan owns COMPLETING the
  migration and flipping the verifier to enforce.
- Claude handoffs stay prose per `docs/ai/handoff-contract.md`; no structured handoff field (NAC-4).
