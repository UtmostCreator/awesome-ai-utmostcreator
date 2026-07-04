# Adapter Contract

Adapters for Copilot, OpenCode, and Claude must preserve canonical rules from `docs/ai/` and avoid introducing conflicting policy.

## Adapter Surfaces

These files are runtime adapters, not canonical policy:

- `AGENTS.md`, `.github/copilot-instructions.md`, `CLAUDE.md` — kit-managed; rendered from
  `packages/ai-universal-rules/templates/core/AGENTS.template.md`,
  `templates/core/copilot-instructions.template.md`, and `templates/core/CLAUDE.template.md`
  respectively. `CLAUDE.md` became kit-managed as of the Claude adapter parity plan
  (`docs/tickets/arch-todo-claude-code-adapter-parity-20260704-120000`, P1-1); before that it
  was hand-maintained with no template source. It ships via the `adapter-claude` pack with
  `merge_strategy: replace`, the same class as `AGENTS.md`, and carries the same out-of-band
  re-render hazard described below.
- `.github/agents/**`, `.github/instructions/**`, `.github/prompts/**`, `.github/skills/**`, `.github/workflows/**`
- `.opencode/agents/**`, `.opencode/commands/**`, `.opencode/skills/**`
- their render sources under `packages/ai-universal-rules/templates/**`

Canonical policy lives in `docs/ai/**` and repository code. Per `docs/ai/source-of-truth.md`, adapter files are lower authority than canonical docs; when they disagree, the canonical doc wins.

## Out-Of-Band Local Additions (Non-Kit-Managed)

A separately installed tool may append content directly to a rendered adapter surface
outside this kit's render pipeline. The current example: the `graphify` skill's own
installer appends a `## graphify` section to `AGENTS.md` and `CLAUDE.md` directly; this
section does not exist in `AGENTS.template.md` and is not part of any pack entry.

Rules for this class of addition:

- It is not an adapter-drift bug to be "fixed" by deleting it — it is intentional,
  third-party, install-time content. `validate-adapter-drift.php`'s content-parity
  gap (see below) will not and should not flag it as a required-reference violation.
- It is a real regeneration hazard for `AGENTS.md` and, as of the Claude adapter parity
  plan (`docs/tickets/arch-todo-claude-code-adapter-parity-20260704-120000`, P1-1),
  `CLAUDE.md` too: the pack registry renders both with `merge_strategy: replace`, so a
  future full re-render from `AGENTS.template.md` or `CLAUDE.template.md` (for example
  after a template edit followed by `php tools/ai/ai.php install --apply`) will silently
  overwrite either file's out-of-band section. Before running a full re-render of either
  file, check for a trailing `## graphify` (or similar out-of-band) section and re-apply
  it afterward, or re-run that tool's own installer.
- Do not bake tool-specific, existence-conditional content (for example "this project
  has a knowledge graph at `graphify-out/`") into the universal `AGENTS.template.md`
  source — that would assert a false claim on every other install target that does not
  have the tool installed.

## Contract Rules

- Keep adapters thin: short routing and tool/permission posture, not duplicated long procedure.
- Point adapters back to canonical `docs/ai/**` docs and capabilities.
- Do not introduce policy in an adapter that contradicts canonical docs.
- When a runtime cannot support a feature, document the fallback instead of implying parity.
- Kit-managed adapters are re-rendered from `packages/ai-universal-rules/templates/**`; edit the template (and keep any committed render in sync), not only the rendered copy. See `docs/ai/source-of-truth.md` for editable-vs-generated classification.
- When a change thins, relocates, or merges a shipped surface across runtimes, review
  it with the semantic-parity methodology in `docs/ai/integration-matrix.md`
  ("Semantic-Parity Review Methodology") — compare topic-level coverage, not file
  structure, since Copilot/OpenCode/Claude load surfaces through different
  mechanisms by design.
- Handoffs follow `docs/ai/integration-matrix.md` ("Handoff Mechanism Per Runtime"):
  the prose "Recommended next step" sentence from `docs/ai/handoff-contract.md` is
  the mandatory baseline on every runtime; Copilot's structured `handoffs:`
  frontmatter (where used) is additive on top of it, never a replacement.

## Two Different "Drift" Concepts

These are distinct and must not be conflated:

- Adapter drift (this contract): enforced by `tools/ai/validate-adapter-drift.php`. It checks adapter surfaces for required canonical references, oversize bodies, and non-agnostic literals. It does not yet do full content parity against template sources; treat that as a known limitation, not a guarantee.
- Advisor scorecard drift: produced by `tools/ai/advisor/drift.php` into `docs/ai/generated/advisor-drift.md`. It reports per-area advisor score deltas versus a saved baseline. It is unrelated to adapter/template parity.

## Verification

- `php tools/ai/validate-adapter-drift.php` — adapter surface checks (`--fail-on-warn` to gate, `--changed-only` for diff scope).
- See `docs/ai/validation.md` for the full validator set.
