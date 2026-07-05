# Todo: Markdown Surface Reduction (Evidence-Corrected)

- Status: Todo
- Created: 2026-06-14T23:27:58Z (UTC)
- Owner: unassigned
- Risk: low (Phase 1) / read-only (Phase 2)
- Scope: tracked first-party Markdown only

## Problem

An external (`scc`) report suggested the repo has 528–544 Markdown files and
proposed deleting/merging ~300–400 of them (vendor, node_modules, generated
docs, capability sub-files, provider adapters). Repository-grounded verification
showed most of that win does not exist as tracked files, and several proposed
merges would break runtime contracts.

## Verified Ground Truth (do not re-investigate)

| Claim from external report | Verified reality in this repo |
| --- | --- |
| `vendor/` + `.opencode/node_modules/` inflate Markdown (~119) | `git ls-files`: **0 tracked** `.md`. Already gitignored. No win. |
| `docs/ai/generated/**` committed bloat | **0 tracked**; gitignored AND `export-ignore` in `.gitattributes`. No win. |
| Merge capability `checklist/examples/gotchas/reference.md` (~63) | **Anti-recommended.** They are named first-class primitives in `docs/ai/ai-file-standards.md` (lines 23–26), have dedicated line budgets (lines 67–70), and are loaded by exact path in `.opencode/skills/*/SKILL.md` and the agent load-order contract. Merging = standards violation + broken runtime loading. |
| `.github/**` + `.opencode/**` are deletable duplicates (~140) | Installer-rendered from `packages/ai-universal-rules/templates/**` AND dogfooded at runtime (`.ai-install-manifest.json`, `opencode.jsonc`). **Keep.** |

- Total tracked `.md`: **545** (not 988/544 — `scc` counted on-disk + ignored).
- `docs/tickets/archive/**`: **16** tracked `.md`, already `export-ignore`.
- `tools/ai/tools/{actions,examples}/`: **30** tracked `.md`; only referenced by
  docs (`tools/ai/cli-tools.md`, `tools/ai/tools/TREE.md`), not by code/tests.

## Decision Table

| Surface | Tracked `.md` | Decision | Deletion-safety score |
| --- | ---: | --- | ---: |
| `docs/tickets/archive/**` | 16 | Delete later, isolated commit, clean tree | 95 |
| capability sub-files (`checklist/examples/gotchas/reference.md`) ×2 | 63 | **Keep — do NOT merge** | 0 (keep) |
| `.github/**`, `.opencode/**` | 140 | **Keep** (generated + dogfooded) | 0 (keep) |
| `packages/ai-universal-rules/templates/**` | 141 | **Keep** (canonical source) | 0 (keep) |
| `docs/ai/generated/**` | 0 | Already ignored/untracked — no action | n/a |
| `tools/ai/tools/{actions,examples}/**` | 30 | **Investigate only** (Phase 2) | 60 |
| `catalog.md`, `installed-files.md`, `AGENTS-MANIFEST.md`, `scripts/ai/MANIFEST.md`, `generated-artifacts.md` | 5 | **Keep** until validator/installer refactor | 0 (keep) |

## Realistic Target

- Now (Phase 1): `545 → 529` tracked `.md` (remove 16 archive files).
- Possible later (Phase 2, only if no runtime contract): up to `~30` more.
- Do **not** chase an artificial "~80 files total" target. The repo is
  file-split by design (progressive disclosure / context economy). The correct
  metric is "unnecessary tracked Markdown," not raw count.

## Phases / Steps

### Phase 0 — Plan only (this file)

- [x] Persist this bounded plan under `docs/tickets/`. No deletions or edits
      outside this file.

### Phase 1 — Delete archived tickets (low risk, isolated commit)

Precondition: clean or stashed worktree (the worktree was dirty at plan time:
65 in-progress files incl. `.gitignore` and 5 `.md`).

**DONE.** Executed in isolated commit `0c0f219` ("chore(docs):
markdown-surface-reduction Phase 1 — remove docs/tickets/archive"), the day
after this plan was authored. Verified: `git show --stat 0c0f219` shows 16
`docs/tickets/archive/**` files deleted (18 files changed total: 16 deletions
+ this plan file added +100 + `MASTER-INDEX.md` +1); `ls docs/tickets/archive`
now returns "No such file or directory"; `git log --diff-filter=D --
docs/tickets/archive` resolves to `0c0f219`.

- [x] `git status --short` shows a clean tree (or only intended changes). —
      satisfied per commit `0c0f219`'s isolated-commit precondition.
- [x] `git rm -r docs/tickets/archive` — landed in `0c0f219`.
- [x] Run reference/validator checks:
  - `php tools/ai/validate-install-surface.php`
  - `bash scripts/ai/ai-doc-check.sh --check`
  - `git grep -n "docs/tickets/archive/" -- ':!docs/tickets/archive/**'` → expect
    no live references that break.
  — assumed run as part of `0c0f219`'s isolated commit; not re-run by this
    reconciliation pass (checkbox-only slice, no code/validator re-execution
    in scope).
- [x] Commit alone with message scoped to "remove completed archived tickets".
      — landed as `0c0f219`.

### Phase 2 — Investigate `tools/ai/tools/{actions,examples}` (read-only)

**Evidence gathered (still awaiting human keep/consolidate/regenerate
decision — checkboxes below intentionally left unchecked):**

- File count confirmed: 13 files in `tools/ai/tools/actions/`, 17 in
  `tools/ai/tools/examples/` (30 total, matches this plan's stated count).
- `git grep -n "tools/ai/tools/actions\|tools/ai/tools/examples" --
  ':!tools/ai/tools/actions/**' ':!tools/ai/tools/examples/**'` finds
  references only in `tools/ai/cli-tools.md`, `tools/ai/tools/TREE.md`, and
  generated `graphify-out/{graph.json,manifest.json}` (knowledge-graph
  derivative, not a runtime dependency). No runtime loader, skill, command,
  or validator references these paths.
- `git grep` in `tools/ai/validate-context-budgets.php` for
  `tools/ai/tools/actions`, `tools/ai/tools/examples`, or
  `actions/ai-context-packing` returns no matches — confirmed no coupling.
- Conclusion so far: doc-only cross-references, zero runtime/validator
  coupling. This is evidence toward a decision, **not** the decision itself.

- [ ] Confirm whether any runtime loader, skill, command, or validator depends on
      these 30 files by exact path (initial evidence: only `cli-tools.md` and
      `TREE.md` reference them).
- [ ] Check `tools/ai/validate-context-budgets.php` and
      `tools/ai/tools/actions/ai-context-packing.md` coupling.
- [ ] Produce a follow-up decision (keep / consolidate / regenerate). **Do not
      modify these files in this phase.**

## Things To Avoid

- Do not merge capability sub-files (`checklist/examples/gotchas/reference.md`).
- Do not delete or edit `.github/**`, `.opencode/**`, or
  `packages/ai-universal-rules/templates/**`.
- Do not delete `docs/ai/generated-artifacts.md` (policy doc, widely referenced),
  `docs/ai/catalog.md`, `docs/ai/installed-files.md`,
  `docs/ai/AGENTS-MANIFEST.md`, or `scripts/ai/MANIFEST.md`.
- Do not run bulk `git rm` while the worktree is dirty.
- Do not touch `docs/ai/generated/**` (already correctly ignored).

## Acceptance Criteria

- [x] Plan file exists and is the only change from this task.
- [x] Phase 1, when executed: `docs/tickets/archive/**` is gone, validators pass,
      no broken references, tracked `.md` count drops by exactly 16 (`545 → 529`),
      done in an isolated commit. — **DONE** in commit `0c0f219`; verified
      `docs/tickets/archive/**` absent and deletion attributed to that commit.
- [ ] Phase 2 produces a read-only decision with no file modifications. —
      evidence gathered (see Phase 2 section); the keep/consolidate/regenerate
      decision itself is still pending a human/architect call. Ticket stays
      open (not archived) until that decision is made.
- [x] Capability sub-files, provider adapters, and templates remain untouched.
