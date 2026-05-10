# app-configs - Repository Instructions

## Project Summary

- Project: `app-configs`
- Type: `php project`
- Summary: `AI workflow starter for app-configs`
- Primary language: `unknown`
- Primary runtime: `unknown`
- Active paths: `.ai-install-manifest.json,.ai-logs,.editorconfig,.eslintrc.json,.gitattributes,.github,.gitignore,.husky,.lefthook.yml,.markdownlint-cli2.yaml,.opencode,.prettierrc.json,.repomixignore,.schemas,.shellcheckrc,.stylelintrc.json,AGENTS.md,CLAUDE.md,CONTRIBUTING.md,README.md,SECURITY.md,SUPPORT.md,composer.json,composer.lock,configs,docs,justfile,llms.txt,packages,phpunit.xml.dist,policies,scripts,tests,tools`
- Inactive or legacy paths: `unknown`
- Primary entrypoints: `README.md, docs/ai/project-context.md`

## OpenCode Script-First Rule

- Start every non-trivial OpenCode session with `git status --short`, then collect evidence in this order: `AI_OUTPUT=json bash scripts/ai/ai-search.sh changed QUERY . --fixed`, `staged`, then `tracked` before broad modes.
- Read files through `AI_OUTPUT=json bash scripts/ai/preview-file.sh PATH --around LINE --context 30` or `--range A:B`; do not use raw `cat`, `sed`, `awk`, `grep`, `rg`, `find`, `fd`, glob, or list before the approved wrappers unless explicitly approved.
- Discover usage with `bash scripts/ai/query-usage.sh SYMBOL_OR_PATH` before adding parallel logic or changing contracts.
- Verify with `bash scripts/ai/ai-verify.sh .` when behavior or wiring changes; for `ai-search` changes also run `AI_OUTPUT=json bash scripts/ai/ai-search.sh doctor` and the focused ai-search tests when available.
- Escalate to `bash scripts/ai/pack-context.sh` or repomix context scripts only when bounded evidence is insufficient and the human approves the larger context pack.
- Approval is required for `unsafe-all`, reading or editing secrets, `AI_ALLOW_UNLIMITED=1`, `ai-edit`, `ai-rollback`, `install-mandatory-tools`, destructive git, generated artifact edits, or any command that can delete, overwrite, install, deploy, or expose credentials.

## Default Workflow

Use this default workflow unless the task is clearly trivial:

- `research when the owner is unclear -> plan for multi-step or risky work -> implement the bounded slice -> review in fresh context -> verify with evidence -> add release audit for medium or high risk`

Default evidence command:

`AI_OUTPUT=json bash scripts/ai/ai-search.sh MODE QUERY . --fixed`

File preview rule:

`AI_OUTPUT=json bash scripts/ai/preview-file.sh PATH --around LINE --context 30`

After `ai-search.sh` finds a relevant file or line, inspect it with `preview-file.sh`. Do not use raw `cat` for large, unknown, generated, binary-looking, or vendored files. Prefer `--around` when a line number is known, `--range` when a block range is known, and JSON mode for evidence pipelines.

Workflow rules:

- Prefer the smallest safe change.
- Before changing code, config, docs, or workflow logic, search for similar existing patterns in the touched area and nearby owners and report the closest overlap as a percentage.
- If overlap is roughly `>=75%`, flag reuse or replacement immediately and recommend updating the existing pattern instead of adding a duplicate.
- After completing the change, run a touched-scope stale sweep on edited files and nearby references for stale methods, stale data assumptions, stale commands/paths, outdated docs, unresolved placeholders, and generated-output drift.
- When the repository includes a tool map or command wrappers, load that routing first and prefer `rg`, `fd`, `ast-grep`/`sg`, and structured queries over raw `grep`, `find`, or broad file dumps.
- Treat `scripts/ai/pre-tool-use.sh` as the canonical pre-execution policy gate and `scripts/ai/post-tool-use.sh` as the canonical post-execution evidence writer; when a surface cannot auto-load repository hooks, preserve the same boundary manually and use `.ai-logs/` as the canonical local evidence root.
- Keep stable policy here and move procedural depth into capabilities, prompts, commands, or staged agents.
- For non-trivial work, classify risk as `low`, `medium`, or `high` to choose review and verification depth.
- Ground decisions in active code and configuration, not aspiration.
- Do not invent systems, services, persistence layers, or infrastructure that are not present.
- Escalate when ambiguity would change architecture, persistence shape, public interfaces, dependency surface, security posture, or rollout risk.
- Say `unknown` when the repository does not prove something.
- If a slice grows beyond roughly 6 files or 300-500 changed lines, pause and confirm it is still one bounded outcome.
- Stop repeated review or fix loops after three iterations and surface unresolved tradeoffs clearly.

## Approval Required Before Proceeding

Ask for approval before making:

- `secrets, destructive changes, auth or billing changes`
- A human approver must be able to explain each changed section well enough to own the merge.

## Core Engineering Rules

- Keep behavior explicit.
- Prefer existing repository patterns over introducing new ones.
- Keep orchestration and state ownership out of presentation code when the repository already separates those concerns.
- Avoid unrelated refactors during bug fixes.
- Do not modify inactive or legacy paths unless the task explicitly requires it.

## Read This First

Inspect the current implementation before making architectural or behavioral changes:

- `README.md, docs/ai/project-context.md`
- `docs/ai/execution-protocol.md` for non-trivial execution and verification flow
- `docs/ai/ai-file-standards.md` before adding or expanding AI workflow files
- `docs/ai/agents.md, docs/ai/failure-handling.md, docs/ai/agent-ops-checklist.md, docs/ai/integration-matrix.md`

## Architecture Notes

`Keep policy and capability docs canonical; keep runtime adapters thin.`

## Risk Areas

- `stale docs, adapter drift, unsafe command usage`

## Capability Map

- Core project context file: `docs/ai/project-context.md`
- Available capabilities: `project-context, verify-change, review-diff`
- Capability composition notes: `start with project-context, then verify-change, then review-diff`
- Prefer capability folders for reusable workflow knowledge; keep this file focused on baseline policy.

## Entry Point Rules

- Use prompt files or commands for recurring one-off tasks.
- Use staged agents when fresh context, tool boundaries, or handoffs improve safety.
- Use capability folders or skills for deeper optional procedures.
- Do not turn this file into the only bug-fix, release, or migration workflow definition.

## Execution Protocol

For non-trivial work, follow `docs/ai/execution-protocol.md`.

Minimum flow:

1. classify task mode
2. check worktree/diff state before edits
3. protect pre-existing user changes
4. apply the smallest patch
5. verify with relevant evidence
6. report verification status honestly

## Release and Migration Safety

- For `medium` and `high` risk changes, define rollback or disable path before implementation.
- For `medium` and `high` risk changes, define what observable signal confirms success after deployment.
- Use a feature flag for `medium` or `high` risk behavior changes when practical.
- For additive-only migrations, document rollback posture and proceed.
- For migrations that drop, rename, or restructure existing data, use an expand-contract strategy.
- Plan large backfills separately from schema mutation when data volume or runtime impact is significant.

## Prototype Lane

- Exploratory code may be created in prototype paths.
- Prototype code must not be merged directly into production paths.
- Any promoted prototype must be respecified as a normal bounded slice and pass the standard workflow.

## Testing Rules

- Prefer the lowest test level that proves the behavior.
- Add or update focused tests when behavior changes.
- Keep tests deterministic where possible.
- Do not weaken tests to make changes pass.

## Verification Rules

- Primary verification command: `unknown`
- Primary build command: `unknown`
- Primary test command: `unknown`
- Preferred narrow-first verification pattern: `start with the narrowest repo-local check and escalate only if needed`
- Verification ladder: focused proof first -> affected layer tests second -> broader repository verification third -> build as a smoke check when relevant -> release-safety review only when risk warrants it.
- Do not claim verification you did not run.
- Treat build success as a smoke check unless the project defines otherwise.

## Evidence Expectations

- State which command or check produced the claim.
- Separate direct evidence from inference.
- For behavior changes, name the focused test, flow, or assertion that proves the result.
- For `medium` and `high` risk work, state rollback path and success signal alongside verification.
- Do not report recommendations, assumptions, or unrun checks as completed work.

## Review Priorities

- `correctness, regressions, configuration drift`

## Common Gotchas

- `stale paths, broad edits without evidence, guessed behavior`
- Capture recurring failure modes in capability-specific `docs/ai/capabilities/*/gotchas.md` files instead of bloating global policy.

## Documentation Rules

- Distinguish current implementation from future ideas.
- Prefer code-verified statements over planning assumptions.
- Use exact commands that work in the repository.
- Keep reusable workflow guidance in capability support files with examples and checklists.

## Do Not

- Do not assume a stack, framework, or deployment target that is not confirmed.
- Do not silently widen permissions, scope, or behavior.
- Do not delete files or reshape the module layout without approval.
- Do not treat always-on instructions as a replacement for task entry points, staged agents, or enforcement hooks.
