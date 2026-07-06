# Execution Protocol

Use this as the canonical operating contract for non-trivial AI-assisted planning, editing, review, and verification.

## Prime Directive

Prefer the smallest safe change that is easy for humans to read, maintain, and modify.

## Required Sequence

1. classify task mode
2. inspect `git status --short` and relevant `git diff`
3. confirm approval boundaries for protected actions
4. declare intended scope and apply minimal patch
5. inspect final diff
6. run focused verification and report evidence honestly

## Verification Statuses

- `verified`
- `partially-verified`
- `not-verified`
- `failed-verification`

## Terminal Signal Line

End any non-trivial agent response with exactly one machine-parseable line:

```text
signal: <token>
```

`<token>` is one of:

- `verified` / `partially-verified` / `not-verified` / `failed-verification` — mirrors the verification status above; use when the response involved running or skipping verification.
- `blocked: <UNKNOWN>` — insufficient evidence to proceed; pair with the `Blocked by unknown:` block defined in `docs/ai/project-context.md` section 10 (do not redefine that block here).
- `done` — the response is complete and none of the verification tokens applies (for example a scoping handoff, a persisted plan, or an informational answer).
- `error: <one-line reason>` — an unrecoverable failure not caused by a failed verification check (for example a blocked tool call or permission denial).

## Related Docs

- `docs/ai/source-of-truth.md`
- `docs/ai/approval-boundaries.md`
- `docs/ai/verification-matrix.md`
- `docs/ai/failure-handling.md`
- `docs/ai/session-reentry.md`

## Reference Integrity

When changing a function, method, config key, or any named symbol:

- All references must be updated in the same change.
- Task scope boundaries do not exempt call sites of the changed symbol.
- Search for usages before changing shared interfaces.
- If the change propagates widely, report the affected surface and ask before proceeding.

## Test-First Ordering

For feature work, restate the request as a verifiable goal before coding — turn an imperative task into a testable outcome (for example, "add validation" becomes "write tests for the invalid inputs, then make them pass"), then let that goal drive the loop.

For bug fixes:

1. verify existing tests pass without your changes
2. write a failing regression test that reproduces the bug
3. apply the minimal fix
4. verify all affected tests pass

For test additions alongside code changes:

1. stash implementation changes
2. confirm existing tests you plan to modify pass unchanged
3. apply test changes
4. confirm expected pass or fail state

Ask before changing implementation code if existing tests have not been confirmed passing first.

## Long-Running Commands And Anti-Freeze Discipline

Never sit idle waiting on a subprocess. Apply the following rules to every shell invocation:

1. Pick a per-command timeout up front based on the command class:
   - read-only discovery (`rg`, `fd`, `git status/log/diff`, preview-file): 30s
   - focused unit test or single PHPUnit filter: 60s
   - full PHPUnit run (`composer test`): 180s
   - parallel test run (`composer test:fast`): 90s
   - shell test suite (full bats/script harness for this project): 360s
   - repomix/scc/advisor pack or large generation: 240s
2. If a command produces no output for more than its budget, kill it, report the freeze, and bisect: run smaller scopes (single test class, single test method, single file) until you find the offender.
3. Do not run a command "to see if it eventually finishes" without a stated upper bound. State the budget in your plan and stop when it elapses.
4. For PHP tools that use `proc_open`, prefer command shapes that print incremental progress to stderr; if you must capture huge output, the called code must use file descriptors (`['file', $path, 'w']`) rather than pipes — Windows pipe buffers (~4-64KiB) will deadlock otherwise. See `docs/ai/capabilities/evidence-first-execution/gotchas.md`.
5. Run heavy verification with the fast variant first: `composer test:fast` or a `--filter ClassName`. Only fall back to the serial full run when triaging cross-test ordering bugs.
6. After any unexpected timeout, capture: command, expected budget, elapsed wall time, last bytes of output (if any), and whether the same command works under a smaller scope. Include this in the verification report.

## Subagent Dispatch Stall Discipline

A stuck or aborted subagent (Task-tool) call is a distinct failure mode from a single
command timing out: the whole delegated session can hang or abort with no diagnostic at
all, and some harnesses do not expose the same tools (for example a bash/shell tool) to
every subagent persona regardless of what that persona's own permission frontmatter
declares — treat that gap as a harness-level limitation to report, not something the
subagent itself can work around.

1. If a subagent dispatch is aborted or produces no result, do not re-issue the same
   prompt unchanged — that risks repeating the same stuck state. Either simplify the
   prompt (fewer steps, one command at a time) or fall back to running the work directly
   in the calling session, where per-command timeouts and tool availability are already
   known.
2. Keep subagent-dispatched shell commands simple and single-purpose. Avoid heredocs,
   multi-line `awk`/`sed` scripts, and chained `&&`/`;`/pipe constructs inside a Task
   prompt's suggested commands — these are both harder to bound with a timeout and more
   likely to collide with permission patterns that deny redirect/heredoc shapes
   (`* > *`, `* <<*`) even when the underlying intent is read-only.
3. For investigation or verification work that genuinely needs real command execution,
   prefer doing it directly in the calling session over delegating to a subagent whose
   tool access you have not already confirmed, especially for read-only-style personas.
4. Report a stalled/aborted subagent dispatch the same way as any other failure: name the
   exact prompt and subagent type, what was expected, and what fallback was used instead.

## Verification Performance Aids

- `PARATEST_PROCS=12 bash scripts/ai/run-repo-tests.sh` — all repo tests with parallel-first defaults
- `composer test`         — full serial suite (~60s in this repo; use only when serial ordering matters)
- `composer test:fast`    — paratest with 12 workers (~19-21s in this repo)
- `composer test:profile` — profiles against `phpunit.xml.dist`
- `composer test:slow [N]` — ranks the slowest N tests from the last profile

Use `composer test:profile` followed by `composer test:slow` whenever a previously fast suite gets noticeably slower.
