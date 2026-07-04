# Integration Matrix

This kit installs shared AI workflow docs, Copilot surfaces under `.github/`, OpenCode surfaces under `.opencode/`, scripts under `scripts/ai/`, and validation tooling under `tools/ai/`.

This document is also the canonical coverage matrix: it maps every critical topic and
workflow-critical primitive to the surface that guarantees it loads on each runtime,
and it classifies shipped template surfaces so dead files can be flagged.

## Guaranteed-Load Surfaces Per Runtime (Runtime Surface Matrix)

This table and its notes are the maintained runtime surface matrix: the practical
Copilot/OpenCode/Claude differences in how a shipped surface actually loads, without
implying the runtimes are structurally equivalent. A surface is **guaranteed-load**
when the runtime loads it without any agent choice.

| Runtime | Always-on surfaces | Deterministic-load surfaces | Enforced (non-prompt) surfaces |
|---|---|---|---|
| Copilot | `.github/copilot-instructions.md` | `.github/instructions/*.instructions.md` via `applyTo` globs | `.github/hooks/tool-policy.json` + guardian scripts |
| OpenCode | `AGENTS.md` plus the `opencode.jsonc` `instructions[]` doc set | agents, commands, skills loaded by explicit invocation | `opencode.jsonc` `permission` block |
| Claude | `CLAUDE.md`, `AGENTS.md` | docs reached from CLAUDE.md "Read First" routing | none by default (prompt-level only) |

Runtime limitation notes (no false parity):

- Claude has no shipped hook/permission enforcement; shell-policy topics are advisory there.
- Copilot `applyTo` loading is deterministic per touched path, not per session; `applyTo: "**"` files are the practical always-on set.
- OpenCode `instructions[]` docs load every session; this was the broadest always-on surface and the first thinning target; reduced in Phase 3.1 (see below).
- Phase 3.1: the OpenCode `instructions[]` set was reduced from 13 to 8 docs
  (`AGENTS.md`, `docs/ai/project-context.md`, `docs/ai/project/project-interaction.md`,
  `docs/ai/workflow.md`, `docs/ai/execution-protocol.md`, `docs/ai/ai-file-standards.md`,
  `docs/ai/approval-boundaries.md`, `docs/ai/adapter-contract.md`). No critical-topic row
  below cited the 5 removed docs (`docs/ai/tools/ai-search.md`, `docs/ai/tools/tool-map.md`,
  `docs/ai/tools/actions/search-evidence.md`, `docs/ai/tools/actions/preview-file.md`,
  `docs/ai/generated-artifacts.md`) as their coverage owner, so removal is coverage-neutral.
  They are now `deterministic-load`: `AGENTS.md` (always-on) names
  `docs/ai/tools/tool-map.md` and `docs/ai/generated-artifacts.md` by path, and
  `docs/ai/tools/tool-map.md`'s own "See Also" section names the other 3 tool docs,
  closing the reachability chain per rule 3 below.
- Phase 3.2: `.github/copilot-instructions.md` (via its template source) was thinned from
  139 to 117 lines by collapsing the "Working Style", "Evidence-First Execution", and
  "Core Workflow" sections.   Removed bullets were fully redundant with `applyTo: "**"`
  instruction files that load deterministically on every touched path regardless of
  which files are edited: `.github/instructions/copilot-script-enforcement.instructions.md`
  (script-registry preference; the removed bullet's unique `docs/ai/script-registry.json`
  reference was preserved by folding it into the "Copilot-Specific: Shell Script
  Enforcement" section instead of dropping it) and
  `.github/instructions/ai-file-standards.instructions.md` (keep-this-file-policy-focused,
  since it applies to `.github/copilot-instructions.md` itself). "Evidence-First
  Execution" and "Core Workflow" were collapsed to routing sentences pointing at
  `docs/ai/execution-protocol.md` / `.github/instructions/execution-protocol.instructions.md`
  and `docs/ai/workflow.md` respectively, since their content was a verbatim restatement
  of those `**`-scoped surfaces. No bullet cited by this matrix as a Copilot coverage
  owner (Behavioral Baseline, Hard Stops, Limits, the `<APPROVAL_REQUIRED_CHANGES>`
  placeholder) was touched. The rendered `.github/copilot-instructions.md` was hand-edited
  to match, same approved approach as Phase 0-2 and Phase 3.1 (installer render cannot
  regenerate it without an unrelated blast radius on other pending working-tree files);
  a full-file diff against the template shows only expected `<PLACEHOLDER>` resolution.

## Critical-Topic Coverage Matrix

Status: `covered` = guaranteed-load today; `partial` = present but not guaranteed on every runtime; `gap` = pending the named program phase.

| Topic | Copilot | OpenCode | Claude | Status |
|---|---|---|---|---|
| Security | `.github/instructions/security.instructions.md` (`applyTo: **`) | `AGENTS.md` + `docs/ai/approval-boundaries.md` (always-on) | `CLAUDE.md` approval boundaries | covered |
| Approval boundaries | `.github/instructions/approval-boundaries.instructions.md` (`**`) | `docs/ai/approval-boundaries.md` (always-on) | `CLAUDE.md` + `AGENTS.md` | covered |
| Task-context gate | `.github/instructions/context-gate.instructions.md` (`**`) | `AGENTS.md` workflow rules + `docs/ai/project-context.md` §4 | `AGENTS.md` | covered |
| Source-of-truth | `.github/instructions/ai-workflow.instructions.md` + baseline routing | `docs/ai/project-context.md` §3 (always-on) | `CLAUDE.md` "Read First" -> `docs/ai/source-of-truth.md` | covered |
| Verification honesty | `.github/instructions/execution-protocol.instructions.md` (`**`) | `docs/ai/execution-protocol.md` (always-on) | `AGENTS.md` verification rules | covered |
| Escalation | copilot-instructions "Hard Stops" | `AGENTS.md` escalation rules | `AGENTS.md` escalation rules | covered |
| Runtime limitations | copilot-instructions "Limits" | `docs/ai/adapter-contract.md` (always-on) | `CLAUDE.md` fallback note | covered |
| Shell-policy boundaries | `.github/instructions/copilot-script-enforcement.instructions.md` (`**`) + hooks (enforced) | `opencode.jsonc` `permission` (enforced) | `CLAUDE.md` approval section (advisory only) | covered, Claude advisory |
| Clarification-before-action | `.github/instructions/context-gate.instructions.md` + `docs/ai/capabilities/clarification-and-handoff/CAPABILITY.md` | `AGENTS.md` workflow rules + `docs/ai/capabilities/clarification-and-handoff/CAPABILITY.md` | `AGENTS.md` + `docs/ai/capabilities/clarification-and-handoff/CAPABILITY.md` | partial — deterministic-load capability, not guaranteed-load on any runtime; see Phase 3+ for a possible always-on pointer |
| Stop-or-assume rules | `docs/ai/capabilities/clarification-and-handoff/CAPABILITY.md` "Stop-Or-Assume Branch" | same | same | partial — deterministic-load capability, not guaranteed-load on any runtime |
| Preserved handoff payload | `docs/ai/handoff-contract.md` (canonical, routed) | `docs/ai/handoff-contract.md` (canonical, routed) | `docs/ai/handoff-contract.md` via routing | covered (Phase 2.1) |
| Acceptance-criteria capture | `docs/ai/handoff-contract.md` minimum payload | same | same | covered (Phase 2.1); PRD-family capture lands in Phase 4 |
| Verification-expectation capture | `docs/ai/handoff-contract.md` minimum payload + execution-protocol statuses | `docs/ai/execution-protocol.md` statuses + `docs/ai/handoff-contract.md` | `AGENTS.md` evidence expectations + `docs/ai/handoff-contract.md` | covered (Phase 2.1) |
| Loop-guard / stop-after-N-failed-attempts (F-2) | `.github/copilot-instructions.md` "Behavioral Baseline" (always-on) | `AGENTS.md` "Behavioral Baseline" (always-on) | `AGENTS.md` "Behavioral Baseline" (routed via CLAUDE.md) | covered (Phase 1.1) |
| Blocked-edit fallback (F-4) | `.github/copilot-instructions.md` "Behavioral Baseline" (always-on) | `AGENTS.md` "Behavioral Baseline" (always-on) | `AGENTS.md` "Behavioral Baseline" (routed via CLAUDE.md) | covered (Phase 1.1); agent-template enforcement still pending Phase 6.3 |
| Test-first ordering (F-13) | `.github/instructions/execution-protocol.instructions.md` (`**`) | `docs/ai/execution-protocol.md` (always-on) | `AGENTS.md` testing rules | covered |
| Reference integrity (F-13) | `.github/instructions/execution-protocol.instructions.md` "Reference Integrity" | `docs/ai/execution-protocol.md` "Reference Integrity" | `AGENTS.md` call-site rule | covered |

Thinning rule: no thinning slice may remove a surface named in this matrix until the
matrix row names the replacement surface and the per-slice validator gate passes.

## Semantic-Parity Review Methodology (B-10)

Copilot, OpenCode, and Claude load shipped surfaces through different mechanisms
(`applyTo` globs, `instructions[]` entries, prose "Read First" routing), so file
structure diverges across runtimes by design. That divergence is not itself a defect.
A reviewer checks **semantic** coverage parity, not structural parity, using this
procedure whenever a slice thins, relocates, or merges a shipped surface:

1. Identify every row in the Critical-Topic Coverage Matrix touched by the diff
   (content added, removed, or relocated in an always-on or deterministic-load
   surface for any runtime).
2. For each affected runtime column, confirm the row's post-change owning surface
   still satisfies its load-path class from the table above: always-on surfaces
   must still load every session; deterministic-load surfaces must still resolve
   their trigger (glob match, `instructions[]` entry, or a named path reachable from
   an always-on surface per the Reachability Rules below).
3. Diff the row's `Status` column (`covered` / `partial` / `gap`) before and after
   the change. A `covered` -> `partial`/`gap` transition on any runtime is a
   regression and blocks the slice unless this matrix is updated in the same slice
   to name the replacement surface (the Thinning Rule above).
4. Confirm cross-runtime asymmetry is explicit, not silent. Per
   `docs/ai/adapter-contract.md` ("document the fallback instead of implying
   parity"), a runtime that structurally cannot carry a topic (for example Claude's
   lack of hook/permission enforcement) must say so in its matrix cell or in
   "Runtime limitation notes" — a vague or empty cell where another runtime has a
   concrete surface is a false-parity defect, not an accepted gap.
5. Where file structures diverge (for example one topic split across two Copilot
   instruction files but merged into a single OpenCode doc), compare by topic
   content, not by file count or file name: list the specific rules that must
   survive, then confirm each one survives on each runtime, regardless of which
   file carries it.
6. Run the per-slice validator gate (`docs/ai/validation.md`).
   `validate-adapter-drift.php --fail-on-warn` catches missing canonical references
   and oversized bodies but not topic-level semantic loss — steps 1-5 are the
   human/reviewer-agent check that closes that gap; no validator performs them.
7. Record the outcome in this matrix: update the row's `Status` cell and add a
   dated note describing what moved and why coverage held (see the Phase 3.1/3.2
   notes above for the expected shape), so later reviewers audit reasoning instead
   of re-deriving it.

## Shipped Surface Role Classification

Every shipped template surface under `packages/ai-universal-rules/templates/**` (and its
installed output) carries exactly one role:

| Role | Meaning | Dead-file risk |
|---|---|---|
| `always-on-critical` | loaded every session on at least one runtime; owns matrix topics | never dead; changes need coverage proof |
| `deterministic-load` | loaded by a runtime rule (`applyTo` glob, `instructions[]` entry, agent/command/skill invocation, capability routing) | dead when no load rule references it |
| `optional-support` | reached only by explicit human or agent choice (examples, references, optional packs) | dead when nothing routes to it |
| `generated-or-install-only` | rendered or consumed at install time, not read at task time | dead when the generator/installer no longer emits or reads it |

Classification by template subtree:

| Template subtree | Role | Load path |
|---|---|---|
| `packages/ai-universal-rules/templates/core/AGENTS.template.md`, `packages/ai-universal-rules/templates/core/copilot-instructions.template.md`, `packages/ai-universal-rules/templates/core/opencode.json` | always-on-critical | rendered to root/always-on adapter surfaces |
| `packages/ai-universal-rules/templates/core/project-context.template.md`, `packages/ai-universal-rules/templates/core/execution-protocol.template.md`, `packages/ai-universal-rules/templates/core/workflow.template.md`, `packages/ai-universal-rules/templates/core/ai-file-standards.template.md` | always-on-critical (OpenCode `instructions[]`) / deterministic-load (Copilot routing) | `opencode.jsonc` + canonical-doc routing |
| `packages/ai-universal-rules/templates/core/generated-artifacts.template.md` | deterministic-load | routed from `AGENTS.md` "Read This First" by path (Phase 3.1; no longer in OpenCode `instructions[]`) |
| `instructions/*.instructions.md` | deterministic-load | Copilot `applyTo` globs |
| `core/agents/**`, `optional/agents/**` | deterministic-load | agent selection/invocation |
| `workflows/**` | deterministic-load | prompt/command launchers |
| `skills/**`, `commands/**` | deterministic-load | skill/command invocation; each new entry must name its reachability path |
| `capabilities/**` | deterministic-load | capability routing from agents/instructions |
| `snippets/**` | generated-or-install-only | included into rendered surfaces at install/render time |
| `core/project/**`, `docs/**` | optional-support / deterministic-load | canonical-doc routing, user-owned starters |
| `github/hooks/**` | always-on-critical (enforcement) | hook execution on Copilot surfaces |
| `shared/**` | always-on-critical (`docs/ai/AI-GUARDRAILS.md`) / deterministic-load / optional-support (the rest) | base pack + `shared-templates-pack` |
| `internal/**` | none confirmed | not referenced by any pack entry; see the per-file inventory for the confirmed dead-file example |

This subtree table is a summary only. Every individual shipped template file, with its
specific role and load path, is enumerated in `docs/ai/shipped-surface-inventory.md`
(the Phase 0.2 per-file classification companion).

## Reachability Rules (When An Installed File Is Dead)

An installed file counts as **dead** when all of the following hold:

1. It is not always-on on any runtime (not in `opencode.jsonc` `instructions[]`, not a root adapter, not `applyTo: "**"`).
2. No deterministic load rule references it (no `applyTo` glob, no agent/command/skill/capability route, no hook wiring).
3. No shipped always-on or deterministic-load surface links to it by path.
4. The knowledge graph (when present) shows no inbound reference edges (secondary signal only, never the sole evidence).

Review model: any slice that adds a shipped file must name its load path from this
classification; any slice that removes one must show the file is dead by rules 1–3.

## Maintenance

Update this matrix in the same slice as any change that adds, removes, or relocates a
topic owner or shipped surface. Validate with the per-slice gate in `docs/ai/validation.md`.
