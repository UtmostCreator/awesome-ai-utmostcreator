# awesome-ai-utmostcreator

Reusable AI workflow kit for adding GitHub Copilot, OpenCode, and Claude guidance, agents, scripts, validation, and documentation to any repository.

## What This Does

This toolkit installs a complete AI workflow setup into your project:

- **GitHub Copilot** — instructions, agents, prompts, skills, and hooks
- **OpenCode** — commands and skills for OpenCode CLI
- **Claude** — agent instructions via AGENTS.md/CLAUDE.md
- **Bash scripts** — search, verification, context-packing, and policy hooks
- **PHP validators** — configuration checks, catalog generation, install verification

## Quick Start

```bash
# 1. Install prerequisites
bash scripts/ai/install-mandatory-tools.sh --dry-run

# 2. Check prerequisites
php tools/ai/ai.php preflight

# 3. Preview installation into your project
php tools/ai/ai.php install --target /path/to/your-project --profile full-governance --dry-run

# 4. Apply installation
php tools/ai/ai.php install --target /path/to/your-project --profile full-governance --apply

# 5. Validate
php tools/ai/verify-full-install.php
```

## Full Documentation

See [readme-install.md](readme-install.md) for the complete installation guide covering:

- Prerequisites and tool installation
- How the installer works step by step
- Installation profiles and options
- Every script and PHP tool documented
- Validation and verification workflow
- Context packing with Repomix

## Repository Structure

```
packages/ai-universal-rules/  — Source templates (master copies for install)
tools/ai/                     — PHP installer, validators, generators
scripts/ai/                   — Bash helper scripts (search, verify, context)
docs/ai/                      — Canonical AI workflow documentation
.github/                      — GitHub Copilot adapter (instructions, agents, prompts, skills)
.opencode/                    — OpenCode adapter (commands, skills)
tests/                        — PHPUnit + Bash test suites
policies/                     — Policy files
AGENTS.md                     — AI agent instructions (OpenCode, Claude)
CLAUDE.md                     — Claude-specific adapter
```

## Requirements

| Tool | Minimum | Install |
|------|---------|---------|
| PHP | 8.2+ | `brew install php` |
| Bash | 4.0+ | `brew install bash` |
| Git | 2.x | `brew install git` |
| jq | 1.6+ | `brew install jq` |
| rg | latest | `brew install ripgrep` |

Optional for context packing: `repomix`, `scc`, `fd`

## License

See [SECURITY.md](SECURITY.md) and [SUPPORT.md](SUPPORT.md).
