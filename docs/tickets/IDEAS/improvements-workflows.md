Based on the filenames only, these agents do **not** have a clear direct workflow template.

Existing workflow coverage is visible for items such as `architecture-plan`, `repo-investigation`, `plan-slice`, `search-evidence`, `dependency-upgrade`, and `docs-sync`.

> **Correction (grounded review, 2026-07-09):** the P0/P1 "missing workflow" tables below are
> **confirmed still accurate** — direct listing of `packages/ai-universal-rules/templates/workflows/`
> (23 files) shows none of `agent-creation-pipeline.md`, `agent-critic-review.md`,
> `agent-fleet-assessment.md`, `runtime-guardrail-audit.md`, `static-agent-validation.md`,
> `semantic-agent-verification.md`, `build-config-change.md`, `infra-audit.md`, `ui-build.md`,
> `refactor-slice.md`, `repository-review.md`, `workflow-audit.md`, or `project-bootstrap.md`
> exist yet. This is genuinely the strongest part of this review pass. Two caveats found during
> grounding:
>
> - Diagram/architecture-doc coverage: the "P0 fixes" list further down says workflows lack a
>   Mermaid guidance section — that is now **stale**: the architect and architecture-plan-writer
>   agents already carry a `## Architecture Diagram (Mermaid)` section on all rendered surfaces
>   (`docs/tickets/claude-agent-fleet-remediation/plan-30-architecture-mermaid-redesign.md`,
>   landed), and `docs/ai/architecture-diagrams.md` already exists (380 lines, 6+ sections,
>   hand-authored-must-sync, covers install/render/permission pipeline). It does **not** yet
>   cover agent-to-workflow routing as a diagram (only as the `docs/ai/agents.md` prose table)
>   — see `docs/tickets/IDEAS/plan-agent-workflow-routing-diagram.md` for that specific,
>   still-real gap.
> - Every workflow already IS reachable as a command/prompt/skill on all three providers (see
>   the correction in `improvements-commands.md`) — so "missing workflow" here correctly means
>   "missing process/safety-gate content", not "missing entrypoint".

## P0 — clearly missing workflows

| Missing workflow                 |                                                                                                                                    Agents affected | Why missing                                                                                                                                                                                                                                            |
| -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------: | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `agent-creation-pipeline.md`     | `agent-creator-supervisor`, `agent-creator`, `agent-creator-static-validator`, `agent-creator-semantic-verifier`, `agent-creator-runtime-guardian` | The agent pipeline exists inside agent instructions, but there is no top-level workflow for it. The documented flow is: supervisor → creator → static validator → semantic verifier → runtime guardian → human approval.                               |
| `agent-critic-review.md`         |                                                                                                                                     `agent-critic` | No workflow maps to critic-style review of agent specs, permissions, task boundaries, or failure modes.                                                                                                                                                |
| `agent-fleet-assessment.md`      |                                                                                                                             `agent-fleet-assessor` | No workflow for scoring the whole fleet, finding duplicated agents, overpowered agents, missing capabilities, or permission drift.                                                                                                                     |
| `runtime-guardrail-audit.md`     |                                                                                                                   `agent-creator-runtime-guardian` | `generate-permissions` is related, but runtime guardrail validation needs its own workflow: tool allow-list, deny rules, stop conditions, logging, rollback, and traceability. Runtime guardian hard rules already mention these as blocking concerns. |
| `static-agent-validation.md`     |                                                                                                                   `agent-creator-static-validator` | No direct workflow for deterministic validation: schema check, required fields, permission shape, denied paths, generated-file parity, and install-render compatibility.                                                                               |
| `semantic-agent-verification.md` |                                                                                                                  `agent-creator-semantic-verifier` | No direct workflow for semantic checks: does the agent do only its intended job, does it have excessive autonomy, does it fabricate verification, and does it need human gates.                                                                        |

## P1 — specialist agents missing direct workflows

| Missing workflow         |                     Agents affected | Why missing                                                                                                                                                                     |
| ------------------------ | ----------------------------------: | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `build-config-change.md` | `build-config`, `config-maintainer` | `verify-change` exists, but there is no specific workflow for build/tool/runtime config changes: formatter, linter, CI, package manager, Docker/Nix/Vite/Webpack, etc.          |
| `infra-audit.md`         |                     `infra-auditor` | No workflow for infra review: CI/CD, deployment config, secrets exposure, permissions, environment drift, generated assets, and operational safety.                             |
| `ui-build.md`            |                        `ui-builder` | No workflow for UI implementation: design source, component boundary, accessibility, responsive checks, screenshot/visual verification, and regression checks.                  |
| `refactor-slice.md`      |                        `refactorer` | No workflow for safe refactoring: behaviour lock, test baseline, bounded transform, diff risk, and no-feature-change validation.                                                |
| `repository-review.md`   |               `repository-reviewer` | `repo-investigation` is research-oriented and `review-diff` is diff-oriented; there is no whole-repository review workflow.                                                     |
| `workflow-audit.md`      |                  `workflow-auditor` | There is no workflow for auditing the workflow set itself: missing workflows, overlap, stale commands, generated prompt parity, and agent-to-workflow coverage.                 |
| `project-bootstrap.md`   |                      `bootstrapper` | `install` and `post-install-setup` exist, but bootstrapper needs a broader workflow for first repository setup, context discovery, tool detection, and initial safety baseline. |

## P2 — partially covered, but still weak

| Agent                      | Existing nearby workflow                                   | Missing stronger workflow                                                                                      |
| -------------------------- | ---------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| `bugfix`                   | `bug-regression`, `regression-test`                        | `bug-triage.md` for reproduce → isolate → patch-scope → verify.                                                |
| `upgrade`                  | `dependency-upgrade`                                       | `framework-upgrade.md` for Laravel/Symfony/Vue/Nuxt/etc. where changes are larger than package bumps.          |
| `docs`                     | `docs-sync`                                                | `docs-audit.md` for stale docs, README/API drift, broken examples, and generated documentation parity.         |
| `reviewer`                 | `review-diff`                                              | `security-review.md` / `risk-review.md` if reviewer must cover non-functional risk, not only diff correctness. |
| `release-auditor`          | `release-safety`                                           | Mostly covered; only missing if you want explicit `pre-release-audit.md`.                                      |
| `researcher`               | `search-evidence`, `project-context`                       | Mostly covered.                                                                                                |
| `repository-researcher`    | `repo-investigation`                                       | Mostly covered.                                                                                                |
| `post-install`             | `post-install-setup`, `replace-placeholders`               | Mostly covered.                                                                                                |
| `architect`                | `architecture-plan`, `prd-and-tasks`                       | Mostly covered.                                                                                                |
| `architecture-plan-writer` | `plan-slice`, `architecture-plan`                          | Mostly covered.                                                                                                |
| `implementer`              | `new-feature`, `verify-change`, `evidence-first-execution` | Mostly covered.                                                                                                |

## Final missing workflow list

Minimum set to close the obvious gaps:

```text
packages/ai-universal-rules/templates/workflows/agent-creation-pipeline.md
packages/ai-universal-rules/templates/workflows/agent-critic-review.md
packages/ai-universal-rules/templates/workflows/agent-fleet-assessment.md
packages/ai-universal-rules/templates/workflows/runtime-guardrail-audit.md
packages/ai-universal-rules/templates/workflows/static-agent-validation.md
packages/ai-universal-rules/templates/workflows/semantic-agent-verification.md
packages/ai-universal-rules/templates/workflows/build-config-change.md
packages/ai-universal-rules/templates/workflows/infra-audit.md
packages/ai-universal-rules/templates/workflows/ui-build.md
packages/ai-universal-rules/templates/workflows/refactor-slice.md
packages/ai-universal-rules/templates/workflows/repository-review.md
packages/ai-universal-rules/templates/workflows/workflow-audit.md
packages/ai-universal-rules/templates/workflows/project-bootstrap.md
```

Coverage score: **72/100**.

Main weakness: the current workflow set covers common execution paths, but not the **agent-governance layer**: creating, validating, reviewing, supervising, auditing, and runtime-guarding agents.

## Overall score: **74/100**

Strong base, but workflows are uneven. Main problems:

| Area                    | Score | Issue                                                                                             |
| ----------------------- | ----: | ------------------------------------------------------------------------------------------------- |
| Naming consistency      |    70 | Some entries have `name`, some only `description`.                                                |
| Output contracts        |    68 | Many workflows say what to do, but not exact final format.                                        |
| Safety gates            |    72 | Dirty worktree, user-change protection, destructive-action rules are not consistently referenced. |
| Agent/workflow boundary |    65 | Some workflows say “I” as if they are agents; others are commands.                                |
| Reuse/deduplication     |    62 | `architecture-plan`, `plan-slice`, and `prd-and-tasks` overlap.                                   |
| Verification evidence   |    78 | Good intent, but inconsistent “executed vs recommended” distinction.                              |
| Install/tool workflows  |    84 | Stronger than the planning/review workflows.                                                      |

## P0 fixes

### 1. Give every workflow the same required structure

Use one canonical schema:

```md
---
name: <workflow-name>
description: <when to use in one sentence>
argument-hint: "<expected user input>"
risk: low | medium | high
writes: none | docs-only | code | config | install-surface
primary-agent: <agent-name>
related-agents:
  - <agent-name>
---

## Purpose

## Use When

## Do Not Use When

## Required Inputs

## Read First

## Safety Gates

## Workflow

## Stop Conditions

## Output Contract

## Gotchas
```

This will make workflows machine-auditable and easier for agents to follow.

---

### 2. Add missing `name` and `argument-hint`

These are currently weak/incomplete:

```text
review-search-tool.md
script-inventory.md
search-evidence.md
```

They should not be anonymous `description`-only workflows.

Example:

```md
---
name: search-evidence
description: Use when collecting repository evidence with ai-search before planning, editing, or reviewing.
argument-hint: "Describe the symbol, path, behavior, error, or decision to investigate"
risk: low
writes: none
primary-agent: repository-researcher
related-agents:
  - researcher
  - reviewer
  - architect
---
```

---

### 3. Standardise safety gates

Add this to all workflows that inspect or mutate a repository:

```md
## Safety Gates

- Start with `git status --short`.
- Treat pre-existing user changes as protected.
- Do not overwrite, delete, move, or regenerate files outside the workflow scope.
- If the working tree is dirty, classify whether changes are user-owned, agent-owned, or generated.
- Before edits, state the intended files or path surface.
- Never claim verification was run unless the command was executed and the result is known.
```

For read-only workflows, use:

```md
## Safety Gates

- Stay read-only.
- Do not edit, install, regenerate, or run destructive commands.
- Report exact files, line ranges, commands, commits, or uncertainty.
```

---

### 4. Add explicit stop conditions

Most current workflows lack stop rules. Add:

```md
## Stop Conditions

Stop and report instead of continuing when:

- ownership cannot be identified with reasonable confidence
- required inputs are missing and cannot be safely inferred
- the workflow would cross into another workflow's scope
- verification cannot be run but would be required for a success claim
- package/install/checksum state is drifted
- the next action requires destructive or broad mutation not explicitly approved
```

This is especially important for `install`, `dependency-upgrade`, `replace-placeholders`, `generate-permissions`, and `post-install-setup`.

---

### 5. Add exact output format to each workflow

Current outputs are mostly bullet concepts. Use a fixed structure.

Example for planning workflows:

```md
## Output Contract

Return:

1. Scope summary
2. Current owner or `unknown`
3. In scope
4. Out of scope
5. Affected paths
6. Risk level: low | medium | high
7. Plan phases
8. Verification ladder
9. Rollback or disable path, if relevant
10. Recommended next workflow or agent
```

Example for verification workflows:

```md
## Output Contract

Return:

1. Change or behavior verified
2. Smallest valid proof selected
3. Commands run
4. Results
5. Evidence classification: passed | failed | partial | not-run
6. Remaining verification not run
7. Risk after verification
```

---

## P1 fixes by workflow

| Workflow                   | Current issue                                                        | Improvement                                                                                                                      |
| -------------------------- | -------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| `architecture-plan`        | Overlaps with `plan-slice`; durable plan path is embedded only here. | Make it only for medium/high-risk design planning. Move durable-plan rules into shared capability or `architecture-plan-writer`. |
| `plan-slice`               | Similar to `architecture-plan`.                                      | Define it as “smallest implementation slice planner”, not architecture planner.                                                  |
| `prd-and-tasks`            | Strong, but too procedural and agent-specific.                       | Keep it as idea → PRD → parent tasks → expanded plan. Move agent invocation details into handoff contract.                       |
| `bug-regression`           | Good, but missing stop conditions.                                   | Add “stop if reproduction is impossible or nondeterministic”.                                                                    |
| `regression-test`          | Good, but ambiguous whether it can edit.                             | Mark `writes: code` or `writes: tests-only`.                                                                                     |
| `new-feature`              | Good, but needs scope contract.                                      | Require explicit in-scope/out-of-scope and affected behavior.                                                                    |
| `dependency-upgrade`       | Good, but major/minor/patch distinction missing.                     | Add version-risk ladder: patch, minor, major, runtime/toolchain.                                                                 |
| `docs-sync`                | Good, but no output format.                                          | Require “changed docs, source evidence, future/implemented distinction”.                                                         |
| `evidence-first-execution` | Useful, but generic.                                                 | Make it a base workflow referenced by all mutation workflows, not a normal user-facing workflow.                                 |
| `install`                  | Strong.                                                              | Add rollback/backup output and install-surface diff summary.                                                                     |
| `post-install-setup`       | Strong.                                                              | Add stop condition for unresolved placeholders.                                                                                  |
| `generate-permissions`     | Strong.                                                              | Add explicit “preview only, no frontmatter mutation” in output contract.                                                         |
| `replace-placeholders`     | Strong.                                                              | Add generated-file drift check after apply.                                                                                      |
| `scan-stack`               | Strong.                                                              | Add confidence thresholds: high/medium/low and required user confirmation for low-confidence stack.                              |
| `repo-investigation`       | Good.                                                                | Replace direct script names with canonical `ai.php` wrapper if that is now preferred.                                            |
| `review-diff`              | Strong.                                                              | Add explicit severity scale and pass/fail criteria.                                                                              |
| `release-safety`           | Good.                                                                | Add deployment/migration/data contract checklist.                                                                                |
| `mentor-mode`              | Different type from others.                                          | Move to `modes/` rather than `workflows/`, or mark `kind: mode`.                                                                 |
| `review-search-tool`       | Too thin.                                                            | Convert to full workflow or move to `tests/manual/`.                                                                             |
| `script-inventory`         | Too thin.                                                            | Add `name`, output contract, risk classes, parity checks.                                                                        |
| `search-evidence`          | Too thin.                                                            | Add read-only safety gates and exact evidence output.                                                                            |

---

## P2: reduce duplication

### Current overlap

```text
prd-and-tasks
  -> raw idea to PRD and parent tasks

architecture-plan
  -> medium/large design plan

plan-slice
  -> bounded implementation plan
```

Recommended distinction:

| Workflow            | Purpose                                                  |
| ------------------- | -------------------------------------------------------- |
| `prd-and-tasks`     | Convert vague idea into product/acceptance scope.        |
| `architecture-plan` | Resolve architecture, ownership, risk, rollout.          |
| `plan-slice`        | Convert approved scope into a small implementable slice. |

Add this to each:

```md
## Boundary

This workflow stops before implementation. If the next action is code change, hand off to `new-feature`, `bug-regression`, `refactor-slice`, or `implementer`.
```

---

## Recommended additions

Add these shared fragments once and reference them instead of repeating:

```text
docs/ai/workflow-contract.md
docs/ai/workflow-output-contracts.md
docs/ai/workflow-safety-gates.md
docs/ai/workflow-stop-conditions.md
```

Then each workflow can say:

```md
## Read First

- `docs/ai/workflow-contract.md`
- `docs/ai/workflow-safety-gates.md`
- `docs/ai/workflow-stop-conditions.md`
```

This avoids every workflow drifting differently.

---

## Best immediate TODO list

```md
- [ ] Add `name`, `argument-hint`, `risk`, `writes`, and `primary-agent` to every workflow frontmatter.
- [ ] Convert `review-search-tool`, `script-inventory`, and `search-evidence` into full workflow files.
- [ ] Add standard `Safety Gates` and `Stop Conditions` sections to all workflows.
- [ ] Add exact `Output Contract` to every workflow.
- [ ] Clarify boundaries between `prd-and-tasks`, `architecture-plan`, and `plan-slice`.
- [ ] Move `mentor-mode` to `templates/modes/` or add `kind: mode`.
- [ ] Create generated `templates/workflows/INDEX.md` mapping workflow → agents → risk → write surface.
- [ ] Add validator rule: every workflow must have required frontmatter and required headings.
- [ ] Add validator rule: mutation workflows must reference dirty-worktree protection.
- [ ] Add validator rule: verification workflows must distinguish executed evidence from recommendations.
```

## Target score after changes

| Version                                 |  Score |
| --------------------------------------- | -----: |
| Current                                 | 74/100 |
| With consistent schema                  | 84/100 |
| With stop conditions + output contracts | 90/100 |
| With generated index + validator        | 94/100 |
| With agent/workflow coverage matrix     | 97/100 |

---

name: evidence-first-execution
description: Use as the mandatory execution protocol for non-trivial repository work that requires scope control, dirty-worktree protection, and evidence-backed reporting.
argument-hint: 'Describe the task, intended scope, write surface, and risk posture'
risk: medium
writes: none
primary-agent: implementer
related-agents:

- architect
- architecture-plan-writer
- refactorer
- bugfix
- upgrade
- reviewer
- release-auditor
- workflow-auditor

---

## Purpose

Apply a consistent evidence-first protocol before planning, editing, reviewing, or verifying repository changes.

This workflow is a base execution contract. Other workflows may reference it instead of repeating all safety rules.

## Use When

- repository files may be read, edited, generated, installed, or verified
- the task is non-trivial, multi-step, or risk-bearing
- success must be supported by commands, diffs, tests, file evidence, or explicit uncertainty
- the working tree may contain pre-existing user or agent changes
- the task may touch generated files, install surfaces, permissions, configuration, or tests

## Do Not Use When

- answering a purely general question with no repository interaction
- producing a read-only explanation that does not depend on current repo state
- running a specialised workflow that already embeds this protocol and names it explicitly

## Required Inputs

- requested outcome
- intended scope or affected area, if known
- allowed write surface, if any
- risk posture, if known
- verification expectation, if known

If required inputs are missing and cannot be safely inferred, stop and ask one focused question or proceed read-only.

## Read First

- `docs/ai/execution-protocol.md`
- `docs/ai/capabilities/evidence-first-execution/CAPABILITY.md`
- `docs/ai/workflow.md`
- `.github/instructions/context-gate.instructions.md`
- `.github/instructions/approval-boundaries.instructions.md`
- `.github/instructions/generated-artifacts.instructions.md`
- `.github/instructions/testing.instructions.md`

## Safety Gates

1. Start with `git status --short`.
2. Classify visible changes:
   - `user-owned`
   - `agent-owned`
   - `generated`
   - `unknown`

3. Treat all pre-existing non-task changes as protected.
4. Do not overwrite, delete, move, format, regenerate, or mass-edit protected changes.
5. Before mutation, state the intended path surface.
6. Prefer the smallest valid read, edit, test, and verification surface.
7. Do not run destructive commands unless explicitly approved.
8. Do not claim verification was run unless the command was executed and the result is known.
9. Separate executed evidence from recommended follow-up.
10. If generated artifacts are affected, verify source/template parity before claiming completion.

## Task Mode Classification

Classify the task before acting:

| Mode                    | Meaning                                                                  | Default posture                      |
| ----------------------- | ------------------------------------------------------------------------ | ------------------------------------ |
| `read-only`             | investigate, explain, review, or plan without mutation                   | no edits                             |
| `docs-only`             | update documentation without code/config mutation                        | narrow docs edits                    |
| `code-change`           | modify source, tests, or behavior                                        | narrow implementation                |
| `config-change`         | modify tooling, CI, runtime, permissions, install, or generated surfaces | high caution                         |
| `install-or-regenerate` | install, refresh, render, regenerate, or replace managed files           | confirmation required                |
| `review`                | inspect a diff or current state for correctness and risk                 | no edits                             |
| `verify`                | run checks and report evidence                                           | no edits unless explicitly requested |

## Workflow

1. Capture the user request and intended outcome.
2. Run `git status --short`.
3. Classify worktree state and protect pre-existing changes.
4. Identify current owner, affected paths, and write surface.
5. Select the smallest valid workflow:
   - `project-context` for unfamiliar ownership
   - `architecture-plan` or `plan-slice` for planning
   - `bug-regression` for bug fixes
   - `new-feature` for bounded features
   - `dependency-upgrade` for dependency changes
   - `docs-sync` for documentation alignment
   - `review-diff` for review
   - `verify-change` for verification

6. Apply only the minimum required patch or no patch if the selected workflow is read-only.
7. Run the narrowest relevant verification first.
8. Escalate verification only when the risk or changed surface requires it.
9. Report exact evidence, remaining uncertainty, and unresolved risk.

## Stop Conditions

Stop and report instead of continuing when:

- the write surface is unclear and mutation would be risky
- current worktree changes cannot be safely classified
- the task requires overwriting, deleting, moving, or regenerating protected files
- ownership cannot be identified with reasonable confidence
- requested scope crosses unrelated systems
- verification is required but unavailable
- required tools or prerequisites are missing
- install or generated-file checks show source drift
- the next action needs explicit human approval

## Output Contract

Return:

1. Task mode
2. Scope summary
3. Worktree state classification
4. Protected changes, if any
5. Affected paths and owners
6. Actions taken
7. Commands run
8. Verification result:
   - `passed`
   - `failed`
   - `partial`
   - `not-run`

9. Evidence
10. Remaining risks
11. Recommended next workflow or agent

## Evidence Rules

Use precise evidence:

- file paths
- line ranges when available
- command names and exit status
- test names
- diff summary
- commit hashes when history was inspected
- explicit `not-run` for checks not executed

Never convert a recommendation into a verified result.

## Gotchas

- A clean build is not proof of behavior correctness.
- A grep hit is not ownership proof.
- A generated file edit is unsafe unless its source is also updated or the generation path is intentional.
- Formatting unrelated files is scope creep.
- Permission, install, CI, runtime, and migration changes are higher risk than ordinary code edits.

---

name: evidence-first-execution
description: Use as the mandatory execution protocol for non-trivial repository work that requires scope control, dirty-worktree protection, and evidence-backed reporting.
argument-hint: 'Describe the task, intended scope, write surface, and risk posture'
risk: medium
writes: none
primary-agent: implementer
related-agents:

- architect
- architecture-plan-writer
- refactorer
- bugfix
- upgrade
- reviewer
- release-auditor
- workflow-auditor

---

## Purpose

Apply a consistent evidence-first protocol before planning, editing, reviewing, or verifying repository changes.

This workflow is a base execution contract. Other workflows may reference it instead of repeating all safety rules.

## Use When

- repository files may be read, edited, generated, installed, or verified
- the task is non-trivial, multi-step, or risk-bearing
- success must be supported by commands, diffs, tests, file evidence, or explicit uncertainty
- the working tree may contain pre-existing user or agent changes
- the task may touch generated files, install surfaces, permissions, configuration, or tests

## Do Not Use When

- answering a purely general question with no repository interaction
- producing a read-only explanation that does not depend on current repo state
- running a specialised workflow that already embeds this protocol and names it explicitly

## Required Inputs

- requested outcome
- intended scope or affected area, if known
- allowed write surface, if any
- risk posture, if known
- verification expectation, if known

If required inputs are missing and cannot be safely inferred, stop and ask one focused question or proceed read-only.

## Read First

- `docs/ai/execution-protocol.md`
- `docs/ai/capabilities/evidence-first-execution/CAPABILITY.md`
- `docs/ai/workflow.md`
- `.github/instructions/context-gate.instructions.md`
- `.github/instructions/approval-boundaries.instructions.md`
- `.github/instructions/generated-artifacts.instructions.md`
- `.github/instructions/testing.instructions.md`

## Safety Gates

1. Start with `git status --short`.
2. Classify visible changes:
   - `user-owned`
   - `agent-owned`
   - `generated`
   - `unknown`

3. Treat all pre-existing non-task changes as protected.
4. Do not overwrite, delete, move, format, regenerate, or mass-edit protected changes.
5. Before mutation, state the intended path surface.
6. Prefer the smallest valid read, edit, test, and verification surface.
7. Do not run destructive commands unless explicitly approved.
8. Do not claim verification was run unless the command was executed and the result is known.
9. Separate executed evidence from recommended follow-up.
10. If generated artifacts are affected, verify source/template parity before claiming completion.

## Task Mode Classification

Classify the task before acting:

| Mode                    | Meaning                                                                  | Default posture                      |
| ----------------------- | ------------------------------------------------------------------------ | ------------------------------------ |
| `read-only`             | investigate, explain, review, or plan without mutation                   | no edits                             |
| `docs-only`             | update documentation without code/config mutation                        | narrow docs edits                    |
| `code-change`           | modify source, tests, or behavior                                        | narrow implementation                |
| `config-change`         | modify tooling, CI, runtime, permissions, install, or generated surfaces | high caution                         |
| `install-or-regenerate` | install, refresh, render, regenerate, or replace managed files           | confirmation required                |
| `review`                | inspect a diff or current state for correctness and risk                 | no edits                             |
| `verify`                | run checks and report evidence                                           | no edits unless explicitly requested |

## Workflow

1. Capture the user request and intended outcome.
2. Run `git status --short`.
3. Classify worktree state and protect pre-existing changes.
4. Identify current owner, affected paths, and write surface.
5. Select the smallest valid workflow:
   - `project-context` for unfamiliar ownership
   - `architecture-plan` or `plan-slice` for planning
   - `bug-regression` for bug fixes
   - `new-feature` for bounded features
   - `dependency-upgrade` for dependency changes
   - `docs-sync` for documentation alignment
   - `review-diff` for review
   - `verify-change` for verification

6. Apply only the minimum required patch or no patch if the selected workflow is read-only.
7. Run the narrowest relevant verification first.
8. Escalate verification only when the risk or changed surface requires it.
9. Report exact evidence, remaining uncertainty, and unresolved risk.

## Stop Conditions

Stop and report instead of continuing when:

- the write surface is unclear and mutation would be risky
- current worktree changes cannot be safely classified
- the task requires overwriting, deleting, moving, or regenerating protected files
- ownership cannot be identified with reasonable confidence
- requested scope crosses unrelated systems
- verification is required but unavailable
- required tools or prerequisites are missing
- install or generated-file checks show source drift
- the next action needs explicit human approval

## Output Contract

Return:

1. Task mode
2. Scope summary
3. Worktree state classification
4. Protected changes, if any
5. Affected paths and owners
6. Actions taken
7. Commands run
8. Verification result:
   - `passed`
   - `failed`
   - `partial`
   - `not-run`

9. Evidence
10. Remaining risks
11. Recommended next workflow or agent

## Evidence Rules

Use precise evidence:

- file paths
- line ranges when available
- command names and exit status
- test names
- diff summary
- commit hashes when history was inspected
- explicit `not-run` for checks not executed

Never convert a recommendation into a verified result.

## Gotchas

- A clean build is not proof of behavior correctness.
- A grep hit is not ownership proof.
- A generated file edit is unsafe unless its source is also updated or the generation path is intentional.
- Formatting unrelated files is scope creep.
- Permission, install, CI, runtime, and migration changes are higher risk than ordinary code edits.

---

name: review-diff
description: Use when reviewing a change set for correctness, regression risk, policy fit, duplicate logic, and missing verification starting from the diff.
argument-hint: 'Describe the goal of the change, branch, PR, or diff under review'
risk: medium
writes: none
primary-agent: reviewer
related-agents:

- repository-reviewer
- release-auditor
- workflow-auditor
- architect
- implementer

---

## Purpose

Review the current change set from the diff first, then expand into unchanged files only when needed to verify a concern.

This workflow produces findings, risk assessment, and a merge/handoff recommendation. It does not implement fixes.

## Use When

- reviewing a branch, PR, patch, or current working-tree diff
- checking correctness before merge or handoff
- validating whether implementation matches a plan or ticket
- looking for regression risk, contract drift, missing tests, duplicate logic, or unsafe scope expansion
- deciding whether more verification is required

## Do Not Use When

- designing a feature before implementation; use `architecture-plan`
- writing an implementation plan; use `plan-slice`
- investigating root cause without a diff; use `repo-investigation`
- implementing fixes during review; hand off findings instead
- summarising the repository broadly

## Required Inputs

- change goal or ticket, if known
- base branch or PR target, if known
- review scope, if narrower than the full diff
- risk focus, if any:
  - correctness
  - security
  - privacy
  - migration
  - generated artifacts
  - permissions
  - install surface
  - tests
  - release safety

If base branch is unknown, resolve the likely merge base before final verdict.

## Read First

- `docs/ai/capabilities/review-diff/CAPABILITY.md`
- `docs/ai/capabilities/review-diff/checklist.md`
- `docs/ai/capabilities/review-diff/gotchas.md`
- `docs/ai/capabilities/review-diff/examples.md`
- `.github/instructions/testing.instructions.md`
- `.github/instructions/generated-artifacts.instructions.md`
- `.github/instructions/approval-boundaries.instructions.md`

## Safety Gates

- Stay read-only.
- Do not edit files during review.
- Start with `git status --short`.
- Resolve branch/base context before reviewing branch or PR changes.
- Prefer `BASE...HEAD` diff views for branch review.
- Inspect unchanged files only to validate a concrete concern.
- Do not treat style preferences as primary findings unless they affect maintainability, policy, or correctness.
- Do not issue a pass verdict without duplicate-logic screening evidence.
- Do not claim verification passed unless verification evidence is present.

## Review Priorities

1. Correctness
2. Regression risk
3. Security and privacy
4. Contract drift
5. Missing or weak tests
6. Branch/base correctness
7. Generated artifact drift
8. Permission or install-surface drift
9. Duplicate logic and missed reuse
10. Maintainability

## Severity Scale

| Severity  | Meaning                                                                         |
| --------- | ------------------------------------------------------------------------------- |
| `blocker` | Must fix before merge; likely broken, unsafe, destructive, or policy-violating. |
| `high`    | Strong risk of regression, security issue, data issue, or incorrect behavior.   |
| `medium`  | Real issue that should be fixed or explicitly accepted before merge.            |
| `low`     | Minor issue, cleanup, naming, local maintainability, or small missing evidence. |
| `note`    | Non-blocking observation or optional improvement.                               |

## Verdict Scale

| Verdict             | Meaning                                                                                                |
| ------------------- | ------------------------------------------------------------------------------------------------------ |
| `pass`              | No blocking findings; evidence is sufficient for the reviewed risk level.                              |
| `pass-with-notes`   | Mergeable, but minor follow-up exists.                                                                 |
| `changes-requested` | One or more blocker/high/medium issues should be fixed.                                                |
| `blocked`           | Review cannot be completed because required context, base, files, or verification evidence is missing. |

## Workflow

1. Identify review target:
   - working tree
   - staged diff
   - branch diff
   - PR diff

2. Run `git status --short`.
3. Resolve base:
   - current branch
   - target branch
   - merge base
   - preferred diff view

4. Inventory changed files before deep reading.
5. Classify changed surfaces:
   - code
   - tests
   - docs
   - config
   - generated files
   - permissions
   - install/runtime
   - CI/CD

6. Read the diff first.
7. For each concern, inspect only the minimum unchanged context needed.
8. Check whether changed logic duplicates or conflicts with existing logic.
9. Check tests and verification evidence against changed behavior.
10. Check generated artifacts against their source/template.
11. Check release-safety needs for medium/high-risk changes.
12. Produce findings before summary.
13. Provide verdict and recommended next step.

## Duplicate-Logic Screening

Before a `pass` or `pass-with-notes` verdict, include evidence that likely overlap was checked.

Flag reuse or replacement candidates when overlap is roughly `>=75%`.

Screen for:

- similar functions
- similar commands
- similar validators
- similar workflow files
- repeated permission patterns
- repeated shell/PHP wrapper logic
- duplicate generated/rendered copies
- stale aliases or compatibility shims

## Stop Conditions

Return `blocked` instead of a verdict when:

- base branch or merge base cannot be determined for a branch/PR review
- the diff cannot be inspected
- generated files changed but source/template changes are missing or unverifiable
- verification evidence is required but absent
- the patch crosses unrelated systems without an approved plan
- existing user changes make the reviewed diff ambiguous
- required repository context is missing

## Output Contract

Return findings first:

```md
## Verdict

pass | pass-with-notes | changes-requested | blocked

## Findings

### 1. <severity>: <title>

- Location: `<path>:<line>` or `<path>`
- Issue:
- Impact:
- Recommendation:
- Evidence:

## Risk Assessment

- Correctness:
- Regression:
- Security/privacy:
- Generated artifacts:
- Release safety:

## Verification Review

- Evidence present:
- Evidence missing:
- Smallest next verification:

## Duplicate-Logic Screening

- Checked:
- Result:
- Reuse candidates:

## Summary

## Recommended Next Step
```

If there are no findings, explicitly state:

```text
No blocking findings found in the reviewed diff.
```

## Gotchas

- Do not spend the review restating the diff.
- Do not bury findings under a long summary.
- Do not approve a diff solely because tests pass.
- Do not reject a diff solely for personal style preference.
- Do not inspect the whole repository without a review reason.
- Do not fix issues inside the review workflow.
- Do not ignore generated-file or permission drift.
