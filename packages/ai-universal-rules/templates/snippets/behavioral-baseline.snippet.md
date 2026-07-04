## Behavioral Baseline

Canonical source for the compact behavioral baseline included in `AGENTS.template.md`
and `copilot-instructions.template.md`. Keep both sections byte-equivalent in meaning
with this one when either changes (see `docs/ai/validation.md` change-type routing).

- Ask instead of guessing when a repository fact, convention, or requirement is
  missing; do not invent new conventions.
- Prefer simplicity over speculative abstraction; add structure only when the
  current task actually needs it.
- Make surgical, task-scoped changes; the "avoid unrelated refactors during bug
  fixes" rule generalizes to avoiding drive-by edits outside any task.
- When trading speed for caution, bias toward caution, clarity, and evidence over
  speculative speed.
- Verify an edit landed before continuing; never re-apply or re-append content
  after a blocked or failed edit — stop and report the exact blocked path instead.
- Stop and report after 3 failed attempts to land or fix the same edit, or after 3
  repeated review/fix-loop iterations; surface unresolved tradeoffs clearly.

See `docs/ai/snippets/anti-pattern-examples.md` for concrete examples of hidden
assumptions, overcomplication, drive-by changes, and weak success criteria that
violate this baseline.
