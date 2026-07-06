# Arch Todo: Install-Time Language Selection + Safe `ai-verify` Wiring

- Ticket slug: `arch-todo-install-language-select-and-verify-wiring-20260706-014933`
- Status: `PLAN ONLY — not implemented`
- Author: architect handoff (grounded by a read-only researcher pass; see §1a)
- Risk: `medium` (touches the installer's config-write + docs-render path and adds
  stack descriptors; NO auto-editing of user-owned build files, NO auto-activated git
  hooks — the risky wiring is opt-in only by design)
- Verification command of record: `composer test`
- Related tickets:
  - `docs/tickets/arch-todo-safe-language-verify-scripts-20260706-003959` (per-language
    `--language` verify for PHP/JS/TS/Vue/HTML — already wired into `ai-verify.sh`)
  - `docs/tickets/arch-todo-android-kmp-verify-lane-20260706-010421` (Kotlin/Android lane —
    standalone modules present, deliberately NOT wired)

---

## 1) Context

The user wants the install experience to:

1. Ask which **AI tools** to install (opencode / copilot / claude).
2. Ask the project's **primary language(s)** — multi-select, as many as the user wants
   (e.g. php, js, ts, vue, kotlin/android).
3. **Safely wire** the existing per-language `ai-verify` into "some file(s)" so it runs as a
   verification step for the selected languages, **without harming the user's repo or
   introducing unwanted/surprising behavior**.

### 1a) Grounded findings (read-only research; exact evidence)

**AI-tool selection ALREADY EXISTS — do not rebuild it.**

- Interactive wizard prompts targets: `[1] both, [2] copilot, [3] opencode, [4] claude`
  (`tools/ai/commands/install_workflow.php:421-433`) → maps to `--runtime`
  (`both|github-copilot|opencode|claude-code`).
- Adapter packs render the same canonical source per runtime: `adapter-copilot`,
  `adapter-opencode`, `adapter-claude` (`tools/ai/install/packs.php:131,142,155`).
- Profiles compose packs (`tools/ai/install/profiles.php:7-36`).
- GAP: the non-interactive `install-ai-kit.sh` path hardcodes `--profile full-governance
  --runtime both` (`install-ai-kit.sh:165-174`); it does not surface the question.

**Language selection ALREADY EXISTS as "stacks" — reuse it, do not invent a new field.**

- Shipped stack descriptors: `packages/ai-universal-rules/stacks/*.json` = `php`, `js-ts`,
  `python`, `go`, `ruby`, `rust`, `java`, `dotnet`, `shell`, `markdown`, `make`,
  `github-actions`. **No `vue`, no `kotlin`/`android` descriptor yet.**
- Detection: `aiStackDetect()` (`tools/ai/install/stack-detection.php:12`).
- Interactive multi-select: `aiSelectionMultiselect(..., 'Project stacks:', ...)`
  (`install_workflow.php:459-471`), detected stacks pre-checked; backends laravel-prompts →
  fzf → gum → stdin (`selection-engine.php`).
- Non-interactive: `--stacks <csv>` (`tools/ai/install/config.php:224-231`); explicit
  `--stacks` REPLACES detection (`stack_selection.php:29-39`).
- Persistence in `.ai/project.yml`: `selectedStacks`, `detectedStacks`, `primaryStack`,
  `stackToolVersions`, plus structured `.ai/stack-detection.json`
  (`core.php:1229-1236`). Write-through only overwrites `unknown`/empty (`core.php:1251`) —
  never clobbers user edits.

**`--language` ai-verify flag ALREADY IMPLEMENTED.**

- `scripts/ai/ai-verify.sh:21-44,120-124`: `--language <lang>` selects `ai_verify_language`
  (`53-language-dispatch.sh`), dispatching `php | js | ts | vue | html`. Absent flag → full
  pipeline. Every tool is `can_run_tool`-gated (runs only already-installed tools).
- Per-language wrapper scripts (`ai-verify-php.sh` etc.) and registry entries are the sibling
  ticket's P3 and are NOT done yet.
- Kotlin lane (`60..63-*.sh`) present but deliberately NOT wired (its own AC-05).

**`recommendedVerificationCommands` is a DEAD placeholder.**

- Declared in the project.yml value set and set only to `'unknown'`
  (`core.php:1126,1183`); no writer/consumer found. It is the natural, non-executing home
  for surfacing per-language verify commands.

**Safety model already in place.**

- Backup: `aiInstallBackupCreate()` (`backup.php:22`) snapshots every affected file with
  `sha256_before` + `owned_by_install`.
- Manifest: `.ai-install-manifest.json` + `docs/ai/generated/install-manifest.json`.
- Dry-run gates all mutation (`core.php:279`).
- Merge strategies: `skip-if-exists` (user-owned), `replace` (kit-managed),
  `never_auto_merge` (`opencode.jsonc`).
- Approval boundaries (`docs/ai/approval-boundaries.md`): git-hook installation and editing
  user config are approval-gated; hook wiring is explicitly opt-in
  (`install_extras.php:263-289`, `hookDriver` default `none`).

### 1b) Reuse decision (per `>=75%` rule)

- AI-tool selection: **100% exists** → reuse `--runtime`/profile/adapter-packs + wizard
  target prompt; only surface it in the non-interactive path. Do NOT rebuild.
- Language selection: **~90% exists** as stacks → reuse `selectedStacks` + the "Project
  stacks:" multiselect + `--stacks`. Net-new = two stack descriptors (`vue`, `kotlin`) and a
  stack-id → ai-verify-language mapping.
- ai-verify engine: **100% exists** (`--language`) → reuse; do not touch dispatch logic.

---

## 2) Problem

Three gaps block the requested experience:

1. The non-interactive install path does not ask (or accept a flag for) which AI tools —
   though the wizard does. (Surfacing gap, not a missing capability.)
2. Languages are modeled as "stacks" but (a) `vue` and `kotlin`/`android` have no descriptor,
   and (b) stack ids (`php`, `js-ts`) do not 1:1 map to ai-verify language tokens (`php`,
   `js`, `ts`, `vue`, `html`, `kotlin`).
3. There is no mechanism that turns "selected languages" into a discoverable/opt-in
   `ai-verify` verification step, and the only obviously-safe surfaces
   (`recommendedVerificationCommands`, `verification-matrix.md`) are unpopulated.

---

## 3) Target Outcome

- Install asks (interactive) / accepts flags (non-interactive) for **AI tools** and
  **languages**, reusing the existing runtime/stacks machinery.
- A **stack-id → ai-verify-language** mapping exists and is the single source of truth for
  translating `selectedStacks` into `ai-verify --language <lang>` invocations.
- Selected languages are surfaced as **verify commands in data + docs** (safest tier), with
  higher-risk executing wiring (git hook / CI) offered **only as explicit opt-in** that
  reuses the existing `hookDriver`/`hooks install` gate and never auto-activates.
- **No user-owned build file** (`composer.json`, `package.json`, `justfile`, existing hooks)
  is edited without explicit approval.

---

## 4) Design: Safe Wiring Tiers (least-surprising first)

The core architectural decision is a **tiered wiring model**. Each higher tier is strictly
opt-in relative to the tier below. Default install stops at Tier 1.

**Tier 0 — Config truth (always).**
Persist selected languages in `.ai/project.yml` `selectedStacks` (already happens) and add a
derived `verifyLanguages` (mapped ai-verify tokens) so downstream renders/consumers have a
clean list without re-deriving the stack→language mapping. Data only; non-executing.

**Tier 1 — Discoverable commands, non-executing (DEFAULT).**
Populate `recommendedVerificationCommands` (the dead placeholder) and render one
`bash scripts/ai/ai-verify.sh --language <lang> .` line per selected language into
`docs/ai/verification-matrix.md` and `docs/ai/project-context.md` (§8 "Verification
Commands"). These are kit-managed (`merge_strategy: replace`) so they are safe to (re)write.
Nothing executes; the user simply *sees* the exact command to run. **This is the default and
the maximally-safe answer to the user's "wire it into some file" request.**

**Tier 2 — Discoverable wrapper scripts (opt-in, still non-executing).**
Complete the sibling ticket's P3: ship per-language wrapper scripts + registry entries so
`bash scripts/ai/ai-verify-<lang>.sh .` exists and is registry-discoverable. Owned by the
sibling ticket; this plan only *depends* on it, does not duplicate it. Still no auto-run.

**Tier 3 — Executing verification step (EXPLICIT OPT-IN ONLY).**
Only when the user explicitly enables it (a new `--wire-verify=<hook|ci|none>` flag defaulting
to `none`, or an explicit wizard prompt whose default is "no"):
- **hook**: reuse the EXISTING `hookDriver`/`hooks install --driver` opt-in gate
  (`install_extras.php`); write a kit-owned pre-push/pre-commit step that calls
  `ai-verify --language <lang>` for selected languages. Never auto-activate git hooks
  (approval boundary).
- **ci**: emit a kit-owned GitHub Actions job (extend `ci-pack`) that runs the per-language
  verify. CI is a user rollout surface → opt-in, `merge_strategy` respected.

**Tier X — Off-limits without explicit per-change approval.**
Editing user-owned `composer.json` / `package.json` / `justfile` / pre-existing hooks. This
plan does NOT do this in any tier; it is called out only to forbid it.

### 4a) Stack-id → ai-verify-language mapping (net-new, single source of truth)

| selectedStack id | ai-verify `--language` token(s) |
|---|---|
| `php` | `php` |
| `js-ts` | `js`, `ts` |
| `vue` (new descriptor) | `vue` (and `js`/`ts` if also selected) |
| `kotlin` / `android` (new descriptor) | `kotlin` (only once the Kotlin lane is wired — see §6 dependency) |
| others (`python`, `go`, ...) | none (no ai-verify lane yet) — skipped cleanly |

Unmapped stacks are skipped, never error. The mapping lives in ONE new PHP helper
(e.g. `aiVerifyLanguagesForStacks()`), consumed by the Tier 0/1 renderers.

---

## 5) In Scope

- New stack descriptors: `packages/ai-universal-rules/stacks/vue.json`,
  `packages/ai-universal-rules/stacks/kotlin.json` (detection globs: `*.vue`; `*.kt`,
  `*.kts`, `*.gradle.kts`, `settings.gradle.kts`).
- New mapping helper `aiVerifyLanguagesForStacks(array $selectedStacks): array` +
  derived `verifyLanguages` written into `.ai/project.yml` (Tier 0).
- Populate `recommendedVerificationCommands` and render per-language verify commands into
  `docs/ai/verification-matrix.md` + the project-context template's Verification section
  (Tier 1) via the existing placeholder/render path.
- Surface AI-tool selection in the non-interactive `install-ai-kit.sh` path (a `--runtime`
  passthrough / prompt) so the user is actually asked — reusing existing wizard logic.
- New opt-in `--wire-verify=<none|hook|ci>` flag (default `none`) that, ONLY when non-`none`,
  routes into the EXISTING `hookDriver` / `ci-pack` mechanisms (Tier 3). Default install
  behavior is unchanged.
- Tests: mapping helper unit tests; render/placeholder tests; a "default install does NOT
  auto-wire an executing step" regression test; stack-descriptor detection tests.
- Docs: short notes in `docs/ai/verification-matrix.md` and the install docs describing the
  tiers and the opt-in boundary.

## 6) Out Of Scope (Things To Avoid)

- **Do NOT rebuild AI-tool selection** — reuse runtime/profile/adapter packs.
- **Do NOT invent a new language field** — reuse `selectedStacks`; only add derived
  `verifyLanguages`.
- **Do NOT auto-activate git hooks or auto-add CI** — Tier 3 is opt-in, default `none`,
  reusing the existing `hooks install`/`hookDriver` gate.
- **Do NOT edit user-owned** `composer.json` / `package.json` / `justfile` / existing hooks.
- **Do NOT wire the Kotlin lane here.** Its own ticket
  (`arch-todo-android-kmp-verify-lane-20260706-010421`) declares it non-autowired; `kotlin`
  in the mapping stays a no-op until that lane is separately, explicitly wired. This plan
  must not source `60..63-*.sh` or add `--language kotlin` to `ai-verify.sh`.
- **Do NOT touch the sibling PHP/JS lane files** while the concurrent session owns them; this
  plan only *consumes* the already-shipped `--language` contract.
- **Do NOT clobber user project.yml values** — keep the `unknown`-only write-through rule.
- **Do NOT exceed ~6 files per implementation slice** — split per §8.

## 7) Contracts And Boundaries

- **Mapping contract:** `aiVerifyLanguagesForStacks` is total (never throws), returns a
  de-duplicated ordered list of valid ai-verify tokens, skips unmapped stacks.
- **Render contract:** Tier 1 output is kit-managed and idempotent; re-install re-renders
  cleanly and preserves user-owned sections (existing restore-sections mechanism).
- **Opt-in contract:** with `--wire-verify=none` (default) NO executing hook/CI is written;
  proven by a regression test asserting no hook/workflow file is created by a default apply.
- **Safety contract:** all mutations go through the existing backup + manifest + dry-run +
  merge-strategy machinery; no new bypass path.
- **Kotlin non-wire contract:** the android-kmp ticket's AC-05 remains true after this ticket
  (a repo-wide search for `--language kotlin` / `ai_verify_kotlin` in wiring surfaces stays
  empty).

## 8) Todo Plan

### P0 — Language model: descriptors + mapping + config truth (Tier 0)

- [ ] Add `vue.json` and `kotlin.json` stack descriptors with detection globs.
- [ ] Add `aiVerifyLanguagesForStacks()` mapping helper (single source of truth, §4a).
- [ ] Write derived `verifyLanguages` into `.ai/project.yml` (respect `unknown`-only rule).
- [ ] Unit tests: mapping (php→php; js-ts→js,ts; vue→vue; kotlin→kotlin; unknown→[]);
      descriptor detection for `*.vue` and Kotlin/Gradle files.

### P1 — Discoverable commands in data + docs (Tier 1, DEFAULT)

- [ ] Populate `recommendedVerificationCommands` from `verifyLanguages`.
- [ ] Render per-language `ai-verify --language <lang>` lines into
      `docs/ai/verification-matrix.md` + project-context Verification section via the
      existing render/placeholder path.
- [ ] Tests: render output for a php+js-ts project; idempotent re-install; user-section
      preservation.

### P2 — Surface AI-tool selection in the non-interactive path

- [ ] Plumb `--runtime` (and/or a prompt) through `install-ai-kit.sh` so the AI-tool question
      is actually asked / accepted outside the wizard, reusing existing runtime/profile logic.
- [ ] Tests: non-interactive install honors an explicit runtime selection.

### P3 — Opt-in executing wiring (Tier 3, default OFF)

- [ ] Add `--wire-verify=<none|hook|ci>` (default `none`).
- [ ] `hook`: route into the existing `hooks install`/`hookDriver` gate to emit a kit-owned
      verify step; never auto-activate.
- [ ] `ci`: extend `ci-pack` with an opt-in per-language verify job.
- [ ] Regression test: default apply (`--wire-verify` unset) creates NO hook/workflow verify
      step; `--wire-verify=hook`/`ci` create the kit-owned step only.

### P4 — (depends on other tickets) wrapper scripts + Kotlin fold-in

- [ ] Consume the sibling ticket's per-language wrapper scripts once P3 of that ticket lands
      (Tier 2 discoverability). No duplication here.
- [ ] Only after the android-kmp lane is separately+explicitly wired, flip `kotlin` in the
      mapping from no-op to active.

## 9) Acceptance Criteria

- [ ] AC-01: `vue` and `kotlin` stack descriptors exist and detect `*.vue` and
      Kotlin/Gradle files respectively.
- [ ] AC-02: `aiVerifyLanguagesForStacks` maps php→[php], js-ts→[js,ts], vue→[vue],
      kotlin→[kotlin], unknown→[]; is total and de-duplicated.
- [ ] AC-03: `.ai/project.yml` gains `verifyLanguages` derived from selection, written only
      when the value is currently `unknown`/empty (no user clobber).
- [ ] AC-04: `recommendedVerificationCommands` + `verification-matrix.md` +
      project-context Verification section show one `ai-verify --language <lang> .` per
      selected mapped language; re-install is idempotent and preserves user sections.
- [ ] AC-05: Non-interactive install can select AI tools (runtime) explicitly.
- [ ] AC-06: With `--wire-verify` unset, a default apply writes NO executing hook/CI verify
      step (regression-proven). `--wire-verify=hook|ci` writes only a kit-owned, opt-in step
      through the existing gate; user-owned build files are never edited.
- [ ] AC-07: The android-kmp AC-05 (kotlin lane non-wired) still holds — no `--language
      kotlin` / `ai_verify_kotlin` in any wiring surface.
- [ ] AC-08: New tests pass and `composer test` is green (modulo unrelated concurrent churn).

## 10) Verification Plan

1. Focused: PHPUnit for the mapping helper + descriptor detection + render tests.
2. Opt-in safety regression: assert default apply creates no hook/workflow verify step.
3. Kotlin non-wire proof: `rg -n '--language kotlin|ai_verify_kotlin' scripts/ai/ai-verify.sh
   .opencode .github tools/ai/install` returns zero.
4. Install smoke: `php tools/ai/ai.php install --dry-run` (or the wizard) shows the new
   prompts/flags without mutating.
5. Broad: `composer test` (`composer test:fast` first).

Apply per-command timeouts from `docs/ai/execution-protocol.md`.

## 11) Risks And Rollback

- Risk: stack-id ↔ language-token drift. Mitigation: single mapping helper + tests.
- Risk: perceived "unwanted behavior" from auto-running verify. Mitigation: Tier 1 default is
  non-executing (docs/data only); execution is Tier 3 opt-in via existing gates.
- Risk: clobbering user config. Mitigation: reuse backup + manifest + `unknown`-only
  write-through + merge strategies; never edit user build files.
- Risk: prematurely activating the Kotlin lane. Mitigation: kotlin mapping is a no-op until
  its own ticket wires the lane; AC-07 guards it.
- Rollback: revert descriptors/helper/render edits + the opt-in flag; the backup manifest
  restores any written docs/config. No data migration.
- Success signal: prompts/flags work, docs show correct per-language commands, default apply
  auto-wires nothing executing, `composer test` green.

## 12) Handoff Notes

- This is a **plan only**. Recommended next step: implementer agent handoff using OpenCode
  command `/implement`, executing P0 first (descriptors + mapping + Tier 0 config), then P1
  (Tier 1 docs/data — the default-safe answer to the user's request), then P2, then P3
  (opt-in execution). P4 depends on the two sibling tickets landing.
- Key safety stance for the user's concern: the DEFAULT install wires ai-verify only as
  **discoverable commands in docs + `.ai/project.yml`** — it never runs anything on its own.
  Executing it as a real step is always an explicit, reversible opt-in reusing the kit's
  existing hook/CI gates.

### Open Questions For User

1. Q1 (default wiring depth): Confirm Tier 1 (discoverable commands, non-executing) is the
   correct DEFAULT, with execution (Tier 3) strictly opt-in. (Recommended.)
2. Q2 (opt-in surface): For Tier 3, prefer git-hook (`pre-push`) or CI job as the first
   supported opt-in target?
3. Q3 (language granularity): Should `js-ts` stay one stack mapping to both `js`+`ts`, or be
   split into separate selectable stacks?
4. Q4 (kotlin timing): Keep `kotlin` a mapping no-op until the android-kmp lane is separately
   wired (recommended), or wire that lane as part of this effort?
