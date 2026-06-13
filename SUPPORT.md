# Support

This project is an AI workflow starter kit you install into your own repositories. This page
explains what is supported, what is not, and where to ask.

## What Is Supported

- The installer (`install-ai-kit.sh`, `tools/ai/install-ai-kit.php`) and its documented flags.
- The shipped templates, agents, commands, skills, capabilities, scripts, schemas, and policies.
- The validators and generators under `tools/ai/` and helper scripts under `scripts/ai/`.
- GitHub Copilot and OpenCode adapters, plus Claude support via `AGENTS.md` / `CLAUDE.md`.
- Linux and macOS with the documented tooling (PHP 8.2+, Bash, Git, jq, ripgrep). See
  [readme-install.md](readme-install.md) for prerequisites.

## What Is Not Supported

- The external AI tools and models themselves (GitHub Copilot, OpenCode, Claude, ChatGPT). Report
  those issues to their vendors.
- Building or shipping your software for you. The kit gives ideas, scaffolds, prepares, validates,
  and does routine work so you can **maintain sanity** — you stay in control of the result.
- Native Windows without WSL, and minimal shells that are not Bash.
- Changes you make to installed files after installation, or custom agents you create via the
  opt-in agent-creator pack.

## Where to Ask

- **Questions and usage help:** open a GitHub Discussion or issue on this repository.
- **Bugs:** open a GitHub issue with your OS, tool versions, the exact command you ran, and the
  output. Run `php tools/ai/ai.php verify` first and include the result.
- **Security issues:** do not open a public issue — follow [SECURITY.md](SECURITY.md).

## Before You Open an Issue

1. Read [readme-install.md](readme-install.md) and the troubleshooting notes there.
2. Re-run with `--dry-run` to see planned actions without writing.
3. Run `php tools/ai/ai.php verify` and include the output in your report.
