# AI Installation Pack Improvement Plan

> Verified against commit `e8fdf87` (branch `main`) on 2026-06-07.
> Repo-state claims were checked with `git ls-files`, `git grep`, file reads, and
> validator/test runs. Re-verify before acting if the tree has moved on.
> This document supersedes all earlier drafts; conflicting/stale claims have been removed.

## How To Read This Plan

- **Must-have** items are required for a trustworthy, lifecycle-safe 90+.
- **Nice-to-have** items push toward 95+ and polish, but are not blockers.
- **Already done / Verified** items are recorded so no one rebuilds working systems.
- Each behavioral change ships with a named test and an acceptance gate.
- Destructive or approval-gated actions stay as planned tasks; they are not auto-executed.

---

## Verdict Reconciliation

Score depends on what is judged:

- Live repository after validation evidence: ~82/100
- After must-have Phases 0.5-8: ~93/100
- After nice-to-have Phases 9-13: 95-96/100

The key correction versus external reviews and the stale pasted tree: **this repo already
implements most of the "source vs installed" architecture those reviews propose**, under
different names. Do not rebuild it. Anchor work to the verified gaps below.

The previous plan was provably safe only for **empty** target repos. The decisive remaining
work is making install/upgrade/uninstall safe for **existing** repos (adoption, patch-managed
root files, transactional pruning) and making runtime enforcement portable and honest.

### Verified Present (do not re-propose)

- Source/template split: `packages/ai-universal-rules/` is the authored package
  (`templates/core`, `templates/optional`, `templates/internal`, `policies/`, `schemas/`,
  `docs/`, `manifest.json`, `manifest.yml`). Repo root is the dogfooded install.
- Profiles exist as `starter_profiles:` in `packages/ai-universal-rules/manifest.yml`.
- Render/export pipeline: `tools/ai/export-ai-universal-rules.php` (with `--check` in
  `.github/workflows/validate-ai-surface.yml`).
- PHP tooling tracked: `composer.json`, `composer.lock`, `phpunit.xml.dist`.
- Installer CLI subcommands in `tools/ai/ai.php`: `preflight`, `plan`, `install`, `upgrade`,
  `rollback`, `verify`, `packs`, `placeholders`, `hooks` (47 cases total).
  `doctor` and `uninstall` are ABSENT (verified).
- Read-only-by-default install: `install … --dry-run` allowed, `install … --apply` gated
  (see `opencode.jsonc` permission rules).
- Overwrite protection: `tools/ai/install/verify-no-overwrite.php` plus generated-manifest
  `merge_strategy`/`managed` fields (in `tools/ai/install/manifest.php`) gate unmanaged
  replaces. NOTE: these fields live in the GENERATED install manifest, not in the authored
  `packages/ai-universal-rules/manifest.json`.
- `.gitignore` patcher is data-safe: `aiInstallerEnsureGitignoreEntries()`
  (`tools/ai/install/core.php`) is append-only, idempotent, content-preserving. It uses a
  comment header, NOT `# BEGIN/END ai-kit` markers, and does NOT call `git check-ignore`.
- Secret scanner wrapper: `tools/ai/secret-scan.php` shells to gitleaks/trufflehog and
  enforces exit codes in CI/strict mode.
- Adapter drift CI: `validate-adapter-drift.php --changed-only --fail-on-warn`.
- Hygiene in `.gitignore`: `.ai-backups/`, `.ai-logs/*`, `.repomix-context/`,
  `docs/ai/generated/`, `/dist/` ignored; `docs/ai/generated/` has a regenerate-then-check
  test (`tests/php/CliToolsTest.php`).
- Test suite passes: 265 PHPUnit tests + hook bats tests; includes `InstallerSafetyTest`,
  `AdvisorSecretScanTest`, `AgentPermissionPolicyTest`, `CopilotAgentRendererTest`,
  `CliToolsTest`.

### Verified Gaps (the real work)

- No file ownership classes (`owned`/`template`/`rendered`) in the authored manifest.
- No `schemaVersion` / `ownership` in the authored manifest.
- No `.ai/project.yml` target-side values file.
- No `uninstall` or `doctor` subcommand.
- No `migrations/` directory or versioned upgrade steps.
- No first-install adoption/conflict flow for pre-existing user files.
- No `opencode.jsonc` merge handler in the core installer (only referenced in `packs.php`
  and `validate-ai-config.php`) — must never be auto-merged.
- `.gitignore` patcher has no marked block (cannot cleanly self-remove on uninstall) and no
  `git check-ignore` ordering guard.
- Runtime hooks parse policy with `yq` at runtime (a PHP/YAML dependency inside the target).
- POSIX `tool-guardian.sh` missing (enforcement is `.ps1` Windows-only).
- One tracked scratch file: `sh-commands-output.md`.

### Corrected / Removed Claims (were wrong or stale)

- "No tests / no composer / no phpunit" — false; all present.
- "Add `canonical/` and rename `templates/`" — churn; breaks the export tool. Improve in place.
- "Resolve divergent `docs/ai/package/` pairs" — `docs/ai/package/` does not exist at repo
  root. Foundations pairs live under `packages/ai-universal-rules/docs/`; source-vs-rendered,
  investigate before any merge. Do not blind-merge.
- "Merge the two `secret-scan.php` files" — dangerous. `tools/ai/secret-scan.php` (gitleaks
  gate) and `tools/ai/advisor/secret-scan.php` (`aiAdvisorSecretScan()` library) are distinct
  subsystems. Keep both.
- "Build a `.gitignore` patch model from scratch" — already exists and is data-safe; only the
  marked-block wrapper and `git check-ignore` ordering are new.
- "Introduce a new ownership/merge vocabulary" — extend the existing `merge_strategy`/`managed`
  manifest fields instead of a parallel system.
- "Split security into 6 docs" — recreates stub-doc disease; one sectioned `security.md`.
- "Eight profiles" — drift machine; use 3 (minimal/standard/full) + components.
- ".ai-backups untrack/history-scrub" — already gitignored, never tracked; N/A unless history
  shows it was once committed.

---

# MUST-HAVE

## Phase 0.5 - First-Install Adoption & Conflict Handling

Blocks safely shipping into existing repos. The single biggest existing-repo gap.

### Tasks

- [ ] Pre-flight scan kit-claimed paths only: `AGENTS.md`, `.github/**` (kit paths),
      `.opencode/**`, `opencode.jsonc`, `.gitignore`.
- [ ] Classify each: absent -> install; matches a known kit checksum (any prior version) ->
      adopt into lock; differs/foreign -> preserve by default, require `--adopt` or
      `--overwrite-approved`; displaced copies routed per Phase 3.5 ordering.
- [ ] `opencode.jsonc`: never auto-merge. Absent -> install; checksum-matches known kit
      version -> adopt; foreign/modified -> conflict flow that prints the exact keys the kit
      needs and stops (M1).
- [ ] Refuse to run outside a git repo root with a clear error.
- [ ] Extend manifest `merge_strategy` vocabulary with `adopt`, `patch-block`, `pointer`,
      reusing the existing `verify-no-overwrite.php` gate.

### Acceptance Gates / Tests

- [ ] `ExistingAgentsFilePreservedTest`, `ForeignFileRequiresApprovalTest`,
      `AdoptKnownKitFileTest`, `ExistingOpencodeJsoncConflictTest`,
      `InstallOutsideRepoRootRejectedTest`.

---

## Phase 1 - File Ownership Classes And Lock

### Tasks

- [ ] Add an `ownership` field to every authored manifest entry: `owned` (overwritten freely,
      checksum-tracked), `template` (installed once, then user-owned, never overwritten),
      `rendered` (regenerated each install/upgrade from `.ai/project.yml`).
- [ ] Add `component` and `runtimes` fields so a file is classified once, not per profile.
- [ ] Generate `.ai/manifest.lock.json` on target: resolved files + SHA256 + ownership class
      + `mode` (exec bit) + line ending + kit version + `schemaVersion`.
- [ ] Add `schemas/ai/ai-kit-manifest.schema.json`; validate the authored manifest in CI.

### Acceptance Gates

- [ ] Every authored manifest entry declares ownership, component, runtimes.
- [ ] Lock lists every installed file with checksum, ownership, mode, line ending.
- [ ] Manifest schema validation passes in CI.

---

## Phase 2 - Ownership-Aware Upgrade And Reinstall

### Tasks

- [ ] On upgrade, compare on-disk checksum vs lock for each `owned` file: unchanged ->
      overwrite; user-modified -> do not clobber; show diff and route the user copy to
      `.ai/conflicts/` or require `--force-owned`.
- [ ] Never touch `template` files on upgrade unless `--reset-templates`.
- [ ] Re-render `rendered` files from `.ai/project.yml` so user values survive.
- [ ] Make `install` idempotent: a second run with no changes produces no diff.
- [ ] Add `migrations/<version>/` versioned upgrade steps; run in order between installed and
      target versions.

### Acceptance Gates

- [ ] Running install twice produces no diff.
- [ ] Upgrade preserves user-modified `owned` files (diff/conflict, no silent clobber).
- [ ] Upgrade preserves all `template` files by default.
- [ ] Rendered files keep user `project.yml` values across upgrade.
- [ ] Migration steps run deterministically and are tested.

---

## Phase 3 - Project Values File And Uninstall

### Tasks

- [ ] Introduce `.ai/project.yml` as the single target-side customization file (stack, test
      command, build command, src dirs, ticket prefix, runtimes).
- [ ] Renderers consume `.ai/project.yml`; upgrading re-renders with the same values.
- [ ] Consolidate existing placeholder tokens (`PLACEHOLDERS.md`,
      `project-placeholders.schema.json`) to read from `.ai/project.yml`.
- [ ] Add `uninstall --dry-run` and `uninstall --apply`: removes only lock-listed
      `owned`/`rendered` files; preserves `template` files and `project.yml` unless `--purge`;
      preserves backups/logs unless `--purge`.
- [ ] Add `doctor` (env + prerequisites check, read-only).

### Acceptance Gates

- [ ] `project.yml` drives all project-specific rendered values.
- [ ] Uninstall removes only owned/rendered lock-listed files.
- [ ] Uninstall preserves user `template` files and `project.yml` by default.
- [ ] Uninstall dry-run writes nothing.

---

## Phase 3.5 - Patch-Managed Root Files

### Tasks

- [ ] Wrap kit `.gitignore` lines in `# BEGIN ai-kit` / `# END ai-kit`; insert/update inside
      the block only; uninstall removes only that block. Reuse the existing append-only,
      content-preserving patcher; add markers + removal.
- [ ] ORDERING INVARIANT: patch the gitignore block and verify it is effective via
      `git check-ignore` BEFORE writing any backup/conflict file (M2 - prevents committing
      user secrets via `git add -A`).
- [ ] `AGENTS.md` in existing repos: marked-section or pointer mode
      (`See: .ai/generated/AGENTS.md`); full ownership only on empty installs or `--adopt`.

### Acceptance Gates / Tests

- [ ] `GitignorePatchPreservesUserContentTest`, `GitignorePatchIsIdempotentTest`,
      `BackupNeverWrittenBeforeIgnoreEffectiveTest`, `AgentsMarkedSectionUpdatedOnlyTest`.

---

## Phase 4 - Installer Safety & Transactional Completeness

### Tasks

- [ ] Confirm/extend read-only default; writes require `--apply`, overwrites require
      `--overwrite-approved` (or `--force-owned`).
- [ ] `PathGuard`: reject `..`, absolute paths outside target, symlink escapes, and
      case-insensitive path collisions (#9).
- [ ] `Checksums`: write SHA256 to lock on install; `verify` checks them.
- [ ] `.ai/install.lock` with stale-lock detection; concurrent install rejected.
- [ ] SIGINT/SIGTERM -> rollback or marked-recoverable state; `verify` detects incomplete
      transactions; failed install auto-rolls back to byte-identical prior state.
- [ ] Obsolete-file pruning on upgrade per ownership class (unchanged owned -> remove;
      modified owned -> `.ai/conflicts/removed/`; template -> preserve), explicitly removing
      STALE HOOK/POLICY files so an old policy cannot keep enforcing (#10).
- [ ] Enforce `0755` + LF on installed hooks (CRLF on an `sh` shebang breaks Windows-checkout
      hooks; missing exec bit = silent no-enforcement) (#8).
- [ ] Refuse to operate when lock `schemaVersion` > CLI supported, with actionable error (M3).
- [ ] Append-only `.ai/logs/` install/rollback audit log.

### Acceptance Gates / Tests

- [ ] Dry run writes nothing; apply writes only planned files.
- [ ] `verify` fails on checksum mismatch and on incomplete transactions.
- [ ] `ConcurrentInstallRejectedTest`, `InterruptedInstallLeavesRecoverableStateTest`,
      `UpgradeRemovesObsoleteOwnedFileTest`, `UpgradePrunesStaleHooksTest`,
      `ExecutableBitPreservedTest`, `CaseInsensitivePathCollisionRejectedTest`,
      `NewerLockVersionRejectedTest`, `PathTraversalRejectedTest`, `SymlinkEscapeRejectedTest`,
      `FailedInstallAutoRollbackTest`.

---

## Phase 5 - PHP-Free Target Runtime & Honest Enforcement

### Tasks

- [ ] Policy compiler: `policies/command-policy.tiers.yaml` -> generated, dependency-free
      `pre-tool-use` and `post-tool-use` (POSIX `sh` + `ps1`) with a compiled `case` table
      baked in at render time (no `yq` at runtime).
- [ ] Ship a POSIX `tool-guardian.sh` alongside `tool-guardian.ps1` so macOS/Linux Copilot
      enforcement is real, not Windows-only.
- [ ] Document: PHP is required only to run install/upgrade (a prerequisite, or a future
      PHAR); nothing PHP executes inside the target at runtime.
- [ ] Close known secret/exec gaps in the compiled policy:
  - deny reading `~/.ssh/`, `~/.aws/credentials`, `.npmrc`, `.netrc`, `*.pem`, `*.key`,
  - drop bare `env`/`printenv` from auto-allow (only allow safe `env VAR=… cmd` prefixes),
  - block obfuscated execution such as `base64 -d | bash`, `xxd -r | sh`.
- [ ] Minimal local policy override: `project.yml -> policy.allow[]` (exact command + tier +
      reason), compiled into generated hooks. Hard rule: local entries can NEVER downgrade a
      global deny or tier>=3 command; wildcards rejected. Overrides shown in `ai-kit plan`.
      (Prevents "unusable policy -> users disable hooks -> theater", #6.)
- [ ] Hardcoded, NON-configurable context-packing secret denylist floor: `.env*`, `*.pem`,
      `*.key`, `~/.ssh`, `.git/`, `.ai/logs|backups/` (#12 critical subset).
- [ ] Runtime enforcement matrix in `security.md`: each runtime marked
      enforced / partial / advisory based on whether hooks are actually invoked. No generated
      artifact or doc may claim enforcement for a partial/advisory runtime (#4).

### Acceptance Gates / Tests

- [ ] Generated hooks run with zero PHP and zero runtime YAML dependency.
- [ ] POSIX and PowerShell guardians enforce the same compiled policy.
- [ ] `LocalPolicyCannotDowngradeSudoTest`, `LocalPolicyCannotAllowCurlPipeShellTest`,
      `Base64DecodePipeShellBlockedTest`, `SecretFileNotReadableTest`,
      `EnvDumpNotAutoAllowedTest`, `ContextExcludesSecretsTest`,
      `GeneratedPolicyNotClaimedAsEnforcedUnlessHookedTest`.

---

## Phase 6 - Lifecycle And Policy Tests (Fixtures)

### Tasks

- [ ] Add fixtures: `empty-repo`, `existing-copilot-repo`, `existing-opencode-repo`,
      `repo-with-conflicts`, `repo-with-readonly-files`, `repo-with-symlink-escape`,
      `repo-with-secrets`.
- [ ] Add a golden `expected-installed-files.json` test.
- [ ] Policy classification tests: `CommandTierClassificationTest`, `DenyByDefaultTest`,
      `CurlPipeShellBlockedTest`, `ForcePushBlockedTest`, `SudoBlockedByDefaultTest`.
- [ ] Adapter parity test across all surfaces (agents + skills + prompts/commands +
      instructions): undeclared divergence fails CI.

### Acceptance Gates

- [ ] Existing 265-test suite still green.
- [ ] Lifecycle + policy fixtures pass.
- [ ] Adapter parity fails on undeclared divergence.
- [ ] Shellcheck clean for all shipped shell.

---

## Phase 7 - Drift And Generated-Surface Invariant

### Tasks

- [ ] Add a `drift-check` CI job: regenerate everything from `templates/` + manifest +
      `policies/`, then `git diff --exit-code` over rendered surfaces and the lock file.
- [ ] Put a `GENERATED - DO NOT EDIT. Source: …` header on every rendered file.
- [ ] Extend `validate-adapter-drift.php` to cover all duplicated surfaces, with an
      `adapter-deltas` declaration for intentional, reviewed divergences.
- [ ] Resolve the standing adapter-drift WARNs (missing references to `docs/ai/workflow.md`,
      `AI-GUARDRAILS.md`, `project-context.md`) or allowlist them explicitly.

### Acceptance Gates

- [ ] Regenerate -> `git diff --exit-code` is clean on every PR.
- [ ] 0 undeclared adapter drift.
- [ ] 0 standing drift warnings (fixed or explicitly declared).

---

## Phase 8 - Phantom Capabilities And Stale Files

### Tasks

- [ ] Inventory tiny markdown (<250 bytes) and tiny shell scripts (<5 lines).
- [ ] For each stub: complete it, delete it, or convert to an explicit pointer with a
      `Status: pointer - canonical source: <path>` header.
- [ ] Remove unimplemented capabilities from catalog/browse until implemented.
- [ ] Add a `StubValidator` CI gate: fail on shipped markdown below threshold not on an
      allowlist.
- [ ] Remove the one tracked scratch file `sh-commands-output.md`; confirm `.gitignore`
      covers `scc-*.csv`, `*.bak`, `*.tmp`.

### Acceptance Gates

- [ ] 0 unapproved stub docs.
- [ ] 0 advertised-but-unimplemented capabilities.
- [ ] No tracked scratch/audit artifacts.

---

# NICE-TO-HAVE

> Deferred (real value, not breaking): PHAR/Docker/Nix installer; monorepo/subdir install
> feature (keep only the root-only guard, already in Phase 0.5); configurable `.ai/context.yml`;
> `advisor/secret-scan.php` rename and a `DOGFOODING.md`. The critical subsets of these are
> already folded into Phases 0.5 and 5.

## Phase 9 - Security Documentation (Sectioned)

### Tasks

- [ ] Write one `docs/security.md` front door with sections: threat model, prompt-injection
      rules, data classification, supply-chain, hook-bypass limitations.
- [ ] Split a section into its own file only when it exceeds ~150 lines of real content.
- [ ] State the dogfood reality honestly: root `.github/`/`.opencode/` are rendered output,
      not source (point to `docs/architecture.md`).

### Acceptance Gates

- [ ] Security model documented in one authoritative place.
- [ ] No stub security docs.

---

## Phase 10 - Context Economy

### Tasks

- [ ] Point `validate-context-budgets.php` at the rendered install; compute auto-loaded bytes
      per runtime (AGENTS.md + matched instructions) and fail CI over a declared budget.
- [ ] Confirm large docs (`catalog.md`, `BROWSE.md`) are on-demand, not auto-loaded; split
      into index + detail if they are.
- [ ] Trim root instructions to non-negotiables + pointers; push detail into `applyTo`-scoped
      instruction files.

### Acceptance Gates

- [ ] Auto-loaded context under declared budget per runtime.
- [ ] Large browse/catalog docs are on-demand only.

---

## Phase 11 - Supply Chain And Release Trust

### Tasks

- [ ] Pin required tool versions; add `composer audit` (and npm audit where relevant) to CI.
- [ ] Generate release `SHA256SUMS`, an SBOM, and provenance metadata for `dist/` bundles.
- [ ] Define a network-access policy; reject unpinned downloads and `curl | sh` in policy.
- [ ] Build a versioned `dist/ai-kit-vX.Y.Z/` consumer bundle with only consumer-facing files.
- [ ] Ship one optional, self-contained `ai-kit-verify.yml` (~20 lines, pinned container) for
      consumers; keep the full validator/test/security/release workflows source-only.

### Acceptance Gates

- [ ] Dependency audits pass.
- [ ] Release bundle includes manifest, checksums, SBOM, provenance and excludes dev files.
- [ ] Full validator/test/drift/context-budget suite passes before bundle creation.

---

## Phase 12 - Compatibility And Schema Versioning

### Tasks

- [ ] Write `docs/compatibility.md`: OS (Linux/macOS/Windows-WSL), shells, PHP/Git/Composer/
      Node requirements, Copilot surfaces, OpenCode config version.
- [ ] Add a CI matrix for supported platforms where practical, and PHP 8.3/8.4 (CI currently
      pins only 8.2).
- [ ] Add `schemaVersion` to every authored machine-readable file.
- [ ] Add migration-or-clear-failure for old schema versions; backwards-compat tests for
      previous release manifests.

### Acceptance Gates

- [ ] Compatibility doc matches CI matrix.
- [ ] Unsupported platforms fail clearly.
- [ ] Old schema versions migrate or fail with an actionable message.

---

## Phase 13 - Markdown And Doc Hygiene

### Tasks

- [ ] Ensure planning/spec docs use one H1 then `##`/`###` (markdownlint MD025).
- [ ] Add a `Status / Source / Last-verified` header to docs that make repo-state claims.
- [ ] Resolve broken `See Also` links (e.g. `docs/ai/MCP-BOUNDARIES.md` pointing at
      non-existent `../foundations/…` under `docs/ai/`).

### Acceptance Gates

- [ ] markdownlint clean for authored docs.
- [ ] No broken intra-repo doc references (link check in CI).

---

# Target CLI Surface (After Must-Haves)

```text
ai-kit doctor                     # env + prerequisites, read-only
ai-kit plan [--profile=X]         # show changes, read-only
ai-kit install --dry-run|--apply  # apply needs --apply; overwrite needs --overwrite-approved
ai-kit upgrade --dry-run|--apply  # ownership-aware; runs migrations; user values survive
ai-kit verify                     # checksums + drift + policy render freshness
ai-kit rollback --last
ai-kit uninstall --dry-run|--apply  # owned/rendered only; templates kept unless --purge
```

# Target Consumer Footprint (standard profile)

```text
target-project/
- AGENTS.md                      # rendered (structure=kit, values=.ai/project.yml)
- .github/{copilot-instructions.md,instructions/,prompts/,skills/,hooks/}  # owned + compiled hooks
- .opencode/{agents/,commands/,skills/}                                    # owned
- opencode.jsonc                 # adopt-or-conflict; permissions from compiled policy
- .ai/
  - project.yml                # template - user edits, survives upgrades
  - manifest.lock.json         # ownership + checksums + mode + kit version + schemaVersion
  - bin/{pre-tool-use,post-tool-use}   # compiled POSIX, zero deps
  - docs/{install.md,security.md,troubleshooting.md}
  - conflicts/                 # displaced user files on conflict
  - logs/                      # gitignored; installer appends .gitignore block
```

---

# Hard 95+ Gates

- [ ] Test suite: 100% pass (existing 265 + new lifecycle/security/parity).
- [ ] Shellcheck: 0 issues.
- [ ] Secret scan: 0 findings.
- [ ] Generated drift: 0 (regenerate -> `git diff --exit-code`).
- [ ] Stub docs: 0 unapproved.
- [ ] Manifest/schema validation: 100% (incl. ownership/component/runtimes/schemaVersion).
- [ ] Adapter drift: 0 undeclared diffs.
- [ ] Context budget: under declared max per runtime.
- [ ] Install idempotency: 100%.
- [ ] First install into an existing repo: 0 user files clobbered without approval.
- [ ] `opencode.jsonc` never auto-merged (adopt-or-conflict only).
- [ ] Gitignore effective (`git check-ignore`) before any backup write.
- [ ] Upgrade preserves user-modified owned + all template files: 100%.
- [ ] Obsolete/stale hook + policy files pruned on upgrade: 100%.
- [ ] Installed hooks: `0755` + LF; lock schemaVersion forward-compat guard active.
- [ ] Rollback byte match: 100%.
- [ ] Path traversal / symlink / case-collision escape: 100% blocked.
- [ ] Runtime hooks are PHP-free and parity-checked across OS.
- [ ] Local policy overrides cannot downgrade global denies.
- [ ] Release bundle contains only consumer-facing files; supply-chain checks pass.

# Score Path

| Stage | Expected Score |
|---|---:|
| Live repo evidence (commit `e8fdf87`) | ~82 |
| Must-have Phases 0.5-3.5 (adoption, ownership, upgrade, uninstall, patch-managed files) | ~88-90 |
| Must-have Phases 4-8 (transactional safety, PHP-free runtime, tests, drift, stubs) | ~93 |
| Nice-to-have Phases 9-13 | 95-96 |
