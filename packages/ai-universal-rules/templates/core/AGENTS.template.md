<!-- GENERATED — DO NOT EDIT: rendered by ai-kit installer from packages/ai-universal-rules/templates/core/AGENTS.template.md. Edit the template or .ai/project.yml, not this file. -->

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
- Before planning, editing, or reviewing, establish task context from `docs/ai/generated/task-context/latest.md` when a task-context generator has produced it, otherwise from read-only discovery via `scripts/ai/ai-search.sh` plus `git status --short` and `git diff`. If no task context exists, stay read-only until ownership and verification surface are clear. The `docs/ai/generated/task-context/` path is optional, ephemeral generator output read only if present; write durable plans and task context to the committed `docs/tickets/` location, never under the gitignored `docs/ai/generated/`.
- Establish task scope before editing: user description first, then `git diff` / `git status --short`, then a branch-name ticket id (`[A-Z]+-[0-9]+`) looked up in `docs/tickets/`. If no ticket is provided or found, ask for it; if scope or ownership stays unclear, ask one clarifying question and stay read-only. See `.github/instructions/context-gate.instructions.md`.
- Flag any change, including a change already present in the working tree, that looks outside the stated task or ticket scope; pause for confirmation before keeping or extending it.
- When making a new change, sweep already-present data in the touched area and suggest removing anything now outside scope (suggest only; deletion stays approval-gated).
- Before changing code, config, docs, or workflow logic, search for similar existing patterns in the touched area and nearby owners and report the closest overlap as a percentage.
- If overlap is roughly `>=75%`, flag reuse or replacement immediately and recommend updating the existing pattern instead of adding a duplicate.
- After completing the change, run a touched-scope stale sweep on edited files and nearby references for stale methods, stale data assumptions, stale commands/paths, outdated docs, unresolved placeholders, and generated-output drift.
- When the repository includes a tool map or command wrappers, load that routing first (see `docs/ai/tools/tool-map.md`, which routes to `docs/ai/tools/ai-search.md` and the action docs under `docs/ai/tools/actions/`) and prefer `rg`, `fd`, `ast-grep`/`sg`, and structured queries over raw `grep`, `find`, or broad file dumps.
- Treat `scripts/ai/pre-tool-use.sh` as the canonical pre-execution policy gate and `scripts/ai/post-tool-use.sh` as the canonical post-execution evidence writer; when a surface cannot auto-load repository hooks, preserve the same boundary manually and use `.ai-logs/` as the canonical local evidence root.
- Document and flag any command, tool, verification failure, or permission block immediately in the completion report with exact command and status; do not silently omit failed checks.
- For non-trivial work, classify risk as `low`, `medium`, or `high` to choose review and verification depth.
- Do not invent systems, services, persistence layers, or infrastructure that are not present.
- Escalate when ambiguity would change architecture, persistence shape, public interfaces, dependency surface, security posture, or rollout risk.
- Say `unknown` when the repository does not prove something.
- If a slice grows beyond roughly 6 files or 300-500 changed lines, pause and confirm it is still one bounded outcome.

## Behavioral Baseline

- Ask instead of guessing when a repository fact, convention, or requirement is missing; do not invent new conventions.
- On an ambiguous or terse request ("implement now", "is it correct?", or several decisions bundled into one message), pause and offer a structured question set with 2-4 selectable options per question before editing, then act on the chosen answers. See `docs/ai/capabilities/clarification-and-handoff/CAPABILITY.md` ("Structured Question Set"). Where interactive selection is unavailable, state the assumption, mark it `unknown`, and stop on high-impact ambiguity.
- Prefer simplicity over speculative abstraction; add structure only when the current task actually needs it.
- Make surgical, task-scoped changes; avoid drive-by edits outside any task, including during bug fixes.
- When trading speed for caution, bias toward caution, clarity, and evidence over speculative speed.
- Verify an edit landed before continuing; never re-apply or re-append content after a blocked or failed edit — stop and report the exact blocked path instead.
- Stop and report after 3 failed attempts to land or fix the same edit, or after 3 repeated review/fix-loop iterations; surface unresolved tradeoffs clearly.
- Never delete a file, and never overwrite one whose existing content is unexpected or unfamiliar, without asking first — even a file that looks obviously safe to remove or replace; treat unfamiliar existing content as a pre-existing change to flag, not something to silently delete or clobber.
- Match the existing style and conventions of the file you edit, even if you would do it differently.
- Mention unrelated dead code you notice; do not delete it unless asked.
- Remove only imports, variables, or functions that your change made unused — not pre-existing dead code.
- Every changed line should trace directly to the stated request.
- Do not add error handling for scenarios that cannot occur.
- See `docs/ai/snippets/anti-pattern-examples.md` for concrete examples of hidden assumptions, overcomplication, drive-by changes, and weak success criteria that violate this baseline.

## Approval Required Before Proceeding

Ask for approval before making:

- `<APPROVAL_REQUIRED_CHANGES>`
- A human approver must be able to explain each changed section well enough to own the merge.

## External Project Context Policy

OpenCode is configured with `external_directory: ask` so external paths are not hard-blocked,
but the runtime must ask before tools touch paths outside the directory where OpenCode started.

Read-only inspection of another project is allowed when that project or path is explicitly
mentioned in `docs/ai/project-context.md` or `docs/ai/project/project-interaction.md`, subject to
the runtime permission prompt and normal secret/sensitive-file rules. Use it only to answer the
current task, and report which external path was inspected.

When an external project is relevant but not named in those files, ask before reading. The question
must include the exact path or project name, why it matters, and whether access is read-only.

Agents with edit permission may modify only the current project by default. If a task appears to
require editing an external project, stop and ask for separate explicit approval that names the
external path and intended change. If approval is denied, continue with current-repo evidence only
and mark the missing external context or edit as a limitation.

## Web, Browser, and Outside-Repo Access On Request

The secret-file and external-edit rules above do not block ordinary web or browser work. When the
task calls for it, agents MAY:

- request access to paths outside the repository (subject to the `external_directory: ask` prompt),
- fetch and read web pages via the webfetch/websearch tools (subject to their `ask` prompt),
- open, read, and debug browser pages the user points them at (for example a local dev URL).

These stay allowed-on-request. Tightening the secret-file policy never implies removing web, search,
browser, or outside-repo access — it only forbids harvesting secrets from credential files.

## Core Engineering Rules

- Keep behavior explicit and prefer existing repository patterns over introducing new ones.
- Keep orchestration and state ownership out of presentation code when the repository already separates those concerns.
- Do not modify inactive or legacy paths unless the task explicitly requires it.

## Read This First

Inspect the current implementation before making architectural or behavioral changes:

- `<PRIMARY_ENTRYPOINTS>`
- `docs/ai/agents.md` for live agent routing
- `docs/ai/execution-protocol.md` for non-trivial execution and verification flow
- `docs/ai/failure-handling.md` for blocked, failed, or partial work
- `docs/ai/ai-file-standards.md` before adding or expanding AI workflow files
- `docs/ai/agent-ops-checklist.md` and `docs/ai/integration-matrix.md` for agent operations and compatibility checks
- `docs/ai/generated-artifacts.md` before reading, deleting, or regenerating anything under `docs/ai/generated/`

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

- Use prompt files or commands for recurring one-off tasks, and staged agents when fresh context, tool boundaries, or handoffs improve safety.
- Use capability folders or skills for deeper optional procedures; do not turn this file into the only bug-fix, release, or migration workflow definition.

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

- For `medium` and `high` risk changes, define the rollback or disable path and the observable signal that confirms success after deployment.
- See `docs/ai/capabilities/release-safety/CAPABILITY.md` ("Migration And Data Safety") for feature-flag, additive-migration, expand-contract, and backfill-sequencing guidance.

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

- State which command or check produced the claim, and separate direct evidence from inference.
- For behavior changes, name the focused test, flow, or assertion that proves the result.
- For `medium` and `high` risk work, state rollback path and success signal alongside verification.
- Do not report recommendations, assumptions, or unrun checks as completed work.
- When listing runnable commands for users, print one command per line with no ordered/unordered list markers so each line is copy-paste ready.

## Review Priorities

- `<REVIEW_PRIORITIES>`

## Common Gotchas

- `<KNOWN_GOTCHA_THEMES>`
- Capture recurring failure modes in capability gotchas files instead of bloating global policy.

## Documentation Rules

- Distinguish current implementation from future ideas; prefer code-verified statements over planning assumptions.
- Use exact commands that work in the repository.
- Keep reusable workflow guidance in capability support files with examples and checklists.

## Do Not

- Do not assume a stack, framework, or deployment target that is not confirmed.
- Do not silently widen permissions, scope, or behavior.
- Do not delete files or reshape the module layout without approval.
- Do not treat always-on instructions as a replacement for task entry points, staged agents, or enforcement hooks.
