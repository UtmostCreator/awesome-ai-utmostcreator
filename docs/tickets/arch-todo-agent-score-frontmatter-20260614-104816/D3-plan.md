# Plan D3 — Per-Agent agent_assessment Values (split D3a/D3b/D3c)

- Status: **D3a IMPLEMENTED (draft source, approval pending)** · D3b/D3c NOT started
- Risk: D3a low · D3b medium-high (renderer + generated surfaces) · D3c medium
- Dependency: D1 + D2 landed (schema/validator + renderer scaffolding).

## Decision (recorded)

Split D3 into three independently-shippable phases. Do D3a now; do NOT do
D3b/D3c yet (they touch generated surfaces and/or invent numbers).

```text
D3a: decision-only source + source-file schema + validator   <- DONE (draft)
D3b: render approved values into templates/generated surfaces <- gated on human approval
D3c: optional numeric scoring later                          <- gated on a numeric rubric
```

Hard rules (carry verbatim):

- Do NOT add numeric fields now.
- Do NOT render `rationale` (source-only).
- Do NOT use generated display names as source keys (use canonical template keys).
- Do NOT treat draft ratings as approved data.

## Inputs verified (live repo, approved search tools)

- Canonical agent TEMPLATE keys = 24 (13 core + 11 optional), via
  `ai-search.sh files .md packages/ai-universal-rules/templates/{core,optional}/agents`.
  Keys: agent-creator, agent-creator-{runtime-guardian,semantic-verifier,static-validator,supervisor},
  architect, architecture-plan-writer, bootstrapper, bugfix, build-config,
  config-maintainer, docs, implementer, infra-auditor, post-install, refactorer,
  release-auditor, repository-researcher, repository-reviewer, researcher, reviewer,
  ui-builder, upgrade, workflow-auditor.
- `risk_level` is sourced from the EXISTING `docs/ai/AGENTS-MANIFEST.md` Risk column
  (same `low|medium|high|critical` vocabulary — identity mapping, NOT invented).
- Note: the draft decision table provided by the human omitted `bootstrapper` and
  `ui-builder` and included `super-implementer` (which is an OpenCode-only RENDERED
  agent, not a template). The source file is keyed by template, so it carries
  `bootstrapper` + `ui-builder` (flagged REVIEW NEEDED) and excludes `super-implementer`.

## Corrections applied (from review)

1. Used approved search tools (`ai-search.sh files`), not raw `find`.
2. D3a requires only `agent_key`, `template_path`, `risk_level`, `decision`,
   `rationale`. Deep provider-capability mismatch detection deferred to D3b.
3. Approval gate is an explicit `approved: <bool>` flag. While `approved: false`,
   renderers MUST NOT consume the source; the validator reports DRAFT but does not
   fail (a draft is a valid draft).
4. D3b mission/tool-boundary mismatch (AC-D3b-07) is reframed: known mismatches are
   either fixed or explicitly marked `needs_refactor` in the source — no required
   semantic analysis in v1.
5. Manifest-Risk ⇄ `risk_level` mapping is IDENTITY (same vocabulary), verified
   against `AGENTS-MANIFEST.md`; no translation table needed for v1.

## D3a — source file + source-file schema + validator (DONE, draft)

- [x] `schemas/ai/agent-assessment-values.schema.json` — wrapper-file schema
      (`schema`, `approved`, `agents` map; categorical-only; additionalProperties:false).
- [x] `docs/ai/agent-scores.yaml` — 24 entries, canonical template keys,
      `risk_level` (from manifest) + `decision` (draft) + `rationale`, `approved: false`.
- [x] `tools/ai/validate-agent-assessment-values.php` — restricted-subset parser
      (no YAML ext, matching `validate-script-access.php`); enforces 1:1 template
      coverage (no missing/stale), enum validity, no-numeric-in-v1, non-empty
      rationale; reports DRAFT vs APPROVED.
- [x] `tests/php/AgentAssessmentValuesValidatorTest.php` — 8 tests (schema shape,
      live pass+draft, full coverage, missing entry, stale/display-name key,
      numeric rejection, missing rationale, bad enums).
- [x] Wired into `scripts/ai/ai-doc-check.sh` and `just verify-surface`.

D3a acceptance (met):

- [x] `docs/ai/agent-scores.yaml` exists, validates, uses canonical template keys.
- [x] Every live agent template has exactly one entry; no stale entries.
- [x] Every entry has `risk_level`, `decision`, non-empty `rationale`.
- [x] No numeric field present.
- [x] No generated display name used as a source key.
- [x] Source is marked draft (`approved: false`) and NOT consumed by any renderer yet.

## D3a → D3b gate (human action required)

D3b starts only after a human:

1. Reviews the categorical values in `docs/ai/agent-scores.yaml` (especially the
   REVIEW-NEEDED `bootstrapper`/`ui-builder` and every `needs_refactor`).
2. Flips `approved: true` in the source file.
3. Approves the installer re-render (`php tools/ai/ai.php install ... --apply`),
   which mutates generated agent surfaces (approval-gated generated-artifact work).

## D3b — render approved values (NOT started; gated)

- [ ] Renderer reads `docs/ai/agent-scores.yaml`; refuses when `approved: false`.
- [ ] Renderer copies ONLY fields allowed by `agent-assessment.schema.json`
      (`risk_level`, `decision`); `rationale` stays source-only.
- [ ] Drift check: source ⇄ template/rendered ⇄ manifest `Risk` agree (identity map).
- [ ] AC-D3b-07 (reframed): known mission/tool-boundary mismatches are fixed or
      marked `needs_refactor` in source — no required semantic analysis.
- [ ] Re-render via approved installer; validators + `composer test:fast` pass.

## D3c — optional numeric scoring (NOT started; gated)

- [ ] Document a numeric rubric + thresholds BEFORE adding any numeric field.
- [ ] Numeric values are human-approved, never automation-generated.
- [ ] Enable via `--numeric-enabled`; validate against `agent-assessment.schema.json`.

## Verification (D3a, run)

- `php tools/ai/validate-agent-assessment-values.php --root=.` → OK (24 entries, DRAFT)
- `php tools/ai/validate-schemas.php --root=.` → OK (17 schemas)
- `vendor/bin/phpunit --filter AgentAssessmentValuesValidatorTest` → OK (8 tests)
