# Behavioral Baseline — Anti-Pattern Examples

Referenced from the Behavioral Baseline in `AGENTS.md` and
`.github/copilot-instructions.md`. Each anti-pattern pairs a real failure shape with
the baseline rule it violates and the safer move — concrete signal, not abstract
advice.

## Hidden Assumptions

**Anti-pattern:** An agent asked to "add caching" picks Redis without checking
whether the repo already has a cache abstraction, silently assuming the stack.

**Violates:** "Ask instead of guessing when a repository fact, convention, or
requirement is missing; do not invent new conventions."

**Safer move:** Search for an existing cache adapter or config first; if none
exists and the choice is consequential, ask, or state the assumption explicitly
and mark it `unknown` in the handoff.

## Overcomplication

**Anti-pattern:** A request to "validate one config field" grows into a new
plugin system, a generic rule engine, and a config-migration layer nobody asked
for.

**Violates:** "Prefer simplicity over speculative abstraction; add structure
only when the current task actually needs it."

**Safer move:** Implement the one validation check. Name the simpler alternative
out loud and ask whether the added mechanism is actually required before
building it (see the clarification-and-handoff capability's simpler-path
pushback).

## Drive-By Changes

**Anti-pattern:** While fixing an off-by-one bug, the agent also reformats an
unrelated file, renames a variable it finds ugly, and "improves" a comment three
functions away.

**Violates:** "Make surgical, task-scoped changes; avoid drive-by edits outside
any task, including during bug fixes."

**Safer move:** Fix only what the task or ticket scopes. If an adjacent issue is
found, name it in the handoff as a follow-up instead of touching it.

## Weak Success Criteria

**Anti-pattern:** A handoff says "tests pass" with no named test, or "works
correctly" with no observable behavior stated, so the next reviewer cannot tell
what was actually proven.

**Violates:** the handoff contract's requirement that acceptance criteria and
verification expectation be stated, not implied (`docs/ai/handoff-contract.md`).

**Safer move:** Name the exact command or assertion that proves the change, and
report `Not run: <command> — <reason>` for anything recommended but not executed.
