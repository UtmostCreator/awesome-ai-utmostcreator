# <PROJECT_NAME> - Repository Instructions

## Project Summary

- Project: `<PROJECT_NAME>`
- Type: `<PROJECT_TYPE>`
- Summary: `<PROJECT_SUMMARY>`
- Primary language: `<PRIMARY_LANGUAGE>`
- Primary runtime: `<PRIMARY_RUNTIME>`
- Active paths: `<ACTIVE_PATHS>`
- Inactive or legacy paths: `<INACTIVE_PATHS>`
- Primary entrypoints: `<PRIMARY_ENTRYPOINTS>`

## Default Workflow

Use this default workflow unless the task is clearly trivial:

- `research when the owner is unclear -> plan for multi-step or risky work -> implement the bounded slice -> review in fresh context -> verify with evidence -> add release audit for medium or high risk`

File preview rule:

`AI_OUTPUT=json bash scripts/ai/preview-file.sh <path> --around <line> --context 30`

After search finds a relevant file or line, inspect it with `preview-file.sh`. Do not use raw `cat` for large, unknown, generated, binary-looking, or vendored files. Prefer `--around` when a line number is known, `--range` when a block range is known, and JSON mode for evidence pipelines.

Workflow rules:

- Prefer the smallest safe change.
- Before planning, editing, or reviewing, load task context from `docs/ai/generated/task-context/latest.md`, `php tools/ai/compile-task-context.php`, or `php tools/ai/impact.php` when available. If no task context exists, stay read-only until ownership and verification surface are clear.
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

- `<APPROVAL_REQUIRED_CHANGES>`
- A human approver must be able to explain each changed section well enough to own the merge.

## Core Engineering Rules

- Keep behavior explicit.
- Prefer existing repository patterns over introducing new ones.
- Keep orchestration and state ownership out of presentation code when the repository already separates those concerns.
- Avoid unrelated refactors during bug fixes.
- Do not modify inactive or legacy paths unless the task explicitly requires it.

## Read This First

Inspect the current implementation before making architectural or behavioral changes:

- `<PRIMARY_ENTRYPOINTS>`
- `docs/ai/execution-protocol.md` for non-trivial execution and verification flow
- `docs/ai/ai-file-standards.md` before adding or expanding AI workflow files

## Architecture Notes

`<ARCHITECTURE_NOTES>`

## Risk Areas

- `<RISK_AREAS>`

## Capability Map

- Core project context file: `<PROJECT_CONTEXT_PATH>`
- Available capabilities: `<AVAILABLE_CAPABILITIES>`
- Capability composition notes: `<CAPABILITY_COMPOSITION_NOTES>`
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
- Ask before changing implementation code if existing tests have not been confirmed passing first.
- When changing a symbol used elsewhere, update all call sites in the same change.

## Verification Rules

- Primary verification command: `<PRIMARY_VERIFY_COMMAND>`
- Primary build command: `<PRIMARY_BUILD_COMMAND>`
- Primary test command: `<PRIMARY_TEST_COMMAND>`
- Profile slow tests: `composer test:profile` then `composer test:slow [N]`
- Preferred narrow-first verification pattern: `<NARROW_VERIFY_GUIDANCE>`
- Verification ladder: focused proof first -> affected layer tests second -> broader repository verification third -> build as a smoke check when relevant -> release-safety review only when risk warrants it.
- Do not claim verification you did not run.
- Treat build success as a smoke check unless the project defines otherwise.
- Apply per-command timeouts and the anti-freeze discipline from `docs/ai/execution-protocol.md` before running any subprocess.

## Evidence Expectations

- State which command or check produced the claim.
- Separate direct evidence from inference.
- For behavior changes, name the focused test, flow, or assertion that proves the result.
- For `medium` and `high` risk work, state rollback path and success signal alongside verification.
- Do not report recommendations, assumptions, or unrun checks as completed work.
- When listing runnable commands for users, print one command per line with no ordered/unordered list markers so each line is copy-paste ready.

## Review Priorities

- `<REVIEW_PRIORITIES>`

## Common Gotchas

- `<KNOWN_GOTCHA_THEMES>`
- Capture recurring failure modes in capability `gotchas.md` files instead of bloating global policy.

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
