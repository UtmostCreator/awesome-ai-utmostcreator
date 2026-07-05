# Architecture Plan — post-install agent: grant its own gate command

- Ticket: none
- Source: agent permission audit (researcher session ses_137a8cae2ffe), 3-slice decomposition
- Generated: 20260614-230848
- Plan folder: docs/tickets/arch-todo-post-install-verify-placeholders-grant-20260614-230848/
- Status: **Done** (verified 2026-07-05; independently re-confirmed against current repo state
  during doc-hygiene reconciliation — implemented in commit
  `f7a8de711042a3961332759d122376126c3cc039`, already on `main` for weeks)
- Rank: Slice 1 of 3
- Risk: **LOW** (single permission line in one agent template + re-render)

## Context

`packages/ai-universal-rules/templates/core/agents/post-install.md` defines a STRICT
Placeholder Resolution Gate whose clearance proof requires two commands
(post-install.md:251, :268):

- `php tools/ai/ai.php placeholders --fail` — already granted (`'php tools/ai/ai.php placeholders*': allow`, post-install.md:113)
- `php tools/ai/verify-install-placeholders.php` — **NOT granted**

The bash allowlist only grants `'php tools/ai/validate-*.php *': allow` (post-install.md:112).
`verify-install-placeholders.php` is a `verify-*` prefix and does not match `validate-*`, so it
falls through to the `bash "*": deny` baseline → blocked at runtime. The agent therefore cannot
legitimately clear its own mandatory gate. The script exists and is shipped
(`tools/ai/verify-install-placeholders.php`, packs.php:78,320), so this is a permission gap, not
a missing-script reference. `bootstrapper.md:153-154` proves the kit grants `verify-*` scripts
explicitly elsewhere, confirming the pattern.

## Goal / Acceptance Criteria

- [x] AC-1: `templates/core/agents/post-install.md` grants `php tools/ai/verify-install-placeholders.php`
  in its `bash:` permission map (allow), placed near the existing `validate-*.php` grant (line ~112).
  **VERIFIED:** `'php tools/ai/verify-install-placeholders.php*': allow` present at
  `packages/ai-universal-rules/templates/core/agents/post-install.md:165`, directly below the
  `validate-*.php` grant.
- [x] AC-2: The grant is a literal/prefix match that the OpenCode glob resolves before the `"*": deny`
  catch-all (last-match-wins ordering preserved; deny baseline stays at top as it is now).
  **VERIFIED:** the grant is at line 165; the `'*': deny` catch-all is at line 169 (after it); no
  reordering of the baseline.
- [x] AC-3: Re-render adapters so the installed `.opencode/agents` / `.github/agents` copies (if present
  in this repo) reflect the template, OR confirm the render pipeline is the source-of-truth path.
  **VERIFIED:** grant present in all three rendered adapter copies in this repo:
  `.opencode/agents/post-install.md:165`, `.github/agents/post-install.agent.md:77`, and
  `.claude/agents/post-install.md:89`.
- [x] AC-4: `php tools/ai/validate-script-access.php` passes (no new violations).
  **VERIFIED:** re-ran `php tools/ai/validate-script-access.php` —
  `OK: script-access + agent-governance consistency checks passed`.
- [x] AC-5: Any existing agent-permission test still passes; if a test asserts post-install's grants,
  extend it to assert the new grant.
  **VERIFIED:** `verify-install-placeholders.php` is referenced by name in
  `tests/php/InstallerSafetyTest.php` (lines 838, 848, 1802, 2046) and
  `tests/php/PlaceholderRegistryTest.php:98`; the gate-clearance grant itself is exercised via
  `php tools/ai/validate-script-access.php` (AC-4) rather than a standalone permission-map assertion.

## Steps

1. Read post-install.md:108-122 to confirm exact surrounding lines and indentation.
2. Add one line after post-install.md:112:
   `'php tools/ai/verify-install-placeholders.php*': allow`
   (mirror the quoting/style of adjacent entries; trailing `*` to allow args/flags).
3. Locate the render/validate path: `tools/ai/render-agent-permissions.php` and
   `tools/ai/validate-script-access.php`. Run the validator.
4. If this repo ships a rendered copy under `.opencode/agents/post-install.md` or
   `.github/agents/post-install.agent.md`, re-render via the documented generator (do NOT
   hand-edit a generated file) and confirm the grant propagated.
5. Verify (see below).

## Things To Avoid

- Do NOT broaden line 112 to `verify-*.php` wildcard unless necessary — a targeted single grant is
  the smallest safe change and avoids accidentally permitting other `verify-*` scripts.
- Do NOT remove or reorder the `bash "*": deny` baseline.
- Do NOT hand-edit a generated/rendered adapter file; edit the template and re-render.
- Do NOT touch any other agent's permissions in this slice.

## Verification

- `php tools/ai/validate-script-access.php` — must pass.
- `php -l packages/ai-universal-rules/templates/core/agents/post-install.md` is N/A (markdown);
  instead validate frontmatter via the agent-spec/script-access validator.
- If an agent-permission phpunit test exists: `vendor/bin/phpunit --filter <AgentPermission|ScriptAccess>`.
- Grep proof: the new grant line is present and ordered before the deny catch-all.

## Rollback

Revert the single-line template change; no data or schema impact.
