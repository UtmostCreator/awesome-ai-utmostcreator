# Architecture Plan — Primitive Quick Wins (Glob-ify Skills/Capabilities + Gotcha Primitive + render-all Wrapper)

- Ticket: none
- Source: user request for a contributor-first extensibility on-ramp, grounded via 3 read-only
  researchers (wiring/pickup, live PHP-automation inventory, policy/fleet/feasibility) →
  repository-reviewer `CONFIRM-WITH-ADJUSTMENTS` verdict (every cited claim verified true) →
  architect design (`scratchpad/architect-design-spec.md`, six resolutions + 9 slices + 4-plan split)
- Generated: 2026-07-09
- Plan file: docs/tickets/IDEAS/plan-primitive-quickwins-globify-gotcha-renderall.md
- Type: additive quick-win slice group (S1, S2, S3) — first of a deliberately-split 4-plan set. These
  three slices are low-risk, additive, contract-consistent, and have **no dependencies on the other
  three plans**, so they can ship first and independently. Companion plans:
  `plan-agent-docs-generation-and-composed-migration.md` (S4 depends on S3 here),
  `plan-add-primitive-pipeline-and-fleet.md` (S7 depends on S1–S6), and
  `plan-extensibility-onramp-docs.md` (S9 documents all slices).
- Risk: low (S1, S2); low-medium (S3 — extends the renderer to a third destination). No composed
  permission model or byte-parity gate is weakened or forked.

## Context

This repo ships six primitive types (command, workflow, skill, capability, agent, gotcha) from one
canonical source tree (`packages/ai-universal-rules/templates/{core,optional}`) to three providers
(Copilot/`.github`, OpenCode/`.opencode`, Claude/`.claude`) through the mandated pipeline shape
`canonical source → provider registry → renderer → provider output → validation/drift check`
(`docs/ai/adapter-contract.md:72-87`). Agents, commands, and workflows already auto-pick-up today:
they are `type=dir` pack entries whose writers glob `*.md` (`tools/ai/install/fs-writers.php`) and
the catalog recursively scans template dirs (`tools/ai/generate-ai-catalog.php`) — drop a file, regen,
all three providers install it, no `packs.php` edit. Two primitive families break this parity, and the
three-provider agent render+wire path is split across two owners.

## Problem

Three additive gaps block a clean, low-friction on-ramp for the easy primitive types:

1. **Skills and capabilities are NOT auto-picked-up.** Skills are 6 hardcoded per-file `packs.php`
   entries (verified lines 169/170/180/181/205/206); capabilities are per-dir `packs.php` rows. Until
   these are glob-ified to a single dirs-based entry (parity with agents/commands/workflows), a clean
   skill/capability scaffold is impossible — every new skill would need a hand-added `packs.php` row.
2. **No structured gotcha primitive.** Gotchas live only in a monolithic
   `templates/snippets/anti-pattern-examples.md`. There is no one-file-per-gotcha
   `templates/gotchas/<slug>.md` surface, no catalog prefix, and no glob injection.
3. **Three-provider agent render+wire is split and manual.** `render-adapters.php --write` writes ONLY
   `.claude/agents` + `.github/agents` (verified `render-adapters.php:72-73`); `.opencode/agents` is
   written by the INSTALLER (`executor.php:128 → aiInstallerCopyDirAsOpenCodeAgents`). There is no
   single command that regenerates all three provider agent trees, permissions, and catalog together
   and then drift-checks — so a contributor cannot regenerate confidently in one step.

## Target Outcome

- Skills and capabilities auto-pick-up on drop-a-file-and-regen, exactly like agents/commands/workflows,
  with installer round-trips green and shared-target siblings preserved.
- A structured `templates/gotchas/<slug>.md` primitive exists (one file per gotcha), registered in the
  catalog prefix map and injected via a glob writer, with the monolith's content migrated to per-file
  entries.
- A single `render-all.php --check|--write` wrapper regenerates all three provider agent trees (Claude,
  Copilot, **and** OpenCode via the installer route), permissions, and catalog, ending on a green drift
  `--check`.

## In Scope

- **S1 — Glob-ify skills + capabilities packs (quick win, Low, no deps).**
  - Replace the 6 hardcoded per-file skill `packs.php` entries and the per-dir capability rows with a
    single dirs-based entry each (a `skill-dirs`/`capability-dirs`-shaped glob entry, mirroring the
    existing agent/command/workflow dir entries).
  - Preserve `fs-writers.php:37-41` sibling-preservation: the shared skills target hosts standalone
    skill siblings (`ai-search`, `ai-scripts`), so a blind delete-tree over that shared target must NOT
    be introduced — the glob writer must add/update its own files without destroying siblings (NAC:
    respect `fs-writers.php:37-41`, per AC-11).
- **S2 — Structured gotcha primitive (quick win, Low, no deps).**
  - Add a `templates/gotchas/<slug>.md` surface (one file per gotcha, valid OpenCode frontmatter).
  - Register the `templates/gotchas/` prefix in the catalog prefix map (`ai_catalog_lib.php:654-663`,
    per AC-12).
  - Add glob-based injection for the new prefix (mirroring existing glob-injected snippet families).
  - Migrate the monolithic `templates/snippets/anti-pattern-examples.md` content into per-file
    `<slug>.md` entries.
- **S3 — render-all.php wrapper + .opencode third-dest (quick win, Low-Med, no deps).**
  - New `tools/ai/render-all.php --check|--write` that composes, in order: (1)
    `generate-agent-permissions.php --write`, (2) `render-adapters.php --write` (`.claude` + `.github`),
    (3) EXTEND `render-adapters` to a THIRD destination `.opencode/agents` using an OpenCode-native
    writer so a single tool owns all three agent trees, (4) `generate-ai-catalog.php --write`, (5) a
    final `--check` gate.
  - The `.opencode/agents` writer must produce body-parity output equivalent to the current installer
    copy helper (`aiInstallerCopyDirAsOpenCodeAgents`), verified against it.

## Out Of Scope (Things To Avoid)

- **No `packages/**` edits are performed in this planning phase** (NAC-1). This plan documents the
  design; `packages/**` is deny-ruled for edits in the active session and any tool/agent that writes
  canonical source must surface that as an explicit approval gate at implementation time.
- **Do not weaken, bypass, or fork the composed permission model or the byte-parity `--check` gates**
  (NAC-2). S3's render-all wrapper composes the existing tested gates; it must not introduce a second
  parity mechanism.
- **Do not create a second renderer or pipeline** (NAC-3). S3 extends the mandated
  canonical→registry→renderer→output→drift-check shape to a third destination; it does not replace it.
- **No installed-kit authoring surfaces** (NAC-5). These slices only touch contributor-facing canonical
  source and its render/catalog toolchain.
- **Do not modify `architect.md` or `architecture-plan-writer.md`** (NAC-6).
- **No structured Claude handoff field** (NAC-4) — irrelevant to these slices but carried for the set.
- Do NOT pull agent-docs generation, composed-model migration, the discriminated primitive spec,
  scaffold-primitive, the add-primitive workflow, or the on-ramp docs into this plan — those are S4–S9,
  owned by the other three plans. Keep this plan bounded to S1/S2/S3.

## Affected Paths

- `tools/ai/install/packs.php` (S1 — replace per-file skill entries + per-dir capability rows with
  glob dir entries).
- `tools/ai/install/fs-writers.php` (S1 — glob writer must honor sibling-preservation at lines 37-41).
- `packages/ai-universal-rules/templates/gotchas/` (S2 — NEW per-file gotcha surface; write deferred to
  implementation under the `packages/**` approval gate).
- `packages/ai-universal-rules/templates/snippets/anti-pattern-examples.md` (S2 — migrate content out;
  same approval gate).
- `tools/ai/ai_catalog_lib.php:654-663` (S2 — register the `templates/gotchas/` prefix).
- `tools/ai/generate-ai-catalog.php` (S2 — glob injection for the new prefix).
- `tools/ai/render-all.php` (S3 — NEW wrapper CLI).
- `tools/ai/render-adapters.php:72-73` (S3 — extend to a third `.opencode/agents` destination).
- `tools/ai/install/executor.php:128` / `aiInstallerCopyDirAsOpenCodeAgents` (S3 — reference for
  body-parity of the OpenCode-native writer).
- `tests/php/` (S1/S2/S3 — new tests, see Verification Plan).

## Contracts And Boundaries

- Extend the mandated pipeline shape (`adapter-contract.md:72-87`), never replace it — S3 adds a third
  render destination inside the existing renderer, not a parallel tool.
- Reuse existing tested building blocks: `aiCompareOrWrite()` (the `--check`/`--write` byte-parity
  primitive), `render-adapters.php --check`, `generate-ai-catalog.php --check`,
  `generate-agent-permissions.php`. Do not duplicate them.
- S1 must respect `fs-writers.php:37-41` sibling-preservation — the shared skills target is also home to
  standalone skill siblings; adding/updating files must not delete-tree over them.
- S2's new prefix must be registered in the single catalog prefix map (`ai_catalog_lib.php:654-663`), so
  auto-scan + glob injection do the pickup — no `packs.php` row per gotcha.
- The `.opencode/agents` writer in S3 must be body-parity-equivalent to the installer copy helper; this
  is a correctness boundary, not a new format.

## Todo Plan

Priority-grouped, unchecked. P0 = load-bearing prerequisites for the plan set; P1 = the additive
primitive; P2 = follow-on wiring/tests.

- [ ] P0: S3 — Add `tools/ai/render-all.php --check|--write` composing (1) `generate-agent-permissions
      --write`, (2) `render-adapters --write` (`.claude`+`.github`), (3) `render-adapters` third-dest
      `.opencode/agents` via an OpenCode-native writer, (4) `generate-ai-catalog --write`, (5) final
      `--check` gate.
- [ ] P0: S3 — Extend `render-adapters.php` (currently `:72-73` writes only `.claude`+`.github`) to
      write `.opencode/agents`, and verify byte/body parity against
      `aiInstallerCopyDirAsOpenCodeAgents` (`executor.php:128`).
- [ ] P1: S1 — Replace the 6 hardcoded per-file skill `packs.php` entries (lines 169/170/180/181/205/206)
      with a single `skill-dirs` glob entry; replace per-dir capability rows with a `capability-dirs`
      glob entry.
- [ ] P1: S1 — Ensure the glob writer honors `fs-writers.php:37-41` sibling-preservation (no delete-tree
      over the shared skills target; `ai-search`/`ai-scripts` siblings survive).
- [ ] P1: S2 — Create the `templates/gotchas/<slug>.md` per-file surface with valid OpenCode frontmatter.
- [ ] P1: S2 — Register the `templates/gotchas/` prefix in `ai_catalog_lib.php:654-663` and add glob
      injection in `generate-ai-catalog.php`.
- [ ] P2: S2 — Migrate `templates/snippets/anti-pattern-examples.md` monolith content into per-file
      `templates/gotchas/<slug>.md` entries.
- [ ] P2: S1/S2/S3 — Run `render-all.php --write` once to reconcile, then confirm `render-all.php
      --check`, `generate-ai-catalog.php --check`, and `generate-agent-permissions.php --check` all pass.

## Acceptance Criteria

Each AC names its Type / what it Proves / how it is Verified.

- [ ] AC-01 (S1, explicit — AC-11): Type=blast-radius test. Proves the glob-ified skills installer
      round-trips and preserves siblings. Verified by `InstallerSafetyTest` asserting `ai-search` and
      `ai-scripts` skill siblings survive a reinstall (no delete-tree over the shared skills target).
- [ ] AC-02 (S1, explicit): Type=drift gate. Proves the glob-ified skills/capabilities entries produce a
      clean catalog. Verified by `generate-ai-catalog.php --check` passing after regen.
- [ ] AC-03 (S2, explicit — AC-12): Type=registration. Proves the new gotcha prefix is discoverable.
      Verified by the `templates/gotchas/` prefix being present in `ai_catalog_lib.php:654-663` and a
      dropped `<slug>.md` appearing in the generated catalog with no `packs.php` edit.
- [ ] AC-04 (S2, inferred): Type=migration completeness. Proves no gotcha content is lost. Verified by
      every anti-pattern entry from `anti-pattern-examples.md` existing as a per-file
      `templates/gotchas/<slug>.md` entry after migration.
- [ ] AC-05 (S3, explicit — AC-2): Type=three-provider coverage. Proves render+wire covers all three
      provider agent trees including the OpenCode installer route. Verified by `render-all.php --write`
      producing `.claude/agents`, `.github/agents`, AND `.opencode/agents`, and `render-all.php --check`
      passing.
- [ ] AC-06 (S3, inferred): Type=body-parity. Proves the OpenCode-native writer matches the installer
      copy helper. Verified by comparing `.opencode/agents` output from `render-all.php` against
      `aiInstallerCopyDirAsOpenCodeAgents` output (byte/body identical).
- [ ] AC-07 (negative — NAC-3): Type=no-fork assertion. Proves S3 did not introduce a second render or
      pipeline. Verified by review that `render-all.php` only composes existing tools and that the third
      destination lives inside `render-adapters.php`, not a parallel CLI.
- [ ] AC-08 (negative — NAC-1): Type=scope guard. Proves no `packages/**` edit happened in the planning
      phase. Verified by this plan producing no `packages/**` diff; implementation writes are gated.

## Verification Plan

Each step names the exact gate or test that proves an AC.

- `php tools/ai/render-all.php --check` — proves AC-05, AC-07 (single command, all three trees clean).
- `php tools/ai/generate-ai-catalog.php --check` — proves AC-02, AC-03 (catalog clean after glob-ify +
  gotcha prefix).
- `php tools/ai/generate-agent-permissions.php --check` — proves composed permissions unchanged (NAC-2).
- New `InstallerSafetyTest` case (blast-radius after `packs.php` glob-ify) — proves AC-01 (siblings
  `ai-search`/`ai-scripts` survive reinstall).
- New glob-ified skills installer round-trip test (sibling-preservation) — proves AC-01/AC-02.
- New per-file gotcha migration test — proves AC-04 (every monolith entry has a per-file counterpart).
- New `.opencode/agents` body-parity test vs. `aiInstallerCopyDirAsOpenCodeAgents` — proves AC-06.
- `tests/php` full-install-validation (`full-install-validation.php`) — proves the slice group installs
  cleanly end-to-end.

## Risks And Rollback

- **Low (S1)**: no existing test asserts the per-file skill entries, so glob-ifying is low-risk — but a
  blind delete-tree over the shared skills target would race standalone skill siblings; mitigated by the
  `fs-writers.php:37-41` sibling-preservation requirement and the `InstallerSafetyTest` blast-radius
  case (AC-01). Rollback: restore the per-file `packs.php` entries.
- **Low (S2)**: additive new prefix + per-file surface; monolith migration is content-move only.
  Rollback: remove the `templates/gotchas/` prefix and restore the monolith.
- **Low-Medium (S3)**: extending `render-adapters` to a third destination risks body drift vs. the
  installer copy helper; mitigated by the body-parity test (AC-06) and the OpenCode-native writer choice.
  Rollback: `render-all.php` is a new wrapper — deleting it and reverting the third-dest extension
  restores the split render-adapters/installer path.
- **Unknown (non-blocking)**: branch mismatch — the working branch is
  `fix/opencode-agent-body-parity`, not a feature branch matching this plan set; per the architect
  decision these plans live under `docs/tickets/IDEAS/`, so this is non-blocking.

## Handoff Notes

- Recommended next step: `implementer means implementer agent handoff` to build S1/S2/S3 — these are
  the additive, no-dependency quick wins and are safe to ship first. `packages/**` writes at
  implementation time must go through the explicit approval gate (NAC-1).
- S3 (render-all + `.opencode` third-dest) is a prerequisite for
  `plan-agent-docs-generation-and-composed-migration.md` (S4 depends on S3) — land S3 before starting
  that plan.
- Claude handoffs stay prose per `docs/ai/handoff-contract.md`; no structured handoff field is added
  (NAC-4).
