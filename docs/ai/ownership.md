# Ownership

Ownership tells an agent which file, team surface, or source of truth controls a
change. When ownership is unclear, do not guess.

## Source Order

Use active repository evidence before older planning notes.

1. User request and current ticket scope.
2. Current git status and diff.
3. Source files, tests, schemas, and runtime configuration.
4. Canonical docs under `docs/ai/`.
5. Adapter files such as `AGENTS.md`, `.github/**`, and `.opencode/**`.
6. Historical or archived planning docs.

## Unknown Ownership

When ownership is unknown, stop at a bounded read-only investigation and report
`unknown` rather than guessing. Include the files and commands checked.

## Generated Surfaces

If a file is generated, edit the template or generator when known. Do not hand
edit generated output unless the task explicitly approves that regeneration or
there is no generator evidence.
