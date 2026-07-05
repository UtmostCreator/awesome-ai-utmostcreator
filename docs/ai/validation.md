# Validation

Run `php tools/ai/validate-install-surface.php --strict`, `php tools/ai/validate-ai-config.php`, and focused script/PHP checks after installer changes.

## Per-Slice Validator Gate

Validators are the only structural drift protection between template sources and
rendered surfaces (there are no automatic template-to-output edges). Run this gate
after every slice that touches shipped templates or shipped canonical docs:

```bash
php tools/ai/validate-install-surface.php
php tools/ai/validate-adapter-drift.php --fail-on-warn
php tools/ai/validate-ai-config.php
php tools/ai/validate-ai-catalog.php
bash scripts/ai/ai-doc-check.sh --check
```

Separate pre-existing failures from failures introduced by the current change;
report both classes, but only new failures block the slice.

## Permanent Maintenance Loop

Every kit change follows the same repeatable cycle, not only this program's slices:

1. **Change the template source** — never the rendered output (see Change-Type
   Routing below).
2. **Re-prove coverage** — for any topic in `docs/ai/integration-matrix.md`'s
   Critical-Topic Coverage Matrix, confirm the row's `Status` still holds; when the
   change thins, relocates, or merges a surface across runtimes, use the
   Semantic-Parity Review Methodology there instead of comparing file structure.
3. **Re-prove reachability** — see Dead-File Review Model below. A slice that adds a
   shipped file must name its load path; a slice that removes one must show it is
   dead.
4. **Re-render or hand-sync** — re-render via the installer where safe; where a full
   re-render would touch unrelated owned-modified files (the recurring blast-radius
   risk seen throughout this program), hand-edit the rendered copy to match the
   template byte-for-byte and verify with `diff`.
5. **Run the validator gate** — the per-slice gate above. Separate pre-existing
   failures from new ones; only new failures block the slice.

### Dead-File Review Model

Run this whenever a shipped file's reachability is in question, not only during a
planned thinning slice:

- A file counts as dead per the four Reachability Rules in
  `docs/ai/integration-matrix.md` ("When An Installed File Is Dead").
- Before treating a file as dead, search for it by exact path (not just by name)
  across `docs/ai/**`, `packages/ai-universal-rules/templates/**`, and
  `packages/ai-universal-rules/docs/**` — a reference from a maintainer-facing doc
  still counts as a legitimate, if narrow, reachability path. `templates/capabilities/README.md`
  is the worked example: absent from the pack registry, but genuinely referenced
  from `packages/ai-universal-rules/docs/CAPABILITY-MODEL.md`, so it is
  optional-support, not dead.
- Record the review outcome (kept because reachable via `<path>`, or removed
  because dead by rules 1-3) in the same slice that touches the file, so the
  reasoning is auditable later rather than re-derived.
- Use `graphify update .`'s edge presence as a secondary signal only (Phase 5.5),
  never the sole evidence — it can reflect a stale node-ID scheme.

### Multi-Scope Graph Maintenance (Optional, Graphify-Only)

This only applies where `graphify` is separately installed (per
`docs/ai/adapter-contract.md`'s "Out-Of-Band Local Additions" — it is not part of
this kit's pack registry and must never be assumed present). Run
`graphify update .` after a merged phase so the graph tracks restructured shipped
surfaces. For an installed target with multiple independent scopes (for example a
monorepo with separate app and doc scopes), the incremental sequence is:
`graphify update <scope> --no-cluster` per scope, then `merge-graphs` to combine
them, then a final `cluster-only` pass. Doc and config scopes need semantic
re-extraction (a backend key), not just AST extraction, since their meaningful
content is prose and structure rather than code symbols.

## Change-Type Routing (Maintainers)

Edit the template source, never the rendered output. Route by change type:

| Change type | Edit here (source layer) | Gate with |
|---|---|---|
| Global agent rules / root baseline | `packages/ai-universal-rules/templates/core/AGENTS.template.md` | adapter-drift, install-surface, doc-check |
| Copilot baseline | `templates/core/copilot-instructions.template.md` | adapter-drift, install-surface |
| Path/topic instruction rules | `templates/instructions/*.instructions.md` | adapter-drift, install-surface |
| OpenCode always-on set, permissions, watcher | `templates/core/opencode.json` | install-surface, command-policy |
| Agent roles / boundaries | `templates/core/agents/**`, `templates/optional/agents/**` | agent-spec, script-access, doc-check |
| Skills / commands / workflows | `templates/skills/**`, `templates/commands/**`, `templates/workflows/**` | adapter-drift, doc-check |
| Capabilities | `templates/capabilities/**` | doc-check, context-budgets |
| Shared render snippets | `templates/snippets/**` | adapter-drift, install-surface |
| Hook policy / guardians | `templates/github/hooks/**` | command-policy, shellcheck, install-surface |
| Shipped canonical docs | `docs/ai/**` (only files without a GENERATED header) | doc-check, ai-config |
| Coverage matrix / surface classification | `docs/ai/integration-matrix.md` | doc-check |

Files with a `GENERATED — DO NOT EDIT` header (for example `docs/ai/installed-files.md`,
root `AGENTS.md`) are regenerated by their generator; edit the source and re-render.

### Agent Duty-Vs-Permission Check (F-3)

When adding or changing a shipped agent template under `templates/core/agents/**` or
`templates/optional/agents/**`, confirm every duty stated in the body is actually reachable
under that agent's own `permission` frontmatter, and every `allow`/`ask` grant is actually
used by a stated duty — do not ship a wider permission than the agent's job needs. Two real
defects of this class were found and fixed by a Phase 6.2 audit (see
`docs/ai/agent-script-access.md`'s per-agent Script Access section for the fixed shape):
a body "Do not run broad CI" rule contradicted by an `allow` grant on `ai-test-select.sh`/
`run-repo-tests.sh` in the same agent's frontmatter (`release-auditor.md`), and a load-bearing
`mkdir`/`ls` bash permission rule silently mis-indented relative to its sibling keys in the
same YAML mapping (`architecture-plan-writer.md`). Check both directions — a duty blocked by
a missing/denied permission, and a permission grant unused by any stated duty — before
shipping. If an agent's own boundary makes its stated purpose unreachable, convert it to a
skill instead of shipping a defeated agent (F-5).

## Index Surface Sufficiency (Decision)

The current set of shipped routing surfaces is sufficient; no additional
maintained index surface is needed, and no separate contributor quickstart layer
is needed for shipped docs: `docs/ai/project-context.md` is the primary
entrypoint, `docs/ai/workflow.md` and `docs/ai/capabilities/README.md` route by
task, `docs/ai/agents.md` routes by agent, `docs/ai/integration-matrix.md` plus
`docs/ai/shipped-surface-inventory.md` cover coverage/reachability, and this
file's Change-Type Routing table covers maintainer change routing. README-level
routing for this source repository is explicitly out of scope for this program
under the shipped-files constraint (see the plan's Out Of Scope list); this
decision covers shipped docs only.

## Validator Set

All validators live under `tools/ai/` and exit non-zero on failure:

- `validate-install-surface.php` — install pack, profile, script, and adapter template/script-reference contracts (`--strict`).
- `validate-ai-config.php` — AI config integrity.
- `validate-ai-catalog.php` — catalog contents.
- `validate-catalog-drift.php` — committed `INSTALL-CATALOG.md` matches a fresh render of the pack registry.
- `validate-adapter-drift.php` — adapter surfaces carry required canonical references, are not oversized, and avoid non-agnostic literals (`--fail-on-warn`, `--changed-only`). See `docs/ai/adapter-contract.md`.
- `validate-generated-artifacts.php` — generated artifact shape and schema.
- `validate-schemas.php` — JSON Schema contracts (see `docs/ai/schema-ownership.md`).
- `validate-command-policy.php` — compiled command-policy parity.
- `validate-placeholders.php` — unresolved placeholder detection.
- `validate-stub-surfaces.php`, `validate-mentor-parity.php`, `validate-context-budgets.php`, `validate-agent-spec.php` — focused surface checks.
- `validate-script-access.php` — `.github/ai-script-access.yaml` tier/agent consistency: every root script tiered exactly once, dangerous scripts only in `T5_mutation_recovery`, internal modules never exposed, access-manifest agents exist in `docs/ai/AGENTS-MANIFEST.md`, and only the runtime guardian may use the tool-use hooks.

## `ai-verify.sh` Optional Tiered Checks

Beyond the `Validator Set` PHP tools above, `scripts/ai/ai-verify.sh` itself runs two
tiered, env-tunable checks inline (implementation: `scripts/ai/internal/ai-verify/`):

- **Line-count guardrail** (`30-linecount.sh`, on by default: `VERIFY_LINECOUNT=1`) —
  reports `INFO` at `LINECOUNT_INFO` (default 350 lines), `WARN` at `LINECOUNT_WARN`
  (default 550), and a hard verification failure at `LINECOUNT_ERROR` (default 800).
- **jscpd duplication guardrail** (`35-jscpd.sh`, opt-in: `VERIFY_JSCPD=0` by default) —
  when enabled, reports `WARN` at `JSCPD_WARN_PCT` (default 5% duplicated lines) and
  only hard-fails when `JSCPD_FAIL_PCT` is explicitly set and crossed (empty by
  default, so the check is advisory-only unless a project opts in). Uses a local
  `jscpd` binary if present, else `npx --yes jscpd` — only when `VERIFY_JSCPD=1` is
  explicitly set, never a silent network fetch. Known limitation: jscpd's markdown
  reporter only tokenizes fenced code blocks, not prose, so it measures code
  duplication, not full prose duplication.

## Drift Terminology

- "Adapter drift" = adapter-vs-canonical/template consistency (`validate-adapter-drift.php`).
- "Advisor scorecard drift" = advisor score deltas vs. a baseline (`docs/ai/generated/advisor-drift.md`). These are unrelated.
