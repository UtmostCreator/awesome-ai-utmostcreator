# Runbook — P2b: Wire the PHP Gateway into OpenCode Permissions

Execution runbook for **P2b** of the Phase-2 plan
(`./plan-phase2-scripts-migration.md`). This is the "how to run it correctly
without wiring/permission breakage" guide. **Posture-changing** → route through
**release-auditor** before merge. Do **NOT** author a new ticket.

## Goal / non-goals

- **Goal (one bounded outcome):** make the already-built gateway reachable as a
  narrow, safe `bash` allow lane so agents call ONE command shape
  (`php tools/ai/ai.php tool:run <id> -- <args>`) instead of raw piped commands.
- **Non-goals:** no new gateway logic (it exists), no weakening of any
  `deny`/`ask` floor, no script moves, no Symfony/`tool-registry.json`, no
  capability-ID rename.

## Why this is needed (evidence)

- The gateway EXISTS and fails closed: `tools/ai/ai.php:84-86,251-255`;
  `tools/ai/commands/install_extras.php:500-622` (unknown id→exit 1, profile
  mismatch→exit 1, approval-without-`--apply`→exit 2).
- It is UNREACHABLE: `opencode.jsonc` has NO `php tools/ai/ai.php tool:*` allow
  rule; the `bash` block (`opencode.jsonc:73+`) starts with `"*":"ask"` (line 74),
  so a `tool:run` call falls through to `ask`. THIS is the P2b gap.
- Observed friction: `sed -n '/.../,/}/p' file`, `grep ... | head`, and a compound
  `ls && echo && test -f` were all BLOCKED by the bash matcher (pipe/compound) —
  first-hand proof of the friction the single gateway command removes.

## Source-of-truth vs generated (edit the right files)

- **SOURCE template (canonical):** `packages/ai-universal-rules/templates/core/opencode.json`.
- **Adopted/rendered:** root `opencode.jsonc` (header: "Managed by ai-kit … do not
  auto-merge — use ai-kit plan/adopt"). Edit BOTH in lockstep.
- **Never** edit installed `.opencode/**` or `.github/**` copies (generated).
- Known drift to fix in the same change: the template has 3 `ai-search-multi.sh`
  allow lines absent from rendered `opencode.jsonc` — add them to the rendered file.

## Preconditions checklist (run BEFORE editing)

1. Worktree: `git status --short` — note the pre-existing `docs/tickets/**`
   edit-allow change already in `opencode.jsonc` + template; you must NOT bundle it.
2. Gateway works locally (no perms needed, these are allowed):
   - `php tools/ai/ai.php tool:list`
   - `php tools/ai/ai.php tool:describe ai-search`
   - `php tools/ai/ai.php tool:run ai-search --dry-run -- --mode tracked "Needle"`
3. Baseline green: `php tools/ai/validate-ai-config.php` and
   `bash scripts/ai/run-test-focused.sh --filter 'ToolGateway|AgentPermissionPolicy'`.

## Validator mechanics (so you do not break the config gate)

- `tools/ai/validate-ai-config.php` validates ONLY NAMED patterns via
  `requirePermissionValue()` (`:832-839`); it does NOT reject extra/unknown bash
  keys. **Therefore: ADDING the gateway allow rules needs NO validator change.**
- Keep these UNCHANGED (the floor): `permission.bash.*` must stay `ask`/`deny`
  (`:731`); raw read/search `grep*/rg*/find*/fd*/cat*/sed*/awk*` pinned at `:768-769`;
  destructive at `:772-773`; mutating at `:776-777`; native `grep`/`glob`/`list`
  enforced `allow` at `:812` (P2a — do not revert); `read` secret-denies `:785-787`.
- A validator change is only needed if you choose to ENFORCE the gateway rules
  exist — that is the P2c follow-up (a drift test), not part of shipping P2b.

## Steps

### Step 1 — Add the discovery rules (safe, OQ-1-independent)

In BOTH `packages/ai-universal-rules/templates/core/opencode.json` and
`opencode.jsonc`, inside the `bash` object (after the existing
`php tools/ai/ai.php …` allow lines), add:

```jsonc
"php tools/ai/ai.php tool:list*": "allow",
"php tools/ai/ai.php tool:describe*": "allow",
```

These take only structured/no args and contain no pipes, so they are not subject
to OQ-1. **Verify:** `php tools/ai/validate-ai-config.php` (OK), JSON still parses.
**Rollback:** remove the two lines.

### Step 2 — Fix the template drift (same change)

Add the 3 missing `ai-search-multi.sh` allow lines to rendered `opencode.jsonc`
so it matches the template:

```jsonc
"bash scripts/ai/ai-search-multi.sh *": "allow",
"AI_OUTPUT=json bash scripts/ai/ai-search-multi.sh *": "allow",
"env AI_OUTPUT=json bash scripts/ai/ai-search-multi.sh *": "allow",
```

**Verify:** template vs rendered diff now shows no unintended divergence.

### Step 3 — OQ-1 GATE (blocking) — the execution rule

Add the run rule to BOTH files:

```jsonc
"php tools/ai/ai.php tool:run *": "allow",
```

Then RUN THE LIVE OPENCODE TEST (cannot be answered by reading source):

- In an OpenCode session, confirm WITHOUT a prompt:
  `php tools/ai/ai.php tool:run ai-search -- --mode tracked "Needle"`
- Confirm a multi/quoted-arg variant is also allowed without prompt:
  `php tools/ai/ai.php tool:run ai-search -- --mode text "Needle Two" --fixed`
- Confirm a mutating id still fails closed (approval): `tool:run ai-edit` → exit 2.

**Pass:** all read-only `tool:run` calls allowed without prompt; mutating still
gated. **Fail (OQ-1 not satisfied):** do NOT keep the broad rule. Fall back to one
of:

- (a) narrower per-id rules, e.g.
  `"php tools/ai/ai.php tool:run ai-search*": "allow"` repeated per read-only id; OR
- (b) OQ-6: OpenCode custom tools (`https://opencode.ai/docs/custom-tools/`) that
  take structured args and eliminate OQ-1 — but require TS/JS in `.opencode/tools/`
  + a JS/Bun runtime (this is a pure-PHP repo with no `package.json`), so treat as
  a larger, separate decision.

### Step 4 — (Optional, recommended) keep bash.* coexisting

Do NOT flip `bash.*` to `deny` in this slice. Leave it `ask`. Native
`read`/`glob`/`grep`/`list` (already `allow`) cover reads; the gateway covers
scripts. Tightening `bash.*` to `deny` is a separate, higher-risk slice (would
break `head`/`tail`/`git grep` flows until every agent migrates) — see OQ-3.

## How to change permissions SAFELY (rules)

- **No-downgrade:** never weaken `bash.*`, raw read/search, destructive, mutating,
  or `read` secret-denies. A change may only NARROW (add `deny`/`ask`) or add a
  NARROW `allow` for a fail-closed gateway lane.
- **Edit source then render:** change the template first, mirror into `opencode.jsonc`;
  never the installed `.opencode/**`.
- **Stable prefixes only:** use `tool:list*`/`tool:describe*`/`tool:run *`. NEVER
  `php tools/ai/ai.php *` or `tool:*` (would auto-allow future dangerous subcommands).
- **Enforcement stays in PHP + the deny floor:** the gateway is a safe LANE, not a
  fence; the hard floor remains `opencode.jsonc` deny/ask + Copilot
  `.github/hooks/*.json` (`docs/ai/security.md`). Do not move enforcement out of them.
- **Worktree hygiene:** HUNK-STAGE only your gateway lines; do NOT commit the
  pre-existing `docs/tickets/**` edit-allow change. Pattern used previously:
  `git add -p <file>` answering `n` to the pre-existing hunk, `y` to your hunk.

## Verification checklist

- `php tools/ai/validate-ai-config.php` → OK
- `php tools/ai/validate-install-surface.php --strict` → 0 ERROR
- `bash scripts/ai/run-test-focused.sh --filter 'ToolGateway|AgentPermissionPolicy'` → green
- `composer test:fast` → full suite green
- Live OQ-1 test (Step 3) → documented pass/fail in the PR
- `bash scripts/ai/ai-doc-check.sh --check` → OK

## Rollback

P2b is additive: remove the added `tool:list*`/`tool:describe*`/`tool:run *` (and
Step-2 drift lines) from both files and re-run `validate-ai-config.php`. No data
migration, no enforcement removed (the floor never changed).

## Acceptance criteria

- [x] Discovery rules (`tool:list*`, `tool:describe*`) present in template + rendered. (Step 1, commit `5cced30`)
- [x] Template/rendered drift resolved (`ai-search-multi.sh` lines). (Step 2, commit `5cced30`)
- [x] OQ-1 settled by a LIVE OpenCode run, with evidence in the PR; the shipped
      run rule (broad or narrowed) matches that evidence. **(Step 3 confirmed 2026-06-13)**
- [x] No `deny`/`ask` floor weakened; `validate-ai-config.php` green. (Steps 1-2 verified)
- [x] Mutating ids still fail closed via the gateway (exit 2 without `--apply`). **(re-confirmed at Step 3)**
- [x] release-auditor sign-off recorded. **(conditional yes for scoped P2b only, 2026-06-13)**

### Progress

- **Steps 1-2 DONE** (commit `5cced30`): discovery rules + drift fix shipped, additive,
  no floor change. Verified: `validate-ai-config` OK, `validate-install-surface --strict`
  0 errors, gateway `tool:list` works, focused + full suite (692) green.
- **Step 3 `tool:run *` allow ADDED** (commit `f75ffe7`), **OQ-1 live-confirm done;
  release-auditor conditional sign-off recorded for scoped P2b only.** Evidence gathered live this session:
  - `tool:run ai-search -- --mode tracked "Needle"` → ran, exit 0, argv parsed through `--`.
  - `tool:run ai-search -- --mode text "Needle Two" --fixed` → ran (multi + quoted args).
  - `tool:run ai-edit` (mutating) → `status=blocked`, `reason=approval_required` (fails closed).
  - User-provided live OpenCode evidence after commit `f75ffe7`: `tool:list`, `tool:describe`,
    `tool:run ai-search -- --mode tracked "Needle"`, and `tool:run ai-search -- --mode text
    "Needle Two" --fixed` wrote their expected generated artifacts with no approval-prompt
    interruption reported. This settles OQ-1 for the broad `tool:run *` rule in the running
    OpenCode session.
  - Local follow-up evidence: `php tools/ai/ai.php tool:run ai-edit` wrote
    `docs/ai/generated/tool-run.json` with `status=blocked`, `reason=approval_required`,
    `requires_approval=true`; mutating ids still fail closed without `--apply`.
  - Release-auditor sign-off (2026-06-13): **conditional yes for scoped P2b only**. The
    `tool:run *` rule is an additive allow over the existing `bash "*":"ask"` default; no
    existing `deny` or `ask` rule is weakened. Runtime execution remains bounded by the PHP
    gateway, which blocks approval-required tools without `--apply` using
    `reason=approval_required`. OQ-1 is settled by user-provided live OpenCode evidence showing
    `tool:list`, `tool:describe`, and quoted/`--` `tool:run ai-search` invocations completed
    without approval-prompt interruption. This sign-off excludes unrelated current
    unstaged/untracked worktree changes.

## Follow-up (next slice, not P2b)

- **P2c** — registry↔permission drift test: enforce that every read-only profile
  tool has a generated allow path and that rendered `opencode.jsonc` matches the
  template, so the wiring cannot silently regress. Add the `requirePermissionValue`
  assertions for the gateway rules at that point.

## Ranking of this move

| Dimension | Score (0-100) | Note |
| --- | ---: | --- |
| Confidence | 70 | Steps 1-2 high; Step 3 gated on the live OQ-1 test |
| Safety | 78 | additive + no floor change; release-auditor gate |
| Accuracy | 88 | wiring/validator mechanics verified from source |
| Helpfulness | 90 | removes the pipe-friction root cause for agents |
