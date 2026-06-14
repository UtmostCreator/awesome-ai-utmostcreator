# Master Plan Index

Single tracking surface for **every** non-archive plan folder under `docs/tickets/`.
Complements the narrower A–F program index in
`arch-todo-backlog-deferred-program-20260614-104819/plan.md` (Plan G), which only
covers the mega-plan decomposition. This file covers all plans.

- Generated: 20260614 (implementer review pass); REVISED 20260614 (archive + status sync pass)
- Method: status verified against the live working tree and commit history, NOT against
  each plan's self-declared `Status:` line (many are stale). The 20260614 revision also
  verified the uncommitted working-tree changes (721 tests pass, all surface validators
  green) and confirmed the `scripts/ai` folder refactor is fully landed on disk.
- Convention: durable plans live in `docs/tickets/arch-todo-{slug}-{ts}/plan.md`.
  Archived/closed source docs live under `docs/tickets/archive/`.

> SCRIPTS FOLDER REFACTOR — DONE (answer to "why haven't we completed it?"): it IS
> completed. `scripts/ai/bin/{read,context,verify,edit,admin,hooks}/` (41 delegating
> shims) and `scripts/ai/internal/{lib,search,...}/` are present on disk and committed
> (`aad531a` baseline + the P3–P5 / remaining-rework phases). The ONLY open script item
> is P6 below — a THIRD, conflicting taxonomy *proposal* that is REJECTED (it violates
> the frozen "root names must not change" rule). There is no stuck migration.

> ARCHIVED 20260614: eight completed/superseded plan folders were moved to
> `docs/tickets/archive/completed-20260614/`: Plan A (land-scripts-ai-reorg),
> Plan B (shipping-surface), Plan E (ai-search-shortcut-modes), ai-edit-patch-mode,
> agent... remaining-rework, restructure-scripts-ai-by-role-risk,
> restructure-scripts-ai-p3-p5, and the SUPERSEDED repo-cleanup-shipping-surface.

## Legend

- **Status**: `done` (landed, plan label stale) · `actionable` (real ungated work
  remains) · `blocked` (approval/review/dependency gate) · `deferred` (intentionally
  not scheduled) · `superseded` (do not implement from it).
- **Priority**: P1 highest. Priority ranks only the **remaining** work, not done plans.

## Priority Queue (remaining work only)

| P | Plan | Remaining work | Gate | Risk | Status |
|---|------|----------------|------|------|--------|
| P1 | readme-improvements | Mirror mentor L0–L5 + "maintain sanity" into `docs/ai/non-technical-overview.md` | none | low | **done (in working tree)** |
| P2 | doc-surface-hardening (Plan C) item 40b | `git rm` 11 duplicate `.opencode/agents-optional/*.md` + `validate-install-surface.php` | approval (granted) | medium | **done (in working tree)** |
| P3 | agent-score-frontmatter (Plan D) | Re-scoped to D1/D2/D3; D1 schema+validator + D2 renderer implemented (D3 backlog) | review (resolved by re-scope) | high→low | **done (D1+D2, in working tree)** |
| P4 | doc-surface-hardening (Plan C) item 85 | `just verify-surface` recipe wiring existing surface validators | none (unblocked by D1/D2) | low | **done (in working tree)** |
| P5 | ai-search-external-access (Plan F) | **security fix** (NOT a feature): inside-root confinement default + in-script secret block + `blocked` for un-flagged outside-root | release-safety review **DONE → DO-NOT-SHIP-YET**; **DECISION: route to architect** | high (security) | **blocked → architect (confinement+secret-block fix)** |
| P6 | scripts/ai phase-based refactor (NEW proposal) | reorganize into search/context/edit/verify/repomix/git/repo/lifecycle/hooks/session | **REJECTED (decision recorded)** — conflicts with already-landed `bin/{role}/`+`internal/` + frozen "root names must not change" rule; also re-introduces dropped item 70 | high (broad reference-integrity) | **rejected / do-not-implement** |
| — | D3a (agent_assessment values source) | source schema + `docs/ai/agent-scores.yaml` (draft) + validator + test | none | low | **done (draft; approval pending)** |
| — | D3b (render approved values) | renderer reads source; re-render generated surfaces | **human flips `approved: true`** + installer `--apply` approval | medium-high | **gated** |
| — | D3c (numeric scoring) | numeric rubric + thresholds | human-approved numeric rubric | medium | backlog |
| — | Backlog items 90/95/110/115/120/125 | see Plan G index | each needs own architect pass | mixed | backlog |

> NOTE: P1–P4 are completed by the current **uncommitted working tree** (verified: 721
> tests pass, all surface validators exit 0). They are "done" pending commit, not pending
> further implementation. Plan E (ai-search shortcut modes) landed in `09e9505` and its
> golden-fixture refresh is also present in the working tree — so the former "Plan E
> snapshot fix" backlog row is resolved and has been removed.

> DECISIONS RECORDED (20260614 pass 2):
> - **P6 REJECTED** — keep the frozen root-flat contract + `bin/{role}/` alias tree.
>   The ~42 root `scripts/ai/*.sh` files are the intended final state (registered public
>   contract); discoverability is already provided by `scripts/ai/bin/{read,context,verify,
>   edit,admin,hooks}/`. Moving them is high-blast-radius (registry↔packs 1:1 invariant,
>   ~100 refs, hashed manifests, self-anchoring scripts, test globs) and re-introduces
>   dropped item 70. Do not implement.
> - **P5 (F) → architect as a SECURITY FIX** — ai-search already reads outside-root and
>   secrets with `status: ok` and no prompt (the host `external_directory: ask` prompt does
>   NOT fire for allow-listed bash). Fix = confine inside-root by default + in-script secret
>   block + emit `blocked` for un-flagged outside-root + `AI_SEARCH_ALLOW_OUTSIDE_ROOT`
>   default-off disable path.
> - **D3 split to D3a/D3b/D3c; D3a IMPLEMENTED this pass** (draft, approval pending):
>   `schemas/ai/agent-assessment-values.schema.json`, `docs/ai/agent-scores.yaml`
>   (24 entries, canonical template keys, categorical-only, `approved: false`),
>   `tools/ai/validate-agent-assessment-values.php`, `tests/php/AgentAssessmentValuesValidatorTest.php`
>   (8 tests pass), wired into `ai-doc-check.sh` + `just verify-surface`. See D3-plan.md.
>   D3b is gated on a human flipping `approved: true` and approving the installer re-render.
> - **Plan C (doc-surface-hardening) verified essentially COMPLETE**: item 30 committed
>   (`ce1f968`); items 40b/85 in working tree; item 75 done (named placeholder docs are all
>   ≥26 lines ≥ the 20-line floor); item 80 done (`dedup-analysis.md` exists).

### P5 release-safety verdict (recorded; satisfies Plan F item 105a / AC-01)

DO-NOT-SHIP-YET. Crux findings: (1) `external_directory: ask` is an OpenCode runtime key
(`opencode.jsonc:224`) NOT in the bash enforcement path — it does not fire for an allow-listed
`bash scripts/ai/ai-search.sh *` call (proven: `ai-search files opencode /tmp` → `ok`); (2)
ai-search is ALREADY ungated outside-root (`internal/search/35-parse-positionals.sh`,
`40-output-json.sh:90`); (3) no in-pipeline secret block. Item 105 is NOT additive — confinement
default + in-script secret guard are prerequisites. Full verdict in Plan F header.

### P6 scripts/ai refactor — CONFLICT (do not implement as proposed)

The proposed taxonomy (`search/ context/ edit/ verify/ repomix/ git/ repo/ lifecycle/ hooks/
session/` + renamed `bin/` entrypoints + `all_in_one.sh`→`ai-run.sh`) is a THIRD, incompatible
reorg that collides with already-landed structure and a frozen rule:

- Plan A landed `scripts/ai/bin/{read,context,verify,edit,admin,hooks}/` (role/risk) + `internal/`
  (commit `aad531a`); the proposal's `bin/` holds tool wrappers by NAME, not role.
- Frozen migration strategy (`arch-todo-remaining-rework`): "root script `scripts/ai/<name>.sh`
  is the canonical, registered contract; `bin/<role>/` are additive UNREGISTERED aliases;
  internals under `internal/`. **Root names must NOT change.**" The proposal moves the 43
  canonical root scripts into subfolders and renames entrypoints — directly violating this.
- Proposal also re-introduces DROPPED `repo-tool-inventory.sh` surface (Plan G dropped item 70).

Needs an architect decision: reconcile vs. the registry contract, `bin/{role}/` shims, the
script registry (`tools/ai/install/script-registry.php`), `.github/ai-script-access.yaml`, and
the manifest tests, OR reject as superseded. NOT an implementer slice until reconciled.

## All Plans (full inventory, with evidence)

### Done — ARCHIVED 20260614 to `archive/completed-20260614/`

These landed; their folders were moved to `docs/tickets/archive/completed-20260614/`.

| Plan folder (now under archive/completed-20260614/) | What it delivered | Evidence |
|---|---|---|
| `arch-todo-land-scripts-ai-reorg-20260614-104813` (Plan A) | scripts/ai reorg baseline | commit `aad531a`; `scripts/ai/{bin,internal}/` present |
| `arch-todo-shipping-surface-20260614-104814` (Plan B) | export-ignore, exclude lists, ship-audit, doc archival | commit `1841a7f`; `.gitattributes:13-18`; `scripts/ai/ship-audit.sh` |
| `arch-todo-ai-search-shortcut-modes-20260614-104817` (Plan E) | function/method/interface/enum/route/config-key modes | commit `09e9505`; `scripts/ai/internal/search/25-modes.sh:23` |
| `arch-todo-ai-edit-patch-mode-20260614T090427Z` | patch mode + `--count-matches` fix + guard | `scripts/ai/internal/ai-edit/40-plan-apply.sh:21,107,144,176` |
| `arch-todo-remaining-rework-20260613-231930` | 7 phases (sh-introspect, manifest, script splits) | `internal/{pre-tool-use,ai-verify,...}/` splits; Phase 3b deferred |
| `arch-todo-restructure-scripts-ai-by-role-risk-20260613-214236` | P0–P2 inventory + manifest test (doc-only) | `scripts/ai/MANIFEST.md`; `tests/php/ScriptsAiManifestTest.php` |
| `arch-todo-restructure-scripts-ai-p3-p5-20260613-220424` | P3–P5 file moves + 42 shims | old `scripts/ai/lib`/`ai-search` gone; `bin/*` populated |
| `arch-todo-repo-cleanup-shipping-surface-20260614-101701` | SUPERSEDED — decomposed into A–G | carries SUPERSEDED banner |

### Done — kept (has open follow-ups; NOT archived)

| Plan folder | What it delivered | Open follow-up |
|---|---|---|
| `arch-todo-agent-permission-rethink-20260613T154104Z` | registry-canonical perms, gateway wiring, Phase 4 apply-gate | `todo-remaining-work.md` has unchecked P1–P3 (registry projection parity tests, perm-drift test, doc cleanup) |

### Actionable / blocked / deferred (see Priority Queue above)

| Plan folder | Status | Note |
|---|---|---|
| `arch-todo-readme-improvements-20260613T153314Z` | done (working tree) | LICENSE/SECURITY/SUPPORT/at-a-glance + P1 mentor mirror landed; safe to archive after commit |
| `arch-todo-doc-surface-hardening-20260614-104815` (Plan C) | done (verified) | items 30 (committed) / 40b / 85 (working tree) / 75 (docs ≥26 lines) / 80 (`dedup-analysis.md`) all complete; safe to archive after commit |
| `arch-todo-agent-score-frontmatter-20260614-104816` (Plan D) | D1+D2 done; **D3a done (draft)**; D3b gated | per-agent value SOURCE now exists (`docs/ai/agent-scores.yaml`, draft); D3b needs human approval + re-render |
| `arch-todo-ai-search-external-access-20260614-104818` (Plan F) | blocked → architect | re-framed as a SECURITY FIX (confinement + secret block); release-safety review recorded |

### Index (not an implementation slice)

| Plan folder | Status | Note |
|---|---|---|
| `arch-todo-backlog-deferred-program-20260614-104819` (Plan G) | index | tracks A–F program + backlog/dropped items |

## Maintenance

- When a plan lands, move its folder under `docs/tickets/archive/` and update the
  table above.
- When promoting a backlog item (90/95/110/115/120/125), open a dedicated architect
  pass first (per Plan G); do not implement directly from an index.
- Approval-gated (P2) and review/dependency-gated (P3–P5) rows must not be started
  without the named gate cleared.
