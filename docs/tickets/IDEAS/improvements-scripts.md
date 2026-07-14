> **Correction (grounded review, 2026-07-09):** most of the "## TODO" list at the bottom of
> this file is **already implemented**, confirmed by direct file inspection:
>
> - `docs/ai/script-registry.json` exists (plus `docs/ai/script-registry.schema.json`).
> - `docs/ai/script-registry.md` exists.
> - `docs/ai/agent-script-access.md` exists, and is already the canonical per-tier
>   allow/ask/deny doc every agent's "Script Access" section points to (confirmed in this
>   repo's own rendered `implementer` agent body).
> - Agent permission frontmatter is already generated from a composed model
>   (`tools/ai/install/permission-layers/*`, `aiPermissionComposeFromSpec` over
>   `aiInstallerAgentProfiles`), not hand-written per this doc's original proposal.
> - `scripts/ai/bin/{read,context,verify,edit,admin,hooks}/` (role-grouped internals) and
>   `scripts/ai/internal/**` exist exactly as this doc's "Recommended source-of-truth split"
>   describes; the ~42 root `scripts/ai/*.sh` wrappers are the frozen, intended public
>   contract (see `docs/tickets/MASTER-INDEX.md` "P6 REJECTED" — a later, conflicting 3rd
>   reorg proposal was explicitly rejected to keep this exact shape).
>
> What is **not yet fully closed**, per `docs/tickets/claude-agent-fleet-remediation/plan-28-permission-sot-and-render-parity-sync.md`
> (in progress — see working tree): the script-registry -> permission-frontmatter pipeline is
> only one of three still-partially-unlinked enforcement surfaces (composed body vs.
> `.claude/settings.json` vs. `command-policy.tiers.yaml`), and there is still no byte-parity
> render gate covering `.claude/agents` + `.github/agents` end-to-end (Phase 1 of plan-28 is
> landing this now). Treat plan-28 as the authoritative, near-complete answer to this file's
> "Add a machine registry" / "Frontmatter permission pattern" sections — do not restart that
> design; see `docs/tickets/IDEAS/architecture-plan.md` §"Scripts And Permissions".

## Best wiring model

Do **not** wire scripts directly into every agent. Wire them through a registry + capability + workflow + permission layer.

```text id="cjy6k2"
scripts/ai/*
  → docs/ai/script-registry.json
  → docs/ai/script-registry.md
  → docs/ai/capabilities/ai-scripts/*
  → templates/workflows/*
  → templates/commands/*
  → agent permission frontmatter
```

## Recommended source-of-truth split

| Layer                          | Purpose                          | Should contain                                                        |
| ------------------------------ | -------------------------------- | --------------------------------------------------------------------- |
| `scripts/ai/bin/**`            | Canonical executable scripts     | Real script implementations grouped by read/context/edit/verify/hooks |
| `scripts/ai/*.sh`              | Stable public aliases            | Thin compatibility wrappers only                                      |
| `scripts/ai/internal/**`       | Private implementation internals | Never exposed to agents directly                                      |
| `scripts/ai/.ai-logs/**`       | Runtime evidence/log output      | Never executable; read only when auditing                             |
| `scripts/ai/MANIFEST.md`       | Human script inventory           | High-level list and ownership                                         |
| `docs/ai/script-registry.json` | Machine registry                 | Risk, category, path, outputs, allowed agents                         |
| `docs/ai/script-registry.md`   | Human registry                   | Usage table and examples                                              |
| `templates/workflows/*.md`     | Process wiring                   | Which scripts each workflow may use                                   |
| `templates/commands/*.md`      | User entrypoints                 | Which workflow to invoke                                              |
| `templates/*/agents/*.md`      | Permission enforcement           | Per-script `allow` / `ask` / `deny`                                   |

## Script categories

### 1. Read scripts — safe for most read-only agents

```text id="6a3xes"
scripts/ai/bin/read/ai-search.sh
scripts/ai/bin/read/ai-search-multi.sh
scripts/ai/bin/read/ai-search-introspect.sh
scripts/ai/bin/read/fd-files.sh
scripts/ai/bin/read/gh-pr-context.sh
scripts/ai/bin/read/git-branch-origin.sh
scripts/ai/bin/read/git-forensics.sh
scripts/ai/bin/read/preview-file.sh
scripts/ai/bin/read/query-usage.sh
scripts/ai/bin/read/repo-stats.sh
scripts/ai/bin/read/repo-tool-inventory.sh
scripts/ai/bin/read/rg-code.sh
scripts/ai/bin/read/sh-introspect.sh
scripts/ai/bin/read/ai-file-freshness.sh
```

Wire to:

```text id="xezy0q"
workflows/search-evidence.md
workflows/repo-investigation.md
workflows/project-context.md
workflows/review-diff.md
workflows/script-inventory.md
```

Allow for:

```text id="ey3lyr"
researcher
repository-researcher
repository-reviewer
reviewer
architect
architecture-plan-writer
agent-critic
agent-fleet-assessor
workflow-auditor
```

Default permission: **allow**.

---

### 2. Context packaging scripts — useful but heavier

```text id="ag6nqg"
scripts/ai/bin/context/ai-diff-context.sh
scripts/ai/bin/context/ai-structured.sh
scripts/ai/bin/context/ai-task.sh
scripts/ai/bin/context/pack-context.sh
scripts/ai/bin/context/repomix-context-tree.sh
scripts/ai/bin/context/repomix-ensure-fresh.sh
scripts/ai/bin/context/repomix-freshness.sh
scripts/ai/bin/context/repomix-scc-router.sh
scripts/ai/bin/context/run-repomix-context.sh
scripts/ai/bin/context/run-repomix-file.sh
```

Wire to:

```text id="mo71a0"
workflows/project-context.md
workflows/architecture-plan.md
workflows/plan-slice.md
workflows/review-diff.md
workflows/repository-review.md
workflows/agent-fleet-assessment.md
```

Allow for:

```text id="q0vgvq"
architect
architecture-plan-writer
repository-reviewer
reviewer
agent-fleet-assessor
workflow-auditor
```

Default permission: **ask** for heavy/context-packaging scripts, **allow** for lightweight freshness/diff-context.

Recommended:

| Script                    | Default |
| ------------------------- | ------: |
| `ai-diff-context.sh`      |   allow |
| `ai-structured.sh`        |   allow |
| `repomix-freshness.sh`    |   allow |
| `repomix-ensure-fresh.sh` |     ask |
| `pack-context.sh`         |     ask |
| `run-repomix-context.sh`  |     ask |
| `run-repomix-file.sh`     |     ask |
| `ai-task.sh`              |     ask |
| `repomix-scc-router.sh`   |   allow |
| `repomix-context-tree.sh` |   allow |

---

### 3. Verify scripts — allowed for implement/review/verify agents

```text id="c33txe"
scripts/ai/bin/verify/ai-doc-check.sh
scripts/ai/bin/verify/ai-install-coverage.sh
scripts/ai/bin/verify/ai-test-select.sh
scripts/ai/bin/verify/ai-verify.sh
scripts/ai/bin/verify/check-file-refs.sh
scripts/ai/bin/verify/list-todos.sh
scripts/ai/bin/verify/run-repo-tests.sh
scripts/ai/bin/verify/run-test-focused.sh
scripts/ai/bin/verify/ship-audit.sh
```

Wire to:

```text id="qxdrie"
workflows/verify-change.md
workflows/bug-regression.md
workflows/regression-test.md
workflows/review-diff.md
workflows/release-safety.md
workflows/docs-sync.md
workflows/install.md
workflows/post-install-setup.md
workflows/workflow-audit.md
```

Allow for:

```text id="r0znyd"
implementer
bugfix
upgrade
refactorer
ui-builder
build-config
reviewer
release-auditor
post-install
workflow-auditor
```

Default permission:

| Script                   | Default |
| ------------------------ | ------: |
| `run-test-focused.sh`    |   allow |
| `ai-test-select.sh`      |   allow |
| `ai-doc-check.sh`        |   allow |
| `check-file-refs.sh`     |   allow |
| `list-todos.sh`          |   allow |
| `ai-verify.sh`           |     ask |
| `run-repo-tests.sh`      |     ask |
| `ship-audit.sh`          |     ask |
| `ai-install-coverage.sh` |     ask |

Reason: broad repo verification can be expensive/noisy, so it should usually be `ask`.

---

### 4. Edit scripts — only for write-capable agents

```text id="bde1ma"
scripts/ai/bin/edit/ai-edit.sh
scripts/ai/bin/edit/ai-rollback.sh
```

Wire to:

```text id="kdub73"
workflows/new-feature.md
workflows/bug-regression.md
workflows/refactor-slice.md
workflows/dependency-upgrade.md
workflows/docs-sync.md
workflows/replace-placeholders.md
```

Allow for:

```text id="b5lj7b"
implementer
bugfix
refactorer
upgrade
docs
build-config
ui-builder
```

Default permission:

| Script           | Default |
| ---------------- | ------: |
| `ai-edit.sh`     |     ask |
| `ai-rollback.sh` |     ask |

Deny for:

```text id="0490lr"
researcher
repository-researcher
reviewer
repository-reviewer
architect
architecture-plan-writer
agent-critic
agent-fleet-assessor
workflow-auditor
agent-creator-static-validator
agent-creator-semantic-verifier
```

---

### 5. Hook/runtime scripts — guardian/supervisor only

```text id="kompa7"
scripts/ai/bin/hooks/pre-tool-use.sh
scripts/ai/bin/hooks/post-tool-use.sh
scripts/ai/bin/hooks/session-checkpoint.sh
scripts/ai/bin/hooks/watch-loop.sh
```

Wire to:

```text id="749zsz"
workflows/runtime-guardrail-audit.md
workflows/agent-creation-pipeline.md
workflows/evidence-first-execution.md
```

Allow for:

```text id="ui0pny"
agent-creator-runtime-guardian
agent-creator-supervisor
workflow-auditor
```

Default permission:

| Script                  | Default |
| ----------------------- | ------: |
| `pre-tool-use.sh`       |     ask |
| `post-tool-use.sh`      |     ask |
| `session-checkpoint.sh` |     ask |
| `watch-loop.sh`         |    deny |

`watch-loop.sh` should stay denied by default because it implies long-running/background behaviour.

---

### 6. Admin scripts — maintainer/install only

You have:

```text id="63tcl7"
scripts/ai/bin/admin
scripts/ai/install-mandatory-tools.sh
scripts/ai/prune-shipped-targets.sh
scripts/ai/build-ai-help-bundle.sh
scripts/ai/all_in_one.sh
```

Wire to:

```text id="9r36cv"
workflows/install.md
workflows/post-install-setup.md
workflows/scan-stack.md
workflows/workflow-audit.md
```

Allow for:

```text id="54s8fu"
bootstrapper
post-install
workflow-auditor
release-auditor
```

Default permission:

| Script                       |     Default |
| ---------------------------- | ----------: |
| `install-mandatory-tools.sh` | deny or ask |
| `prune-shipped-targets.sh`   |         ask |
| `build-ai-help-bundle.sh`    |         ask |
| `all_in_one.sh`              |        deny |

`all_in_one.sh` should not be available to normal agents. It is too broad.

---

## Add a machine registry

Create or expand:

```text id="bh1icx"
docs/ai/script-registry.json
```

Suggested shape:

```json id="35l1hu"
{
  "id": "ai-search",
  "category": "read",
  "canonical_path": "scripts/ai/bin/read/ai-search.sh",
  "public_alias": "scripts/ai/ai-search.sh",
  "risk": "low",
  "writes": false,
  "destructive": false,
  "long_running": false,
  "requires_clean_tree": false,
  "outputs": ["stdout", "json_optional"],
  "primary_workflows": [
    "search-evidence",
    "repo-investigation",
    "project-context"
  ],
  "allowed_agents": [
    "researcher",
    "repository-researcher",
    "architect",
    "reviewer"
  ],
  "default_permission": "allow"
}
```

Required fields:

```text id="k50gte"
id
category
canonical_path
public_alias
risk
writes
destructive
long_running
requires_clean_tree
outputs
primary_workflows
allowed_agents
default_permission
```

## Add a human script access doc

Create:

```text id="fprz18"
docs/ai/agent-script-access.md
```

Structure:

```md id="gn6ygf"
# Agent Script Access

## Permission tiers

- allow: safe, bounded, read-only or focused verification
- ask: broad, expensive, write-capable, rollback, install, generated, or runtime-control
- deny: destructive, internal-only, long-running, or too broad

## Read scripts

## Context scripts

## Verify scripts

## Edit scripts

## Hook/runtime scripts

## Admin scripts

## Internal scripts

`internal/**` is never called directly by agents.
```

Then each agent can say:

```md id="k31epp"
## Script Access

Full script access rules are defined in `docs/ai/agent-script-access.md`.
This agent may only use scripts explicitly allowed in its permission frontmatter.
```

## Frontmatter permission pattern

For OpenCode-style agents, generate per-agent rules like this:

```yaml id="tb5x6g"
permission:
  bash:
    "*": deny

    # Safe read
    "bash scripts/ai/ai-search.sh *": allow
    "bash scripts/ai/fd-files.sh *": allow
    "bash scripts/ai/preview-file.sh *": allow
    "bash scripts/ai/git-forensics.sh *": allow

    # Context
    "bash scripts/ai/ai-diff-context.sh *": allow
    "bash scripts/ai/repomix-freshness.sh *": allow
    "bash scripts/ai/run-repomix-context.sh *": ask

    # Verification
    "bash scripts/ai/run-test-focused.sh *": allow
    "bash scripts/ai/ai-verify.sh *": ask
    "bash scripts/ai/run-repo-tests.sh *": ask

    # Mutation
    "bash scripts/ai/ai-edit.sh *": ask
    "bash scripts/ai/ai-rollback.sh *": ask

    # Runtime hooks
    "bash scripts/ai/pre-tool-use.sh *": deny
    "bash scripts/ai/post-tool-use.sh *": deny
    "bash scripts/ai/session-checkpoint.sh *": deny
    "bash scripts/ai/watch-loop.sh *": deny

    # Internals
    "bash scripts/ai/internal/*": deny
    "bash scripts/ai/bin/*": deny
```

Important: agents should usually call the stable aliases:

```text id="45fvgq"
scripts/ai/ai-search.sh
scripts/ai/ai-verify.sh
scripts/ai/run-test-focused.sh
```

not:

```text id="b6n7sd"
scripts/ai/bin/read/ai-search.sh
scripts/ai/internal/search/...
```

## Recommended agent access matrix

| Agent group                                      |  Read |   Context |    Verify |                Edit |                        Hooks | Admin |
| ------------------------------------------------ | ----: | --------: | --------: | ------------------: | ---------------------------: | ----: |
| `researcher`, `repository-researcher`            | allow | allow/ask |      deny |                deny |                         deny |  deny |
| `architect`, `architecture-plan-writer`          | allow | allow/ask |  deny/ask |                deny |                         deny |  deny |
| `implementer`, `bugfix`, `refactorer`, `upgrade` | allow |     allow | allow/ask |                 ask |                         deny |  deny |
| `reviewer`, `repository-reviewer`                | allow | allow/ask |       ask |                deny |                         deny |  deny |
| `docs`                                           | allow |     allow |     allow |       ask docs-only |                         deny |  deny |
| `build-config`, `ui-builder`                     | allow |     allow | allow/ask |          ask scoped |                         deny |  deny |
| `release-auditor`                                | allow |     allow |       ask |                deny |                         deny |   ask |
| `post-install`, `bootstrapper`                   | allow |     allow |       ask | ask install-surface |                         deny |   ask |
| `agent-creator-*`                                | allow | allow/ask |       ask |                deny | ask only guardian/supervisor |  deny |
| `workflow-auditor`                               | allow |     allow |       ask |                deny |               ask audit-only |   ask |

## Workflow-to-script wiring

Add a `Scripts Used` section to every workflow.

Example for `repo-investigation.md`:

```md id="qbf1eu"
## Scripts Used

Preferred:

- `bash scripts/ai/ai-search.sh text "$ARGUMENTS" . --fixed`
- `bash scripts/ai/fd-files.sh "$ARGUMENTS"`
- `bash scripts/ai/preview-file.sh <path>`
- `bash scripts/ai/git-forensics.sh <path-or-pattern>`
- `bash scripts/ai/gh-pr-context.sh <pr-or-branch>`

Denied:

- `bash scripts/ai/ai-edit.sh *`
- `bash scripts/ai/ai-rollback.sh *`
- `bash scripts/ai/run-repo-tests.sh *`
```

Example for `bug-regression.md`:

```md id="7xuhvl"
## Scripts Used

Preferred:

- `bash scripts/ai/ai-search.sh text "$ARGUMENTS" . --fixed`
- `bash scripts/ai/run-test-focused.sh <test>`
- `bash scripts/ai/ai-test-select.sh <changed-path>`
- `bash scripts/ai/ai-verify.sh <scope>`

Ask before:

- `bash scripts/ai/run-repo-tests.sh *`
- `bash scripts/ai/ai-edit.sh *`
- `bash scripts/ai/ai-rollback.sh *`
```

Example for `review-diff.md`:

```md id="0z6c5q"
## Scripts Used

Preferred:

- `bash scripts/ai/ai-diff-context.sh`
- `bash scripts/ai/ai-search.sh changed-text "$ARGUMENTS" . --fixed`
- `bash scripts/ai/ai-search.sh staged-text "$ARGUMENTS" . --fixed`
- `bash scripts/ai/check-file-refs.sh`
- `bash scripts/ai/ai-doc-check.sh`

Ask before:

- `bash scripts/ai/ship-audit.sh`
- `bash scripts/ai/ai-verify.sh`

Denied:

- `bash scripts/ai/ai-edit.sh *`
```

## Command-to-script rule

Commands should not directly list many scripts. Commands should load a workflow.

Good:

```md id="o4i5ld"
Use workflow: `templates/workflows/repo-investigation.md`
```

Avoid:

```md id="r2nbab"
Run these 12 scripts...
```

Exception: very thin utility commands such as `search-evidence.md` and `verify.md` can name exact scripts.

## Permission tier recommendation

| Tier   | Meaning                                  | Example scripts                                                |
| ------ | ---------------------------------------- | -------------------------------------------------------------- |
| Tier 0 | Internal/private; never direct agent use | `scripts/ai/internal/**`, `common.sh`                          |
| Tier 1 | Safe read-only                           | `ai-search.sh`, `fd-files.sh`, `preview-file.sh`, `rg-code.sh` |
| Tier 2 | Read-only but heavy/context-building     | `pack-context.sh`, `run-repomix-context.sh`                    |
| Tier 3 | Focused verification                     | `run-test-focused.sh`, `ai-test-select.sh`, `ai-doc-check.sh`  |
| Tier 4 | Broad verification/release audit         | `run-repo-tests.sh`, `ship-audit.sh`, `ai-install-coverage.sh` |
| Tier 5 | Mutation/rollback/install/runtime        | `ai-edit.sh`, `ai-rollback.sh`, hooks, admin scripts           |

Default policy:

```text id="h4k30b"
Tier 0: deny
Tier 1: allow
Tier 2: ask
Tier 3: allow or ask by agent
Tier 4: ask
Tier 5: ask only for write/admin/guardian agents; deny for read-only agents
```

## Most important rule

Expose only these to agents:

```text id="2fc7xk"
scripts/ai/*.sh
```

Do not expose these directly:

```text id="4652iv"
scripts/ai/bin/**
scripts/ai/internal/**
scripts/ai/.ai-logs/**
scripts/ai/common.sh
```

`bin/**` and `internal/**` are implementation details. Agents should call stable top-level wrappers so you can move internals without breaking agent instructions.

## TODO

```md id="w6g8mf"
- [ ] Create `docs/ai/script-registry.json` with one entry per public `scripts/ai/*.sh` wrapper.
- [ ] Mark each script with category, risk, writes, destructive, long_running, workflows, agents, and default_permission.
- [ ] Create `docs/ai/agent-script-access.md` from the registry.
- [ ] Add `Scripts Used` sections to workflows, not commands.
- [ ] Generate agent permission frontmatter from the registry.
- [ ] Deny direct agent access to `scripts/ai/bin/**`, `scripts/ai/internal/**`, `.ai-logs/**`, and `common.sh`.
- [ ] Keep top-level `scripts/ai/*.sh` wrappers as the public stable API.
- [ ] Add validator: every script referenced by an agent or workflow must exist in `script-registry.json`.
- [ ] Add validator: every registered script must have at least one owning workflow.
- [ ] Add validator: write-capable scripts must never be `allow` for read-only agents.
```

## Target design score

| Design                                                              |  Score |
| ------------------------------------------------------------------- | -----: |
| Agents manually call arbitrary scripts                              | 45/100 |
| Agents call top-level scripts with manual permissions               | 70/100 |
| Registry + workflow wiring + generated permission frontmatter       | 92/100 |
| Registry + validator + generated docs + generated agent permissions | 97/100 |
