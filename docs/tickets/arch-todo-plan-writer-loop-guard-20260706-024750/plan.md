# Architecture Plan — Plan-Writer Loop Guard + Archive mv-Denial

- Ticket: none
- Source: architect design handoff (Project 2 of 5)
- Generated: 20260706-024750
- Plan folder: docs/tickets/arch-todo-plan-writer-loop-guard-20260706-024750/
- Sequence: **Project 2 (SECOND)** in a five-plan effort. Execution order across the effort is 1 -> 2 -> 3 -> 4 -> 5.
- Risk: LOW-MEDIUM

## Global Constraints

- Edit ONLY shipped template sources under `packages/ai-universal-rules/templates/**` and installer/generator PHP under `tools/ai/install/**`. `.claude/`, `.opencode/`, `.github/`, `AGENTS.md`, `CLAUDE.md` are GENERATED — never hand-edit; fix the template/generator so a re-install regenerates them.
- **Constraint-#1 EXCEPTION (user-approved):** `.opencode/skills/architecture-plan-writer/SKILL.md` is REPO-LOCAL with NO template source and MAY be edited directly. It is flagged in-plan wherever it is touched.
- Logging is OUT OF SCOPE. Do not touch `docs/tickets/arch-todo-runner-agnostic-logging-core-20260706/**` or any dirty logging file.
- MUST-NOT-TOUCH dirty in-flight files (on main): README.md, docs/ai/script-registry.json, docs/ai/script-registry.md, docs/ai/scripts-reference.md, docs/ai/verification-matrix.md, install-ai-kit.sh, schemas/ai/evidence-event.schema.json, scripts/ai/MANIFEST.md, scripts/ai/ai-verify.sh, scripts/ai/common.sh, scripts/ai/internal/ai-verify/90-run.sh, scripts/ai/internal/lib/30-logging.sh, tests/scripts/ai/test-common.sh, tools/ai/install/script-registry.php, tools/ai/validate-ai-config.php, tools/ai/validate-install-surface.php (dirty — run for verification, do NOT edit), plus untracked logging additions.

## Context

The architecture-plan-writer agent has an infinite/duplicate markdown-rewrite risk in its Update/Expand modes, and an archive path that must work around a `mv` denial with a write+tombstone flow.

## Problem

- Template `packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md`: frontmatter denies `mv`/`cp`/`rm` via a trailing `'*': deny` (line 53); prose confirms this at line 228.
- Update Mode (lines 127-135) and Expand mode (lines 151-154) re-emit full content, creating duplicate-block risk.
- Existing guards: read-first (line 129), content-compare (line 133), loop-stop-on-repeat (lines 134, 241). There is NO numeric "stop after 3 attempts" guard in the agent template — that numeric guard lives only in `packages/ai-universal-rules/templates/snippets/behavioral-baseline.snippet.md:15-18`.
- Archive uses a write+tombstone workaround (line 228) because `mv` is denied. Deferred root cause is noted at `docs/tickets/arch-todo-code-quality-gate-and-agent-rules-20260705T131645Z/plan.md:49`.
- The repo-local skill `.opencode/skills/architecture-plan-writer/SKILL.md` (lines 64-67) restates the guards but has NO template source (constraint-#1 EXCEPTION, user-approved direct edit).

## Target Outcome

- The agent template carries a concrete numeric hard-stop (verify-landed -> stop after N=3 -> never re-append after a blocked/failed edit), mirroring `behavioral-baseline.snippet.md:17-18`.
- The idempotent archive write+tombstone flow is clearly documented so it cannot loop.
- The repo-local skill wording is aligned and flagged as a constraint-#1 exception.

## In Scope

- Add an explicit numeric hard-stop and no-re-append guard to the agent template's Update/Expand-mode sections.
- Clarify the idempotent archive write+tombstone flow in the template.
- Align the repo-local skill wording (constraint-#1 exception).
- Record — but not implement — the approval-gated option to narrowly relax the archive `mv` deny.

## Out Of Scope (Things To Avoid)

- Broadening `edit`/`bash` permissions beyond `docs/tickets/**`.
- Removing any existing loop guard (add to them, never remove).
- Creating a second plan file for the same ticket (the very bug class this agent guards against).
- Implementing the `mv` deny relaxation without explicit user sign-off (relaxing a deny is approval-gated).
- Any logging files or the dirty must-not-touch list.

## Affected Paths

- `packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md` — add concrete hard-stop (verify-landed -> stop after N=3 -> never re-append after blocked/failed edit, mirroring `behavioral-baseline.snippet.md:17-18`); clarify idempotent archive write+tombstone flow.
- `.opencode/skills/architecture-plan-writer/SKILL.md` — **CONSTRAINT-#1 EXCEPTION** (repo-local, no template — direct edit approved): align guard wording.

## Contracts And Boundaries

- The agent's write surface must stay strictly `docs/tickets/**`; no permission widening.
- Existing guards (read-first, content-compare, loop-stop-on-repeat) must remain; new guards are additive.
- Template line-budget hard max for `.opencode/agents/*.md` / core agent templates is 320 lines (see `docs/ai/ai-file-standards.md`) — the edit must stay within budget.
- The archive flow must remain `mv`-free (write to `archive/` + tombstone the original) until/unless the deny is relaxed with sign-off.

## Todo Plan

- [x] P0-1: Add an explicit numeric hard-stop + no-re-append guard to the agent template's Update/Expand-mode sections (verify-landed -> stop after N=3 -> never re-append after a blocked/failed edit), mirroring `behavioral-baseline.snippet.md:17-18`; clarify the idempotent archive write+tombstone flow. (Strengthened per IMPROVEMENTS: added a bounded-edit algorithm — read-first, heading-bounded section replacement, verify-landed, explicit "one attempt" definition, stop-after-3, never-re-append — plus explicit archive partial-state handling for all 4 states.)
- [x] P1-1: Align the repo-local skill wording in `.opencode/skills/architecture-plan-writer/SKILL.md` (flag as constraint-#1 exception).
- [x] P2-1: (needs-user-confirmation) Record the option to narrowly relax the archive `mv` deny to an `mv docs/tickets/**` allow to remove the tombstone workaround. This is approval-gated (relaxing a deny). Left as an open decision; NOT implemented — confirmed `mv`/`cp`/`rm` remain denied in the template's `bash` permission block (unchanged).

## Acceptance Criteria

- [ ] AC-01: `php tools/ai/validate-agent-spec.php` passes for the architecture-plan-writer template. NOT VERIFIED AS SPECIFIED: this tool takes a `spec.json` argument matching `schemas/ai/agent-spec.schema.json` (agent-creator pipeline output); there is no such spec file for this hand-authored template, so the command as written does not apply to a markdown agent file. Ran `php tools/ai/validate-agent-spec.php --self-test` instead (`self-test OK: detector passes a clean spec and rejects planted violations`) as the closest available proof the validator itself is sound; no spec.json exists to run it against for this agent.
- [x] AC-02: The added guard wording is concrete and reads: verify-landed -> stop after 3 -> no re-append after a blocked/failed edit. Verified via `rg -ni "stop after 3|blocked/failed edit|never re-append|bounded replacement"` matching in both the template and the skill (case-insensitive: the template's bullet starts a sentence, "Stop after 3 ...").
- [x] AC-03: The agent template stays within the 320-line hard max (ai-file-standards). 245 -> 256 lines (`wc -l`), well under 320.
- [x] AC-04: The repo-local skill wording matches the template guard and is flagged as a constraint-#1 exception. Added matching "Update/Expand Loop Guard" and "Archive Partial-State Handling" sections plus a "Repo-Local Exception Note" section to the SKILL.md.
- [ ] AC-05: `composer test:fast` is green. NOT GREEN as-is: 896 tests, 2 pre-existing failures (`CliToolsTest::testValidateGeneratedArtifactsExitsZero`, `GeneratedHeaderTest::testValidateGeneratedArtifactsPasses`) both caused by unrelated `docs/ai/repo-required-tools.md` drift from other in-flight/dirty work on this branch (untracked verify scripts). Confirmed pre-existing and unrelated to this slice by stashing only the two files this plan touched and re-running the same two failing tests in isolation — same 2 failures occurred with this plan's changes fully removed.
- [x] AC-06: P2-1 is recorded as needs-user-confirmation with NO code change made for it. Confirmed via `rg -n "mv|cp|rm" ...architecture-plan-writer.md` — the `bash` permission block's trailing `'*': deny` is unchanged; no `mv`/`cp`/`rm` allow rule was added.

## Verification Plan

- AC-01: `php tools/ai/validate-agent-spec.php`.
- AC-02 / AC-04: inspect the edited template and skill for the concrete guard wording.
- AC-03: line count check against the 320 hard max.
- AC-05: `composer test:fast`.
- AC-06: confirm no permission/deny change was applied for P2-1; run `php tools/ai/validate-adapter-drift.php` for drift on the touched surfaces.

## Risks And Rollback

- Risk: exceeding the line budget when adding guard text. Mitigation: keep the guard terse and reference the snippet rather than duplicating it fully.
- Risk: skill/template drift if only one side is updated. Mitigation: P1-1 aligns both; drift validators confirm.
- Rollback: revert the template and skill edits; existing guards remain functional (they were only added to, not replaced).
- Success signal: `validate-agent-spec.php` clean and the guard wording visibly present in both surfaces.

## Handoff Notes

- Recommended next step: hand off to the reviewer agent using OpenCode command: /review-diff (reviewer means reviewer agent handoff).
- P2-1 requires explicit user sign-off before any deny relaxation; keep it as an open decision.
- Implementation status (this pass): P0-1 and P1-1 done and verified per the checkboxes above, folding in the IMPROVEMENTS section's bounded-edit algorithm, attempt definition, and archive partial-state table. P2-1 confirmed as not implemented (needs-user-confirmation, unchanged).
- Deferred: the IMPROVEMENTS section's AC-11-style suggestion to also register the `.opencode/skills/architecture-plan-writer/SKILL.md` constraint-#1 exception in `docs/ai/adapter-contract.md`'s "Out-Of-Band Local Additions" section was deliberately NOT done. That section documents a different mechanism (a separately installed tool appending content to a rendered file outside the kit's render pipeline, e.g. graphify), not a first-party file that simply has no template source; forcing a fit risked conflating two distinct concepts the same doc explicitly warns against ("Two Different Drift Concepts"). AC-11's underlying need (a persistent, non-transient record of the exception) is instead satisfied directly inside the SKILL.md itself via a new "Repo-Local Exception Note" section, which also cross-references `docs/ai/adapter-contract.md`.
- Not done: the IMPROVEMENTS section's HTML/markdown section-anchor comment convention (`<!-- plan-writer:section:start -->`) was intentionally skipped — no such convention exists elsewhere in the template set, and the plan's own instruction preferred prose heading-bounded replacement in that case.

## IMPROVEMENTS:

## Verdict

**Plan is mostly safe, but under-specified around idempotency and drift.**

| Area                      |  Score |
| ------------------------- | -----: |
| Scope control             | 88/100 |
| Permission safety         | 90/100 |
| Loop prevention           | 75/100 |
| Archive behaviour clarity | 72/100 |
| Testability               | 70/100 |
| Overall                   | 80/100 |

Main issue: the plan says “add guard wording,” but guard wording alone may not prevent duplicate rewrites unless the template forces a **bounded edit algorithm**.

---

## Main criticism

### 1. “Stop after 3” is not enough

A numeric guard reduces infinite loops, but it does not prevent the first or second duplicate append.

The template should require this sequence:

```text
read existing file
detect current section/content
compare intended content
replace bounded section only
verify landed
stop after 3 failed attempts
never append full document after a failed/blocked edit
```

Add this invariant:

```md
The agent must never repair a failed Update/Expand by appending a second full copy of the plan. Recovery must be bounded replacement or stop.
```

---

### 2. Define what an “attempt” means

Without this, agents may count vaguely.

Better:

```md
One attempt = one write/edit operation against the same target plan file in the same run.
After 3 failed or non-landing attempts on the same target file, stop and report the exact blocker.
```

This prevents loopholes like “I retried with a different phrasing, so it is not the same attempt.”

---

### 3. Add section anchors for bounded updates

Update/Expand mode should not re-emit full markdown unless creating a new plan.

Recommended invariant:

```md
For Update/Expand on an existing plan, modify only the smallest relevant section. Prefer anchored section replacement over full-file rewrite.
```

Best pattern:

```md
<!-- plan-writer:section:start risks -->

...

<!-- plan-writer:section:end risks -->
```

If you do not want markers, use heading-bounded replacement:

```text
replace content between "## Risks And Rollback" and the next "## "
```

Score impact: **+12/100 loop safety**.

---

### 4. The repo-local skill exception is risky

Directly editing:

```text
.opencode/skills/architecture-plan-writer/SKILL.md
```

is acceptable only because the plan explicitly says it has no template source.

But this is a maintainability smell. The better long-term action is:

```text
Create a template/source for this skill, or record it in an explicit repo-local exceptions registry.
```

Otherwise, future maintainers may not know why this generated-looking path is hand-maintained.

Add AC:

```md
AC-07: The repo-local skill exception is documented in a persistent source-of-truth note, not only in this ticket.
```

---

### 5. Archive tombstone flow needs stronger idempotency rules

Current wording says write to archive + tombstone original, but does not define what happens on partial completion.

Add explicit states:

| State                                   | Required behaviour                     |
| --------------------------------------- | -------------------------------------- |
| Archive exists, original not tombstoned | Tombstone original only                |
| Archive missing, original active        | Write archive, then tombstone original |
| Archive exists, original tombstoned     | Stop; already archived                 |
| Archive missing, original tombstoned    | Stop and report inconsistent state     |

Add invariant:

```md
Archive mode must be resumable. It must detect partial archive state before writing and must not create duplicate archive copies.
```

---

## Better acceptance criteria

Add these:

| AC    | Requirement                                                                        |
| ----- | ---------------------------------------------------------------------------------- |
| AC-07 | Update/Expand wording requires bounded replacement, not full re-append             |
| AC-08 | “Attempt” is defined as one write/edit operation against the same target file      |
| AC-09 | Archive flow defines partial-state recovery                                        |
| AC-10 | No duplicate ticket plan can be created when a matching plan folder already exists |
| AC-11 | Skill exception is documented outside this transient plan                          |
| AC-12 | Verification includes a grep/assertion that `mv`, `cp`, and `rm` remain denied     |

---

## Better verification commands

Add concrete checks:

```bash
php tools/ai/validate-agent-spec.php
php tools/ai/validate-adapter-drift.php
composer test:fast
wc -l packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md
rg -n "stop after 3|blocked/failed edit|never re-append|bounded replacement" \
  packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md \
  .opencode/skills/architecture-plan-writer/SKILL.md
rg -n "mv|cp|rm|docs/tickets" packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md
```

Also add a rendered-output check if the template feeds generated agents:

```bash
rg -n "stop after 3|never re-append" .opencode/agents .github/agents .claude 2>/dev/null
```

Only inspect generated output; do not hand-edit it.

---

## Suggested implementation wording

Use this exact guard style in both the template and repo-local skill:

```md
Update/Expand loop guard:

- Before editing an existing plan, read the current file and identify the smallest heading-bounded section to change.
- Prefer bounded section replacement; do not re-emit or append the full plan unless creating a new plan file.
- After each edit, verify the intended change landed.
- One attempt means one write/edit operation against the same target file in the same run.
- After 3 failed, blocked, or non-landing attempts, stop and report the blocker.
- Never recover from a blocked/failed edit by appending a second full copy of the plan.
```

Archive wording:

```md
Archive mode:

- Do not use `mv`, `cp`, or `rm`.
- Archive by writing the archived copy under `docs/tickets/**/archive/`, then tombstone the original.
- Before writing, check whether the archive copy or tombstone already exists.
- If the archive copy exists and the original is already tombstoned, stop as already archived.
- If the archive copy exists but the original is not tombstoned, only tombstone the original.
- If the original is tombstoned but the archive copy is missing, stop and report inconsistent archive state.
```

---

## Position on `mv` relaxation

Do **not** implement `mv docs/tickets/**` in this slice.

Current risk trade-off:

| Option                            | Safety | Simplicity | Recommendation          |
| --------------------------------- | -----: | ---------: | ----------------------- |
| Keep write+tombstone              | 85/100 |     65/100 | Use now                 |
| Allow narrow `mv docs/tickets/**` | 70/100 |     90/100 | Separate approval slice |
| Broad `mv` allow                  | 20/100 |     95/100 | Reject                  |

The tombstone flow is awkward but safer. Relaxing a deny belongs in a separate permission-focused slice with tests.

---

## Revised action list

1. Keep the plan scope, but strengthen P0-1 from “add wording” to “add bounded-edit algorithm.”
2. Define attempt counting.
3. Add archive partial-state handling.
4. Add ACs for no duplicate full-plan append and no duplicate archive copy.
5. Document the repo-local skill exception in a persistent source-of-truth location.
6. Keep `mv` relaxation as **needs-user-confirmation**, no implementation.
7. Add grep-based verification for guard wording and permission non-widening.

Final posture: **Proceed after tightening ACs and guard wording.**
