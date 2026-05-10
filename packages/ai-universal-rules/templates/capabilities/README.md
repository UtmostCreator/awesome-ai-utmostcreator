# Capability Templates

These folders are the canonical reusable workflow units in this kit.

Use them to store:

- trigger-oriented workflow instructions
- setup requirements
- gotchas
- examples
- checklists
- scripts or templates when a workflow benefits from deterministic helpers

Recommended first capabilities:

- `project-context`
- `verify-change`
- `review-diff`
- `bug-regression`

Recommended next capabilities for many repositories:

- `release-safety`
- `dependency-upgrade`

Tool adapters should stay thin:

- OpenCode skills can adapt directly from these folders.
- GitHub Copilot agents and prompts can mirror the same workflow language.

When a capability grows, add support files rather than bloating the entry file.

In adopted repositories, copy these folders to `docs/ai/capabilities/` by default.
