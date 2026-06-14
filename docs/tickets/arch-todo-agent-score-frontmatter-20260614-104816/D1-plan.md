# Plan D1 — Optional agent_assessment Rubric Schema + Pilot

- Status: Todo (implementable now)
- Risk: low (additive optional schema; no behavior change; no mass re-render)
- Dependency: Plan A committed (satisfied)
- Supersedes original Plan D items 35a (schema part)

## Goal

Define the frozen `agent_assessment` rubric as an OPTIONAL, unenforced contract:
a JSON schema describing the fields/ranges, validation that only triggers when the
block is present, and one pilot agent template carrying the block as a documented
example with placeholder/`unknown` markers (no invented scores).

## Frozen rubric fields (definitions only; ranges per archived todo-agents-rework.md)

- `score` 0-100, `confidence` 0-100
- `role_clarity` `scope_control` `permission_safety` `output_contract`
  `evidence_required` `verification_strength` 0-15 each
- `handoff_quality` 0-10
- `risk_level` enum: low | medium | high | critical
- `decision` enum: approve | approve_with_minor_fixes | needs_refactor | block

## Steps

1. Add `schemas/ai/agent-assessment.schema.json` — all fields OPTIONAL, ranges/enums
   as above, `additionalProperties: false`.
2. Add validation in the doc-check pipeline (or a small validator) that, FOR EACH
   agent template/rendered file containing an `agent_assessment:` frontmatter block,
   validates it against the schema; absence is valid.
3. Add the block to ONE pilot template (`packages/ai-universal-rules/templates/core/agents/architect.md`)
   with `unknown`/placeholder values so it is a structural example, never invented data.
4. Add a focused test proving: (a) schema is well-formed/addressable; (b) a present
   block validates; (c) a malformed block fails; (d) absence passes.

## Out of scope / avoid

- No per-agent VALUES for the other agents (that is D3, human-gated).
- No Copilot renderer change (that is D2).
- No installer re-render.
- Do not reintroduce the two-number design_readiness/execution_trust scheme.

## Acceptance criteria

- [ ] Schema exists, well-formed, registered with the schema validator.
- [ ] Validation passes whether or not a rubric block is present.
- [ ] Pilot template carries a documented placeholder block (no invented numbers).
- [ ] `risk_level` (when present) is checkable against manifest `Risk` (AC-04 rewrite).
- [ ] Focused test green; `validate-schemas` and doc-check green.

## Verification

- `php tools/ai/sh-introspect`-independent: `bash scripts/ai/ai-doc-check.sh --check`
- `vendor/bin/phpunit --filter <new test>`
