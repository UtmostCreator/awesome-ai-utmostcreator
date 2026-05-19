---
applyTo: "**"
description: "Minimal repository-wide fallback rules for Copilot path-specific instruction routing"
---

# Base Instructions

- Follow `AGENTS.md` and `.github/copilot-instructions.md`.
- Work evidence-first and do not implement from memory.
- Preserve unrelated user changes.
- Inspect `git status --short` before non-trivial edits.
- Prefer the smallest correct change.
- Use the narrowest relevant verification first.
- Do not perform protected actions unless explicitly approved.
- Do not weaken tests or bypass validation to make checks pass.
