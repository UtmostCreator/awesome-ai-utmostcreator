# Clarification And Handoff Capability

## Purpose

Decide when to ask instead of assume, and preserve enough context in every handoff
that the next human or agent can continue without re-deriving it. This capability is
the canonical clarification contract; agents and workflow launchers reference it
rather than restating it.

## When To Use

- before starting implementation on any non-trivial or ambiguous task
- before choosing between two or more plausible interpretations of a request
- whenever a task, agent, or session hands off to another agent or to a human

## When To Ask

Ask a clarifying question when at least one of these holds:

- the task's acceptance criteria cannot be inferred from the user request, the
  diff, tests, or `docs/tickets/`
- two or more materially different interpretations would lead to different
  files, contracts, or risk levels
- the request implies a destructive, security, schema, or public-interface change
  without naming the exact target
- a named external project or path is required but not listed in
  `docs/ai/project-context.md` or `docs/ai/project/project-interaction.md`

Do not ask when the answer is cheaply discoverable from the repository (run
`ai-search`/`preview-file` first) or when the ambiguity does not change the
outcome.

## Maximum Question Count

Prefer a single pause over a long back-and-forth. The structured question set
below is the preferred shape for that pause: it may carry the few tightly related
decisions that block the current step, each as its own selectable item. Reserve a
free-text blocking question for the one highest-impact unknown when options cannot
be enumerated. If more decisions remain after the pause, record them as unresolved
questions in the handoff payload (see `docs/ai/handoff-contract.md`) rather than
opening a second round.

## Structured Question Set (Preferred)

When a request is ambiguous, terse ("implement now", "is it correct?"), or bundles
several decisions, do not act on a guess. Generate a small, structured question set
and let the user choose before editing:

- Ask 1-4 questions total, only for decisions that actually change files, contracts,
  scope, or risk. Drop anything cheaply discoverable from the repo (run
  `ai-search`/`preview-file` first).
- Give each question 2-4 concrete, selectable options. Add a short "why it matters"
  and mark the option you recommend with `(recommended)` and one line of reasoning.
- Do not include catch-all options like "Other"; the harness adds a free-text path.
- Wait for the selection before implementing. Restate the chosen answers in one line
  as the confirmed scope, then proceed.

On harnesses that render selectable choices (Copilot, OpenCode, Claude interactive),
emit the set as a choice prompt. Where interactive selection is unavailable, fall
back to the stop-or-assume branch below.

## Stop-Or-Assume Branch

When a clarifying question cannot be asked (for example, unattended or batch
execution):

1. If the ambiguity is low-impact and reversible, state the assumption
   explicitly, proceed, and mark it `unknown`/assumed in the handoff.
2. If the ambiguity is high-impact, irreversible, or touches an approval
   boundary (`docs/ai/approval-boundaries.md`), stay read-only, report the
   blocking unknown, and stop instead of guessing.

## Surfacing Unknowns

Say `unknown` for any claim the repository does not prove. Never fill an unknown
with a plausible-sounding guess presented as fact.

## Multiple Plausible Interpretations

When ambiguity is material (acceptance criteria, scope, or contract would differ),
present the plausible interpretations briefly, name the one you recommend and
why, and let the user pick or correct before implementation continues.

## Simpler-Path Pushback

When a request implies more mechanism than the goal requires (new abstraction
layer, new service, new generated pipeline), name the simpler alternative that
meets the same goal and ask whether the added complexity is actually required
before building it.

## Output Contract

Every clarification-and-handoff interaction produces (or updates) the minimum
preserved payload defined in `docs/ai/handoff-contract.md`: scope, clarified
constraints, assumptions, acceptance criteria, likely files, verification
expectation, unresolved questions, recommended next step, downstream-confirmation
flag, and remaining work.

## Stop Conditions

- more than one high-impact unknown remains after the single allowed question
- the task requires an approval-gated action with no approval on record
- the request's simpler-path pushback was raised and not resolved
