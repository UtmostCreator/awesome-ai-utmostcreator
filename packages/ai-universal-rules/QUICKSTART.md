# Quickstart

Use this flow when adapting the kit to a real repository.

Installer-first flow is the supported default in this repository. Manual copy steps are still described below as the structural equivalent.

## 0. Use The Installer First

Dry-run the base shape you want before copying anything manually:

```bash
php tools/ai/install-ai-kit.php --target /path/to/target-repo --profile copilot --dry-run
```

Common starting points:

- Copilot only: `php tools/ai/install-ai-kit.php --target /path/to/target-repo --profile copilot`
- OpenCode only: `php tools/ai/install-ai-kit.php --target /path/to/target-repo --profile opencode`
- Dual runtime: `php tools/ai/install-ai-kit.php --target /path/to/target-repo --profile dual`
- Runtime-only refresh without re-copying base policy: `php tools/ai/install-ai-kit.php --target /path/to/target-repo --profile copilot --no-base`
- Add scripts and stronger automation: `php tools/ai/install-ai-kit.php --target /path/to/target-repo --profile dual --with scripts-pack,advisor-pack`

For the full ordered command flow and selective pack recipes, use `../../docs/ai/install-order.md`.

## 1. Choose A Runtime Strategy

- OpenCode only
- GitHub Copilot only
- dual-tool repository with one shared capability layer

If you are unsure, start with one runtime first and add the second only after the base flow is stable.

## 2. Copy The Base Layer

For every repository:

- `templates/core/AGENTS.template.md` -> `AGENTS.md`
- `templates/core/project-context.template.md` -> `docs/ai/project-context.md`
- `templates/core/workflow.template.md` -> `docs/ai/workflow.md`
- `templates/capabilities/project-context/` -> `docs/ai/capabilities/project-context/`
- `templates/capabilities/verify-change/` -> `docs/ai/capabilities/verify-change/`
- `templates/capabilities/review-diff/` -> `docs/ai/capabilities/review-diff/`
- `templates/shared/guardrails/AI-GUARDRAILS.md` -> `docs/ai/AI-GUARDRAILS.md`

Add only when the repo really needs them:

- `templates/capabilities/bug-regression/`
- `templates/capabilities/release-safety/`
- `templates/capabilities/dependency-upgrade/`

## 3. Add One Runtime Adapter

For OpenCode:

- `templates/core/agents/`
- `templates/workflows/`
- `templates/commands/`

For GitHub Copilot:

- `templates/core/copilot-instructions.template.md` -> `.github/copilot-instructions.md`
- `templates/instructions/`
- `templates/core/agents/`
- `templates/workflows/` when the active surface supports prompt files

## 4. Replace Placeholders

Use `PLACEHOLDERS.md` as the source of truth.

Recommended order:

1. core policy and project-context files
2. capability folders
3. shared guardrail and approval templates
4. runtime adapters
5. optional packs

Delete sections that do not fit the repository instead of leaving vague generic prose.

## 5. Configure Workflow Layers

Before rollout, make sure the repo has a clear answer for each layer:

1. always-on policy: what broad rules apply often?
2. durable facts: what belongs in project context?
3. task entry points: which repeated one-off tasks deserve prompts or commands?
4. staged roles: do you need researcher, planner, implementer, reviewer, release auditor?
5. optional capability packs: which deeper procedures should stay out of always-on files?
6. enforcement: what should hooks, tool restrictions, or MCP boundaries guarantee?

## 6. Validate Before Use

Run a placeholder and leak check:

1. search for unresolved `<PLACEHOLDER_NAME>` tokens
2. search for project-specific names that should not exist in shared templates
3. search for duplicated rules across repo-wide instructions and capability folders
4. confirm every destructive or high-impact workflow has an approval gate

## 7. Test The Workflow, Not Just The Files

Run at least these scenarios in a toy repo or branch:

1. unfamiliar-area question -> should route through `project-context`
2. bug fix -> should prefer reproduction, minimal fix, evidence
3. existing diff review -> should start from the diff, not re-plan the feature
4. medium-risk change -> should require rollback, observability, and approval notes
5. prompt or command task -> should stay narrow and not leak global workflow detail everywhere
6. multi-step task -> should use staged handoffs rather than one bloated context

## 8. Add Optional Packs Later

Only after the base flow works cleanly:

- specialist agents
- specialist prompts
- release hooks
- MCP integrations
- delivery artifacts such as slice cards

## See Also

- `docs/ONBOARDING.md` — what to customize and in what order after install
- `docs/INSTALL-GITHUB-COPILOT.md` — GitHub Copilot runtime install guide
- `docs/INSTALL-OPENCODE.md` — OpenCode runtime install guide
- `docs/INSTALL-CATALOG.md` — full profile and pack index
- `PLACEHOLDERS.md` — placeholder reference for all copied templates
- `docs/RELEASE-BUNDLES.md` — export bundles for adoption and release previews
- `../../docs/ai/install-order.md` — full ordered command flow and selective pack recipes

## 9. Use Generated Browse And Release Assets

After changing templates or docs:

1. run `php tools/ai/generate-ai-catalog.php`
2. inspect `packages/ai-universal-rules/docs/BROWSE.md`
3. validate with `php tools/ai/validate-ai-catalog.php`
4. preview a starter bundle with `php tools/ai/export-ai-universal-rules.php --profile=dual-runtime-starter`

Also generate installation instruction docs from live registries:

1. run `php tools/ai/ai.php install-docs --write`
2. verify drift with `php tools/ai/ai.php install-docs --check`

If you want installer-managed post-install helpers, use `--run-after-install` with one of these ids:

- `repomix-context`
- `repomix-tree`
- `repomix-scc-router`
- `pack-context`
- `repo-tool-inventory`
- `install-mandatory-tools`

## What Good Looks Like

- policy is short and stable
- procedures live in capabilities, prompts, commands, or agent workflows
- verification reports evidence instead of generic confidence
- destructive actions are gated
- runtime debug steps are documented for misbehavior
- one canonical template layer defines each reusable workflow asset
