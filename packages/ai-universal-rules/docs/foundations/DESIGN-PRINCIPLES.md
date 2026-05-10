# Design Principles

Use these principles when extending the kit.

## 1. Policy Over Procedure In Always-On Files

Keep always-on instructions short, stable, and broad.

## 2. Capability-First Reusable Workflows

Put deep reusable procedure in capability folders, not in giant global prompts.

## 3. Task Entry Points Stay Narrow

Prompt files and commands should be named entry points for recurring one-off jobs.

## 4. Stage Boundaries Reduce Drift

Use agents and subagents to isolate research, planning, implementation, review, and release posture when that separation helps.

Start with the simplest arrangement that can do the job. Add more staged agents only when a single-agent or single-entry workflow stops being clear, safe, or testable.

## 5. Enforcement Is Explicit

Document what is advisory and what is deterministic.

## 6. Surface Differences Are Real

Document fallbacks instead of pretending every runtime has the same control model.

## 7. Evidence Beats Confidence

Completion claims should point to proof.

## 8. Tool Contracts Matter

Tool names, descriptions, boundaries, and expected outputs are part of the workflow design, not an implementation detail.

## See Also

- `CAPABILITY-MODEL.md` — capability-first reusable workflow principle
- `CONTROL-MODEL.md` — advisory vs deterministic distinction
- `PRECEDENCE.md` — non-overlap and layering rules
- `../workflows/SYSTEM-WORKFLOW.md` — where these principles play out in the lifecycle
