# Phase 4 — Close the `--apply` No-Prompt Mutation Lane

Extends `./plan-phase2-scripts-migration.md` (P2b) and `./plan-phase3-registry-projection.md`.
Do **NOT** author a parallel ticket: same gateway/permission surface.

- Source: release-auditor SHIP-WITH-CONDITIONS verdict + reviewer FAIL verdict on the
  committed `tool:run *` allow rule (commits `f75ffe7`, `e30cec1`).
- Risk posture: **medium-high** — closes a permission-floor regression. The change itself
  is additive/narrowing (a guard, never a widening), so rollback is trivial.

## Problem (confirmed from source)

`"php tools/ai/ai.php tool:run *": "allow"` (`opencode.jsonc:154`,
`packages/ai-universal-rules/templates/core/opencode.json:154`) matches `--apply`. So
`php tools/ai/ai.php tool:run ai-edit --apply -- ...` runs a mutating, approval-required
script with **no OpenCode prompt**. The PHP `--apply` check is the sole boundary:

- `install_extras.php:687` `$wantsApply = in_array('--apply', $args, true);`
- `install_extras.php:688` approval gate returns exit 2 **only when `--apply` is absent**.
- `install_extras.php:418` real (non-dry-run) execution happens iff `--apply` present.

## Architect decision (locked)

Adopt **Fix A + Fix B**, with **B load-bearing** and **A defense-in-depth**.

- **Fix B (PHP gateway, mandatory):** the gateway refuses `--apply` for an
  approval-required tool unless an explicit human-set env `AI_GATEWAY_ALLOW_APPLY=1` is
  present. Returns the existing `mutating_requires_apply` reason code (already in the P3.6
  vocabulary, `install_extras.php:501`, currently unused) + `safe_next_step`. Exit 2
  (blocked), consistent with the existing approval gate.
  - Why env, and why this is not agent-bypassable in practice: OpenCode agent bash runs
    do not set `AI_GATEWAY_ALLOW_APPLY`; a human running the gateway in their own shell can
    export it deliberately. Combined with Fix A's prompt, the agent path is blocked at two
    layers. This is OQ-1-independent (pure PHP, statically testable).
- **Fix A (OpenCode glob, defense-in-depth):** add a later, last-match-wins rule
  `"php tools/ai/ai.php tool:run * --apply*": "ask"` after the `tool:run *` allow, so any
  `--apply` invocation prompts. A is NOT the sole guard (its match across `--`/arg-order is
  OQ-1, unverified statically); B remains the floor.

Rejected: C (brittle per-id allowlist), D (status quo — needs human risk acceptance).

## Constraints

1. **Template first, then mirror.** Edit `packages/ai-universal-rules/templates/core/opencode.json`
   then mirror byte-identically into `opencode.jsonc`. Parity is enforced by
   `AgentPermissionPolicyTest::projectConfigProvider` and `InstallerSafetyTest`.
2. **Do not rewrite the gateway logic** — it is correct and fail-closed. Only add the env gate.
3. **Read-only `tool:run *` stays `allow`** — preserve the friction-reduction goal.
4. **Worktree note:** an unrelated uncommitted scripts-restructure refactor is present
   (`scripts/ai/bin/**`, `scripts/ai/internal/**`). Keep Phase 4 edits isolated to:
   `tools/ai/commands/install_extras.php`, the two `opencode` config files,
   `tests/php/ToolGatewayTest.php`, `tests/php/AgentPermissionPolicyTest.php`. Do not touch
   the refactor's files.

## Slices

### P4.1 — Fix B: PHP env-gated `--apply` refusal  `[mandatory]`
- **What:** In `aiRunToolRunCommand` (`install_extras.php`), after the existing approval
  gate, when `requires_approval` AND `--apply` is present AND `getenv('AI_GATEWAY_ALLOW_APPLY')
  !== '1'`, return a `mutating_requires_apply` blocked payload (exit 2) instead of executing.
- **Files:** `tools/ai/commands/install_extras.php`.
- **Acceptance:**
  - [x] `tool:run ai-edit --apply` (no env) → exit 2, `reason=mutating_requires_apply`.
  - [x] `tool:run ai-edit --apply` with `AI_GATEWAY_ALLOW_APPLY=1` → proceeds to execution.
  - [x] `tool:run ai-edit` (no --apply) → still exit 2 `approval_required` (unchanged).
  - [x] read-only `tool:run ai-search -- ...` → unaffected.

### P4.2 — Fix A: OpenCode `--apply` ask rule  `[defense-in-depth]`
- **What:** Add `"php tools/ai/ai.php tool:run * --apply*": "ask"` immediately AFTER the
  `tool:run *` allow in BOTH config files (last-match-wins).
- **Files:** `packages/ai-universal-rules/templates/core/opencode.json` (source) then
  `opencode.jsonc` (mirror).
- **Acceptance:**
  - [x] both files contain the rule directly after `tool:run *: allow`; byte-identical.
  - [x] `validate-ai-config.php` passes; parity tests pass.

### P4.3 — Tests  `[mandatory]`
- **What:**
  - `ToolGatewayTest`: new case asserting `tool:run <mutating> --apply` (no env) → exit 2,
    mirroring `testToolRunMutatingToolWithoutApplyIsBlocked`; and (optionally) that with the
    env set it does not early-return at the gate.
  - `AgentPermissionPolicyTest`: drift case next to `testProjectConfigAsksBeforeInstallApply`
    asserting no `tool:run`-family pattern containing `--apply` resolves to `allow`, over both
    config files via `projectConfigProvider`.
- **Files:** `tests/php/ToolGatewayTest.php`, `tests/php/AgentPermissionPolicyTest.php`.
- **Acceptance:** [ ] both new tests fail before P4.1/P4.2 and pass after.

## Verification (all statically provable, no live session)

```
php -l tools/ai/commands/install_extras.php
php vendor/bin/phpunit --filter 'ToolGatewayTest|AgentPermissionPolicyTest' --no-coverage
php tools/ai/validate-ai-config.php
php tools/ai/ai.php registry:export --check
composer test:fast
```

Live (only to close OQ-1 for Fix A; not required for B's correctness):
- Confirm `tool:run <id> --apply` hits the `* --apply*` ask rule under OpenCode's tokenizer,
  both arg orderings. Tracked as OQ-1.

## Rollback / observability

- **Rollback:** revert the env-gate block (P4.1) and the two config lines (P4.2). Additive
  guard only; no data migration. The floor returns to the audited state, not worse.
- **Success signal:** `tool:run <mutating> --apply` from an agent context returns
  `mutating_requires_apply` (visible in `docs/ai/generated/tool-run.json`); a human with the
  env set can still run it. Failure signal: any agent-context mutation executing without the
  env or the prompt.

## Residual risk after Phase 4
- Fix B fully closes the agent no-prompt mutation lane (statically proven).
- Fix A's live behavior is still OQ-1 (needs a session) but is no longer the sole guard, so
  OQ-1 stops being a merge blocker for the mutation-safety concern.
- The release-auditor's `--apply` posture blocker is resolved by engineering rather than by
  asking an approver to accept the risk.

## Recommended next step
Implementer applies P4.1 → P4.2 → P4.3 in one bounded slice, runs the verification block,
then re-review. Keep OQ-1 (live Fix-A confirmation) as a follow-up, no longer blocking.

## Implementation status (2026-06-13) — DONE

- **P4.1 (Fix B) DONE** — `tools/ai/commands/install_extras.php`: after the existing
  approval gate, an approval-required tool invoked with `--apply` but without
  `AI_GATEWAY_ALLOW_APPLY=1` returns `mutating_requires_apply` (blocked, exit 2). Also
  mapped `mutating_requires_apply` to `blocked` status in `aiToolGatewayReasonPayload`.
- **P4.2 (Fix A) DONE** — added `"php tools/ai/ai.php tool:run * --apply*": "ask"` directly
  after the `tool:run *` allow in BOTH `packages/ai-universal-rules/templates/core/opencode.json`
  and `opencode.jsonc` (line 155, byte-identical, last-match-wins).
- **P4.3 (tests) DONE** — `ToolGatewayTest`: +3 assertions (block w/o env exit 2; bypass
  with env; `mutating_requires_apply`→blocked). `AgentPermissionPolicyTest`:
  `testProjectConfigNeverPlainAllowsToolRunApply` over both config files, asserting no
  `tool:run --apply` plain-allow, the `ask` override exists, and it is ordered AFTER the allow.

Verification run (all green):
- `validate-ai-config.php` = 0; `registry:export --check` = 0.
- `ToolGatewayTest` 17/17; `AgentPermissionPolicyTest` 136/136 (5 env-skip).
- Phase-4 + permission/installer-safety suites (`ToolGatewayTest|AgentPermissionPolicyTest|
  RegistryProjectionTest|ScriptRegistryInvariantTest|CommandPolicyCompilerTest|
  InstallerSafetyTest`): 245/245 (5 env-skip).
- `tool:run ai-edit --apply` (no env) → exit 2 `mutating_requires_apply`;
  with `AI_GATEWAY_ALLOW_APPLY=1 ... -- --help` → bypasses Fix B gate.

Known unrelated failure (NOT this slice): `composer test:fast` shows 2 failures for
`docs/ai/repo-required-tools.md` drift, owned by the separate uncommitted scripts-restructure
refactor (it added `scripts/ai/bin/**`). My Phase 4 files are not referenced by that doc. Left
for the refactor owner to regenerate (`php tools/ai/repo-tool-inventory.php --write`).

## Effect on the release-auditor blocker
- The `--apply` no-prompt mutation lane is now **closed by Fix B regardless of OpenCode
  tokenizer behavior** (statically proven). The release-auditor's blocking condition
  ("approver must own the no-prompt `--apply` posture") is resolved by engineering.
- OQ-1 (live Fix-A confirmation) is now a non-blocking follow-up: Fix A is defense-in-depth,
  not the sole guard.
