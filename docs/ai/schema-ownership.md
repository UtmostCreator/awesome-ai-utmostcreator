# Schema Ownership

Every JSON Schema under `schemas/ai/` is a shipped contract. This table records, for each
schema, what produces data for it, what consumes/points at it, and what validates it. A schema
with no producer, no consumer, and no validator is an unwired stub and should be wired or removed.

Integrity of this directory is enforced by `tools/ai/validate-schemas.php` (wired into
`scripts/ai/ai-doc-check.sh`), which fails if any schema is malformed or missing `$schema`,
`$id`, or `title`.

## Status meanings

- `active-runtime-contract` — a tool/test reads or enforces it at runtime.
- `active-generated-contract` — a generated/shipped data file points at it via `$schema`.
- `public-doc-contract` — documented as a supported shape; addressable via `$id`, no runtime consumer yet.

## Ownership table

| Schema | Producer | Consumer / Referenced by | Validator | Status |
| --- | --- | --- | --- | --- |
| `ai-catalog.schema.json` | `tools/ai/ai_catalog_lib.php` (emits `$schema`) | `packages/ai-universal-rules/catalog.json` | `validate-ai-catalog.php`, `validate-schemas.php` | active-generated-contract |
| `ai-command-policy.schema.json` | command-policy compiler | `policies/ai/policy.yaml` shape | `validate-ai-config.php`, `validate-schemas.php` | active-runtime-contract |
| `ai-file-standards.schema.json` | `generate-ai-file-standards.php` | `packages/ai-universal-rules/policies/ai-file-standards.json` (`$schema`) | `validate-schemas.php` | active-generated-contract |
| `ai-universal-rules-manifest.schema.json` | export pipeline | `packages/ai-universal-rules/manifest.json` (`$schema`) | `validate-schemas.php` | active-generated-contract |
| `evidence-event.schema.json` | evidence/observability writers | `validate-ai-config.php`, `validate-ai-catalog.php`, `ai_catalog_lib.php` | `validate-ai-config.php`, `validate-schemas.php` | active-runtime-contract |
| `agent-spec.schema.json` | agent authoring | `validate-agent-spec.php` (hardcoded rules), agent-creator prompt | `validate-schemas.php` | public-doc-contract |
| `ai-install-manifest.schema.json` | installer manifest writer | `tests/php/OwnershipClassesTest.php` (structural read) | `validate-schemas.php` | active-runtime-contract |
| `ai-manifest-lock.schema.json` | installer lock writer | `tests/php/OwnershipClassesTest.php` (structural read) | `validate-schemas.php` | active-runtime-contract |
| `advisor-recommendation.schema.json` | advisor output | `tests/php/AdvisorSchemaTest.php` (existence) | `validate-schemas.php` | public-doc-contract |
| `project-scorecard.schema.json` | advisor scorer | `tests/php/AdvisorSchemaTest.php` (existence) | `validate-schemas.php` | public-doc-contract |
| `project-signals.schema.json` | advisor scanner | `tests/php/AdvisorSchemaTest.php` (existence) | `validate-schemas.php` | public-doc-contract |
| `project-placeholders.schema.json` | placeholder registry | `PLACEHOLDERS.md` | `validate-schemas.php` | public-doc-contract |
| `ai-handoff.schema.json` | agent handoff output | `docs/ai/handoff-contract.md` (shape) | `validate-schemas.php` | public-doc-contract |
| `generated-artifacts.schema.json` | generated-artifacts policy | `docs/ai/generated-artifacts.md` (shape) | `validate-schemas.php` | public-doc-contract |
| `verification-matrix.schema.json` | verification matrix authoring | `docs/ai/verification-matrix.md` (shape) | `validate-schemas.php` | public-doc-contract |

## Adding a schema

1. Add `schemas/ai/<name>.schema.json` with `$schema`, a unique `$id`
   (`https://app-configs.local/schemas/<name>.schema.json`), and a `title`.
2. Ship it via `tools/ai/install/packs.php` and list it in `docs/ai/installed-files.md`.
3. Add a row to this table naming its producer, consumer, validator, and status.
4. Run `php tools/ai/validate-schemas.php --root=.` and `bash scripts/ai/ai-doc-check.sh --check`.

## Removing a schema

Only remove a `public-doc-contract` schema with no producer/consumer. Remove its file, its
`packs.php` entry, its `installed-files.md` line, its catalog entry (if any), and its row here in
the same change. Re-run the validators above.
