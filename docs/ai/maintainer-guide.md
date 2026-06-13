# Maintainer Guide

Developer and maintainer reference for working on the kit itself (not for installing it).

If you only want to install the kit into a project, use the [installation guide](../../readme-install.md)
instead.

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

### Untracked Folders (generated or installed at runtime)

These folders exist on disk after installation or generation but are not committed to this repository:

| Folder              | Purpose                                                               | How It Appears                       |
| ------------------- | --------------------------------------------------------------------- | ------------------------------------ |
| `.github/`          | GitHub Copilot adapter (instructions, agents, prompts, skills, hooks) | Created by installer or self-install |
| `docs/`             | Canonical AI workflow documentation and generated artifacts           | Created by installer or self-install |
| `scripts/`          | Bash helper scripts (search, verify, context, hooks)                  | Created by installer or self-install |
| `.ai-logs/`         | Local AI evidence logs (gitignored)                                   | Created at runtime by AI tools       |
| `.repomix-context/` | Generated context bundles for AI consumption                          | Created by context packing scripts   |
| `vendor/`           | Composer dependencies                                                 | Created by `composer install`        |

### Root-Level Files Explained

| File                        | Purpose                                                           |
| --------------------------- | ----------------------------------------------------------------- |
| `AGENTS.md`                 | AI agent instructions — consumed by OpenCode and Claude           |
| `CLAUDE.md`                 | Claude-specific adapter pointing to canonical docs                |
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
| `templates/core/`         | AGENTS.md, CLAUDE.md, VS Code settings templates       |
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
- `schemas/ai/` holds the JSON schemas that define the expected shape of catalog files, manifests,
  command policies, and other structured metadata used by the PHP validators.

Adapters are thin layers over canonical docs. See [adapter-contract.md](adapter-contract.md) for
the rules adapters must follow.

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
