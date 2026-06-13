# Security Policy

## Reporting a Vulnerability

If you find a security issue, report it privately. Do **not** open a public issue for an
unpatched vulnerability.

- Open a [GitHub security advisory](../../security/advisories/new) for this repository, or
- Contact the maintainer directly through the contact listed on the repository profile.

Please include: what you found, how to reproduce it, the affected files or commands, and the
impact you expect. We will acknowledge your report and work with you on a fix and disclosure
timeline.

## What Counts as a Security Issue

This project installs AI workflow files, scripts, and policies into other repositories. Treat the
following as security issues:

- **Secret leakage** — any path where a script, generated artifact, or AI context bundle exposes
  credentials, tokens, or `.env` contents.
- **Unsafe command execution** — a script or policy that runs, or allows an agent to run,
  destructive or untrusted commands without the documented approval gate.
- **Destructive install behaviour** — the installer overwriting or deleting files outside its
  documented managed paths, or without a backup when one was requested.
- **Policy bypass** — any way to defeat the `scripts/ai/pre-tool-use.sh` policy gate, the per-agent
  permission allow/ask/deny lists, or the approval boundaries in `docs/ai/approval-boundaries.md`.
- **Prompt injection that escalates privilege** — repository content that causes an agent to ignore
  scope rules and perform unapproved writes or command execution.

## Safety Is Risk Reduction, Not a Guarantee

The kit ships rules, checks, and safer defaults: a policy gate, approval boundaries, secret-scan
configuration, backups, and validation. These **reduce** risk; they do **not guarantee** total
safety. You remain responsible for reviewing what agents do and what the installer changes. Human
review is the final authority before any risky change is accepted.

## Scope

Security reports about this kit's own scripts, installer, policies, and templates are in scope.
Issues in the external AI tools themselves (GitHub Copilot, OpenCode, Claude, or the underlying
models) should be reported to those vendors.
