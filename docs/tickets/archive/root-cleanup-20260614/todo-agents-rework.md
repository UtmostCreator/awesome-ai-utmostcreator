> STATUS (verified against live repo): this design proposal has **largely
> landed**. Corrections:
>
> - ALREADY-DONE — script-access tiers (T0_help..T5_mutation_recovery) +
>   per-agent allow/deny: implemented in `.github/ai-script-access.yaml` and
>   `docs/ai/agent-script-access.md` (enforced by `tests/php/AiScriptAccessManifestTest.php`).
> - ALREADY-DONE — event lifecycle (E00..E120), per-agent ranking/lifecycle,
>   and size-based mutation/gate risk: captured in `docs/ai/AGENTS-MANIFEST.md`
>   (enforced by `tests/php/AgentsManifestTest.php`).
> - ALREADY-DONE — OpenCode permission pattern: live in `opencode.jsonc` +
>   agent frontmatter (agent rules may only tighten global policy).
> - STALE PATHS — the "Core rule" block cites `scripts/ai/ai-search/*.sh` and
>   `scripts/ai/lib/*.sh`; these moved to `scripts/ai/internal/search/*.sh` and
>   `scripts/ai/internal/lib/*.sh`. The stable entrypoints list also omits the
>   `scripts/ai/bin/{read,context,verify,edit,admin,hooks}/` shim layer.
> - INCOMPLETE — the agent set omits `super-implementer` and `bootstrapper`
>   (OpenCode-only) and the GitHub-only agents (`bugfix`, `build-config`, `docs`,
>   `infra-auditor`, `upgrade`).
> - STILL-VALID (only genuinely open item) — the two-score `agent_score`
>   (`design_readiness`/`execution_trust`) frontmatter is not yet implemented.

## Improved scoring model

Use **score = operational readiness**, not “agent quality”.

|      Score | Meaning                                                                   | Required action                                                               |
| ---------: | ------------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| **95–100** | Surgical accuracy. Deterministic, evidence-backed, almost no ambiguity.   | Can gate other agents. Use only for validators/auditors with strong evidence. |
|  **90–94** | Production-grade. Low doubt, clear scope, strong verification.            | Can approve or block work. Minor wording/config edits only.                   |
|  **85–89** | Strong usable agent. Most requirements covered, small corrections likely. | Safe to use with reviewer/auditor gate.                                       |
|  **80–84** | Usable but not fully stable. Some ambiguity or missing checks.            | Refactor soon; require stronger review.                                       |
|  **75–79** | Risky / partially correct. Likely needs architecture review.              | Do not allow high-impact edits without supervisor.                            |
|  **60–74** | Exploratory only. Useful for research, not trusted execution.             | Needs rewrite or strict containment.                                          |
|    **<60** | Unsafe/incomplete.                                                        | Do not use.                                                                   |

### Recommended scoring formula

```yaml
agent_assessment:
  score: 0-100
  confidence: 0-100
  role_clarity: 0-15
  scope_control: 0-15
  permission_safety: 0-15
  output_contract: 0-15
  evidence_required: 0-15
  verification_strength: 0-15
  handoff_quality: 0-10
  risk_level: low | medium | high | critical
  decision: approve | approve_with_minor_fixes | needs_refactor | block
```

## Key rule

**95+ should be rare.**

Do **not** give 95–100 to agents that generate large changes, refactor code, edit configs, or upgrade systems. Those agents are inherently uncertain.

Best candidates for **95+**:

| Agent type       | Why                                        |
| ---------------- | ------------------------------------------ |
| Static validator | Deterministic checks                       |
| Runtime guardian | Enforces execution constraints             |
| Release auditor  | Gatekeeper with explicit checklist         |
| Supervisor       | Orchestrates and blocks unsafe transitions |
| Reviewer         | Verifies already-produced work             |

Agents like `implementer`, `refactorer`, `upgrade`, and `config-maintainer` can be excellent, but usually should cap around **85–92** because they mutate the repository.

---

## Event lifecycle

| Event                        | Must happen when                                          | Owner agent                          | Output                                                 |
| ---------------------------- | --------------------------------------------------------- | ------------------------------------ | ------------------------------------------------------ |
| **E00 Intake**               | Any user/task request arrives                             | Supervisor / main agent              | Task summary, risk estimate                            |
| **E10 Scope Contract**       | Before planning or editing                                | Architect / implementer / refactorer | In-scope, out-of-scope, allowed paths, blocked paths   |
| **E20 Repository Read**      | Before any implementation                                 | Repository researcher                | Existing structure, related files, risks               |
| **E30 Architecture Gate**    | If change is broad, risky, config-heavy, or cross-cutting | Architect                            | Approved plan or block                                 |
| **E40 Plan Written**         | Before multi-file edits                                   | Architecture plan writer             | Ordered TODO, ACs, verification steps                  |
| **E50 Edit Gate**            | Before mutation                                           | Supervisor / runtime guardian        | Confirmed allowed paths and edit permission            |
| **E60 Implementation**       | Only after E10–E50 pass                                   | Implementer / bugfix / refactorer    | Minimal scoped changes                                 |
| **E70 Self-check**           | Immediately after implementation                          | Same editing agent                   | What changed, why, local verification                  |
| **E80 Static Validation**    | After changes, before review                              | Static validator                     | Contract, syntax, policy, forbidden-operation check    |
| **E90 Semantic Review**      | After static validation                                   | Semantic verifier / reviewer         | Does the change actually solve the task?               |
| **E100 Runtime/Smoke Check** | If runnable/buildable                                     | Runtime guardian / build-config      | Tests, smoke checks, failure report                    |
| **E110 Release Audit**       | Before merge/release/shipping                             | Release auditor                      | Ship / no-ship decision                                |
| **E120 Post-install Audit**  | After installation/bootstrap/config generation            | Post-install                         | Installed files, drift, missing files, unsafe defaults |

---

## Recommended per-agent ranking and purpose

| Agent                                      | Target readiness | Risk            | Description                                                                                    | Must run on events        |
| ------------------------------------------ | ---------------: | --------------- | ---------------------------------------------------------------------------------------------- | ------------------------- |
| `agent-creator-supervisor.agent.md`        |        **90–95** | High control    | Orchestrates agent creation, blocks unsafe handoffs, ensures validators run.                   | E00, E30, E50, E110       |
| `agent-creator-runtime-guardian.agent.md`  |        **90–95** | Critical safety | Verifies runtime permissions, denied commands, mutation boundaries, unsafe execution attempts. | E50, E100                 |
| `agent-creator-static-validator.agent.md`  |        **88–94** | Medium          | Checks schema, frontmatter, permissions, required sections, missing contracts.                 | E80                       |
| `agent-creator-semantic-verifier.agent.md` |        **88–94** | Medium          | Checks whether agent meaning, role, and behavior match intended design.                        | E90                       |
| `agent-creator.agent.md`                   |        **82–88** | High            | Creates new agents, but must not approve its own output.                                       | E40, E60, then validators |
| `architect.agent.md`                       |        **90–94** | High            | Owns scope, architecture boundaries, adapter strategy, risk posture.                           | E10, E30                  |
| `architecture-plan-writer.agent.md`        |        **88–92** | Medium          | Converts approved architecture into TODOs, ACs, verification steps.                            | E40                       |
| `implementer.agent.md`                     |        **85–90** | High            | Performs scoped implementation only after contract and plan approval.                          | E60, E70                  |
| `refactorer.agent.md`                      |        **82–88** | High            | Performs structural improvement; must be tightly path-scoped.                                  | E30, E40, E60             |
| `bugfix.agent.md`                          |        **85–90** | Medium          | Reproduces, isolates, fixes, verifies bug without unrelated cleanup.                           | E20, E60, E70             |
| `reviewer.agent.md`                        |        **90–95** | High gate       | Reviews changes against scope, ACs, correctness, regressions.                                  | E90                       |
| `repository-reviewer.agent.md`             |        **88–93** | Medium          | Reviews repository health, structure, ownership, risks.                                        | E20, E90                  |
| `repository-researcher.agent.md`           |        **86–91** | Low/medium      | Read-only discovery of files, patterns, conventions, prior art.                                | E20                       |
| `release-auditor.agent.md`                 |        **90–95** | Critical gate   | Final no-ship/ship decision based on tests, docs, config, safety.                              | E110                      |
| `workflow-auditor.agent.md`                |        **86–92** | Medium          | Checks workflow consistency across agents, CI, docs, permissions.                              | E80, E110                 |
| `config-maintainer.agent.md`               |        **84–89** | High            | Maintains AI/tool config; risky because config changes alter future behavior.                  | E30, E60, E80             |
| `build-config.agent.md`                    |        **82–88** | High            | Edits build/CI/package/config files; should be gated.                                          | E30, E60, E100            |
| `post-install.agent.md`                    |        **86–92** | High            | Verifies installed templates, generated files, drift, missing setup.                           | E120                      |
| `infra-auditor.agent.md`                   |        **85–90** | High            | Reviews infrastructure/security/deployment assumptions.                                        | E30, E100, E110           |
| `docs.agent.md`                            |        **85–90** | Low/medium      | Updates docs after verified behavior, not before.                                              | After E90 or E110         |
| `researcher.agent.md`                      |        **80–88** | Medium          | External/internal research; evidence quality matters more than edits.                          | E20, E30                  |
| `upgrade.agent.md`                         |        **78–85** | Critical        | Handles version/tooling upgrades; needs strongest gates.                                       | E30, E40, E60, E100, E110 |

---

## Size-based risk from your table

| Risk tier                       | Files                                                                                                                              | Reason                                                                          |
| ------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------- |
| **Highest review priority**     | `implementer`, `refactorer`, `researcher`, `post-install`, `config-maintainer`, `architect`                                        | 185–237 lines. Long instructions increase contradiction risk and context drift. |
| **Medium review priority**      | `reviewer`, `architecture-plan-writer`, `release-auditor`, `repository-researcher`, `agent-creator-supervisor`, `workflow-auditor` | Important gatekeepers; must be precise and non-overlapping.                     |
| **Lower review priority**       | `bugfix`, `build-config`, `docs`, `infra-auditor`, `upgrade`, `repository-reviewer`                                                | Shorter files, but some have high mutation risk despite small size.             |
| **Special validation priority** | `agent-creator-*` suite                                                                                                            | These define agents that create/validate agents, so errors compound.            |

---

## Recommended event rules

### 1. Any edit-capable agent must start with this

```yaml
scope_contract_required: true
must_define:
  - in_scope
  - out_of_scope
  - allowed_paths
  - blocked_paths
  - risk_level
  - verification_plan
  - stop_conditions
```

### 2. Any high-risk file change must trigger architect first

High-risk means changes to:

```text
.github/**
packages/**/templates/**
scripts/**
config/**
ci/**
installer/**
permissions/**
agent definitions
```

Required sequence:

```text
repository-researcher
→ architect
→ architecture-plan-writer
→ implementer/refactorer/config-maintainer
→ static-validator
→ semantic-verifier
→ reviewer
→ release-auditor
```

### 3. Agent creation must never be self-approved

Required sequence:

```text
agent-creator
→ agent-creator-static-validator
→ agent-creator-semantic-verifier
→ agent-creator-runtime-guardian
→ agent-creator-supervisor
```

### 4. Refactoring requires stronger gates than bug fixing

Bugfix path:

```text
bugfix
→ static-validator
→ reviewer
```

Refactor path:

```text
repository-researcher
→ architect
→ architecture-plan-writer
→ refactorer
→ static-validator
→ semantic-verifier
→ reviewer
```

Upgrade path:

```text
repository-researcher
→ architect
→ architecture-plan-writer
→ upgrade
→ build-config
→ runtime-guardian
→ release-auditor
```

---

## Better descriptions to embed in agents

### For execution agents

```md
This agent may execute only after a scope contract exists.
It must not expand scope, modify unrelated files, or perform opportunistic cleanup.
If required information is missing, it must stop and report the missing evidence.
All changes must map directly to an acceptance criterion.
```

### For reviewer agents

```md
This agent does not improve the implementation directly.
It verifies whether the completed work satisfies the scope contract, acceptance criteria, safety constraints, and verification plan.
It must return approve, approve_with_minor_fixes, needs_refactor, or block.
```

### For validator agents

```md
This agent performs deterministic validation only.
It must report exact failed checks, affected files, and required corrections.
It must not rewrite the task, expand scope, or approve semantic correctness unless explicitly assigned that role.
```

### For supervisor agents

```md
This agent owns sequencing and escalation.
It decides which specialist agent runs next, blocks unsafe transitions, and ensures no edit-capable agent approves its own work.
It must prefer stopping over guessing when permissions, scope, or ownership are unclear.
```

---

## Recommended final ranking model

Use two scores, not one:

```yaml
agent_score:
  design_readiness: 0-100
  execution_trust: 0-100
```

Example:

| Agent                      | Design readiness | Execution trust |
| -------------------------- | ---------------: | --------------: |
| `architect`                |               92 |              90 |
| `implementer`              |               90 |              86 |
| `refactorer`               |               88 |              82 |
| `release-auditor`          |               93 |              94 |
| `runtime-guardian`         |               92 |              95 |
| `agent-creator`            |               87 |              82 |
| `agent-creator-supervisor` |               92 |              94 |

This prevents a dangerous mistake: a well-written mutating agent may have high design quality but lower execution trust because its actions are inherently risky.

## Practical threshold policy

```yaml
policy:
  score_95_100:
    allowed_to_gate: true
    allowed_to_mutate: false_or_strictly_scoped

  score_90_94:
    allowed_to_gate: true
    allowed_to_mutate: only_with_scope_contract

  score_85_89:
    allowed_to_mutate: yes
    requires_review: true

  score_80_84:
    allowed_to_mutate: limited
    requires_architect: true
    requires_reviewer: true

  score_75_79:
    allowed_to_mutate: false
    use_for: analysis_only

  below_75:
    allowed: false
```

## Best target state

Your agent system should work like this:

```text
Research discovers.
Architect scopes.
Plan-writer converts scope into ACs.
Implementer changes.
Validator checks structure.
Semantic verifier checks meaning.
Reviewer checks correctness.
Runtime guardian checks execution safety.
Release auditor decides ship/no-ship.
Post-install confirms installed state.
```

This gives you a clean production rule:

> **No agent that creates or mutates work should be the same agent that finally approves it.**

## Core rule

Do **not** give agents direct access to internal modules:

```text
scripts/ai/internal/search/*.sh
scripts/ai/internal/lib/*.sh
```

They should use only stable entrypoints (the root names, also addressable via
the `scripts/ai/bin/{read,context,verify,edit,admin,hooks}/` shim layer):

```text
scripts/ai/ai-search.sh
scripts/ai/ai-search-multi.sh
scripts/ai/ai-search-introspect.sh
scripts/ai/sh-introspect.sh
```

Internal files are implementation details.

---

## Script access tiers

| Tier                                 | Scripts                                                                                                                                                                                                                                | Access policy                                                           |
| ------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| **T0 universal help/introspection**  | `sh-introspect.sh`, `ai-search-introspect.sh`, `query-usage.sh`, `repo-tool-inventory.sh`, `repo-stats.sh`                                                                                                                             | Allow to almost all agents                                              |
| **T1 safe discovery**                | `ai-search.sh`, `ai-search-multi.sh`, `fd-files.sh`, `rg-code.sh`, `preview-file.sh`, `ai-file-freshness.sh`, `check-file-refs.sh`                                                                                                     | Allow to read/research/review agents                                    |
| **T2 context building**              | `pack-context.sh`, `repomix-context-tree.sh`, `run-repomix-context.sh`, `run-repomix-file.sh`, `repomix-freshness.sh`, `repomix-ensure-fresh.sh`, `ai-diff-context.sh`, `gh-pr-context.sh`, `git-branch-origin.sh`, `git-forensics.sh` | Allow to architect/reviewer/researcher agents                           |
| **T3 validation/testing**            | `ai-verify.sh`, `ai-doc-check.sh`, `ai-test-select.sh`, `run-test-focused.sh`, `run-repo-tests.sh`, `ai-install-coverage.sh`                                                                                                           | Allow to validators, reviewers, release agents, implementers after edit |
| **T4 structured planning/reporting** | `ai-task.sh`, `ai-structured.sh`, `build-ai-help-bundle.sh`, `session-checkpoint.sh`                                                                                                                                                   | Allow to supervisor/planner/auditor agents                              |
| **T5 guarded mutation/recovery**     | `ai-edit.sh`, `ai-rollback.sh`, `prune-shipped-targets.sh`, `install-mandatory-tools.sh`, `post-tool-use.sh`, `pre-tool-use.sh`, `all_in_one.sh`, `watch-loop.sh`                                                                      | Deny by default; grant only to specific agents                          |

---

## Recommended script assignment per agent

| Agent                                      | Must be able to use                                                                                                                   | Optional                                                       | Should not use                                                                           |
| ------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| `agent-creator.agent.md`                   | `sh-introspect.sh`, `ai-search-introspect.sh`, `query-usage.sh`, `build-ai-help-bundle.sh`, `ai-doc-check.sh`, `check-file-refs.sh`   | `ai-search.sh`, `repo-tool-inventory.sh`, `ai-structured.sh`   | `ai-edit.sh`, `ai-rollback.sh`, `prune-shipped-targets.sh`, `install-mandatory-tools.sh` |
| `agent-creator-supervisor.agent.md`        | `sh-introspect.sh`, `ai-search-introspect.sh`, `build-ai-help-bundle.sh`, `ai-verify.sh`, `ai-doc-check.sh`, `repo-tool-inventory.sh` | `pre-tool-use.sh`, `post-tool-use.sh`, `session-checkpoint.sh` | `ai-edit.sh`, `prune-shipped-targets.sh`                                                 |
| `agent-creator-static-validator.agent.md`  | `sh-introspect.sh`, `ai-search-introspect.sh`, `ai-doc-check.sh`, `check-file-refs.sh`, `ai-verify.sh`                                | `repo-tool-inventory.sh`, `query-usage.sh`                     | All mutation scripts                                                                     |
| `agent-creator-semantic-verifier.agent.md` | `sh-introspect.sh`, `ai-search-introspect.sh`, `ai-search.sh`, `preview-file.sh`, `ai-doc-check.sh`                                   | `build-ai-help-bundle.sh`, `pack-context.sh`                   | All mutation scripts                                                                     |
| `agent-creator-runtime-guardian.agent.md`  | `sh-introspect.sh`, `repo-tool-inventory.sh`, `pre-tool-use.sh`, `post-tool-use.sh`, `ai-verify.sh`, `ai-search.sh unsafe-patterns`   | `git-forensics.sh`, `session-checkpoint.sh`                    | `ai-edit.sh`, `install-mandatory-tools.sh`, `prune-shipped-targets.sh`                   |

---

| Agent                               | Must be able to use                                                                                                                                                                   | Optional                                                                               | Should not use                                                                             |
| ----------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| `architect.agent.md`                | `ai-search.sh`, `ai-search-multi.sh`, `preview-file.sh`, `fd-files.sh`, `rg-code.sh`, `pack-context.sh`, `repomix-context-tree.sh`, `repo-stats.sh`, `git-forensics.sh`, `ai-task.sh` | `gh-pr-context.sh`, `run-repomix-context.sh`, `ai-diff-context.sh`, `ai-structured.sh` | `ai-edit.sh`, `ai-rollback.sh`, `prune-shipped-targets.sh`                                 |
| `architecture-plan-writer.agent.md` | `ai-search.sh`, `preview-file.sh`, `ai-task.sh`, `ai-structured.sh`, `check-file-refs.sh`                                                                                             | `pack-context.sh`, `repo-stats.sh`, `repomix-context-tree.sh`                          | All mutation scripts                                                                       |
| `implementer.agent.md`              | `ai-search.sh`, `preview-file.sh`, `fd-files.sh`, `rg-code.sh`, `ai-test-select.sh`, `run-test-focused.sh`, `ai-verify.sh`                                                            | `ai-diff-context.sh`, `run-repo-tests.sh`, `ai-file-freshness.sh`                      | `prune-shipped-targets.sh`, `install-mandatory-tools.sh`, `all_in_one.sh`, `watch-loop.sh` |
| `bugfix.agent.md`                   | `ai-search.sh`, `ai-diff-context.sh`, `preview-file.sh`, `rg-code.sh`, `ai-test-select.sh`, `run-test-focused.sh`, `ai-verify.sh`                                                     | `git-forensics.sh`, `run-repo-tests.sh`                                                | `prune-shipped-targets.sh`, `install-mandatory-tools.sh`, `all_in_one.sh`                  |
| `refactorer.agent.md`               | `ai-search.sh`, `ai-search-multi.sh`, `preview-file.sh`, `fd-files.sh`, `rg-code.sh`, `repomix-context-tree.sh`, `ai-diff-context.sh`, `ai-test-select.sh`, `ai-verify.sh`            | `pack-context.sh`, `run-test-focused.sh`, `run-repo-tests.sh`                          | `prune-shipped-targets.sh`, `install-mandatory-tools.sh`, `all_in_one.sh`                  |
| `upgrade.agent.md`                  | `ai-search.sh`, `ai-search-multi.sh`, `preview-file.sh`, `ai-search.sh deps`, `ai-search.sh config`, `ai-test-select.sh`, `run-repo-tests.sh`, `ai-verify.sh`, `git-forensics.sh`     | `repomix-context-tree.sh`, `ai-diff-context.sh`, `gh-pr-context.sh`                    | `install-mandatory-tools.sh` unless explicitly approved                                    |

---

| Agent                            | Must be able to use                                                                                                                                                                          | Optional                                                          | Should not use                                                         |
| -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------- | ---------------------------------------------------------------------- |
| `reviewer.agent.md`              | `ai-diff-context.sh`, `ai-search.sh`, `preview-file.sh`, `check-file-refs.sh`, `ai-verify.sh`, `ai-doc-check.sh`, `run-test-focused.sh`                                                      | `run-repo-tests.sh`, `git-forensics.sh`, `gh-pr-context.sh`       | All mutation scripts                                                   |
| `repository-reviewer.agent.md`   | `repo-stats.sh`, `repo-tool-inventory.sh`, `repomix-context-tree.sh`, `ai-search.sh`, `fd-files.sh`, `rg-code.sh`, `check-file-refs.sh`                                                      | `pack-context.sh`, `git-forensics.sh`, `ai-file-freshness.sh`     | All mutation scripts                                                   |
| `repository-researcher.agent.md` | `ai-search.sh`, `ai-search-multi.sh`, `fd-files.sh`, `rg-code.sh`, `preview-file.sh`, `repo-stats.sh`, `repomix-context-tree.sh`, `pack-context.sh`                                          | `git-forensics.sh`, `gh-pr-context.sh`, `run-repomix-context.sh`  | All mutation scripts                                                   |
| `researcher.agent.md`            | `ai-search.sh`, `ai-search-multi.sh`, `preview-file.sh`, `pack-context.sh`, `repomix-context-tree.sh`, `git-forensics.sh`, `repo-stats.sh`                                                   | `gh-pr-context.sh`, `run-repomix-context.sh`, `query-usage.sh`    | All mutation scripts                                                   |
| `release-auditor.agent.md`       | `ai-diff-context.sh`, `gh-pr-context.sh`, `git-branch-origin.sh`, `git-forensics.sh`, `ai-verify.sh`, `ai-doc-check.sh`, `run-repo-tests.sh`, `ai-install-coverage.sh`, `check-file-refs.sh` | `repo-stats.sh`, `repo-tool-inventory.sh`, `repomix-freshness.sh` | `ai-edit.sh`, `prune-shipped-targets.sh`, `install-mandatory-tools.sh` |
| `workflow-auditor.agent.md`      | `repo-tool-inventory.sh`, `ai-search-introspect.sh`, `sh-introspect.sh`, `ai-verify.sh`, `ai-doc-check.sh`, `check-file-refs.sh`, `ai-search.sh config`                                      | `build-ai-help-bundle.sh`, `repomix-context-tree.sh`              | `ai-edit.sh`, `prune-shipped-targets.sh`                               |

---

| Agent                        | Must be able to use                                                                                                                                  | Optional                                                                   | Should not use                                                         |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- | ---------------------------------------------------------------------- |
| `config-maintainer.agent.md` | `ai-search.sh config`, `ai-search.sh deps`, `preview-file.sh`, `check-file-refs.sh`, `ai-file-freshness.sh`, `ai-verify.sh`                          | `ai-doc-check.sh`, `git-forensics.sh`, `ai-diff-context.sh`                | `install-mandatory-tools.sh` unless task explicitly requires it        |
| `build-config.agent.md`      | `ai-search.sh config`, `ai-search.sh deps`, `preview-file.sh`, `ai-test-select.sh`, `run-test-focused.sh`, `run-repo-tests.sh`, `ai-verify.sh`       | `repo-tool-inventory.sh`, `git-forensics.sh`                               | `install-mandatory-tools.sh` by default                                |
| `post-install.agent.md`      | `ai-install-coverage.sh`, `ai-file-freshness.sh`, `check-file-refs.sh`, `repo-tool-inventory.sh`, `repo-stats.sh`, `ai-verify.sh`, `ai-doc-check.sh` | `repomix-ensure-fresh.sh`, `repomix-freshness.sh`, `session-checkpoint.sh` | `ai-edit.sh`, `prune-shipped-targets.sh`                               |
| `infra-auditor.agent.md`     | `ai-search.sh config`, `ai-search.sh deps`, `repo-tool-inventory.sh`, `git-forensics.sh`, `ai-verify.sh`, `run-repo-tests.sh`                        | `gh-pr-context.sh`, `ai-diff-context.sh`                                   | `ai-edit.sh`, `install-mandatory-tools.sh`, `prune-shipped-targets.sh` |
| `docs.agent.md`              | `ai-search.sh docs`, `preview-file.sh`, `check-file-refs.sh`, `ai-doc-check.sh`, `ai-file-freshness.sh`                                              | `pack-context.sh`, `build-ai-help-bundle.sh`                               | `ai-edit.sh` unless docs-only edit permission exists                   |
| `build-config.agent.md`      | `ai-search.sh config`, `ai-search.sh deps`, `preview-file.sh`, `ai-test-select.sh`, `run-test-focused.sh`, `run-repo-tests.sh`, `ai-verify.sh`       | `repo-tool-inventory.sh`, `repomix-freshness.sh`                           | `install-mandatory-tools.sh` unless approved                           |

---

## Best minimal permissions by agent type

### Read-only agents

Applies to:

```text
repository-researcher
repository-reviewer
researcher
architecture-plan-writer
agent-creator-static-validator
agent-creator-semantic-verifier
workflow-auditor
infra-auditor
```

Allow:

```text
scripts/ai/ai-search.sh
scripts/ai/ai-search-multi.sh
scripts/ai/ai-search-introspect.sh
scripts/ai/sh-introspect.sh
scripts/ai/query-usage.sh
scripts/ai/repo-tool-inventory.sh
scripts/ai/repo-stats.sh
scripts/ai/fd-files.sh
scripts/ai/rg-code.sh
scripts/ai/preview-file.sh
scripts/ai/check-file-refs.sh
scripts/ai/ai-file-freshness.sh
scripts/ai/pack-context.sh
scripts/ai/repomix-context-tree.sh
```

Deny:

```text
scripts/ai/ai-edit.sh
scripts/ai/ai-rollback.sh
scripts/ai/prune-shipped-targets.sh
scripts/ai/install-mandatory-tools.sh
scripts/ai/all_in_one.sh
scripts/ai/watch-loop.sh
```

---

### Edit-capable agents

Applies to:

```text
implementer
bugfix
refactorer
config-maintainer
build-config
docs
upgrade
```

Allow read/help:

```text
scripts/ai/ai-search.sh
scripts/ai/ai-search-multi.sh
scripts/ai/preview-file.sh
scripts/ai/fd-files.sh
scripts/ai/rg-code.sh
scripts/ai/ai-diff-context.sh
scripts/ai/ai-test-select.sh
scripts/ai/run-test-focused.sh
scripts/ai/ai-verify.sh
scripts/ai/check-file-refs.sh
```

Allow only after edits:

```text
scripts/ai/run-repo-tests.sh
scripts/ai/ai-doc-check.sh
scripts/ai/repomix-ensure-fresh.sh
```

Deny by default:

```text
scripts/ai/ai-edit.sh
scripts/ai/ai-rollback.sh
scripts/ai/prune-shipped-targets.sh
scripts/ai/install-mandatory-tools.sh
scripts/ai/all_in_one.sh
scripts/ai/watch-loop.sh
```

Important: if OpenCode/Copilot already provides native `edit`/`write` tools, agents should **not** need `ai-edit.sh`.

---

### Gatekeeper agents

Applies to:

```text
reviewer
release-auditor
agent-creator-supervisor
agent-creator-runtime-guardian
```

Allow:

```text
scripts/ai/ai-diff-context.sh
scripts/ai/gh-pr-context.sh
scripts/ai/git-branch-origin.sh
scripts/ai/git-forensics.sh
scripts/ai/ai-verify.sh
scripts/ai/ai-doc-check.sh
scripts/ai/run-repo-tests.sh
scripts/ai/ai-install-coverage.sh
scripts/ai/check-file-refs.sh
scripts/ai/repo-tool-inventory.sh
scripts/ai/sh-introspect.sh
scripts/ai/ai-search-introspect.sh
```

Runtime guardian additionally:

```text
scripts/ai/pre-tool-use.sh
scripts/ai/post-tool-use.sh
```

Deny:

```text
scripts/ai/ai-edit.sh
scripts/ai/prune-shipped-targets.sh
scripts/ai/install-mandatory-tools.sh
```

Allow rollback only to explicit recovery owner:

```text
scripts/ai/ai-rollback.sh
```

---

## Script-by-script purpose map

| Script                       | Purpose                            | Primary agents                                     |
| ---------------------------- | ---------------------------------- | -------------------------------------------------- |
| `ai-search.sh`               | Unified repository search          | Almost all agents                                  |
| `ai-search-multi.sh`         | Multi-query search                 | Researcher, architect, refactorer                  |
| `ai-search-introspect.sh`    | Search tool contract/introspection | Agent creators, validators, workflow auditor       |
| `sh-introspect.sh`           | Shell script introspection         | Agent creators, validators, runtime guardian       |
| `query-usage.sh`             | Help/usage lookup                  | Agent creators, workflow auditor                   |
| `build-ai-help-bundle.sh`    | Generate help bundle               | Agent creator, supervisor, docs                    |
| `preview-file.sh`            | Safe file preview                  | Almost all read/edit agents                        |
| `fd-files.sh`                | File discovery                     | Researcher, reviewer, implementer                  |
| `rg-code.sh`                 | Code search                        | Researcher, implementer, reviewer                  |
| `check-file-refs.sh`         | Broken file reference checks       | Docs, reviewer, release auditor                    |
| `ai-file-freshness.sh`       | Staleness/freshness check          | Docs, post-install, config-maintainer              |
| `repo-stats.sh`              | Repository metrics                 | Architect, repository reviewer                     |
| `repo-tool-inventory.sh`     | Tool/script inventory              | Workflow auditor, runtime guardian                 |
| `pack-context.sh`            | Compact context bundle             | Researcher, architect                              |
| `repomix-context-tree.sh`    | Context tree                       | Researcher, architect, repository reviewer         |
| `run-repomix-context.sh`     | Larger repo context                | Researcher, architect                              |
| `run-repomix-file.sh`        | Single-file repomix context        | Researcher, reviewer                               |
| `repomix-freshness.sh`       | Repomix freshness status           | Post-install, release auditor                      |
| `repomix-ensure-fresh.sh`    | Ensure generated context is fresh  | Post-install, release auditor                      |
| `repomix-scc-router.sh`      | Size/complexity routing            | Architect, repository reviewer                     |
| `ai-diff-context.sh`         | Diff-focused context               | Reviewer, bugfix, release auditor                  |
| `gh-pr-context.sh`           | PR context                         | Reviewer, release auditor                          |
| `git-branch-origin.sh`       | Branch base/origin detection       | Release auditor, reviewer                          |
| `git-forensics.sh`           | Git history/ownership evidence     | Architect, reviewer, auditor                       |
| `ai-task.sh`                 | Task structure / planning          | Architect, plan writer, supervisor                 |
| `ai-structured.sh`           | Structured output generation       | Plan writer, supervisor                            |
| `ai-test-select.sh`          | Select relevant tests              | Implementer, bugfix, refactorer                    |
| `run-test-focused.sh`        | Run focused tests                  | Implementer, bugfix                                |
| `run-repo-tests.sh`          | Run broader tests                  | Reviewer, release auditor                          |
| `ai-verify.sh`               | Main verification                  | Validators, reviewer, release auditor              |
| `ai-doc-check.sh`            | Documentation checks               | Docs, reviewer, release auditor                    |
| `ai-install-coverage.sh`     | Install coverage validation        | Post-install, release auditor                      |
| `pre-tool-use.sh`            | Tool-use guard                     | Runtime guardian only                              |
| `post-tool-use.sh`           | Post-tool audit                    | Runtime guardian only                              |
| `session-checkpoint.sh`      | Session checkpoint                 | Supervisor, post-install                           |
| `ai-edit.sh`                 | Scripted editing                   | Prefer deny; only if native edit unavailable       |
| `ai-rollback.sh`             | Recovery rollback                  | Supervisor/runtime recovery only                   |
| `prune-shipped-targets.sh`   | Delete/prune shipped targets       | Release/post-install only with explicit approval   |
| `install-mandatory-tools.sh` | Installs tools                     | Bootstrap/post-install only with explicit approval |
| `all_in_one.sh`              | Broad orchestration                | Deny by default                                    |
| `watch-loop.sh`              | Long-running loop                  | Deny by default                                    |

---

## Recommended OpenCode-style permission pattern

### Universal safe script access

```yaml
permission:
  bash:
    "bash scripts/ai/ai-search.sh *": allow
    "AI_OUTPUT=json bash scripts/ai/ai-search.sh *": allow
    "bash scripts/ai/ai-search-multi.sh *": allow
    "bash scripts/ai/preview-file.sh *": allow
    "bash scripts/ai/fd-files.sh *": allow
    "bash scripts/ai/rg-code.sh *": allow
    "bash scripts/ai/sh-introspect.sh *": allow
    "bash scripts/ai/ai-search-introspect.sh *": allow
    "bash scripts/ai/query-usage.sh *": allow
    "bash scripts/ai/repo-stats.sh *": allow
    "bash scripts/ai/repo-tool-inventory.sh *": allow
```

### Universal deny list

```yaml
permission:
  bash:
    "bash scripts/ai/ai-edit.sh *": deny
    "bash scripts/ai/ai-rollback.sh *": deny
    "bash scripts/ai/prune-shipped-targets.sh *": deny
    "bash scripts/ai/install-mandatory-tools.sh *": deny
    "bash scripts/ai/all_in_one.sh *": deny
    "bash scripts/ai/watch-loop.sh *": deny
```

### Release/audit extra allow

```yaml
permission:
  bash:
    "bash scripts/ai/ai-diff-context.sh *": allow
    "bash scripts/ai/gh-pr-context.sh *": allow
    "bash scripts/ai/git-branch-origin.sh *": allow
    "bash scripts/ai/git-forensics.sh *": allow
    "bash scripts/ai/ai-verify.sh *": allow
    "bash scripts/ai/ai-doc-check.sh *": allow
    "bash scripts/ai/run-repo-tests.sh *": allow
    "bash scripts/ai/ai-install-coverage.sh *": allow
```

### Runtime guardian extra allow

```yaml
permission:
  bash:
    "bash scripts/ai/pre-tool-use.sh *": allow
    "bash scripts/ai/post-tool-use.sh *": allow
```

---

## Highest-priority correction

Do **not** let every agent call every helper script.

Best production rule:

```text
All agents get search/help/introspection.
Research agents get context builders.
Edit agents get focused tests and verification.
Gatekeepers get diff, PR, branch, verification, and audit scripts.
Only runtime/recovery agents get hook/rollback scripts.
No normal agent gets prune/install/all-in-one/watch-loop.
```

This keeps agents useful without giving them broad execution power.
