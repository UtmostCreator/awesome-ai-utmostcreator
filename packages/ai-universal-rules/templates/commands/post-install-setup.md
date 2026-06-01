---
description: Guided post-install setup for installed projects
agent: config-maintainer
---

Use this command immediately after installation or re-installation.

Read first:

- `docs/ai/POST-INSTALL.md`
- `docs/ai/project-context.md`
- `docs/ai/project-context-placeholders.md`

Run the setup sequence in order:

Step 1:
!`php tools/ai/ai.php placeholders --fail`

Step 2:
!`php tools/ai/validate-ai-config.php`

Step 3:
!`php tools/ai/validate-install-surface.php --strict`

Step 4:
!`php tools/ai/validate-ai-catalog.php`

Step 5:
!`php tools/ai/ai.php advisor --all`

Important:

- Treat unresolved placeholders as setup blockers for write-capable AI usage.
- Separate install-surface failures from unrelated application-level lint, typecheck, or security findings.
- If `frontend.instructions.md` or `testing.instructions.md` still contains glob placeholders, report them explicitly.
