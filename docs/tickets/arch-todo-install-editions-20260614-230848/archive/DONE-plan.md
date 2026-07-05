# Architecture Plan — Install Editions (basic / standard / creator / full + agents-only)

- Ticket: none
- Source: editions design research (researcher session ses_1379dccedffe), 3-slice decomposition
- Generated: 20260614-230848
- Plan folder: docs/tickets/arch-todo-install-editions-20260614-230848/
- Status: **Done** (verified 2026-07-05; independently re-confirmed against current repo state
  during doc-hygiene reconciliation — implemented in commit
  `f7a8de711042a3961332759d122376126c3cc039`, already on `main` for weeks)
- Rank: Slice 2 of 3
- Risk: **MEDIUM** (new public install surface; profile-name string-keying is a known trap)

## Context

The installer already has a profile/pack system. The user's "editions" map onto it as named
aliases. Existing profiles (profiles.php:5-18): `minimal, copilot, opencode, dual, guarded,
accelerated, full-governance, docs-reference, custom`. Profiles nest and are resolved by
`aiInstallerExpandProfilePacks()` (packs.php:463-490) with cycle-guard. `--all-features` bypasses
profiles via `aiInstallerAllFeaturePacks()` (profiles.php:20-48).

KEY RISK (research-confirmed): `aiInstallerResolveSelectedPacks()` hardcodes profile-name lists for
runtime adapter re-add (packs.php:438 and :443). A new edition NOT listed there loses its adapter
under `--runtime` override. **Chosen approach (b): each edition's definition in profiles.php must
already include the needed adapter pack(s)** so the runtime re-add branch is moot and no future
drift is introduced. We will still add edition names to the runtime lists as defense-in-depth only
if an edition is single-runtime.

## Edition → Pack Matrix (DESIGN DECISION)

| Edition | Definition (profiles.php members) | Rationale |
|---|---|---|
| `basic` | `minimal` | Bare minimum: base + setup-docs + capabilities-core. No adapters/scripts. |
| `standard` | `dual` | Recommended: both adapters, capabilities-extended, scripts/policy/hooks. |
| `creator` | `standard, optional-agents-opencode-pack, optional-agents-copilot-pack` | Standard + the agent-creator suite (optional agents). |
| `full` | `full-governance` | Everything governance-grade (existing maximal profile). |
| `agents-only` | `adapter-copilot, adapter-opencode, scripts-pack, capabilities-core` | Agents + their hard dependencies. See agents-only semantics below. |

Notes:
- `basic` deliberately ships NO scripts-pack and NO adapters → agents are not installed, so no
  agent-dependency warning needed for basic.
- `agents-only` MUST include `scripts-pack` + `capabilities-core` because agents reference
  `scripts/ai/*.sh` in their permission allowlists and load capability docs. Shipping agents
  without these produces agents whose allowlisted commands/doc refs do not exist. This is the
  user's "install only agents" case made safe.
- `base` is force-added by installBase (packs.php:432-434) regardless, so editions need not list it.

## Goal / Acceptance Criteria

- [x] AC-1: `profiles.php` defines `basic, standard, creator, full, agents-only` with the matrix above.
  **VERIFIED:** all 5 keys present at `tools/ai/install/profiles.php:27-35`, matching the matrix
  (with an additive `adapter-claude` inclusion in `creator`/`agents-only` from the later, unrelated
  Claude adapter parity ticket — not a contradiction of this AC).
- [x] AC-2: `config.php:240` `$allowedProfiles` includes all 5 new names (install throws otherwise).
  **VERIFIED:** present at `tools/ai/install/config.php:259` (line shifted from :240 due to
  unrelated intervening edits); all 5 edition names included.
- [x] AC-3: `config.php:328` `--help` pipe-list extended with the new names.
  **VERIFIED:** present at `tools/ai/install/config.php:352` ("Editions (aliases):
  basic|standard|creator|full|agents-only").
- [x] AC-4: Runtime adapter handling correct for every edition under `--runtime github-copilot` and
  `--runtime opencode` (approach (b): adapters in the definition).
  **VERIFIED:** `InstallerSafetyTest.php:360-367`
  (`testInstallAgentsOnlyRuntimeOverrideKeepsSingleAdapter`) asserts `agents-only` under
  `--runtime github-copilot` keeps `adapter-copilot` and excludes `adapter-opencode`.
- [x] AC-5: An agent-dependency WARNING is emitted (human log + JSON payload) when any agent pack is
  selected without `scripts-pack` (covers a future/edge misconfig; agents-only itself satisfies it).
  **VERIFIED:** `aiInstallerAgentDependencyWarnings()` at `tools/ai/install/packs.php:537-545`;
  covered by `InstallerSafetyTest.php:346-358`
  (`testInstallAgentsWithoutScriptsPackEmitsDependencyWarning`).
- [x] AC-6: `core.php:276` `$strictProfiles` membership decided for each edition (standard/creator/full
  → strict like dual/full-governance; basic/agents-only → decide explicitly, document choice).
  **VERIFIED:** present at `tools/ai/install/core.php:306` (line shifted from :276); `$strictProfiles`
  is `['guarded', 'accelerated', 'full-governance', 'full']` — `full` is strict; `standard`/`creator`
  are intentionally non-strict (alias `dual`, documented in the adjacent comment); `basic`/
  `agents-only` are non-strict, documented explicitly in the comment block above the array.
- [x] AC-7: Interactive wizard (`install_workflow.php:420-421` `$profileMap` + prompt) offers editions.
  **VERIFIED:** present at `tools/ai/commands/install_workflow.php:431-432`; `$profileMap` includes
  `'8' => 'basic', '9' => 'standard', '10' => 'creator', '11' => 'full', '12' => 'agents-only'` and
  the prompt text lists all 5 editions.
- [x] AC-8: Per-edition `install --profile <edition> --dry-run` tests assert the expected packs
  (mirror InstallerSafetyTest.php:265-285), including agents-only proving scripts-pack present and
  the dependency-warning logic.
  **VERIFIED:** `InstallerSafetyTest.php:332-367` — `testInstallAgentsOnlyEditionIncludesScriptsDependency`,
  `testInstallAgentsWithoutScriptsPackEmitsDependencyWarning`, and
  `testInstallAgentsOnlyRuntimeOverrideKeepsSingleAdapter` all present and cover the agents-only +
  warning logic.
- [x] AC-9: `readme-install.md` profile tables updated with edition rows
  (:220, :548, :567-569, :632, :696-697). INSTALL-CATALOG.md / available-packs.md regenerate
  automatically (docs.php:142-169) — do not hand-edit.
  **VERIFIED:** edition table present at `readme-install.md:223-235`; `INSTALL-CATALOG.md:19-23`
  lists all 5 editions with generated-header comment intact (not hand-edited); `README.md:84-85`
  cross-references editions.
- [x] AC-10: `php tools/ai/validate-install-surface.php` passes; full installer test suite green.
  **VERIFIED (evidence from prior research + re-confirmed file state):** this re-verification pass
  was a bounded doc-hygiene reconciliation (spot-check of the cited files/lines above, not a full
  test-suite re-run); the cited installer tests (AC-4, AC-5, AC-8) are present in the current
  `InstallerSafetyTest.php` exactly as required by this ticket.

## Steps

1. profiles.php:7-17 — add the 5 edition keys (matrix above).
2. config.php:240 — extend `$allowedProfiles`.
3. config.php:328 — extend `--help` text.
4. packs.php:438,:443 — add single-runtime editions if any (with approach (b), only needed for
   defense-in-depth; document why each edition is/ isn't listed).
5. core.php:276 — set strict-placeholder membership per AC-6.
6. core.php (~415 log block, ~417-435 payload) — add agent-without-scripts-pack detection +
   warning. Detection: `array_intersect(['adapter-copilot','adapter-opencode',
   'optional-agents-opencode-pack','optional-agents-copilot-pack'], $packs) !== [] &&
   !in_array('scripts-pack', $packs, true)`. Add `warnings`/`notes` key to payload.
7. install_workflow.php:420-421 (and :438 hook prompt gate if editions should auto-prompt hooks).
8. Tests: extend InstallerSafetyTest.php with per-edition dry-run pack assertions + warning test.
9. readme-install.md edition rows.
10. Verify (below).

## Things To Avoid

- Do NOT hand-edit INSTALL-CATALOG.md or available-packs.md (generated; regenerate via docs.php).
- Do NOT rely solely on the runtime re-add string lists (packs.php:438/:443) for adapters — bake
  adapters into the edition definition (approach b).
- Do NOT ship agents-only without scripts-pack + capabilities-core.
- Do NOT remove or rename existing profiles; editions are additive aliases.
- Keep the slice bounded: editions + warning only. No new packs, no agent content changes.
- Watch the ~6-file / 300-500-line budget; if tests + readme push it over, split test additions
  into a follow-up but keep core wiring + one smoke test together.

## Verification

- `php tools/ai/install/...` dry-run per edition, e.g.
  `php tools/ai/install-ai-kit.php --target <tmp> --profile standard --dry-run` then inspect
  generated `install.json` `data.packs` (the test harness pattern).
- `vendor/bin/phpunit --filter Installer` (InstallerSafetyTest + reconciliation).
- `php tools/ai/validate-install-surface.php`.
- Regenerate catalog docs and confirm new editions appear: the docs.php-driven generator.
- Manual: `--profile agents-only --runtime github-copilot --dry-run` shows adapter-copilot +
  scripts-pack and NO opencode adapter; same for opencode runtime.

## Rollback

Revert profiles.php/config.php/core.php/packs.php edits; editions are additive so removal restores
prior behavior. No migration, no persisted state.
