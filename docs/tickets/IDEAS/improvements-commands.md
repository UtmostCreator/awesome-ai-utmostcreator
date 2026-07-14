Your commands should become **small wrappers** around workflows.

> **Correction (grounded review, 2026-07-09):** the "42/100" score and the "current
> commands" list below only inventoried `packages/ai-universal-rules/templates/commands/`
> (5 thin files). That is **not** the actual shipped command/skill surface. Confirmed by
> direct inspection of `tools/ai/install/packs.php` (lines ~164-207) and the installed
> adapters in this repo:
>
> - EVERY file in `packages/ai-universal-rules/templates/workflows/` (23 files) is
>   already rendered as a slash-invokable primitive on all three providers:
>   `.opencode/commands/*.md` (`install_type: opencode-commands`), `.github/prompts/*.prompt.md`
>   (`rename_ext: .prompt.md`, `runtime-copilot.sh:43`), and `.claude/skills/*/SKILL.md` +
>   `.opencode/skills/*/SKILL.md` + `.github/skills/*/SKILL.md` (`install_type: skill-dirs`).
> - Confirmed counts in this repo: `.opencode/commands/` = 24 files, `.github/prompts/` = 23
>   files, `.claude/skills/` = 23 dirs, `.opencode/skills/` = 24 dirs, `.github/skills/` = 23 dirs.
> - So `plan.md` already exists as `plan-slice`/`architecture-plan` (command + prompt + skill),
>   `bugfix.md` as `bug-regression`, `review.md` as `review-diff`, `investigate.md` as
>   `repo-investigation`, `docs-sync.md`, `upgrade.md` as `dependency-upgrade`, `release-check.md`
>   as `release-safety`, `scan-stack.md`, `permissions-preview.md` as `generate-permissions` — all
>   of the "P0 — missing commands" below are already shipped, just named after the workflow
>   instead of the shorter proposed alias.
> - What is genuinely true and still open: only 5 files live under the thin
>   `templates/commands/` tier (`install`, `post-install-setup`, `search-evidence`,
>   `verify-ai-wiring`, `verify`) — this is the correct "thin command, not 1:1 with every
>   workflow" tier described later in this same file ("Do not add commands for every
>   workflow"), and it is intentionally small. `.claude/commands/*.md` only gets these 5 (not the
>   23 workflow-derived ones — Claude gets workflows as **skills**, not as `.claude/commands/`,
>   per `packs.php:207` vs `:201`), which is a real, narrower asymmetry worth tracking — see
>   `docs/tickets/IDEAS/plan-provider-wiring-reconciliation.md`.
> - Revised coverage read: **naming/alias clarity gap, not a missing-entrypoint gap.** See
>   `docs/tickets/IDEAS/architecture-plan.md` §"Commands And Workflows" for the corrected
>   assessment and the one real recommendation (a short alias layer), instead of the ~25-file
>   command-creation program implied below.

## Current command coverage: ~~42/100~~ corrected: command *entrypoints* are ~90/100 (23/23 workflows already reachable on every provider); the open gap is naming/alias ergonomics and the Claude `.claude/commands` vs `.claude/skills` asymmetry, not missing commands.

Current thin `templates/commands/` tier (deliberately not 1:1 with workflows):

```text id="2y3c4t"
commands/install.md
commands/post-install-setup.md
commands/search-evidence.md
commands/verify-ai-wiring.md
commands/verify.md
```

Every workflow in `templates/workflows/*.md` (23 files) is additionally already a command/prompt/skill on every provider (see correction above). The remaining real gap is that the invocation name is the workflow name (`bug-regression`, `dependency-upgrade`, `release-safety`) rather than a short user-facing verb (`bugfix`, `upgrade`, `release-check`), and that agent-governance workflows listed as "P0 missing" below (`agent-creation-pipeline`, `agent-critic-review`, `agent-fleet-assessment`, `runtime-guardrail-audit`, `static-agent-validation`, `semantic-agent-verification`) are confirmed **still absent** from `templates/workflows/` — those, not the P0 table below, are the real missing-workflow list (see `improvements-workflows.md`, which already tracks this correctly).

## P0 — missing commands to add first

| Command                  | Maps to workflow                   | Priority | Why needed                                            |
| ------------------------ | ---------------------------------- | -------: | ----------------------------------------------------- |
| `plan.md`                | `plan-slice` / `architecture-plan` |       P0 | Main entrypoint before implementation.                |
| `prd.md`                 | `prd-and-tasks`                    |       P0 | For vague feature ideas before planning.              |
| `bugfix.md`              | `bug-regression`                   |       P0 | Common workflow: reproduce → fix → verify.            |
| `review.md`              | `review-diff`                      |       P0 | Main pre-merge/review command.                        |
| `investigate.md`         | `repo-investigation`               |       P0 | Read-first root-cause investigation.                  |
| `docs-sync.md`           | `docs-sync`                        |       P0 | Needed after workflow/code/config changes.            |
| `upgrade.md`             | `dependency-upgrade`               |       P0 | Safe dependency/framework/tooling upgrade path.       |
| `release-check.md`       | `release-safety`                   |       P0 | Rollout/rollback/migration risk review.               |
| `scan-stack.md`          | `scan-stack`                       |       P0 | Required before permission overlays.                  |
| `permissions-preview.md` | `generate-permissions`             |       P0 | Clearer user-facing name than `generate-permissions`. |

## P1 — agent-governance commands

These matter because your missing workflows are mostly around agent creation, validation, and audit.

| Command                 | Maps to workflow                                          | Why needed                                                         |
| ----------------------- | --------------------------------------------------------- | ------------------------------------------------------------------ |
| `agent-create.md`       | `agent-creation-pipeline`                                 | One safe command for supervisor → creator → validators → guardian. |
| `agent-validate.md`     | `static-agent-validation` + `semantic-agent-verification` | Validate one agent before shipping.                                |
| `agent-critic.md`       | `agent-critic-review`                                     | Review one proposed/existing agent for risk and overreach.         |
| `agent-fleet-assess.md` | `agent-fleet-assessment`                                  | Score all agents, duplication, gaps, permissions, stale docs.      |
| `workflow-audit.md`     | `workflow-audit`                                          | Detect missing/overlapping/stale workflows and commands.           |
| `runtime-guardrails.md` | `runtime-guardrail-audit`                                 | Check stop conditions, hooks, permissions, logging, rollback.      |

## P1 — implementation support commands

| Command              | Maps to workflow      | Why needed                                           |
| -------------------- | --------------------- | ---------------------------------------------------- |
| `feature.md`         | `new-feature`         | Bounded feature implementation entrypoint.           |
| `regression-test.md` | `regression-test`     | Add/prove failing test without fixing.               |
| `refactor.md`        | `refactor-slice`      | Safe no-behaviour-change refactor path.              |
| `config-change.md`   | `build-config-change` | CI/tooling/runtime config changes are high-risk.     |
| `ui-build.md`        | `ui-build`            | UI change workflow with visual/accessibility checks. |
| `infra-audit.md`     | `infra-audit`         | CI/CD/secrets/deployment/runtime review.             |

## P2 — maintenance and inventory commands

| Command                   | Maps to workflow       | Why needed                                     |
| ------------------------- | ---------------------- | ---------------------------------------------- |
| `replace-placeholders.md` | `replace-placeholders` | Existing workflow but missing command.         |
| `script-inventory.md`     | `script-inventory`     | Existing workflow but missing command.         |
| `review-search-tool.md`   | `review-search-tool`   | Existing thin workflow but missing command.    |
| `project-context.md`      | `project-context`      | Useful for unfamiliar repos before edits.      |
| `repo-review.md`          | `repository-review`    | Whole-repository review, not just diff review. |
| `bootstrap.md`            | `project-bootstrap`    | First-time repo context/setup flow.            |

## Recommended final command set

```text id="54p5y8"
packages/ai-universal-rules/templates/commands/
  install.md
  post-install-setup.md
  verify.md
  verify-ai-wiring.md
  search-evidence.md

  plan.md
  prd.md
  investigate.md
  review.md
  feature.md
  bugfix.md
  regression-test.md
  refactor.md
  docs-sync.md
  upgrade.md
  release-check.md

  scan-stack.md
  permissions-preview.md
  replace-placeholders.md
  script-inventory.md
  project-context.md

  agent-create.md
  agent-validate.md
  agent-critic.md
  agent-fleet-assess.md
  runtime-guardrails.md
  workflow-audit.md
```

## Do not add commands for every workflow

Avoid this:

```text id="k70m9h"
evidence-first-execution.md
architecture-plan.md
plan-slice.md
dependency-upgrade.md
generate-permissions.md
```

Better user-facing aliases:

| Internal workflow                  | User-facing command              |
| ---------------------------------- | -------------------------------- |
| `evidence-first-execution`         | no direct command; base protocol |
| `architecture-plan` / `plan-slice` | `plan.md`                        |
| `dependency-upgrade`               | `upgrade.md`                     |
| `generate-permissions`             | `permissions-preview.md`         |
| `release-safety`                   | `release-check.md`               |
| `repo-investigation`               | `investigate.md`                 |
| `review-diff`                      | `review.md`                      |

## Command template to standardise all commands

```md id="o2fvvy"
---
description: <short user-facing action>
argument-hint: "<what the user should provide>"
---

Use workflow: `packages/ai-universal-rules/templates/workflows/<domain>/<workflow>.md`

## Inputs

- `$ARGUMENTS`

## Required Sequence

1. Load the mapped workflow.
2. Apply its safety gates.
3. Run only the commands allowed by that workflow.
4. Report executed evidence separately from recommendations.

## Output

- workflow used
- scope
- actions taken
- commands run
- result
- next step
```

## Best next TODO

```md id="ji93w6"
- [ ] Add P0 commands: plan, prd, bugfix, review, investigate, docs-sync, upgrade, release-check, scan-stack, permissions-preview.
- [ ] Add P1 agent-governance commands: agent-create, agent-validate, agent-critic, agent-fleet-assess, runtime-guardrails, workflow-audit.
- [ ] Rename user-facing command concepts: generate-permissions → permissions-preview, release-safety → release-check, repo-investigation → investigate.
- [ ] Keep evidence-first-execution as a base workflow, not a command.
- [ ] Add validator rule: every command must map to exactly one primary workflow.
- [ ] Add generated index: command → workflow → agents → write surface → risk.
```

Target command coverage after P0: **78/100**.
After P0 + P1 governance: **92/100**.

## Difference

| Item         | Purpose                          | Location                                                       | Should contain                                                                              |
| ------------ | -------------------------------- | -------------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| **Command**  | User-facing entrypoint           | `templates/commands/*.md`                                      | How to start a task from `$ARGUMENTS`; which workflow to load; minimal routing/output rules |
| **Workflow** | Reusable process contract        | `templates/workflows/*.md`                                     | When to use, safety gates, steps, stop conditions, output contract                          |
| **Agent**    | Executor with role + permissions | `templates/core/agents/*.md`, `templates/optional/agents/*.md` | What the agent is allowed to do, tools, boundaries, behaviour                               |

## Simple model

```text
Command = button / slash command
Workflow = standard operating procedure
Agent = worker with permissions
```

Example:

```text
/review "check this diff"
  → command: commands/review.md
  → workflow: workflows/review-diff.md
  → agent: reviewer
```

## In your repo

### Command

A command should be **thin**:

```md
---
description: Review current diff
argument-hint: "Describe the change or PR"
---

Use workflow: `packages/ai-universal-rules/templates/workflows/review-diff.md`

Inputs:

- `$ARGUMENTS`

Required sequence:

1. Load the workflow.
2. Apply workflow safety gates.
3. Report using workflow output contract.
```

A command should **not** duplicate the whole workflow.

---

### Workflow

A workflow should be **durable and reusable**:

```md
---
name: review-diff
description: Use when reviewing a change set for correctness, regression risk, and missing verification
---

## Use When

## Do Not Use When

## Safety Gates

## Workflow

## Stop Conditions

## Output Contract
```

It can be used by:

```text
commands/review.md
agents/reviewer.md
agents/repository-reviewer.md
agents/release-auditor.md
```

## Key distinction

| Question                               | Put it in command |        Put it in workflow |
| -------------------------------------- | ----------------: | ------------------------: |
| “What should the user type?”           |               Yes |                        No |
| “How should `$ARGUMENTS` be passed?”   |               Yes |                        No |
| “Which workflow should run?”           |               Yes |                 Sometimes |
| “What are the safety gates?”           |                No |                       Yes |
| “What exact steps should be followed?” |                No |                       Yes |
| “What is the output format?”           |       Maybe short |                       Yes |
| “Which agent should execute?”          |             Maybe |                     Maybe |
| “What files can be edited?”            |                No | Yes, or agent permissions |

## Good mapping

```text
commands/install.md
  → workflows/install.md

commands/post-install-setup.md
  → workflows/post-install-setup.md

commands/search-evidence.md
  → workflows/search-evidence.md

commands/verify.md
  → workflows/verify-change.md

commands/review.md
  → workflows/review-diff.md

commands/bugfix.md
  → workflows/bug-regression.md

commands/plan.md
  → workflows/plan-slice.md or workflows/architecture-plan.md

commands/upgrade.md
  → workflows/dependency-upgrade.md
```

## Rule of thumb

Create a **workflow** when you need a reusable process.

Create a **command** only when the user should be able to invoke that process directly.

## Best structure

```text
templates/
  commands/
    review.md
    bugfix.md
    plan.md
    verify.md

  workflows/
    review/review-diff.md
    implementation/bug-regression.md
    planning/plan-slice.md
    core/verify-change.md

  agents/
    reviewer.md
    bugfix.md
    implementer.md
```

## Recommendation

Your commands should become **small wrappers** around workflows.

Target ratio:

```text
1 workflow can have 0–3 commands
1 command should have exactly 1 primary workflow
1 agent can use many workflows
```

Current issue: some command files are acting like mini-workflows. Move the durable logic into `templates/workflows/`, then make commands thin entrypoints.
