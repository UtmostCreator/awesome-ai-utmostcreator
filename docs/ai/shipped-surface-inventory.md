# Shipped Surface Inventory

Per-file companion to the subtree-level "Shipped Surface Role Classification" table in
`docs/ai/integration-matrix.md`. Every file under `packages/ai-universal-rules/templates/**`
is listed here with its role and load path, so a dead-file review can name a specific file
rather than only a subtree. Snapshot taken by direct enumeration (`fd -t f . packages/ai-universal-rules/templates`)
cross-checked against `tools/ai/install/packs.php`; refresh this file whenever the template
tree gains or loses a top-level file.

Roles: `always-on-critical` / `deterministic-load` / `optional-support` / `generated-or-install-only`
(see `docs/ai/integration-matrix.md` for the definitions and dead-file reachability rules).

## core/ — root adapters and canonical-doc sources

| File | Role | Load path |
|---|---|---|
| `core/AGENTS.template.md` | always-on-critical | renders to root `AGENTS.md` (`packs.php` base pack) |
| `core/copilot-instructions.template.md` | always-on-critical | renders to `.github/copilot-instructions.md` (`packs.php` adapter-copilot pack) |
| `core/CLAUDE.template.md` | always-on-critical | renders to root `CLAUDE.md` (`packs.php` adapter-claude pack); added by the Claude adapter parity plan (`docs/tickets/arch-todo-claude-code-adapter-parity-20260704-120000`, P1-1), replacing the prior hand-maintained `CLAUDE.md` |
| `core/opencode.json` | always-on-critical | renders to `opencode.jsonc`, `never_auto_merge` (`packs.php` adapter-opencode pack) |
| `core/project-context.template.md` | always-on-critical | renders to `docs/ai/project-context.md`; listed in OpenCode `instructions[]` |
| `core/project-context.placeholders.md` | optional-support | renders to `docs/ai/project-context-placeholders.md`, reference companion |
| `core/execution-protocol.template.md` | always-on-critical | renders to `docs/ai/execution-protocol.md`; listed in OpenCode `instructions[]` |
| `core/ai-file-standards.template.md` | always-on-critical | renders to `docs/ai/ai-file-standards.md`; listed in OpenCode `instructions[]` |
| `core/generated-artifacts.template.md` | deterministic-load | renders to `docs/ai/generated-artifacts.md`; removed from OpenCode `instructions[]` in Phase 3.1, now routed by explicit path from `AGENTS.md` "Read This First" |
| `core/workflow.template.md` | always-on-critical | renders to `docs/ai/workflow.md`; listed in OpenCode `instructions[]` |
| `core/POST-INSTALL.template.md` | deterministic-load | renders to `docs/ai/POST-INSTALL.md`, read post-install only |
| `core/project-stack.template.md` | optional-support | renders to `docs/ai/project-stack.md`, `skip-if-exists`, user-owned once created |
| `core/copilot-vscode-settings.template.json` | optional-support | renders to `.vscode/settings.json`, `skip-if-exists` |
| `core/project/README.md` | optional-support | renders to `docs/ai/project/README.md`, user-owned starter |
| `core/project/project-interaction.md` | optional-support | renders to `docs/ai/project/project-interaction.md`, user-owned starter |
| `core/project/conventions.md` | optional-support | renders to `docs/ai/project/conventions.md`, user-owned starter |

## core/agents/** — base staged-agent roles (13 files)

All render through the runtime-specific agent renderer into `.github/agents/*.agent.md`
(`adapter-copilot` pack), `.opencode/agents/*.md` (`adapter-opencode` pack), and — as of the
Claude adapter parity plan (`docs/tickets/arch-todo-claude-code-adapter-parity-20260704-120000`,
P0) — `.claude/agents/*.md` (`adapter-claude` pack, via `claude-agent-renderer.php`):
`architect.md`, `architecture-plan-writer.md`, `bootstrapper.md`, `config-maintainer.md`,
`implementer.md`, `post-install.md`, `refactorer.md`, `release-auditor.md`,
`repository-researcher.md`, `repository-reviewer.md`, `researcher.md`, `reviewer.md`,
`workflow-auditor.md`.
Role: `deterministic-load` (agent invocation on all three runtimes).

## claude/ — Claude-specific templates (added by the Claude adapter parity plan)

| File | Role | Load path |
|---|---|---|
| `claude/settings.json` | deterministic-load | merged (never replaced) into `.claude/settings.json` via `claude-settings-merge.php`, `adapter-claude` pack; carries `permissions.allow`/`deny` baseline |

## instructions/*.instructions.md — Copilot path/topic rules (22 files)

All render 1:1 into `.github/instructions/*.instructions.md` (`adapter-copilot` pack, whole-dir
copy) and load deterministically via each file's own `applyTo` frontmatter glob:
`ai-file-standards`, `ai-scripts`, `ai-search`, `ai-tooling`, `ai-workflow`,
`approval-boundaries`, `architecture`, `base`, `ci-workflows`, `composer`, `config-infra`,
`context-gate`, `copilot-script-enforcement`, `execution-protocol`, `frontend`,
`generated-artifacts`, `php`, `security`, `shell`, `targets`, `testing`, `tools`.
Role: `deterministic-load`. `base`, `security`, `approval-boundaries`, `context-gate`,
`execution-protocol`, `copilot-script-enforcement` use `applyTo: "**"` (practical always-on
set for Copilot); the rest are path-scoped.

## capabilities/** — reusable workflow procedures

| Capability dir | Role | Load path |
|---|---|---|
| `capabilities/README.md` | deterministic-load | capability router, referenced from `AGENTS.md`/copilot-instructions |
| `capabilities/project-context/*` (4 files) | deterministic-load | `capabilities-core` pack -> `docs/ai/capabilities/project-context/` |
| `capabilities/verify-change/*` (5 files) | deterministic-load | `capabilities-core` pack -> `docs/ai/capabilities/verify-change/` |
| `capabilities/review-diff/*` (5 files) | deterministic-load | `capabilities-core` pack -> `docs/ai/capabilities/review-diff/` |
| `capabilities/evidence-first-execution/*` (5 files) | always-on-critical | `capabilities-core` pack; also directly checked by `validate-install-surface.php` |
| `capabilities/clarification-and-handoff/CAPABILITY.md` | deterministic-load | `capabilities-core` pack -> `docs/ai/capabilities/clarification-and-handoff/` (new, this slice) |
| `capabilities/bug-regression/*` (6 files) | deterministic-load | `capabilities-extended` pack |
| `capabilities/release-safety/*` (5 files) | deterministic-load | `capabilities-extended` pack |
| `capabilities/dependency-upgrade/*` (5 files) | deterministic-load | `capabilities-governance` pack |
| `capabilities/mentor-mode/*` (6 files) | deterministic-load | `capabilities-governance` pack |

## workflows/** — prompt/command/skill launchers (19 files)

Each file renders three ways from the same source: `.github/prompts/*.prompt.md`
(`adapter-copilot`), `.github/skills/*/SKILL.md` (`adapter-copilot`, skill-dirs),
`.opencode/skills/*/SKILL.md` and `.opencode/commands/*.md` (`adapter-opencode`).
Role: `deterministic-load` (explicit human/agent invocation). Files: `architecture-plan`,
`bug-regression`, `dependency-upgrade`, `docs-sync`, `evidence-first-execution`,
`mentor-mode`, `new-feature`, `plan-slice`, `post-install-setup`, `prd-and-tasks`
(Phase 4.1; reuses `clarification-and-handoff` and `architecture-plan-writer` rather
than restating them), `project-context`, `regression-test`, `release-safety`,
`repo-investigation`, `review-diff`, `review-search-tool`, `script-inventory`,
`search-evidence`, `verify-change`.

## commands/** — OpenCode-only command wrappers (4 files)

`post-install-setup.md`, `search-evidence.md`, `verify-ai-wiring.md`, `verify.md`. Role:
`deterministic-load`, routed to `.opencode/commands/*` via `adapter-opencode` pack
(`install_type: opencode-commands`).

## skills/** — standalone OpenCode skill adapters (2 files)

`ai-search/SKILL.md`, `ai-scripts/SKILL.md`. Role: `deterministic-load`, direct
file-level pack entries into `.opencode/skills/<name>/SKILL.md` (`adapter-opencode` pack).

## optional/agents/** — opt-in staged-agent roles (11 files)

Whole-dir copy through the runtime-specific agent renderer into `.opencode/agents-optional`
(`optional-agents-opencode-pack`) and merged into `.github/agents` (`optional-agents-copilot-pack`).
Role: `deterministic-load` when the optional pack is selected and the agent is not marked
`hidden: true` in its own frontmatter (hidden agents, e.g. `ui-builder.md`, are intentionally
excluded from shipped output by the renderer — that is a deliberate suppression, not
dead-file drift). Files: `agent-creator.md`, `agent-creator-runtime-guardian.md`,
`agent-creator-semantic-verifier.md`, `agent-creator-static-validator.md`,
`agent-creator-supervisor.md`, `bugfix.md`, `build-config.md`, `docs.md`,
`infra-auditor.md`, `ui-builder.md`, `upgrade.md`.

## optional/delivery/** — opt-in delivery templates (2 files)

`README.md`, `slice-card.template.md`. Role: `optional-support`, `delivery-pack`,
`skip-if-exists` into `docs/ai/delivery/`.

## shared/** — cross-runtime shared templates (4 files)

`approvals/APPROVAL-PACKET.template.md`, `guardrails/AI-GUARDRAILS.md`,
`project-interaction.md`, `verification/VERIFICATION-EVIDENCE.template.md`. Role:
`always-on-critical` for `guardrails/AI-GUARDRAILS.md` (base pack, required); the rest are
`deterministic-load`/`optional-support` via `shared-templates-pack`.

## snippets/** — shared render-source snippets (7 files)

| File | Role | Load path |
|---|---|---|
| `snippets/agent-tools-execute.snippet.md` | deterministic-load | synced into agent bodies by `tools/ai/generate-agent-snippets.php` |
| `snippets/agent-tools-readonly.snippet.md` | deterministic-load | synced into agent bodies by `tools/ai/generate-agent-snippets.php` |
| `snippets/behavioral-baseline.snippet.md` | generated-or-install-only | canonical source manually mirrored into `core/AGENTS.template.md` and `core/copilot-instructions.template.md`; also copied wholesale via `shared-templates-pack` to `docs/ai/snippets/` |
| `snippets/anti-pattern-examples.md` | deterministic-load (Phase 5.3) | named by path from the always-on Behavioral Baseline bullet list in `AGENTS.template.md`/`copilot-instructions.template.md` (and their rendered outputs), so it is reachable at task time, not just an install-time reference copy; also copied via `shared-templates-pack` to `docs/ai/snippets/` |
| `snippets/approvals.snippet.md` | generated-or-install-only | no content-injection consumer found; reachable only via the whole-dir `shared-templates-pack` copy to `docs/ai/snippets/` (install-time reference copy, not read at task time) |
| `snippets/verification.snippet.md` | generated-or-install-only | same as `approvals.snippet.md` |
| `snippets/workflow.snippet.md` | generated-or-install-only | same as `approvals.snippet.md` |

## github/** — Copilot hooks and CI workflow sources

`hooks/tool-policy.json` (always-on-critical, enforcement), `hooks/tool-guardian.json`,
`hooks/scripts/tool-guardian.sh`, `hooks/scripts/tool-guardian.ps1`,
`hooks/scripts/command-policy.compiled.sh` (all `deterministic-load`, `hooks-pack`),
`pull_request_template.md` (`deterministic-load`, `adapter-copilot` pack),
`workflows/validate-ai-surface.yml` (`deterministic-load`, `ci-pack`),
`workflows/test-external-install.yml`, `workflows/export-ai-universal-rules-preview.yml`
(`optional-support`, `kit-authoring-pack`, source-repo-only).

## docs/ai/tools/** — tool-map and action docs (5 files)

`tools/ai-search.md`, `tools/tool-map.md`, `tools/actions/preview-file.md`,
`tools/actions/search-evidence.md`, `tools/actions/use-ai-script.md`. Role:
`deterministic-load`, `scripts-pack`. Removed from OpenCode `instructions[]` in Phase 3.1
(the critical-topic matrix named no topic owned by these docs); reachability now runs
`AGENTS.md` (always-on) -> `tools/tool-map.md` (named by path) -> its own "See Also"
section, which names the other 3 by path.

## internal/** — confirmed dead-file example

`internal/agents/ai-maintenance.md` — **dead** by all three reachability rules: not in any
`opencode.jsonc` `instructions[]` or root adapter (rule 1), not referenced by any
`packs.php` entry (`rg templates/internal` across `tools/ai/**` returns no matches, rule 2),
and no other shipped surface links to it by path (rule 3; only self-reference found). Not
deleted in this slice (deletion is approval-gated); flagged here as the worked example this
classification exercise is meant to produce.

## Maintenance

Refresh this inventory in the same slice as any change that adds, removes, or relocates a
top-level shipped template file. Update `docs/ai/integration-matrix.md`'s reference to this
file if either file's structure changes materially.
