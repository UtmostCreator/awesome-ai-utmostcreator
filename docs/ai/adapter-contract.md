# Adapter Contract

Adapters for Copilot, OpenCode, and Claude must preserve canonical rules from `docs/ai/` and avoid introducing conflicting policy.

## Adapter Surfaces

These files are runtime adapters, not canonical policy:

- `AGENTS.md`, `CLAUDE.md`, `.github/copilot-instructions.md`
- `.github/agents/**`, `.github/instructions/**`, `.github/prompts/**`, `.github/skills/**`, `.github/workflows/**`
- `.opencode/agents/**`, `.opencode/commands/**`, `.opencode/skills/**`
- their render sources under `packages/ai-universal-rules/templates/**`

Canonical policy lives in `docs/ai/**` and repository code. Per `docs/ai/source-of-truth.md`, adapter files are lower authority than canonical docs; when they disagree, the canonical doc wins.

## Contract Rules

- Keep adapters thin: short routing and tool/permission posture, not duplicated long procedure.
- Point adapters back to canonical `docs/ai/**` docs and capabilities.
- Do not introduce policy in an adapter that contradicts canonical docs.
- When a runtime cannot support a feature, document the fallback instead of implying parity.
- Kit-managed adapters are re-rendered from `packages/ai-universal-rules/templates/**`; edit the template (and keep any committed render in sync), not only the rendered copy. See `docs/ai/source-of-truth.md` for editable-vs-generated classification.

## Two Different "Drift" Concepts

These are distinct and must not be conflated:

- Adapter drift (this contract): enforced by `tools/ai/validate-adapter-drift.php`. It checks adapter surfaces for required canonical references, oversize bodies, and non-agnostic literals. It does not yet do full content parity against template sources; treat that as a known limitation, not a guarantee.
- Advisor scorecard drift: produced by `tools/ai/advisor/drift.php` into `docs/ai/generated/advisor-drift.md`. It reports per-area advisor score deltas versus a saved baseline. It is unrelated to adapter/template parity.

## Verification

- `php tools/ai/validate-adapter-drift.php` — adapter surface checks (`--fail-on-warn` to gate, `--changed-only` for diff scope).
- See `docs/ai/validation.md` for the full validator set.
