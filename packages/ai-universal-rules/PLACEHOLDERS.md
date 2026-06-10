# Placeholders

All placeholders use angle brackets:

```text
<PLACEHOLDER_NAME>
```

Use uppercase snake case for every token. Replace each token with repository-specific content before adopting the templates in a live project, unless the file is intentionally kept as an example.

## Required Placeholders

| Placeholder                   | Meaning                                               | Example Value                                       | Used In                        |
| ----------------------------- | ----------------------------------------------------- | --------------------------------------------------- | ------------------------------ |
| `<PROJECT_NAME>`              | Repository or product name                            | `Acme Portal`                                       | Most templates                 |
| `<PROJECT_SUMMARY>`           | One-sentence project description                      | `A monorepo for a customer support platform.`       | Core templates, examples       |
| `<PROJECT_TYPE>`              | High-level repository shape                           | `web app`, `backend service`, `library`, `monorepo` | Core templates, skill          |
| `<PRIMARY_LANGUAGE>`          | Main implementation language                          | `TypeScript`                                        | Core templates, skill          |
| `<PRIMARY_RUNTIME>`           | Main runtime or execution environment                 | `Node.js`, `JVM`, `browser`, `multiple`             | Core templates, skill          |
| `<ACTIVE_PATHS>`              | Main code locations that own current behavior         | `apps/web, packages/core`                           | Core templates, skill          |
| `<INACTIVE_PATHS>`            | Legacy or non-authoritative paths to avoid by default | `legacy/, prototype/`                               | Core templates                 |
| `<PRIMARY_ENTRYPOINTS>`       | Key files or services to inspect first                | `apps/web/src/app.tsx, packages/core/src/index.ts`  | Core templates                 |
| `<PRIMARY_VERIFY_COMMAND>`    | Main verification command                             | `pnpm test && pnpm build`                           | Core templates, verify command |
| `<PRIMARY_BUILD_COMMAND>`     | Smallest meaningful build command                     | `pnpm build`                                        | Core templates, snippets       |
| `<PRIMARY_TEST_COMMAND>`      | Main focused test command                             | `pnpm test`                                         | Core templates, snippets       |
| `<PROJECT_CONTEXT_PATH>`      | Location of the durable project context file          | `docs/ai/project-context.md`                        | Core templates                 |
| `<EXTRA_DOCS>`                | Extra project docs (from `.ai/project.yml` `context.extraDocs`) | `- [\`docs/architecture.md\`](docs/architecture.md)` | Core templates (auto-rendered) |
| `<AVAILABLE_CAPABILITIES>`    | Capability folders enabled in the repository          | `project-context, verify-change, review-diff`       | Core templates                 |
| `<REVIEW_PRIORITIES>`         | Top correctness and regression review areas           | `state ownership, API contracts, data loss risk`    | Core templates, reviewer roles |
| `<APPROVAL_REQUIRED_CHANGES>` | Changes that require approval before proceeding       | `schema changes, auth changes, dependency changes`  | Core templates                 |
| `<LINT_COMMAND>`              | Main lint command                                     | `npm run lint`                                      | Core templates                 |
| `<PACKAGE_MANAGER>`           | Primary package manager                               | `pnpm`, `composer`, `pip`                           | Core templates                 |
| `<SOURCE_DIRS>`               | Main source directories                               | `src/, apps/`                                       | Core templates                 |
| `<TEST_DIRS>`                 | Test directories                                      | `tests/, packages/*/__tests__/`                     | Core templates                 |
| `<TEST_COMMAND>`              | Focused test command                                  | `pnpm test`                                         | Core templates                 |
| `<BUILD_COMMAND>`             | Build command                                         | `pnpm build`                                        | Core templates                 |
| `<CI_COMMANDS>`               | CI commands run on every push or PR                   | `pnpm ci`                                           | Core templates                 |
| `<PROTECTED_PATHS>`           | Paths requiring approval before edits                 | `.github/workflows/, infra/`                        | Core templates                 |

## Optional Placeholders

| Placeholder                       | Meaning                                                           | Example Value                                                         | Used In                             |
| --------------------------------- | ----------------------------------------------------------------- | --------------------------------------------------------------------- | ----------------------------------- |
| `<FRONTEND_PATH_GLOB>`            | Frontend-related path scope                                       | `apps/web/**`                                                         | Copilot frontend instruction        |
| `<TEST_PATH_GLOB>`                | Test-related path scope                                           | `**/test/**,**/*.spec.ts`                                             | Copilot testing instruction         |
| `<TARGET_PLATFORMS>`              | Supported execution targets                                       | `web, api, worker`                                                    | Core templates, targets instruction |
| `<ARCHITECTURE_NOTES>`            | Short architecture summary                                        | `Feature modules with shared domain packages.`                        | Core templates, skill               |
| `<RISK_AREAS>`                    | High-risk technical areas                                         | `billing, migrations, background jobs`                                | Core templates, reviewers           |
| `<NARROW_VERIFY_GUIDANCE>`        | Preferred narrow-first verification strategy                      | `run package-local tests before repo-wide build`                      | Core templates, skills              |
| `<CAPABILITY_COMPOSITION_NOTES>`  | How capabilities should hand off to each other                    | `bug-regression uses project-context then verify-change`              | Core templates                      |
| `<RELEASE_SAFETY_NOTES>`          | Release and rollout notes that apply across workflows             | `feature flag fallback, smoke path, dashboards to watch after deploy` | Core templates                      |
| `<KNOWN_GOTCHA_THEMES>`           | Repeated failure modes to highlight globally                      | `over-broad diffs, build-only evidence, stale legacy paths`           | Core templates                      |
| `<COPILOT_SURFACE>`               | Target Copilot runtime                                            | `VS Code`, `CLI`, `GitHub.com`                                        | Docs, Copilot guidance              |
| `<SUPPORTED_FEATURES>`            | Stable features the target surface supports                       | `repo instructions, path instructions, custom agents`                 | Docs, examples                      |
| `<OPTIONAL_FEATURES>`             | Features that are available but non-essential or preview-only     | `prompt files, handoffs`                                              | Docs, examples                      |
| `<INSTRUCTION_PRECEDENCE_NOTES>`  | Short explanation of instruction layering                         | `Nearest AGENTS.md wins for agent instructions.`                      | Docs, examples                      |
| `<CONFLICT_AVOIDANCE_NOTES>`      | Short warning about overlapping instructions                      | `Keep repo-wide and path-specific guidance complementary.`            | Docs, examples                      |
| `<GLOBAL_OR_SHARED_RULE_SOURCES>` | Any higher-level or shared instruction sources                    | `organization instructions, personal instructions`                    | Docs, examples                      |
| `<OPTIONAL_VERIFY_COMMAND>`       | Additional verification command if needed                         | `pnpm lint`                                                           | Snippets, commands                  |
| `<SCRIPTS_ROOT>`                  | Repository-root path used when rendering approved script examples | `scripts/ai`                                                          | Rendered Copilot agent templates    |
| `<CHANGE_REQUEST>`                | Short problem/request statement for planning prompts              | `Add release safety checks for migration workflow`                    | Prompt templates                    |
| `<RISK_LEVEL>`                    | Requested/assumed risk posture used in planning prompts           | `medium`                                                              | Prompt templates                    |
| `<AFFECTED_PATHS>`                | Candidate touched paths for scoped planning prompts               | `tools/ai/, docs/ai/`                                                 | Prompt templates                    |
| `<APPROVAL_REASON>`               | Why approval may be required for a planned change                 | `schema mutation and rollback impact`                                 | Prompt templates                    |
| `<PROPOSED_CHANGE_SUMMARY>`       | Brief implementation summary in planning outputs                  | `Add strict placeholder validator and profile gate`                   | Prompt templates                    |
| `<NON_GOALS>`                     | Explicit out-of-scope items in planning outputs                   | `No runtime adapter redesign`                                         | Prompt templates                    |
| `<ROLLBACK_PATH>`                 | Rollback plan statement in planning outputs                       | `Revert commit and restore manifest-backed files`                     | Prompt templates                    |
| `<SUCCESS_SIGNAL>`                | Observable success signal in planning outputs                     | `validator and fixture matrix pass`                                   | Prompt templates                    |
| `<VERIFICATION_PLAN>`             | Ordered verification steps in planning outputs                    | `unit checks -> installer dry-run -> catalog check`                   | Prompt templates                    |
| `<OPEN_QUESTIONS>`                | Known unknowns that block safe implementation                     | `final guarded profile pack composition`                              | Prompt templates                    |
| `<CLAIM_BEING_PROVED>`            | Behavior claim under verification                                 | `strict profiles block unresolved required placeholders`              | Capability templates                |
| `<FOCUSED_PROOF_COMMAND>`         | Primary narrow proof command                                      | `php tools/ai/validate-placeholders.php`                              | Capability templates                |
| `<PASS_FAIL_OR_OUTPUT>`           | Result field recorded in proof templates                          | `exit 0`                                                              | Capability templates                |
| `<FOCUSED_PROOF_EXPLANATION>`     | Explanation of what the focused proof demonstrates                | `registry covers all template tokens`                                 | Capability templates                |
| `<OPTIONAL_VERIFY_EXPLANATION>`   | Explanation for optional secondary verification                   | `catalog check confirms no generated drift`                           | Capability templates                |
| `<UNRUN_CHECKS_AND_REASON>`       | Explicit unrun checks disclosure token                            | `full fixture matrix deferred to next slice`                          | Capability templates                |
| `<RESIDUAL_RISK_NOTES>`           | Residual risk statement token                                     | `profile migration edge cases remain`                                 | Capability templates                |

## Project Context Extensions

These placeholders appear in `docs/ai/project-context.md` (and related canonical docs). They describe how the installed project organizes files, ignores, scripts, and commands. Fill them during install or first audit.

| Placeholder                       | Meaning                                                       | Example Value                                                | Used In                          |
| --------------------------------- | ------------------------------------------------------------- | ------------------------------------------------------------ | -------------------------------- |
| `<PRIMARY_STACK>`                 | Primary technology stack label                                | `Laravel + Vue`                                              | Project context                  |
| `<FILE_PLACEMENT_RULES>`          | Where new files of each kind belong                           | `Domain code under src/Domain/; tests under tests/Unit/`     | Project context                  |
| `<NAMING_RULES>`                  | Required naming conventions                                   | `PascalCase classes; snake_case migrations`                  | Project context                  |
| `<GOLDEN_EXAMPLES>`               | Canonical example paths to mimic                              | `src/Domain/Order/Order.php, tests/Unit/Order/OrderTest.php` | Project context                  |
| `<FORMATTER_CONFIG_FILES>`        | Formatter configuration files in the repo                     | `.prettierrc.json, pint.json`                                | Project context                  |
| `<LINTER_CONFIG_FILES>`           | Linter configuration files in the repo                        | `.eslintrc.json, phpstan.neon`                               | Project context                  |
| `<EDITORCONFIG_PATH>`             | Path to the EditorConfig file                                 | `.editorconfig`                                              | Project context                  |
| `<IGNORE_FILES>`                  | Ignore lists in the repo                                      | `.gitignore, .prettierignore, .eslintignore`                 | Project context                  |
| `<GENERATED_FILES>`               | Paths of generated artifacts that must not be hand-edited     | `docs/ai/generated/, packages/*/dist/`                       | Project context                  |
| `<PROTECTED_FILES>`               | Paths that require approval before edit                       | `.git/, vendor/, composer.lock`                              | Project context                  |
| `<INSTALL_COMMAND>`               | Command to install project dependencies                       | `composer install && npm install`                            | Project context                  |
| `<FORMAT_COMMAND>`                | Command to run the formatter                                  | `npm run format && vendor/bin/pint`                          | Project context                  |
| `<PROJECT_FORMATTING_EXCEPTIONS>` | Paths or filetypes excluded from formatting                   | `legacy/, vendor/`                                           | Project context                  |
| `<PROJECT_IGNORED_FILES>`         | Additional ignore patterns specific to this project           | `*.local.php, .env.local`                                    | Project context                  |
| `<PROJECT_ALLOWED_SCRIPTS>`       | Approved repository scripts beyond the default registry       | `scripts/deploy/release.sh`                                  | Project context                  |
| `<PROJECT_FORBIDDEN_SCRIPTS>`     | Scripts that must never run without explicit approval         | `scripts/legacy/wipe-db.sh`                                  | Project context                  |
| `<PROJECT_SECURITY_RULES>`        | Additional security rules specific to this project            | `Never log raw PAN; PII never in fixtures`                   | Project context                  |
| `<UNKNOWN>`                       | Format slot for an unresolved condition in blocked responses  | `task ownership`                                             | Blocked response format          |
| `<FILES_OR_COMMANDS_CHECKED>`     | Format slot listing the evidence inspected before a block     | `git status, docs/ai/project-context.md`                     | Blocked response format          |
| `<NEXT_STEP>`                     | Format slot stating the safest next action when blocked       | `ask for owner of legacy/billing/`                           | Blocked response format          |

## Meta Placeholder

`<PLACEHOLDER>` appears in installer log messages and docs as a generic stand-in for "any uppercase token". `<PLACEHOLDER_NAME>` is the same device used in the format example at the top of this file. Both are documentation devices, not project values, so the installer does not substitute them.

## Machine-Readable Registry

This document has a machine-readable companion: `placeholders.json` (shipped from `packages/ai-universal-rules/placeholders.json`, installed as `.ai/placeholders.json`). It maps every token to its `required` flag, category, and `.ai/project.yml` key (`projectYmlKey`). Tooling reads the JSON registry:

- the installer and `php tools/ai/verify-install-placeholders.php` derive the required-token gate from it,
- `php tools/ai/ai.php placeholders --apply` substitutes mapped tokens from `.ai/project.yml` values,
- `php tools/ai/validate-placeholders.php` fails when this file and the registry drift apart.

When adding or removing a token, update both this file and `placeholders.json` in the same change.

## Notes

- Keep replacements concise.
- Prefer concrete commands over prose when filling command placeholders.
- Delete sections that do not fit instead of leaving vague guidance behind.
- Do not invent tool features that your target environment does not support.
- Only fill capability placeholders with features you have verified for the target runtime.
- Run `php tools/ai/validate-placeholders.php` to confirm every token used in templates is documented here and stays in sync with `placeholders.json`.
- After install, run `php tools/ai/verify-install-placeholders.php` against the installed project to confirm no required placeholder remains unresolved.
- Prefer filling `.ai/project.yml` and running `php tools/ai/ai.php placeholders --apply` over hand-editing each file.
