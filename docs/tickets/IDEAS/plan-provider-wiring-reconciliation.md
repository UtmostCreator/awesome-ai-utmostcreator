# Architecture Plan — Provider Wiring Reconciliation (Agents + Commands + Workflows + Instructions)

- Ticket: none
- Source: user request to "wire all correctly agents + commands + workflow + instructions...
  ensure fully match for all providers (copilot, opencode, claude)"; grounded against
  `tools/ai/install/packs.php`, `docs/ai/integration-matrix.md`, `docs/ai/adapter-contract.md`, and
  `docs/tickets/claude-agent-fleet-remediation/plan-28-permission-sot-and-render-parity-sync.md`
- Generated: 2026-07-09
- Plan file: docs/tickets/IDEAS/plan-provider-wiring-reconciliation.md
- Type: one-time reconciliation audit (manual/semantic), companion to
  `plan-provider-content-parity-validator.md` (automated/ongoing gate) — **these two plans are
  deliberately split**: this one is a bounded audit pass producing a findings table; the other
  designs the durable CI check. Do not merge them into one slice.
- Risk: low for the audit itself (read-only); medium for any fix it recommends, gated per-fix

## Context

This repo ships four primitive types to three providers (Copilot/`.github`, OpenCode/`.opencode`,
Claude/`.claude`) from one canonical source tree
(`packages/ai-universal-rules/templates/{core,optional}/agents`, `templates/workflows`,
`templates/commands`). `docs/ai/integration-matrix.md` already documents *known, intentional*
per-runtime asymmetries (Claude's coarser settings.json enforcement, no `task: ask` equivalent,
Copilot's structured `handoffs:` vs. prose-only elsewhere). `plan-28` (in progress) is already
building a byte-parity render gate, but scoped specifically to **agent bash/edit permission**
bodies (`.claude/agents` + `.github/agents` vs. canonical templates) — confirmed via its own Head B
problem statement ("`generate-agent-permissions.php --check` covers only templates + `.opencode`").
It does not audit commands/skills/prompts content parity or instructions-equivalent coverage.

## Problem

No single audit pass has walked all four primitive types x three providers together and recorded,
in one place, exactly which pairings are (a) intentionally asymmetric and documented, (b)
accidentally asymmetric and undocumented, or (c) unverified. Without this, a future contributor
cannot tell "is this gap a known design tradeoff or a bug" without re-deriving the whole wiring
graph from source each time.

## Target Outcome

One findings document (this plan's primary deliverable, produced during implementation — not
written here since a full evidence walk is itself the implementation work) that enumerates every
{primitive-type x provider} pairing, its current wiring path (`packs.php` entry or absence), and a
classification (`intentional-asymmetry-documented`, `intentional-asymmetry-undocumented-fix-docs`,
`accidental-gap-fix-code`, `unverified-needs-follow-up`). Feeds directly into
`plan-provider-content-parity-validator.md`'s design (that plan can then encode exactly the
`intentional-asymmetry-documented` cases as allowed exceptions instead of guessing).

## In Scope

- Enumerate every `packs.php` entry with `install_type` touching `core/agents`, `optional/agents`,
  `workflows`, or `commands` as its `source`, and its `target` (one row per provider x primitive
  pairing). Confirmed starting inventory from this pass's own grounding:
  - agents: `core/agents` + `optional/agents` -> `.opencode/agents`, `.github/agents`,
    `.claude/agents` (3 renderers: `copilot-agent-renderer.php`, `claude-agent-renderer.php`, plus
    OpenCode's own dir-copy path — confirm exact OpenCode renderer name during implementation).
  - workflows -> `.opencode/commands` (`opencode-commands`), `.github/prompts` (rename-ext),
    `{opencode,github,claude}/skills` (`skill-dirs`) — confirmed 4 targets, not 3 (workflows fan out
    to both a command-shaped AND a skill-shaped surface on OpenCode).
  - commands (the 5 thin templates) -> `.opencode/commands`, `.claude/commands` — **not** to
    `.github/prompts` or `.github/commands` (confirmed absent in `packs.php`); Copilot only gets
    workflow-derived prompts, never the 5 thin command templates directly. Confirm whether this is
    documented anywhere as intentional, or is an undocumented gap.
  - instructions: `.github/instructions/*.instructions.md` (Copilot `applyTo`-scoped) has no
    structural OpenCode/Claude equivalent by design (`docs/ai/integration-matrix.md`'s Runtime
    Surface Matrix already documents OpenCode uses `AGENTS.md` + `opencode.jsonc instructions[]`,
    Claude uses `CLAUDE.md` + `AGENTS.md`) — confirm this is fully and currently accurately
    reflected, since `integration-matrix.md` itself warns some of its own rows may drift.
- For each row, verify against the live repo (not just `packs.php`'s stated intent) that the target
  actually contains the expected file count (spot-check counts, matching the grounding done in
  `architecture-plan.md`).
- Cross-check every asymmetry found against `docs/ai/integration-matrix.md`'s existing
  documentation — flag any asymmetry that is real but undocumented.
- Produce the findings table described in Target Outcome.

## Out Of Scope (Things To Avoid)

- Re-designing or re-implementing `plan-28`'s byte-parity gate (agent permission bodies) — this
  plan explicitly excludes that surface from its own scope to avoid duplication; reference
  `plan-28`'s findings instead of re-deriving them.
- Fixing any found gap in this plan — findings only; each fix becomes its own bounded follow-up
  slice with its own risk classification (a doc fix is low risk; a new renderer/pack entry is
  medium; changing which primitives Copilot/Claude receive is a design decision requiring
  `architect` review, not an audit-plan decision).
- Building the automated validator — that is `plan-provider-content-parity-validator.md`'s job.
- Any change to `.github/instructions/*.instructions.md` content itself (only inventorying its
  existence/scope, not auditing its prose).

## Affected Paths (Read-Only For This Plan; Fix Paths TBD Per Finding)

- Read: `tools/ai/install/packs.php`, `tools/ai/install/executor.php`,
  `tools/ai/install/runtime-copilot.sh`, `docs/ai/integration-matrix.md`,
  `docs/ai/adapter-contract.md`
- Read: `.opencode/{agents,commands,skills}/`, `.github/{agents,prompts,skills,instructions}/`,
  `.claude/{agents,commands,skills}/`
- Write (this plan's own deliverable): a new findings doc, location TBD during implementation —
  recommend `docs/ai/generated/provider-wiring-audit.md` if ephemeral, or a section in
  `docs/ai/integration-matrix.md` itself if the findings should become durable canonical doc
  content (recommended: the latter, since `integration-matrix.md` already owns this exact
  cross-provider coverage-matrix role and already has a "Maintenance/Thinning Rule" this findings
  table should follow rather than creating a competing doc).

## Contracts And Boundaries

- This is a research/audit slice: read repo state, classify, report. No code or template edit
  happens in this plan's own execution beyond the findings doc itself.
- Reuse `docs/ai/integration-matrix.md`'s existing table conventions (Runtime Surface Matrix,
  Critical-Topic Coverage Matrix) rather than inventing a new matrix format — extend that file with
  a new "Primitive Wiring Matrix" section instead of a separate doc, per the `>=75%` reuse rule.
- Any classification of `accidental-gap-fix-code` must name the specific fix as a follow-up plan
  reference, not implement it inline.

## Todo Plan

- [ ] Enumerate all `packs.php` entries for the 4 primitive types x 3 providers (confirm exact
      counts/targets per the "In Scope" bullet list above).
- [ ] Verify each entry against live installed file counts in this repo.
- [ ] Cross-check each asymmetry against `docs/ai/integration-matrix.md`'s existing documentation.
- [ ] Classify every {primitive x provider} pairing using the four-way classification.
- [ ] Add a new "Primitive Wiring Matrix" section to `docs/ai/integration-matrix.md` with the
      findings table.
- [ ] For every `accidental-gap-fix-code` or `intentional-asymmetry-undocumented-fix-docs` finding,
      open (name, do not implement) a follow-up ticket reference.

## Acceptance Criteria

- AC-01: Every cell of the {agents, commands, workflows, instructions} x {Copilot, OpenCode,
  Claude} = 12-cell matrix has an explicit classification (no blank cells).
- AC-02: Every `accidental-gap` or `undocumented-asymmetry` finding cites the exact evidence
  (file/path/count) that proves the gap, not an assumption.
- AC-03: `docs/ai/integration-matrix.md`'s new section follows its existing table/heading
  conventions (verified by a diff review, not a new doc-format invention).
- AC-04 (negative): No code, template, or pack-registry file is edited by this plan's own
  execution — only `docs/ai/integration-matrix.md` gains content.

## Verification Plan

- Manual cross-check of the findings table against direct `Glob`/file-count evidence (repeat the
  same style of verification used in `architecture-plan.md`'s "Findings By Area").
- `php tools/ai/validate-adapter-drift.php` (confirms the doc edit itself introduces no drift
  warning against `integration-matrix.md`'s own reference rules, if any apply).

## Risks And Rollback

- **Low**: read-only audit; the only write is one new doc section.
- **Rollback**: revert the `integration-matrix.md` section addition.

## Handoff Notes

- Recommended next step: `repository-researcher means repository-researcher agent handoff` to
  execute the enumeration (script-first, evidence-heavy — matches that agent's own charter), then
  `docs means docs agent handoff` to land the findings section in `integration-matrix.md`.
- Any fix findings become their own architect-reviewed follow-up plans; do not implement fixes
  directly from this audit's findings without that gate, per this repo's own workflow rule (`route
  through architect first for medium/high-risk design decisions`).
- This plan's findings are a required input to `plan-provider-content-parity-validator.md` — land
  this one first, or at minimum draft its findings table before finalizing that plan's exception
  list.
