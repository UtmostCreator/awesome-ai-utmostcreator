# Placeholders Index

This kit ships canonical docs, instructions, agents, and prompts containing `<UPPERCASE_TOKEN>` placeholders. Installed projects must replace the required tokens before AI write-capable flows are trusted.

## Canonical Sources

| Source                                              | Purpose                                                                 |
| --------------------------------------------------- | ----------------------------------------------------------------------- |
| `packages/ai-universal-rules/PLACEHOLDERS.md`       | Authoritative dictionary: every token, meaning, example, used-in column |
| `.schemas/project-placeholders.schema.json`         | JSON schema enforcing the registry shape                                |
| `docs/ai/project-context-placeholders.md`           | Quick guide for filling `docs/ai/project-context.md`                    |
| `tools/ai/install/core.php`                         | Installer substitution map (required + optional tokens, default values) |
| `tools/ai/validate-placeholders.php`                | Kit check: every token used in templates is documented in the dictionary |
| `tools/ai/verify-install-placeholders.php`          | Installed-project check: no required token remains unresolved           |

## Lifecycle

```text
kit-author -> kit-validate (validate-placeholders.php) -> publish
            |
installed-project <- install (substitute defaults) <- run installer
            |
            v
audit + replace defaults -> verify-install-placeholders.php -> ready
```

## Token Groups

The dictionary groups tokens as:

1. **Required project facts** - project name, type, language, runtime, paths, entrypoints, primary verify/build/test commands, capabilities, review priorities, approval-required changes.
2. **Optional adapter facts** - frontend/backend/test path globs, target platforms, architecture notes, risk areas, narrow-verify guidance, capability composition, release safety, Copilot surface and features.
3. **Project context extensions** - primary stack, file placement, naming, golden examples, formatter/linter configs, EditorConfig path, ignore files, generated/protected files, install/format commands, project-specific format exceptions, ignored files, allowed/forbidden scripts, security rules.
4. **Format slots** - `<UNKNOWN>`, `<FILES_OR_COMMANDS_CHECKED>`, `<NEXT_STEP>` are output template slots used in blocked-response examples; they remain as placeholders in canonical docs because they describe a structure, not a project value.
5. **Meta** - `<PLACEHOLDER>` is a documentation device for "any uppercase token"; the installer does not substitute it.

See `packages/ai-universal-rules/PLACEHOLDERS.md` for the full table including required vs optional status and example values.

## Verification

Kit author check (this repository):

```bash
php tools/ai/validate-placeholders.php
```

Installed-project check (run against the target project after installing):

```bash
php tools/ai/verify-install-placeholders.php --target /path/to/installed-project
```

A successful install means `verify-install-placeholders.php` exits 0 with no unresolved required tokens.

## Adding A New Placeholder

1. Add the token row to `packages/ai-universal-rules/PLACEHOLDERS.md` (required, optional, or project-context-extension table).
2. If it is a project fact that the installer can default, add it to the `$map` in `tools/ai/install/core.php::aiInstallerApplyPlaceholders()`.
3. If it must be resolved before trust is granted, add it to the `$required` array in `aiInstallerCollectPlaceholderStatus()`.
4. Use the token in the relevant template under `packages/ai-universal-rules/templates/` and/or in `docs/ai/`, `.github/`, `.opencode/` canonical surfaces.
5. Run `php tools/ai/validate-placeholders.php` locally; it should still exit 0.
