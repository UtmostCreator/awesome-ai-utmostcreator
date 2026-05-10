# Post-Install Checklist

Your AI workflow kit was installed successfully. **Complete every item below before running write-capable AI agents.**

---

## Step 1 — Required: Fill Project Context (MUST DO)

Open `docs/ai/project-context.md` and replace every `<PLACEHOLDER>` with real project values.

This file is the single source of truth that all agents, prompts, and instructions read. Leaving placeholders here means agents will invent values or produce incorrect output.

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

```
applyTo: 'resources/js/**,resources/ts/**,resources/vue/**'
```

If you have no frontend code, replace with `applyTo: ''` or delete the file.

### `.github/instructions/testing.instructions.md`

Find the line: `applyTo: '<TEST_PATH_GLOB>'`

Replace `<TEST_PATH_GLOB>` with the glob for your test files, for example:

```
applyTo: 'tests/**,src/**/*.test.ts'
```

---

## Step 3 — Required: Validate the Install

Run these validation commands from your project root. All must pass before proceeding:

```bash
# Validate configuration consistency
php tools/ai/validate-ai-config.php

# Validate install surface (Copilot and OpenCode surfaces are correct)
php tools/ai/validate-install-surface.php --strict
```

Both must output `OK:` with no `ERROR:` lines. `WARN:` lines are informational only.

---

## Step 4 — Required: Verify Tool Prerequisites

```bash
php tools/ai/ai.php preflight
```

This checks that required tools (`php`, `git`, `jq`, `rg`) are available. Install any missing tools listed.

---

## Step 5 — Optional but Recommended

### Wire git hooks (blocks debug code and enforces commit format)

```bash
# Choose one:
php tools/ai/ai.php hooks install --driver native    # git core.hooksPath
php tools/ai/ai.php hooks install --driver husky     # Husky
php tools/ai/ai.php hooks install --driver lefthook  # Lefthook
```

### Run the project advisor (identifies gaps in your config)

```bash
php tools/ai/ai.php advisor --all
```

### Generate context for AI consumption

```bash
bash scripts/ai/repomix-context-tree.sh analyze .
```

---

## Quick Verification After Setup

Run this to confirm the full install is healthy:

```bash
php tools/ai/validate-ai-config.php && php tools/ai/validate-install-surface.php --strict
```

Both must show `OK:` with no `ERROR:` lines.

---

## Placeholder Audit

To list all remaining unresolved placeholders in your installed files:

```bash
php tools/ai/ai.php placeholders --fail
```

This exits non-zero if any required placeholders remain. Run it in CI to enforce resolution.

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
