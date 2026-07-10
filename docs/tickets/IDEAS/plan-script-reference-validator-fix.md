# Architecture Plan — Fix `validateAdapterScriptReferences` (script-reference validator)

- Ticket: `docs/tickets/IDEAS/improvements-scripts.md` (closes its 3 remaining "## TODO" validators)
- Source: architect scoping of improvements-scripts.md + implementer-side grounding (dry-run)
- Generated: 2026-07-09
- Plan file: docs/tickets/IDEAS/plan-script-reference-validator-fix.md
- Type: bugfix + bounded validator extension (in-place, no new CLI/registry/test harness)
- Risk: low (strengthens one existing read-only validator; proven to land green — 0 new violations)

## Context

`improvements-scripts.md` has a 2026-07-09 correction banner: most of its proposals are already
implemented, and `plan-28` owns the registry→permission pipeline. Only its 3 bottom "## TODO"
validators were potentially open. Architect verdict (with evidence):

| # | Validator | Verdict |
|---|---|---|
| 1 | Every script referenced by an agent/workflow must exist in `script-registry.json` | **PARTIALLY-COVERED** — real bounded gap (this plan) |
| 2 | Every registered script must have an owning workflow | **CLOSE — not applicable.** Needs a `primary_workflows`/`owning_workflow` registry field that was deliberately rejected (P6-REJECTED; plan-28 LOCKED negative AC-09 "no second mapping"). The inverse (workflow→registered-script) is exactly what validator 1 enforces. |
| 3 | Write-capable scripts must never be `allow` for read-only agents | **CLOSE — already covered** at two layers: `tests/php/RegistryProjectionTest.php:98-107`, `tests/php/ScriptRegistryInvariantTest.php:112-124`, and `tests/php/PermissionComposeTest.php:750-764` (guarded mutation scripts never `allow` for any composed agent). |

Validator 1 lives in `tools/ai/validate-install-surface.php` →
`validateAdapterScriptReferences()` (L672-708), already wired into AI-surface CI.

## Problem

The validator is almost entirely non-functional and carries a latent false-positive, and the two
defects currently cancel each other out (so CI is green today for the wrong reason):

1. **Guard bug (`validate-install-surface.php:698`)**: `preg_match_all(...) !== 1` skips every file
   whose script-reference count is not exactly 1. `preg_match_all` returns the match *count*, so
   files with 2+ references are silently skipped. Measured: **0 of 121** multi-reference files are
   actually scanned today.
2. **Unanchored-regex false positive (L698)**: `#(?:<SCRIPTS_ROOT>|scripts/ai)/([\w.-]+\.sh)#`
   matches the `scripts/ai/<name>.sh` substring inside `tests/scripts/ai/<name>.sh`. There is a real
   legitimate test script `tests/scripts/ai/test-ai-search.sh` referenced in 7 files; the regex
   would flag it as an unregistered `scripts/ai/test-ai-search.sh`. This is currently hidden ONLY
   because those 7 files are multi-reference and bug #1 skips them. Fixing bug #1 alone would expose
   bug #2 and turn CI red on a false positive.
3. **Scan-set gap (L686-692)**: the target set omits `.claude/agents/*.md`, so a `scripts/ai/*.sh`
   reference appearing only in a rendered Claude agent body is not validated. (Low practical risk —
   Claude bodies are generated from the same source that feeds `.github`/`.opencode` — but the
   ticket's literal "referenced by an agent" is only enforced through the adapter template layer.)

## Target Outcome

`validateAdapterScriptReferences` scans all agent/workflow/adapter markdown (including `.claude/agents`),
correctly checks every reference in multi-reference files, and does not false-positive on
`tests/scripts/ai/*`. Verified green: **0 unregistered-script violations** on the current tree.

## In Scope — the exact changes (all inside `tools/ai/validate-install-surface.php`)

1. **Guard fix (L698)**: `!== 1` → `< 1` (covers 0-and-error safely: `preg_match_all` returns
   `false` on error and `0` on no-match, both `< 1`; `>= 1` proceeds).
2. **Regex anchor (L698)**: add a negative lookbehind so the `scripts/ai` alternative only matches at
   a path boundary — `#(?:<SCRIPTS_ROOT>|(?<![\w/])scripts/ai)/([A-Za-z0-9._-]+\.sh)#`. The
   `(?<![\w/])` rejects `tests/scripts/ai/...` (preceded by `/`) while still matching
   `bash scripts/ai/foo.sh` (preceded by space) and the `<SCRIPTS_ROOT>/` form.
3. **Scan-set (L686-692)**: append `listMarkdownFilesUnder($root . '/.claude/agents')` to `$targets`.
4. **Regression test**: add a focused PHPUnit case (reuse the existing install-surface test pattern /
   `RegistryProjectionTest` setUpBeforeClass+realpath idiom — do NOT create a parallel harness)
   proving: (a) a fixture with 2+ references catches an unregistered one (guards bug #1), and (b) a
   `tests/scripts/ai/*.sh` reference is NOT flagged (guards bug #2).
5. **Ticket close-out** (documented HERE, not in the ticket file): `improvements-scripts.md` is
   itself pre-existing uncommitted WIP, so this change does not edit it (to avoid bundling unrelated
   WIP into this commit). Resolution of its 3 remaining "## TODO" validators:
   - Validator 1 (referenced script must be registered) — **DONE** via this fix.
   - Validator 3 (write-capable scripts never `allow` for read-only agents) — **already covered**:
     `tests/php/PermissionComposeTest.php` (guarded mutation scripts never `allow` for any composed
     agent) + `RegistryProjectionTest.php` / `ScriptRegistryInvariantTest.php`.
   - Validator 2 (registered script must have an owning workflow) — **not applicable**: presupposes a
     `primary_workflows`/`owning_workflow` registry field deliberately rejected (P6-REJECTED;
     plan-28 LOCKED negative AC-09 "no second mapping"). The inverse (workflow → registered script)
     is exactly what validator 1 enforces.
   The WIP owner should tick these boxes in `improvements-scripts.md` when the WIP lands.

## Out Of Scope (things to avoid)

- Do NOT add `primary_workflows`/`allowed_agents`/`owning_workflow` fields to the registry (rejected
  data model; violates P6-REJECTED and plan-28 LOCKED negative AC-09).
- Do NOT create a new `tools/ai/validate-*.php`, CLI subcommand, or second registry — extend the
  existing function in place (repo `>=75%` reuse rule).
- Do NOT touch the permission-composition layer, `.claude/settings.json` generation, or
  `command-policy.tiers.yaml` — those are plan-28's surface.
- Do NOT re-implement validator 3; it is already enforced twice.

## Affected Paths

- `tools/ai/validate-install-surface.php` (function `validateAdapterScriptReferences`, L686-698)
- NEW PHPUnit test `tests/php/InstallSurfaceScriptReferenceTest.php`
- `docs/tickets/IDEAS/improvements-scripts.md` — NOT edited by this commit (pre-existing WIP);
  close-out recorded in this plan's In-Scope item 5 instead

## Acceptance Criteria

- AC-01: `php tools/ai/validate-install-surface.php` exits 0 on the current tree (proven in planning:
  fixed guard + anchored regex + `.claude/agents` → 0 violations).
- AC-02: The regression test fails if the guard is reverted to `!== 1` (multi-ref file with an
  unregistered script must be caught).
- AC-03: The regression test fails if the regex anchor is removed (a `tests/scripts/ai/*.sh` fixture
  must NOT be flagged).
- AC-04 (negative): no new CLI, registry field, or parallel test harness is created.
- AC-05: full `vendor/bin/phpunit` shows no NEW failures vs. the pre-existing WIP baseline
  (RegistryProjectionTest, ScriptRegistryInvariantTest, AiScriptAccessManifestTest, PermissionComposeTest
  all stay green).

## Verification Plan

1. `php tools/ai/validate-install-surface.php` → exit 0.
2. New PHPUnit case green; revert-guard and remove-anchor mutations each make it red.
3. `vendor/bin/phpunit` → only the pre-existing WIP failures remain (the 8 unrelated catalog/render/settings ones).

## Risks And Rollback

- **Low.** Read-only validator change, proven green. If a future real unregistered reference appears,
  the validator correctly reddens — that is the intended behavior, resolved by registering the script.
- **Rollback**: revert the one function + delete the test.

## Handoff Notes

- Implement as a single bounded slice; all code lives in `validateAdapterScriptReferences`.
- Do not route any part into plan-28.
- Recommended next step: `implementer` for the 3 in-place changes + regression test, then close the
  three validators in `improvements-scripts.md`.
