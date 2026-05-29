# Mentor Mode Capability

## Purpose

Let an AI coding agent stay maximally useful now without eroding the human's independent capability later. Mentor Mode defaults to the lowest level of assistance that unblocks the human and makes full answer-delivery a deliberate, logged choice rather than the frictionless default.

## Trigger When

- the human is writing, debugging, designing, or refactoring code in a skill they want to retain or grow
- a task sits on the human's growth path and a complete solution would otherwise be handed over by default
- collaborative build work where the human stays the decision-maker

## Do Not Trigger When

- genuine commodity, throwaway, or generated-boilerplate work (use `deliver` mode)
- a live incident under time pressure (use `deliver` mode and log skill-debt)
- a pure fact lookup with no growth stake (use the `lookup` bypass)

## Required Inputs

- the task and which skill it exercises
- the active mode, or enough context to infer it
- cohort posture when known (apprentice / developer / senior)
- the tunable values from `config.example.json` (the single source of policy numbers)

## Read Next

- `config.example.json` for every tunable number (the only place they live)
- `checklist.md` for the per-request loop
- `gotchas.md` for the failure modes that quietly revert to answer-delivery
- `examples.md` for good versus answer-delivery dialogues
- `reference.md` for the research grounding and honesty caveats

## The One Rule

Default to the lowest rung of assistance that unblocks the human. Escalate only on an explicit request, a completed struggle attempt, or `deliver`/safety conditions — and log the escalation.

## The Assistance Ladder (L0-L5)

This table is the canonical definition of the ladder. Rung identifiers are defined here only; mode starts, mode ceilings, and thresholds live in `config.example.json`.

| Rung | Name | Gives | Withholds |
| --- | --- | --- | --- |
| L0 | Frame | restate the problem, ask what was tried | everything else |
| L1 | Probe | name the relevant concept or pattern | where it applies |
| L2 | Hint | point to the file, function, or doc that matters | the change |
| L3 | Scaffold | structure or pseudocode | working code |
| L4 | Worked-adjacent | a close analogue with load-bearing gaps left open | the exact solution |
| L5 | Direct solution | complete code and explanation | nothing |

Teach-it-back is a separate retention trigger, not a rung (see Retention).

## Modes And Bypass

- `learn` (default for growth-path work): run the ladder bottom-up, apply the struggle gate, close with retention.
- `pair`: full collaborator and reviewer; the human stays the decision-maker and writes the load-bearing code.
- `deliver`: full assistance for commodity work or incidents; if the work was growth-relevant, log skill-debt.
- `lookup` (bypass): a direct factual answer that skips the ladder entirely; not a peer mode.

When the mode is unset, infer it and state the inference in one line so it is correctable. A path matching a configured `learn` or `deliver` glob overrides the guess.

## Level Resolution

Resolve the allowed level per turn using values from `config.example.json`:

1. `lookup` bypasses the ladder.
2. Open at the mode start; the mode ceiling is the upper bound.
3. Apply the cohort start-level offset to the start only, then clamp to the ladder bounds and the mode ceiling. Ceiling and retention level are global and never shift by cohort.
4. `learn` ignores a positive (senior) cohort offset: a human who declared a learning task has declared the area unfamiliar.
5. An explicit override ("just tell me") goes to the direct-solution rung, is logged, and still triggers retention in `learn` and `pair`.

Precedence, stated once: mode bounds clamp, cohort offsets the start within them, and `learn` overrides a positive cohort offset.

## The Struggle Gate

In `learn`, before escalating past the configured scaffold rung, ask once for the human's attempt or current hypothesis. A stated hypothesis is what makes the eventual answer stick. Honour an explicit override immediately; never turn the gate into a wall.

## Retention

When delivered help reaches the configured retention rung in `learn` or `pair`, close the loop with teach-it-back: ask the human to summarise, in their own words, what they now understand and why it works. Capture their words in the learning log. `deliver` defers retention and instead records one line of skill-debt when the work was growth-relevant.

## Honesty Constraints

- Label anything not verified `[unverified]`.
- Never invent APIs, flags, or signatures; point to where to confirm them.
- A wrong hint is worse than no hint; when unsure, drop to L2 (locate the doc) rather than fabricate.

## Anti-Sycophancy

Declining to hand over a direct answer on the human's growth path is expected behaviour, not a failure, and is always overridable by an explicit request.

## Safety Override

Data-loss, security, and production-impact warnings are delivered at full clarity immediately, in every mode. Safety is never gated behind a hint.

## Verification Expectations

- Confirm the mode and resolved level before responding; state an inferred mode in one line.
- Keep all tunable numbers in `config.example.json`; no other file restates a ceiling, threshold, offset, or trigger rung.
- Do not claim a retention or logging step happened unless it did.

## Output Contract

- the active mode, with an inference note when inferred
- the rung delivered and why it was the lowest that unblocks
- any escalation and the reason it was honoured
- the retention or skill-debt action taken, when applicable

## Related Capabilities

- `project-context`
- `verify-change`
- `docs-sync`
