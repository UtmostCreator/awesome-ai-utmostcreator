# Todo: Skills & Commands Consolidation (apply agent-skills-commands.yaml v2.8)

- Owner: implementer (canonical templates) + configuration-maintainer (install/render) + reviewer (verify)
- Source spec (desired state): `../../../../handsoff/handoff/agent-skills-commands.yaml` (v2.8, governance repo `handsoff`)
- Status: **Phase 1–2 complete (this doc). Phase 5 execution staged, slice-by-slice, gated.**
- Date: 2026-07-14

## Goal (scoped)

Reduce this repo's skill surface from **35 → 21 canonical skills** and normalize the
public command surface, per the governance spec — **without** breaking live agents, tests,
validators, or losing functionality, and **without** hand-editing renderer-owned surfaces.

## Established source-of-truth (proven this session)

| Layer | Path | Role |
|---|---|---|
| Desired-state spec | `handsoff/handoff/agent-skills-commands.yaml` | logical catalog + migration decisions (external repo) |
| **Canonical skill bodies** | `packages/ai-universal-rules/templates/workflows/<id>.md` (34) + `templates/skills/{ai-search,ai-scripts}/SKILL.md` (2) | the ONLY hand-edit surface for skills |
| Canonical commands | `packages/ai-universal-rules/templates/commands/*.md` (5) | hand-edit surface for commands |
| Install sync | `tools/ai/install/packs.php` (`skill-dirs` copies `templates/workflows` → `.{claude,github,opencode}/skills`) | mirrors canonical → provider surfaces |
| Renderer-owned (DO NOT hand-edit) | `.claude/skills`, `.github/skills`, `.opencode/skills`, `.github/prompts`, `.opencode/commands`, `.claude/commands`, generated catalogs | install/render output; byte-identical mirrors |
| Generated inventories | `docs/ai/catalog.md`, `packages/ai-universal-rules/catalog.json`, `.../BROWSE.md`, `package-lock.ai.json` | regenerate; never hand-edit |

Edit rule: change canonical → re-run installer/renderer → regenerate catalog → run validators.

## Conservative assumptions (explicit, per review)

1. Generated/rendered provider surfaces remain non-editable; only canonical templates are edited.
2. No destructive deletion of any skill/command until its incoming references are migrated AND its migration target exists.
3. Governance (`handsoff`) and implementation (this repo) are tracked as separate changes.
4. `mentor-mode` is **out of scope for removal** (user directive): keep its functionality and 4 assistance levels; keep `validate-mentor-parity.php` green. The spec's "move mentor-mode → interaction_instruction" is **not applied**.

## Phase 1 — Incoming-reference graph (DONE, evidence-backed)

Reference counts = files naming the id, excluding the id's own body and excluding
`vendor/examples/node_modules/.ai backups/graphify-out`. "Live" = non-generated,
non-`docs/tickets` archival, non-`___ARCHITECTURE_2.0` model files.

| Candidate | Spec disposition | Total refs | Live coupling that blocks naive delete |
|---|---|---:|---|
| agent-semantic-verification | merge → agent-definition-review#preflight | 12 | **agent-factory** agent (3 providers + template), handoff.yaml |
| plan-slice | merge → architecture-plan#slice | 24 | workflow.md, prd-and-tasks skill body, handoff.yaml |
| regression-test | merge → bug-regression#reproduce_only | 17 | capabilities/bug-regression/reference.md, integration-matrix.md |
| search-evidence | merge → ai-search (+ remove command) | 29 | `opencode.jsonc`, `.github/instructions/ai-search.instructions.md`, install-manifest, tool-map docs |
| review-search-tool | remove+compose (ai-search+review-diff) | 11 | **`tools/ai/validate-ai-config.php`** |
| architecture-plan-writer | move → plan-writer agent boundary | 94 | mostly archival; agent already `D` on this branch |
| evidence-first-execution | move → always-on protocol | 28 | **`tests/php/PruneShippedTargetsTest.php`**, `scripts/ai/prune-shipped-targets.sh`, `policies/ai-file-standards.json` |
| prd-and-tasks | move → workflow | 16 | **plan-writer** agent (3 providers + template) |
| project-context | move → generated_context | **214** | **`AGENTS.md`** + live agents architect/implementer/plan-writer/configuration-maintainer/reviewer/researcher |
| generate-permissions | convert → tool `permission-generate` | 19 | **`tests/php/PermissionsSuggestCommandTest.php`, `StackProjectDocTest.php`**, scan-stack skill |
| replace-placeholders | convert → tool `placeholder-replace` | 12 | **`tests/php/PlaceholderRegistryTest.php`** |
| scan-stack | convert → tool `stack-scan` | 20 | **`tests/php/StackProjectDocTest.php`**, `validate-install-surface.php`, `install/stack-project-doc.php` |
| script-inventory | convert → tool `script-inventory-scan` | 11 | shipped-surface-inventory.md, dedup-analysis archive |

## Phase 2 — Reconciliation (DONE): blockers found in this repo

- **B1 — missing tool targets.** `tools/ai/scan-stack.php`, `script-inventory.php`, `placeholder-replace.php` **do not exist**. Spec's `deterministic_tools` assumes them. → Tool-conversion of scan-stack, script-inventory, replace-placeholders is **BLOCKED** until the scripts exist (or the existing `ai.php` subcommands are wired as the target). `generate-permissions` → `generate-agent-permissions.php` is the only ready conversion.
- **B2 — absent merge modes.** `reproduce_only`, `slice`, `preflight`, and ai-search's search-evidence mode are **absent** from canonical bodies. → Every merge must **author the mode first** (additive), then redirect refs, then remove the source.
- **B3 — live tests pin the convert-to-tool skills.** Removing them fails `PermissionsSuggestCommandTest`, `StackProjectDocTest`, `PlaceholderRegistryTest`. → Tests must be migrated to the tool contract before removal.
- **B4 — project-context is load-bearing (214 refs incl. AGENTS.md + 6 live agents).** "Move to generated_context" is a **major, standalone migration**, not part of this pass. → Deferred to its own ticket.
- **B5 — governance drift.** Spec references `templates/core/{skills,commands}` and `handoff/gen_skills.py`; neither exists in this repo (bodies live in `templates/workflows/`; generator lives only in `handsoff`). → Reconcile spec `canonical_source`/paths to this repo's real layout (governance-repo change).

## Phase 5 — Execution sequence (per slice, no broken intermediate state)

For EACH candidate, in this order (never delete before steps 1–3):
1. **Add target** — author the mode/section in the kept canonical body (additive), or create the tool script.
2. **Redirect live references** — point agents / `role_skill_loading` / instructions / tests at the canonical id+mode or tool.
3. **Alias window** — record deprecation in `docs/ai/agent-deprecations.md`; keep the old id resolvable for one release.
4. **Remove canonical body** — delete `templates/workflows/<id>.md` (and command/prompt templates).
5. **Re-sync + regenerate** — `php tools/ai/install-ai-kit.php` (or targeted mirror) → `php tools/ai/generate-ai-catalog.php`.
6. **Validate** (below).
7. **Two clean fleet assessments** across a release boundary → then remove alias.

### Recommended slice order (safest → hardest)
1. **regression-test → bug-regression#reproduce_only** (additive mode; no missing target; no live test).
2. **plan-slice → architecture-plan#slice**.
3. **agent-semantic-verification → agent-definition-review#preflight** (redirect agent-factory).
4. **review-search-tool → compose** (update `validate-ai-config.php` expected-set first).
5. **search-evidence → ai-search** (+ retire command; update `opencode.jsonc`, instructions, manifest).
6. **generate-permissions → permission-generate tool** (target exists; migrate 2 tests).
7. **prd-and-tasks → workflow** (redirect plan-writer).
8. **BLOCKED until scripts exist:** scan-stack, script-inventory, replace-placeholders (B1/B3).
9. **Separate ticket:** project-context (B4), evidence-first-execution (B3), architecture-plan-writer (mostly done on branch).

## Validators (run after every slice)

```
php tools/ai/validate-ai-config.php
php tools/ai/validate-ai-catalog.php
php tools/ai/generate-ai-catalog.php --check
php tools/ai/validate-install-surface.php
php tools/ai/validate-catalog-drift.php
php tools/ai/validate-mentor-parity.php        # mentor-mode must stay green
php tools/ai/validate-adapter-drift.php
composer test  # or: the tests/php/*.php touched by the slice
```

## Things to avoid

- Hand-deleting `.claude/skills`, `.github/skills`, `.opencode/skills`, prompts, or generated catalogs.
- Removing any id while a live agent/test/validator still names it.
- Touching `mentor-mode` behavior or its 4 levels.
- Treating spec disposition as proof a file is unreferenced.

## Cross-provider shipping parity (REQUIRED — added 2026-07-14)

**R1 — agents, commands, AND skills ship identically across providers, differing only in each
provider's own frontmatter/structure.** The `name` and `description` (and body intent) MUST be the
same string across Claude / Copilot / OpenCode; only structural shells differ per
`provider_integration.render_targets` + `*_frontmatter_keys` in the spec:
- Claude skill: `.claude/skills/<id>/SKILL.md` (frontmatter: `description` req; `name` optional).
- Copilot skill: `.github/skills/<id>/SKILL.md` (`name`+`description` req).
- OpenCode skill: `.opencode/skills/<id>/SKILL.md` (`name`+`description` req; `argument-hint` ignored).
- Commands: Claude `.claude/commands/<id>.md`, Copilot `.github/prompts/<id>.prompt.md`, OpenCode `.opencode/commands/<id>.md`.
- Agents: `.claude/agents/<id>.md`, `.github/agents/<id>.agent.md`, `.opencode/agents/<id>.md`.

Same-id skill+command projects **skill-only on Claude/Copilot**, command+skill on OpenCode
(`projection_rules`) — parity means "same description everywhere it is shipped," not "shipped everywhere."

Baseline (2026-07-14): skill bodies are byte-identical across the 3 providers (good); agent
descriptions match (good, only YAML quoting differs). **Gap:** command/prompt surface is NOT
parity-complete — e.g. `verify-ai-wiring` ships as Claude+OpenCode command but has **no
`.github/prompts/verify-ai-wiring.prompt.md`**. Audit and close every such gap.

## Full integration coverage (REQUIRED — added 2026-07-14)

**R2 — every handoff and every agent/skill/command workflow is covered end-to-end.** Companion
`handoff/agent-handoff.yaml` owns agents + handoff contracts + workflows; this catalog owns the
skills/commands they invoke. After consolidation, verify each workflow's participants load only
kept skill ids (with valid mode pins) and each handoff resolves:
- Workflows to cover: `agent_creation`, `delivery`, `fleet_assurance`, `post_install`, and pending `prd_and_tasks`.
- For each: every `role_skill_loading` entry is a kept skill; every command a workflow triggers invokes exactly one kept typed target; every handoff `goto` is an allowed_next edge (`handoff/dispatch.py --check`).
- Declare `orchestrator` role + `prd_and_tasks` workflow in the companion (spec `pending_roles`/`pending_workflows`).

## Remaining work — TODO checklist

Unblock (prereqs):
- [ ] B1/B5: reconcile spec in `handsoff` — create `scan-stack.php` / `script-inventory.php` / `placeholder-replace.php` OR retarget `deterministic_tools` to existing `ai.php` subcommands; fix `templates/core/{skills,commands}` vs real `templates/workflows` path drift; note `gen_skills.py` lives only in `handsoff`.
- [ ] Confirm the skill-render/install invocation that re-mirrors `skill-dirs` into `.{claude,github,opencode}/skills` in this dogfood repo (active profile is `agents-only`).

Execute slices (each: add-target → redirect-refs → alias → remove → re-render → validate):
- [ ] regression-test → bug-regression#reproduce_only (additive mode).
- [ ] plan-slice → architecture-plan#slice.
- [ ] agent-semantic-verification → agent-definition-review#preflight (redirect agent-factory, 3 providers + template).
- [ ] review-search-tool → compose(ai-search, review-diff) (update `validate-ai-config.php` expected-set first).
- [ ] search-evidence → ai-search + retire command (update `opencode.jsonc`, `ai-search.instructions.md`, install manifest).
- [ ] generate-permissions → tool `permission-generate` (migrate `PermissionsSuggestCommandTest`, `StackProjectDocTest`).
- [ ] prd-and-tasks → workflow (redirect plan-writer; declare workflow in companion).
- [ ] BLOCKED until scripts exist: scan-stack, script-inventory, replace-placeholders (+ their tests).
- [ ] Separate tickets: project-context (214 refs, incl. AGENTS.md + 6 agents), evidence-first-execution (tests+prune script), architecture-plan-writer (mostly done on branch).

Parity + integration (R1/R2):
- [ ] Audit description parity for all agents/commands/skills across the 3 providers; close mismatches (start: `verify-ai-wiring` missing Copilot prompt).
- [ ] Verify all 5 workflows' role_skill_loading + handoffs resolve after consolidation.
- [ ] Add/extend a parity validator asserting identical `name`+`description` per shipped surface.

Close-out:
- [ ] Regenerate catalog (`generate-ai-catalog.php`) + run every validator; `validate-mentor-parity.php` green.
- [ ] Record each retired id in `docs/ai/agent-deprecations.md`; two clean fleet assessments before removing aliases.

## Acceptance criteria

- Canonical skill count = 21 (mentor-mode exempt); provider mirrors byte-match per provider structure.
- No live agent/test/validator references a removed id.
- **R1:** every shipped agent/command/skill has the same `name`+`description` across the providers it ships to; no orphaned single-provider command/prompt.
- **R2:** all 5 workflows (`agent_creation`, `delivery`, `fleet_assurance`, `post_install`, `prd_and_tasks`) have every participant loading kept skills and every handoff edge valid.
- All validators above pass; `validate-mentor-parity.php` green.
- `docs/ai/agent-deprecations.md` records each retired id with its target.
- Governance spec reconciled for B1/B5 (handled in `handsoff`).
