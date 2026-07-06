# Architecture Plan — Preview Large-File Failure + Review/Research Pre-Flight Framing

- Ticket: none
- Source: architect design handoff (Project 4 of 5)
- Generated: 20260706-024750
- Plan folder: docs/tickets/arch-todo-preview-large-file-and-review-framing-20260706-024750/
- Sequence: **Project 4 (FOURTH)** in a five-plan effort. Execution order across the effort is 1 -> 2 -> 3 -> 4 -> 5.
- Risk: LOW

## Global Constraints

- Edit ONLY shipped template sources under `packages/ai-universal-rules/templates/**` and installer/generator PHP under `tools/ai/install/**`. `.claude/`, `.opencode/`, `.github/`, `AGENTS.md`, `CLAUDE.md` are GENERATED — never hand-edit; fix the template/generator so a re-install regenerates them.
- **Constraint-#1 EXCEPTIONS (user-approved):** these REPO-LOCAL files have NO template source and MAY be edited directly, each flagged in-plan: `tools/ai/tools/actions/file-viewing.md`, `tools/ai/tools/tool-map.md`, `tools/ai/cli-tools.md`, `scripts/ai/preview-file.sh`.
- Logging is OUT OF SCOPE. Do not touch `docs/tickets/arch-todo-runner-agnostic-logging-core-20260706/**` or any dirty logging file.
- MUST-NOT-TOUCH dirty in-flight files on main: `README.md`, `docs/ai/script-registry.json`, `docs/ai/script-registry.md`, `docs/ai/scripts-reference.md`, `docs/ai/verification-matrix.md`, `install-ai-kit.sh`, `schemas/ai/evidence-event.schema.json`, `scripts/ai/MANIFEST.md`, `scripts/ai/ai-verify.sh`, `scripts/ai/common.sh`, `scripts/ai/internal/ai-verify/90-run.sh`, `scripts/ai/internal/lib/30-logging.sh`, `tests/scripts/ai/test-common.sh`, `tools/ai/install/script-registry.php`, `tools/ai/validate-ai-config.php`, `tools/ai/validate-install-surface.php` (dirty — run for verification, do NOT edit), plus untracked logging additions.

## Context

The `preview-file.sh` 64KiB safety gate (F-11) causes large-file preview failures. Several repo-local tool docs still recommend raw `bat --line-range` for huge files, which bypasses the safer project preview wrapper. Review and research agents also lack compact pre-flight framing, so they may start reviewing without first stating target, goal, checks, rationale, and out-of-scope boundaries.

Reviewer PR-read is already functional and is documentation-only in this plan.

## Problem

- `scripts/ai/preview-file.sh:76` sets `max_bytes_raw="65536"` (64KiB), enforced at lines 197-205.
- `--range` does NOT bypass the 64KiB raw-file gate.
- `--force` bypasses the gate and must remain an explicit, rationale-required bypass.
- `preview-file.sh` is REPO-LOCAL and has no template source.
- Conflicting docs recommend raw `bat --line-range`:
  - `tools/ai/tools/actions/file-viewing.md:18`
  - `tools/ai/tools/tool-map.md:65,110`
  - `tools/ai/cli-tools.md:55`

- These three docs are REPO-LOCAL and have no template source. They are approved Constraint-#1 exceptions for direct edits.
- A same-named `tool-map.md` template renders to the DIFFERENT path `docs/ai/tools/tool-map.md`, which is already safe and is NOT the target of this plan.
- `researcher.md` template grants `bat*` and `preview-file.sh*`, and already warns not to open large dumps/generated files directly, but it does not prescribe the safer large-file command shape.
- `reviewer.md` template already has `'bash scripts/ai/gh-pr-context.sh *': allow`.
- `scripts/ai/gh-pr-context.sh` exists.
- Claude reviewer can use the Bash tool with no `gh` deny in this context.
- Therefore, reviewer PR-read is already functionally covered; this plan only documents that fact.

## Target Outcome

- `researcher.md` and `reviewer.md` carry compact 3-5 line pre-flight framing:
  - target/scope
  - main goal
  - what to check
  - why those checks matter
  - explicit out-of-scope boundaries

- `researcher.md` prefers `scripts/ai/preview-file.sh --range START:END --max-bytes N` over raw `bat --line-range` for large/generated files.
- The three repo-local tool docs stop recommending raw `bat --line-range` for huge/generated files.
- The docs route legitimate large/generated file previews through `scripts/ai/preview-file.sh --range START:END --max-bytes N`.
- `--force` is documented as exceptional and rationale-required, not as the default recommendation.
- The default 64KiB safety gate remains unchanged.
- No code path makes `--range` silently bypass the 64KiB gate.
- Reviewer PR-read path is documented as already functional, with no permission or behaviour change.

## In Scope

- `researcher.md` template:
  - prefer `scripts/ai/preview-file.sh --range START:END --max-bytes N` over raw `bat --line-range` for large/generated files
  - document `--force` as exceptional and rationale-required
  - add compact pre-flight framing

- `reviewer.md` template:
  - add compact pre-flight framing
  - state what to review, main goal, what to look for, why it matters, and what is out of scope
  - document reviewer PR-read path as already functional if the template contains a relevant reviewer workflow section

- Repo-local tool docs:
  - `tools/ai/tools/actions/file-viewing.md`
  - `tools/ai/tools/tool-map.md`
  - `tools/ai/cli-tools.md`
  - route large/generated files through `scripts/ai/preview-file.sh --range START:END --max-bytes N`
  - document `--force` as exceptional and rationale-required
  - stop recommending raw `bat --line-range` for huge/generated files

- `scripts/ai/preview-file.sh`:
  - may receive documentation/help-text clarification only
  - must NOT change the default 64KiB gate in this slice
  - must NOT make `--range` silently bypass the gate

- Document reviewer PR-read as already functional.

## Out Of Scope

- Raising the default `preview-file.sh` 64KiB safety gate.
- Making `--range` bypass the 64KiB gate.
- Recommending `--force` as the default large-file workflow.
- Editing generated files directly:
  - `.claude/**`
  - `.opencode/**`
  - `.github/**`
  - `AGENTS.md`
  - `CLAUDE.md`

- Editing the same-named `tool-map.md` template that renders to `docs/ai/tools/tool-map.md`.
- Assuming the three repo-local tool docs are templated.
- Changing reviewer PR-read permissions or scripts unless verification proves they are broken.
- Any logging files or dirty must-not-touch files.
- Widening scope into the other four plans.

## Affected Paths

- `packages/ai-universal-rules/templates/core/agents/researcher.md`
  - add compact pre-flight framing
  - prefer `scripts/ai/preview-file.sh --range START:END --max-bytes N` over raw `bat --line-range` for large/generated files
  - document `--force` as exceptional

- `packages/ai-universal-rules/templates/core/agents/reviewer.md`
  - add compact pre-flight framing
  - optionally document PR-read path as already functional if the local workflow section is the correct place

- `tools/ai/tools/actions/file-viewing.md`
  - **CONSTRAINT-#1 EXCEPTION**
  - repo-local, no template
  - route large/generated previews through `scripts/ai/preview-file.sh --range START:END --max-bytes N`
  - stop recommending raw `bat --line-range` for huge/generated files

- `tools/ai/tools/tool-map.md`
  - **CONSTRAINT-#1 EXCEPTION**
  - repo-local, no template
  - same routing fix

- `tools/ai/cli-tools.md`
  - **CONSTRAINT-#1 EXCEPTION**
  - repo-local, no template
  - same routing fix

- `scripts/ai/preview-file.sh`
  - **CONSTRAINT-#1 EXCEPTION**
  - repo-local, no template
  - documentation/help-text clarification only if needed
  - do NOT raise the default gate in this slice

## Contracts And Boundaries

### Large-file preview contract

- Default raw-file safety gate remains `65536` bytes.
- `--range` does not bypass the gate.
- `--force` remains the explicit bypass.
- `--force` must be documented as exceptional and rationale-required.
- Legitimate large/generated file preview should use:

```bash
scripts/ai/preview-file.sh --range START:END --max-bytes N path/to/file
```

- Use `--force` only when the file must be inspected despite the gate, and state why.
- Do not recommend raw `bat --line-range` for huge/generated files.

### Pre-flight framing contract

Before review or research, the agent should state a compact 3-5 line frame:

- target/scope
- main goal
- what will be checked
- why those checks matter
- what is explicitly out of scope

The pre-flight frame must guide the task. It must not become a separate report or verbose planning section.

### Reviewer PR-read contract

- Reviewer PR-read is already functional.
- OpenCode path: `scripts/ai/gh-pr-context.sh`.
- Claude path: Bash tool where available.
- This plan documents the path only.
- Do not alter permissions, shell scripts, or generated files for PR-read in this slice.

## Todo Plan

- [x] P1-1: `researcher.md` template — add a large/generated-file rule that prefers `scripts/ai/preview-file.sh --range START:END --max-bytes N` over raw `bat --line-range`.
- [x] P1-2: `researcher.md` template — document `--force` as exceptional and rationale-required.
- [x] P1-3: `researcher.md` template — add compact 3-5 line pre-flight framing.
- [x] P1-4: `reviewer.md` template — add compact 3-5 line pre-flight framing: what to review, main goal, what to look for, why, and what is out of scope. (Added additively; did not touch the sibling's pre-existing `## Clarification And Handoff` section.)
- [x] P2-1: **CONSTRAINT-#1 EXCEPTION** — fix `tools/ai/tools/actions/file-viewing.md` to route large/generated previews through `scripts/ai/preview-file.sh --range START:END --max-bytes N`.
- [x] P2-2: **CONSTRAINT-#1 EXCEPTION** — fix `tools/ai/tools/tool-map.md` with the same routing rule.
- [x] P2-3: **CONSTRAINT-#1 EXCEPTION** — checked `tools/ai/cli-tools.md`; it contains no huge-file `bat --line-range` recommendation (only a generic `bat -n --paging=never` in the edit-start example and an unrelated `preview-file.sh` mention), so no fix was needed — N/A, verified via `rg`.
- [x] P2-4: **CONSTRAINT-#1 EXCEPTION** — clarified `scripts/ai/preview-file.sh` `--help` text for `--max-bytes` and `--force`; did NOT raise the default 64KiB gate (verified unchanged).
- [x] P2-5: Documented reviewer PR-read as already functional via a one-clause addition to the existing Script Access bullet in `reviewer.md`. Did not change PR-read permissions or scripts.

## Acceptance Criteria

- [x] AC-01: `researcher.md` and `reviewer.md` pass `php tools/ai/validate-agent-spec.php`. **Deviation**: `validate-agent-spec.php` takes a `spec.json` argument (`Usage: php tools/ai/validate-agent-spec.php <spec.json> | --self-test`) and is not applicable to `.md` agent templates directly — confirmed via `--help`/no-arg output. Ran `--self-test` (passes: `self-test OK`) and used `php tools/ai/validate-adapter-drift.php --changed-only` as the closest available proxy against the changed template surfaces (`OK: adapter drift validation completed`).
- [x] AC-02: `researcher.md` prefers `scripts/ai/preview-file.sh --range START:END --max-bytes N` over raw `bat --line-range` for large/generated files.
- [x] AC-03: `researcher.md` documents `--force` as exceptional and rationale-required.
- [x] AC-04: `researcher.md` carries compact 3-5 line pre-flight framing.
- [x] AC-05: `reviewer.md` carries compact 3-5 line pre-flight framing covering what to review, main goal, what to look for, why, and out-of-scope boundaries.
- [x] AC-06: The three repo-local tool docs route large/generated previews through `scripts/ai/preview-file.sh --range START:END --max-bytes N` (`tools/ai/cli-tools.md` had no such recommendation to begin with — N/A, confirmed via `rg`).
- [x] AC-07: The three repo-local tool docs no longer recommend raw `bat --line-range` for huge/generated files.
- [x] AC-08: The two touched repo-local tool docs (`file-viewing.md`, `tool-map.md`) document `--force` as exceptional and rationale-required; `cli-tools.md` had no huge-file guidance to annotate.
- [x] AC-09: The default `max_bytes_raw="65536"` remains unchanged in `scripts/ai/preview-file.sh` (verified via `rg` before and after edit).
- [x] AC-10: No code path makes `--range` silently bypass the 64KiB gate (gate check at lines 197-205 runs before `--range` handling at line 229+; unchanged).
- [x] AC-11: `scripts/ai/preview-file.sh` was edited; the edit is help-text only (added a `Notes:` block to `usage()`); no default gate or bypass behaviour changed.
- [x] AC-12: Reviewer PR-read documentation states it is already functional (Script Access bullet) and does not change permissions (frontmatter untouched).
- [~] AC-13: The tool docs pass `bash scripts/ai/check-file-refs.sh` (exit 0; edited docs not listed as orphans). markdownlint/markdownlint-cli2 is **not installed** in this environment — not run.
- [x] AC-14: `scripts/ai/preview-file.sh` passes shellcheck (only pre-existing SC1091 info-level notice on an unedited line, confirmed present before this change via `git stash`); no bats test file exists for `preview-file.sh` (confirmed via `rg -g '*.bats'` — none found).
- [x] AC-15: `composer test:fast` — 896 tests, 2 pre-existing failures (`CliToolsTest::testValidateGeneratedArtifactsExitsZero`, `GeneratedHeaderTest::testValidateGeneratedArtifactsPasses`, both about `docs/ai/repo-required-tools.md` drift from unrelated sibling-added scripts). Confirmed identical failures occur with my 5 files stashed out — pre-existing, not caused by this slice.

## Verification Plan

### Agent template validation

```bash
php tools/ai/validate-agent-spec.php
```

Inspect:

```bash
rg -n "pre-flight|what to review|main goal|what to look for|why|out of scope" \
  packages/ai-universal-rules/templates/core/agents/reviewer.md \
  packages/ai-universal-rules/templates/core/agents/researcher.md
```

Inspect large-file guidance:

```bash
rg -n "preview-file\.sh.*--range|--max-bytes|--force|bat --line-range" \
  packages/ai-universal-rules/templates/core/agents/researcher.md
```

### Repo-local docs validation

Confirm safer routing appears:

```bash
rg -n "preview-file\.sh.*--range" \
  tools/ai/tools/actions/file-viewing.md \
  tools/ai/tools/tool-map.md \
  tools/ai/cli-tools.md
```

Confirm `--max-bytes` and `--force` are documented:

```bash
rg -n -- "--max-bytes|--force" \
  tools/ai/tools/actions/file-viewing.md \
  tools/ai/tools/tool-map.md \
  tools/ai/cli-tools.md
```

Confirm raw `bat --line-range` is not recommended for huge/generated files:

```bash
rg -n "bat --line-range" \
  tools/ai/tools/actions/file-viewing.md \
  tools/ai/tools/tool-map.md \
  tools/ai/cli-tools.md
```

If any `bat --line-range` remains, it must be clearly limited to normal/small files and must not be recommended for huge/generated files.

Run docs checks:

```bash
bash scripts/ai/check-file-refs.sh
markdownlint tools/ai/tools/actions/file-viewing.md tools/ai/tools/tool-map.md tools/ai/cli-tools.md
```

### Preview script validation

Confirm default gate is unchanged:

```bash
rg -n 'max_bytes_raw="65536"' scripts/ai/preview-file.sh
```

Confirm `--range` is not silently made into a bypass:

```bash
rg -n -- "--range|--force|max_bytes_raw|max-bytes" scripts/ai/preview-file.sh
```

If `scripts/ai/preview-file.sh` was edited, run:

```bash
shellcheck scripts/ai/preview-file.sh
```

Run existing bats for preview-file if present:

```bash
rg -n "preview-file" tests scripts -g '*.bats'
```

Then run the matching bats target or test file found by the search.

### PR-read documentation check

```bash
rg -n "gh-pr-context|PR read|pull request|already functional|Bash" \
  packages/ai-universal-rules/templates/core/agents/reviewer.md \
  tools/ai/tools/actions/file-viewing.md \
  tools/ai/tools/tool-map.md \
  tools/ai/cli-tools.md
```

Expected: documentation only; no permission/script change.

### Full verification

```bash
composer test:fast
```

## Risks And Rollback

- Risk: misleading large-file guidance still says `--range` alone is enough.
  Mitigation: require `--range START:END --max-bytes N` for legitimate large previews, with `--force` reserved for exceptional rationale-required bypasses.

- Risk: weakening the 64KiB safety gate by raising the default.
  Mitigation: this plan explicitly forbids raising the default in this slice; AC-09 asserts `max_bytes_raw="65536"` remains unchanged.

- Risk: making `--range` silently bypass the gate.
  Mitigation: AC-10 forbids this; verification inspects `--range`, `--force`, `max_bytes_raw`, and `--max-bytes` logic.

- Risk: recommending `--force` too broadly.
  Mitigation: docs must describe `--force` as exceptional and rationale-required.

- Risk: pre-flight framing becomes verbose and slows agents down.
  Mitigation: limit it to 3-5 lines and require it to guide the task, not become a report.

- Risk: broken doc references after routing changes.
  Mitigation: run `bash scripts/ai/check-file-refs.sh` and markdownlint.

- Risk: scope creep into reviewer PR-read implementation.
  Mitigation: document PR-read as already functional; do not alter permissions or scripts in this slice.

- Rollback: revert the two template edits and the approved repo-local edits. Because the default safety gate remains unchanged, rollback does not alter preview behaviour.

- Success signal: agents and docs consistently route large/generated previews through `preview-file.sh --range START:END --max-bytes N`, `--force` is exceptional, pre-flight framing is present and compact, PR-read remains documentation-only, and tests are green.

## Handoff Notes

- Recommended next step: hand off to the implementer agent using OpenCode command: /implement.
- The four repo-local edits are user-approved Constraint-#1 exceptions; keep them flagged as such.
- Do not raise the `preview-file.sh` default max bytes in this slice.
- Do not make `--range` bypass the safety gate.
- Do not touch logging files or dirty must-not-touch files.
