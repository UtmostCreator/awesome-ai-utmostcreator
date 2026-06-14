# Hooks

Installed hooks route policy decisions through `scripts/ai/pre-tool-use.sh` and
runtime.

## Policy Hook

`scripts/ai/pre-tool-use.sh` is the canonical pre-execution policy gate. It is
used to classify commands, detect risky operations, and decide whether a command
is allowed, denied, or requires approval.

## Evidence Hook

`scripts/ai/post-tool-use.sh` is the canonical post-execution evidence writer. It
records command outcomes and supports later review or session reentry.

## Runtime Fallback

Some runtimes cannot automatically wire repository hooks. In that case, preserve
the same boundary manually: classify risky commands before execution and report
the verification evidence after execution.

## Local Evidence

Use `.ai-logs/` as the local evidence root. Do not commit local evidence logs
unless a task explicitly asks for a durable artifact.
