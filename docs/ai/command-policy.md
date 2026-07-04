# Command Policy

Command policy groups terminal actions by risk so agents can choose safe defaults
and know when to stop.

## Risk Groups

- Read-only probes inspect files, git state, or repository metadata.
- Verification commands run tests, validators, or static checks.
- Mutation-adjacent commands write generated docs, update indexes, or move files.
- Destructive commands delete, reset, force-push, deploy, or alter secrets.

## Default Handling

Read-only probes are allowed when scoped. Verification is allowed when it is the
smallest relevant proof. Mutation-adjacent commands ask when generated output or
user files may change. Destructive commands are denied unless explicitly
approved.

## Command Reporting

Report exact commands and outcomes. If a command times out or fails, state the
budget, exit status when known, and the next safe diagnostic step.

## Portable Policy Compilation (Design Decision, Not Yet Implemented)

Current state: `docs/ai/command-policy.tiers.yaml` plus `docs/ai/script-registry.json`
are the canonical policy source. `tools/ai/compile-command-policy.php` already
compiles them into `.github/hooks/scripts/command-policy.compiled.sh` (Copilot's
hook enforcement), and `php tools/ai/validate-command-policy.php` checks that
compiled output against the source.

Gap this decision addresses: `packages/ai-universal-rules/templates/core/opencode.json`'s
`permission` block is hand-authored separately from the same intent (its
`read`/`edit`/`bash` sub-blocks use a different shape than the tiers file's
`tier0`/`tier1`/... `allow`/`ask`/`deny` lists), with no compiled-output check
between the two. This
is exactly the hand-authored per-runtime policy drift risk field evidence
surfaced (F-1, L-2): two humans maintaining "the same" policy in two shapes will
eventually disagree without either surface detecting it. Claude has no
hook/permission enforcement surface at all (per `docs/ai/integration-matrix.md`'s
runtime matrix), so there is nothing to compile to there — that asymmetry is
already correctly documented, not a gap.

Decision: extend the existing compiler, do not build a second one. A future
slice should add an OpenCode-shaped output target to
`tools/ai/compile-command-policy.php` (or a sibling compile step reading the same
tiers file) that emits the `permission` block content rendered into
`opencode.jsonc`, plus a drift check in `tools/ai/validate-command-policy.php`
comparing the compiled shape against the committed template block.

This is a design decision only. Implementing the compiler extension needs
separate approval: it touches a config file that is a live tool-execution safety
gate, so a compile bug has real blast radius, and is out of scope for this
program's docs-and-templates slices.
