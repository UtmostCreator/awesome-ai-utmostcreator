# Architecture Plan — Agent Naming Assessment (Rename Vs. No-Rename)

- Ticket: none
- Source: user request to assess whether any agents need renaming, grounded against
  `docs/ai/agents.md`, `docs/ai/AGENTS-MANIFEST.md` (referenced, not yet read in full),
  `packages/ai-universal-rules/templates/{core,optional}/agents/*.md`, and
  `packages/ai-universal-rules/templates/workflows/*.md`
- Generated: 2026-07-09
- Plan file: docs/tickets/IDEAS/plan-agent-rename-assessment.md
- Type: naming/consistency assessment; **recommendation is NOT to rename existing shipped agents**
- Risk: the assessment itself is low-risk (docs); the rejected alternative (bulk agent rename)
  would be high-risk (touches templates, 3 provider renders, permission compositions, tests,
  handoff registries, catalogs)

## Context

25 canonical agents exist across `templates/core/agents/` (13) and `templates/optional/agents/`
(12): `architect`, `architecture-plan-writer`, `bootstrapper`, `config-maintainer`, `implementer`,
`post-install`, `refactorer`, `release-auditor`, `repository-researcher`, `repository-reviewer`,
`researcher`, `reviewer`, `workflow-auditor`, plus `agent-creator*` (5), `agent-critic`,
`agent-fleet-assessor`, `bugfix`, `build-config`, `docs`, `infra-auditor`, `ui-builder`, `upgrade`.
23 workflows exist in `templates/workflows/`. Several agent names and their nearest-matching
workflow name diverge.

## Problem

Confirmed naming mismatches (agent name vs. nearest/only related workflow name):

| Agent | Nearest workflow | Mismatch type |
|---|---|---|
| `bugfix` | `bug-regression` | different lexical root (fix vs. regression) |
| `upgrade` | `dependency-upgrade` | agent name is a subset/shorter form |
| `docs` | `docs-sync` | agent name is a subset/shorter form |
| `repository-reviewer` | none yet (`repository-review.md` proposed, not built) | workflow missing entirely |
| `workflow-auditor` | none yet (`workflow-audit.md` proposed, not built) | workflow missing entirely |
| `bootstrapper` | none yet (`project-bootstrap.md` proposed, not built) | workflow missing entirely |
| `build-config` | none yet (`build-config-change.md` proposed, not built) | workflow missing entirely |
| `infra-auditor` | none yet (`infra-audit.md` proposed, not built) | workflow missing entirely |
| `ui-builder` | none yet (`ui-build.md` proposed, not built) | workflow missing entirely |
| `refactorer` | none yet (`refactor-slice.md` proposed, not built) | workflow missing entirely |

None of these are confusing enough to block usage today (`docs/ai/agents.md`'s routing table
already disambiguates every agent with a clear "When to use" sentence), but they are real
inconsistencies a newcomer or an automated router could trip on.

## Target Outcome

A documented decision: keep all 25 existing agent names as-is, and close the naming gap from the
workflow side (name the 7 missing workflows above after their matching existing agent, e.g.
`workflow-audit.md` not `audit-workflows.md`), rather than renaming any shipped agent.

## Why Not Rename (Blast-Radius Evidence)

A rename of even one agent (e.g. `bugfix` -> `bug-fix`, or `docs` -> `docs-sync`) requires
coordinated edits across, at minimum:

- `packages/ai-universal-rules/templates/{core,optional}/agents/<name>.md` (canonical source file
  rename)
- 3 rendered copies per provider: `.opencode/agents/<name>.md`,
  `.github/agents/<name>.agent.md`, `.claude/agents/<name>.md` — each is a file **rename**, not an
  edit, which this repo's own approval-boundaries rule treats cautiously ("If a rename cannot be
  represented as a direct move, stop and report `needs-rename-approval`" — `docs/ai/approval-
  boundaries.md`)
- `packages/ai-universal-rules/templates/workflows/*.md`'s `related-agents:` frontmatter lists that
  name the agent (multiple workflow files reference agent names by string)
- `tools/ai/install/permission-layers/compositions.php` and `packs.php` (agent-keyed permission
  composition, pack membership lists)
- `tools/ai/install/copilot-agent-handoff-registry.php` (`handoffs:` entries reference agent IDs by
  string — a rename that misses one entry silently breaks a Copilot handoff button)
- `docs/ai/agents.md`, `docs/ai/AGENTS-MANIFEST.md`, `docs/ai/agent-scores.yaml` (if D3a's
  per-agent value source is active), `docs/ai/catalog.md`, `packages/ai-universal-rules/catalog.json`
- Every existing plan file under `docs/tickets/**` that names the agent in prose (dozens of hits;
  historical text, but still a large blast radius if "fixed" literally)
- `tests/php/*AgentRendererTest.php`, `InstallerSafetyTest.php`, and any test asserting agent file
  presence/content by name

This is squarely the kind of "similar logic needs approval to replace" / "diff exceeds the planned
slice" stop condition this repo's own agent policy defines. The naming mismatch causes no observed
functional confusion today (verified: `docs/ai/agents.md`'s table already disambiguates every
agent by a clear one-sentence purpose, independent of the workflow-name mismatch).

## In Scope

- This assessment and its recommendation.
- A rule for future workflow naming: when a new workflow's primary purpose is "the process an
  existing agent runs," name the workflow after that agent (or a close variant), not vice versa —
  add this as one sentence to `docs/ai/ai-file-standards.md` or the workflow authoring guidance so
  future additions do not reintroduce the same mismatch class.
- Naming the 7 still-missing workflows (already tracked in `improvements-workflows.md`'s P0/P1
  tables) with agent-aligned names in this plan's recommendation table below, for whoever
  implements those workflows next.

## Out Of Scope (Things To Avoid)

- Renaming any existing shipped agent file, in any provider, in this pass.
- Building the 7 missing workflows themselves (tracked separately in `improvements-workflows.md`;
  this plan only fixes their proposed *names* to align with existing agents).
- Any change to `tools/ai/install/permission-layers/**`, `packs.php`, or the handoff registry.

## Recommended Workflow Names (Agent-Aligned, For Future Implementation)

| Missing workflow (as proposed in `improvements-workflows.md`) | Keep as-is? | Rationale |
|---|---|---|
| `repository-review.md` | Yes | Already agent-aligned (`repository-reviewer`). |
| `workflow-audit.md` | Yes | Already agent-aligned (`workflow-auditor`). |
| `project-bootstrap.md` | Yes | Already agent-aligned (`bootstrapper`, close enough — "bootstrap" root shared). |
| `build-config-change.md` | Yes | Already agent-aligned (`build-config`). |
| `infra-audit.md` | Yes | Already agent-aligned (`infra-auditor`). |
| `ui-build.md` | Yes | Already agent-aligned (`ui-builder`). |
| `refactor-slice.md` | Yes | Already agent-aligned (`refactorer`). |

Every one of the 7 proposed names in `improvements-workflows.md` is already agent-aligned — no
change needed there. The only real mismatches are the 3 pre-existing pairs (`bugfix`/`bug-regression`,
`upgrade`/`dependency-upgrade`, `docs`/`docs-sync`), which are agent-name-is-a-subset-of-workflow-
name cases, not contradictions, and are already disambiguated by `docs/ai/agents.md`'s routing
table — no action recommended.

## Contracts And Boundaries

- No agent template file is renamed by this plan.
- The one addition (a naming-consistency sentence in workflow authoring guidance) must not
  contradict `docs/ai/ai-file-standards.md`'s existing adapter/primitive-role rules.

## Todo Plan

- [ ] Add one sentence to the workflow-authoring guidance (`docs/ai/ai-file-standards.md` or
      `docs/ai/workflow.md`, whichever already owns "how to name a new workflow") stating the
      agent-alignment naming rule.
- [ ] No agent renames scheduled; close this plan as "assessed, no rename action" once the naming
      rule sentence lands.

## Acceptance Criteria

- AC-01: No existing agent template, rendered agent file, or agent ID string is changed by this
  plan.
- AC-02: A one-sentence naming rule exists in the appropriate authoring doc, citing this
  assessment.
- AC-03 (negative): `git diff --stat` after implementation shows at most one small doc edit — no
  agent/permission/handoff-registry files touched.

## Verification Plan

- Confirm via `ai-search.sh` that no agent file, permission composition, or handoff registry entry
  was touched (`git diff --stat` should show only the one doc-guidance file).
- Manual read of the added sentence against `docs/ai/ai-file-standards.md`'s existing tone/format.

## Risks And Rollback

- **Very low**: this plan's only in-scope action is one guidance sentence. Rollback: revert that
  one edit.
- **Not taken (documented for future reference)**: bulk-renaming agents remains available as a
  future decision if a maintainer decides the naming mismatch is worth the blast radius above —
  this plan documents the cost so that decision is informed, not assumed away.

## Handoff Notes

- Recommended next step: `implementer means implementer agent handoff` for the single guidance
  sentence; no `architect` or `reviewer` gate needed given the near-zero blast radius.
- If a future maintainer wants to revisit renaming, start from this plan's blast-radius list rather
  than re-deriving it.
