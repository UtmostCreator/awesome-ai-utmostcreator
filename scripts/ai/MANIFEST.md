# scripts/ai Role/Risk Manifest

This manifest is the P0 inventory for organizing `scripts/ai` by role and risk.
It is classification-only: current top-level script paths remain the public
contract, and no runtime entrypoints have moved.

Future folder names such as `bin/read`, `bin/context`, `bin/verify`, `bin/edit`,
`bin/admin`, `bin/hooks`, `internal/lib`, and `internal/search` are target
categories for later phases, not active paths in this slice.

## Public/facade scripts

| Script | Target role | Registry risk | Public/private | Notes |
| --- | --- | --- | --- | --- |
| `scripts/ai/ai-diff-context.sh` | context | read-only | public | Diff-aware context extraction. |
| `scripts/ai/ai-doc-check.sh` | verify | read-only | public | Documentation consistency check. |
| `scripts/ai/ai-edit.sh` | edit | mutating | public guarded | Guarded text/AST edits. |
| `scripts/ai/ai-file-freshness.sh` | read | read-only | public | File freshness inspection. |
| `scripts/ai/ai-install-coverage.sh` | verify | read-only | public | Install coverage verification. |
| `scripts/ai/ai-rollback.sh` | edit | mutating | public guarded | Rollback for guarded edits. |
| `scripts/ai/ai-search-introspect.sh` | read | read-only | public | Search wrapper introspection; not registry-listed in current evidence (read-only by source: static parse, never executes targets). |
| `scripts/ai/ai-search-multi.sh` | read | read-only | public | Batch search wrapper. |
| `scripts/ai/ai-search.sh` | read | read-only | public facade | Facade over `scripts/ai/internal/search/*`. |
| `scripts/ai/ai-structured.sh` | context | read-only | public | Structured evidence/context helper. |
| `scripts/ai/ai-task.sh` | context | read-only | public | Task/context helper. |
| `scripts/ai/ai-test-select.sh` | verify | read-only | public | Test selection helper. |
| `scripts/ai/ai-verify-html.sh` | verify | read-only | public | Thin `ai-verify.sh --language html` wrapper; no tool logic. |
| `scripts/ai/ai-verify-js.sh` | verify | read-only | public | Thin `ai-verify.sh --language js` wrapper; no tool logic. |
| `scripts/ai/ai-verify-php.sh` | verify | read-only | public | Thin `ai-verify.sh --language php` wrapper; no tool logic. |
| `scripts/ai/ai-verify-ts.sh` | verify | read-only | public | Thin `ai-verify.sh --language ts` wrapper; no tool logic. |
| `scripts/ai/ai-verify-vue.sh` | verify | read-only | public | Thin `ai-verify.sh --language vue` wrapper; no tool logic. |
| `scripts/ai/ai-verify.sh` | verify | read-only | public | Verification wrapper. |
| `scripts/ai/all_in_one.sh` | admin | mutating/unknown | public admin | Administrative all-in-one workflow; not registry-listed in current evidence. |
| `scripts/ai/build-ai-help-bundle.sh` | admin | read-only/unknown | public admin | Help bundle build helper; not registry-listed in current evidence. |
| `scripts/ai/check-file-refs.sh` | verify | read-only | public | File reference validation. |
| `scripts/ai/common.sh` | internal-lib | read-only | public facade | Shared shell facade; callers should source this, not numbered library modules. |
| `scripts/ai/fd-files.sh` | read | read-only | public | File discovery wrapper. |
| `scripts/ai/gh-pr-context.sh` | read | read-only | public | GitHub PR context lookup. |
| `scripts/ai/git-branch-origin.sh` | read | read-only | public | Branch origin/merge-base inspection. |
| `scripts/ai/git-forensics.sh` | read | read-only | public | Read-only git history tracing. |
| `scripts/ai/install-mandatory-tools.sh` | admin | mutating | public admin | Tool installation helper; approval-gated by policy. |
| `scripts/ai/list-todos.sh` | verify | read-only/mutating (guarded `--apply`) | public guarded | Lists `docs/tickets/**/plan*.md` files with incomplete Todo Plan / Acceptance Criteria items; `archive` mode moves fully-complete files to `archive/DONE-<name>` only with `--apply`. |
| `scripts/ai/pack-context.sh` | context | read-only | public | Context packing. |
| `scripts/ai/post-tool-use.sh` | hooks | read-only | public hook | Post-tool evidence/runtime hook. |
| `scripts/ai/pre-tool-use.sh` | hooks | read-only | public hook | Pre-tool policy/runtime hook. |
| `scripts/ai/preview-file.sh` | read | read-only | public | Safe file preview. |
| `scripts/ai/prune-shipped-targets.sh` | admin | mutating | public admin | Shipped-target maintenance; approval-gated by policy. |
| `scripts/ai/query-usage.sh` | read | read-only | public | Usage/token estimation helper. |
| `scripts/ai/repo-stats.sh` | read | read-only | public | Repository statistics. |
| `scripts/ai/repo-tool-inventory.sh` | read | read-only | public | Repository tool inventory. |
| `scripts/ai/repomix-context-tree.sh` | context | read-only | public | Repomix context tree helper. |
| `scripts/ai/repomix-ensure-fresh.sh` | context | read-only | public | Repomix freshness enforcement. |
| `scripts/ai/repomix-freshness.sh` | context | read-only | public | Repomix freshness check. |
| `scripts/ai/repomix-scc-router.sh` | context | read-only | public | Repomix SCC routing. |
| `scripts/ai/rg-code.sh` | read | read-only | public | Code-focused search. |
| `scripts/ai/run-repo-tests.sh` | verify | read-only | public | Repository test runner. |
| `scripts/ai/run-repomix-context.sh` | context | read-only | public | Repomix context pack. |
| `scripts/ai/run-repomix-file.sh` | context | read-only | public | Repomix single-file pack. |
| `scripts/ai/run-test-focused.sh` | verify | read-only | public | Focused test runner. |
| `scripts/ai/session-checkpoint.sh` | hooks | mutating | public hook | Session continuity snapshot. |
| `scripts/ai/ship-audit.sh` | verify | read-only | public | Installer pack forbidden-path audit. |
| `scripts/ai/sh-introspect.sh` | read | read-only | public | Shell script introspection. |
| `scripts/ai/watch-loop.sh` | hooks | read-only/long-running | public hook | Long-running watch helper; not a normal read command. |

## Private implementation modules

| Path | Target role | Public/private | Notes |
| --- | --- | --- | --- |
| `scripts/ai/internal/lib/00-env.sh` | internal-lib | private | Loaded through `scripts/ai/common.sh`. |
| `scripts/ai/internal/lib/05-core.sh` | internal-lib | private | Loaded through `scripts/ai/common.sh`. |
| `scripts/ai/internal/lib/10-json.sh` | internal-lib | private | Loaded through `scripts/ai/common.sh`. |
| `scripts/ai/internal/lib/20-paths.sh` | internal-lib | private | Loaded through `scripts/ai/common.sh`. |
| `scripts/ai/internal/lib/30-logging.sh` | internal-lib | private | Loaded through `scripts/ai/common.sh`. |
| `scripts/ai/internal/lib/31-log-redaction.sh` | internal-lib | private | Loaded through `scripts/ai/common.sh`. |
| `scripts/ai/internal/lib/40-session.sh` | internal-lib | private | Loaded through `scripts/ai/common.sh`. |
| `scripts/ai/internal/lib/50-policy.sh` | internal-lib | private | Loaded through `scripts/ai/common.sh`. |
| `scripts/ai/internal/lib/60-exec-guard.sh` | internal-lib | private | Loaded through `scripts/ai/common.sh`. |
| `scripts/ai/internal/lib/70-secrets.sh` | internal-lib | private | Loaded through `scripts/ai/common.sh`. |
| `scripts/ai/internal/lib/80-tokens.sh` | internal-lib | private | Loaded through `scripts/ai/common.sh`. |
| `scripts/ai/internal/lib/90-snapshot.sh` | internal-lib | private | Loaded through `scripts/ai/common.sh`. |
| `scripts/ai/internal/search/00-bootstrap.sh` | internal-search | private | Loaded through `scripts/ai/ai-search.sh`. |
| `scripts/ai/internal/search/10-contract.sh` | internal-search | private | Loaded through `scripts/ai/ai-search.sh`. |
| `scripts/ai/internal/search/20-state.sh` | internal-search | private | Loaded through `scripts/ai/ai-search.sh`. |
| `scripts/ai/internal/search/25-modes.sh` | internal-search | private | Loaded through `scripts/ai/ai-search.sh`. |
| `scripts/ai/internal/search/30-parse-flags.sh` | internal-search | private | Loaded through `scripts/ai/ai-search.sh`. |
| `scripts/ai/internal/search/35-parse-positionals.sh` | internal-search | private | Loaded through `scripts/ai/ai-search.sh`. |
| `scripts/ai/internal/search/40-output-json.sh` | internal-search | private | Loaded through `scripts/ai/ai-search.sh`. |
| `scripts/ai/internal/search/45-results-rg.sh` | internal-search | private | Loaded through `scripts/ai/ai-search.sh`. |
| `scripts/ai/internal/search/50-results-context.sh` | internal-search | private | Loaded through `scripts/ai/ai-search.sh`. |
| `scripts/ai/internal/search/55-scope-args.sh` | internal-search | private | Loaded through `scripts/ai/ai-search.sh`. |
| `scripts/ai/internal/search/60-guards.sh` | internal-search | private | Loaded through `scripts/ai/ai-search.sh`. |
| `scripts/ai/internal/search/65-backend-files.sh` | internal-search | private | Loaded through `scripts/ai/ai-search.sh`. |
| `scripts/ai/internal/search/70-backend-text.sh` | internal-search | private | Loaded through `scripts/ai/ai-search.sh`. |
| `scripts/ai/internal/search/75-backend-git.sh` | internal-search | private | Loaded through `scripts/ai/ai-search.sh`. |
| `scripts/ai/internal/search/80-backend-curated.sh` | internal-search | private | Loaded through `scripts/ai/ai-search.sh`. |
| `scripts/ai/internal/search/85-backend-ast.sh` | internal-search | private | Loaded through `scripts/ai/ai-search.sh`. |
| `scripts/ai/internal/search/90-doctor.sh` | internal-search | private | Loaded through `scripts/ai/ai-search.sh`. |
| `scripts/ai/internal/search/95-dispatch.sh` | internal-search | private | Loaded through `scripts/ai/ai-search.sh`. |

## Migration status

- P0–P2: complete (inventory, classification, document-only mapping).
- P3a: complete — `ai-search/*.sh` modules relocated to `scripts/ai/internal/search/`,
  loaded through the `scripts/ai/ai-search.sh` facade.
- P3b: complete — `lib/*.sh` modules relocated to `scripts/ai/internal/lib/`, loaded
  through the `scripts/ai/common.sh` facade; `tools/ai/install/packs.php` `dir` entry
  and the `sh-introspect` index glob updated.
- P4 (Option B — additive `scripts/ai/bin/<role>/` delegating shims): complete.
  - P4.0: `internal/search` packs `dir` entry added (install parity).
  - P4.1–P4.3: all 41 public scripts have role/risk shims under
    `scripts/ai/bin/{read,context,verify,edit,admin,hooks}/`, shipped via the single
    `scripts/ai/bin` packs `dir` entry. Each shim `exec`s its canonical root impl and is
    byte-identical for `--introspect`/`--help`.
  - P4.4: registry unchanged (no `source_path`/`installed_path` edits; `registry:export`
    no-op). `common.sh` has no shim (sourced facade, not a command).
- P5 (finalize): complete — every public script has a shim, no orphans, full validator
  sweep + serial test suite + doc-check all pass.

### P4 bin/ alias contract (Option B)

- `scripts/ai/bin/<role>/<name>.sh` is a GENERATED delegating shim that `exec`s the
  canonical implementation at `scripts/ai/<name>.sh`. The canonical file STAYS at the
  root; `bin/` is a role/risk-addressable alias tree, not the implementation's home.
- `bin/` shims are intentionally UNREGISTERED (not in `script-registry.php`); they ship
  via a single `scripts/ai/bin` packs `dir` entry, so the registry↔packs 1:1 invariant
  is unaffected.
- Do not hand-edit shims; they are derived artifacts.

### Standing guardrails

- Preserve all current root script names; they remain the public/registered contract.
  `bin/` aliases never replace them.
- Private implementation modules live under `scripts/ai/internal/{lib,search}/` and are
  loaded only through their facades (`common.sh`, `ai-search.sh`); do not source them
  directly.

## P2 target path mapping (document-only)

This section is a planning reference, not a runtime layout. Nothing here moves a
file or creates an active path. The `Current path` column is the live public
contract; the `Future target path` column is the role/risk destination proposed
for the approval-gated P3-P5 phases. No `scripts/ai/bin/**` or
`scripts/ai/internal/**` path exists or is created by this section.

| Current path | Target role | Future target path (proposed, not active) |
| --- | --- | --- |
| `scripts/ai/ai-file-freshness.sh` | read | `scripts/ai/bin/read/ai-file-freshness.sh` |
| `scripts/ai/ai-search-introspect.sh` | read | `scripts/ai/bin/read/ai-search-introspect.sh` |
| `scripts/ai/ai-search-multi.sh` | read | `scripts/ai/bin/read/ai-search-multi.sh` |
| `scripts/ai/ai-search.sh` | read | `scripts/ai/bin/read/ai-search.sh` |
| `scripts/ai/fd-files.sh` | read | `scripts/ai/bin/read/fd-files.sh` |
| `scripts/ai/gh-pr-context.sh` | read | `scripts/ai/bin/read/gh-pr-context.sh` |
| `scripts/ai/git-branch-origin.sh` | read | `scripts/ai/bin/read/git-branch-origin.sh` |
| `scripts/ai/git-forensics.sh` | read | `scripts/ai/bin/read/git-forensics.sh` |
| `scripts/ai/preview-file.sh` | read | `scripts/ai/bin/read/preview-file.sh` |
| `scripts/ai/query-usage.sh` | read | `scripts/ai/bin/read/query-usage.sh` |
| `scripts/ai/repo-stats.sh` | read | `scripts/ai/bin/read/repo-stats.sh` |
| `scripts/ai/repo-tool-inventory.sh` | read | `scripts/ai/bin/read/repo-tool-inventory.sh` |
| `scripts/ai/rg-code.sh` | read | `scripts/ai/bin/read/rg-code.sh` |
| `scripts/ai/sh-introspect.sh` | read | `scripts/ai/bin/read/sh-introspect.sh` |
| `scripts/ai/ai-diff-context.sh` | context | `scripts/ai/bin/context/ai-diff-context.sh` |
| `scripts/ai/ai-structured.sh` | context | `scripts/ai/bin/context/ai-structured.sh` |
| `scripts/ai/ai-task.sh` | context | `scripts/ai/bin/context/ai-task.sh` |
| `scripts/ai/pack-context.sh` | context | `scripts/ai/bin/context/pack-context.sh` |
| `scripts/ai/repomix-context-tree.sh` | context | `scripts/ai/bin/context/repomix-context-tree.sh` |
| `scripts/ai/repomix-ensure-fresh.sh` | context | `scripts/ai/bin/context/repomix-ensure-fresh.sh` |
| `scripts/ai/repomix-freshness.sh` | context | `scripts/ai/bin/context/repomix-freshness.sh` |
| `scripts/ai/repomix-scc-router.sh` | context | `scripts/ai/bin/context/repomix-scc-router.sh` |
| `scripts/ai/run-repomix-context.sh` | context | `scripts/ai/bin/context/run-repomix-context.sh` |
| `scripts/ai/run-repomix-file.sh` | context | `scripts/ai/bin/context/run-repomix-file.sh` |
| `scripts/ai/ai-doc-check.sh` | verify | `scripts/ai/bin/verify/ai-doc-check.sh` |
| `scripts/ai/ai-install-coverage.sh` | verify | `scripts/ai/bin/verify/ai-install-coverage.sh` |
| `scripts/ai/ai-test-select.sh` | verify | `scripts/ai/bin/verify/ai-test-select.sh` |
| `scripts/ai/ai-verify-html.sh` | verify | `scripts/ai/bin/verify/ai-verify-html.sh` |
| `scripts/ai/ai-verify-js.sh` | verify | `scripts/ai/bin/verify/ai-verify-js.sh` |
| `scripts/ai/ai-verify-php.sh` | verify | `scripts/ai/bin/verify/ai-verify-php.sh` |
| `scripts/ai/ai-verify-ts.sh` | verify | `scripts/ai/bin/verify/ai-verify-ts.sh` |
| `scripts/ai/ai-verify-vue.sh` | verify | `scripts/ai/bin/verify/ai-verify-vue.sh` |
| `scripts/ai/ai-verify.sh` | verify | `scripts/ai/bin/verify/ai-verify.sh` |
| `scripts/ai/check-file-refs.sh` | verify | `scripts/ai/bin/verify/check-file-refs.sh` |
| `scripts/ai/list-todos.sh` | verify | `scripts/ai/bin/verify/list-todos.sh` |
| `scripts/ai/run-repo-tests.sh` | verify | `scripts/ai/bin/verify/run-repo-tests.sh` |
| `scripts/ai/run-test-focused.sh` | verify | `scripts/ai/bin/verify/run-test-focused.sh` |
| `scripts/ai/ship-audit.sh` | verify | `scripts/ai/bin/verify/ship-audit.sh` |
| `scripts/ai/ai-edit.sh` | edit | `scripts/ai/bin/edit/ai-edit.sh` |
| `scripts/ai/ai-rollback.sh` | edit | `scripts/ai/bin/edit/ai-rollback.sh` |
| `scripts/ai/all_in_one.sh` | admin | `scripts/ai/bin/admin/all_in_one.sh` |
| `scripts/ai/build-ai-help-bundle.sh` | admin | `scripts/ai/bin/admin/build-ai-help-bundle.sh` |
| `scripts/ai/install-mandatory-tools.sh` | admin | `scripts/ai/bin/admin/install-mandatory-tools.sh` |
| `scripts/ai/prune-shipped-targets.sh` | admin | `scripts/ai/bin/admin/prune-shipped-targets.sh` |
| `scripts/ai/post-tool-use.sh` | hooks | `scripts/ai/bin/hooks/post-tool-use.sh` |
| `scripts/ai/pre-tool-use.sh` | hooks | `scripts/ai/bin/hooks/pre-tool-use.sh` |
| `scripts/ai/session-checkpoint.sh` | hooks | `scripts/ai/bin/hooks/session-checkpoint.sh` |
| `scripts/ai/watch-loop.sh` | hooks | `scripts/ai/bin/hooks/watch-loop.sh` |
| `scripts/ai/common.sh` | internal-lib | facade kept at root; modules -> `scripts/ai/internal/lib/**` (DONE) |
| `scripts/ai/internal/lib/*.sh` | internal-lib | `scripts/ai/internal/lib/*.sh` (migrated) |
| `scripts/ai/internal/search/*.sh` | internal-search | `scripts/ai/internal/search/*.sh` (migrated) |

### P2 guardrails

- This mapping is document-only; do not create any path in the "Future target
  path" column during P2.
- Moving any file to a target path requires separate explicit approval (P3-P5).
- When P3-P5 begins, the current root names must remain resolvable via
  compatibility shims before any consumer reference is updated.
