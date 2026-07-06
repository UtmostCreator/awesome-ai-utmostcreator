# awesome-ai-utmostcreator

A ready-to-use **AI workflow** starter kit you install into your own project so AI tools work
consistently and safely from day one.

Think of it as a starter kit you install into your own project, not an app you run on its own. It
copies tested templates, helper scripts, and safety rules into your repo so GitHub Copilot,
OpenCode, and Claude have clear, shared rules to follow.

## At a Glance

<!-- markdownlint-disable MD060 -->
| Item                         | Answer                                                           |
| ---------------------------- | ---------------------------------------------------------------- |
| What is this?                | AI workflow kit for software repositories                        |
| Where do I run it?           | From this repo, pointed at another repo (the target)             |
| What does it support?        | GitHub Copilot, OpenCode, Claude (`AGENTS.md` / `CLAUDE.md`)     |
| Does it edit code by itself? | No — it scaffolds, prepares, and validates; you stay in control  |
| Risk level                   | Low by default (backs up and verifies); no guaranteed safety     |
| Main command                 | `bash install-ai-kit.sh /path/to/your-project`                  |
<!-- markdownlint-enable MD060 -->

## What It Does

It adds ready-made **configuration** (sensible shared rules), safety rules so tools ask before risky
actions, helper scripts that keep the setup correct, and tooling that prepares your files — all from one source supporting GitHub Copilot, OpenCode, and Claude.

## How It Works

This repository is the **source kit** — you run its installer and point it at a separate target
project; the kit is never the project you are building.

```text
  this source repo            your project
  (the kit)                   (the target)
  ┌──────────────┐  install   ┌──────────────┐    configures
  │ templates    │ ─────────▶ │ AI rules     │ ─────────────▶  your AI tools
  │ scripts      │            │ scripts      │                 (Copilot /
  │ safety rules │            │ safety rules │                  OpenCode /
  └──────────────┘            └──────────────┘                  Claude)
```

## What This Is Not

- It is **not** an AI model, an IDE, or a chatbot, and does **not** replace GitHub Copilot, OpenCode, or Claude — it configures them.
- It does **not** make risky, automatic changes to your code on its own.
- It is **not** a universal tool that builds and ships software for you. It gives ideas, scaffolds,
  prepares, validates, and does routine work so you can **maintain sanity** — the result is in your
  hands, and human review stays the final authority.

## Quick Start

From a local clone of this repository, run the installer and point it at your target project:

```bash
bash install-ai-kit.sh /path/to/your-project
```

That validates this source checkout, installs into your project with a backup, and runs
verification. For reinstalls with `--force`, runtime selection (Copilot-only / OpenCode-only /
Claude-via-base), backups, rollback, and the advanced cross-repo sequence, see the
[installation guide](readme-install.md). Installed surfaces are listed in
[What Gets Added](#what-gets-added-to-your-project) below.

## After Installing — Do These (recommended)

```bash
php tools/ai/ai.php verify                # validate the install
php tools/ai/ai.php placeholders --fail   # check no placeholders are left
```

Then, in the installed project: run the `post-install-setup` agent/command to update required files;
build `docs/ai/project-context.md` yourself or with the `researcher` agent; and put cross-project
logic in `docs/ai/shared/project-interaction.md` (per-project defaults go in
`docs/ai/project/project-interaction.md`). Custom agents/rules need the optional agent-creator pack.

## What It Ships With — and Why

- **AI agents and recommended order** — research → plan → implement → review → release, chained for
  best results. See the roster and purpose in [docs/ai/agents.md](docs/ai/agents.md), the shipped
  inventory and runtime differences in [docs/ai/AGENTS-MANIFEST.md](docs/ai/AGENTS-MANIFEST.md), the
  chaining order in [docs/ai/workflow.md](docs/ai/workflow.md), and profile/edition coverage
  (`basic`, `standard`, `creator`, `full`, `agents-only`) in [readme-install.md](readme-install.md).
- **Capabilities** — load-on-demand reusable workflows (bug-regression, release-safety,
  review-diff, and more). See [docs/ai/capabilities/README.md](docs/ai/capabilities/README.md).
- **Gotchas** — each capability ships a `gotchas.md` capturing recurring traps and the safe
  response, kept next to the workflow. See [docs/ai/ai-file-standards.md](docs/ai/ai-file-standards.md).
- **Mentor mode (L0–L5)** — help escalates in layers so you learn instead of just receiving answers:
  **L0** frame, **L1** name the concept, **L2** point to the file/doc, **L3** scaffold, **L4**
  worked-adjacent example, **L5** direct solution; a struggle gate and teach-it-back step reinforce
  retention. See [docs/ai/capabilities/mentor-mode/CAPABILITY.md](docs/ai/capabilities/mentor-mode/CAPABILITY.md).
- **AI builder (agent-creator)** — an opt-in pipeline (supervisor → creator → validators) plus the
  architecture-plan-writer to scaffold new agents safely. It is **optional**
  (`optional-agents-opencode-pack` / `optional-agents-copilot-pack`, removable with `--without ...`).
  See [docs/ai/agents.md](docs/ai/agents.md).

## Per-Language Verification Wrappers

Alongside the full `ai-verify.sh` gate, five thin, **manual-only** wrappers narrow verification to
one language, running only tools already present in your project — never installing anything:

```bash
bash scripts/ai/ai-verify-php.sh .    # Pint --test, PHPStan, Psalm, Rector --dry-run, composer validate/audit
bash scripts/ai/ai-verify-js.sh .     # ESLint, Biome, Vitest --changed, knip
bash scripts/ai/ai-verify-ts.sh .     # tsc --noEmit, ESLint/Biome, Vitest --changed
bash scripts/ai/ai-verify-vue.sh .    # vue-tsc, nuxi typecheck (when Nuxt files changed), ESLint/Biome
bash scripts/ai/ai-verify-html.sh .   # Biome -> htmlhint -> clean skip
```

These are **not wired into any agent** — run them yourself, or point one at a project path:
`bash scripts/ai/ai-verify-php.sh path/to/project`. Default scope is `changed` (dirty/staged/
untracked files only); `AI_VERIFY_SCOPE=all` widens it. `AI_VERIFY_MODE=suggest` switches ESLint to
a non-mutating `--fix-dry-run --format json` report. `VERIFY_OUTPUT_FORMAT=json` switches
PHPStan/Psalm to machine-readable output, written under `.ai-logs/verify/`.
`VERIFY_FULL=1 AI_VERIFY_SCOPE=all` additionally runs the full-project gate: phpunit/pest, full
Vitest/Playwright, deptrac, composer-require-checker, composer-unused (advisory), trivy, semgrep,
osv-scanner, jscpd, and lychee.

A ready-to-copy example with a working config for every tool above (PHPStan, Psalm, Rector,
deptrac, ESLint, Biome, Vitest, Playwright, and more) lives in
[`examples/verify-config-example/`](examples/verify-config-example/README.md) — copy its config
files into your project, install the listed tools yourself, then run the wrappers above.

## Safety and Scope

The kit ships rules, checks, and safer defaults — not guaranteed safety — and agents work only
within the scope you give them:

- They stay read-only until scope and ownership are clear, ask one clarifying question when scope is
  missing, and never implement from memory or proceed past unclear scope.
- They ask for or build acceptance criteria (ACs); ACs must be observable and testable, and agents
  proceed only with high confidence — no guessing.
- Scope is enforced by tested scripts: `scripts/ai/pre-tool-use.sh` (policy gate: allow/ask/deny,
  blocks destructive commands) and `scripts/ai/post-tool-use.sh` (evidence writer), plus per-agent
  permission allow/ask/deny lists. See the
  [context gate](.github/instructions/context-gate.instructions.md),
  [execution protocol](docs/ai/execution-protocol.md), and [tool map](docs/ai/tools/tool-map.md).

## What Gets Added To Your Project

| What's added  | What It Provides                                                                 |
| ------------- | -------------------------------------------------------------------------------- |
| `AGENTS.md`   | Repository-wide AI agent instructions (consumed by OpenCode, Claude)             |
| `CLAUDE.md`   | Claude-specific thin adapter pointing to canonical docs                          |
| `.github/`    | GitHub Copilot adapter — instructions, agents, prompts, skills, hooks, workflows |
| `.opencode/`  | OpenCode adapter — agents, commands, skills                                      |
| `docs/ai/`    | Canonical AI workflow documentation, capabilities, and generated artifacts       |
| `scripts/ai/` | Bash helper scripts — search, verification, context packing, policy hooks        |
| `schemas/ai/` | JSON schemas for catalog and manifest validation                                 |
| `policies/`   | Governance policy instances for command-level enforcement                        |

## Supported AI Tools

- **GitHub Copilot** (VS Code, CLI, GitHub.com) — instructions, agents, prompts, skills, hooks.
- **OpenCode** (CLI) — agents, commands, skills.
- **Claude** (via `AGENTS.md` / `CLAUDE.md`) — agent instructions and a thin adapter.
- More AI tools welcome via PR — see `packages/ai-universal-rules/templates/` for the pattern.

## Who Should Use This

Teams adopting AI coding tools, maintainers who want one consistent, safe AI setup across many projects, and anyone who wants AI tools to follow shared rules instead of a blank slate.

## Documentation

- [Installation guide](readme-install.md) — full install, options, runtime selection, backup, rollback
- [How to ask agents](docs/ai/how-to-ask-agents.md) — what to specify, what to avoid, and how agents ask before acting
- [Agent routing guide](docs/ai/agents.md) — when to use which agent and common handoffs
- [Shipped agent inventory](docs/ai/AGENTS-MANIFEST.md) — exact agent names, runtime coverage, and surface differences
- [Non-technical overview](docs/ai/non-technical-overview.md) — plain-English explanation
- [Maintainer guide](docs/ai/maintainer-guide.md) — working on the kit itself
- [AI guardrails](docs/ai/AI-GUARDRAILS.md) — safety rules
- [Policies](policies/README.md) — command-level governance
- [Script registry](docs/ai/script-registry.md) — every shipped `scripts/ai/*.sh` wrapper,
  including the per-language `ai-verify-<lang>.sh` scripts, with risk/approval classification

## License

Licensed under the Apache License 2.0 — see [LICENSE](LICENSE). For AI safety rules, see
[docs/ai/AI-GUARDRAILS.md](docs/ai/AI-GUARDRAILS.md) and [policies/README.md](policies/README.md).
