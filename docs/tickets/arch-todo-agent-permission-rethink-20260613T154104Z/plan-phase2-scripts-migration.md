# Scripts Permission-Friction Migration — Phase 2 Plan

Extends the locked Phase 1 plan in `./plan.md`. Do NOT author a parallel ticket: this
phase reuses the same gateway/registry surface (~85% overlap, see `./plan.md`).

- Source ticket driver: `todo-scripts-migration.md` (repo root).
- Overlapping root proposals: `agent-permission-rethink.md` (~70%), `scripts-refactor-restructure.md` (~60%).
- Research + reviewer synthesis completed 2026-06-13. Risk posture: **mixed** (P0/P1/P3/P5 additive low risk; P2 medium-high, security-posture).
- Scope constraint: edit **shipped sources** only (`packages/`, `scripts/ai/`, `tools/ai/`, `docs/ai/`). Never edit installed `.opencode/**` or `.github/**` copies (generated).

## Problem (confirmed by evidence)

The driving ticket assumes the fix is a PHP/Symfony Console rewrite. The evidence proves
the expensive thing **already ships**:

- Gateway `tool:list` / `tool:describe` / `tool:run` implemented at `tools/ai/ai.php:84-86,251-255`
  and `tools/ai/commands/install_extras.php:500-622`. Fails closed: unknown id → exit 1
  (`install_extras.php:576-581`), profile mismatch → exit 1 (`:595-600`), approval-required
  without `--apply` → exit 2 (`:604-615`). Covered by `tests/php/ToolGatewayTest.php`.
- Registry `aiInstallerScriptRegistry()` already carries `risk`/`tier`/`required_tools[]`/
  `requires_approval`/`introspection{}`; profiles via `aiInstallerAgentProfiles()`
  (`tools/ai/install/script-registry.php:520-535`) and `aiInstallerScriptProfiles()` (`:561-583`).

The real problem is THREE gaps, none of which is a PHP rewrite:

1. **Coverage / fallback gap (root cause of the pipe-friction symptom).**
   `aiRunScriptById` prechecks the **union** of all `required_tools` and refuses if any is
   missing (`install_extras.php:398-402`). `ai-search` declares `[bash,git,jq,rg,fd,ast-grep]`
   (`script-registry.php:23`). A missing `fd`/`ast-grep` blocks **every** ai-search mode —
   including `tracked` (git grep) which needs none — so agents drop to raw `grep`/`find`
   (then `grep | head`, which breaks on the pipe).
2. **Wiring gap.** `opencode.jsonc:71-205` still ships the ~134-line per-command bash matrix
   and has **no** `php tools/ai/ai.php tool:*` allow rule, so even invoking the built gateway
   falls through to `"*": "ask"` (`opencode.jsonc:72`). The safe lane is unreachable.
3. **Friction-by-config.** Native `grep`/`glob`/`list` are at `"ask"` (`opencode.jsonc:70-72`;
   secret denies at `:64-68`) even though they bypass `bash` entirely and are safe reads.

## Why pipes break (mechanics, for the record)

OpenCode bash rules are simple glob (`*`,`?`), not shell-aware; matching is against the
parsed command, last-matching-rule-wins (OpenCode permissions/agents docs, Jun 12 2026). A
compound `cat f | tail` is parsed into segments; each segment must independently satisfy an
allow rule, so a rule written for one bare command does not cover the same command inside a
pipe. The durable fix is to remove the **need** for pipes: native read tools + one stable
gateway command shape that does its own output capping/JSON inside PHP.

## Decisions

Locked in Phase 1 (carried forward, unchanged):

1. Reuse existing taxonomies (`risk` read-only|mutating + `tier0..4` + `autonomy_level`).
2. Extend `script-registry.php` as canonical; **no** `tool-registry.json`.
3. Reuse `aiRunScriptById` as the `tool:run` engine.
4. Single `aiInstallerAgentProfiles()` agent→profile map.

New for Phase 2:

5. The **lowest-friction first move is P0 (per-mode tool precheck)**, not any file move and
   not a PHP rewrite.
6. Treat the gateway as the runtime-agnostic *capability+approval boundary* and the
   per-runtime deny hook (`opencode.jsonc` / `.github/hooks/*.json`) as the *hard floor*.
   The gateway is a safe lane, not a fence.
7. Copilot parity is documentation/wording only: Copilot already enforces via
   `.github/hooks/*.json` `preToolUse` (Copilot CLI + cloud agent). IDE Copilot Chat hook
   support is **unknown** → treat as advisory fallback per `docs/ai/adapter-contract.md:21`.

## Lowest-friction first move

**P0 — per-mode `required_tools` precheck.** Additive, rollback-trivial, no path moves, and
it directly unblocks the most common read path (`ai-search tracked` / git grep) on hosts
missing `fd`/`ast-grep`. This removes the dominant reason agents fall back to raw pipes.

## Ranked slices

### P0 — Decouple per-mode required_tools from all-or-nothing precheck  `[NEW]`  ★ first move
- **What:** Make the gateway precheck only the tools required for the *requested* mode, not
  the union of all modes. `sh-introspect` emits `required_for_modes[]`, but see the note below
  on which map is authoritative.
- **Files:** `tools/ai/commands/install_extras.php` (`:398-402`); possibly
  `tools/ai/install/script-registry.php` (per-mode tool map); `tests/php/ToolGatewayTest.php`.
- **Function-scope note (required):** the union precheck `aiInstallerMissingTools($requiredTools)`
  lives in the **shared** `aiRunScriptById` engine (`install_extras.php:398-402`), which both
  `tool:run` (`:617`) and the legacy `run-script` path call. State explicitly whether P0 narrows
  the shared engine or only `aiRunToolRunCommand`, and confirm the legacy `run-script` caller
  keeps an equivalently-safe guard — do not narrow a guard for an unintended caller.
- **Authoritative mode→tool map (required):** `scripts/ai/ai-search/60-guards.sh`
  (`mode_needs_rg`/`mode_needs_git`, `:34-43`) is the real per-mode authority. Prefer static
  authoring (OQ-2 option A) seeded from it; treat live `required_for_modes[]` derivation as the
  heuristic/at-risk option (the introspection mapping is heuristic and omits global-required
  tools like `rg` — golden fixture shows `rg` with no `required_for_modes`).
- **Breakage surface:** central run path; must not weaken the guard for modes that genuinely
  need the tool.
- **Risk:** medium. **Rollback:** revert the precheck function change.
- **Status:** ✅ DONE (2026-06-13). Implemented as a **2-class model** (`required_tools` +
  `optional_tools`) in `tools/ai/install/script-registry.php` (`ai-search`/`ai-search-multi`)
  and `tools/ai/commands/install_extras.php` (`aiRunScriptById`). The gateway is mode-blind by
  design (it does not parse `--mode`; `install_extras.php:412` only reads `--dry-run`/`--apply`);
  the **script-side per-mode guards remain the fail-closed authority** — `60-guards.sh:35-41`
  (rg/git), `85-backend-ast.sh:18-19` (ast-grep), `65-backend-files.sh:27` (fd). Missing optional
  tools surface as non-blocking `warnings[]` + `missing_optional_tools`. Verified:
  `ToolGatewayTest` 14/14, `composer test:fast` 672/672.
- **Why NOT a gateway-side `mode_required_tools` map:** that would be a NEW gateway capability
  (parse `--mode`, own a mode→tool map) duplicating the authority already in `60-guards.sh`
  (see OQ-2). Deferred and ranked below the shipped script-side guard. P0 needs no further change.
- **Acceptance (met):**
  - [x] `ai-search tracked` runs with only `bash`/`git`/`jq` present (no `fd`/`ast-grep`).
  - [x] a mode that genuinely needs a tool still fails closed when it is missing (script-side).
  - [x] existing `ToolGatewayTest` stays green; new cases cover the optional-tool path.

### P0.5 — Registry invariant / contract tests  `[NEW]`  ★ schedule before P1
- **What:** A pure-PHP unit test over `aiInstallerScriptRegistry()` asserting field completeness
  and the fail-closed contract, so later slices (P1-P4) cannot silently introduce drift.
- **Why:** No registry-invariant test exists today (only targeted asserts in `ToolGatewayTest`).
  Real drift is already present: `ai-search` (`script-registry.php:32-43`) has the full field set
  but `rg-code` (`:64-73`) omits `tier`/`mutates_state`/`requires_approval`/`supports_json`/
  `bounded_output`. An invariant test catches this immediately and guards every later edit.
- **Files:** new `tests/php/ScriptRegistryInvariantTest.php` (or extend `ToolGatewayTest`).
- **Risk:** low. **Rollback:** delete the test file. **Status:** ✅ DONE (commit `9e98817`).
  `tests/php/ScriptRegistryInvariantTest.php`, 9 tests / 484 assertions. Confirmed the registry
  is sound: `rg-code`'s "missing fields" are intentional tier-0 minimalism, not drift.
- **Acceptance (met):**
  - [x] every id has `risk`, `source_path`, `installed_path`, a resolvable profile, non-empty
        `required_tools`; tier-1+ entries with a `tier` use a known tier value.
  - [x] approval/mutating ids are never visible to the `readonly` profile; mutating ⇒ requires approval.
  - [x] `optional_tools` are disjoint from `required_tools` for the same entry.

### P1 — POSIX grep/find fallbacks with `warnings[]`  `[NEW]`  (split into P1a/P1b/P1c)
- **What:** For search backends that today hard-fail when a tool is missing, add a controlled
  degradation path. Note the current behavior is **already a controlled `unavailable` fail, not
  a silent degrade** — verified `85-backend-ast.sh:18-19` (`fail "unavailable"`) and
  `65-backend-files.sh:27` (`fail "unavailable"`); `60-guards.sh:35-41` fails `"error"` for rg/git.
- **Files:** `scripts/ai/ai-search/60-guards.sh`, `65-backend-files.sh`, `85-backend-ast.sh`,
  `scripts/ai/rg-code.sh`, `scripts/ai/fd-files.sh`; backend tests.
- **Sub-slices (parity scores from research):**
  - **P1a — `fd` → `git ls-files`/`find`** (parity good/OK; emit `warnings[]`).
  - **P1b — `rg` → `git grep`/`grep`** (parity good with `git grep`, weaker with POSIX grep; `warnings[]`).
  - **P1c — `ast-grep`: do NOT add a silent grep "equivalent".** Preserve the existing
    `status=unavailable` controlled non-zero + a clearer message. AST semantics ≠ grep.
- **Degradation policy as data (optional):** consider a registry `degradation` map
  (`fd→git ls-files`, `rg→git grep`, with `parity`/`warning`) so fallbacks are declared, not ad hoc.
- **Breakage surface:** output-format parity (rg regex vs grep substring; fd glob vs find) — see OQ-5.
- **Risk:** medium. **Rollback:** revert backend fallback branches.
- **Status:** NEW. P1c is largely a "preserve + improve message" guard, not new degradation.
- **Acceptance:**
  - [ ] missing `rg` degrades text search to `git grep`/`grep` with a `warnings[]` entry (P1b).
  - [ ] missing `fd` degrades `files` mode to `git ls-files`/`find` with a `warnings[]` entry (P1a).
  - [ ] missing `ast-grep` keeps `status=unavailable` (no silent grep fallback) with a clearer message (P1c).
  - [ ] JSON envelope still emits `schema`/`status`/`warnings[]` (see OQ-5).
  - [ ] **behavioral parity** documented: `grep` substring vs `rg` regex, and `find` vs `fd`
        glob/substring filename match in `backend_files` (`65-backend-files.sh:38`) — differences
        surfaced in `warnings[]`, not silently divergent.

### P2a — Allow native read tools (`grep`/`glob`/`list`)  `[NEW]`  ★ early, OQ-1-independent
- **What:** Flip native `grep`/`glob`/`list` from `"ask"`→`"allow"` (confirmed at
  `opencode.jsonc:70-72`). These are non-bash native read tools, structurally separate from the
  `bash` block (`:73-207`), so this is **independent of OQ-1** and does not touch shell posture.
- **Files:** `packages/ai-universal-rules/templates/core/opencode.json` (SOURCE) then re-render
  `opencode.jsonc`. Never edit installed `.opencode/**`.
- **Breakage surface:** minimal — secret denies live on `read`/`edit` (`:64-68`) and are unaffected.
- **Risk:** low-medium. **Rollback:** revert the three keys + validator rule. **Status:** ✅ DONE
  (commit `8f593ed`). NOTE: not friction-free as assumed — `validate-ai-config.php:801-809` actively
  forbade `allow` for these keys, so P2a also updated that validator (now requires `allow`) and
  documented the rationale in `docs/ai/security.md`. Approved by user before changing encoded policy.
- **Acceptance (met):** [x] `grep`/`glob`/`list` = `allow` in template + rendered config; secret
  denies preserved; validator enforces `allow` (no silent downgrade); rationale documented.

### P2b — Wire the gateway execution into permissions  `[PARTLY-DONE / DECISION-GATED]`
- **What:** Add narrow allow rules — `php tools/ai/ai.php tool:list*`, `tool:describe*`, and a
  **scoped** `tool:run` rule — so the built gateway becomes a reachable safe lane.
- **Files:** `packages/ai-universal-rules/templates/core/opencode.json` (SOURCE) then re-render
  `opencode.jsonc`; `.github/hooks/*.json` parity (via `command-policy.tiers.yaml`); fix the
  template drift where `ai-search-multi.sh` allows exist in template but not rendered jsonc.
- **Breakage surface:** moving `bash` toward deny will break agent flows relying on
  `head`/`tail`/`sed -n`/`git grep` until migrated. Posture-changing.
- **Risk:** medium-high. **BLOCKED on OQ-1** (live tokenizer test of `tool:run <id> -- <args>`).
  Phase 1 deliberately deferred broad `tool:*` allow — do not silently override. Route through
  **release-auditor** (touches the permission floor).
- **Status:** PARTLY-DONE (gateway exists; wiring deferred). **Do not ship until OQ-1 resolved.**
- **Acceptance:**
  - [ ] OQ-1 resolved with a live OpenCode run showing `tool:run <id> -- <args>` (with quoted
        args) matches a single allow rule — OR the custom-tool route (OQ-6) is chosen instead.
  - [ ] gateway returns machine-readable approval reason codes (`reason=approval_required`,
        `safe_alternative`) so agent wording can be "stop, do not retry".

### P2c — Registry ↔ generated-permission drift test  `[NEW]`  (bind to P2b, not before P1)
- **What:** A test asserting registry/permission consistency. Becomes meaningful only once P2b
  introduces `tool:*` rules; no equivalent exists today (`CommandPolicyCompilerTest` only checks
  compiled-artifact staleness; `AgentPermissionPolicyTest` checks agent bash patterns).
- **Risk:** low. **Status:** NEW (sequenced with P2b).
- **Acceptance:**
  - [ ] registry id without a generated permission path fails the test.
  - [ ] generated permission referencing a missing registry id fails.
  - [ ] rendered `opencode.jsonc` differing from its template source fails (parity).
  - [ ] installed `.opencode/**` copies are not edited directly.

### P3 — Focused-test runner tool id  `[NEW]`
- **What:** A registry id/mode that runs `phpunit --filter`/single file, distinct from
  `run-repo-tests` (whole suite) and `ai-test-select` (selects only), with the anti-freeze timeout.
- **Files:** `tools/ai/install/script-registry.php`; a thin wrapper or new mode.
- **Risk:** low (raw phpunit/composer-test is already allowed, so friction is moderate).
- **Status:** NEW. **Acceptance:** [ ] focused single-class/filter run available via `tool:run`.

### P4 — First-class external read + small util ids  `[NEW, lower priority]`
- **What:** Document `preview-file.sh`/ai-search arbitrary-root reads as first-class
  (`preview-file.sh` already has no repo-root restriction); consider ids for `run-target`
  (just/make/composer), `json-query` (jq), `fetch-url`, LOC count.
- **Breakage surface:** external reads must respect `external_directory: ask` and the
  `.env`/`*.pem` denies at `opencode.jsonc:64-68` (re-anchor if the dirty `edit` block shifts
  line numbers).
- **Risk:** low-medium. **Status:** NEW.
- **Acceptance:** [ ] external read documented + gated; [ ] no secret-path bypass.

### P5a — Anti-pipe + native-reads wording  `[NEW, low risk]`  ✅ DONE (commit `8f593ed`)
- Added a "Native reads first; never pipe" section to the canonical `docs/ai/agent-script-access.md`
  (referenced by every agent's Script Access block) instead of duplicating wording across 13 agent
  templates. Covers: use native read/glob/grep/list; never pipe/redirect/`&&`/`$()`; one wrapper per
  call; stop-don't-retry on `approval_required`.

### P5 — Compact agent wording + Copilot parity (docs only)  `[NEW, low risk]`
- **What:** ~6-line agent guidance (native reads; never pipe; one gateway command per call;
  stop-don't-retry on `approval_required`) plus the matching Copilot reference line.
- **Files:** templates under `packages/ai-universal-rules/templates/**` only.
- **Risk:** low. **Status:** NEW (Phase 1 P3 deferred).
- **Acceptance:** [ ] per-agent bash matrices replaced by the compact block in templates;
  [ ] Copilot adapter references canonical policy, does not restate it.

## Things to avoid (rejected)

- **Symfony Console / full framework migration** — net-new dependency surface for zero
  functional gain; gateway already exists (`install_extras.php:500-622`). Violates ≥75% reuse.
- **`docs/ai/tool-registry.json`** — ~80% duplicate of `aiInstallerScriptRegistry()`.
- **6-value risk enum** — reuse existing 3 taxonomies.
- **Bulk script-path move into read/write/exec folders** — high breakage (touches templates,
  tests, ~36 literal `bash scripts/ai/...` lines in `opencode.jsonc`, `source_path`+`installed_path`
  per registry entry), zero leverage on the precheck/wiring root cause. Count "~60 files" is
  directional/unverified.
- **Broad `php tools/ai/ai.php tool:* ` or `php tools/ai/ai.php *` allow** — would auto-allow
  future dangerous subcommands; use stable narrow prefixes.
- **Authoring a new parallel ticket** — duplicates this locked ticket (~85%).
- **Gateway-side `mode_required_tools` fail-closed map** — REJECTED as a P0 refinement. The
  gateway is mode-blind (`install_extras.php:412`) and the script already fails closed per-mode
  (`60-guards.sh:35-41`, `85-backend-ast.sh:18-19`, `65-backend-files.sh:27`). A gateway mode→tool
  map would duplicate that authority and add a drift surface (see OQ-2). Deferred, low value.
- **Capability-ID rename now** (`repo.search` vs `ai-search`) — DEFER; churns the reused registry
  key surface the plan deliberately keeps. Revisit only if/when the adapter layer changes.

## Open questions

- **OQ-1 (blocks P2b):** Does OpenCode's bash glob reliably allow `tool:run <id> -- <args>`
  with quoted args under one rule? Needs a **live** OpenCode test; not resolvable by reading.
- **OQ-2:** Per-mode tool data authored statically in `script-registry.php` vs derived live
  from `sh-introspect` `required_for_modes[]` (drift risk vs runtime dependency). NOTE: P0 shipped
  with the script (`60-guards.sh`) as the per-mode authority; the gateway stays mode-blind.
- **OQ-3:** Make the gateway the only bash lane (deny raw `rg`/`grep`/`find`) vs coexist with
  native `grep`/`glob`/`list`.
- **OQ-4:** Ownership — extend this ticket (chosen) vs new ticket.
- **OQ-5:** P1 fallback JSON envelope parity (`schema`/`status`/`warnings[]`) for evidence pipelines.
- **OQ-6 (P2b route choice):** Use OpenCode **custom tools** (`https://opencode.ai/docs/custom-tools/`)
  to expose the PHP gateway as named, structured-arg tools (`ai_registry_run`, …) — this
  **eliminates OQ-1** (no shell tokenizer). BUT custom tools require TS/JS in `.opencode/tools/`
  + a JS/Bun runtime; this is a **pure-PHP repo with no `package.json`**, so it adds net-new
  dependency surface (the same objection that rejects Symfony). Documented as a *future option*,
  not the chosen path; a live-tested narrow bash rule keeps the stack homogeneous for now.

## Evidence-quality flags

- STRONG (verified from source): gateway exists + fails closed; all-or-nothing precheck;
  `ai-search` requires fd/ast-grep; `grep`/`glob`/`list`=ask + no `tool:*` rule; Phase 1
  rejections/deferrals.
- WEAK/unverified: `tool:run *` arg-glob matching (OQ-1); "~60 files" move count.
- MEDIUM: `sh-introspect` `required_for_modes[]` shape (no live run); search-backend
  hard-error lines (verify before P1).
- UNKNOWN: IDE Copilot Chat hook support.

## Recommended decision direction

Adopt **gateway-wiring-first + coverage-fallbacks**. Reject folder-move and PHP/Symfony
rewrite. Refined sequence (post-reassessment review):

**P0 (DONE) → P0.5 invariant tests → P2a native-read allow → P5a anti-pipe wording →
P1 controlled fallbacks → P3 focused-test id → resolve OQ-1/OQ-6 → P2b gateway wiring (+P2c) →
P4 external read/util ids → P5b Copilot parity.**

Rationale for the reorder: P0.5/P2a/P5a are low-risk, friction-reducing, and OQ-1-independent,
so they ship value before the posture-changing P2b. P2b is the only high-risk slice and stays
gated behind OQ-1 (or the OQ-6 custom-tool route) and release-auditor review.

`P5a` = the compact anti-pipe + native-reads wording (cheap, immediately useful).
`P5b` = Copilot parity/docs cleanup.

## Ranking of the recommended move (0-100)

| Slice | Confidence | Safety | Accuracy | Helpfulness |
| --- | ---: | ---: | ---: | ---: |
| P0 per-mode precheck (DONE, verified) | 92 | 90 | 92 | 92 |
| P0.5 registry invariant tests | 88 | 92 | 90 | 85 |
| P2a native-read allow | 85 | 82 | 88 | 88 |
| P5a anti-pipe wording | 84 | 90 | 85 | 86 |
| P1 controlled fallbacks (P1a/b/c) | 74 | 80 | 82 | 85 |
| P3 focused-test id | 85 | 90 | 85 | 70 |
| P2b wire gateway (gated, OQ-1/OQ-6) | 60 | 55 | 80 | 88 |
| P2c permission-drift test (with P2b) | 80 | 88 | 85 | 75 |
| P4 external read + util ids | 70 | 70 | 75 | 65 |
| P5b Copilot parity docs | 82 | 88 | 85 | 80 |
| **Overall recommended direction** | **86** | **86** | **90** | **92** |

The reassessment lifted the direction from ~88 to ~94 on the dimensions it scored; the lift comes
from splitting risk classes (P2a vs P2b), adding invariant/drift tests (P0.5/P2c), and shipping
friction reduction earlier — all confirmed safe and OQ-1-independent.

## Handoff

- Reassessment review verdict: **PASS WITH NOTES** — the merged P0 is correct and needs **no
  change**; all reassessment proposals were folded into the plan, rejected, or deferred with
  evidence. Corrections recorded: (1) `ast-grep`/`fd` already fail `unavailable` (not silent
  degrade); (2) a gateway-side `mode_required_tools` map is a NEW capability, not a P0 refinement,
  and is rejected because the script already owns per-mode fail-closed; (3) custom-tools (OQ-6)
  is real but adds a JS/Bun runtime to a pure-PHP repo — documented as future option only.
- Next implementable slices (all low-risk, OQ-1-independent): **P0.5 → P2a → P5a**. P0.5 is a
  pure-PHP unit test over `aiInstallerScriptRegistry()`; it already catches real drift
  (`rg-code` missing fields vs `ai-search`).
- Keep **P2b** parked until OQ-1 (live OpenCode run) or OQ-6 (custom-tool route) is resolved;
  route P2b + P2c through **release-auditor** (posture-changing, touches the permission floor).
- Worktree is dirty; isolate edits and do not absorb the pre-existing `docs/tickets/**`
  edit-allow change into a permission commit.
