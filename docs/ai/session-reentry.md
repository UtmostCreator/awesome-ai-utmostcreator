# Session Reentry

Session reentry is the process for safely resuming work after a handoff, context
loss, or interruption.

## First Checks

Before resuming work, inspect `git status --short`, current diff, relevant docs,
and previous verification evidence. Do not assume the worktree is unchanged.

## Scope Recovery

Recover scope from the user request, the active ticket under `docs/tickets/`, and
the latest commits. If these conflict, trust active repository evidence and
report the conflict.

## Dirty Worktree Protection

Identify pre-existing user changes before editing. Do not stage, overwrite, or
move unrelated files unless the user explicitly approves that exact action.

## Verification Recovery

Separate verification already run from verification only recommended. If a prior
run failed, rerun the smallest relevant check after the fix and report the new
evidence.
