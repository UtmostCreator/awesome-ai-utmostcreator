# Maintainer Guide

Developer and maintainer reference for working on the kit itself (not for installing it).
This document is **source-repo-only**: it is not listed in `docs/ai/installed-files.md`
and never ships to installed targets.

If you only want to install the kit into a project, use the [installation guide](../../readme-install.md)
instead.

## Repository States (Read This First)

Every doc that touches install-time surfaces must state which of these states it
describes. This repository is in the **self-installed source kit** state:

| State | What it means | `.github/`, `docs/`, `scripts/` |
| --- | --- | --- |
| Source kit | The kit's template sources and tooling, before self-install | would not exist yet |
| Self-installed source kit (**this repo**) | The kit installed into itself; rendered outputs are **tracked** and refreshed by render | tracked in git, regenerated from `packages/ai-universal-rules/templates/**` |
| Installed target | An external project the kit was installed into | created by the installer, owned by that project |

## Start Here / Edit Here / Generated Here

| Layer | Paths | Rule |
| --- | --- | --- |
| **Start here** (control entrypoints) | `docs/ai/maintainer-guide.md` (maintainers), `docs/ai/validation.md` (change-type routing), `docs/ai/integration-matrix.md` (coverage + surface classification) | route every kit change through the change-type table in `validation.md` |
| **Edit here** (source layer) | `packages/ai-universal-rules/templates/**`, `docs/ai/**` files without a GENERATED header, `tools/ai/**`, `schemas/ai/**` | the only hand-edit layer for shipped behavior |
| **Generated here** (render outputs) | root `AGENTS.md`, root `CLAUDE.md` (kit-managed as of the Claude adapter parity plan, `docs/tickets/arch-todo-claude-code-adapter-parity-20260704-120000` — see `docs/ai/adapter-contract.md`), `.github/**` adapter files, `.opencode/**`, `.claude/**`, files with a `GENERATED — DO NOT EDIT` header, `docs/ai/generated/**` | never hand-edit; re-render from the source layer and gate with validators |

## Repository Layout

### Tracked Folders

| Folder        | Purpose                                                        | Audience                                |
| ------------- | -------------------------------------------------------------- | --------------------------------------- |
| `.` (root)    | Repository root files and project-level entrypoints            | Humans, CI, package managers, AI agents |
| `.opencode/`  | OpenCode runtime adapter — agents, commands, skills            | OpenCode CLI users                      |
| `schemas/ai/` | JSON schemas for catalog and manifest validation               | Validation tooling, maintainers         |
| `packages/`   | **Source templates** — master copies of all installable files  | Teams installing into external repos    |
| `policies/`   | Governance policy instances (allow/deny/confirm command rules) | Maintainers                             |
| `tests/`      | PHPUnit + Bash test suites and fixtures                        | Maintainers, CI                         |
| `tools/`      | **PHP installer, validators, generators**, AI tooling CLI      | Maintainers, AI agents                  |

### Installer-Produced Folders (tracked here, installer output in targets)

Because this repository is a **self-installed source kit**, these folders are tracked
in git here. In installed targets the installer creates them; here they are refreshed
by render/self-install and must never be hand-edited where a GENERATED header applies:

| Folder     | Purpose                                                                | State in this repo | State in installed targets |
| ---------- | ---------------------------------------------------------------------- | ------------------ | -------------------------- |
| `.github/` | GitHub Copilot adapter (instructions, agents, prompts, skills, hooks)  | tracked, rendered  | created by installer       |
| `docs/`    | Canonical AI workflow docs (`docs/ai/generated/**` stays gitignored)   | tracked            | created by installer       |
| `scripts/` | Bash helper scripts (search, verify, context, hooks)                   | tracked            | created by installer       |

### Untracked Folders (runtime or dependency output)

| Folder              | Purpose                                       | How It Appears                     |
| ------------------- | ---------------------------------------------- | ---------------------------------- |
| `.ai-logs/`         | Local AI evidence logs (gitignored)            | Created at runtime by AI tools     |
| `.repomix-context/` | Generated context bundles for AI consumption   | Created by context packing scripts |
| `vendor/`           | Composer dependencies                          | Created by `composer install`      |

### Root-Level Files Explained

| File                        | Purpose                                                           |
| --------------------------- | ----------------------------------------------------------------- |
| `AGENTS.md`                 | AI agent instructions — consumed by OpenCode and Claude           |
| `CLAUDE.md`                 | Claude-specific adapter pointing to canonical docs (kit-managed, rendered from `templates/core/CLAUDE.template.md`) |
| `README.md`                 | Project overview and beginner-first entry point                   |
| `readme-install.md`         | Complete installation guide with every tool and script documented |
| `llms.txt`                  | Machine-readable project summary for AI tools                     |
| `opencode.jsonc`            | OpenCode CLI configuration                                        |
| `composer.json`             | PHP dependencies (PHPUnit for testing)                            |
| `composer.lock`             | Locked dependency versions                                        |
| `phpunit.xml.dist`          | PHPUnit test configuration                                        |
| `.ai-install-manifest.json` | Machine-readable record of what was installed                     |
| `.editorconfig`             | Editor whitespace and indent rules                                |
| `.gitattributes`            | Git line-ending and diff rules                                    |
| `.gitignore`                | Files excluded from git tracking                                  |
| `.gitleaks.toml`            | Secret scanning rules (used by gitleaks)                          |
| `.gitleaksignore`           | Gitleaks false-positive suppressions                              |
| `.markdownlint-cli2.yaml`   | Markdown lint rules                                               |
| `.shellcheckrc`             | Shell lint rules (used by shellcheck)                             |

## Template Source (packages/ai-universal-rules)

`packages/ai-universal-rules/` is the heart of the kit. It contains the **master copies** of
every file that the installer copies into target projects:

| Subfolder                 | Contains                                               |
| ------------------------- | ------------------------------------------------------ |
| `templates/core/`         | AGENTS.md, CLAUDE.md, Copilot instructions, opencode.json, VS Code settings templates, and `templates/core/agents/` (canonical agent source rendered into `.github/agents/`, `.opencode/agents/`, and `.claude/agents/`) |
| `templates/claude/`       | Claude-specific templates (`.claude/settings.json` permission baseline) |
| `templates/instructions/` | Copilot `.instructions.md` files (path-specific rules) |
| `templates/github/`       | Copilot agents, prompts, skills                        |
| `templates/commands/`     | OpenCode command definitions                           |
| `templates/skills/`       | OpenCode and Copilot skill definitions                 |
| `templates/capabilities/` | Reusable workflow capability packages                  |
| `templates/docs/`         | Documentation templates                                |
| `templates/workflows/`    | CI workflow templates                                  |
| `templates/shared/`       | Shared cross-adapter templates                         |
| `docs/`                   | Package-level documentation                            |
| `policies/`               | Policy template files                                  |
| `manifest.json`           | Package manifest — lists all installable files         |
| `catalog.json`            | Machine-readable catalog of package contents           |

## PHP Toolchain (tools/ai)

| Tool Category         | Key Files                                                                                  | What They Do                                                     |
| --------------------- | ------------------------------------------------------------------------------------------ | ---------------------------------------------------------------- |
| **CLI dispatcher**    | `ai.php`                                                                                   | Main entry point — routes `install`, `verify`, `preflight`, etc. |
| **Installer engine**  | `install/core.php`, `install/packs.php`, `install/profiles.php`                            | Template resolution, conflict detection, file writing            |
| **Validators**        | `validate-ai-config.php`, `validate-ai-catalog.php`, `validate-install-surface.php`, etc.  | Check configuration integrity without writing files              |
| **Generators**        | `generate-ai-catalog.php`, `generate-repo-structure.php`, `generate-ai-file-standards.php` | Produce documentation and metadata artifacts                     |
| **Full verification** | `verify-full-install.php`                                                                  | Runs all validators in sequence                                  |

## Adapters (.opencode, .github)

- `.opencode/` contains agent definitions (`agents/`), commands (`commands/`), and skills
  (`skills/`) for the OpenCode CLI tool. These are also installable into target projects via the
  `opencode` profile.
- `.github/` is the GitHub Copilot adapter (instructions, agents, prompts, skills, hooks). It is
  created by the installer or by self-install.
- Two separate OpenCode config files intentionally coexist: root `opencode.jsonc` is the
  kit-rendered configuration (`instructions[]`, `permission`, watcher) from
  `packages/ai-universal-rules/templates/core/opencode.json`; `.opencode/opencode.json` is a
  separately installed, non-kit-managed config (currently registers the `graphify` plugin only).
  OpenCode merges config from both locations; if a second plugin or setting needs to be added to
  `.opencode/opencode.json`, confirm the merge behavior with the installed OpenCode version first
  rather than assuming precedence.
- `schemas/ai/` holds the JSON schemas that define the expected shape of catalog files, manifests,
  command policies, and other structured metadata used by the PHP validators.

Adapters are thin layers over canonical docs. See [adapter-contract.md](adapter-contract.md) for
the rules adapters must follow.

### Regenerating This Repo's Own `.claude/agents` And `.github/agents`

`.claude/agents/*.md` and `.github/agents/*.agent.md` are rendered from
`packages/ai-universal-rules/templates/{core,optional}/agents/*.md` using the same renderer
functions the installer uses. Because this repo self-installs (`SKIP_EXISTING_UNMANAGED` on those
two dirs), a template fix does not automatically reach the shipped copies — check and reconcile
explicitly:

```bash
# Byte-parity check (CI-gated in validate-ai-surface.yml): fails if any shipped .claude/agents or
# .github/agents file no longer matches what its template renders.
php tools/ai/render-adapters.php --check

# Reconcile: re-render both trees in place from the current templates. Never touches AGENTS.md,
# CLAUDE.md, .opencode/**, or any other installed surface — only .claude/agents/*.md and
# .github/agents/*.agent.md.
php tools/ai/render-adapters.php --write
```

See `docs/tickets/claude-agent-fleet-remediation/plan-28-permission-sot-and-render-parity-sync.md`
(Phase 1) for the design rationale and `docs/ai/source-of-truth.md` for the generated-vs-hand-authored
boundary this closes.

## Running Tests

From a fresh clone, install PHP dependencies before running any PHPUnit, ParaTest, or repo-wide
test command:

```bash
composer install
```

Then run the full repository test runner:

```bash
PARATEST_PROCS=12 bash scripts/ai/run-repo-tests.sh
```

The test runner and the direct PHPUnit commands rely on Composer-managed binaries and autoloaded
packages under `vendor/`.

## Generators & Schemas

Regenerate the repo-structure assessment when the layout changes, and use `--check` for a
CI-friendly freshness check:

```bash
# Regenerate repo-structure assessment (JSON, CSV, MD, log)
php tools/ai/generate-repo-structure.php --with-scc

# Check if structure data is current (CI-friendly)
php tools/ai/generate-repo-structure.php --check --with-scc
```

This generator writes its output under `docs/ai/generated/`, which is gitignored and safe to
delete and regenerate. The tables in this guide are maintained by hand and are not produced by
that generator.

## Validation

For the full validator set and how to run it, see [validation.md](validation.md).
