# Registry Projection & Generated Surface — Phase 3 Plan

Extends `./plan.md` (Phase 1, locked + DONE) and `./plan-phase2-scripts-migration.md`
(Phase 2, mostly DONE). Do **NOT** author a parallel ticket: this reuses the same
registry/gateway surface (~85% overlap) and the same locked decisions.

- Source proposals: `todo-script-regestry-ref.md` (repo root) + the architect-review
  follow-up that reframed it as "projection, not enrichment".
- Architecture review completed 2026-06-13 against current source (line-cited below).
- Risk posture: **mostly low/additive** (P3.0-P3.3, P3.5 generators + tests). P3.4
  (effects-gated permission deltas) is medium and routes through release-auditor.

## Why this plan exists (and what it corrects)

The `todo-script-regestry-ref.md` proposal scored well on instinct but was built on
**false premises about the current code**. A prior reviewer pass and this architect pass
both confirmed, from source, that most of what it asks for is either already shipped or
already rejected with evidence in Phases 1-2. This plan keeps only the genuinely-new,
not-yet-covered work and frames it correctly as **projection from the PHP source**, not
manual enrichment of the JSON mirror.

### Premise corrections (verified against source)

| Proposal premise | Verdict | Evidence |
| --- | --- | --- |
| Profiles should be `read/context/verify/edit/admin` | **REJECTED** (locked Decision 1) | `script-registry.php:537-552` ships `readonly/verify/impl`; "no new scale" decision at `:530-533`. |
| Make `id/tier/required_tools/approval/...` all `required` in schema | **REJECTED as written** | `tier` is intentionally optional on tier-0 wrappers (`ScriptRegistryInvariantTest`); `autonomy_level`/`profiles`/`requires_approval` are **derived**, not stored (`script-registry.php:491-516,578-615`); `approval` (allow/ask/deny) does not exist in PHP at all. |
| Hand-populate ~40 JSON entries with rich fields | **REPLACE with generator** | No PHP→JSON generator exists; JSON ships static via `packs.php:197` (`merge_strategy:replace`); only a 4-field parity check exists (`validate-install-surface.php:581`). Hand-editing is the drift the proposal itself warns against. |
| Add per-entry `gateway` run strings | **REJECTED** (redundant) | Uniform shape already implemented: `php tools/ai/ai.php tool:run <id>` (`ai.php:255`, `install_extras.php:580`). Per-entry strings only add drift. |
| Add `mode_required_tools` as P0-critical | **ALREADY REJECTED** (Phase 2) | 2-class model shipped (`required_tools`+`optional_tools`); script-side per-mode guards are the fail-closed authority (`60-guards.sh:35-41`, `85-backend-ast.sh:18-19`, `65-backend-files.sh:27`). See Phase 2 "rejected". |
| scripts-reference.md says "run scripts directly" | **FALSE** | It already says "Look up the script in `docs/ai/script-registry.json` before running it" (`scripts-reference.md:7`) and gates mutating scripts (`:11`). |
| Gateway `tool:run/list/describe` are aspirational | **FALSE** | Implemented + tested (`ai.php:251-255`, `install_extras.php:519/547/580`, `tests/php/ToolGatewayTest.php`). |
| Add standardized reason codes | **PARTLY EXISTS, worth finishing** | `approval_required` status shipped (`install_extras.php:625`); but other failures use ad-hoc strings (`:524,562,608,614`). A shared vocabulary is genuinely new value (already requested by Phase 2 P2b acceptance, `plan-phase2:191`). |

### Carry-forward locked decisions (unchanged)
1. Reuse existing taxonomies: `risk` (read-only|mutating) + `tier0..4` + `autonomy_level`.
2. `tools/ai/install/script-registry.php` is canonical; no `tool-registry.json`.
3. `aiRunScriptById` is the `tool:run` engine.
4. Single `aiInstallerAgentProfiles()` agent→profile map; profiles **derived**, not stored.

## Target architecture (projection-first)

```text
tools/ai/install/script-registry.php        canonical source + derivation helpers
        |  (already has: aiInstallerScriptProfiles / RequiresApproval / InferAutonomy)
        v
aiToolGatewayDescriptor()  (install_extras.php:478)   <- ALREADY a mini-normalizer
        |  extend into a single shared normalizer used by gateway AND exporter
        v
php tools/ai/ai.php registry:export [--format=json] [--check PATH]   [NEW]
        |
        v
docs/ai/script-registry.json    generated mirror (validators + adapters + installed projects)
docs/ai/scripts-reference.md     prose hand-written + GENERATED table block
```

Key rule (the whole point of Phase 3): **derived fields live in one PHP normalizer; JSON
and docs are generated projections, never the authority.**

## Slices (only genuinely-new work)

### P3.0 — Single normalizer (extend, do not create new file)  `[NEW]`  ★ first
- **What:** Promote `aiToolGatewayDescriptor()` (`install_extras.php:478-490`) — which
  already derives `profiles`, `requires_approval`, normalizes `required_tools` — into the
  one normalized-entry producer reused by the gateway *and* the exporter. Add the remaining
  projected fields (`tier`, `autonomy_level`, `mutates_state`, `optional_tools`,
  `writes_paths`) by reading existing static/derived PHP data. Do **not** create a separate
  `script-registry-normalizer.php` unless the function outgrows its file.
- **Files:** `tools/ai/commands/install_extras.php` (extend descriptor) or a small helper in
  `tools/ai/install/script-registry.php`; reuse `aiInstallerScriptProfiles`/`RequiresApproval`/`InferScriptAutonomyLevel`.
- **Risk:** low (additive; gateway output gains fields). **Rollback:** revert helper.
- **Acceptance:**
  - [ ] one function returns a normalized entry; gateway `tool:describe` and the exporter both call it.
  - [ ] derived fields are computed, never hand-stored.
  - [ ] `ToolGatewayTest` stays green (assert new fields present).

### P3.1 — `registry:export` generator + `--check`  `[NEW]`  ★ the maintainability win
- **What:** `php tools/ai/ai.php registry:export --format=json [--output PATH]` writes a
  deterministic, stable-key-ordered JSON projection of the normalized registry.
  `--check PATH` exits non-zero if `PATH` differs from freshly-generated output (CI drift gate).
- **Files:** `tools/ai/ai.php` (1 switch case + usage), `tools/ai/commands/` (handler reusing
  P3.0 normalizer + existing `aiCliWriteArtifact` envelope style).
- **Constraint:** `validate-install-surface.php:325-328` requires `script-registry.json` to
  exist — the generator must keep it present and parity-valid, not remove it.
- **Risk:** low. **Rollback:** revert the subcommand; JSON stays hand-synced as today.
- **Acceptance:**
  - [ ] generated JSON is byte-stable across runs (stable key order, fixed pretty-print).
  - [ ] current `docs/ai/script-registry.json` regenerates to an equal or richer file.
  - [ ] `registry:export --check docs/ai/script-registry.json` passes in CI; edited JSON fails it.

### P3.2 — Schema tightening around the generated projection  `[NEW]`
- **What:** In `docs/ai/script-registry.schema.json`: narrow `risk` enum to
  `["read-only","mutating"]` (drop low/medium/high — never emitted); add **optional** `tier`
  enum `tier0..tier4`; add `profiles` (items enum `readonly|verify|impl`), `required_tools`,
  `optional_tools`, `mutates_state`, `requires_approval` as **declared properties**. Keep
  `additionalProperties` loose first; flip to `unevaluatedProperties:true` only after the full
  field list lands; defer `false` until P3.1 guarantees the field set.
- **Do NOT** make derived fields `required` on canonical PHP entries. They may be `required`
  on **generated JSON** only after P3.1 exists.
- **Files:** `docs/ai/script-registry.schema.json`.
- **Risk:** low (a static file + the generated output validate against it). **Rollback:** revert schema.
- **Acceptance:**
  - [ ] schema validates the P3.1 output; `tier` stays optional; `risk` no longer allows low/medium/high.

### P3.3 — Parity + invariant tests for the projection  `[NEW]`
- **What:** Extend `ScriptRegistryInvariantTest` (or add a golden-fixture test) to assert the
  PHP→JSON projection round-trips: every PHP id appears in generated JSON; generated JSON has
  no extra ids; mutating ⇒ `requires_approval=true`; `act_with_approval` ⇒ not auto-allow;
  read-only ⇒ `mutates_state=false`; `required_tools` from an approved vocabulary; every
  generated entry has `profiles` and `autonomy_level`. Add a golden fixture
  (`tests/fixtures/script-registry.generated.json`) compared to `registry:export` output.
- **Files:** `tests/php/ScriptRegistryInvariantTest.php` (+ fixture).
- **Risk:** low. **Rollback:** delete added cases/fixture.
- **Acceptance:** [ ] drift in metadata or permissions visibility fails a test deterministically.

### P3.4 — `effects` projection + invariants  `[NEW, medium]`  route through release-auditor
- **What:** Project an `effects` object (`filesystem: none|evidence_write|generated_write|
  mutation`, `network: none|read|write`, `process: bounded|long_running`,
  `requires_clean_tree`, reuse existing `writes_paths`/`reads_secret_values`/`bounded_output`).
  This refines the coarse read-only/mutating split so "read-only but writes `.ai-logs`" is
  declared, not hidden. **Reuse existing fields** (`writes_paths` already on richer entries) —
  do not add a parallel `outputs.paths`.
- **Invariants:** read-only may write only declared evidence/generated paths; mutating ⇒ approval;
  `long_running` ⇒ ask-gated; `network!=none` ⇒ documented + profile-gated;
  `reads_secret_values` ⇒ deny/ask with reason.
- **Why medium:** if any invariant changes a profile's allow set, that is a permission-posture
  change → release-auditor + the OQ-1 gating already governing Phase 2 P2b.
- **Files:** P3.0 normalizer; `script-registry.php` (populate effects where missing); tests.
- **Risk:** medium (posture-adjacent). **Rollback:** revert effects projection + invariants.
- **Acceptance:** [ ] effects declared per entry; [ ] invariants enforced by test; [ ] no silent
  permission widening (any allow-set change called out for release-auditor).

### P3.5 — Generated table block in `scripts-reference.md`  `[NEW, low]`
- **What:** Keep prose hand-written; generate only the script table between
  `<!-- GENERATED:SCRIPT_TABLE_START -->` / `<!-- GENERATED:SCRIPT_TABLE_END -->` markers from
  the normalized registry, via a `--check` mode mirroring P3.1. Replaces the manual 40-row
  table (`scripts-reference.md:18-58`) that `validateScriptsReferenceCoverage()` only
  loosely guards (basename-present check, `validate-install-surface.php:604-626`).
- **Files:** `scripts/`-or-`tools/ai/` generator; `docs/ai/scripts-reference.md`.
- **Risk:** low. **Rollback:** revert to manual table.
- **Acceptance:** [ ] prose stays editable; [ ] table regenerates + `--check` gates drift;
  [ ] missing/extra ids fail validation.

### P3.6 — Standardized gateway reason-code vocabulary  `[NEW, low]`  (satisfies Phase 2 P2b dep)
- **What:** Replace ad-hoc gateway error strings (`install_extras.php:524,562,596,608,614`)
  with a fixed enum: `unknown_id`, `unknown_profile`, `profile_mismatch`,
  `missing_required_tool`, `approval_required`, `mutating_requires_apply`, `unsupported_mode`,
  `external_directory_blocked`, `secret_path_blocked`, `timeout`. Always include
  `safe_next_step`. Makes the "stop, do not retry" agent contract machine-readable and
  unblocks Phase 2 P2b acceptance (`plan-phase2:190-191`).
- **Files:** `tools/ai/commands/install_extras.php`; `tests/php/ToolGatewayTest.php`.
- **Risk:** low (output-shape; exit codes unchanged). **Rollback:** revert to string messages.
- **Acceptance:** [ ] every gateway non-ok path emits a `reason` from the enum + `safe_next_step`;
  [ ] `ToolGatewayTest` asserts the codes.

## Explicitly deferred / rejected (do not pull forward)
- **Capability-ID rename** (`repo.search` vs `ai-search`) — DEFER (Phase 2 rejected; churns the
  reused registry key surface). Revisit only with an adapter-layer change.
- **Adapter allowlist generation from registry** — that is Phase 2 **P2c**, bound to **P2b**,
  blocked on **OQ-1**. Do not duplicate it here.
- **New profile taxonomy / 6-value enum / per-entry gateway strings / Symfony rewrite /
  folder moves** — rejected in Phases 1-2 with evidence.
- **`additionalProperties:false`** — only after P3.1+P3.2 guarantee the full field set.

## Sequencing
```text
P3.0 normalizer -> P3.1 exporter+check -> P3.2 schema -> P3.3 parity tests
   -> P3.5 generated doc table (low risk, parallel-able)
   -> P3.6 reason codes (low risk; unblocks Phase 2 P2b)
   -> P3.4 effects (medium; release-auditor; after the projection is stable)
```

## Rollback posture
All slices are additive projections over an unchanged canonical source. P3.0-P3.3/P3.5/P3.6
rollback = revert the generator/test/schema edits; the JSON reverts to today's hand-synced
state with no behavior change. P3.4 is the only posture-adjacent slice and ships only after
release-auditor review and confirmation that no profile allow-set silently widens.

## Success signal
- `registry:export --check` is green in CI and fails on any hand-edit of the JSON/doc table.
- `ToolGatewayTest` + `ScriptRegistryInvariantTest` cover projection + reason codes and stay green.
- No new profile names, no new top-level registry file, no per-entry gateway strings introduced.

## Evidence-quality flags
- STRONG (verified from source): no exporter/normalizer/doc-markers exist; gateway + descriptor
  shape; profile derivation; schema risk enum looseness; JSON ships static via packs.
- MEDIUM: exact effort to retrofit `effects` on every entry (some richer entries already carry
  `writes_paths`/`reads_secret_values`/`bounded_output`; tier-0 wrappers do not).
- DEFERRED to live test: any P3.4 invariant that would change an allow set (same OQ-1 gate as P2b).

## Recommended next step
Hand **P3.0 → P3.1 → P3.2 → P3.3** to `implementer` (additive, pure-PHP, test-backed). Add
**P3.5 / P3.6** in the same lane. Route **P3.4** through `release-auditor` before merge.
Keep Phase 2 **P2b/P2c** parked on OQ-1 as already decided.

## Implementation status (2026-06-13)

Shipped this session (all additive, pure-PHP + docs/schema, no permission-posture change):

- **P3.0 DONE** — `aiInstallerNormalizeScriptEntry()` + `aiInstallerNormalizedScriptRegistry()`
  + `aiInstallerRenderScriptRegistryJson()` in `tools/ai/install/script-registry.php`.
  `aiToolGatewayDescriptor()` now delegates to the shared normalizer (`install_extras.php`).
- **P3.1 DONE** — `registry:export [--output PATH] [--check [PATH]]` subcommand
  (`ai.php` dispatch + usage; `aiRunRegistryExportCommand` in `install_extras.php`).
  `docs/ai/script-registry.json` regenerated as the projection (40 entries, +900 lines);
  `--check` is a deterministic CI drift gate (verified exit 1 on drift, 0 when synced).
- **P3.2 DONE** — `docs/ai/script-registry.schema.json`: `risk` enum → `[read-only,mutating]`;
  added optional `tier` enum `tier0..4`; declared `profiles` (enum readonly/verify/impl),
  `required_tools`, `optional_tools`, `writes_paths`, `introspection`. Generated JSON validates.
- **P3.3 DONE** — new `tests/php/RegistryProjectionTest.php` (10 tests): committed JSON == generated
  projection, id parity, determinism, and projection invariants (mutating⇒approval⇒impl-only,
  read-only⇒no mutate, act_with_approval⇒approval, tier/risk vocab).
- **P3.6 DONE** — `aiToolGatewayReasonCodes()` + `aiToolGatewayReasonPayload()`; all gateway
  non-ok paths now emit a `reason` from the fixed vocabulary + `safe_next_step` (exit codes
  unchanged). `missing_required_tool` reason added to `aiRunScriptById` precheck. Covered by
  a new `ToolGatewayTest` case. This satisfies the Phase 2 P2b dependency (`plan-phase2:190-191`).

Deferred (with rationale):

- **P3.5 DEFERRED** — generating the `scripts-reference.md` table would drop the hand-written
  When/Why/Produces/Help prose (the registry has no equivalent fields). Needs an architect
  decision on adding prose fields to the registry first. Not shipped to avoid destroying docs.
- **P3.4 DEFERRED** — `effects` projection is medium-risk/posture-adjacent; route through
  release-auditor as planned. Not started this session.

Verification (this session):
- `RegistryProjectionTest` + `ScriptRegistryInvariantTest` + `ToolGatewayTest`: 34/34, 920 assertions.
- `composer test:fast`: 692/692 (6 env-skipped), no regressions.
- `php tools/ai/validate-install-surface.php`: passed (4-field JSON parity green; WARNs pre-existing
  and unrelated to this slice).
- `php tools/ai/validate-command-policy.php`: exit 0 (pre-existing minor metadata warnings unchanged).
- `registry:export --check` exit 0; `tool:run ai-edit` exit 2 (approval); `tool:describe nope` exit 1.

Files touched: `tools/ai/install/script-registry.php`, `tools/ai/commands/install_extras.php`,
`tools/ai/ai.php`, `docs/ai/script-registry.schema.json`, `docs/ai/script-registry.json` (generated),
`tests/php/RegistryProjectionTest.php` (new), `tests/php/ToolGatewayTest.php`.
