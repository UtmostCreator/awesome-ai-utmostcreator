> STATUS (verified): the P0/P1 script split this doc proposes is **ALREADY
> DONE** in the in-flight `scripts/ai` reorg. Every P0/P1 target now has a
> `scripts/ai/internal/<name>/` module set with a thin root wrapper (e.g.
> `ai-verify.sh` 718->~82, `ai-edit.sh` 660->~117, `repomix-scc-router.sh`
> 967->~85, `repomix-context-tree.sh` 733->~76, `ai-diff-context.sh` 662->~76,
> `pre-tool-use.sh` ->~40, `prune-shipped-targets.sh` 399->~53). `lib/60-exec-guard.sh`
> moved to `scripts/ai/internal/lib/exec-guard/`. The whole "Do not refactor
> first / ai-search file list", "Recommended folder structure", sections 1-8,
> acceptance criteria, and commit plan below are superseded by the committed
> `docs/tickets/arch-todo-restructure-scripts-ai-*` work and kept only for
> historical context. Only the P2 deferred scripts below remain valid.

## Remaining (still-valid) deferred scripts

These were never split and are genuine remaining work. Paths corrected for the
reorg (canonical impl at the path shown; root name is a `bin/<role>/` shim):

| Priority | File                                              | Score | Status                          |
| -------: | ------------------------------------------------- | ----: | ------------------------------- |
|       P2 | `scripts/ai/bin/read/preview-file.sh`             |    40 | 307 lines, not split            |
|       P2 | `scripts/ai/bin/edit/ai-rollback.sh`              |    41 | 277 lines, not split            |
|       P2 | `scripts/ai/bin/admin/install-mandatory-tools.sh` |    26 | 211 lines, not split            |
|       P2 | `scripts/ai/bin/read/ai-search-multi.sh`          |    26 | 311 lines, not split            |

---

## (Historical, superseded) Original plan — Do **not** refactor first

`ai-search/` is already correctly moving toward modular structure. Do not spend first effort there except for small cleanup.

Keep these mostly stable now:

```text
ai-search/00-bootstrap.sh
ai-search/10-contract.sh
ai-search/20-state.sh
ai-search/25-modes.sh
ai-search/30-parse-flags.sh
ai-search/35-parse-positionals.sh
ai-search/40-output-json.sh
ai-search/45-results-rg.sh
ai-search/50-results-context.sh
ai-search/55-scope-args.sh
ai-search/60-guards.sh
ai-search/65-backend-files.sh
ai-search/70-backend-text.sh
ai-search/75-backend-git.sh
ai-search/80-backend-curated.sh
ai-search/85-backend-ast.sh
ai-search/90-doctor.sh
ai-search/95-dispatch.sh
```

Current `ai-search/` modularity score: **82/100**.
Main issue is not structure, but possible file role overlap between `10-contract`, `30-parse-flags`, `55-scope-args`, and `95-dispatch`.

---

# Recommended folder structure

Keep root scripts as thin wrappers for backward compatibility:

```text
ai-verify.sh
pre-tool-use.sh
ai-edit.sh
repomix-scc-router.sh
...
```

Each wrapper should only load files from its matching subdir and call dispatch.

---

## 1. `ai-verify.sh` → `ai-verify/`

Highest priority.

```text
ai-verify.sh
ai-verify/
  00-bootstrap.sh
  05-env.sh
  10-contract.sh
  15-usage.sh
  20-state.sh
  25-tool-detection.sh
  30-parse-flags.sh
  35-scope-discovery.sh
  40-repo-checks.sh
  45-file-checks.sh
  50-ai-config-checks.sh
  55-test-selection.sh
  60-command-runner.sh
  70-output-json.sh
  80-report-human.sh
  90-doctor.sh
  95-dispatch.sh
```

**Why first:** it has the highest complexity and likely mixes CLI parsing, checks, output, and execution.

Target after refactor:

```text
Lines per file: <180
Complexity per file: <30
Root wrapper: <40 lines
```

---

## 2. `pre-tool-use.sh` → `pre-tool-use/`

Safety-critical. Refactor before adding more policy logic.

```text
pre-tool-use.sh
pre-tool-use/
  00-bootstrap.sh
  05-env.sh
  10-contract.sh
  15-usage.sh
  20-state.sh
  25-input-schema.sh
  30-parse-event.sh
  35-normalize-tool-call.sh
  40-command-classify.sh
  45-path-classify.sh
  50-policy-load.sh
  55-risk-score.sh
  60-decision.sh
  65-deny-reasons.sh
  70-output-json.sh
  80-audit-log.sh
  90-doctor.sh
  95-dispatch.sh
```

**Why:** hook scripts must be auditable. A monolithic safety gate becomes dangerous because policy, parsing, and decision logic are hard to verify.

Target score after refactor: **90/100 production readiness**.

---

## 3. `ai-edit.sh` → `ai-edit/`

Mutation script. Split before expanding features.

```text
ai-edit.sh
ai-edit/
  00-bootstrap.sh
  05-env.sh
  10-contract.sh
  15-usage.sh
  20-state.sh
  25-edit-plan.sh
  30-parse-flags.sh
  35-target-resolve.sh
  40-read-current.sh
  45-patch-build.sh
  50-guards.sh
  55-diff-preview.sh
  60-apply-edit.sh
  65-post-verify.sh
  70-output-json.sh
  80-report-human.sh
  90-doctor.sh
  95-dispatch.sh
```

**Important:** keep all actual mutation in `60-apply-edit.sh`. Everything before that should be read-only.

---

## 4. `lib/60-exec-guard.sh` → `lib/exec-guard/`

Do not leave this as one large library file.

```text
lib/60-exec-guard.sh
lib/exec-guard/
  00-bootstrap.sh
  10-contract.sh
  20-command-normalize.sh
  30-command-taxonomy.sh
  40-readonly-rules.sh
  45-write-rules.sh
  50-dangerous-patterns.sh
  55-pipe-rules.sh
  60-decision.sh
  70-output.sh
  90-self-test.sh
```

Then make `lib/60-exec-guard.sh` a compatibility loader:

```sh
# shellcheck shell=sh
. "$AI_LIB_DIR/exec-guard/00-bootstrap.sh"
. "$AI_LIB_DIR/exec-guard/10-contract.sh"
...
```

**Why:** this file is security-sensitive and already has complexity `75`.

---

## 5. `repomix-scc-router.sh` → `repomix-scc-router/`

Biggest file by lines. Refactor after safety scripts.

```text
repomix-scc-router.sh
repomix-scc-router/
  00-bootstrap.sh
  05-env.sh
  10-contract.sh
  15-usage.sh
  20-state.sh
  25-config.sh
  30-parse-flags.sh
  35-input-resolve.sh
  40-scc-runner.sh
  45-scc-parser.sh
  50-routing-rules.sh
  55-size-thresholds.sh
  60-repomix-runner.sh
  65-output-paths.sh
  70-output-json.sh
  80-report-human.sh
  90-doctor.sh
  95-dispatch.sh
```

**Main goal:** separate `scc` collection, routing decisions, and `repomix` execution.

---

## 6. `repomix-context-tree.sh` → `repomix-context-tree/`

```text
repomix-context-tree.sh
repomix-context-tree/
  00-bootstrap.sh
  05-env.sh
  10-contract.sh
  15-usage.sh
  20-state.sh
  30-parse-flags.sh
  35-root-resolve.sh
  40-tree-collect.sh
  45-ignore-rules.sh
  50-rank-files.sh
  55-budgeting.sh
  60-render-tree.sh
  70-output-json.sh
  80-report-human.sh
  90-doctor.sh
  95-dispatch.sh
```

**Main goal:** separate tree discovery, filtering, ranking, and rendering.

---

## 7. `ai-diff-context.sh` → `ai-diff-context/`

```text
ai-diff-context.sh
ai-diff-context/
  00-bootstrap.sh
  05-env.sh
  10-contract.sh
  15-usage.sh
  20-state.sh
  30-parse-flags.sh
  35-git-range.sh
  40-diff-collect.sh
  45-file-map.sh
  50-context-expand.sh
  55-token-budget.sh
  60-render-context.sh
  70-output-json.sh
  80-report-human.sh
  90-doctor.sh
  95-dispatch.sh
```

**Main goal:** separate git diff detection from context expansion.

---

## 8. `prune-shipped-targets.sh` → `prune-shipped-targets/`

Destructive operation, so it deserves a clean structure.

```text
prune-shipped-targets.sh
prune-shipped-targets/
  00-bootstrap.sh
  05-env.sh
  10-contract.sh
  15-usage.sh
  20-state.sh
  30-parse-flags.sh
  35-target-collect.sh
  40-candidate-rules.sh
  45-refuse-rules.sh
  50-dry-run-plan.sh
  55-snapshot.sh
  60-delete-apply.sh
  70-output-json.sh
  80-report-human.sh
  90-doctor.sh
  95-dispatch.sh
```

**Rule:** only `60-delete-apply.sh` may delete. Everything else must be dry-run/read-only.

---

# Final recommended order

```text
1. ai-verify.sh
2. pre-tool-use.sh
3. ai-edit.sh
4. lib/60-exec-guard.sh
5. repomix-scc-router.sh
6. repomix-context-tree.sh
7. ai-diff-context.sh
8. prune-shipped-targets.sh
9. preview-file.sh
10. ai-rollback.sh
11. install-mandatory-tools.sh
12. ai-search-multi.sh
```

## Acceptance criteria for each refactor

```text
AC1: Root script remains executable with the same CLI.
AC2: Root script becomes a thin wrapper under 40 lines.
AC3: Each module has one clear responsibility.
AC4: No module exceeds 180 lines unless justified.
AC5: No module has complexity above 30 where avoidable.
AC6: --help output remains identical or better.
AC7: --json output remains backward-compatible.
AC8: Destructive actions are isolated into one clearly named apply/delete module.
AC9: Dry-run mode works before apply mode.
AC10: Existing tests or smoke checks pass.
```

## Best first commit plan

Start with only one file:

```text
P0 commit 1:
  refactor: split ai-verify into numbered modules
```

Then:

```text
P0 commit 2:
  refactor: split pre-tool-use policy gate

P0 commit 3:
  refactor: split ai-edit mutation pipeline

P0 commit 4:
  refactor: split exec guard library
```

Do **not** refactor all large files in one pass. For this repo, the safest sequence is:

```text
verify → hook gate → edit → exec guard → repomix
```
