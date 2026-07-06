# Architecture Plan: Ask-First Interaction Mode, Access Policy Clarification, and "How To Ask Agents" Guide

- Ticket/slug: `arch-todo-ask-mode-and-access-policy-20260706-224717`
- Risk: medium (multi-surface policy + adapter change across Copilot/OpenCode/Claude; no runtime code path, no schema, no secrets touched)
- Owner surface: `packages/ai-universal-rules/templates/**` (canonical) + 1:1 rendered copies under `.github/**`, `docs/ai/**`, plus regenerated `AGENTS.md` / `CLAUDE.md` / `.github/copilot-instructions.md`

## Problem / Motivation

Analysis of real Copilot agent-mode sessions (headless-cms multi-repo work) surfaced:

1. The agent **read `.env`** to find an endpoint; the security rule only forbids *editing*
   `.env*`, not *reading* it. The user challenged it. Policy gap.
2. Users want to keep agents able to **request access outside the repo, use webfetch, open
   and read/debug browser pages** on request — so the fix for (1) must NOT hard-block those.
3. For **non-security global env values**, agents should be allowed to discover values with
   programming-language tooling; for **security values**, agents must always ask the user to
   provide them directly.
4. Terse imperatives ("implement now", "is it correct?", bundled multi-question prompts) led
   to premature action. Users want agents to **generate a set of clarifying questions with
   selectable options** and let the user choose before proceeding.
5. **Todo-list creation** inside each harness should be auto-approved (it is safe, read-only
   planning state) so the ask-first flow does not add friction to planning.
6. There is no **root-level guide** telling users how to ask agents: what to specify, why,
   what to avoid, and how to raise agent consistency.

## Scope (in)

- Security/env policy wording (canonical + rendered instruction file, plus AGENTS/CLAUDE/Copilot summaries).
- External-access + webfetch + browser read/debug: document as explicitly allowed-on-request (preserve, do not tighten).
- `clarification-and-handoff` capability: add a **structured question-set-with-options** interaction mode; reconcile with the existing "at most one question" rule.
- Agent references (Copilot/OpenCode/Claude) that already cite the clarification capability: point them at the new question-set mode; note Claude's non-interactive fallback.
- Harness config: auto-approve todo-list creation (OpenCode `todowrite`, Copilot/Claude equivalent) where a permission surface exists.
- New root doc `HOW-TO-ASK-AGENTS.md` (or `docs/ai/how-to-ask-agents.md` linked from root) covering specify / why / avoid / consistency.

## Scope (out — do NOT touch)

- The pre-existing uncommitted `list-todos.sh` work (`.github/ai-script-access.yaml`,
  `scripts/ai/MANIFEST.md`, `scripts/ai/list-todos.sh`, `scripts/ai/bin/verify/list-todos.sh`).
  Unrelated in-progress user work; leave staged/untouched and do not include in this commit.
- Any runtime PHP behavior, schema, installer executor logic.
- Any secret file. No reading `.env` to "test" the new rule.

## Slices (bounded, sequential, human-testable)

### Slice 1 — Env / secrets read+edit policy
- Edit `templates/instructions/security.instructions.md` + rendered `.github/instructions/security.instructions.md`:
  - Forbid **reading, opening, editing, or transforming** `.env*`, keys, certs, tokens, credential files without approval.
  - Add the split rule: non-security global env/config values MAY be discovered via
    language/tooling (runtime config, documented defaults); security values MUST be requested
    from the user directly, never harvested from `.env`.
- Mirror one line into `approval-boundaries` instruction/doc if needed.
- Acceptance: security file states read-not-just-edit; env split rule present; no external/webfetch capability removed.

### Slice 2 — Preserve + document external / webfetch / browser access
- Confirm `external_directory: ask`, `webfetch: ask`, `websearch: ask` remain (do NOT flip to deny).
- Add explicit wording (external-project policy section of AGENTS template + project-interaction doc)
  that agents MAY request outside-repo access, fetch web pages, and open/read/debug browser pages
  when the task requests it, subject to the existing `ask` prompt.
- Acceptance: no permission downgraded; wording says access is allowed-on-request, not blocked.

### Slice 3 — Clarification capability: question-set-with-options mode
- Edit `templates/capabilities/clarification-and-handoff/CAPABILITY.md` + rendered
  `docs/ai/capabilities/clarification-and-handoff/CAPABILITY.md`:
  - Add "Structured Question Set" section: when a request is ambiguous or bundles multiple
    decisions, generate a small set of questions, each with 2-4 concrete selectable options
    (plus recommended option), and wait for selection before editing.
  - Reconcile with existing "at most one question per pause": the single-question limit applies
    to blocking free-text asks; a bounded option-set is the preferred structured form and may
    carry the few tightly-related decisions for one pause.
  - Keep the Claude non-interactive fallback (state assumption, mark `unknown`, stop if high-impact).
- Acceptance: capability describes option-set interaction; single-question rule reconciled, not contradicted.

### Slice 4 — Wire question-generation into agents (all 3 harnesses)
- Update the agents already citing the capability (reviewer, config-maintainer, repository-reviewer,
  architect, implementer, etc.) to reference the new structured-question mode, in templates +
  rendered `.github/agents/**`, `.opencode/**`, `.claude/**` copies.
- Add a short line to AGENTS/Copilot/CLAUDE summaries: prefer structured question sets with options
  before acting on ambiguous or terse imperatives.
- Acceptance: at least the summary surfaces + capability reference the option-set behavior; Claude fallback noted.

### Slice 5 — Auto-approve todo-list creation per harness
- OpenCode `opencode.jsonc` + `templates/core/opencode.json`: add `todowrite`/todo permission = `allow`.
- Copilot/Claude: document that todo/plan list creation is pre-approved (harness-level), no gating prompt.
- Acceptance: todo creation is `allow` where a permission key exists; no other permission widened.

### Slice 6 — Root "How To Ask Agents" guide
- New `docs/ai/how-to-ask-agents.md` linked from `README.md` (root-visible), covering:
  specify (goal, done-signal, target repo, read-only vs change), why each matters, what to avoid
  (bundled asks, vague success), and how to raise consistency (question-set mode, one-goal-per-turn).
- Respect `docs/ai/ai-file-standards.md` line budgets.
- Acceptance: doc exists, root-reachable, covers all four asks.

### Slice 7 — Review, fix, verify, commit
- `git diff` review; run `php tools/ai/ai.php install-docs --check`, `validate-ai-config.php`,
  `validate-adapter-drift.php`, doc-check, markdownlint on changed files.
- Re-render generated AGENTS/CLAUDE/Copilot ONLY via installer if a template summary changed (ask-gated).
- Commit with a scoped message; exclude the out-of-scope `list-todos.sh` files.

## Things To Avoid

- Do not flip `webfetch`/`websearch`/`external_directory` from `ask` to `deny`.
- Do not delete or rewrite whole files; in-place edits only.
- Do not touch the pre-existing `list-todos.sh` change set.
- Do not read `.env` or any secret to "verify" anything.
- Do not let template and rendered 1:1 copies drift — edit both.
- Keep each doc within `docs/ai/ai-file-standards.md` budgets.

## Verification

- `php tools/ai/ai.php install-docs --check`
- `php tools/ai/validate-ai-config.php`
- `php tools/ai/validate-adapter-drift.php --fail-on-warn`
- `bash scripts/ai/ai-doc-check.sh --check`
- `markdownlint-cli2` on changed markdown
- `composer test` (smoke) if any PHP is touched (not expected)

## Acceptance Criteria (whole ticket)

1. Security policy forbids reading (not just editing) secret files; env split rule present.
2. External/webfetch/browser access preserved and documented as allowed-on-request.
3. Clarification capability offers structured question-set-with-options mode.
4. Agents across all three harnesses reference the question-set behavior; Claude fallback intact.
5. Todo-list creation auto-approved where a permission surface exists.
6. Root-reachable "how to ask agents" guide exists.
7. Template ↔ rendered parity holds; validators pass; out-of-scope files untouched.
