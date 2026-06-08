# AI Universal Rules — Consolidated Integration Plan

> Verified against commit `ac5cdf4` (branch `main`) on 2026-06-07.
> Supersedes prior drafts. Integrates: verified-repo baseline (~82/100 at `e8fdf87`),
> critical-only additions (CP-0–CP-4, M1–M3), and the corrected CP-5 user-content spec.
> Re-verify repo state before acting; strike anything already landed.

## Landed So Far (pushed to origin/main)

| Commit | Work | Status |
|---|---|---|
| `79e3569` | Manifest reconciliation: `files{}` canonical (fixed dual-writer data loss) | done |
| `d898180` | First-install safety: git-root guard, opencode.jsonc adopt-or-conflict (M1), `--adopt` adoption | done (direct installer) |
| `72e8d93` | Ownership classes (`owned`/`template`/`rendered`) + component + runtimes + install-manifest schema + CI check | done |
| `79c098b` | Ownership-aware upgrade classification + `.ai/conflicts/` preservation helper | partial — see RF-2 |
| `5dfa2bc` | Read-only `doctor` command | done |
| `ac5cdf4` | Ownership-aware `uninstall` | partial — see RF-3 |
| `6b32160` | RF-3 fix: uninstall never recursively deletes user dirs | done |
| `084e42d` | RF-1/2/4 + Phase 3c project.yml + Phase 0 lock + Phase 2b migrations + Phase 3.5 gitignore/AGENTS marker; planner force preserves templates | done |
| `460cc49` | Phase 5a reserved user-namespace gate | done |
| `13a5c45` | Phase 5b user-section byte-preservation + backup retention | done |
| `844bf29` | Phase 6 PathGuard + single-process install lock | done |
| `25cf316` | Phase 6b case-collision guard + doctor checksum/lock checks | done |
| `a0a6dbb` `575d64d` | Phase 7a POSIX tool-guardian.sh + ps1 parity (+inventory) | done |
| `9a40e32` | Phase 7b-1 dependency-free policy compiler; hooks-pack ships guardian/compiled | done |
| `6557578` `58bc001` | Phase 7b-2 expanded secret denies + local-override no-downgrade allow-list | done |
| `6c6452b` `cb18ca4` | Phase 7b-3 security.md enforcement matrix (invariant 6) | done |
| `9fc32ef` | Compiler wired into install/upgrade (local overrides apply at install) | done |
| `c67e264` | Phase 8 end-to-end install/upgrade/uninstall lifecycle tests | done |
| `1f0487c` `79ceede` | Phase 10 scratch-artifact removal; ai-doc-check generated-exclusion + 403/429 | done |
| `dbc0497` | Phase 10b StubValidator CI gate | done |
| `4ec1495` | INSTALL-CATALOG standalone drift check | done |
| `670ea88` | Phase 8b read-only fixture coverage + clean copy failure | done |
| `70e12a9` `3c8578d` | Phase 5c extraDocs injection + template refresh channel | done |
| `b25b18c` | De-duplication: shared project.yml list parser | done |

Test suite: 265 → 293 → 366 passing, 0 failures, 6 skipped.

## Open Reviewer Findings (must fix before "complete")

> From review-diff of `e8fdf87..ac5cdf4`. Verdict: CONDITIONAL — guards are sound, but two
> behaviors are inert via the user-facing `ai.php` orchestrator, and uninstall can delete
> user files inside kit-owned directories.

- [x] **RF-1 (high) — orchestrator does not forward `--adopt`/`--allow-non-git`.**
      `tools/ai/commands/install_workflow.php:226-247` builds the subprocess command and omits
      these flags; `aiInstallerConfigFromAiArgs` (install_preflight.php:229) does not even parse
      them. So `ai.php install --apply --adopt` still aborts on `CONFLICT_FOREIGN`. The new
      adopt/non-git flow only works calling `install-ai-kit.php` directly (which the tests do).
      Fix: parse + forward both flags; add an orchestrator-path test.
- [x] **RF-2 (high) — upgrade `--apply` is a no-op for source updates.**
      `install_workflow.php:715` builds reinstall args without `--force`; without it the planner
      marks differing files `SKIP_EXISTING_UNMANAGED` and skips them, so `owned` auto-update
      files are never rewritten and the Phase 2 preserve-then-update flow never updates. (The
      missing `--force` is pre-existing at `e8fdf87`, but Phase 2 made the comment/intent false.)
      Fix: pass `--force` into the reinstall AFTER the `.ai/conflicts/`
      preservation step; add an end-to-end upgrade-apply test asserting an owned source-updated
      file is actually rewritten and the user edit is preserved in conflicts.
- [x] **RF-3 (medium, data-loss) — uninstall recursively deletes owned directory entries.**
      `install_workflow.php:819-820` calls `aiInstallerDeleteTree` on `dir`-type manifest entries
      (`.opencode/agents`, `.opencode/commands`, `.opencode/skills`, capability dirs). A user file
      added alongside kit files (e.g. `.opencode/agents/my-agent.md`) is deleted. Violates the
      "never delete directories / user bytes are sacred" invariant.
      Fix: delete only manifest-recorded files; refuse to recursively delete a directory that
      contains unrecorded entries; add a `repo-with-user-ai-content` uninstall test.
- [x] **RF-4 (medium) — missing orchestrator/upgrade-apply integration tests.**
      Every install integration test invokes `install-ai-kit.php` directly, so the orchestrator
      wiring (RF-1, RF-2) is unverified. Add `ai.php install --apply` and `ai.php upgrade --apply`
      tests.
- [x] **RF-5 (low) — `aiRunDoctor` duplicates the preflight env-check block** (~60-70%).
      Extract a shared `aiInstallerEnvironmentChecks()` helper consumed by both.
- [x] **RF-6 (low) — `aiInstallerResolveRuntimes` substring matching** misclassifies future
      pack names containing both `copilot`/`opencode`. Prefer an explicit per-pack runtime map.
- [x] **RF-7 (low, latent) — schema gap:** `aiInstallerMergeWorkflowManifest` synthesises minimal
      `['managed'=>true]` entries without `ownership` when canonical `files{}` is absent; such a
      manifest would fail `ai-install-manifest.schema.json` (requires `ownership`). Add `ownership`
      to synthesized entries or relax the schema for the fallback.

## Already done — do not rebuild

Source/template split (`packages/ai-universal-rules/`), `starter_profiles`, export pipeline +
`--check` in CI, composer/phpunit tooling, installer subcommands
(`preflight/plan/install/upgrade/rollback/verify`), read-only-default install, gitleaks wrapper,
adapter-drift CI, gitignore hygiene, 293-test suite. **Rejected permanently:** `canonical/`
rename, 8 profiles, 6 security docs, merging the two secret-scan files, blind-merging
`packages/.../docs/` pairs.

## Core invariants (every phase serves these; every gate proves one)

1. **Write-allowlist:** install/upgrade/uninstall may only touch manifest-planned files (first
   install), lock-listed files, or marked managed sections. Never directories. Everything else is
   `foreign` and byte-identical forever without per-path approval.
2. **User file wins:** any collision between an incoming kit file and an existing foreign file
   resolves in the user's favor by default.
3. **Ignore-before-backup:** the `.gitignore` ai-kit block is patched and proven effective
   (`git check-ignore`) before any backup/conflict byte is written.
4. **`plan` and `verify` write nothing.**
5. **Markers are frozen API:** `# BEGIN ai-kit`, `<!-- BEGIN ai-kit:user -->` never change without
   a versioned migration.
6. **No false enforcement claims:** a runtime is documented as enforced only if hooks are provably
   invoked.

---

# MUST-HAVE

## Phase 0 — Ownership Model & Lock (foundation)

> Partially landed: ownership/component/runtimes on manifest entries (72e8d93) and the
> install-manifest schema exist. Remaining: 5-class model, `.ai/manifest.lock.json`, createdDirs,
> schemaVersion everywhere, forward-compat guard.

- [x] Ownership classes (final, five stored + one implicit): `owned`, `rendered`, `template`,
      `patch-managed`, implicit `foreign`; `deprecated` is **computed at plan time**, never stored
      (963dd8c, a9a3d96)
- [x] Lock file (`.ai/manifest.lock.json`) entries: `path, ownership, component, runtimes, source,
      generator, sha256, mode, lineEnding, kitVersion, schemaVersion`
- [x] Lock records `createdDirs: []` (only these may be removed when empty)
- [x] `schemaVersion` on every authored machine-readable YAML config (d6a123e; JSON schemas use
      `$schema` by design)
- [x] Refuse operation when lock `schemaVersion` > CLI support (forward-compat guard, M3)

**Gates:** manifest 100% classified · lock schema valid in CI · newer-lock rejection tested.

## Phase 1 — First-Install Adoption & Conflict

> Mostly landed (d898180): git-root guard, opencode.jsonc adopt-or-conflict, `--adopt`. Remaining:
> RF-1 orchestrator forwarding; `AGENTS.md` marked-section/pointer mode; checksum-set adoption.

- [x] Fix RF-1: forward `--adopt`/`--allow-non-git` through the orchestrator
- [x] Classify against any known kit checksum → adopt into lock (30ad5e3; `ADOPT_KNOWN_KIT`)
- [x] Existing `AGENTS.md`: marked-section or pointer mode (`.ai/generated/AGENTS.md`)

**Gates:** existing user files preserved · foreign overwrite requires explicit flag · jsonc never
merged · out-of-root rejected · orchestrator path tested.

## Phase 2 — Patch-Managed Root Files

- [x] `.gitignore` managed block (insert/update/remove inside markers only):
      `.ai/logs/ .ai/backups/ .ai/conflicts/ .ai/templates-new/ .ai/local-manifest.json
      .ai/install.lock .repomix-context/ *.tmp *.bak`
- [x] Enforce invariant 3: ignore patched + `git check-ignore` verified before any backup write
- [x] Marker syntax frozen/versioned (invariant 5) (6a7979d; `markers.php` + freeze test)

**Gates:** block idempotent · user gitignore content untouched · `BackupNeverWrittenBeforeIgnoreEffectiveTest` green.

## Phase 3 — Ownership-Aware Upgrade, Migrations, Pruning

> Partially landed (79c098b): classification + conflict preservation. Remaining: RF-2 `--force`
> reinstall so updates actually apply; deprecated/incoming routing; migrations; idempotency
> (incl. the pre-existing SKIP_PROTECTED_CORE drift); createdDirs-only dir removal.

- [x] Fix RF-2: reinstall with `--force` after preservation so owned/auto-update
      files are actually rewritten
- [x] Upgrade per class: `owned` unchanged → update · `owned` user-modified → conflict (or
      `--force-owned`) · `rendered` → regenerate from `project.yml` · `template` → untouched
      (`--reset-templates` only) · `foreign` → invisible (3a4e6ee; `aiUpgradeResolveFileAction`)
- [x] Computed `deprecated`: unchanged → delete (already in backup) · modified →
      `conflicts/<ts>/removed/` — explicitly covers stale hooks/policies (a9a3d96, 430131f)
- [x] Incoming-path collision with foreign file → kit file to `conflicts/<ts>/incoming/` (ad59285)
- [x] `migrations/<version>/` run in order between installed and target version
- [x] Install idempotent: second run with no changes → zero diff (fixes SKIP_PROTECTED_CORE drift)
      (a9d4a6d)
- [x] Empty-dir removal only from lock `createdDirs`; no recursive directory deletes anywhere
      (252b95f)

**Gates:** idempotency · user-modified preserved · templates preserved · stale hooks pruned ·
obsolete-modified routed to conflicts · upgrade actually updates.

## Phase 4 — `project.yml`, Uninstall, Restore

> Uninstall landed (ac5cdf4) but has RF-3 (recursive dir delete). Remaining: fix RF-3; project.yml;
> restore.

- [x] Fix RF-3: uninstall removes manifest-recorded files only; never recursively deletes a
      directory that contains unrecorded (user) entries
- [x] `.ai/project.yml` (`template` class): single target-side values file; renderers consume it;
      upgrades re-render with same values → zero re-customization
- [x] Consolidate existing placeholder tokens to read from `project.yml` (39d49cf; 18 project-fact
      tokens, unset keys keep base defaults)
- [x] `restore --from <ts> [--path]`: checksum-gated copy-back, logged to `.ai/logs/` (59cb762)

**Gates:** uninstall never deletes user content · dry-runs write nothing · restore round-trips ·
checksum gate enforced.

## Phase 5 — User AI-Content Coexistence (CP-5)

- [x] Enforce invariant 1: file-level allowlist; foreign files excluded from all write/delete
      logic (manifest `files{}` + lock are the write allowlist; RF-3 uninstall preserves foreign)
- [x] Reserved namespace contract: kit never ships `local-*`, `*.local.*`, `**/local/**`; CI gate
      via `aiInstallerIsReservedUserNamespace` (validate-install-surface + pack-registry check + tests)
- [x] Ship `docs/ai/project/` with exactly three templates (`README.md`, `project-interaction.md`,
      `conventions.md`) (4cf41bf; template class)
- [x] `project.yml → context.extraDocs:`; renderers inject references; user pointers survive
      re-render (`<EXTRA_DOCS>` regenerated from project.yml each install)
- [x] `<!-- BEGIN ai-kit:user -->` sections preserved byte-for-byte in rendered files
- [x] Template refresh channel: upstream changes → `.ai/templates-new/<path>` + plan notice
- [x] `.ai/local-manifest.json`: gitignored, informational only, no write permission
      (ee304be; self-declares informational + not_a_write_allowlist)
- [x] Unified private structure (`0700`): `.ai/backups/<ts>-<op>/`,
      `.ai/conflicts/<ts>-<op>/{files,incoming,removed}/`, `.ai/templates-new/`
      (focused verification: `PrivateStructureTest`; affected installer/upgrade tests green)
- [x] Backups scoped to operation-touched files only; retention last 5 (`aiInstallBackupPruneOld`)

**Gates:** foreign agents/skills/docs survive upgrade and uninstall · collision → user wins ·
extraDocs survive re-render · template refresh never overwrites · backups mirror paths.

## Phase 6 — Installer Safety Hardening (transactional)

- [x] Read-only default enforced by the CLI itself; writes need `--apply` (orchestrator dry-run by
      default). `--overwrite-approved`/`--allow-core-overwrite` gate core overwrites
- [x] `PathGuard`: reject `..`, absolute escapes, symlink traversal, case-insensitive collisions
      (`aiInstallerAssertSafePlanTargets` + `aiInstallerAssertNoCaseCollisions`)
- [x] `Checksums` to lock on install (`sha256` per lock entry, `mode`, `lineEnding`); `doctor`
      validates via `aiInstallerCollectChecksumDrift`
- [x] `.ai/install.lock`: single process, stale-lock detection (`aiInstallerAcquireInstallLock`
      via flock); `doctor` flags a leftover lock as a possible interrupted install
- [x] SIGINT/SIGTERM → rollback or marked-recoverable; `verify` detects incomplete transactions;
      append-only `.ai/logs/` audit log; failed install auto-rolls back
      (focused verification: transactional rollback + incomplete-transaction tests green)

**Gates:** traversal/symlink/case-collision blocked · concurrent install rejected · interrupted
install recoverable · checksum mismatch fails verify.

## Phase 7 — PHP-Free Runtime, Policy Compile, Honest Enforcement

- [x] Policy compiler: `command-policy.tiers.yaml` → dependency-free compiled `case` table
      (`compile-command-policy.php` → `command-policy.compiled.sh`); wired into install
- [x] POSIX `tool-guardian.sh` beside `tool-guardian.ps1` (rule parity test-enforced)
- [x] Minimal local overrides: `project.yml → policy.allow[]`; hard no-downgrade of global
      denies/tier-≥3; wildcards rejected (compiler validation + install-time recompile)
- [x] Compiled/guardian policy closes gaps: deny `~/.ssh`, `~/.aws/credentials`, `.npmrc`,
      `.netrc`, `*.pem`, `*.key`; block `base64 -d | sh` obfuscation
- [x] Runtime enforcement matrix in `security.md` (invariant 6)
- [x] Document: PHP required only at install/upgrade time (`docs/ai/security.md`)

**Gates:** hooks run with zero deps · sh/ps1 parity · downgrade rejected · exfiltration/obfuscation
regression tests green · no over-claimed enforcement.

## Phase 8 — Consolidated Test Suite

- [x] Fixture coverage: empty-repo, existing-copilot/opencode (adoption), conflicts, readonly-files,
      symlink-escape (PathGuard), secrets (AdvisorSecretScan + ToolGuardian), user-ai-content
- [x] Lifecycle (install→upgrade→uninstall), adoption, patch/markers, user-content, safety/policy
      coverage; orchestrator + upgrade-apply paths (RF-4 via AI_CLI_REPO_ROOT integration tests)
- [x] shellcheck clean (guardian + compiled policy + doc-check scripts)

**Gate:** existing 293 + all above green; single largest score lever.

## Phase 9 — Drift Invariant

- [x] CI `drift-check` gate (`ai-doc-check drift`): repo-tool-inventory, generated-artifacts,
      agent-snippets, context-budgets, agent-spec, stub-surfaces, catalog-drift
- [x] Standalone INSTALL-CATALOG drift check (`validate-catalog-drift.php`) — closes the
      install-time-only regeneration gap
- [x] `GENERATED — DO NOT EDIT` headers on every rendered file
      (renderers + generated-artifact header validation; `validate-generated-artifacts` green)
- [x] Extend adapter-drift to all surfaces; resolve/allowlist standing soft-max WARNs
      (context-budget allowlist with justifications; adapter drift scans prompts/skills/workflows/templates)

## Phase 10 — Phantom Surface

- [x] `StubValidator` CI gate (`validate-stub-surfaces.php`): content-based phantom detection
      (empty-body md / no-statement sh), wired into drift gate
- [x] Unimplemented capabilities removed from catalog/browse (audit: `validate-stub-surfaces` +
      `validate-ai-catalog` green; no unimplemented stubs found)
- [x] Remove tracked scratch artifacts (`sh-commands-output.md` removed)

---

# Remaining Open Items (must-have phases 0-10)

## Landed this session (verified, tested, committed)

> Continuation work after `2257e03`. Each slice shipped with focused tests; full fast suite
> grew 366 → 408 passing, 6 skipped. Commits on `main`.

| Commit | Item | Work |
|---|---|---|
| `d6a123e` | **P0-a** | `schemaVersion` on `command-policy.tiers.yaml` + `policies/ai/policy.yaml`; `validate-ai-config.php` asserts it (negative test via temp repo clone). JSON Schemas excluded by design (`$schema` anchor). |
| `963dd8c` | **P0-b/1** | `patch-managed` first-class ownership (resolver + surface validator + manifest schema enum). |
| `a9a3d96` | **P0-b/2** | Computed `deprecated` at plan time (`aiUpgradeComputeDeprecated`, never stored); surfaced in upgrade dry-run report. |
| `3a4e6ee` | **P0-b/3, P3-a/b** | Per-class upgrade routing extracted to `aiUpgradeResolveFileAction` (rendered→regenerate, patch-managed→update-managed-block, owned→conflict/auto-update, `--force-owned`). |
| `430131f` | **P0-b/4, P3-b** | Apply-path `aiUpgradeRemoveDeprecated`: unchanged→delete (backed up), user-modified→`conflicts/<ts>/removed/`; recorded-files-only; end-to-end orchestrator test. |
| `30ad5e3` | **P1** | Checksum-set adoption: `tools/ai/known-kit-checksums.json` + `ADOPT_KNOWN_KIT` planner action (matches prior kit hash → adopt, not foreign-conflict). |
| `a9d4a6d` | **P3-d** | Full idempotency: identical core file under force → `SKIP_IDENTICAL_EXISTING` (was `SKIP_PROTECTED_CORE` drift). End-to-end zero-diff double-install test. |
| `252b95f` | **P3-e** | Empty-dir removal gated to lock `createdDirs`; `aiInstallerReadLockCreatedDirs`; never removes pre-existing/non-empty dirs. |
| `6a7979d` | **P2** | Marker syntax frozen/versioned: `tools/ai/install/markers.php` constants + `AI_MARKER_SCHEMA_VERSION` + freeze test pinning exact bytes. |
| `ad59285` | **P3-c** | Incoming foreign-collision → `aiInstallerOfferIncomingConflict` writes kit version to `conflicts/<ts>/incoming/`; foreign file never overwritten. |
| `39d49cf` | **P4-a** | 18 customizable project-fact placeholder tokens sourced from `.ai/project.yml`; unset keys keep base defaults; deterministic re-render. |
| `2dc5210` | (scope) | Out-of-scope VS Code extension installer isolated into its own commit before plan work. |

## Still genuinely incomplete

- [x] **P0 (closed):** DECIDED — no JSON schema change needed. All 15 JSON schemas anchor their
      identity via `$schema`; `tiers.yaml` + `policy.yaml` carry the integer `schemaVersion` they
      require. A separate authoring-version key on JSON schemas is not warranted and is not planned.
- [x] **P4-b:** `restore --from <ts> [--path]` checksum-gated copy-back, logged to `.ai/logs/`,
      dry-run writes nothing; wired into `ai.php` (59cb762)
- [x] **P5:** Ship `docs/ai/project/` 3 templates; `.ai/local-manifest.json` informational writer;
      unified `0700` private structure (`<ts>-<op>` subdirs incl. `incoming`/`removed`)
- [x] **P6:** SIGINT/SIGTERM rollback or marked-recoverable; `verify` detects incomplete
      transactions; append-only `.ai/logs/` audit; failed-install auto-rollback
- [x] **P9:** `GENERATED — DO NOT EDIT` headers on rendered files; resolve/allowlist soft-max
      WARNs; extend adapter-drift to all surfaces
- [x] **P10:** Audit catalog/browse for unimplemented capabilities and remove or mark

## Quality hardening (landed, verified)

- [x] **P3:** `GENERATED — DO NOT EDIT` headers on `AGENTS.template.md` +
      `.opencode`/`.github` agents/commands/skills + `installed-files.md`; soft
      `Managed by ai-kit` notice on `opencode.jsonc`; `_generated` metadata on hook JSON;
      enforcement in `validate-generated-artifacts.php` (shared `generated-header.php` helper)
- [x] **P4:** `## Editable vs Generated Files` section in `docs/ai/source-of-truth.md` +
      pointers in `project/README.md` and `POST-INSTALL.template.md`
- [x] **P5:** `ai-kit status` command (`php tools/ai/ai.php status`) reusing doctor/checksum
      helpers; honest policy-enforcement classification
- [x] **N1:** 8 stranded required tokens (`PRIMARY_STACK`, `FILE_PLACEMENT_RULES`,
      `NAMING_RULES`, `GOLDEN_EXAMPLES`, `FORMATTER_CONFIG_FILES`, `LINTER_CONFIG_FILES`,
      `EDITORCONFIG_PATH`, `IGNORE_FILES`) wired into `.ai/project.yml`
- [x] **N2:** Removed orphan `<BACKEND_PATH_GLOB>`

---

# NICE-TO-HAVE (95 → 97)

- [ ] **Phase 11 — Security doc:** one sectioned `security.md` (✓ landed `docs/ai/security.md`);
      add `DOGFOODING.md`
- [ ] **Phase 12 — Context economy:** budget validator on rendered install; catalog/browse
      on-demand; trim root instructions
- [~] **Phase 13 — Release trust (partial):** DONE — versioned `dist/` consumer-only export +
      `SHA256SUMS` + read-only `--verify` + aggregate context budget, wired into CI. REMAINING:
      SBOM/provenance, `composer audit` in CI, optional `ai-kit-verify.yml`, PHAR path.
- [ ] **Phase 14 — Compatibility:** `compatibility.md` + CI matrix (PHP 8.3/8.4, macOS/Linux/WSL);
      old-schema migrate-or-fail; monorepo/subdir; configurable `context.yml`
- [ ] **Phase 15 — Doc hygiene:** markdownlint, link check, Status/Source/Last-verified headers;
      rename `advisor/secret-scan.php` → `secret_scan_lib.php`

---

# Final surfaces

```text
ai-kit doctor | plan [--profile] | install --dry-run|--apply | upgrade --dry-run|--apply
       | verify | rollback --last | restore --from <ts> [--path] | status [--write]
       | uninstall --dry-run|--apply [--purge] | adopt --dry-run|--apply
```

# Hard 95+ gates

- [x] All tests green (incl. full Phase 8) · shellcheck 0 · secret scan 0
      (`composer test:fast`; `shellcheck`; `secret-scan --strict` green)
- [x] Drift 0 · adapter divergence 0 undeclared · stubs 0 unapproved · manifest 100% classified
      (`ai-doc-check --check`; adapter drift validator; install-surface strict green)
- [x] Idempotency 100% · rollback byte-match 100% · traversal/symlink/case 100% blocked
      (full fast suite plus focused rollback/recovery tests green)
- [x] Invariant proof: an upgrade changes bytes only in kit-owned-and-unmodified files or managed
      sections; all user-authored AI content byte-identical in place
- [x] Runtime hooks PHP-free, OS-parity-checked, honestly classified
- [x] Context budget under declared max · release bundle consumer-only with checksums
      (aggregate per-bundle budget `release.max_bundle_lines: 13500` enforced; `export-ai-universal-rules.php` writes `SHA256SUMS` + read-only `--verify`; `ReleaseBundleTest` + CI `--check`/`--verify` green)

# Sequencing & score path

Dependencies: RF-1..RF-7 first (close shipped gaps) → 0 → {1,2} → 3 → {4,5} → 6 → 7 → 8 → {9,10};
nice-to-haves parallel after 8. Each phase ships with its tests — never code first, tests later.

| Stage | Score | Status |
|---|---:|---|
| Verified baseline (`e8fdf87`) | ~82 | done |
| Landed (through `ac5cdf4`) + RF fixes (RF-1..RF-7) | ~86 | done (`b25b18c`) |
| Phases 0–4 (core of each landed; see Remaining Open Items for tails) | ~88 | substantially done |
| Phases 5–7 | ~92 | done (P5 templates dir + private-struct tail open) |
| Phases 8–10 | ~94–95 | core done; P9 headers/WARNs + P10 capability audit open |
| Phases 11–15 | 95–97 | not started (security.md landed) |

One-line summary the implementing AI should hold: **the lock is the only permission to write; the
user's bytes are sacred; every guarantee is a named test, not a promise.**
