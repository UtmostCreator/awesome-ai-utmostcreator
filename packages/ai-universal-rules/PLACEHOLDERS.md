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
| `<AVAILABLE_CAPABILITIES>`    | Capability folders enabled in the repository          | `project-context, verify-change, review-diff`       | Core templates                 |
| `<REVIEW_PRIORITIES>`         | Top correctness and regression review areas           | `state ownership, API contracts, data loss risk`    | Core templates, reviewer roles |
| `<APPROVAL_REQUIRED_CHANGES>` | Changes that require approval before proceeding       | `schema changes, auth changes, dependency changes`  | Core templates                 |

## Optional Placeholders

| Placeholder                       | Meaning                                                           | Example Value                                                         | Used In                             |
| --------------------------------- | ----------------------------------------------------------------- | --------------------------------------------------------------------- | ----------------------------------- |
| `<FRONTEND_PATH_GLOB>`            | Frontend-related path scope                                       | `apps/web/**`                                                         | Copilot frontend instruction        |
| `<BACKEND_PATH_GLOB>`             | Backend-related path scope                                        | `services/api/**`                                                     | Copilot targets instruction         |
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

## Notes

- Keep replacements concise.
- Prefer concrete commands over prose when filling command placeholders.
- Delete sections that do not fit instead of leaving vague guidance behind.
- Do not invent tool features that your target environment does not support.
- Only fill capability placeholders with features you have verified for the target runtime.
