# arch-todo — README content + docs improvements

Durable plan (committed location, NOT gitignored). This file demonstrates the universal
plan-storage fix: AI-generated durable plans live under `docs/tickets/`, never under the
gitignored `docs/ai/generated/`.

## Goal

Make README + supporting docs explain what the kit ships with, why, and how it helps — covering
agents, mentor mode, capabilities, gotchas, the AI builder (agent-creator), safety/scope discipline,
reinstall/update/backup, runtime selection, install-option semantics, post-install validation, and
the project-context / project-interaction files. Keep README beginner-first and under 150 lines by
linking to deeper docs rather than embedding. Integrate the QoL items from `readme-todo.md` while
following the existing README architecture (short front door + linked guides). Fix the gitignored
plan-path failure.

## Source of evidence

- Research report A (README content gaps): 12 topics, all evidenced with file:line.
- Research report B (gitignore plan-path): durable plans → `docs/tickets/`.
- `readme-todo.md` (existing 383-line production-grade plan, P0–P11).
- Current `README.md` = 98 lines; all README links exist EXCEPT `LICENSE` (missing).

## CRITICAL correctness fixes (must apply; user framing was inaccurate)

- [x] **Mentor mode is L0–L5 (six rungs), NOT L1–L4.** Source: `docs/ai/capabilities/mentor-mode/CAPABILITY.md:38-51`.
  Rungs: L0 Frame, L1 Probe, L2 Hint, L3 Scaffold, L4 Worked-adjacent, L5 Direct solution. Four modes:
  learn/pair/deliver/lookup (`:53-61`). README/overview MUST describe L0–L5 and why each layer exists
  (knowledge retention via struggle-gate + teach-it-back, `:74-80`). Do NOT write "L1–L4".
  - AC: any mentor-mode explanation lists all six rungs L0–L5 with a one-line purpose each.
  - AC: explains the struggle gate and teach-it-back retention mechanism.

- [x] **There is NO `--runtime claude`.** Source: `tools/ai/install/config.php:252` (`github-copilot|opencode|both`).
  Claude is served via the base layer (`AGENTS.md` + `CLAUDE.md`). "Claude-only" = base/minimal install.
  - AC: docs never imply a `--runtime claude`; Claude described as base-layer support.

- [x] **Safety is not a guarantee.** Use exact phrase the user requires: the kit helps you
  "**maintain sanity**" — it gives ideas, scaffolds, prepares, validates, and does routine work; it
  does NOT build and ship software for you. Ship this as a warning. (Grammar-correct only; phrase
  "maintain sanity" must appear verbatim.)
  - AC: README/overview contains a clear "this is not a universal tool that builds and ships software
    for you — it helps you maintain sanity" style warning, with the literal phrase "maintain sanity".

- [x] **No numeric "instruction-specificity score" exists** — it is a context gate
  (`.github/instructions/context-gate.instructions.md`). Describe as a gate, not a score.
  - AC: docs describe scope discipline as a context gate, not a numeric score.

---

## P0 — Ship-blockers (from readme-todo.md, evidence-confirmed)

- [x] Add a real `LICENSE` file (currently MISSING). README "License" section must link `LICENSE`,
  not present a guardrail/policy doc as the license.
  - AC: `LICENSE` exists; README links it; no policy doc presented as a license.
- [ ] Verify all README links resolve (all currently exist except LICENSE). Keep doc-check green.
  - AC: `bash scripts/ai/ai-doc-check.sh --check` passes.
  - NOT fully checked: `lychee --offline` (the actual README-links check) passes with 0 errors, but
    the overall script exits FAIL on `validate-context-budgets` — `.opencode/skills/graphify/SKILL.md`
    = 669 lines > hard max 350. This is a pre-existing, unrelated, third-party out-of-band addition
    (see `docs/ai/adapter-contract.md`), not caused by this ticket's edits, and out of scope to fix here.
- [x] Add `SECURITY.md` and `SUPPORT.md` (report path, supported vs unsupported, safety-not-guaranteed).
  - AC: per readme-todo.md P0 ACs. Verified: both files exist with a reporting path, a supported vs
    not-supported list, and "reduce risk, not a guarantee" / "maintain sanity" language.

## P1 — Fix the gitignored plan-path failure (UNIVERSAL approach)

- [x] Standardize durable AI plans/task-context to `docs/tickets/` (committed, not gitignored). The
  `architecture-plan-writer` agent/skill already does this (`docs/tickets/arch-todo-{slug}-{ts}/plan.md`);
  the general `architecture-plan` skill names no path — add the `docs/tickets/` target there.
  Reserve `docs/ai/generated/task-context/` for ephemeral, machine-regenerated context only.
  - Files to update (docs-sync slice, evidence from report B):
    - `.opencode/skills/architecture-plan/SKILL.md` (Output section: add `docs/tickets/` write target)
    - `AGENTS.md:31` + `packages/ai-universal-rules/templates/core/AGENTS.template.md:31`
    - `docs/ai/project-context.md:39-46` + template `...core/project-context.template.md:39-46`
    - `.github/instructions/context-gate.instructions.md:10` + template; `architecture.instructions.md:14` + template
    - `docs/ai/generated-artifacts.md` (note: durable plans → docs/tickets/; this dir ephemeral only)
      — DONE this session: added a 3-line clarifying note after the intro.
    - `docs/tickets/README.md` (extend convention to cover `arch-todo-*` plan folders)
  - AC: every doc that names the task-context path clarifies durable vs ephemeral and points durable
    plans at `docs/tickets/`. Verified: all 6 files above now state this (spot-checked with `rg`).
  - AC: linking to a plan under `docs/tickets/` passes `ai-doc-check.sh` (lychee --offline); no
    committed doc links into `docs/ai/generated/`. Verified: `lychee --offline` = 0 errors;
    `rg -n "docs/ai/generated/task-context" README.md` = 0 hits.

## P2 — README "what it ships with + why + how it helps" (beginner-first, linked)

Keep README < 150 lines; add concise sections that LINK to deep docs (do not embed long content).

- [x] Add "At a glance" table (readme-todo.md P1) — purpose, supported tools, install target, edits
  code by itself = No, main command. 10-second comprehension.
- [x] Add "What gets installed and why" — short bullets mapping each surface to the value it adds,
  linking deeper docs. Cover:
  - [x] **Agents + recommended chaining order** → link `docs/ai/agents.md` (roster/purpose) +
    `docs/ai/workflow.md` (research → plan → implement → review → release). State agents are chained
    for best results and reference the doc that shows their purpose and execution order.
  - [x] **Capabilities** (16 folders) → link `docs/ai/capabilities/README.md`; explain they are
    load-on-demand reusable workflows.
  - [x] **Gotchas** (per-capability recurring-trap docs) → link `docs/ai/ai-file-standards.md` +
    example; explain why they exist (capture failure modes near the workflow).
  - [x] **Mentor mode (L0–L5)** → link `docs/ai/capabilities/mentor-mode/CAPABILITY.md`; explain each
    layer and why it exists for knowledge retention.
  - [x] **AI builder / agent-creator + everyday usage** → link `docs/ai/agents.md`; state the
    supervisor → creator → validators pipeline and architecture-plan-writer, and that these are
    **OPT-IN optional packs** (`optional-agents-opencode-pack`, `optional-agents-copilot-pack`,
    removable via `--without ...`). Custom agent/rule creation only available if you opt in.
- [x] Add "What it is NOT" warning (mandatory phrasing): not a universal tool that builds/ships
  software for you — it helps you **maintain sanity** (ideas, scaffold, prepare, validate, routine
  work; you stay in control).
  - AC: literal phrase "maintain sanity" present; no overpromise of autonomy or guaranteed safety.

## P3 — Safety, scope discipline, and the scripts that enforce it

- [ ] Add a short "Safety & scope" section (link `.github/instructions/context-gate.instructions.md`,
  `docs/ai/execution-protocol.md`, `docs/ai/tools/tool-map.md`). State truthfully (evidence-backed):
  NOT fully checked — README's "Safety and Scope" section exists and links all three docs, and
  states 3 of the 4 required claims below, but does not yet mention the "Blocked by unknown" format.
  Leaving this parent box unchecked to reflect that gap honestly; out of scope for this docs-only
  slice (only the 2 assigned fixes were made).
  - [x] Agents stay read-only until scope/ownership is clear; ask ONE clarifying question; never
    implement from memory; never proceed past unclear/missing scope.
  - [x] Agents ask for / build acceptance criteria (ACs) and ACs must be observable and testable
    (`architecture-plan-writer.md:98`); proceed only with high confidence, no guessing.
  - [x] Scope is enforced by tested scripts: `scripts/ai/pre-tool-use.sh` (policy gate, allow/ask/deny,
    blocks destructive commands) + `scripts/ai/post-tool-use.sh` (evidence writer), plus per-agent
    `permission.bash` allow/ask/deny and `docs/ai/agent-script-access.md`.
  - [ ] "Blocked by unknown" response format (`docs/ai/project-context.md` §10). NOT present in
    README's Safety and Scope section — a real, small remaining gap, not part of this slice's 2
    assigned fixes.
  - AC: all claims map to a real file; no safety guarantee claimed (use "rules, checks, safer defaults").

## P4 — Install lifecycle: reinstall / update / backup / runtime selection / options

- [x] In README keep ONE primary command; move detail to `readme-install.md`. Ensure
  `readme-install.md` covers (readme-todo.md P2 + report A findings 8–11):
  - [x] **Reinstall / update** — `--force` (overwrite managed files), `--reinstall`,
    `--allow-core-overwrite`, `--adopt` (overwrite foreign files at kit paths, records backup).
  - [x] **Backup behaviour** — backups to `.ai/backups/<TIMESTAMP>-install/` (keeps last 5);
    audit logs to `.ai/logs/install-transactions-<DATE>.jsonl`. Explain what is/ISN'T restored.
  - [x] **Rollback** — `php tools/ai/ai.php rollback --backup <id> --apply` (`docs/ai/POST-INSTALL.md`).
  - [x] **Runtime selection** — `--runtime github-copilot|opencode|both`; profiles
    `copilot|opencode|dual|full-governance`. Provide exact commands for:
    Copilot-only, OpenCode-only, and Claude-via-base (no `--runtime claude`).
  - [x] **Beginner option table** — plain-English `--force`, `--backup`, `--adopt`, `--verify-after`,
    `--non-interactive`, `--allow-placeholders`, `--dry-run`, `--allow-core-overwrite`. Explain what
    happens WITH vs WITHOUT `--force` so newcomers understand.
  - [x] **"I want to..." command decision table** (install/reinstall/validate/test/generate docs/pack
    context) — lives in `readme-install.md`, not README.
  - AC: a newcomer can pick the right command and understand `--force` consequences in < 15s.

## P5 — Post-install validation + MUST-HAVE actions

- [x] Document mandatory post-install steps (report A finding 11):
  - [x] After install you MUST validate the project: `php tools/ai/ai.php verify`
    (and `verify --strict`, `placeholders --fail`, `full-install-validation.php`).
  - [x] **Replace/refresh required files via the post-install agent/command** (`post-install-setup`;
    agents `.opencode/agents/post-install.md`, `.github/agents/post-install.agent.md`) to update all
    required files after install.
  - [x] **Build the project-context file yourself or via an agent** (`docs/ai/project-context.md`;
    `project-context` capability + `researcher` agent).
  - [x] **Project-interaction lives in the shared location for cross-project logic**:
    `docs/ai/shared/project-interaction.md` (cross-project) and `docs/ai/project/project-interaction.md`
    (per-project defaults). All cross-project logic goes strictly in the shared file unless you build
    custom agents/instructions via the agent-creator (opt-in optional agents).
  - AC: a "MUST HAVE after install" checklist exists with copy-paste commands and the two
    project-interaction paths named correctly.

## P6 — Integrate remaining readme-todo.md QoL (following existing README architecture)

Adopt, deferring large new docs as a tracked backlog (do not block README on all of them):
- [x] Now: "At a glance", "first successful install" result, "safe default" statement (P1);
  command decision table + rollback + uninstall notes in install guide (P2). Verified: At-a-glance
  table in README; command decision table (`readme-install.md` "I Want To..." section), Rollback
  section, and Uninstall / Safe Removal section all present in `readme-install.md`.
- [ ] Backlog (tracked, lower priority): `docs/ai/compatibility.md` (P3), `docs/ai/security-model.md`
  + `docs/ai/threat-model.md` (P4), `docs/ai/troubleshooting.md` (P5), `template-authoring.md` +
  `adapter-authoring.md` (P6), `CHANGELOG.md` + `CONTRIBUTING.md` + `CODE_OF_CONDUCT.md` (P7),
  markdownlint + link-check + README length gate in CI (P8), `agent-permissions.md` +
  `agent-operating-model.md` + `context-policy.md` (P9), glossary (P10).
  - AC: backlog items captured here so nothing is lost; README links only to docs that already exist.
  - DEFERRED (explicitly, per this plan's own framing): out of scope for this docs-only slice; not
    implemented and intentionally left unchecked. README does not link to any of these not-yet-built
    docs, so the "links only to existing docs" half of the AC still holds.

## README acceptance checklist (readme-todo.md P11)

- [x] First 5 lines explain the project; first 20 explain why.
- [x] Exactly one primary install command; source repo vs target repo explained.
- [x] Explains what gets installed and what it is NOT; no advanced flags / no giant generated tree.
- [x] Does not overpromise security; links only to existing files (incl. real `LICENSE`).
- [x] Understandable by a non-technical reader and still useful to a senior engineer.
- [x] README < 150 lines. Verified: `wc -l README.md` = 149 (after this session's trim from 155).

## Status (implementer session)

A prior reviewer pass confirmed nearly everything in this plan was already done. This session found
and fixed the 2 remaining real gaps:

1. `docs/ai/generated-artifacts.md` now states durable plans live in `docs/tickets/` (this dir is
   ephemeral-only).
2. `README.md` trimmed from 155 → 149 lines (tightened wording only; no required section removed).

**Not archived.** Two items remain honestly unchecked and this plan stays at `plan.md` (not moved to
`archive/DONE-plan.md`):

- P3's "Blocked by unknown" response-format mention is still missing from README's Safety and Scope
  section — a real, small gap, but outside this slice's assigned 2 fixes.
- P0's `bash scripts/ai/ai-doc-check.sh --check` does not fully pass: the link-check
  (`lychee --offline`, the actual concern for README links) is clean (0 errors), but
  `validate-context-budgets` fails on `.opencode/skills/graphify/SKILL.md` (669 lines > hard max
  350) — a pre-existing, unrelated, third-party out-of-band addition (see
  `docs/ai/adapter-contract.md`), not caused by this ticket's edits.

Everything else in P0–P6 and the README acceptance checklist is now checked with verified evidence
(see inline notes on each item).

## Verification (run after implementation)

```
wc -l README.md
rg -n "AI workflow" README.md
rg -n "configuration" README.md
rg -n "maintain sanity" README.md docs/ai/non-technical-overview.md
rg -n "L0|L1|L2|L3|L4|L5" docs/ai/non-technical-overview.md README.md   # mentor mode six rungs
rg -n "runtime claude" README.md readme-install.md docs/ai   # expect 0 hits
rg -n "docs/ai/generated/task-context" README.md             # expect 0 hits
php tools/ai/validate-ai-config.php
bash scripts/ai/ai-doc-check.sh --check
ls LICENSE
```

Pass: README < 150 lines; substrings present; mentor mode shows L0–L5; no `runtime claude`; no README
links into gitignored generated paths; validator + doc-check green; LICENSE exists.

## Non-goals

- Do not embed long command blocks in README (link to readme-install.md).
- Do not absorb the unrelated dirty worktree changes (sh-introspect refactor, agent files, scripts).
- Do not claim guaranteed safety or autonomous software delivery.
- Do not write durable plans into `docs/ai/generated/` (gitignored).

## Recommended execution chain

researcher (done) → docs-sync/implementer for P1+P3+P4+P5 doc edits → implementer for README (P2/P6)
and LICENSE/SECURITY/SUPPORT (P0) → reviewer (/review-diff) → verify with the commands above.
