# Architecture Plan — Agent Improvements: Close Genuine Handoff/Enforcement Gaps From Audit

- Ticket: none
- Source: architect handoff (Plan Writer Handoff Envelope v1, Scope ID `agent-improvements-audit`)
- Generated: 2026-07-09T21:18:25Z
- Plan file: docs/tickets/agent-improvements-audit/plan-1-agent-improvements.md

> **Completion instruction:** When every `## Todo Plan` item and every `## Acceptance Criteria` item below is checked `[x]`, rename this file to `DONE-plan-1-agent-improvements.md` and move it into `archive/` under this branch folder (`docs/tickets/agent-improvements-audit/archive/DONE-plan-1-agent-improvements.md`). See "Archive On Completion" in the architecture-plan-writer contract for the exact steps.

## Context

The idea file `docs/tickets/IDEAS/improvements-agents.md` audited the architect and architecture-plan-writer agents and scored the chain 84/100. On verification the audit was found to describe the CLAUDE-RENDERED adapters (`.claude/agents/architect.md:4-7` uses `tools: Read, Grep, Glob, Bash, Agent` / `disallowedTools: Write, Edit` / `permissionMode: plan`), not the OpenCode source templates. The OpenCode templates already enforce most P0/P1 items via `permission:` maps. Only a few gaps remain genuinely open.

This plan covers ONLY those genuinely-open gaps. It explicitly excludes every audit item already satisfied by the OpenCode `permission:` templates (see `## Out Of Scope`).

## Problem

The audit's remaining, verified-open gaps are:

1. The live `.claude/settings.json` is stale relative to its template — it lacks the shell-write denies the template already carries, so on the Claude runtime the architect's `permissionMode: plan` blocks native Edit but does NOT hard-deny Bash shell-write.
2. The architect handoff to the plan writer is prose-only, not a strict machine-readable envelope.
3. The plan writer lacks an explicit token-discipline rule for handoff-driven invocations, risking redundant architecture discovery.
4. Plan-file acceptance criteria lack a Type/Proves/Verification structure.
5. The architect has no numeric cap on canonical-reference loading.
6. Neither template nor live settings denies a bare `tee file` write.

## Target Outcome

- Live `.claude/settings.json` re-rendered from its template so the shell-write denies are present.
- Architect template emits a strict, machine-readable Plan Writer Handoff Envelope.
- Plan-writer template documents a Handoff-First Token Discipline section.
- Plan-file AC format carries Type/Proves/Verification sub-structure.
- Architect Canonical References states a numeric reference-load cap.
- Claude settings template denies bare `tee *` writes.
- No already-satisfied item is duplicated or regressed.

## In Scope

- P0-01: Re-render/install the live `.claude/settings.json` from `packages/ai-universal-rules/templates/claude/settings.json` (via the installer, NOT by hand-editing the generated live file) so its deny list contains `Bash(* > *)`, `Bash(* >> *)`, `Bash(cat > *)`, `Bash(* <<*)`. Verify the template already contains these denies (`:181-190`) before re-render.
- P1-01: Add a strict machine-readable "Plan Writer Handoff Envelope" block to the architect template Final Output (currently prose bullets at `architect.md:237-244`). Envelope fields: handoff version, source/target agent, scope ID, ticket, branch hint, title, write target, scope status, in/out scope, affected paths, source-of-truth files, contracts, ordered steps, ACs with type tag, verification mapping, risks, rollback, scope-lock sentence, next required agent.
- P1-02: Add a "Handoff-First Token Discipline" section to the architecture-plan-writer template: when invoked with an architect handoff, do NOT repeat architecture discovery; limit inspection to git status, current branch, existing plan files in the target folder, the existing plan file in Update Mode, and only specific referenced docs when a required handoff field is missing.
- P2-01: Strengthen the AC format in the plan-file template (`architecture-plan-writer.md:214-223`) to map each AC to what it proves and its verification (`- [ ] AC-01: ...` with `Type:`, `Proves:`, `Verification:` sub-lines).
- P2-02: Add a numeric canonical-reference-load cap to the architect template Canonical References (`architect.md:168-170`), e.g. "load only files directly connected to affected paths/contracts; max 3 initial reference files unless evidence requires more."
- P2-03: Add a `Bash(tee *)` deny to `packages/ai-universal-rules/templates/claude/settings.json` (neither template nor live settings currently denies a bare `tee file`, only redirections).

## Out Of Scope (Things To Avoid)

Do NOT add, duplicate, or re-implement any of the following — they are already satisfied:

- Architect edit hard-deny: already `edit: deny` at `packages/ai-universal-rules/templates/core/agents/architect.md:18`; bash is `'*': deny` + read-only allowlist (`:21-95`). No shell write path (tee/cat>/sed -i/cp/mv/rm/php -r/python) is reachable in the OpenCode template.
- Writer write-scope: already `edit: '*': deny` + `docs/tickets/**: allow` at `architecture-plan-writer.md:13-22`.
- Architect plan-writer-first routing: already at `architect.md:233-235` and `:280`.
- Writer intake stop-conditions: already at `architecture-plan-writer.md:255-257` (Stop Conditions) + `:109` (Incoming Handoff Contract).
- Archive `Archived:` tombstone marker: already at `architecture-plan-writer.md:243`.
- `date` narrowing: both `date *` (`:32`) and bare `date` (`:52`) already present.

Also out of scope: any change to the architect `edit: deny` frontmatter or the writer `docs/tickets/**` scope; any hand-edit of generated outputs; any widening beyond the six in-scope items above.

## Affected Paths

- `packages/ai-universal-rules/templates/core/agents/architect.md` (P1-01 envelope, P2-02 reference cap)
- `packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md` (P1-02 token discipline, P2-01 AC mapping)
- `packages/ai-universal-rules/templates/claude/settings.json` (P2-03 tee deny; already has P0-01 denies)
- Installer re-render/install step that refreshes live `.claude/settings.json` and rendered `.claude/agents/*`, `.opencode/agents/*` (P0-01)

## Contracts And Boundaries

- Source-of-truth = `packages/.../templates/**`. NEVER hand-edit generated outputs: `.claude/agents/*.md` (carry GENERATED marker), `.claude/settings.json`, `.opencode/agents/*.md` — they are re-rendered by the installer.
- Editing `packages/**` is deny-listed for most agents and needs explicit user approval (AGENTS.md approval boundaries).
- Adapter re-render uses `merge_strategy: replace` and will overwrite the out-of-band `## graphify` section in `AGENTS.md`/`CLAUDE.md` — must be re-applied after (`docs/ai/adapter-contract.md`).

## Architecture Diagram

No diagram — single-surface documentation/template edits with no multi-module data-flow hop (per architect handoff: "Diagram: not required").

## Todo Plan

- [ ] P0-01: Verify `packages/ai-universal-rules/templates/claude/settings.json:181-190` already contains `Bash(* > *)`, `Bash(* >> *)`, `Bash(cat > *)`, `Bash(* <<*)`; then re-render/install the live `.claude/settings.json` from the template via the installer (do NOT hand-edit the generated live file). Re-apply the out-of-band `## graphify` section in `AGENTS.md`/`CLAUDE.md` if the re-render overwrote it.
- [ ] P1-01: Add a strict machine-readable "Plan Writer Handoff Envelope" block to the architect template Final Output (`architect.md:237-244`) with all required envelope fields (handoff version, source/target agent, scope ID, ticket, branch hint, title, write target, scope status, in/out scope, affected paths, source-of-truth files, contracts, ordered steps, ACs with type tag, verification mapping, risks, rollback, scope-lock sentence, next required agent).
- [ ] P1-02: Add a "Handoff-First Token Discipline" section to the architecture-plan-writer template limiting inspection on handoff-driven invocations to git status, current branch, existing plan files in the target folder, the Update-Mode existing plan file, and only specific referenced docs when a required handoff field is missing.
- [ ] P2-01: Update the Required Plan File Format ACs in `architecture-plan-writer.md:214-223` so each AC carries `Type:`, `Proves:`, and `Verification:` sub-lines.
- [ ] P2-02: Add a numeric canonical-reference-load cap to the architect template Canonical References (`architect.md:168-170`), e.g. "max 3 initial reference files unless evidence requires more."
- [ ] P2-03: Add a `Bash(tee *)` deny to `packages/ai-universal-rules/templates/claude/settings.json`.
- [ ] P2-04: After template edits, keep both agent template files within `docs/ai/ai-file-standards.md` line budgets (agent hard-max 320).

## Acceptance Criteria

- [ ] AC-01: Live `.claude/settings.json` deny list contains `Bash(* > *)`, `Bash(* >> *)`, `Bash(cat > *)`, `Bash(* <<*)`.
  - Type: enforcement/config
  - Proves: shell-write is hard-denied on the Claude runtime, not only blocked by `permissionMode: plan`.
  - Verification: `rg -n 'cat > \*|\* > \*|\* <<' .claude/settings.json` returns hits for all four patterns.
- [ ] AC-02: The architect template Final Output contains a "Plan Writer Handoff Envelope" block with all required fields.
  - Type: doc/contract
  - Proves: architect emits a strict machine-readable handoff, not prose-only bullets.
  - Verification: `rg -n "Plan Writer Handoff Envelope" packages/ai-universal-rules/templates/core/agents/architect.md` returns a hit; manual read confirms all required fields present.
- [ ] AC-03: The architecture-plan-writer template contains a "Handoff-First Token Discipline" section.
  - Type: doc/contract
  - Proves: the writer has an explicit rule to avoid redundant architecture discovery on handoff-driven runs.
  - Verification: `rg -n "Handoff-First Token Discipline" packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md` returns a hit.
- [ ] AC-04: The plan-file Required Plan File Format ACs include Type/Proves/Verification sub-structure.
  - Type: doc/format
  - Proves: future plan files map each AC to what it proves and how it is verified.
  - Verification: read the "Required Plan File Format" `## Acceptance Criteria` block in `architecture-plan-writer.md` and confirm `Type:`/`Proves:`/`Verification:` sub-lines.
- [ ] AC-05: The architect Canonical References section states a numeric reference-load cap.
  - Type: doc/policy
  - Proves: the architect has a bounded reference-load budget.
  - Verification: `rg -n "max" packages/ai-universal-rules/templates/core/agents/architect.md` shows the cap adjacent to Canonical References.
- [ ] AC-06 (negative): No already-satisfied item is duplicated; architect `edit: deny` and writer `docs/tickets/**` scope remain unchanged.
  - Type: regression/negative
  - Proves: the plan did not widen scope or regress existing enforcement.
  - Verification: `git diff` shows no change to the architect `edit: deny` frontmatter line or the writer `edit: '*': deny` / `docs/tickets/**: allow` frontmatter lines.
- [ ] AC-07: Validators pass and agent files stay within line budgets.
  - Type: verification/gate
  - Proves: adapter drift and permission parity remain green and files respect the size contract.
  - Verification: `php tools/ai/validate-adapter-drift.php` and `php tools/ai/generate-agent-permissions.php --check` pass; both agent template files are within `docs/ai/ai-file-standards.md` agent hard-max (320 lines).

## Verification Plan

- AC-01 -> `rg -n 'cat > \*|\* > \*|\* <<' .claude/settings.json`
- AC-02 -> `rg -n "Plan Writer Handoff Envelope" packages/ai-universal-rules/templates/core/agents/architect.md` + manual read of Final Output
- AC-03 -> `rg -n "Handoff-First Token Discipline" packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md`
- AC-04 -> preview the "Required Plan File Format" `## Acceptance Criteria` block via `bash scripts/ai/preview-file.sh packages/ai-universal-rules/templates/core/agents/architecture-plan-writer.md`
- AC-05 -> `rg -n "max" packages/ai-universal-rules/templates/core/agents/architect.md` near Canonical References
- AC-06 -> `git diff` on the architect and writer frontmatter lines
- AC-07 -> `php tools/ai/validate-adapter-drift.php` ; `php tools/ai/generate-agent-permissions.php --check` ; `bash scripts/ai/ai-doc-check.sh --check` ; `wc -l` on both agent template files against hard-max 320

## Risks And Rollback

- Risk: re-render overwrites out-of-band `## graphify` sections in `AGENTS.md`/`CLAUDE.md` (`merge_strategy: replace`). Mitigation: re-apply those sections after re-render (or re-run graphify's installer).
- Risk: editing `packages/**` is deny-listed for most agents and requires explicit user approval before the implementer proceeds.
- Rollback: revert template edits via git; the live `.claude/settings.json` is regenerated from the template, so it can be re-rendered or reverted to the prior committed state.

## Handoff Notes

- Next required agent: implementer (once this plan is approved).
- Scope lock: this plan only covers the genuinely-open gaps identified when validating `docs/tickets/IDEAS/improvements-agents.md` against the CURRENT files; it explicitly EXCLUDES audit items already satisfied by the OpenCode `permission:` templates.
- Approval gate: because P0-01, P1-01, P1-02, P2-01, P2-02, and P2-03 all touch `packages/**` and/or trigger an installer re-render of generated outputs, the implementer must obtain explicit user approval before mutating those paths (AGENTS.md approval boundaries).
