# Architecture Plan — Compact Agent Bash-Policy Bodies

- Ticket: arch-todo-compact-agent-bash-policy-bodies-20260708T190021Z
- Source: architect design handoff (compact-agent-bash-policy-bodies)
- Generated: 2026-07-08T19:00:21Z
- Plan file: docs/tickets/arch-todo-compact-agent-bash-policy-bodies-20260708T190021Z/plan.md
- Branch: claude-agent-fleet-remediation

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan.md` and move it into `archive/` under this ticket folder (`docs/tickets/arch-todo-compact-agent-bash-policy-bodies-20260708T190021Z/archive/DONE-plan.md`). See "Archive On Completion" below for the exact steps.

## Context

The rendered Copilot `## Shell Boundary` and Claude `## Bash Command Policy` agent bodies currently embed a repeated ~104-line enumerated bash allowlist. That enumeration duplicates content whose authoritative, enforced location is elsewhere: the OpenCode `permission:` YAML frontmatter, `.claude/settings.json`, and the compiled Copilot tier policy (`.github/hooks/tool-policy.json` / `pre-tool-use.sh`). The duplication inflates rendered agent bodies (~224 lines Copilot, ~241 lines Claude), risks false-parity claims, and is hard to maintain. This plan persists the architect design that replaces those enumerated bodies with a compact policy block plus a pointer to the canonical registry and per-agent enforced surfaces, and adds a user-facing How-To for changing permissions.

## Problem

The enumerated allowlist is restated in advisory rendered bodies where it is NOT enforced, causing: (a) large, drifting bodies, (b) confusion about which surface actually enforces, and (c) risk of false-parity language across Copilot/OpenCode/Claude. The enumeration must stay only on the surfaces that enforce it, and the bodies must ROUTE to the per-agent enforced surface instead of BEING the list.

## Target Outcome

- Copilot `## Shell Boundary` and Claude `## Bash Command Policy` rendered bodies carry a compact policy paragraph + pointers instead of the full enumerated list.
- The full enumerated allowlist remains intact and unchanged on its enforced surfaces (OpenCode `permission:` YAML; Claude `.claude/settings.json`).
- Section shape is consistent across all three providers: allowed tier, authoritative enforced list per runtime, and fallback when enforcement is unavailable — with no false-parity claims.
- Per-agent authorization is preserved; each rendered agent points to its OWN enforced surface (`.opencode/agents/<id>.md`) and `docs/ai/agent-script-access.md`.
- A new `## Changing Permissions (How-To)` doc section explains how to change permissions across all three providers.
- All changes flow through the generator/renderers, stay within ai-file-standards line budgets, and pass existing validators.

## In Scope

Exactly what this plan covers — the 4 SOURCE files plus the mandated regeneration:

- Edit `tools/ai/install/copilot-agent-renderer.php`: replace the `## Shell Boundary` per-command bullet loop (~lines 111-116) with the compact policy block. Keep pre-check steps (100-110) and closing (117-118). Keep the "no execute -> no Shell Boundary section" branch (asserted by `CopilotAgentRendererTest` line 147: architect has none).
- Edit `tools/ai/install/claude-agent-renderer.php`: replace the `## Bash Command Policy` per-command bullet loop (~lines 82-149) with the compact block. KEEP the header (78-81) and the closing `.claude/settings.json wins` paragraph (150-152) verbatim (a test asserts it). Reconcile/remove the now-dead special cases: refactorer cluster-collapse (89-125) and release-auditor/researcher string rewrites (202-245).
- Edit `docs/ai/agent-script-access.md`: add a new `## Changing Permissions (How-To)` section (chosen over a new file; overlap check found no existing file >=75% — candidates agent-script-access.md ~40%, command-policy.md ~25%, adapter-contract.md ~30%).
- Edit `tests/php/CopilotAgentRendererTest.php` and `tests/php/ClaudeAgentRendererTest.php`: assert the new compact shape and NOT require the full enumerated list; preserve existing header assertions and the `.claude/settings.json wins` assertion.
- Regenerate generated artifacts via `php tools/ai/render-adapters.php --write` — regenerates ~22 files under `.claude/agents/*.md` and `.github/agents/*.agent.md` (generated output; must match renderer output, NOT hand-edited).

## Out Of Scope (Things To Avoid)

Explicit non-goals — must NOT be touched or added:

- N-1: Do NOT modify `.claude/settings.json`.
- N-2: Do NOT modify OpenCode `permission:` YAML or `aiPermissionRenderOpenCodeBlock()`.
- N-3: Do NOT modify `.github/hooks/tool-policy.json`, `pre-tool-use.sh`, or the compiled tier policy.
- N-4: Do NOT hand-edit any rendered `.claude/agents/*.md` or `.github/agents/*.agent.md`; all regeneration via renderer + `render-adapters.php --write`.
- N-5: Do NOT collapse per-agent allowlists into one shared generic list.
- N-6: Do NOT touch the 2 not-yet-migrated agents' inline blocks (release-auditor, architecture-plan-writer).
- N-7: Do NOT introduce false-parity claims; keep the advisory-vs-enforced distinction from `docs/ai/security.md`.
- Do NOT build the OpenCode-shaped compiled output from `command-policy.tiers.yaml` (Phase-2 note in `command-policy.md`).
- Do NOT migrate the 2 legacy agents (release-auditor, architecture-plan-writer) to layer composition.
- Do NOT create a brand-new standalone doc file.
- Do NOT change `docs/ai/script-registry.*` or `docs/ai/scripts-reference.md` catalog content.
- Do NOT change OpenCode agent bodies (the existing `## Script Access` body section already points to `docs/ai/agent-script-access.md` — leave as-is).

## Affected Paths

Source files (hand-edited):

- `tools/ai/install/copilot-agent-renderer.php`
- `tools/ai/install/claude-agent-renderer.php`
- `docs/ai/agent-script-access.md`
- `tests/php/CopilotAgentRendererTest.php`
- `tests/php/ClaudeAgentRendererTest.php`

Generated files (regenerated only via `render-adapters.php --write`, never hand-edited):

- `.claude/agents/*.md` (~part of the ~22 regenerated files)
- `.github/agents/*.agent.md` (~part of the ~22 regenerated files)

Enforced surfaces that MUST NOT appear in the diff (safety invariant):

- `.opencode/agents/*.md` `permission:` blocks
- `.claude/settings.json`
- `.github/hooks/tool-policy.json`, `pre-tool-use.sh`, compiled tier policy

## Contracts And Boundaries

- Per-agent specificity mechanism: the `.opencode/agents/<id>.md` reference in both compact blocks is generated from the agent id (`<id>` = filename stem), so each rendered agent names its OWN OpenCode enforced surface. The compact body no longer IS the allowlist; it ROUTES to the per-agent enforced surface + `docs/ai/agent-script-access.md` (already per-agent-aware). No agent collapses to a generic list.
- The compact block must remain a function of `$agentId` AND of whether the agent has execute/Bash, to preserve the no-execute -> no-section branch (Copilot architect agent has no `## Shell Boundary` section).
- Claude closing paragraph (`.claude/settings.json wins`) and Claude header must be preserved verbatim — both are test-asserted.
- OpenCode `permission:` YAML frontmatter is the enforced surface for OpenCode; body carries no enumerated list. Left unchanged.
- Enforced-vs-advisory distinction is the canonical honesty contract from `docs/ai/security.md`; no false-parity language.

### Compact body literal target — Copilot (`## Shell Boundary`)

```markdown
## Shell Boundary

You may use shell execution only for approved scripts from the repository registry. Before running any script:

1. Confirm the script exists in the repository.
2. Confirm it is listed in `docs/ai/script-registry.md` and `docs/ai/script-registry.json`.
3. Confirm it is also documented in `docs/ai/scripts-reference.md`.
4. Run it from the repository root using the repository-root path shown below.
5. If any condition fails, stop and report `unknown`.

**Allowed tier:** read/verify wrappers under `<SCRIPTS_ROOT>` plus focused test/validate commands for this agent's role. This agent's exact approved-command allowlist is defined per-agent, not restated here — see its authoritative surface below.

**Authoritative enforced list (per runtime):**
- GitHub Copilot CLI: enforced by the pre-tool hook `.github/hooks/tool-policy.json` → `<SCRIPTS_ROOT>/pre-tool-use.sh` (compiled from `docs/ai/command-policy.tiers.yaml`).
- This agent's per-command authorization: `docs/ai/agent-script-access.md` (per-agent tiers) and the OpenCode `permission:` block in `.opencode/agents/implementer.md`.

**When enforcement is unavailable** (e.g. the VS Code custom-agent surface, which is advisory only): treat this section as required policy, run only registry-listed scripts, and do not claim automatic enforcement.

Treat `<SCRIPTS_ROOT>/pre-tool-use.sh` as the canonical pre-execution policy gate and `<SCRIPTS_ROOT>/post-tool-use.sh` as the canonical post-execution evidence writer.

Do not run arbitrary shell commands. Do not run: `rm`, `mv`, `cp`, `chmod`, `curl | sh`, install commands, unregistered `<SCRIPTS_ROOT>/*.sh`, `git push`, `git reset`, deploy commands.
```

Note: `.opencode/agents/implementer.md` above is the illustrative example for the `implementer` agent; the renderer substitutes the actual `<id>` (filename stem) per agent. `<SCRIPTS_ROOT>` is the renderer's existing scripts-root token.

### Compact body literal target — Claude (`## Bash Command Policy`)

```markdown
## Bash Command Policy

Claude Code frontmatter cannot express per-command bash allowlists — only the tool-level `Bash` grant above. Treat this section as required agent policy; hard enforcement depends on `.claude/settings.json`.

**Allowed tier:** read/verify wrappers under `<SCRIPTS_ROOT>` plus focused test/validate commands for this agent's role. This agent's exact approved-command allowlist is defined per-agent, not restated here.

**Authoritative enforced list (per runtime):**
- Claude Code: enforced by `.claude/settings.json` `permissions.allow`/`permissions.deny` (a coarser, fleet-wide baseline).
- This agent's per-command authorization: `docs/ai/agent-script-access.md` (per-agent tiers) and the OpenCode `permission:` block in `.opencode/agents/implementer.md`.

**When enforcement is unavailable:** there is no `ask` approval tier on Claude Code — any script the `ask`-tier prose describes is not runnable here unless it also appears in `.claude/settings.json`. Do not run arbitrary shell commands.

Do not run — and `.claude/settings.json` hard-blocks — `rm -rf`, `sudo`, `git push --force`, `git reset --hard`, `git clean -f`, `curl`, `wget`.

Hard enforcement (beyond this advisory body policy) lives in `.claude/settings.json` `permissions.allow`/`permissions.deny` rules. If this list and `.claude/settings.json` disagree, `.claude/settings.json` wins — it is the enforced surface, not this body text.
```

Note: the final `.claude/settings.json wins` paragraph and the header must be preserved verbatim from the current renderer (both test-asserted). `.opencode/agents/implementer.md` is the per-`<id>` illustrative example.

### New doc section outline (`docs/ai/agent-script-access.md` -> `## Changing Permissions (How-To)`)

1. Where permissions live — one source (`tools/ai/install/permission-layers/*.php`), three projections (OpenCode `permission:` YAML, Claude `settings.json` + advisory body, Copilot hooks + advisory body); enforced-vs-advisory pointer to `docs/ai/security.md`.
2. Add or remove a command — edit the relevant pack/composition in `permission-layers/`, then `php tools/ai/generate-agent-permissions.php --write` (OpenCode block) and `php tools/ai/render-adapters.php --write` (Copilot/Claude bodies).
3. Make it more granular — per-agent vs global: per-agent via composition entry; global via shared pack.
4. Per-agent vs global — how composition keys (filename stem) map to agents; note the 2 legacy inline agents.
5. Claude enforced floor — `.claude/settings.json` is hand-maintained/union-merged from `templates/claude/settings.json`, NOT projected from the seam; edit separately (Phase-2 gap per `command-policy.md`).
6. Copilot enforced hook — `docs/ai/command-policy.tiers.yaml` → `php tools/ai/compile-command-policy.php`.
7. Validate — full gate list (`render-adapters.php --check`, `generate-agent-permissions.php --check`, `validate-adapter-drift.php`, `composer test`).

## Todo Plan

- [ ] P0: Run `php -l tools/ai/install/copilot-agent-renderer.php` and `php -l tools/ai/install/claude-agent-renderer.php` to capture a clean pre-edit syntax baseline.
- [ ] P0: Edit `tools/ai/install/copilot-agent-renderer.php` — replace the `## Shell Boundary` per-command bullet loop (~111-116) with the compact policy block; keep pre-check steps (100-110) and closing (117-118); keep the no-execute -> no-section branch intact.
- [ ] P0: Edit `tools/ai/install/claude-agent-renderer.php` — replace the `## Bash Command Policy` per-command bullet loop (~82-149) with the compact block; keep header (78-81) and closing `.claude/settings.json wins` paragraph (150-152) verbatim.
- [ ] P0: In `claude-agent-renderer.php`, reconcile/remove the now-dead special cases — refactorer cluster-collapse (89-125) and release-auditor/researcher string rewrites (202-245) — that reference the enumerated list (main hidden complexity).
- [ ] P0: Ensure both compact blocks remain a function of `$agentId` (per-`<id>` `.opencode/agents/<id>.md` reference) and of whether the agent has execute/Bash (preserve no-section branch). Do NOT collapse to a generic shared list (N-5).
- [ ] P1: Update `tests/php/CopilotAgentRendererTest.php` — assert the compact shape and that the full enumerated list is NOT required; preserve the header assertion and the architect-has-no-section assertion (line 147).
- [ ] P1: Update `tests/php/ClaudeAgentRendererTest.php` — assert the compact shape and that the full enumerated list is NOT required; preserve the header assertion and the `.claude/settings.json wins` assertion.
- [ ] P1: Add the `## Changing Permissions (How-To)` section to `docs/ai/agent-script-access.md` per the 7-point outline above.
- [ ] P0: Run `php -l` on both renderers again post-edit (syntax clean).
- [ ] P0: Run focused tests — `vendor/bin/phpunit --filter CopilotAgentRendererTest`, `--filter ClaudeAgentRendererTest`, and `--filter PermissionRenderAdaptersTest`.
- [ ] P0: Run `php tools/ai/render-adapters.php --write`, then `git diff --stat` to confirm ONLY `.claude/agents/*.md` + `.github/agents/*.agent.md` changed (plus the 5 source files); then `php tools/ai/render-adapters.php --check`.
- [ ] P0: Run `php tools/ai/generate-agent-permissions.php --check` — OpenCode block untouched, must be in sync.
- [ ] P0: Run `php tools/ai/validate-adapter-drift.php --changed-only --fail-on-warn`.
- [ ] P1: Run the full suite — `composer test:fast` (fall back to `composer test` only if triaging ordering).
- [ ] P1: Confirm each regenerated file is within budget (Copilot hard-max 300, Claude hard-max 320; expect ~120-140 lines, down from ~224/~241) and measurably reduced.
- [ ] P2: Confirm enforced-surface invariant — `.opencode/agents/*.md` `permission:` blocks and `.claude/settings.json` do NOT appear in the diff.

## Acceptance Criteria

- [ ] AC-01: Copilot + Claude rendered bodies no longer contain the full enumerated allowlist; they carry the compact policy block + pointers to `docs/ai/agent-script-access.md` and the registry docs. (Verify: `render-adapters.php --write` output + focused grep of a sample regenerated agent file shows compact block, no enumerated list.)
- [ ] AC-02: Full enumerated allowlist remains intact on enforced surfaces (OpenCode `permission:` YAML; Claude `.claude/settings.json`); neither is modified. (Verify: `git diff --stat` shows no `.opencode/agents/*.md` or `.claude/settings.json` changes.)
- [ ] AC-03: Consistent compact section shape across all three providers, each stating allowed tier, authoritative enforced list per runtime, and fallback when enforcement unavailable — with no false-parity claim. (Verify: inspect rendered Copilot + Claude bodies + confirm OpenCode `## Script Access` unchanged.)
- [ ] AC-04: Per-agent authorization preserved; each body points to its OWN enforced surface `.opencode/agents/<id>.md` (id = filename stem). (Verify: two distinct sample agents render distinct `<id>` references.)
- [ ] AC-05: `## Changing Permissions (How-To)` in `docs/ai/agent-script-access.md` covers add/remove, granularity, per-agent vs global, source file to edit, regenerate command, and validators — for all three providers. (Verify: section present with all 7 outline points.)
- [ ] AC-06: CI gates pass — `php tools/ai/render-adapters.php --check`, `php tools/ai/generate-agent-permissions.php --check`, `php tools/ai/validate-adapter-drift.php --changed-only --fail-on-warn`, and `composer test`. (Verify: each exits 0.)
- [ ] AC-07: Rendered line counts are within ai-file-standards budgets (Copilot <=300, Claude <=320) and measurably reduced from the pre-change ~224/~241. (Verify: `wc -l` on regenerated sample files.)
- [ ] AC-08: After renderer edits, `render-adapters.php --write` regenerates all shipped files so committed bytes match renderer output. (Verify: `render-adapters.php --check` exits 0 with no pending diff.)
- [ ] AC-09: OpenCode agent bodies unchanged. (Verify: `git diff --stat` shows no changes under `.opencode/agents/**`.)
- [ ] AC-10: Renderer unit tests updated to assert the compact shape and NOT require the full list; header assertions and the `.claude/settings.json wins` assertion preserved. (Verify: `--filter CopilotAgentRendererTest` and `--filter ClaudeAgentRendererTest` pass with new assertions.)

## Verification Plan

Each step names the command or inspection surface that proves an AC:

- `php -l tools/ai/install/copilot-agent-renderer.php` / `php -l tools/ai/install/claude-agent-renderer.php` — renderer syntax clean.
- `vendor/bin/phpunit --filter CopilotAgentRendererTest` — proves AC-01, AC-10 (Copilot compact shape, no full list, header + architect-no-section preserved).
- `vendor/bin/phpunit --filter ClaudeAgentRendererTest` — proves AC-01, AC-10 (Claude compact shape, no full list, header + settings.json-wins preserved).
- `vendor/bin/phpunit --filter PermissionRenderAdaptersTest` — proves render-adapters projection integrity.
- `php tools/ai/render-adapters.php --write` then `git diff --stat` — proves AC-08 regeneration and scoping (only source files + `.claude/agents/*.md` + `.github/agents/*.agent.md`).
- `php tools/ai/render-adapters.php --check` — proves AC-08 (committed bytes == renderer output).
- `php tools/ai/generate-agent-permissions.php --check` — proves AC-02/AC-09 (OpenCode block in sync, untouched).
- `php tools/ai/validate-adapter-drift.php --changed-only --fail-on-warn` — proves AC-03/AC-06 (no drift, no false-parity policy violations).
- `git diff --stat` inspection for `.opencode/agents/**` and `.claude/settings.json` absence — proves AC-02, AC-09, and the enforced-surface safety invariant.
- `wc -l` on regenerated sample `.claude/agents/*.md` and `.github/agents/*.agent.md` — proves AC-07 budgets and reduction.
- Manual inspection of two distinct rendered agent bodies — proves AC-04 (per-`<id>` reference) and AC-03 (shape parity, no false-parity).
- Manual inspection of `docs/ai/agent-script-access.md` — proves AC-05 (7-point How-To present).
- `composer test:fast` (or `composer test`) — proves AC-06 full suite.

## Risks And Rollback

- Risk level: **MEDIUM** — permission-policy prose across enforced-surface-adjacent renderers. No enforced surface changes, but honesty/parity errors carry a safety-perception blast radius.
- Main hidden complexity: reconciling the refactorer cluster-collapse (claude-agent-renderer.php ~89-125) and the release-auditor/researcher string-rewrite special cases (~202-245) that reference the enumerated list. Removing the enumeration can leave these paths dead or inconsistent — reconcile them deliberately, not by blind deletion.
- Rollback posture: `git revert` the source commit and re-run `php tools/ai/render-adapters.php --write` to restore the prior rendered bodies.
- Success signal post-merge: CI `validate-ai-surface.yml` green; `.opencode/agents/*.md` `permission:` blocks and `.claude/settings.json` byte-identical to pre-change (must NOT appear in the diff — the observable safety invariant).

## Archive On Completion

When every `## Todo Plan` item AND every `## Acceptance Criteria` item is checked `[x]`:

1. `mkdir -p docs/tickets/arch-todo-compact-agent-bash-policy-bodies-20260708T190021Z/archive`.
2. Write the full plan contents to `docs/tickets/arch-todo-compact-agent-bash-policy-bodies-20260708T190021Z/archive/DONE-plan.md`.
3. Replace this `plan.md` with a one-line tombstone pointing to `./archive/DONE-plan.md`.

Do not archive while any item is still unchecked.

## Handoff Notes

- Recommended next step: hand off to the implementer agent using OpenCode command: /implement
- Start with the P0 syntax baseline and the two renderer edits; treat the special-case reconciliation in `claude-agent-renderer.php` as the highest-risk sub-step.
- Preserve the Claude header and `.claude/settings.json wins` paragraph verbatim — both are test-asserted.
- Never hand-edit generated `.claude/agents/*.md` or `.github/agents/*.agent.md`; regenerate via `render-adapters.php --write` only.
- The enforced-surface diff invariant (`.opencode/agents/*.md` permission blocks and `.claude/settings.json` unchanged) is the primary safety check — verify it before considering the slice complete.

signal: done
