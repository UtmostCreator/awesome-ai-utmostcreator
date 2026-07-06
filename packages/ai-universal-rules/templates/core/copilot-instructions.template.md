# Repository Instructions For <PROJECT_NAME>

Use these instructions as the repository-wide baseline for GitHub Copilot.
They should remain valid even if advanced agent features or prompt files are unavailable on the active surface.

## Project Context

- Project: `<PROJECT_NAME>`
- Type: `<PROJECT_TYPE>`
- Summary: `<PROJECT_SUMMARY>`
- Active paths: `<ACTIVE_PATHS>`
- Avoid by default: `<INACTIVE_PATHS>`
- Primary entrypoints: `<PRIMARY_ENTRYPOINTS>`
- Project context file: `<PROJECT_CONTEXT_PATH>`
- Capability folders available: `<AVAILABLE_CAPABILITIES>`

## Mandatory First Step

Before planning, editing, or reviewing, apply:

- `.github/instructions/context-gate.instructions.md`

If task context is missing, perform read-only research only.

Establish scope in order: user description, then `git diff` / `git status --short`, then a branch-name ticket id (`[A-Z]+-[0-9]+`) looked up in `docs/tickets/`. Ask for the ticket if none is provided or found, and ask one clarifying question if scope stays unclear. Flag any change — including pre-existing working-tree changes — that looks outside the stated scope, and suggest removing already-present data that is now out of scope (suggest only; deletion stays approval-gated).

## Canonical Docs

Use `docs/ai/project-context.md`, `docs/ai/workflow.md`, `docs/ai/execution-protocol.md`, `docs/ai/ai-file-standards.md`, `docs/ai/failure-handling.md`, `docs/ai/agent-ops-checklist.md`, `docs/ai/integration-matrix.md`, approval/generated-artifact docs, adapter contracts, script registries, and relevant capability files including `docs/ai/capabilities/agent-observability-and-evidence/CAPABILITY.md`, `docs/ai/capabilities/evaluation-and-regression/CAPABILITY.md`, and `docs/ai/capabilities/preview-environments/CAPABILITY.md`.

## Targeted Instructions

Apply relevant files under `.github/instructions/`, especially context gate, tools, script enforcement, execution protocol, AI file standards, testing, and security instructions.

## Copilot-Specific: Shell Script Enforcement

Copilot VS Code built-in tools (`grep_search`, `read_file`, `file_search`, etc.) are preferred for search and read operations.
However, for **verification, validation, workflow commands, and git forensics**, you MUST use `run_in_terminal` with the repository shell scripts listed in `.github/instructions/copilot-script-enforcement.instructions.md`, restricted to entries in `docs/ai/script-registry.md` and `docs/ai/script-registry.json`.

Mandatory before committing any code change:

- `bash scripts/ai/ai-verify.sh .` — verification suite
- `php tools/ai/validate-ai-config.php` — config validation
- `php tools/ai/validate-ai-catalog.php` — catalog validation
- `bash scripts/ai/ai-doc-check.sh --check` — doc consistency

Do not claim verification is complete without running at least one of these commands.

## Working Style

- For non-trivial work, classify risk as `low`, `medium`, or `high` to set review and verification depth.
- Read existing code and follow established repository patterns before proposing structural changes or new abstractions.
- When the repository includes a tool map or command wrappers, load that routing first and prefer `rg`, `fd`, `ast-grep`/`sg`, and structured queries over raw `grep`, `find`, or broad file dumps.
- Treat the selected custom agent `.agent.md` tools list as the hard upper bound for tool use; prompt-file `tools:` must be equal or narrower than the agent, never wider.
- Treat `scripts/ai/pre-tool-use.sh` as the canonical pre-execution policy gate and `scripts/ai/post-tool-use.sh` as the canonical post-execution evidence writer; when the runtime supports repository hooks, keep them wired through `.github/hooks/tool-policy.json`, and when it does not, preserve the same boundary without claiming automatic enforcement.
- Approval-free read-only commands stay read-only — searches, file reads, diagnostics, diff/history inspection, and approved registry scripts such as `git status*`, `git diff*`, `git log*`, `bash scripts/ai/ai-search.sh *`, `AI_OUTPUT=json bash scripts/ai/ai-search.sh *`, `bash scripts/ai/preview-file.sh *`, `AI_OUTPUT=json bash scripts/ai/preview-file.sh *`, `bash scripts/ai/query-usage.sh *`, `bash scripts/ai/git-forensics.sh *`, and validator/check commands that do not mutate source.
- Ask for approval before making: `<APPROVAL_REQUIRED_CHANGES>`
- A human approver must be able to explain each changed section well enough to own the merge.
- Distinguish current implementation from planned or hypothetical systems, and say `unknown` instead of guessing when repository evidence is missing.
- If a slice grows beyond roughly 6 files or 300-500 changed lines, pause and confirm it is still one bounded outcome.

## Behavioral Baseline

- Ask instead of guessing when a repository fact, convention, or requirement is missing; do not invent new conventions.
- On an ambiguous or terse request ("implement now", "is it correct?", or several bundled decisions), pause and offer a structured question set with 2-4 selectable options per question before editing, then act on the chosen answers. See `docs/ai/capabilities/clarification-and-handoff/CAPABILITY.md` ("Structured Question Set").
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

## Evidence-First Execution

Follow `docs/ai/execution-protocol.md` and `.github/instructions/execution-protocol.instructions.md` for non-trivial planning, editing, review, and verification: classify task mode, inspect `git status --short` and relevant diffs, protect pre-existing user changes, keep edits inside declared scope, and end with changed files, verification status, rollback path for medium/high risk, and remaining risks.

## Core Workflow

Follow the default task flow in `docs/ai/workflow.md`: load task context -> identify affected files and targets -> classify risk -> check approval boundaries -> plan the smallest safe change -> implement -> verify -> report evidence.

## Quality Bar

- Keep logic close to its existing owner and add focused tests when behavior changes.
- Prioritize review around: `<REVIEW_PRIORITIES>`
- Main verification command: `<PRIMARY_VERIFY_COMMAND>`. Start with the smallest relevant verification and escalate only when needed.
- Use the verification ladder: focused proof first -> affected layer tests second -> broader repository verification third -> build as a smoke check when relevant -> release-safety review only when risk warrants it.
- For `medium` and `high` risk changes, define rollback or disable path and post-deploy confirmation signal.
- For migrations that drop, rename, or restructure existing data, use expand-contract.
- Treat prototype paths as exploratory only; promoted prototype code must pass the normal workflow before merge.

## Hard Stops

Stop and ask or report a blocker when:

- ownership is unclear
- test failures are unexplained
- task context or a referenced ticket's description is missing (ticket ids are looked up in `docs/tickets/`)
- the diff exceeds approved scope, or pre-existing working-tree changes fall outside it
- destructive action is needed
- any file deletion is needed, even a file that looks obviously safe to remove — ask first, every time
- generated artifacts drift unexpectedly

## Common Gotchas

- `<KNOWN_GOTCHA_THEMES>`
- When a workflow asset has its own `gotchas` section, follow the narrower guidance there.

## Limits

- Copilot surface: `<COPILOT_SURFACE>`
- Stable supported features: `<SUPPORTED_FEATURES>`
- Optional or preview features: `<OPTIONAL_FEATURES>`
- Instruction precedence notes: `<INSTRUCTION_PRECEDENCE_NOTES>`
- Conflict avoidance notes: `<CONFLICT_AVOIDANCE_NOTES>`
- Global or shared rule sources: `<GLOBAL_OR_SHARED_RULE_SOURCES>`
- Stronger VS Code posture: combine fine-grained custom-agent tools, terminal auto-approval allowlists, repo hook policy, and sandbox/network restrictions instead of relying on prompts alone.
- Copilot terminal posture: allow simple read-only git and inventory commands, but do not auto-approve heredocs, inline interpreters (`python3`, `php -r`), write redirects, destructive git, or piped network installers; keep sandbox and network filters enabled.
- Local evidence artifacts default to `.ai-logs/` as documented in `.ai-logs/README.md`.
- Do not assume prompt file support, custom-agent properties, handoffs, or advanced workflows behave the same on every Copilot surface.
- Treat hooks, skills, and MCP as surface-aware features with explicit fallbacks, and do not imply tool features that are not clearly supported in the current environment.
