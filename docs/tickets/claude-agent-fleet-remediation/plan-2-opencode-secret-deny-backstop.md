# Architecture Plan — OpenCode Secret-Path Deny Backstop

- Ticket: none (branch: claude-agent-fleet-remediation)
- Source: architect handoff (OpenCode secret-path deny-backstop fix; closes reviewer.md open MAJOR)
- Generated: 2026-07-07 19:33:34 BST
- Plan file: docs/tickets/claude-agent-fleet-remediation/plan-2-opencode-secret-deny-backstop.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-2-opencode-secret-deny-backstop.md` and move it into `archive/` under this branch folder (`docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-2-opencode-secret-deny-backstop.md`). See "Archive On Completion" below for the exact steps.

## Context

OpenCode's native `permission.bash` map is the real enforcement surface for reader wrappers (`docs/ai/security.md:21`). It resolves bash rules with `.findLast()` = last-matching-rule-wins in FILE ORDER (`render-adapters.php:98-107`; tests `PermissionRenderAdaptersTest.php:144-190`, `AgentPermissionPolicyTest.php:375-385`). A deny must therefore render AFTER any overlapping allow to take effect.

The `reviewer` agent has a `'*': deny` floor (`.opencode/agents/reviewer.md:21`), but `preview-file.sh *: allow` (line 62) reopens all paths for that wrapper. The same shape applies to its `'*': deny`-floor read-agent siblings that grant the same reader wrappers. Copilot and Claude do not have this native surface; they keep the prompt-level Sensitive File Rule as an honest documented fallback.

## Problem

Reader wrappers (`preview-file.sh`, `ai-search.sh`, etc.) that are granted `allow` on OpenCode can currently open secret paths (`.env`, `*.pem`, `*.key`, `*.crt`, `id_rsa*`, `id_ed25519*`, `secrets.*`, `credentials.*`, `auth.json`, `.npmrc`) because there is no permission-level deny backstop after the wrapper's `allow` entry. This is reviewer.md's open MAJOR: the only protection today is prompt-level prose, which is not enforced by the OpenCode permission engine.

Two structural blockers prevent simply adding a `deny` line:

- **BLOCKER A** — `render-adapters.php:116-118` DROPS any bash entry whose `effect === $starEffect`. For a `'*': deny` agent, every `deny` is stripped as a redundant no-op, so a naive secret-deny never renders.
- **BLOCKER B** — the renderer emits non-floor entries in `$model` composition order, and `deny_packs` apply BEFORE `allow_packs`/`ask_packs` (`compose.php:114-122`). An ordinary deny_pack would render BEFORE the overlapping wrapper allow, so `.findLast()` would pick the allow and the deny would be inert.

## Target Outcome

Add a real, permission-level, correctly-ordered secret-path `deny` backstop to the OpenCode `permission.bash` map for `reviewer` and its `'*': deny`-floor read-agent siblings, so reader wrappers cannot open secret paths. The backstop is OpenCode-scoped only; Copilot/Claude keep the prompt-level Sensitive File Rule as an honest, documented fallback. This closes reviewer.md's open MAJOR.

## In Scope

- Renderer retention rule (Part 1): a class/provenance-based rule in `render-adapters.php:116-118` so a `deny` whose `class` is `exception` or the dedicated backstop pack class is RETAINED even when `effect === $starEffect`; only `layer`-class redundant denies (e.g. `git branch*`) remain stripped. Renderer stays a pure function of the model (no pack-name coupling).
- Backstop content (Part 2): a new effect-homogeneous deny pack `core.safe_read.deny_secret_reads` in `packs.php`, wired via a new `backstop_deny_packs` compose lane.
- New `backstop_deny_packs` compose lane in `compose.php`, applied AFTER `ask_packs` and immediately BEFORE `exceptions`, guarded by a non-empty check (mirroring `if ($denyPacks !== [])` at `compose.php:114`) so unaffected agents stay byte-identical.
- Wiring the new lane key in `agent-spec.php` / `compositions.php`.
- Applying the shared pack to the affected agents: reviewer + `'*': deny`-floor read siblings granting the same wrappers — researcher, architect, release-auditor, workflow-auditor, repository-reviewer, repository-researcher; with per-agent subsetting for architecture-plan-writer and script-runner.
- Regenerating `.opencode/agents/*.md` and `packages/ai-universal-rules/templates/core/agents/*.md` mirrors via `--write` (generated output).
- Updating affected tests (`PermissionComposeTest`, `PermissionRenderAdaptersTest`) and the `testProvenanceMatchesMergeOrder` expectation for the new lane position.
- Updating prose in `docs/ai/security.md` and `reviewer.md`; and adapter-contract.md / agent-script-access.md ONLY if they currently assert "no backstop".

Pattern shape: match full command string incl. bare, `AI_OUTPUT=json ` and `env AI_OUTPUT=json ` prefixes. Bounded reader subset (path-argument wrappers that read/print content): `preview-file.sh`, `ai-search.sh`, `ai-search-multi.sh`, `rg-code.sh`, `fd-files.sh`, `query-usage.sh`, `git-forensics.sh`. Secret globs: `*.env*`, `*.pem`, `*.key`, `*.crt`, `*id_rsa*`, `*id_ed25519*`, `*secrets.*`, `*credentials.*`, `*auth.json*`, `*.npmrc*`.

## Out Of Scope (Things To Avoid)

- Raw `git show`/`log`/`diff`/`blame` revspec access — a revspec is not a filesystem path; a glob would be leaky/over-broad. Stays prompt-only, documented honestly.
- Any OpenCode-enforcement claim for Copilot or Claude; those runtimes keep the prompt-level Sensitive File Rule only.
- Removing or weakening the prompt-level Sensitive File Rule prose (N4).
- Over-broad globs that block legitimate non-secret reads (N5).
- Altering the `'*'` floor or the hard-deny floor of any agent (N6).
- Coupling the renderer to pack names (renderer must stay a pure function of the model).
- Per-agent `exceptions` for the secret deny (collides with the no-duplicate-exception test for a shared pack) — a shared pack is REQUIRED once >=2 agents share the patterns.
- Any change to non-`docs/tickets/` files during THIS plan-writing task (implementation happens later via the implementer agent).
- Growing the slice beyond ~6 core files (excluding regenerated `*.md` mirrors); pause if it grows.

## Affected Paths

- `tools/ai/install/permission-layers/render-adapters.php` — Part 1 retention rule (`:116-118`); respect renderer purity contract (`:7-15`).
- `tools/ai/install/permission-layers/packs.php` — new `core.safe_read.deny_secret_reads` pack.
- `tools/ai/install/permission-layers/compose.php` — new `backstop_deny_packs` lane + provenance ordering (`:114-122`).
- `tools/ai/install/permission-layers/agent-spec.php` and `tools/ai/install/permission-layers/compositions.php` — wire the new lane key.
- `.opencode/agents/*.md` (affected agents) — regenerated via `--write` (generated).
- `packages/ai-universal-rules/templates/core/agents/*.md` mirrors — regenerated via `--write` (generated).
- Tests: `PermissionComposeTest`, `PermissionRenderAdaptersTest` (+ focused new tests below).
- Prose: `docs/ai/security.md`, `.opencode/agents/reviewer.md`; possibly `docs/ai/adapter-contract.md` / `agent-script-access.md` only if they assert "no backstop".

_Note: exact permission-layer file paths above are stated per the architect handoff; the implementer must confirm each path exists before editing (mark `unknown` if a path differs)._

## Contracts And Boundaries

- Renderer purity: `render-adapters.php:7-15` — no pack-name coupling; retention keyed on model `class` only.
- Compose ordering: new lane sits AFTER ask-packs and BEFORE agent `exceptions`; `testProvenanceMatchesMergeOrder` MUST be updated for the new lane position.
- Effect-homogeneity: `testPacksAreEachEffectHomogeneous` — the new pack must be 100% `deny`.
- No-duplicate-exception: `testNoExceptionPatternDuplicatedAcrossTwoOrMoreAgents` — forces a shared pack once >=2 agents share patterns (hence a pack, not per-agent exceptions).
- `'*'`-first: `testRenderOpenCodeBlockAlwaysEmitsStarBashEntryFirst...` — `'*'` bash entry stays first.
- Script-reference registration: `testEveryComposedScriptReferenceIsRegistered` — regex extracts the `.sh` stem before the secret glob, so it stays OK.
- allowedBash projection already filters to allow-only, so deny entries are inertly skipped (Copilot/Claude projection unaffected; AC-6 met free).
- Security-honesty invariant 6 — no false enforcement claim across runtimes.
- `ask`-floor agents (repository-researcher, repository-reviewer) benefit too: deny != ask floor, so the Part-1 filter is not even engaged for them.

## Todo Plan

- [ ] P0: **Confirm OpenCode glob suffix-match semantics** for `*.env*` mid-command against the cited `permission/index.ts evaluate()` matcher BEFORE finalizing patterns. Mark `unknown` until confirmed; if suffix-in-arg won't match, adjust patterns (e.g. `* *.env` + `* *.env *`). This is the top implementation-time verification gate.
- [ ] P0: Part 1 — add the class-based retention rule in `render-adapters.php:116-118`: a `deny` whose `class` is `exception` or the backstop pack class is RETAINED even when `effect === $starEffect`; keep `layer`-class redundant denies stripped. No pack-name coupling.
- [ ] P0: Part 2 — add the effect-homogeneous `core.safe_read.deny_secret_reads` deny pack in `packs.php` with the bounded reader subset × secret globs × prefix variants (bare, `AI_OUTPUT=json `, `env AI_OUTPUT=json `).
- [ ] P0: Add the `backstop_deny_packs` compose lane in `compose.php`, applied AFTER `ask_packs` and immediately BEFORE `exceptions`, guarded by a non-empty check mirroring `if ($denyPacks !== [])` at `compose.php:114`.
- [ ] P0: Wire the new `backstop_deny_packs` lane key through `agent-spec.php` / `compositions.php` and apply the shared pack to reviewer + `'*': deny`-floor read siblings (researcher, architect, release-auditor, workflow-auditor, repository-reviewer, repository-researcher), with per-agent subsetting for architecture-plan-writer and script-runner.
- [ ] P1: Update `testProvenanceMatchesMergeOrder` for the new lane position (after ask-packs, before agent:exceptions).
- [ ] P1: Add focused new tests: (a) filter-survival — an exception/backstop-class deny with `effect === starEffect` survives while a layer-class redundant deny is still stripped; (b) composed-model — `bash scripts/ai/preview-file.sh *.env*` resolves to deny for reviewer AND its render index > the `preview-file.sh *` allow index; (c) projection — allowedBash contains no secret-deny pattern; (d) failing-fixture.
- [ ] P1: Run `generate-agent-permissions.php --check` (expect drift for the intended agents only), then `--write` to regenerate `.opencode/agents/*.md` and the `packages/ai-universal-rules/templates/core/agents/*.md` mirrors.
- [ ] P2: Update prose in `docs/ai/security.md` and `.opencode/agents/reviewer.md` to document the OpenCode-only backstop and the honest Copilot/Claude prompt-level fallback.
- [ ] P2: Update `docs/ai/adapter-contract.md` / `agent-script-access.md` ONLY if they currently assert "no backstop"; otherwise leave untouched.

## Acceptance Criteria

- [ ] AC-01 (N1): Diff is additive-only for secret-deny lines — every other rendered line is byte-identical (`generate-agent-permissions.php --check` before/after shows drift limited to the intended agents; unaffected agents unchanged).
- [ ] AC-02 (N2): The `core.safe_read.deny_secret_reads` pack is 100% `deny` (`testPacksAreEachEffectHomogeneous` passes for it).
- [ ] AC-03: For reviewer's rendered OpenCode block, a secret read (e.g. `bash scripts/ai/preview-file.sh *.env*`) resolves to `deny`, and its render index is GREATER than the `preview-file.sh *` allow index (deny wins under `.findLast()`).
- [ ] AC-04: The `'*'` bash entry remains first in every affected agent (`testRenderOpenCodeBlockAlwaysEmitsStarBashEntryFirst...` passes).
- [ ] AC-05: A layer-class redundant deny (e.g. `git branch*`) is still stripped when `effect === starEffect`; only exception/backstop-class denies survive (filter-survival test passes).
- [ ] AC-06 (N3): allowedBash projection for Copilot/Claude contains NO secret-deny pattern (deny entries inertly skipped by the allow-only projection).
- [ ] AC-07 (N4): The prompt-level Sensitive File Rule prose is unchanged/retained in Copilot/Claude surfaces.
- [ ] AC-08 (N5): No over-broad glob blocks a legitimate non-secret read (patterns bounded to the known secret shapes only).
- [ ] AC-09 (N6): No agent's `'*'` floor or hard-deny floor is altered.
- [ ] AC-10: `composer test:fast` passes; `validate-adapter-drift.php --fail-on-warn` and `validate-generated-artifacts.php` pass.
- [ ] AC-11: `testProvenanceMatchesMergeOrder` passes with the new lane positioned after ask-packs and before agent:exceptions.

## Verification Plan

- `php tools/ai/generate-agent-permissions.php --check` — expect drift for the intended agents only (proves AC-01 scope before write).
- `php tools/ai/generate-agent-permissions.php --write` — regenerate; then `git diff` shows only additive secret-deny lines, each AFTER the overlapping allow, with `'*': deny` still first (proves AC-01, AC-03, AC-04).
- `composer test:fast` — full focused suite including the effect-homogeneity, provenance, star-first, and no-duplicate-exception contracts (proves AC-02, AC-04, AC-11 and regression safety).
- New focused tests: filter-survival (AC-05), composed-model deny-index (AC-03), projection has no secret-deny pattern (AC-06), failing-fixture.
- `php tools/ai/validate-adapter-drift.php --fail-on-warn` — adapter surface parity (part of AC-10).
- `php tools/ai/validate-generated-artifacts.php` — regenerated mirrors consistent (part of AC-10).
- Manual/prose check: Sensitive File Rule prose still present in Copilot/Claude surfaces (AC-07); no `'*'`/hard-deny floor changed (AC-09).

## Risks And Rollback

- **(medium) OpenCode glob suffix-match semantics** for `*.env*` mid-command must be confirmed against the cited `permission/index.ts evaluate()` matcher BEFORE finalizing patterns. Mark `unknown` until confirmed. If suffix-in-arg won't match, adjust patterns (e.g. `* *.env` + `* *.env *`). This is the TOP implementation-time verification gate.
- **(low) Over-broad glob (N5)** — mitigated by binding patterns to known secret shapes; defense-in-depth only.
- **(low) Provenance/byte-stability** — mitigated by guarding the lane with a non-empty check mirroring `if ($denyPacks !== [])` at `compose.php:114`, so unaffected agents stay byte-identical.
- **Rollback:** revert the ~4 core source files (`render-adapters.php`, `packs.php`, `compose.php`, `agent-spec.php`/`compositions.php`), regenerate via `--write`, and confirm with `generate-agent-permissions.php --check`. Success signal: `--check` reports no drift and `composer test:fast` is green.
- **Risk level:** medium. Keep the slice bounded to ~6 core files (excluding regenerated `*.md` mirrors); pause if it grows.

## Handoff Notes

- Start implementation with the P0 glob-semantics confirmation gate; do not finalize patterns until the `evaluate()` matcher behavior for `*.env*` mid-command is confirmed (mark `unknown` until then).
- Renderer change (Part 1) must stay a pure function of the model — key retention on `class`, never on pack name.
- The shared pack is REQUIRED (not per-agent exceptions) because `testNoExceptionPatternDuplicatedAcrossTwoOrMoreAgents` forces a pack once >=2 agents share the patterns.
- Confirm each permission-layer file path exists before editing; if a path differs from this plan, mark `unknown` and reconcile before proceeding.
- Recommended next step: hand off to the implementer means implementer agent handoff using OpenCode command: /implement

## Archive On Completion

When every `## Todo Plan` item AND every `## Acceptance Criteria` item above is checked `[x]`:

1. `mkdir -p docs/tickets/claude-agent-fleet-remediation/archive`
2. Write the full completed plan to `docs/tickets/claude-agent-fleet-remediation/archive/DONE-plan-2-opencode-secret-deny-backstop.md` (apply the `DONE-` prefix).
3. Replace this original file with a one-line tombstone: `Archived: ./archive/DONE-plan-2-opencode-secret-deny-backstop.md (all Todo items and Acceptance Criteria complete on <timestamp>).`

Do not archive while any item is still unchecked.
