# Installation Guide

This document explains **everything** in this repository — what it does, how the parts connect, and how to install it. Written for someone who has never seen this repo before.

---

## Table of Contents

1. [What This Repository Is](#what-this-repository-is)
2. [Supported AI Surfaces](#supported-ai-surfaces)
3. [Repository Layout](#repository-layout)
4. [Prerequisites](#prerequisites)
5. [Quick Start — AI Workflow Kit](#quick-start--ai-workflow-kit)
6. [Quick Start — Workstation Configs](#quick-start--workstation-configs)
7. [How The AI Installer Works (Step by Step)](#how-the-ai-installer-works-step-by-step)
8. [Installation Profiles](#installation-profiles)
9. [Install Options Reference](#install-options-reference)
10. [What Gets Installed (File Map)](#what-gets-installed-file-map)
11. [Scripts — What Each One Does](#scripts--what-each-one-does)
12. [PHP Tools — What Each One Does](#php-tools--what-each-one-does)
13. [Validation and Verification](#validation-and-verification)
14. [Repomix Context Generation](#repomix-context-generation)
15. [Regenerating Generated Files](#regenerating-generated-files)
16. [Git Hooks](#git-hooks)
17. [Style and Linting Config Files](#style-and-linting-config-files)
18. [Known Issues and Gotchas](#known-issues-and-gotchas)
19. [Repository Split Consideration](#repository-split-consideration)

---

## What This Repository Is

**This repository is designed to be installed into other projects.** It is not an application — it is a reusable AI workflow kit.

It provides a complete, AI-tool-agnostic set of templates, installers, validators, generators, and scripts that give any repository a production-ready AI-assisted development workflow. Currently supports **GitHub Copilot** (VS Code, CLI, GitHub.com) and **OpenCode** (CLI), with the architecture designed to support additional AI surfaces.

The kit handles:

- **Agent instructions** — rules and context for AI agents across multiple tools
- **Prompt and skill libraries** — reusable task prompts, capability packages, and skills
- **Policy enforcement** — tool allowlists, approval boundaries, command-risk tiers
- **Verification tooling** — validators that catch drift, stale docs, and missing files
- **Context packing** — scripts that prepare codebases for AI within token budgets
- **Bash scripts** — search, verification, evidence, and automation wrappers

---

## Supported AI Surfaces

This kit is **AI-tool-agnostic by design**. Each supported AI surface gets its own adapter layer, while shared policy, documentation, and capability logic lives in canonical locations.

| AI Surface                                    | Status                | Adapter Location                         | What Gets Installed                                     |
| --------------------------------------------- | --------------------- | ---------------------------------------- | ------------------------------------------------------- |
| **GitHub Copilot** (VS Code, CLI, GitHub.com) | Supported             | `.github/`                               | Instructions, agents, prompts, skills, hooks, workflows |
| **OpenCode** (CLI)                            | Supported             | `.opencode/`                             | Agents, commands, skills                                |
| **Claude** (via AGENTS.md/CLAUDE.md)          | Supported             | Root files                               | Agent instructions, thin adapter                        |
| _Additional surfaces_                         | Open for contribution | `packages/ai-universal-rules/templates/` | PRs welcome — follow existing template patterns         |

### How Adapters Work

Each AI surface has a thin adapter layer that points to **canonical documentation** in `docs/ai/`. This means:

- Policy changes propagate to all surfaces automatically
- Each surface only contains surface-specific syntax and configuration
- Adding a new AI surface means creating a new template set in `packages/ai-universal-rules/templates/`

---

## Repository Layout

### Tracked Source (committed to git)

These are the source files that make up the kit:

| Folder / File                  | What It Does                                                                                                                            | Installed to Target? |
| ------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------- | -------------------- |
| `packages/ai-universal-rules/` | **Source templates** — the master copies of all files that get installed. Contains `templates/`, `manifest.json`, `catalog.json`.       | No (source only)     |
| `tools/ai/`                    | **PHP installer, validators, generators** — the CLI tools that copy templates, validate config, generate catalogs, and verify installs. | No (source only)     |
| `.opencode/`                   | **OpenCode adapter** — agents, commands, and skills for the OpenCode CLI tool. Gets installed via the `opencode` profile.               | Yes                  |
| `.schemas/`                    | **JSON schemas** — validation contracts for catalog, manifest, command policy, and other structured metadata.                           | Yes                  |
| `policies/`                    | **Governance policies** — allow/deny/confirm command rules consumed by script-level policy enforcement.                                 | Yes                  |
| `tests/`                       | **Test suite** — PHPUnit tests for PHP tools, Bash tests for shell scripts.                                                             | No                   |
| `AGENTS.md`                    | **Agent instructions** — repository-wide rules for AI agents (OpenCode, Claude). Gets installed to target repos.                        | Yes                  |
| `CLAUDE.md`                    | **Claude-specific** — thin adapter pointing to canonical docs.                                                                          | Yes                  |

### Generated at Runtime (not committed, created by installer or scripts)

These folders appear on disk after running the installer (`self-install`) or generation scripts:

| Folder        | What It Does                                                                                                                              |
| ------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| `.github/`    | **GitHub Copilot adapter** — instructions, agents, prompts, skills, hooks, and workflows. Created by installer via the `copilot` profile. |
| `docs/ai/`    | **Canonical documentation** — workflow guides, architecture docs, script registry, capability descriptions. Created by installer.         |
| `scripts/ai/` | **Bash helper scripts** — search wrappers, verification scripts, context-packing tools. Created by installer via `scripts-pack`.          |
| `.ai-logs/`   | **Local AI evidence logs** — session logs and evidence artifacts. Gitignored.                                                             |
| `vendor/`     | **Composer dependencies** — created by `composer install`.                                                                                |

---

## Prerequisites

### Required Tools

| Tool         | Minimum Version | How to Check         | How to Install                                    |
| ------------ | --------------- | -------------------- | ------------------------------------------------- |
| PHP          | **8.2+**        | `php -v`             | Pre-installed via Herd, or `brew install php`     |
| Composer     | 2.x             | `composer --version` | `brew install composer`                           |
| Bash         | **4.0+**        | `bash --version`     | macOS ships 3.2; install 5.x: `brew install bash` |
| Git          | 2.x             | `git --version`      | `brew install git`                                |
| jq           | 1.6+            | `jq --version`       | `brew install jq`                                 |
| rg (ripgrep) | latest          | `rg --version`       | `brew install ripgrep`                            |

### Required for Context Packing (optional feature)

| Tool    | How to Install           |
| ------- | ------------------------ |
| repomix | `npm install -g repomix` |
| scc     | `brew install scc`       |
| fd      | `brew install fd`        |

### Install All CLI Tools At Once

```bash
bash scripts/ai/install-mandatory-tools.sh
```

> **Warning**: This script installs packages via Homebrew/apt/winget. Review it before running. Use `--dry-run` first:
>
> ```bash
> bash scripts/ai/install-mandatory-tools.sh --dry-run
> ```

### macOS Bash Version Note

macOS ships with Bash 3.2 at `/bin/bash`. Many scripts in this repo require Bash 4+. After `brew install bash`, the new version lives at `/opt/homebrew/bin/bash`. To make it the default in new terminals:

```bash
# Add to ~/.zprofile:
eval "$(/opt/homebrew/bin/brew shellenv)"
```

---

## Quick Start — AI Workflow Kit

### One-Command Install Into Any Project

The installer script [`install-ai-kit.sh`](install-ai-kit.sh) is at the root of this repository. Run it from a local clone of this repo pointing at any target project. It checks required tools, validates the source install surface, backs up target files, installs `full-governance` for Copilot + OpenCode, validates the result, and attempts optional context/inventory steps when their tools are available.

If `php tools/ai/validate-install-surface.php --strict` fails in this source checkout, stop and fix the reported missing pack sources before installing into another project. The root installer now runs that validation before writing target files so a broken source checkout cannot produce a partial external install.

**Workstation tools (run once from this repo, not per project):**

```bash
bash scripts/ai/install-mandatory-tools.sh   # rg, fd, jq, scc, watchexec, etc.
npm install -g repomix                        # context packing
```

**Install into a project:**

```bash
bash install-ai-kit.sh /path/to/your-project
# or with an explicit project name:
bash install-ai-kit.sh /path/to/your-project "your-project-name"
```

---

### Step-by-Step Install (manual, with preview)

If you prefer to control each step from a local clone of this repo:

```bash
# 1. Check prerequisites (PHP 8.2+, git, jq, rg must be present)
php tools/ai/ai.php preflight

# 2. Preview what would be installed — NO files are written
php tools/ai/install-ai-kit.php \
  --target /path/to/your-project \
  --profile full-governance \
  --runtime both \
  --dry-run

# 3. Apply — backs up existing files, writes all new files, validates after
php tools/ai/install-ai-kit.php \
  --target /path/to/your-project \
  --profile full-governance \
  --runtime both \
  --project-name "your-project-name" \
  --backup \
  --verify-after \
  --non-interactive

# OpenCode-only full install — excludes GitHub Copilot adapter files
php tools/ai/install-ai-kit.php \
  --target /path/to/your-project \
  --profile full-governance \
  --runtime opencode \
  --without optional-agents-copilot-pack \
  --project-name "your-project-name" \
  --backup \
  --verify-after \
  --non-interactive

# 4. Audit remaining placeholders
php tools/ai/ai.php placeholders --fail

# 5. Open the post-install guide for required follow-up steps
cat /path/to/your-project/docs/ai/POST-INSTALL.md
```

### Required Flags Reference

| Flag                   | Required?                            | Default           | What It Does                                                     |
| ---------------------- | ------------------------------------ | ----------------- | ---------------------------------------------------------------- |
| `--target <path>`      | **Yes** (for external install)       | `.` (current dir) | Target project root                                              |
| `--profile <name>`     | Yes                                  | `dual`            | Which packs to install. Use `full-governance` for complete setup |
| `--runtime <name>`     | Yes                                  | `both`            | `github-copilot`, `opencode`, or `both`                          |
| `--without <packs>`    | Optional                             | off               | Remove packs from the profile; use `optional-agents-copilot-pack` when doing an OpenCode-only full install |
| `--project-name <n>`   | Recommended                          | inferred from dir | Sets `<PROJECT_NAME>` placeholder in installed files             |
| `--backup`             | **Yes** if target has existing files | off               | Archives managed files before overwriting                        |
| `--non-interactive`    | Yes in CI                            | off               | Disables interactive prompts                                     |
| `--dry-run`            | —                                    | off               | Preview only — no files written                                  |
| `--output-json <file>` | Optional                             | off               | Write install summary + placeholder list to JSON file            |
| `--force`              | Only when recovering                 | off               | Overwrite even non-managed files                                 |
| `--verify-after`       | Optional                             | off               | Run validators automatically after install                       |

### Profiles Quick Reference

| Profile           | What It Installs                                      | Best For                       |
| ----------------- | ----------------------------------------------------- | ------------------------------ |
| `minimal`         | Base docs only                                        | Evaluation only                |
| `copilot`         | Base + Copilot adapter                                | GitHub Copilot only            |
| `opencode`        | Base + OpenCode adapter                               | OpenCode CLI only              |
| `dual`            | Base + both adapters                                  | Teams using both tools         |
| `full-governance` | Everything — agents, scripts, hooks, CI, capabilities, advisor tooling, target-local PHP validators | **Recommended for production** |
| `accelerated`     | Full except evidence/evaluation packs                 | Fast start                     |

### Post-Install Required Steps (summary)

After any install, these three steps are **required before running write-capable AI agents**:

1. Edit `docs/ai/project-context.md` — replace every `<PLACEHOLDER>` with your actual project values
2. Edit `.github/instructions/frontend.instructions.md` — replace `<FRONTEND_PATH_GLOB>`
3. Edit `.github/instructions/testing.instructions.md` — replace `<TEST_PATH_GLOB>`

Full checklist with every placeholder explained: `docs/ai/POST-INSTALL.md` (installed in your project root).

To audit remaining placeholders at any time:

```bash
php tools/ai/ai.php placeholders --fail
```

---

### Install into THIS repository (self-install / refresh)

```bash
php tools/ai/ai.php install --profile full-governance --reinstall --dry-run
php tools/ai/ai.php install --profile full-governance --reinstall --apply
```

---

### Quick Start — Workstation Configs

Workstation configs are manual copy-and-merge. There is no automated installer.

1. Read the relevant setup doc in `docs/` (e.g., `docs/nvim-setup.md`)
2. Copy or merge the config from `configs/` into your local environment
3. Replace machine-specific placeholders
4. Run `just doctor` to verify your local toolchain

---

## How The AI Installer Works (Step by Step)

Here is exactly what happens when you run `php tools/ai/ai.php install`:

### 1. Entry Point

```
tools/ai/ai.php  ← CLI dispatcher (routes subcommands)
   └── tools/ai/commands/install_workflow.php  ← install/upgrade/rollback logic
       └── tools/ai/install/core.php  ← core installer engine
```

`ai.php` is the main CLI. It parses the command (`install`, `verify`, `preflight`, etc.) and delegates to the right command file.

### 2. Profile Resolution

The installer reads the `--profile` argument and expands it into a list of **packs** (groups of files). For example:

- `full-governance` = `base` + `adapter-copilot` + `adapter-opencode` + `scripts-pack` + `hooks-pack` + `capabilities-governance` + `ci-pack` + `advisor-pack` + `target-tools-pack` + more

Profiles and packs are defined in `tools/ai/install/profiles.php` and `tools/ai/install/packs.php`.

### 3. Template Resolution

For each file in the expanded pack list, the installer:

1. Looks up the **source template** in `packages/ai-universal-rules/templates/`
2. Reads the template content
3. Replaces `<PLACEHOLDER_NAME>` tokens with target-repo values (project name, paths, etc.)
4. Determines the **target path** in the destination repo

### 4. Conflict Check

Before writing, the installer checks:

- Does the target file already exist?
- Is it identical to what we'd write? → Skip
- Is it different? → Depends on flags:
  - `--reinstall` → Overwrite managed files
  - `--force` → Overwrite everything
  - `--upgrade-suffix=-upgrade` → Write as `filename-upgrade` for manual merge
  - Default → Skip and warn

### 5. Write Phase

If `--apply` is specified, files are written. If `--dry-run` (default), nothing is written — you just see the plan.

### 6. Post-Install

After writing, the installer updates:

- `.ai-install-manifest.json` — machine-readable record of what was installed
- `docs/ai/generated/install.json` and `install.md` — human-readable install report

### Flow Diagram

```
User runs: php tools/ai/ai.php install --profile full-governance --apply
    │
    ├── ai.php parses args → routes to install command
    ├── install_workflow.php resolves profile → pack list
    ├── install/packs.php expands packs → file list
    ├── install/core.php processes each file:
    │   ├── Read template from packages/ai-universal-rules/templates/
    │   ├── Replace placeholders
    │   ├── Check for conflicts in target repo
    │   └── Write to target path (or skip/warn)
    ├── Write .ai-install-manifest.json
    └── Write docs/ai/generated/install.json + install.md
```

---

## Installation Profiles

| Profile           | What It Installs                                                   | When to Use                    |
| ----------------- | ------------------------------------------------------------------ | ------------------------------ |
| `minimal`         | Base policy + project context + guardrails + 3 core capabilities   | Smallest useful setup          |
| `copilot`         | `minimal` + GitHub Copilot adapter (instructions, agents, prompts) | VS Code / GitHub Copilot only  |
| `opencode`        | `minimal` + OpenCode adapter (commands, skills)                    | OpenCode CLI only              |
| `dual`            | `minimal` + both Copilot and OpenCode adapters                     | Using both AI tools            |
| `guarded`         | `dual` + hook policy + guard reminders                             | Safety-conscious setup         |
| `accelerated`     | `dual` + scripts + policy + evidence packs                         | Power-user setup               |
| `full-governance` | Everything: `accelerated` + all capabilities + hooks + CI + advisor tooling + target-local PHP validators | **Recommended** — full setup   |
| `scripts-only`    | Just the bash scripts from `scripts/ai/`                           | Bash scripts without AI config |
| `custom`          | Empty base — opt into packs with `--with`                          | Cherry-pick specific packs     |

### Optional Packs (use with `--with`)

```bash
php tools/ai/install-ai-kit.php --target /path --profile copilot --with scripts-pack,advisor-pack
```

Available packs: `scripts-pack`, `advisor-pack`, `target-tools-pack`, `docs-reference-pack`, `delivery-pack`, `preview-environments-pack`, `evaluation-pack`, `service-boundary-pack`, `mcp-boundaries-pack`

---

## Install Options Reference

| Option                      | What It Does                                           |
| --------------------------- | ------------------------------------------------------ |
| `--target <path>`           | Directory to install into (default: current directory) |
| `--profile <name>`          | Which installation profile to use                      |
| `--reinstall`               | Refresh all managed files (overwrites them)            |
| `--dry-run`                 | Show what would happen without writing files (default) |
| `--apply`                   | Actually write the files                               |
| `--force`                   | Overwrite even files with local modifications          |
| `--with <packs>`            | Add optional packs (comma-separated)                   |
| `--without <packs>`         | Remove packs from the profile                          |
| `--all-features`            | Enable every registered optional pack                  |
| `--upgrade-suffix <suffix>` | Write collisions as `file-upgrade` instead of skipping |
| `--verify-after`            | Run validation automatically after install             |
| `--backup-only`             | Create backup without installing                       |
| `--allow-placeholders`      | Don't fail on unresolved `<PLACEHOLDER>` tokens        |
| `--toolchain-check`         | Show required tool state before install                |
| `--run-after-install <id>`  | Run a post-install script (e.g., `repomix-tree`)       |

---

## What Gets Installed (File Map)

When you install `full-governance` into a target repo, these files are created:

### Root-Level Files

| Installed File          | Source Template                                        | Purpose                                  |
| ----------------------- | ------------------------------------------------------ | ---------------------------------------- |
| `AGENTS.md`             | `templates/core/AGENTS.template.md`                    | AI agent instructions (OpenCode, Claude) |
| `CLAUDE.md`             | (generated)                                            | Claude-specific thin adapter             |
| `.vscode/settings.json` | `templates/core/copilot-vscode-settings.template.json` | VS Code sandbox + auto-approve rules     |

### `.github/` — Copilot Adapter

| Installed File                           | Purpose                                        |
| ---------------------------------------- | ---------------------------------------------- |
| `.github/copilot-instructions.md`        | Repository-wide Copilot instructions           |
| `.github/instructions/*.instructions.md` | Path-specific Copilot rules (22 files)         |
| `.github/agents/*.agent.md`              | Agent mode definitions (10 agents)             |
| `.github/prompts/*.prompt.md`            | One-shot task prompts (16 prompts)             |
| `.github/skills/*/SKILL.md`              | Runtime-loaded capability adapters (16 skills) |
| `.github/hooks/tool-policy.json`         | Tool execution policy gate                     |

### `docs/ai/` — Canonical Documentation

| Installed File                 | Purpose                                  |
| ------------------------------ | ---------------------------------------- |
| `docs/ai/project-context.md`   | Durable project context for all AI tools |
| `docs/ai/workflow.md`          | Default task workflow                    |
| `docs/ai/AI-GUARDRAILS.md`     | Safety rules                             |
| `docs/ai/capabilities/*/`      | Reusable procedure packages              |
| `docs/ai/script-registry.md`   | Approved script allowlist                |
| `docs/ai/script-registry.json` | Machine-readable script registry         |

### `scripts/ai/` — Bash Scripts

Installed via `scripts-pack`. See the [Scripts](#scripts--what-each-one-does) section below.

---

## Scripts — What Each One Does

### Search and Discovery (read-only, safe to run anytime)

| Script            | What It Does                                                                   | Example                                                     |
| ----------------- | ------------------------------------------------------------------------------ | ----------------------------------------------------------- |
| `ai-search.sh`    | **Main search entry point** — routes to rg, fd, git, or ast-grep based on mode | `bash scripts/ai/ai-search.sh tracked "function" .`         |
| `rg-code.sh`      | Ripgrep wrapper with mode presets (php, js, config, json)                      | `bash scripts/ai/rg-code.sh "pattern" . --mode php`         |
| `fd-files.sh`     | File discovery wrapper using fd                                                | `bash scripts/ai/fd-files.sh "*.php" .`                     |
| `preview-file.sh` | Safe file preview with line-range support                                      | `bash scripts/ai/preview-file.sh path/file.php --around 50` |
| `query-usage.sh`  | Find all usages of a symbol across the repo                                    | `bash scripts/ai/query-usage.sh "myFunction"`               |
| `repo-stats.sh`   | Per-file and per-directory line/size metrics                                   | `bash scripts/ai/repo-stats.sh . --json`                    |

### Git and History (read-only)

| Script             | What It Does                                  | Example                                           |
| ------------------ | --------------------------------------------- | ------------------------------------------------- |
| `git-forensics.sh` | Trace git history for a file or symbol        | `bash scripts/ai/git-forensics.sh "path/to/file"` |
| `gh-pr-context.sh` | Pull request metadata, checks, reviews, diffs | `bash scripts/ai/gh-pr-context.sh`                |

### Verification and Validation (read-only, **run after every change**)

| Script                   | What It Does                                                         | Example                                          |
| ------------------------ | -------------------------------------------------------------------- | ------------------------------------------------ |
| `ai-verify.sh`           | **Main verification entry point** — runs the full verification stack | `bash scripts/ai/ai-verify.sh .`                 |
| `ai-doc-check.sh`        | Check AI doc consistency and cross-references                        | `bash scripts/ai/ai-doc-check.sh --check`        |
| `ai-file-freshness.sh`   | Find AI files not modified in N days                                 | `bash scripts/ai/ai-file-freshness.sh --days 90` |
| `ai-install-coverage.sh` | Check which expected AI files exist vs missing                       | `bash scripts/ai/ai-install-coverage.sh`         |
| `check-file-refs.sh`     | Find files not referenced anywhere (orphans)                         | `bash scripts/ai/check-file-refs.sh`             |
| `ai-test-select.sh`      | Select and run relevant tests for changed files                      | `bash scripts/ai/ai-test-select.sh`              |

### Context Packing (read-only, generates bundles for AI consumption)

| Script                    | What It Does                                | Example                                                   |
| ------------------------- | ------------------------------------------- | --------------------------------------------------------- |
| `repomix-context-tree.sh` | Generate tree-structured context bundles    | `bash scripts/ai/repomix-context-tree.sh all .`           |
| `run-repomix-context.sh`  | Guided context build with dependency checks | `bash scripts/ai/run-repomix-context.sh /path/to/project` |
| `repomix-scc-router.sh`   | SCC-complexity-ranked context bundles       | `bash scripts/ai/repomix-scc-router.sh all .`             |
| `pack-context.sh`         | Pack AI context for a specific scope        | `bash scripts/ai/pack-context.sh`                         |
| `ai-diff-context.sh`      | Narrow context for changed/staged/PR files  | `bash scripts/ai/ai-diff-context.sh`                      |

### Mutation Scripts (require approval, **use with caution**)

| Script                       | What It Does                                             | Example                                                 |
| ---------------------------- | -------------------------------------------------------- | ------------------------------------------------------- |
| `ai-edit.sh`                 | Scoped repository edits via ast-grep or text replacement | `bash scripts/ai/ai-edit.sh ast-grep php "old" "new" .` |
| `ai-rollback.sh`             | Create/restore rollback snapshots                        | `bash scripts/ai/ai-rollback.sh`                        |
| `install-mandatory-tools.sh` | Install CLI tools via brew/apt/winget                    | `bash scripts/ai/install-mandatory-tools.sh --dry-run`  |

### Policy Hooks (called automatically by AI tool integrations)

| Script             | What It Does                                                  |
| ------------------ | ------------------------------------------------------------- |
| `pre-tool-use.sh`  | Policy gate — checks if a command is allowed before execution |
| `post-tool-use.sh` | Evidence writer — logs what was executed after completion     |

### Shared Library and Other

| Script                  | What It Does                                                                              |
| ----------------------- | ----------------------------------------------------------------------------------------- |
| `common.sh`             | Shared helper functions used by other scripts. **Requires Bash 4+.** Not called directly. |
| `session-checkpoint.sh` | Save/restore session state for long-running AI conversations                              |
| `watch-loop.sh`         | Watched command retry wrapper for iterative workflows                                     |
| `ai-task.sh`            | Task tracking wrapper                                                                     |
| `ai-structured.sh`      | Structured output helper                                                                  |

---

## PHP Tools — What Each One Does

### Main CLI Dispatcher

| File              | What It Does                                  | Example                         |
| ----------------- | --------------------------------------------- | ------------------------------- |
| `tools/ai/ai.php` | **Main entry point** — routes all subcommands | `php tools/ai/ai.php <command>` |

Available subcommands: `install`, `upgrade`, `rollback`, `verify`, `preflight`, `env-check`, `packs`, `list`, `freshness`, `placeholders`, `install-docs`, `next`, `ask`, `estimate`, `impact`, `budget`

### Installers

| File                               | What It Does                                                   |
| ---------------------------------- | -------------------------------------------------------------- |
| `tools/ai/install-ai-kit.php`      | Standalone installer (can be called directly without `ai.php`) |
| `tools/ai/install-ai-kit.sh`       | Thin shell wrapper around `install-ai-kit.php`                 |
| `tools/ai/install-copilot-kit.sh`  | Legacy wrapper — Copilot-only install                          |
| `tools/ai/install-opencode-kit.sh` | Legacy wrapper — OpenCode-only install                         |

### Validators (read-only, generate no files)

| File                               | What It Does                                    | Example                                         |
| ---------------------------------- | ----------------------------------------------- | ----------------------------------------------- |
| `validate-ai-config.php`           | Checks root workflow files and references       | `php tools/ai/validate-ai-config.php`           |
| `validate-ai-catalog.php`          | Checks package/catalog metadata integrity       | `php tools/ai/validate-ai-catalog.php`          |
| `validate-install-surface.php`     | Checks installed files match expectations       | `php tools/ai/validate-install-surface.php`     |
| `validate-adapter-drift.php`       | Checks adapters are in sync with canonical docs | `php tools/ai/validate-adapter-drift.php`       |
| `validate-generated-artifacts.php` | Checks generated files are current              | `php tools/ai/validate-generated-artifacts.php` |
| `validate-placeholders.php`        | Checks for unresolved `<PLACEHOLDER>` tokens    | `php tools/ai/validate-placeholders.php`        |
| `validate-command-policy.php`      | Checks command risk tiers are consistent        | `php tools/ai/validate-command-policy.php`      |
| `verify-full-install.php`          | Runs all validators in sequence                 | `php tools/ai/verify-full-install.php`          |

### Generators (write files)

| File                             | What It Does                                               | Example                                               |
| -------------------------------- | ---------------------------------------------------------- | ----------------------------------------------------- |
| `generate-ai-catalog.php`        | Generates `docs/ai/catalog.md`, `catalog.json`, `llms.txt` | `php tools/ai/generate-ai-catalog.php`                |
| `generate-repo-structure.php`    | Generates repo structure JSON/CSV/MD                       | `php tools/ai/generate-repo-structure.php --with-scc` |
| `generate-ai-file-standards.php` | Generates AI file standards doc                            | `php tools/ai/generate-ai-file-standards.php`         |
| `repo-tool-inventory.php`        | Generates required tools doc from scripts                  | `php tools/ai/repo-tool-inventory.php`                |
| `build-context-pack.php`         | Builds context pack bundles                                | `php tools/ai/build-context-pack.php`                 |
| `export-ai-universal-rules.php`  | Exports package for distribution                           | `php tools/ai/export-ai-universal-rules.php`          |

### Other Tools

| File                           | What It Does                                    |
| ------------------------------ | ----------------------------------------------- |
| `secret-scan.php`              | Scans for accidentally committed secrets        |
| `suggest-verification.php`     | Suggests which verification to run for a change |
| `maintenance-mode.php`         | Toggle maintenance mode for AI workflows        |
| `render-agent-permissions.php` | Renders agent tool permissions                  |

---

## Validation and Verification

### After Any Code Change (minimum)

```bash
# Quick validation (recommended after every change)
php tools/ai/validate-ai-config.php
php tools/ai/validate-ai-catalog.php
php tools/ai/generate-ai-catalog.php --check
```

### After Installing to a Target Repo

```bash
php tools/ai/verify-full-install.php
```

### Full Verification Suite (bash wrapper)

```bash
bash scripts/ai/ai-verify.sh .
```

### After Changing AI Docs

```bash
bash scripts/ai/ai-doc-check.sh --check
```

### Verification Ladder (escalation order)

1. **Syntax check** — `php -l file.php` or `shellcheck script.sh`
2. **Focused validator** — e.g., `validate-ai-config.php` for config changes
3. **Catalog freshness** — `php tools/ai/generate-ai-catalog.php --check`
4. **Full verification** — `php tools/ai/verify-full-install.php`
5. **PHPUnit tests** — `./vendor/bin/phpunit` (requires `composer install`)
6. **Bash tests** — `bash tests/scripts/ai/test-*.sh` (one per script)

---

## Repomix Context Generation

Repomix creates AI-ready context bundles from repository source code. This is useful for feeding large codebases to AI tools that need to understand your project.

### Generate context for any project

```bash
SECRETS_SCAN=0 bash scripts/ai/run-repomix-context.sh /path/to/project \
  --top 0 --min-code 0 --min-files 0 --depth 3
```

> **Note**: The target directory must be a git repository. If you see `Error: no files available after applying ignore rules`, the directory is not a git repo — see [Non-git directory workaround](#error-no-files-available-after-applying-ignore-rules) below.

### Error: no files available after applying ignore rules

This error occurs when you run repomix against a directory that has no git repository:

```
Error: no files available after applying ignore rules
[ERROR] context tree generation failed
```

`run-repomix-context.sh` and `repomix-context-tree.sh` both require git — repomix uses the git file list to determine which files to include. A non-git directory has no tracked files, so repomix finds nothing.

**Workaround A — Initialize git first (recommended):**

```bash
cd /path/to/directory
git init
git add -A
git commit -m "init for repomix"
SECRETS_SCAN=0 bash /path/to/awesome-ai-utmostcreator/scripts/ai/run-repomix-context.sh . \
  --depth 3 --top 0 --min-code 0 --min-files 0
```

**Workaround B — Run repomix directly from inside the directory (no git required):**

```bash
cd /path/to/directory
repomix \
  --include "**/*" \
  --no-gitignore \
  --no-git-sort-by-changes \
  --output /tmp/context-output.xml
```

> **Important**: `cd` into the directory first. Passing a path as a positional argument does not work reliably in non-git directories — repomix must be run from the target directory itself.

For Obsidian vaults (markdown only):

```bash
cd /Users/you/obsidian/vault-name
repomix --include "**/*.md" --no-gitignore --no-git-sort-by-changes --output /tmp/vault-context.xml
```

Adjust `--include` as needed (e.g., `"**/*.{js,ts,php}"` for code-only repos).

### Generate context for this repo

```bash
bash scripts/ai/repomix-context-tree.sh all .
```

### Using `just` shortcuts

```bash
just context-pack-all          # Full tree-context build
just context-analyze            # Inspect how repo splits under budget
just context-tree-run           # Guided build with dependency checks
```

### Parameter reference

| Parameter             | Default | Recommended | Why                                                                   |
| --------------------- | ------- | ----------- | --------------------------------------------------------------------- |
| `--depth`             | 1       | 3           | Captures nested folders; use 4–5 for very large projects              |
| `--top`               | 25      | 0           | `0` means all routes — ensures nothing is skipped                     |
| `--min-code`          | 300     | 0           | `0` captures small files/configs that matter                          |
| `--min-files`         | 2       | 0           | `0` captures single-file routes                                       |
| `--min-score`         | 0       | 0           | No score filtering                                                    |
| `--min-complexity`    | 0       | 0           | No complexity filtering                                               |
| `--max-bundle-tokens` | 100000  | 100000      | Max tokens per bundle; oversized routes are force-split               |
| `--compress`          | off     | on          | Reduces token usage (added automatically by `run-repomix-context.sh`) |
| `--style`             | xml     | xml         | XML style is default and most compatible                              |
| `--context-window`    | 128000  | 128000      | Match your model's context window                                     |
| `SECRETS_SCAN`        | 1       | 0           | Disable if gitleaks is not installed or project is local-only         |
| `MAX_BUNDLE_TOKENS`   | 100000  | 100000      | Env var override for `--max-bundle-tokens`                            |

### Output structure

```
.repomix-context/tree-context/
├── index.md              ← Human-readable route index (open first)
├── tree-plan.json        ← Machine-readable route plan
├── tree-manifest.json    ← Full generation manifest
├── bundles/              ← Packed context files per route
└── indexes/              ← Split-route child indexes
```

### Other context scripts

| Script                    | Purpose                                                 |
| ------------------------- | ------------------------------------------------------- |
| `repomix-context-tree.sh` | Lower-level: analyze, plan, pack, or clean context tree |
| `repomix-scc-router.sh`   | Size-aware routing using scc metrics                    |
| `pack-context.sh`         | Quick focused context for a single area                 |
| `ai-diff-context.sh`      | Context from current git diff only                      |

---

## Regenerating Generated Files

When you change templates, config, or cataloged assets, regenerate in this order:

| #   | Command                                               | What It Updates                                  |
| --- | ----------------------------------------------------- | ------------------------------------------------ |
| 1   | `php tools/ai/generate-ai-catalog.php`                | `docs/ai/catalog.md`, `catalog.json`, `llms.txt` |
| 2   | `php tools/ai/generate-repo-structure.php --with-scc` | `docs/ai/generated/repo-structure.*`             |
| 3   | `php tools/ai/ai.php install-docs --target . --write` | `docs/ai/generated/install-*.*`                  |
| 4   | `bash scripts/ai/repomix-context-tree.sh all .`       | `.repomix-context/tree-context/*`                |
| 5   | `bash scripts/ai/repo-tool-inventory.sh`              | `docs/ai/repo-required-tools.md`                 |

If `--check` shows drift:

```bash
php tools/ai/generate-ai-catalog.php
php tools/ai/generate-ai-catalog.php --check
```

---

## Git Hooks

This repo includes git hooks via **two systems** (only one is needed):

| System       | Config File     | How It Runs                                                                                   |
| ------------ | --------------- | --------------------------------------------------------------------------------------------- |
| **Lefthook** | `.lefthook.yml` | `lefthook install` (recommended)                                                              |
| **Husky**    | `.husky/`       | Requires `package.json` + `npm install` (currently non-functional — no `package.json` exists) |

Both call the same underlying scripts:

- `scripts/hooks/pre-commit.sh` — checks for merge conflict markers, runs `php -l` on staged PHP files
- `scripts/hooks/commit-msg.sh` — validates commit message format

**Recommendation**: Use Lefthook (`brew install lefthook && lefthook install`).

---

## Style and Linting Config Files

These files exist at the repo root. Some are active, some are **reference configs for target projects**:

| File                      | What It Configures             | Status in This Repo                          |
| ------------------------- | ------------------------------ | -------------------------------------------- |
| `.editorconfig`           | Editor whitespace, indent, EOL | **Active** — works standalone                |
| `.markdownlint-cli2.yaml` | Markdown lint rules            | **Active** — used by markdownlint            |
| `.shellcheckrc`           | Shell lint rules               | **Active** — used by shellcheck              |
| `.gitleaks.toml`          | Secret scanning rules          | **Active** — used by gitleaks                |
| `.eslintrc.json`          | ESLint for Vue 3 + TypeScript  | **Reference only** — no JS/TS source in repo |
| `.prettierrc.json`        | Prettier formatting rules      | **Reference only** — no JS/TS source in repo |
| `.stylelintrc.json`       | Stylelint for Tailwind/Vue     | **Reference only** — no CSS source in repo   |
| `configs/php/pint.json`   | Laravel Pint PHP formatter     | **Reference** — template for PHP projects    |

---

## Running Tests

### PHPUnit (PHP tools)

```bash
composer install
./vendor/bin/phpunit
```

### Bash Script Tests

All scripts have tests in `tests/scripts/ai/`. Requires Bash 4+.

```bash
# Run all test suites
SUITE_TIMEOUT=60 bash tests/scripts/ai/run-all-tests.sh

# Run a single suite
bash tests/scripts/ai/test-common.sh
```

---

## Maintenance Mode

Temporarily permit full install/verify workflows:

```bash
php tools/ai/maintenance-mode.php enable --reason "full-governance reinstall" --ttl-seconds 1800
# run installation commands
php tools/ai/maintenance-mode.php disable
```

---

## Known Issues and Gotchas

| Issue                                                                                      | Status            | Workaround                                                                                                                                                                         |
| ------------------------------------------------------------------------------------------ | ----------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| macOS Bash is 3.2; scripts need 4+                                                         | Known             | `brew install bash` + add to PATH via `~/.zprofile`                                                                                                                                |
| `.husky/` exists but no `package.json`                                                     | Orphaned          | Use Lefthook instead                                                                                                                                                               |
| `.eslintrc.json`, `.prettierrc.json`, `.stylelintrc.json` reference frameworks not present | Reference configs | Not a bug — they serve as starter configs for target projects                                                                                                                      |
| `docs/ai/project-context.md` has `unknown` values                                          | Intentional       | Template defaults — filled in per-project during install                                                                                                                           |
| VS Code sandbox `deniedDomains` warning                                                    | Fixed             | Disappears after VS Code restart                                                                                                                                                   |
| `~/.gitignore_global` may block `git add`                                                  | Per-user          | Use `git add -f <file>` for files in `.vscode/`, `scripts/`, `.github/`, `docs/`                                                                                                   |
| Windows Git not in PATH                                                                    | Known             | `$env:Path = "C:\Program Files\Git\cmd;$env:Path"`                                                                                                                                 |
| `repomix` not found                                                                        | Missing tool      | `npm i -g repomix`                                                                                                                                                                 |
| `Error: no files available after applying ignore rules` when running repomix               | Not a git repo    | `git init && git add -A && git commit -m "init"` in the target dir, or `cd /path/to/dir && repomix --include "**/*" --no-gitignore --no-git-sort-by-changes --output /tmp/out.xml` |

---

## Repository Split Consideration

This repo serves two audiences. A future split could look like:

| Repo                                      | Contents                                                                                                                           | Audience                                 |
| ----------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------- |
| **workstation-configs** (or dotfiles)     | `configs/`, `docs/*.md` (non-AI), `reference/php/`, `scripts/doctor.sh`, `scripts/hooks/`                                          | Developer setting up their Mac           |
| **ai-workflow-kit** (or keep app-configs) | `packages/`, `tools/ai/`, `scripts/ai/`, `docs/ai/`, `.github/`, `AGENTS.md`, `CLAUDE.md`, `.opencode/`, `tests/`, `composer.json` | Developer adding AI workflow to any repo |

**Why split?** The workstation configs add confusion for users who only want the AI toolkit, and vice versa. The `README.md` already acknowledges the boundary.

**Why not split yet?** The workstation configs serve as live dogfood for testing the AI toolkit in a real repo context.
