# Post-Install Checklist

Your AI workflow kit was installed successfully. **Complete every item below before running write-capable AI agents.**

---

## Step 1 — Required: Fill Project Context (MUST DO)

Open `docs/ai/project-context.md` and replace every `<PLACEHOLDER>` with real project values.

This file is the single source of truth that all agents, prompts, and instructions read. Leaving placeholders here means agents will invent values or produce incorrect output.

> Which files are safe to edit vs re-rendered on upgrade? See `docs/ai/source-of-truth.md` → "Editable vs Generated Files".

### Required placeholders — fill before any write-capable AI run

| Placeholder                | Where it appears                         | What to put there                                                      |
| -------------------------- | ---------------------------------------- | ---------------------------------------------------------------------- |
| `<PRIMARY_STACK>`          | Section 1 — Project Identity             | e.g. `Laravel 11, PHP 8.3, MySQL 8`                                    |
| `<FILE_PLACEMENT_RULES>`   | Section 5 — Placement, Naming, and Reuse | e.g. `Controllers in app/Http/Controllers/, Services in app/Services/` |
| `<NAMING_RULES>`           | Section 5                                | e.g. `PascalCase classes, snake_case DB columns`                       |
| `<GOLDEN_EXAMPLES>`        | Section 5                                | e.g. `See app/Http/Controllers/UserController.php`                     |
| `<FORMATTER_CONFIG_FILES>` | Section 6 — Formatting                   | e.g. `.prettierrc.json, pint.json`                                     |
| `<LINTER_CONFIG_FILES>`    | Section 6                                | e.g. `.eslintrc.json, phpstan.neon`                                    |
| `<EDITORCONFIG_PATH>`      | Section 6                                | e.g. `.editorconfig` or `none`                                         |
| `<IGNORE_FILES>`           | Section 6                                | e.g. `.gitignore, .prettierignore`                                     |
| `<INSTALL_COMMAND>`        | Section 8 — Verification Commands        | e.g. `composer install && npm install`                                 |
| `<FORMAT_COMMAND>`         | Section 8                                | e.g. `./vendor/bin/pint && npm run format`                             |

### Optional placeholders — fill to improve AI output quality

| Placeholder                       | What to put there                                                  |
| --------------------------------- | ------------------------------------------------------------------ |
| `<GENERATED_FILES>`               | Paths/globs for auto-generated files agents must not edit directly |
| `<PROTECTED_FILES>`               | Files agents must never modify without explicit approval           |
| `<PROJECT_FORMATTING_EXCEPTIONS>` | Files/paths excluded from formatting rules                         |
| `<PROJECT_IGNORED_FILES>`         | Additional paths beyond `.gitignore` agents should skip            |
| `<PROJECT_ALLOWED_SCRIPTS>`       | Scripts agents may run without asking                              |
| `<PROJECT_FORBIDDEN_SCRIPTS>`     | Script patterns agents must never run                              |
| `<PROJECT_SECURITY_RULES>`        | Project-specific secrets, auth, and billing boundaries             |

---

## Step 2 — Required: Fill Path Globs in Instruction Files

Two instruction files contain glob placeholders that scope which file-type rules apply.

### `.github/instructions/frontend.instructions.md`

Find the line: `applyTo: '<FRONTEND_PATH_GLOB>'`

Replace `<FRONTEND_PATH_GLOB>` with the glob that matches your frontend files, for example:

```yaml
applyTo: "resources/js/**,resources/ts/**,resources/vue/**"
```

If you have no frontend code, replace with `applyTo: ''` or delete the file.

### `.github/instructions/testing.instructions.md`

Find the line: `applyTo: '<TEST_PATH_GLOB>'`

Replace `<TEST_PATH_GLOB>` with the glob for your test files, for example:

```yaml
applyTo: "tests/**,src/**/*.test.ts"
```

### Guided setup helper

If your AI surface supports shipped workflows or commands, start with `post-install-setup` after filling the obvious placeholders.

- OpenCode: use the installed command `post-install-setup`
- Copilot / workflow-capable surfaces: use the shipped `post-install-setup` workflow or skill

---

## Step 3 — Required: Validate the Install

Run these validation commands from your project root. All must pass before proceeding:

```bash
# Confirm required placeholders are resolved
php tools/ai/ai.php placeholders --fail

# Validate the installed AI surface
php tools/ai/validate-ai-config.php
php tools/ai/validate-install-surface.php --strict
php tools/ai/validate-ai-catalog.php
```

Review any `FAIL:` lines before proceeding. Tool-missing warnings are informational only.

---

## Step 4 — Recommended: Verify Tool Prerequisites And Follow-Up Guidance

```bash
php tools/ai/ai.php advisor --all
```

This gives the installed repo a guided follow-up pass after the install-safe validators are green.

If you want broader repository verification after setup, run it explicitly:

```bash
AI_ALLOW_NO_TIMEOUT=1 VERIFY_SECRETS=0 bash scripts/ai/ai-verify.sh .
```

Treat application-level lint, typecheck, dependency-auth, or Semgrep failures as target-repo issues unless the installed AI files caused them.

---

## Step 5 — Optional but Recommended

### Wire git hooks (blocks debug code and enforces commit format)

```bash
# Choose one:
lefthook install
```

### Generate context for AI consumption

```bash
bash scripts/ai/repomix-context-tree.sh analyze .
```

---

## Quick Verification After Setup

Run this to confirm the full install is healthy:

```bash
php tools/ai/ai.php placeholders --fail && \
php tools/ai/validate-ai-config.php && \
php tools/ai/validate-install-surface.php --strict && \
php tools/ai/validate-ai-catalog.php
```

Both should complete without `FAIL:` lines.

---

## Placeholder Audit

To list all remaining unresolved placeholders in your installed files:

```bash
rg -n '<[A-Z0-9_]+>' AGENTS.md docs/ai .github .opencode
```

Any remaining matches should be reviewed and resolved before write-capable AI runs.

---

## Kit Descriptors Under `.ai/`

The kit's own package descriptors are installed under `.ai/` (as `.ai/kit-manifest.json`, `.ai/kit-manifest.yml`, `.ai/catalog.json`, `.ai/package-lock.ai.json`) so they never collide with your project's own root files of the same name.

To inspect them, or copy one back out to its canonical root name when you want it there:

```bash
php tools/ai/ai.php descriptors --list
php tools/ai/ai.php descriptors --copy-out --name manifest.json            # dry-run preview (default)
php tools/ai/ai.php descriptors --copy-out --name manifest.json --apply    # write it to root
```

Copy-out never overwrites an existing, differing root file: it preserves yours and snapshots the kit copy under `.ai/conflicts/` so you can merge as you see fit. Only `manifest.json` and `manifest.yml` are copy-out safe; `catalog.json` and `package-lock.ai.json` stay informational-only under `.ai/`.

---

## Reference

| File                                      | Purpose                                                 |
| ----------------------------------------- | ------------------------------------------------------- |
| `docs/ai/project-context.md`              | Primary AI context — fill all placeholders here         |
| `docs/ai/project-context-placeholders.md` | Full placeholder reference with descriptions            |
| `docs/ai/workflow.md`                     | AI workflow and agent usage guide                       |
| `docs/ai/agents.md`                       | Available agents and when to use them                   |
| `docs/ai/scripts-reference.md`            | All installed scripts explained                         |
| `docs/ai/validation.md`                   | Validation and verification procedures                  |
| `.ai-install-manifest.json`               | Records what was installed, versions, and managed files |
| `.ai/local-manifest.json`                 | Informational install summary + relocated-descriptor provenance |
