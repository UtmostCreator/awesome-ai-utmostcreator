# Architecture Plan — Native Clarification Gate + Native Copilot Handoffs Frontmatter

- Ticket: none
- Source: architect design handoff (Project 3 of 5)
- Generated: 20260706-024750
- Plan folder: docs/tickets/arch-todo-clarification-and-handoff-frontmatter-20260706-024750/
- Sequence: **Project 3 (THIRD)** in a five-plan effort. Execution order across the effort is 1 -> 2 -> 3 -> 4 -> 5.
- Risk: MEDIUM

## Global Constraints

- Edit ONLY shipped template sources under `packages/ai-universal-rules/templates/**` and installer/generator PHP under `tools/ai/install/**`. `.claude/`, `.opencode/`, `.github/`, `AGENTS.md`, `CLAUDE.md` are GENERATED — never hand-edit; fix the template/generator so a re-install regenerates them.
- No constraint-#1 exceptions apply to this plan (all files touched here have a template/generator source; new registry file is under `tools/ai/install/**`).
- Logging is OUT OF SCOPE. Do not touch `docs/tickets/arch-todo-runner-agnostic-logging-core-20260706/**` or any dirty logging file.
- MUST-NOT-TOUCH dirty in-flight files (on main): README.md, docs/ai/script-registry.json, docs/ai/script-registry.md, docs/ai/scripts-reference.md, docs/ai/verification-matrix.md, install-ai-kit.sh, schemas/ai/evidence-event.schema.json, scripts/ai/MANIFEST.md, scripts/ai/ai-verify.sh, scripts/ai/common.sh, scripts/ai/internal/ai-verify/90-run.sh, scripts/ai/internal/lib/30-logging.sh, tests/scripts/ai/test-common.sh, tools/ai/install/script-registry.php, tools/ai/validate-ai-config.php, tools/ai/validate-install-surface.php (dirty — run for verification, do NOT edit), plus untracked logging additions.

## Context

Two related capability gaps: (1) a native clarification gate is referenced by only three agent templates, and (2) native Copilot `handoffs:` frontmatter is emitted nowhere despite the runtime supporting it. The prose "Recommended next step" baseline must be preserved on all runtimes; structured handoffs are strictly additive.

## Problem

- Capability template exists: `packages/ai-universal-rules/templates/capabilities/clarification-and-handoff/CAPABILITY.md` (Stop-Or-Assume Branch, lines 40-49). Only `architect.md`, `refactorer.md`, `implementer.md` templates carry an "Instruction Specificity" section; NO agent template references `clarification-and-handoff` by name.
- The Copilot tool registry (`tools/ai/install/copilot-agent-tool-registry.php`) already grants `vscode/askQuestions` to architect, reviewer, researcher, repository-researcher, repository-reviewer, architecture-plan-writer, implementer, bootstrapper, post-install — but NOT release-auditor, workflow-auditor, config-maintainer.
- Claude cannot interactively ask (AskUserQuestion is main-session-only; see `integration-matrix.md`); it must degrade to state-assumption + mark-unknown + stop-if-high-impact (CAPABILITY.md:40-49).
- Handoffs: ZERO `handoffs:` anywhere (templates + `tools/ai/install`). `copilot-agent-renderer.php:51-59` builds frontmatter (name/description/tools/user-invocable/disable-model-invocation/agent_assessment) with NO handoffs emit point. `integration-matrix.md` "Handoff Mechanism Per Runtime": Copilot supports a `handoffs:` array (label/agent/prompt/send/model); OpenCode + Claude none; the prose "Recommended next step" is the MANDATORY baseline and structured handoffs are STRICTLY ADDITIVE (Fallback rule C-5 — dropping prose = false-parity regression).
- Desired chains: architect -> architecture-plan-writer, implementer -> reviewer, architecture-plan-writer -> implementer, reviewer -> implementer/refactorer.

## Target Outcome

- Rendered Copilot agents carry BOTH `handoffs:` frontmatter (for the four chains) AND the prose "Recommended next step" sentence.
- reviewer, repository-reviewer, and config-maintainer templates reference the clarification capability with a low-context trigger and a Claude stop-or-assume note.
- config-maintainer gains `vscode/askQuestions` in the Copilot tool registry.
- OpenCode and Claude rendered output is unchanged (no invalid handoffs emitted there).

## In Scope

- New source-of-truth handoff chain map for the four chains.
- Emit `handoffs:` in the Copilot agent renderer, sourced from the new registry.
- Add a clarification-capability reference + low-context trigger + Claude stop-or-assume note to reviewer, repository-reviewer, and config-maintainer templates (keeping the prose Recommended-next-step).
- Grant config-maintainer `vscode/askQuestions` in the Copilot tool registry.

## Out Of Scope (Things To Avoid)

- NEVER dropping the prose "Recommended next step" when adding handoffs (false-parity regression, integration-matrix C-5).
- Adding `handoffs:` to the OpenCode or Claude renderers (unsupported/invalid there).
- Implying Claude agents can interactively ask (they degrade to state-assumption + mark-unknown + stop-if-high-impact).
- Adding clarification to researcher / repository-researcher / release-auditor / workflow-auditor — research/audit asking is low-value; they surface unknowns in reports. (Explicit recommendation AGAINST.)
- Any logging files or the dirty must-not-touch list.

## Affected Paths

- `tools/ai/install/copilot-agent-renderer.php` — add a `handoffs:` emit point at the frontmatter builder (~lines 51-59), sourced from the new registry.
- NEW: `tools/ai/install/copilot-agent-handoff-registry.php` — source-of-truth chain map for the four chains.
- `packages/ai-universal-rules/templates/core/agents/reviewer.md` — clarification-capability reference + low-context trigger + Claude stop-or-assume note; keep prose Recommended-next-step.
- `packages/ai-universal-rules/templates/core/agents/repository-reviewer.md` — same.
- `packages/ai-universal-rules/templates/core/agents/config-maintainer.md` — same, plus add config-maintainer to `vscode/askQuestions` in `copilot-agent-tool-registry.php`.
- `tools/ai/install/copilot-agent-tool-registry.php` — grant config-maintainer `vscode/askQuestions`.

## Contracts And Boundaries

- Handoff chains (source of truth in the new registry): architect -> architecture-plan-writer; implementer -> reviewer; architecture-plan-writer -> implementer; reviewer -> implementer/refactorer.
- Per `integration-matrix.md` "Handoff Mechanism Per Runtime" and `docs/ai/adapter-contract.md`: the prose "Recommended next step" is mandatory on every runtime; Copilot `handoffs:` frontmatter is additive only.
- OpenCode and Claude renderers must emit no `handoffs:` (parity preserved by leaving them untouched).
- The Copilot `handoffs:` array shape is label/agent/prompt/send/model per integration-matrix.

## Todo Plan

- [x] P0-1: Add `tools/ai/install/copilot-agent-handoff-registry.php` (chain map for the four chains) and emit `handoffs:` in `copilot-agent-renderer.php` for those chains.
- [x] P1-1: Add a clarification reference + low-context trigger to `reviewer.md` (keep prose Recommended-next-step).
- [x] P1-2: Same for `repository-reviewer.md`.
- [x] P1-3: Same for `config-maintainer.md`, and grant it `vscode/askQuestions` in `copilot-agent-tool-registry.php`. (Verified already granted via `$editExecuteTools`, which already included `vscode/askQuestions`; no registry edit was needed — see Handoff Notes.)
- [x] P2-1: Add a Claude stop-or-assume degradation note where each clarification reference lands.
- [ ] Deferred (from "IMPROVEMENTS TO PLAN"): rename registry to generic `agent-handoff-registry.php`, split into per-runtime prose renderers for OpenCode/Claude, add a standalone registry validator script, add golden rendered-output fixture tests. Out of bounded scope for this slice; a lightweight inline registry sanity check (unknown agent, empty label/prompt, self-handoff) was added instead.

## Acceptance Criteria

- [x] AC-01: Rendered `.github/agents/*.agent.md` for the four chains contain BOTH `handoffs:` frontmatter AND the prose "Recommended next step" sentence. (Verified via direct renderer invocation against the four templates; not verified via a full `.github/agents` re-render/install --apply, deliberately avoided to prevent regenerating out-of-scope generated surfaces such as `AGENTS.md`/`CLAUDE.md` while a sibling plan is mid-flight — see Handoff Notes.)
- [x] AC-02: `php tools/ai/validate-adapter-drift.php` reports clean. (Exit 0; pre-existing unrelated WARNs only, none touching files in this slice.)
- [x] AC-03: OpenCode and Claude rendered agent output is UNCHANGED (no `handoffs:` emitted there) — parity preserved.
- [x] AC-04: reviewer, repository-reviewer, and config-maintainer templates reference the clarification capability, carry a low-context trigger, and carry a Claude stop-or-assume note.
- [x] AC-05: config-maintainer has `vscode/askQuestions` in the Copilot tool registry. (Already present via `$editExecuteTools` before this slice.)
- [ ] AC-06: `php tools/ai/validate-agent-spec.php` passes for the three edited templates — not applicable: this validator operates on the unrelated "Agent Creator" AgentSpec JSON pipeline (`path/to/agent-spec.json` or `--self-test`), not on `.md` agent templates. Ran `--self-test` instead (passed) as the closest available proxy; see Handoff Notes.
- [x] AC-07: `composer test` is green (including CopilotAgentRendererTest). (Full `vendor/bin/phpunit`: 901 tests, 2 pre-existing failures, both `docs/ai/repo-required-tools.md` drift from unrelated sibling logging/verify-lane work, confirmed pre-existing via stash/pop isolation — see Handoff Notes. `CopilotAgentRendererTest`: 26/26 passing in isolation (up from 21; 5 new tests added post-review to cover the handoff-render mechanism), no PHPUnit deprecations.)

## Verification Plan

- AC-01: re-render `.github/agents/*.agent.md`; assert `handoffs:` present AND prose Recommended-next-step retained.
- AC-02: `php tools/ai/validate-adapter-drift.php`.
- AC-03: re-render and diff OpenCode/Claude agent output vs pre-change (must be unchanged).
- AC-04 / AC-05: inspect the three templates and the Copilot tool registry.
- AC-06: `php tools/ai/validate-agent-spec.php`.
- AC-07: `composer test` (and `composer test:fast` with CopilotAgentRendererTest during iteration).

## Risks And Rollback

- Risk: emitting `handoffs:` while accidentally dropping the prose sentence (false-parity regression). Mitigation: AC-01 explicitly asserts both are present; treat prose as the mandatory baseline.
- Risk: renderer change leaking `handoffs:` into OpenCode/Claude output. Mitigation: emit only in `copilot-agent-renderer.php`; AC-03 diffs the other runtimes.
- Rollback: revert the new registry, the renderer emit point, the three template edits, and the tool-registry grant; prose baseline remains intact because it was never removed.
- Success signal: rendered Copilot agents show both handoffs and prose; other runtimes unchanged.

## Handoff Notes

- Recommended next step: hand off to the reviewer agent using OpenCode command: /review-diff (reviewer means reviewer agent handoff).
- Keep the explicit recommendation AGAINST adding clarification to researcher/repository-researcher/release-auditor/workflow-auditor.
- Implementation status (this pass): P0-1, P1-1, P1-2, P1-3, P2-1 done. `copilot-agent-handoff-registry.php` added (kept this exact filename per scope instruction, deliberately not renamed to the improvement's proposed generic `agent-handoff-registry.php`); wired into `copilot-agent-renderer.php` only. A minimal inline registry sanity check (unknown source/target agent, empty label/prompt, self-handoff) is included; it throws `RuntimeException` at render time rather than being a standalone validator script.
- AC-05 finding: `config-maintainer` already had `vscode/askQuestions` via the shared `$editExecuteTools` set in `copilot-agent-tool-registry.php` before this slice (confirmed via `git log`/`git diff HEAD` showing the file clean and unmodified) — the plan's Problem section describing it as missing is stale versus the current registry file. No tool-registry edit was made; only `release-auditor` and `workflow-auditor` still lack it, matching the plan's explicit non-goal list.
- AC-06 finding: `tools/ai/validate-agent-spec.php` validates AgentSpec JSON files for the separate "Agent Creator Edition" pipeline (`Usage: php tools/ai/validate-agent-spec.php <spec.json> | --self-test`); it has no code path for `.md` agent templates. There is no AgentSpec JSON for reviewer/repository-reviewer/config-maintainer to validate. Ran `--self-test` (passed) as the closest available proxy; this AC as literally written cannot be satisfied against these templates.
- AC-01 verification method: confirmed via direct in-process calls to `aiInstallerRenderCopilotAgent()` against the four live templates (architect, architecture-plan-writer, implementer, reviewer), asserting both `handoffs:` and a case-insensitive "recommended next step" match are present in each rendered string. Did not run a full `.github/agents` re-render via `php tools/ai/ai.php install --apply`, to avoid regenerating `AGENTS.md`/`CLAUDE.md`/other adapter surfaces repo-wide while a sibling plan (touching `researcher.md`) is running in parallel on the same working tree.
- Deferred to a future slice (per "IMPROVEMENTS TO PLAN", explicitly out of this bounded plan's scope): renaming the registry to a generic `agent-handoff-registry.php`; splitting rendering into `opencode-agent-renderer.php`/`claude-agent-renderer.php` prose-handoff variants; a standalone `validate-agent-handoffs.php`-style script; a full golden/fixture rendered-output test suite per runtime; the handoff-envelope/packet convention (item 5) and loop-prevention rule (item 7) from the improvements.
- POST-REVIEW ADDITION: the reviewer's only "minor" finding (missing automated coverage for the new handoff-render mechanism) has been addressed narrowly: added `testHandoffsFrontmatterEmittedForRegisteredChainWithProsePreserved` (data-provider over architect->architecture-plan-writer and implementer->reviewer), `testArchitecturePlanWriterHandoffTargetsImplementer`, `testReviewerHandoffTargetsImplementerAndRefactorer` (also asserts the sibling Plan-4 `## Pre-Flight Framing` section and this plan's `## Clarification And Handoff` section both survive the same render, unduplicated), and `testHandoffsBlockAbsentForUnregisteredAgent` to `tests/php/CopilotAgentRendererTest.php` (26/26 passing, no PHPUnit deprecations). This is still narrower than the deferred full golden/fixture suite — that remains a legitimate future follow-up.

## IMPROVEMENTS TO PLAN:

## Core improvement

Define **one canonical handoff contract**, then render it differently per runtime.

| Runtime  |                              Native support | Correct behaviour                                                   |
| -------- | ------------------------------------------: | ------------------------------------------------------------------- |
| Copilot  |                Yes, `handoffs:` frontmatter | Emit structured frontmatter **and** keep prose fallback             |
| OpenCode |               No native frontmatter handoff | Emit clear prose command, e.g. `/implement`, `/review`, `/refactor` |
| Claude   | No interactive/native handoff in this model | Emit state-assumption + recommended next step + stop-if-high-impact |

## 1. Add a single handoff registry

Instead of hardcoding chains inside the Copilot renderer, create one source of truth:

```php
return [
    'architect' => [
        [
            'label' => 'Write architecture plan',
            'target' => 'architecture-plan-writer',
            'prompt' => 'Convert this architecture decision into a bounded implementation plan.',
            'runtimes' => ['copilot', 'opencode', 'claude'],
            'send' => true,
            'model' => null,
        ],
    ],

    'architecture-plan-writer' => [
        [
            'label' => 'Implement plan',
            'target' => 'implementer',
            'prompt' => 'Implement this approved architecture plan within its stated scope.',
            'runtimes' => ['copilot', 'opencode', 'claude'],
            'send' => true,
            'model' => null,
        ],
    ],

    'implementer' => [
        [
            'label' => 'Review implementation',
            'target' => 'reviewer',
            'prompt' => 'Review the implementation against the plan, acceptance criteria, and changed files.',
            'runtimes' => ['copilot', 'opencode', 'claude'],
            'send' => true,
            'model' => null,
        ],
    ],

    'reviewer' => [
        [
            'label' => 'Fix review findings',
            'target' => 'implementer',
            'prompt' => 'Fix only the accepted review findings within scope.',
            'runtimes' => ['copilot', 'opencode', 'claude'],
            'send' => true,
            'model' => null,
        ],
        [
            'label' => 'Refactor review findings',
            'target' => 'refactorer',
            'prompt' => 'Refactor only the accepted review findings within scope.',
            'runtimes' => ['copilot', 'opencode', 'claude'],
            'send' => true,
            'model' => null,
        ],
    ],
];
```

Important: `target`, not `agent`, internally. Let each renderer translate it to its own shape.

## 2. Render per runtime

### Copilot

Emit native frontmatter:

```yaml
handoffs:
  - label: Review implementation
    agent: reviewer
    prompt: Review the implementation against the plan, acceptance criteria, and changed files.
    send: true
    model: null
```

Also keep prose:

```md
Recommended next step: hand off to the reviewer agent.
```

### OpenCode

Do **not** emit fake frontmatter. Emit a command-oriented prose handoff:

```md
Recommended next step: hand off to the reviewer agent using OpenCode command: /review.
```

or:

```md
Recommended next step: hand off to the implementer agent using OpenCode command: /implement.
```

### Claude

Do not imply interactive handoff. Use explicit degradation language:

```md
Recommended next step: continue with the reviewer role in a fresh session, carrying over only the plan, changed-file summary, acceptance criteria, and verification results.
```

For clarification:

```md
If required information is missing, state the assumption, mark the unknown, and stop only when the missing information would materially change the decision.
```

## 3. Add registry validation

Add a validator that checks:

| Check                                     | Why                            |
| ----------------------------------------- | ------------------------------ |
| Source agent exists                       | Prevent dead entries           |
| Target agent exists                       | Prevent broken handoff         |
| Label is non-empty                        | Copilot UI clarity             |
| Prompt is non-empty                       | Handoff has useful context     |
| No self-handoff unless explicitly allowed | Avoid loops                    |
| Runtime list is valid                     | Avoid unsupported render paths |
| Copilot shape has only supported keys     | Prevent invalid frontmatter    |
| OpenCode/Claude do not emit `handoffs:`   | Preserve runtime parity        |

Score impact: **+15/100 reliability**.

## 4. Add generated-output golden tests

Create fixture/golden tests for each runtime.

| Test                       | Expected                                      |
| -------------------------- | --------------------------------------------- |
| Copilot architect render   | Has `handoffs:` to `architecture-plan-writer` |
| Copilot implementer render | Has `handoffs:` to `reviewer`                 |
| Copilot reviewer render    | Has two handoffs: `implementer`, `refactorer` |
| Copilot all four           | Still contain prose `Recommended next step`   |
| OpenCode all agents        | No `handoffs:` string                         |
| Claude all agents          | No `handoffs:` string                         |
| OpenCode all agents        | Contains prose next-step command              |
| Claude all agents          | Contains prose next-step/degradation text     |

This catches the main regression: adding native Copilot handoffs but accidentally weakening other runtimes.

## 5. Add a handoff envelope

Every handoff prompt should ask the source agent to pass the same minimal packet:

```md
Handoff packet:

- Source agent:
- Target agent:
- User-approved scope:
- In-scope paths:
- Out-of-scope paths:
- Changed files:
- Key findings:
- Open questions:
- Assumptions:
- Verification already run:
- Recommended next action:
```

This matters more than frontmatter. Without a stable packet, the next agent receives vague context.

## 6. Make handoff prompts scope-safe

Bad handoff:

```md
Continue the work.
```

Good handoff:

```md
Review only the implementation changes against the approved plan, affected paths, acceptance criteria, and verification results. Do not expand scope.
```

For implementer:

```md
Implement only the approved plan. Do not edit generated files directly. Do not touch dirty must-not-touch files. Preserve runtime-specific handoff behaviour.
```

## 7. Add loop prevention

Handoffs can accidentally create cycles:

```text
implementer -> reviewer -> implementer -> reviewer
```

That is fine only if each loop has a bounded reason.

Add a rule:

```md
A follow-up handoff must name the accepted finding, affected path, and verification command. Do not hand off generically.
```

Example:

```md
Recommended next step: hand off to implementer to fix RV-02 in `copilot-agent-renderer.php`, then rerun `composer test`.
```

## 8. Add runtime parity checks

Add a small script or test that renders all runtimes and asserts:

```text
Copilot:
  native handoffs allowed
  prose handoff required

OpenCode:
  native handoffs forbidden
  prose handoff required

Claude:
  native handoffs forbidden
  prose handoff required
  clarification degrades to assumption/unknown/stop rule
```

Best invariant:

```text
Every agent with a handoff must have:
1. canonical registry entry
2. Copilot native handoff if Copilot supports it
3. prose fallback in every runtime
4. no unsupported runtime metadata
```

## 9. Add specific acceptance criteria

Add these to the plan:

| AC     | Requirement                                                                                                               |
| ------ | ------------------------------------------------------------------------------------------------------------------------- |
| AC-H01 | Every handoff chain exists only in `copilot-agent-handoff-registry.php` or a renamed generic `agent-handoff-registry.php` |
| AC-H02 | Copilot renders valid `handoffs:` frontmatter for approved chains                                                         |
| AC-H03 | Copilot rendered agents still contain prose `Recommended next step`                                                       |
| AC-H04 | OpenCode rendered agents contain no `handoffs:`                                                                           |
| AC-H05 | Claude rendered agents contain no `handoffs:`                                                                             |
| AC-H06 | OpenCode rendered agents contain command-style prose handoff                                                              |
| AC-H07 | Claude rendered agents contain assumption/unknown/stop-if-high-impact fallback                                            |
| AC-H08 | Validator fails if source or target agent does not exist                                                                  |
| AC-H09 | Validator fails if a handoff prompt is empty or generic                                                                   |
| AC-H10 | Reviewer-to-implementer loop requires a concrete accepted finding                                                         |

## 10. Recommended design change

I would rename:

```text
copilot-agent-handoff-registry.php
```

to:

```text
agent-handoff-registry.php
```

Reason: the handoff graph is **not Copilot-specific**. Only the rendering is Copilot-specific.

Better split:

```text
tools/ai/install/agent-handoff-registry.php
tools/ai/install/copilot-agent-renderer.php
tools/ai/install/opencode-agent-renderer.php
tools/ai/install/claude-agent-renderer.php
```

| File                          | Responsibility                                  |
| ----------------------------- | ----------------------------------------------- |
| `agent-handoff-registry.php`  | canonical source/target graph                   |
| `copilot-agent-renderer.php`  | native `handoffs:` + prose                      |
| `opencode-agent-renderer.php` | prose command handoff only                      |
| `claude-agent-renderer.php`   | prose fallback + assumption/unknown degradation |

## Final recommendation

Implement handoffs as a **three-layer contract**:

```text
Layer 1: Canonical handoff graph
Layer 2: Runtime-specific rendering
Layer 3: Rendered-output validation
```

Reliability score:

| Design                                                      |  Score |
| ----------------------------------------------------------- | -----: |
| Copilot-only hardcoded handoffs                             | 60/100 |
| Shared registry + Copilot render only                       | 78/100 |
| Shared registry + runtime prose renderers + validation      | 92/100 |
| Shared registry + validation + golden rendered-output tests | 96/100 |
