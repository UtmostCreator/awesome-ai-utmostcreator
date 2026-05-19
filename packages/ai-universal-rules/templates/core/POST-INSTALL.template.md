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

```yaml
applyTo: 'resources/js/**,resources/ts/**,resources/vue/**'
```

If you have no frontend code, replace with `applyTo: ''` or delete the file.

### `.github/instructions/testing.instructions.md`

Find the line: `applyTo: '<TEST_PATH_GLOB>'`

Replace `<TEST_PATH_GLOB>` with the glob for your test files, for example:

```yaml
applyTo: 'tests/**,src/**/*.test.ts'
```

---

## Step 3 — Required: Validate the Install

Run these validation commands from your project root. All must pass before proceeding:

```bash
# Validate AI-facing docs and references
bash scripts/ai/ai-doc-check.sh --check docs/ai .github AGENTS.md CLAUDE.md

# Run the installed verification smoke check
AI_ALLOW_NO_TIMEOUT=1 VERIFY_SECRETS=0 AI_VERIFY_SCOPE=ai bash scripts/ai/ai-verify.sh .
```

Review any `FAIL:` lines before proceeding. Tool-missing warnings are informational only.

---

## Step 4 — Required: Verify Tool Prerequisites

```bash
bash scripts/ai/ai-verify.sh .
```

This confirms the installed script surface is runnable in the target repo. If macOS does not have `gtimeout`, the script now falls back to unbounded execution with a warning.

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
bash scripts/ai/ai-doc-check.sh --check docs/ai .github AGENTS.md CLAUDE.md && \
AI_ALLOW_NO_TIMEOUT=1 VERIFY_SECRETS=0 AI_VERIFY_SCOPE=ai bash scripts/ai/ai-verify.sh .
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
