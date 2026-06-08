# awesome-ai-utmostcreator — Project Interaction Guide

This source repository packages and installs AI workflow files into other projects.

## External project interaction map

| External project/system | Direction | Local entrypoint | External path/reference | Contract |
|---|---:|---|---|---|
| Install target repository | outbound | `php tools/ai/install-ai-kit.php --target <path>` | user-provided target path | Installer writes selected AI kit files into the target when `--apply` is approved. |
| Fresh verification target | outbound | install and verification commands | `/home/utmostcreator/Projects/Test` when explicitly requested | Test target may be reset/reinstalled only when the user asks for that path. |

## Rules

- Read-only inspection of external projects named here or in `docs/ai/project-context.md` is allowed
  when relevant, subject to OpenCode `external_directory: ask` and sensitive-file rules.
- Do not edit external projects unless the user explicitly approves the exact external path and
  intended change.
- If code or docs reveal a new cross-project dependency, propose updating this file.
