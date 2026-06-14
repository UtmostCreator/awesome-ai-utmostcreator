> STATUS (verified against live repo): partially landed; central premise
> inverted by the actual reorg. Corrections:
>
> - INVERTED PREMISE — P3/P9 propose "move scripts into numbered lifecycle
>   folders, keep wrappers at old root paths". Reality: root paths remain the
>   canonical impls; `scripts/ai/bin/{read,verify,context,edit,hooks,admin}/`
>   hold GENERATED DELEGATING SHIMS, and roles are word-based, not numbered.
> - ALREADY-DONE — P0 inventories: `scripts/ai/MANIFEST.md` (scripts, with
>   role/risk) and `docs/ai/AGENTS-MANIFEST.md` (agents) exist.
> - ALREADY-DONE — P4 `.github/ai-script-access.yaml`: exists (T0..T5 tiers,
>   per-agent `allowed_tiers`/`denied_scripts`), enforced by
>   `tests/php/AiScriptAccessManifestTest.php` (dangerous-only-in-T5,
>   runtime-guardian-only tool hooks, every script in exactly one tier).
> - STALE PATHS — `scripts/ai/ai-search/*` and `scripts/ai/lib/*` moved to
>   `scripts/ai/internal/{search,lib}/*`. Verification `find ... maxdepth 2`
>   misses scripts now at depth 3 (`scripts/ai/bin/<role>/*.sh`); use maxdepth 3.
> - SUPERSEDED — `MIGRATION.md` was never created; migration is tracked under
>   `docs/tickets/arch-todo-restructure-scripts-ai-*`.
> - STILL-VALID — P1 lifecycle groups, P2 agent renames (no `NN-` prefixes
>   exist), P5 `.github/ai-agent-lifecycle.yaml`, P6 `agent_score:` metadata,
>   P7 output contracts in agents, P8 `.github/agent-permissions/` templates.

## Recommendation

**Use numbered naming for orchestration/lifecycle clarity, but do not number every script blindly.**

Best approach:

```text
Agents: yes, optional numeric prefixes help lifecycle order.
Scripts: use category folders + stable descriptive names.
Internal modules: numeric prefixes are good.
Public entrypoints: keep descriptive names, add aliases if renamed.
```

# Advanced TODO: Unify Agent Orchestration, Agent Naming, Script Access, and Verification

## Goal

Create one coherent AI-agent workflow where:

- Agent names reflect lifecycle order.
- Agent responsibilities are non-overlapping.
- Script access is assigned by role and risk tier.
- Mutation is gated by scope, validation, and review.
- Old names continue working during migration.
- The system can be audited automatically.

---

# P0 — Freeze Current State Before Renaming

## TODO

- [ ] Create a complete inventory of current agents.
- [ ] Create a complete inventory of current scripts.
- [ ] Mark each agent as:
  - `orchestrator`
  - `research`
  - `planning`
  - `execution`
  - `validation`
  - `review`
  - `release`
  - `post-install`

- [ ] Mark each script as:
  - `read-only`
  - `context-builder`
  - `validator`
  - `test-runner`
  - `mutator`
  - `recovery`
  - `dangerous-or-broad`

- [ ] Add a temporary `MIGRATION.md` file that records every old name, proposed new name, and migration status.

## Acceptance criteria

- [ ] Every current `.agent.md` file appears in the inventory.
- [ ] Every current `scripts/ai/*.sh` entrypoint appears in the inventory.
- [ ] Internal modules under `scripts/ai/ai-search/*.sh` and `scripts/ai/lib/*.sh` are listed as internal-only.
- [ ] No rename happens yet.

## Verification

```bash
find .github/agents -maxdepth 1 -type f -name '*.agent.md' | sort
find scripts/ai -maxdepth 1 -type f -name '*.sh' | sort
find scripts/ai/internal/search scripts/ai/internal/lib -type f -name '*.sh' | sort
```

---

# P1 — Define Canonical Lifecycle Groups

## TODO

Create lifecycle groups:

```text
00-orchestration
10-discovery
20-planning
30-execution
40-validation
50-review
60-release
70-post-install
80-agent-factory
90-runtime-safety
```

## Recommended agent lifecycle

| Lifecycle           | Purpose                                        |
| ------------------- | ---------------------------------------------- |
| `00-orchestration`  | Decides sequence, prevents unsafe handoffs     |
| `10-discovery`      | Reads repo, gathers context, no mutation       |
| `20-planning`       | Creates scope contract, ACs, verification plan |
| `30-execution`      | Performs scoped changes                        |
| `40-validation`     | Deterministic checks                           |
| `50-review`         | Semantic/correctness review                    |
| `60-release`        | Ship/no-ship decision                          |
| `70-post-install`   | Install/drift verification                     |
| `80-agent-factory`  | Creates and validates agents                   |
| `90-runtime-safety` | Tool and execution safety                      |

## Acceptance criteria

- [ ] Each lifecycle group has a written purpose.
- [ ] Each group has allowed agent types.
- [ ] Each group has allowed script tiers.
- [ ] No agent belongs to more than two lifecycle groups.

## Verification

```bash
grep -R "lifecycle:" .github/agents packages -n || true
grep -R "allowed_script_tiers:" .github/agents packages -n || true
```

---

# P2 — Decide Naming Strategy

## Decision

Use **numbered agent filenames**, but keep **stable internal agent IDs**.

Example:

```yaml
id: architect
display_name: 20 Architect
```

Filename can become:

```text
20-architect.agent.md
```

But the internal `id` should remain:

```yaml
id: architect
```

This avoids breaking references that depend on the agent ID.

## Agent rename map

| Current file                               | Proposed canonical filename            | Lifecycle                     |
| ------------------------------------------ | -------------------------------------- | ----------------------------- |
| `agent-creator-supervisor.agent.md`        | `80-agent-factory-supervisor.agent.md` | `80-agent-factory`            |
| `agent-creator-runtime-guardian.agent.md`  | `90-agent-runtime-guardian.agent.md`   | `90-runtime-safety`           |
| `agent-creator-static-validator.agent.md`  | `80-agent-static-validator.agent.md`   | `80-agent-factory`            |
| `agent-creator-semantic-verifier.agent.md` | `80-agent-semantic-verifier.agent.md`  | `80-agent-factory`            |
| `agent-creator.agent.md`                   | `80-agent-creator.agent.md`            | `80-agent-factory`            |
| `architect.agent.md`                       | `20-architect.agent.md`                | `20-planning`                 |
| `architecture-plan-writer.agent.md`        | `21-architecture-plan-writer.agent.md` | `20-planning`                 |
| `repository-researcher.agent.md`           | `10-repository-researcher.agent.md`    | `10-discovery`                |
| `researcher.agent.md`                      | `11-researcher.agent.md`               | `10-discovery`                |
| `repository-reviewer.agent.md`             | `12-repository-reviewer.agent.md`      | `10-discovery` / `50-review`  |
| `implementer.agent.md`                     | `30-implementer.agent.md`              | `30-execution`                |
| `bugfix.agent.md`                          | `31-bugfix.agent.md`                   | `30-execution`                |
| `refactorer.agent.md`                      | `32-refactorer.agent.md`               | `30-execution`                |
| `config-maintainer.agent.md`               | `33-config-maintainer.agent.md`        | `30-execution`                |
| `build-config.agent.md`                    | `34-build-config.agent.md`             | `30-execution`                |
| `upgrade.agent.md`                         | `35-upgrade.agent.md`                  | `30-execution`                |
| `docs.agent.md`                            | `36-docs.agent.md`                     | `30-execution` / `60-release` |
| `workflow-auditor.agent.md`                | `40-workflow-auditor.agent.md`         | `40-validation`               |
| `infra-auditor.agent.md`                   | `41-infra-auditor.agent.md`            | `40-validation`               |
| `reviewer.agent.md`                        | `50-reviewer.agent.md`                 | `50-review`                   |
| `release-auditor.agent.md`                 | `60-release-auditor.agent.md`          | `60-release`                  |
| `post-install.agent.md`                    | `70-post-install.agent.md`             | `70-post-install`             |

## Acceptance criteria

- [ ] Every old agent has one canonical new filename.
- [ ] Internal `id` values remain stable unless there is a separate migration plan.
- [ ] No duplicate lifecycle number has ambiguous ownership.
- [ ] Agent creation agents are clearly separated from normal repo agents.

## Verification

```bash
find .github/agents -maxdepth 1 -type f -name '*.agent.md' | sort
grep -R "^id:" .github/agents -n
```

---

# P3 — Decide Script Naming Strategy

## Recommendation

Do **not** rename all public scripts to numeric names.

Use this rule:

| Script type                      | Naming style                         |
| -------------------------------- | ------------------------------------ |
| Public entrypoints               | Descriptive names                    |
| Internal modules                 | Numbered names                       |
| Lifecycle orchestration wrappers | Numbered names allowed               |
| Dangerous scripts                | Descriptive names with explicit risk |
| Compatibility wrappers           | Keep old names temporarily           |

## Keep as stable public entrypoints

```text
ai-search.sh
ai-search-multi.sh
ai-search-introspect.sh
sh-introspect.sh
preview-file.sh
rg-code.sh
fd-files.sh
ai-verify.sh
ai-doc-check.sh
run-repo-tests.sh
run-test-focused.sh
ai-diff-context.sh
git-forensics.sh
repo-tool-inventory.sh
repo-stats.sh
```

## Good candidates for grouped folders

```text
scripts/ai/
  00-help/
  10-discovery/
  20-context/
  30-planning/
  40-validation/
  50-review/
  60-release/
  70-install/
  90-safety/
  99-danger/
```

## Proposed script folder map

| Current script               | Proposed category | Rename?              |
| ---------------------------- | ----------------- | -------------------- |
| `ai-search.sh`               | `10-discovery`    | No                   |
| `ai-search-multi.sh`         | `10-discovery`    | No                   |
| `fd-files.sh`                | `10-discovery`    | No                   |
| `rg-code.sh`                 | `10-discovery`    | No                   |
| `preview-file.sh`            | `10-discovery`    | No                   |
| `pack-context.sh`            | `20-context`      | No                   |
| `repomix-context-tree.sh`    | `20-context`      | No                   |
| `run-repomix-context.sh`     | `20-context`      | No                   |
| `run-repomix-file.sh`        | `20-context`      | No                   |
| `ai-diff-context.sh`         | `50-review`       | No                   |
| `gh-pr-context.sh`           | `50-review`       | No                   |
| `git-forensics.sh`           | `50-review`       | No                   |
| `git-branch-origin.sh`       | `50-review`       | No                   |
| `ai-task.sh`                 | `30-planning`     | No                   |
| `ai-structured.sh`           | `30-planning`     | No                   |
| `ai-verify.sh`               | `40-validation`   | No                   |
| `ai-doc-check.sh`            | `40-validation`   | No                   |
| `check-file-refs.sh`         | `40-validation`   | No                   |
| `ai-test-select.sh`          | `40-validation`   | No                   |
| `run-test-focused.sh`        | `40-validation`   | No                   |
| `run-repo-tests.sh`          | `40-validation`   | No                   |
| `ai-install-coverage.sh`     | `70-install`      | No                   |
| `ai-file-freshness.sh`       | `70-install`      | No                   |
| `repomix-freshness.sh`       | `70-install`      | No                   |
| `repomix-ensure-fresh.sh`    | `70-install`      | No                   |
| `pre-tool-use.sh`            | `90-safety`       | No                   |
| `post-tool-use.sh`           | `90-safety`       | No                   |
| `ai-rollback.sh`             | `99-danger`       | No                   |
| `prune-shipped-targets.sh`   | `99-danger`       | No                   |
| `install-mandatory-tools.sh` | `99-danger`       | No                   |
| `all_in_one.sh`              | `99-danger`       | Consider deprecating |
| `watch-loop.sh`              | `99-danger`       | Consider deprecating |

## Acceptance criteria

- [ ] Public script names remain descriptive.
- [ ] Folder grouping is lifecycle-based.
- [ ] Existing script names still work through wrappers or symlinks.
- [ ] Dangerous scripts are not mixed with read-only discovery scripts.
- [ ] Internal numeric modules stay internal.

## Verification

```bash
find scripts/ai -maxdepth 3 -type f -name '*.sh' | sort
bash scripts/ai/ai-search.sh doctor .
bash scripts/ai/repo-tool-inventory.sh
```

---

# P4 — Add Script Access Manifest

## TODO

Create:

```text
.github/ai-script-access.yaml
```

## Required schema

```yaml
version: 1

tiers:
  T0_help:
    risk: low
    scripts: []

  T1_discovery:
    risk: low
    scripts: []

  T2_context:
    risk: low_medium
    scripts: []

  T3_validation:
    risk: medium
    scripts: []

  T4_planning:
    risk: medium
    scripts: []

  T5_mutation_recovery:
    risk: high_critical
    scripts: []

agents:
  architect:
    lifecycle: "20-planning"
    allowed_tiers: ["T0_help", "T1_discovery", "T2_context", "T4_planning"]
    denied_scripts: ["ai-edit.sh", "ai-rollback.sh", "prune-shipped-targets.sh"]

  implementer:
    lifecycle: "30-execution"
    allowed_tiers: ["T0_help", "T1_discovery", "T3_validation"]
    denied_scripts:
      [
        "prune-shipped-targets.sh",
        "install-mandatory-tools.sh",
        "all_in_one.sh",
        "watch-loop.sh",
      ]
```

## Acceptance criteria

- [ ] Every agent has `allowed_tiers`.
- [ ] Every script appears in exactly one tier.
- [ ] Dangerous scripts appear only in `T5_mutation_recovery`.
- [ ] Runtime guardian is the only normal agent allowed to use `pre-tool-use.sh` and `post-tool-use.sh`.
- [ ] No read-only agent has mutation scripts.

## Verification

```bash
test -f .github/ai-script-access.yaml
grep -n "T5_mutation_recovery" .github/ai-script-access.yaml
grep -n "pre-tool-use.sh" .github/ai-script-access.yaml
grep -n "post-tool-use.sh" .github/ai-script-access.yaml
```

---

# P5 — Add Agent Lifecycle Manifest

## TODO

Create:

```text
.github/ai-agent-lifecycle.yaml
```

## Required schema

```yaml
version: 1

events:
  E00_intake:
    owner: supervisor
    required_output:
      - task_summary
      - risk_estimate

  E10_scope_contract:
    owner: architect
    required_output:
      - in_scope
      - out_of_scope
      - allowed_paths
      - blocked_paths
      - stop_conditions

  E20_repository_read:
    owner: repository-researcher
    required_output:
      - related_files
      - existing_patterns
      - risks

  E30_architecture_gate:
    owner: architect
    required_output:
      - approved_plan_or_block

  E40_plan_written:
    owner: architecture-plan-writer
    required_output:
      - ordered_todo
      - acceptance_criteria
      - verification_steps

  E50_edit_gate:
    owner: runtime-guardian
    required_output:
      - allowed_paths_confirmed
      - denied_paths_confirmed
      - mutation_allowed

  E60_implementation:
    owner:
      - implementer
      - bugfix
      - refactorer
      - config-maintainer
      - build-config
      - docs
      - upgrade
    required_output:
      - changed_files
      - rationale

  E70_self_check:
    owner: editing_agent
    required_output:
      - local_verification
      - remaining_risks

  E80_static_validation:
    owner:
      - workflow-auditor
      - agent-static-validator
    required_output:
      - deterministic_failures
      - pass_or_fail

  E90_semantic_review:
    owner:
      - reviewer
      - agent-semantic-verifier
    required_output:
      - correctness_review
      - approve_or_block

  E100_runtime_smoke:
    owner:
      - runtime-guardian
      - build-config
    required_output:
      - smoke_result
      - failed_commands

  E110_release_audit:
    owner: release-auditor
    required_output:
      - ship_decision
      - release_risks

  E120_post_install:
    owner: post-install
    required_output:
      - installed_files
      - drift_report
      - missing_files
```

## Acceptance criteria

- [ ] Every event has one owner or explicit owner list.
- [ ] Every event has required output.
- [ ] Mutation cannot happen before E50.
- [ ] Release cannot happen before E80, E90, and E100 where applicable.

## Verification

```bash
test -f .github/ai-agent-lifecycle.yaml
grep -n "E50_edit_gate" .github/ai-agent-lifecycle.yaml
grep -n "E110_release_audit" .github/ai-agent-lifecycle.yaml
```

---

# P6 — Add Agent Score Contract

## TODO

Every agent must expose this metadata:

```yaml
agent_score:
  design_readiness: 0
  execution_trust: 0
  risk_level: low
  can_mutate: false
  can_gate: false
  requires_scope_contract: true
  requires_review_after: true
```

## Target scoring

| Agent                         | Design readiness | Execution trust | Can mutate | Can gate |
| ----------------------------- | ---------------: | --------------: | ---------- | -------- |
| `20-architect`                |               92 |              90 | No         | Yes      |
| `21-architecture-plan-writer` |               90 |              88 | No         | No       |
| `30-implementer`              |               90 |              86 | Yes        | No       |
| `31-bugfix`                   |               89 |              87 | Yes        | No       |
| `32-refactorer`               |               88 |              82 | Yes        | No       |
| `33-config-maintainer`        |               88 |              84 | Yes        | No       |
| `34-build-config`             |               86 |              82 | Yes        | No       |
| `35-upgrade`                  |               84 |              78 | Yes        | No       |
| `40-workflow-auditor`         |               91 |              90 | No         | Yes      |
| `50-reviewer`                 |               93 |              94 | No         | Yes      |
| `60-release-auditor`          |               94 |              95 | No         | Yes      |
| `70-post-install`             |               90 |              88 | No         | Yes      |
| `90-agent-runtime-guardian`   |               93 |              95 | No         | Yes      |

## Acceptance criteria

- [ ] Mutating agents have lower execution trust than gatekeepers.
- [ ] No mutating agent can self-approve.
- [ ] 95+ is used only for deterministic or gatekeeper roles.
- [ ] Upgrade agent remains below 85 execution trust unless heavily constrained.

## Verification

```bash
grep -R "agent_score:" .github/agents -n
grep -R "can_mutate: true" .github/agents -n
grep -R "can_gate: true" .github/agents -n
```

---

# P7 — Add Standard Output Contracts

## TODO

Add required output contract by agent type.

## Research agent output

```yaml
research_result:
  related_files: []
  relevant_patterns: []
  unknowns: []
  risks: []
  confidence: 0-100
```

## Architect output

```yaml
scope_contract:
  in_scope: []
  out_of_scope: []
  allowed_paths: []
  blocked_paths: []
  risk_level: low | medium | high | critical
  architecture_decision: approve | block | needs_user_decision
  verification_plan: []
  stop_conditions: []
```

## Plan writer output

```yaml
implementation_plan:
  ordered_todo: []
  acceptance_criteria: []
  verification_steps: []
  rollback_plan: []
```

## Execution agent output

```yaml
implementation_report:
  changed_files: []
  acceptance_criteria_satisfied: []
  verification_performed: []
  remaining_risks: []
```

## Reviewer output

```yaml
review_decision:
  decision: approve | approve_with_minor_fixes | needs_refactor | block
  failed_criteria: []
  evidence: []
  required_fixes: []
```

## Release auditor output

```yaml
release_decision:
  decision: ship | no_ship
  blockers: []
  warnings: []
  verification_evidence: []
```

## Acceptance criteria

- [ ] Every agent type has one required output contract.
- [ ] Reviewer and release auditor return explicit decisions.
- [ ] Execution agents report changed files and AC satisfaction.
- [ ] Architecture output includes stop conditions.

## Verification

```bash
grep -R "scope_contract:" .github/agents -n
grep -R "review_decision:" .github/agents -n
grep -R "release_decision:" .github/agents -n
```

---

# P8 — Add Permission Templates

## TODO

Create shared templates:

```text
.github/agent-permissions/
  00-read-only.yaml
  10-context-builder.yaml
  20-planning.yaml
  30-edit-capable.yaml
  40-validator.yaml
  50-reviewer.yaml
  60-release-auditor.yaml
  90-runtime-guardian.yaml
```

## Acceptance criteria

- [ ] Read-only template denies all mutation scripts.
- [ ] Edit-capable template allows focused tests but denies broad dangerous scripts.
- [ ] Runtime guardian template is the only one that allows hook scripts.
- [ ] Release auditor template allows verification, diff, branch, and install coverage scripts.
- [ ] No template grants `all_in_one.sh` or `watch-loop.sh`.

## Verification

```bash
find .github/agent-permissions -type f -name '*.yaml' | sort
grep -R "all_in_one.sh" .github/agent-permissions -n
grep -R "watch-loop.sh" .github/agent-permissions -n
grep -R "pre-tool-use.sh" .github/agent-permissions -n
```

---

# P9 — Add Compatibility Wrappers

## TODO

If scripts are moved into folders, keep wrappers at old paths.

Example:

```bash
#!/usr/bin/env bash
exec "$(dirname "$0")/10-discovery/ai-search.sh" "$@"
```

## Acceptance criteria

- [ ] Existing commands still work.
- [ ] New canonical paths work.
- [ ] Wrapper files contain no business logic.
- [ ] Wrappers can be removed only in a future major version.

## Verification

```bash
bash scripts/ai/ai-search.sh doctor .
bash scripts/ai/10-discovery/ai-search.sh doctor .
```

---

# P10 — Add Automated Consistency Checks

## TODO

Create or extend verification so it checks:

- agent filename matches lifecycle group
- agent `id` is stable
- agent has score metadata
- agent has allowed script tiers
- denied dangerous scripts are not allowed
- output contract exists
- no self-approval for mutating agents
- old script wrappers still work
- internal modules are not exposed to agents

## Acceptance criteria

- [ ] `ai-verify.sh` fails if an agent lacks lifecycle metadata.
- [ ] `ai-verify.sh` fails if a read-only agent has mutation scripts.
- [ ] `ai-verify.sh` fails if dangerous scripts are allowed outside approved agents.
- [ ] `ai-verify.sh` fails if a mutating agent can gate itself.
- [ ] `ai-verify.sh` passes on the migrated structure.

## Verification

```bash
bash scripts/ai/ai-verify.sh
bash scripts/ai/ai-doc-check.sh
bash scripts/ai/check-file-refs.sh
```

---

# P11 — Migration Order

## Correct order

```text
1. Inventory current agents/scripts.
2. Add lifecycle manifest.
3. Add script access manifest.
4. Add score contract.
5. Add output contracts.
6. Add permission templates.
7. Add automated verification.
8. Rename agents.
9. Move scripts into category folders only if wrappers exist.
10. Run full verification.
11. Update documentation.
12. Remove obsolete names only in a future major release.
```

## Do not do this

```text
Do not rename agents first.
Do not move scripts before wrappers exist.
Do not number all public scripts.
Do not give all agents all helper scripts.
Do not allow mutating agents to approve themselves.
Do not expose internal modules as public tools.
```

---

# P12 — Final Verification Matrix

## Required final commands

```bash
find .github/agents -maxdepth 1 -type f -name '*.agent.md' | sort
find .github/agent-permissions -type f -name '*.yaml' | sort
find scripts/ai -maxdepth 3 -type f -name '*.sh' | sort

bash scripts/ai/ai-search.sh doctor .
bash scripts/ai/repo-tool-inventory.sh
bash scripts/ai/ai-doc-check.sh
bash scripts/ai/check-file-refs.sh
bash scripts/ai/ai-verify.sh
```

## Release gate

The migration is complete only when:

- [ ] All agents have lifecycle metadata.
- [ ] All agents have score metadata.
- [ ] All agents have allowed script tiers.
- [ ] All agents have output contracts.
- [ ] All dangerous scripts are denied by default.
- [ ] All old script paths still work.
- [ ] All old agent references are either updated or explicitly aliased.
- [ ] `ai-verify.sh` passes.
- [ ] `ai-doc-check.sh` passes.
- [ ] `check-file-refs.sh` passes.

---

# Final Decision

Use this final policy:

```text
Number agent filenames by lifecycle.
Do not number all public scripts.
Group scripts by lifecycle folders only after wrappers exist.
Keep descriptive script names.
Keep internal numbered modules private.
Use manifests as the source of truth.
Use ai-verify.sh as the final enforcement gate.
```
