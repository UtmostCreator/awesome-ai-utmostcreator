# Shipping Surface

This document classifies repository top-level paths for install, archive, and
release decisions. It is policy guidance for this source repository; installer
pack registries and generated manifests remain the executable source of truth.

## Classes

- `shipped` — copied, rendered, or packaged into target projects or release artifacts.
- `source` — source-repo implementation, tests, schemas, or templates used to build shipped output.
- `repo-local` — local workflow state, plans, editor configuration, or source-repo-only docs.
- `generated` — reproducible output; do not hand-edit unless the generator contract says so.
- `dependency/build` — external dependencies or build outputs; never treat as source-owned shipping input.

## Top-level path classification

| Path | Class | Notes |
| --- | --- | --- |
| `.ai-install-manifest.json` | source | Source repo install manifest evidence. |
| `.ai-logs/` | repo-local | Local execution evidence; export-ignored. |
| `.ai/` | source / repo-local | Kit metadata and local project config. |
| `.editorconfig` | shipped | Baseline editor behavior. |
| `.git/` | repo-local | VCS internals; never shipped. |
| `.gitattributes` | shipped | Line endings and export-ignore rules. |
| `.github/` | shipped/source | Runtime adapters, workflows, agents, prompts, and instructions. |
| `.gitignore`, `.gitleaks.toml`, `.gitleaksignore`, `.markdownlint-cli2.yaml`, `.repomixignore`, `.shellcheckrc` | shipped/source | Tooling policy/config. |
| `.opencode/` | shipped/source | OpenCode adapters. |
| `.phpunit.result.cache` | repo-local | PHPUnit cache. |
| `.vscode/` | shipped/source | Workspace defaults. |
| `AGENTS.md`, `CLAUDE.md`, `README.md`, `llms.txt`, `PLACEHOLDERS.md`, `SECURITY.md`, `SUPPORT.md` | shipped/source | Root docs and adapter entrypoints. |
| `ai-search-todo-tests.md`, `improvement-plan.md`, `readme-install.md`, `todo-*.md` | repo-local | Planning/history docs; archive under `docs/tickets/`. |
| `composer.json`, `composer.lock`, `phpunit.xml.dist`, `justfile` | source | Source repo runtime and verification config. |
| `configs/` | shipped/source | Starter config payloads. |
| `dist/` | dependency/build | Build output; export-ignored. |
| `docs/` | shipped/source/generated | Canonical docs; `docs/ai/generated/` is generated and `docs/tickets/` is repo-local. |
| `install-ai-kit.sh` | shipped/source | Installer entrypoint. |
| `packages/` | source | Template/package sources. |
| `policies/`, `schemas/`, `scripts/`, `tests/`, `tools/` | source | Policy, contracts, scripts, tests, and maintainer tooling. |
| `reference/` | source | Reference material. |
| `vendor/`, `node_modules/` | dependency/build | Dependency directories; never shipped from source repo. |

## Guardrails

- Add new repo-local or generated directories to `scripts/ai/internal/config/exclude-dirs.txt` when they must not leak into shipped reports.
- Add source-only context exclusions to `scripts/ai/internal/config/source-exclude-dirs.txt` when SCC/context tools should ignore them.
- `scripts/ai/ship-audit.sh` detects forbidden installer pack targets but is not yet a release gate while ignored generated directories remain untracked.
