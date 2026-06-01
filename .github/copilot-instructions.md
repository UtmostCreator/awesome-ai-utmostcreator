# Repository Instructions For awesome-ai-utmostcreator

Use these instructions as the repository-wide baseline for GitHub Copilot.
They should remain valid even if advanced agent features or prompt files are unavailable on the active surface.

## Project Context

- Project: `awesome-ai-utmostcreator`
- Type: `php project`
- Summary: `AI workflow starter for awesome-ai-utmostcreator`
- Active paths: `.ai-install-manifest.json,.ai-logs,.editorconfig,.gitattributes,.github,.gitignore,.gitleaks.toml,.gitleaksignore,.markdownlint-cli2.yaml,.opencode,.repomixignore,.shellcheckrc,.vscode,AGENTS.md,CLAUDE.md,PLACEHOLDERS.md,README.md,composer.json,composer.lock,configs,docs,install-ai-kit.sh,justfile,llms.txt,opencode.jsonc,packages,phpunit.xml.dist,policies,readme-install.md,reference,schemas,scripts,sh-commands-output.md,tests,tools`
- Avoid by default: `unknown`
- Primary entrypoints: `README.md, docs/ai/project-context.md`
- Project context file: `docs/ai/project-context.md`
- Capability folders available: `project-context, verify-change, review-diff`

## Mandatory First Step

Before planning, editing, or reviewing, apply:

- `.github/instructions/context-gate.instructions.md`

If task context is missing, perform read-only research only.

## Canonical Docs

Use `docs/ai/project-context.md`, `docs/ai/workflow.md`, `docs/ai/execution-protocol.md`, `docs/ai/ai-file-standards.md`, `docs/ai/failure-handling.md`, `docs/ai/agent-ops-checklist.md`, `docs/ai/integration-matrix.md`, approval/generated-artifact docs, adapter contracts, script registries, and relevant capability files including `docs/ai/capabilities/agent-observability-and-evidence/CAPABILITY.md`, `docs/ai/capabilities/evaluation-and-regression/CAPABILITY.md`, and `docs/ai/capabilities/preview-environments/CAPABILITY.md`.

## Targeted Instructions

Apply relevant files under `.github/instructions/`, especially context gate, tools, script enforcement, execution protocol, AI file standards, testing, and security instructions.

## Copilot-Specific: Shell Script Enforcement

Copilot VS Code built-in tools (`grep_search`, `read_file`, `file_search`, etc.) are preferred for search and read operations.
However, for **verification, validation, workflow commands, and git forensics**, you MUST use `run_in_terminal` with the repository shell scripts listed in `.github/instructions/copilot-script-enforcement.instructions.md`.

Mandatory before committing any code change:

- `bash scripts/ai/ai-verify.sh .` — verification suite
- `php tools/ai/validate-ai-config.php` — config validation
- `php tools/ai/validate-ai-catalog.php` — catalog validation
- `bash scripts/ai/ai-doc-check.sh --check` — doc consistency

Do not claim verification is complete without running at least one of these commands.

## Working Style

- Prefer the smallest safe change.
- For non-trivial work, classify risk as `low`, `medium`, or `high` to set review and verification depth.
- Read existing code before proposing structural changes.
- When the repository includes a tool map or command wrappers, load that routing first and prefer `rg`, `fd`, `ast-grep`/`sg`, and structured queries over raw `grep`, `find`, or broad file dumps.
- Follow established repository patterns before inventing new abstractions.
- Keep this file policy-focused and use prompt files, skills, or agents for deeper procedures.
- Treat the selected custom agent `.agent.md` tools list as the hard upper bound for tool use.
- Do not let prompt files widen the selected agent tool surface; prompt-file `tools:` must be equal or narrower than the agent.
- For repository shell work, prefer approved wrappers from `docs/ai/script-registry.md`, `docs/ai/script-registry.json`, and `docs/ai/scripts-reference.md` over ad hoc terminal commands.
- Treat `scripts/ai/pre-tool-use.sh` as the canonical pre-execution policy gate and `scripts/ai/post-tool-use.sh` as the canonical post-execution evidence writer; when the runtime supports repository hooks, keep them wired through `.github/hooks/tool-policy.json`, and when it does not, preserve the same boundary without claiming automatic enforcement.
- Approval-free read-only commands should stay read-only: searches, file reads, diagnostics, diff/history inspection, and approved registry scripts.
- Approval-free read-only commands include: `git status*`, `git diff*`, `git log*`, `bash scripts/ai/ai-search.sh *`, `AI_OUTPUT=json bash scripts/ai/ai-search.sh *`, `bash scripts/ai/preview-file.sh *`, `AI_OUTPUT=json bash scripts/ai/preview-file.sh *`, `bash scripts/ai/query-usage.sh *`, `bash scripts/ai/git-forensics.sh *`, and validator/check commands that do not mutate source.
- Ask for approval before making: `secrets, destructive changes, auth or billing changes`
- A human approver must be able to explain each changed section well enough to own the merge.
- Distinguish current implementation from planned or hypothetical systems.
- Say `unknown` instead of guessing when repository evidence is missing.
- If a slice grows beyond roughly 6 files or 300-500 changed lines, pause and confirm it is still one bounded outcome.
- Prefer reusable capability folders for workflow-specific guidance when the repository provides them.

## Evidence-First Execution

Follow `docs/ai/execution-protocol.md` for non-trivial planning, editing, review, and verification.

Before edits:

- classify task mode
- check `git status --short`
- inspect relevant diffs
- avoid overwriting pre-existing user changes
- keep edits inside declared scope

Final responses for code changes must include changed files, verification status, rollback path for medium/high risk, and remaining risks.

## Core Workflow

Default workflow:

1. load task context
2. identify affected files and targets
3. classify risk
4. check approval boundaries
5. plan the smallest safe change
6. implement
7. verify
8. report evidence

## Quality Bar

- Keep logic close to its existing owner.
- Add focused tests when behavior changes.
- Prioritize review around: `correctness, regressions, configuration drift`
- Use `unknown` as the main verification command unless the task needs a narrower command first.
- Start with the smallest relevant verification and escalate only when needed.
- Use the verification ladder: focused proof first -> affected layer tests second -> broader repository verification third -> build as a smoke check when relevant -> release-safety review only when risk warrants it.
- For `medium` and `high` risk changes, define rollback or disable path and post-deploy confirmation signal.
- For migrations that drop, rename, or restructure existing data, use expand-contract.
- Treat prototype paths as exploratory only; promoted prototype code must pass the normal workflow before merge.

## Hard Stops

Stop and ask or report a blocker when:

- ownership is unclear
- test failures are unexplained
- task context is missing for implementation
- the diff exceeds approved scope
- destructive action is needed
- generated artifacts drift unexpectedly

## Common Gotchas

- `stale paths, broad edits without evidence, guessed behavior`
- When a workflow asset has its own `gotchas` section, follow the narrower guidance there.

## Limits

- Copilot surface: `VS Code, CLI, GitHub.com`
- Stable supported features: `repo instructions, path instructions`
- Optional or preview features: `prompt files, custom agents, hooks, MCP`
- Instruction precedence notes: `Nearest AGENTS.md wins for agent instructions.`
- Conflict avoidance notes: `Keep repo-wide and path-specific guidance complementary.`
- Global or shared rule sources: `organization instructions, user-level instructions`
- Stronger VS Code posture: combine fine-grained custom-agent tools, terminal auto-approval allowlists, repo hook policy, and sandbox/network restrictions instead of relying on prompts alone.
- Local evidence artifacts default to `.ai-logs/` as documented in `.ai-logs/README.md`.
- Do not assume prompt file support on every Copilot surface.
- Do not assume custom-agent properties, handoffs, or advanced workflows behave the same on every Copilot surface.
- Do not imply tool features that are not clearly supported in the current environment.
- Treat hooks, skills, and MCP as surface-aware features with explicit fallbacks.
