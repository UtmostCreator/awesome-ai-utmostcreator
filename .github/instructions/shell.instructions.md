---
applyTo: "**/*.sh,scripts/**/*.sh,scripts/ai/**/*.sh"
description: "Shell safety, portability, and verification rules"
---

# Shell Rules

- Quote variables and paths unless intentional splitting is documented.
- Do not use `eval` for untrusted input.
- Validate required tools with `command -v`.
- Prefer strict mode compatible with script shell (`set -eu` or `set -euo pipefail` for bash).
- Use safe temporary-file handling and cleanup.
- Preserve deterministic output and clear exit codes.
