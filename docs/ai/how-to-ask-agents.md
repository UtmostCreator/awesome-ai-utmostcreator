# How To Ask Agents

A short guide for humans working with the AI agents in this repository (Copilot, OpenCode,
Claude). Good prompts are the biggest lever for good, consistent results. This page explains
what to specify, why it matters, what to avoid, and how the agents help by asking before acting.

## The One-Minute Version

Give the agent four things and you will get far better output:

1. **Goal** — what outcome you want, in one sentence.
2. **Done-signal** — how we both know it worked (a passing test, a specific file behavior).
3. **Target** — which repo/path/file the change lands in (especially in multi-repo work).
4. **Mode** — is this read-only research, or a change you want implemented?

If you leave these out, the agent will ask you (see "The Agent Will Ask First" below) rather
than guess — but supplying them up front is faster.

## What To Specify — and Why

| Specify | Why it matters |
| --- | --- |
| One goal per message | Bundled asks ("fix this, also refactor that, and is X correct?") split the agent's attention and lead to partial work. One goal keeps the change bounded and reviewable. |
| A concrete done-signal | "Is it correct?" has no finish line. "The `AccommodationValidationErrorDisplayTest` passes" does. The agent can then verify and prove it. |
| The exact target repo/path | This kit is often used across sibling repos. Naming the path prevents edits landing in the wrong project and triggers the external-access prompt correctly. |
| Read-only vs. change intent | "Review and tell me" is very different from "implement now". Saying which avoids premature edits. |
| Relevant constraints | Reuse existing components, no inline styles, keep it in classes, additive migration only — state these once instead of correcting after. |
| A ticket id or plan path | If the branch matches `[A-Z]+-[0-9]+`, the agent looks it up in `docs/tickets/`. Pointing at a plan file gives instant scope. |

## What To Avoid

- **Terse imperatives with no context**: "implement now", "fix it", "is it green?". They invite
  guessing. The agent will pause and ask, but you lose a round-trip.
- **Several unrelated decisions in one message**: split them, or expect a question set back.
- **Vague success criteria**: prefer a test, a visible behavior, or a named file/line.
- **Asking the agent to read secrets**: do not ask it to open `.env`, keys, or credential files
  to "find a value". For non-security config the agent uses runtime accessors, documented
  defaults, or `*.example` files. For security values (keys, tokens, passwords) it will ask you
  to paste them directly — that is by policy, see
  [security instructions](../../.github/instructions/security.instructions.md).
- **Assuming access is blocked**: agents can still request outside-repo access, fetch web pages,
  and open/read/debug a browser page (for example a local dev URL) when the task needs it. Just
  say so; the runtime will prompt for approval.

## The Agent Will Ask First (Structured Question Set)

When a request is ambiguous, terse, or bundles several decisions, the agent does **not** guess.
It pauses and offers a small **structured question set** — 1-4 questions, each with 2-4
selectable options and a recommended choice. You pick the answers, it restates the confirmed
scope in one line, then it proceeds. This is defined in
[clarification-and-handoff](capabilities/clarification-and-handoff/CAPABILITY.md)
("Structured Question Set").

This is how the repository increases consistency: the same ambiguity produces the same
"stop and confirm" behavior across Copilot, OpenCode, and Claude, instead of one agent guessing
and another asking. Where a harness cannot render selectable choices, the agent states its
assumption, marks it `unknown`, and stops on high-impact ambiguity rather than proceeding blind.

## How To Increase Agent Consistency

- **Point at durable context**: reference `docs/ai/project-context.md`, a plan under
  `docs/tickets/`, or the relevant capability. Agents prefer durable facts over session guesses.
- **Keep slices small**: ask for one bounded, human-testable change at a time. Large asks get
  paused for confirmation once they pass ~6 files.
- **Let it plan**: creating a todo/plan list is pre-approved and frictionless in each harness, so
  asking the agent to "plan first, then implement the first slice" costs nothing and improves
  focus.
- **Answer the question set**: selecting options instead of re-typing free-form keeps scope tight.
- **State constraints once, up front**: reuse rules, style rules, and no-go paths belong in the
  first message, not as later corrections.

## Related

- [Clarification and handoff capability](capabilities/clarification-and-handoff/CAPABILITY.md)
- [Execution protocol](execution-protocol.md)
- [AI guardrails](AI-GUARDRAILS.md)
- [Agent routing guide](agents.md)
