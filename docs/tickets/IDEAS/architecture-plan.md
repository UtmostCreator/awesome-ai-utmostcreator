# Architecture Plan — IDEAS Folder Reconciliation And Remaining Gaps

- Ticket: none
- Source: implementer-led grounded review of `docs/tickets/IDEAS/*.md` against the live repository
- Generated: 2026-07-09
- Plan folder: docs/tickets/IDEAS/
- Type: consolidation architecture plan (reconciles three speculative review docs with confirmed
  repo state; scopes the real remaining work; hands off five follow-up plans in the same folder)
- Risk: low (planning/docs only; no code changes in this plan)

> **Companion plans in this folder** (do not re-derive their content here, only reference it):
> `plan-ticket-archive-consolidation.md`, `plan-agent-rename-assessment.md`,
> `plan-agent-workflow-routing-diagram.md`, `plan-provider-wiring-reconciliation.md`,
> `plan-provider-content-parity-validator.md`.

## Context

`docs/tickets/IDEAS/improvements-commands.md`, `improvements-scripts.md`, and
`improvements-workflows.md` are speculative reviews (no author/date recorded) that scored the
repo's command/script/workflow surface against an idealized target. Grounding them against the
live repository (`packages/ai-universal-rules/templates/**`, `tools/ai/install/**`, `.opencode/**`,
`.github/**`, `.claude/**`, `docs/tickets/**`) found: one document (`improvements-scripts.md`) is
almost entirely already-implemented and stale; one (`improvements-commands.md`) scored the wrong
surface (a 5-file thin-command tier instead of the full 23-workflow rendered surface) and
overstated a real gap; one (`improvements-workflows.md`) is substantially accurate and is the
strongest of the three. Corrections were added in place to each file (see the `> **Correction**`
blocks added 2026-07-09). This plan is the consolidated, evidence-checked architecture read and
the map of what remains actionable.

## Problem

Three unowned, undated review documents risked being treated as current backlog when large parts
of their content had already shipped (script registry, agent-script-access doc, Claude adapter
parity, architecture diagrams, Mermaid guidance on architect/plan-writer agents, permission
composition model) or scored the wrong file tier (command coverage). Without reconciliation,
future agents reading `docs/tickets/IDEAS/` would duplicate already-done work or misjudge real
priority.

## Target Outcome

`docs/tickets/IDEAS/*.md` accurately reflect current repo state (via in-place corrections, already
applied), and the genuinely open items are captured as bounded, non-duplicative plans that name
their real remaining scope and point at the existing authoritative work (`plan-28`, `plan-29/30`,
the Claude adapter parity plan) instead of re-designing it.

## In Scope

- Grounded fact-check of all three `improvements-*.md` files against: `packages/ai-universal-rules/templates/{workflows,commands,core/agents,optional/agents}/`, `tools/ai/install/packs.php`, `.opencode/{commands,skills,agents}/`, `.github/{prompts,skills,agents}/`, `.claude/{commands,skills,agents}/`, `docs/ai/script-registry.{json,md}`, `docs/ai/agent-script-access.md`, `docs/ai/architecture-diagrams.md`, `docs/ai/agents.md`, `docs/tickets/MASTER-INDEX.md`, `docs/tickets/claude-agent-fleet-remediation/plan-28/29/30`, `docs/tickets/arch-todo-claude-code-adapter-parity-20260704-120000/plan.md`.
- In-place `> **Correction**` annotations added to the top of each of the three files (done in this
  pass — see diff).
- This consolidated architecture read.
- Five scoped follow-up plans (listed above), each independently implementable, each checked
  against the `>=75%` reuse rule before being written.

## Out Of Scope (Things To Avoid)

- Rewriting or deleting the three original IDEAS files — corrections are additive annotations, the
  original speculative content stays as historical record.
- Re-implementing anything `plan-28`, `plan-29`, `plan-30`, or the Claude adapter parity plan
  already designed or already landed.
- Implementation of any code change (this plan and its five companions are plans only).
- Restarting the script-registry/permission-composition design (already exists and is the correct
  shape per `docs/tickets/MASTER-INDEX.md`'s "P6 REJECTED" precedent).

## Affected Paths

- `docs/tickets/IDEAS/improvements-commands.md` (corrected, this pass)
- `docs/tickets/IDEAS/improvements-scripts.md` (corrected, this pass)
- `docs/tickets/IDEAS/improvements-workflows.md` (corrected, this pass)
- `docs/tickets/IDEAS/architecture-plan.md` (this file, new)
- `docs/tickets/IDEAS/plan-ticket-archive-consolidation.md` (new)
- `docs/tickets/IDEAS/plan-agent-rename-assessment.md` (new)
- `docs/tickets/IDEAS/plan-agent-workflow-routing-diagram.md` (new)
- `docs/tickets/IDEAS/plan-provider-wiring-reconciliation.md` (new)
- `docs/tickets/IDEAS/plan-provider-content-parity-validator.md` (new)

## Findings By Area

### Commands And Workflows

- **Confirmed wrong in the original doc**: "current command coverage 42/100" only inventoried the
  5-file `templates/commands/` tier. The real entrypoint surface is 23/23 workflows already
  rendered as `.opencode/commands/*.md`, `.github/prompts/*.prompt.md`, and
  `{claude,opencode,github}/skills/*/SKILL.md` (`tools/ai/install/packs.php` lines ~164-207,
  `runtime-copilot.sh:43`). Verified counts: `.opencode/commands/` 24, `.github/prompts/` 23,
  `.claude/skills/` 23, `.opencode/skills/` 24, `.github/skills/` 23.
- **Real, narrower gap confirmed**: `.claude/commands/*.md` only receives the 5 thin
  `templates/commands/` files, not the 23 workflow-derived ones (`packs.php:207` maps
  `templates/commands` -> `.claude/commands`, not `templates/workflows`). Claude gets workflows
  as skills only. This is an intentional asymmetry per the Claude adapter parity plan
  (P2-2: "Claude's own docs say skills and commands register the same `/name` slash command...
  dual-shipping would only register duplicate commands for no benefit") — **not a bug**, but
  worth naming explicitly in the wiring reconciliation plan so it is not "fixed" by accident.
- **Confirmed still missing**: the 6 P0 agent-governance workflows and 7 P1 specialist workflows
  named in `improvements-workflows.md` (`agent-creation-pipeline.md` through `project-bootstrap.md`)
  are genuinely absent from `templates/workflows/`. This is real backlog, already correctly scoped
  in that file; this plan does not re-scope it, only confirms it.

### Scripts And Permissions

- **Confirmed already done**: `docs/ai/script-registry.json`, `docs/ai/script-registry.md`,
  `docs/ai/script-registry.schema.json`, `docs/ai/agent-script-access.md` all exist. Agent
  permission frontmatter is generated from a composed model
  (`tools/ai/install/permission-layers/*`), not hand-authored. `scripts/ai/bin/{read,context,
  verify,edit,admin,hooks}/` + `scripts/ai/internal/**` match the doc's own recommended split
  exactly.
- **Confirmed still open, and already owned by `plan-28`**: three permission-enforcement surfaces
  (composed agent body, `.claude/settings.json`, `command-policy.tiers.yaml`) remain only
  partially linked; a full byte-parity render gate for `.claude/agents` + `.github/agents` is
  landing now (working tree shows `claude-runtime-capabilities.php` and
  `ClaudeCapabilityFilterTest.php` as new/modified, matching plan-28 Phase 3 in progress). Do not
  open a competing design — see `plan-provider-content-parity-validator.md`, which explicitly
  scopes around plan-28 rather than duplicating it.

### Diagrams

- **Confirmed already done**: `docs/ai/architecture-diagrams.md` exists (380 lines, hand-authored-
  must-sync, covers install/render/permission-composition pipeline including a "Current vs.
  Target" permission section). The architect and architecture-plan-writer agent templates already
  carry a `## Architecture Diagram (Mermaid)` / `## Architecture Diagram` section
  (`plan-30-architecture-mermaid-redesign.md`, landed).
- **Confirmed still missing**: no diagram depicts agent-to-workflow routing (which agent uses which
  workflow, grouped, to show correct usage) — `docs/ai/agents.md` has this as a prose table only.
  See `plan-agent-workflow-routing-diagram.md`.

### Agent Naming

- Real mismatches exist between agent names and their nearest workflow (`bugfix` agent vs.
  `bug-regression` workflow, `upgrade` vs. `dependency-upgrade`, `docs` vs. `docs-sync`,
  `repository-reviewer` vs. a not-yet-built `repository-review` workflow, `workflow-auditor` vs. a
  not-yet-built `workflow-audit` workflow, `bootstrapper` vs. a not-yet-built `project-bootstrap`
  workflow). See `plan-agent-rename-assessment.md` for the blast-radius analysis and recommendation
  (spoiler: renaming shipped agents is high-blast-radius; the plan recommends against renaming and
  proposes closing the naming gap by naming new workflows after existing agents instead).

### Ticket Archiving

- `docs/tickets/README.md`'s canonical convention is per-branch-folder:
  `docs/tickets/{branch}/archive/DONE-plan-{n}-{desc}.md` (confirmed still in effect; used by
  `plan-29`/`plan-30`'s own completion instructions).
- `docs/tickets/MASTER-INDEX.md` references a now-**removed** top-level
  `docs/tickets/archive/completed-20260614/` (confirmed absent via `Glob`; it was deleted by the
  `markdown-surface-reduction` plan's Phase 1, commit `0c0f219`, per that plan's own record). So
  the repo currently has exactly one archiving convention in effect (per-branch), and one now-stale
  reference to a deleted convention in `MASTER-INDEX.md`.
- See `plan-ticket-archive-consolidation.md` for the proposed top-level `docs/tickets/archive/*`
  design the user requested, reconciling both historical conventions.

## Contracts And Boundaries

- Source-of-truth precedence for this plan: live repo state (file existence, `packs.php` wiring)
  over any of the three IDEAS documents' self-reported scores.
- `>=75%` reuse rule: every new plan in this batch was checked against `plan-28`, `plan-29`,
  `plan-30`, and the Claude adapter parity plan before being scoped; where overlap was found, the
  new plan narrows to the residual gap instead of re-designing the overlapping part.
- This plan does not implement; implementation of any follow-up plan requires its own
  architecture-plan-writer persistence review and, per each plan's own risk classification,
  reviewer/release-auditor gating.

## Todo Plan

- [x] Read and fact-check all three `improvements-*.md` files against live repo state.
- [x] Add in-place `> **Correction**` blocks to each of the three files.
- [x] Write this consolidated architecture plan.
- [ ] Write `plan-ticket-archive-consolidation.md`.
- [ ] Write `plan-agent-rename-assessment.md`.
- [ ] Write `plan-agent-workflow-routing-diagram.md`.
- [ ] Write `plan-provider-wiring-reconciliation.md`.
- [ ] Write `plan-provider-content-parity-validator.md`.

## Acceptance Criteria

- AC-01: Each of the three original IDEAS files has a dated correction block naming what is
  confirmed done, confirmed wrong, or confirmed still accurate — no silent deletion of original
  content.
- AC-02: This architecture plan cites concrete evidence (file paths, counts, `packs.php` line
  ranges) for every "confirmed" claim above, not restated assumption.
- AC-03: No follow-up plan in this batch re-designs `plan-28`'s permission-parity model,
  `plan-29`/`plan-30`'s diagram work, or the Claude adapter parity plan's rendering pipeline.
- AC-04: Every follow-up plan names its own risk level and verification approach independently.

## Verification Plan

- Re-run the `Glob`/`Grep` checks cited in "Findings By Area" to confirm counts have not drifted
  before implementing any follow-up plan.
- Confirm `docs/tickets/claude-agent-fleet-remediation/plan-28-*.md`'s Todo Plan checkbox state
  before scoping any permission-parity follow-up, since it is actively in progress (working-tree
  evidence: `tools/ai/install/claude-agent-renderer.php` modified, `claude-runtime-capabilities.php`
  and `tests/php/ClaudeCapabilityFilterTest.php` untracked-new at review time).
- This plan itself requires no test run (docs-only); the five follow-up plans each define their
  own verification.

## Risks And Rollback

- **Low**: this is a documentation/planning slice; rollback is reverting the six new/edited files.
- **Medium (informational)**: `plan-28` is mid-flight in the working tree; any follow-up plan that
  touches the same surfaces must re-check its Todo state before implementation, not assume the
  snapshot in this plan is still current.

## Handoff Notes

- Recommended next step: `architecture-plan-writer means architecture-plan-writer agent handoff`
  to review this plan and its five companions for persistence-format compliance, then route each
  companion plan to `implementer` independently as its own bounded slice (do not batch all five
  into one implementation pass — each has a different owner/risk profile).
